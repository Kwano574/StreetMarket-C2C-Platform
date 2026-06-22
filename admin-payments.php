<?php
// starting php session
session_start();

// preventing mysqli fatal errors from breaking the page
mysqli_report(MYSQLI_REPORT_OFF);

// connecting page to database for payment management
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// connecting notification functions
if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

// allowing super admin and payment manager to manage payments
requireAdminRole(["super_admin","payment_manager"]);

// page variables
$message = "";
$message_type = "success";

// checking if database table exists
function adminPaymentsTableExists($conn, $table){

    $table = mysqli_real_escape_string($conn, $table);

    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// checking if database column exists
function adminPaymentsColumnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// sending notifications safely
function sendAdminPaymentNotification($conn, $user_id, $notification_title, $notification_message, $link){

    $user_id = intval($user_id);

    if($user_id <= 0){
        return false;
    }

    // checking if notifications table exists
    if(!adminPaymentsTableExists($conn, "notifications")){
        return false;
    }

    // checking correct title column name because some databases use tittle instead of title
    $title_column = "";

    if(adminPaymentsColumnExists($conn, "notifications", "tittle")){
        $title_column = "tittle";
    }elseif(adminPaymentsColumnExists($conn, "notifications", "title")){
        $title_column = "title";
    }else{
        return false;
    }

    $notification_title = mysqli_real_escape_string($conn, $notification_title);
    $notification_message = mysqli_real_escape_string($conn, $notification_message);
    $link = mysqli_real_escape_string($conn, $link);

    // inserting notification
    $insert_notification = "
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES('$user_id', '$notification_title', '$notification_message', '$link', 'No', NOW())
    ";

    return mysqli_query($conn, $insert_notification);
}

// displaying success message from previous action
if(isset($_SESSION['success_message'])){
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}

// displaying error message from previous action
if(isset($_SESSION['error_message'])){
    $message = $_SESSION['error_message'];
    $message_type = "error";
    unset($_SESSION['error_message']);
}

// refunding full payment group
if(isset($_GET['refund_group'])){

    $reference_order_id = intval($_GET['refund_group']);

    // getting reference order before refund
    $reference_stmt = $conn->prepare("
    SELECT *
    FROM orders
    WHERE order_id=?
    AND payment_status='paid'
    AND payment_method='card'
    AND status!='completed'
    AND status!='cancelled'
    AND delivery_status!='delivered'
    AND delivery_status!='cancelled'
    LIMIT 1
    ");

    $reference_stmt->bind_param("i", $reference_order_id);
    $reference_stmt->execute();
    $reference_result = $reference_stmt->get_result();

    if($reference_result && $reference_result->num_rows > 0){

        $reference_order = $reference_result->fetch_assoc();

        $buyer_id = intval($reference_order['buyer_id']);
        $seller_id = intval($reference_order['seller_id']);
        $order_group_id = trim($reference_order['order_group_id']);

        if(empty($order_group_id)){

            $_SESSION['error_message'] = "Payment group reference is missing.";
            header("Location: admin-payments.php");
            exit();

        }

        // getting all paid card orders inside this group
        $group_stmt = $conn->prepare("
        SELECT *
        FROM orders
        WHERE buyer_id=?
        AND seller_id=?
        AND order_group_id=?
        AND payment_status='paid'
        AND payment_method='card'
        AND status!='completed'
        AND status!='cancelled'
        AND delivery_status!='delivered'
        AND delivery_status!='cancelled'
        ");

        $group_stmt->bind_param("iis", $buyer_id, $seller_id, $order_group_id);
        $group_stmt->execute();
        $group_result = $group_stmt->get_result();

        if($group_result && $group_result->num_rows > 0){

            // starting database transaction
            mysqli_begin_transaction($conn);

            $refund_ok = true;
            $refunded_item_count = 0;

            while($order = $group_result->fetch_assoc()){

                $product_id = intval($order['product_id']);
                $quantity = intval($order['quantity']);

                if($quantity < 1){
                    $quantity = 1;
                }

                // restoring product stock after refund
                $restore_stmt = $conn->prepare("
                UPDATE product
                SET quantity = quantity + ?, status='available'
                WHERE product_id=?
                ");

                $restore_stmt->bind_param("ii", $quantity, $product_id);

                if(!$restore_stmt->execute()){
                    $refund_ok = false;
                    break;
                }

                $refunded_item_count++;
            }

            if($refund_ok){

                // updating payment and order status for all paid card orders in this group
                $refund_stmt = $conn->prepare("
                UPDATE orders
                SET payment_status='refunded',
                    status='cancelled',
                    cancelled_by='admin',
                    delivery_status='cancelled'
                WHERE buyer_id=?
                AND seller_id=?
                AND order_group_id=?
                AND payment_status='paid'
                AND payment_method='card'
                AND status!='completed'
                AND status!='cancelled'
                AND delivery_status!='delivered'
                AND delivery_status!='cancelled'
                ");

                $refund_stmt->bind_param("iis", $buyer_id, $seller_id, $order_group_id);

                if(!$refund_stmt->execute()){
                    $refund_ok = false;
                }
            }

            if($refund_ok){

                mysqli_commit($conn);

                // notifying buyer safely
                sendAdminPaymentNotification(
                    $conn,
                    $buyer_id,
                    "Payment Refunded",
                    "Your card payment for order group #".$order_group_id." was marked as refunded by admin. Items refunded: ".$refunded_item_count.".",
                    "orders.php"
                );

                // notifying seller safely
                sendAdminPaymentNotification(
                    $conn,
                    $seller_id,
                    "Payment Refunded",
                    "Admin refunded order group #".$order_group_id.". Items refunded: ".$refunded_item_count.". Product stock has been restored.",
                    "manage-deliveries.php"
                );

                $_SESSION['success_message'] = "Payment group refunded successfully and product stock restored.";

            }else{

                mysqli_rollback($conn);

                $_SESSION['error_message'] = "Refund failed. Product stock was not fully restored.";

            }

        }else{

            $_SESSION['error_message'] = "This payment group cannot be refunded. It may already be delivered, completed, cancelled or refunded.";

        }

    }else{

        $_SESSION['error_message'] = "This payment cannot be refunded because it is not a paid card payment or it has already been delivered, completed, cancelled or refunded.";

    }

    header("Location: admin-payments.php");
    exit();
}

// getting search and filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$payment_filter = isset($_GET['payment_filter']) ? trim($_GET['payment_filter']) : "";
$method_filter = isset($_GET['method_filter']) ? trim($_GET['method_filter']) : "";

// getting payment statistics
$total_payments_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE order_group_id IS NOT NULL
AND order_group_id != ''
");

$total_payments_data = mysqli_fetch_assoc($total_payments_result);
$total_payments = $total_payments_data ? intval($total_payments_data['total']) : 0;

$paid_payments_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE payment_status='paid'
AND order_group_id IS NOT NULL
AND order_group_id != ''
");

$paid_payments_data = mysqli_fetch_assoc($paid_payments_result);
$paid_payments = $paid_payments_data ? intval($paid_payments_data['total']) : 0;

$pending_payments_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE payment_status='pending'
AND order_group_id IS NOT NULL
AND order_group_id != ''
");

$pending_payments_data = mysqli_fetch_assoc($pending_payments_result);
$pending_payments = $pending_payments_data ? intval($pending_payments_data['total']) : 0;

$refunded_payments_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE payment_status='refunded'
AND order_group_id IS NOT NULL
AND order_group_id != ''
");

$refunded_payments_data = mysqli_fetch_assoc($refunded_payments_result);
$refunded_payments = $refunded_payments_data ? intval($refunded_payments_data['total']) : 0;

// getting paid order value
$total_paid_value_result = mysqli_query($conn, "
SELECT SUM(total_amount) AS total
FROM orders
WHERE payment_status='paid'
AND status!='cancelled'
AND order_group_id IS NOT NULL
AND order_group_id != ''
");

$total_paid_value_data = mysqli_fetch_assoc($total_paid_value_result);
$total_paid_value = $total_paid_value_data && $total_paid_value_data['total'] != NULL ? floatval($total_paid_value_data['total']) : 0;

// building grouped payment query
$payments_query = "
SELECT
MIN(orders.order_id) AS reference_order_id,
orders.order_group_id,
orders.buyer_id,
orders.seller_id,
MIN(orders.order_date) AS order_date,
orders.payment_method,
orders.payment_status,
orders.status,
orders.delivery_status,
SUM(orders.total_amount) AS group_total,
COUNT(orders.order_id) AS item_count,
buyer.full_name AS buyer_name,
seller.full_name AS seller_name,
seller.business_name AS seller_business_name
FROM orders
INNER JOIN users AS buyer ON orders.buyer_id = buyer.user_id
INNER JOIN users AS seller ON orders.seller_id = seller.user_id
WHERE orders.order_group_id IS NOT NULL
AND orders.order_group_id != ''
";

$params = [];
$types = "";

if($search != ""){
    $search_value = "%" . $search . "%";
    $payments_query .= " AND (orders.order_group_id LIKE ? OR buyer.full_name LIKE ? OR seller.full_name LIKE ? OR seller.business_name LIKE ?)";
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "ssss";
}

if($payment_filter != ""){
    $payments_query .= " AND orders.payment_status=?";
    $params[] = $payment_filter;
    $types .= "s";
}

if($method_filter != ""){
    $payments_query .= " AND orders.payment_method=?";
    $params[] = $method_filter;
    $types .= "s";
}

$payments_query .= "
GROUP BY
orders.order_group_id,
orders.buyer_id,
orders.seller_id,
orders.payment_method,
orders.payment_status,
orders.status,
orders.delivery_status,
buyer.full_name,
seller.full_name,
seller.business_name
ORDER BY order_date DESC
";

$payments_stmt = $conn->prepare($payments_query);

if(count($params) > 0){
    $payments_stmt->bind_param($types, ...$params);
}

$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Payments | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.success-message{
background:#dcfce7;
color:#166534;
border-left:5px solid #16a34a;
padding:15px;
border-radius:10px;
margin-bottom:20px;
font-weight:bold;
}

.error-message{
background:#fee2e2;
color:#991b1b;
border-left:5px solid #dc2626;
padding:15px;
border-radius:10px;
margin-bottom:20px;
font-weight:bold;
}

.stats-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
gap:15px;
margin-bottom:25px;
}

.stat-card{
background:white;
padding:20px;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
text-align:center;
}

.stat-card h3{
font-size:30px;
margin-bottom:8px;
color:#111;
}

.filter-box{
background:white;
padding:20px;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
margin-bottom:25px;
}

.filter-form{
display:grid;
grid-template-columns:2fr 1fr 1fr auto auto;
gap:12px;
align-items:end;
}

.filter-form input,
.filter-form select{
padding:12px;
border:1px solid #ddd;
border-radius:8px;
width:100%;
}

.filter-form button,
.clear-btn{
padding:12px 16px;
border:none;
border-radius:8px;
background:#111;
color:white;
text-decoration:none;
font-weight:bold;
cursor:pointer;
text-align:center;
}

.clear-btn{
background:#555;
}

.table-wrapper{
overflow-x:auto;
background:white;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

table{
width:100%;
border-collapse:collapse;
background:#fff;
min-width:1000px;
}

th,
td{
padding:14px;
border-bottom:1px solid #ddd;
text-align:left;
vertical-align:top;
}

th{
background:#111;
color:white;
}

.refund-btn,
.track-btn{
padding:9px 14px;
color:#fff;
text-decoration:none;
border-radius:8px;
font-weight:bold;
display:inline-block;
margin:3px 0;
}

.refund-btn{
background:#c62828;
}

.track-btn{
background:#111;
}

.status-paid{
color:green;
font-weight:bold;
}

.status-refunded{
color:#c62828;
font-weight:bold;
}

.status-pending{
color:orange;
font-weight:bold;
}

.status-cancelled{
color:#991b1b;
font-weight:bold;
}

@media(max-width:900px){

.filter-form{
grid-template-columns:1fr;
}

table{
display:block;
overflow-x:auto;
white-space:nowrap;
}

}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img src="images/logo.png" alt="Logo">

<h1>Admin Payments</h1>

</div>

<nav>

<a href="Admin-dashboard.php">Dashboard</a>
<a href="admin-orders.php">Orders</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-notifications.php">Notifications</a>
<a href="admin-logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<h2>Manage Payments</h2>

<p>Monitor paid, pending and refunded marketplace payment groups.</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<!-- Payment statistics cards -->
<div class="stats-grid">

<div class="stat-card">

<h3><?php echo $total_payments; ?></h3>

<p>Total Payment Groups</p>

</div>

<div class="stat-card">

<h3><?php echo $paid_payments; ?></h3>

<p>Paid Groups</p>

</div>

<div class="stat-card">

<h3><?php echo $pending_payments; ?></h3>

<p>Pending Groups</p>

</div>

<div class="stat-card">

<h3><?php echo $refunded_payments; ?></h3>

<p>Refunded Groups</p>

</div>

<div class="stat-card">

<h3>R<?php echo number_format($total_paid_value, 2); ?></h3>

<p>Paid Order Value</p>

</div>

</div>

<!-- Search and filter form -->
<div class="filter-box">

<form method="GET" class="filter-form">

<div>

<label>Search Payment</label>

<input type="text" name="search" placeholder="Search by group ID, buyer or seller" value="<?php echo htmlspecialchars($search); ?>">

</div>

<div>

<label>Payment Status</label>

<select name="payment_filter">

<option value="">All Statuses</option>

<option value="paid" <?php if($payment_filter == "paid"){ echo "selected"; } ?>>Paid</option>

<option value="pending" <?php if($payment_filter == "pending"){ echo "selected"; } ?>>Pending</option>

<option value="refunded" <?php if($payment_filter == "refunded"){ echo "selected"; } ?>>Refunded</option>
<option value="cancelled" <?php if($payment_filter == "cancelled"){ echo "selected"; } ?>>Cancelled</option>

</select>

</div>

<div>

<label>Payment Method</label>

<select name="method_filter">

<option value="">All Methods</option>

<option value="cash" <?php if($method_filter == "cash"){ echo "selected"; } ?>>Cash</option>

<option value="card" <?php if($method_filter == "card"){ echo "selected"; } ?>>Card</option>

</select>

</div>

<button type="submit">Filter</button>

<a href="admin-payments.php" class="clear-btn">Clear</a>

</form>

</div>

<div class="table-wrapper">

<table>

<tr>
<th>Payment Group</th>
<th>Buyer</th>
<th>Seller</th>
<th>Method</th>
<th>Items</th>
<th>Amount</th>
<th>Payment Status</th>
<th>Order Status</th>
<th>Delivery Status</th>
<th>Action</th>
</tr>

<?php if($payments_result && $payments_result->num_rows > 0){ ?>

<?php while($payment = $payments_result->fetch_assoc()){ ?>

<?php

$reference_order_id = intval($payment['reference_order_id']);
$order_group_id = !empty($payment['order_group_id']) ? $payment['order_group_id'] : "SM".$reference_order_id;
$seller_display = !empty($payment['seller_business_name']) ? $payment['seller_business_name'] : $payment['seller_name'];

?>

<tr>

<td>

#<?php echo htmlspecialchars($order_group_id); ?>

<br>

<small><?php echo date("d M Y H:i", strtotime($payment['order_date'])); ?></small>

</td>

<td><?php echo htmlspecialchars($payment['buyer_name']); ?></td>

<td><?php echo htmlspecialchars($seller_display); ?></td>

<td><?php echo ucwords($payment['payment_method']); ?></td>

<td><?php echo intval($payment['item_count']); ?> product(s)</td>

<td>R<?php echo number_format($payment['group_total'], 2); ?></td>

<td>

<?php if($payment['payment_status'] == "paid"){ ?>

<span class="status-paid">Paid</span>

<?php }elseif($payment['payment_status'] == "refunded"){ ?>

<span class="status-refunded">Refunded</span>

<?php }elseif($payment['payment_status'] == "cancelled"){ ?>

<span class="status-cancelled">Cancelled</span>

<?php }else{ ?>

<span class="status-pending">Pending</span>

<?php } ?>

</td>

<td><?php echo ucfirst($payment['status']); ?></td>

<td><?php echo ucwords(str_replace("_", " ", $payment['delivery_status'])); ?></td>

<td>

<a href="order-tracking.php?id=<?php echo $reference_order_id; ?>" class="track-btn">Track</a>

<?php if(
    $payment['payment_status'] == "paid" &&
    $payment['payment_method'] == "card" &&
    $payment['status'] != "completed" &&
    $payment['status'] != "cancelled" &&
    $payment['delivery_status'] != "delivered" &&
    $payment['delivery_status'] != "cancelled"
){ ?>

<a href="admin-payments.php?refund_group=<?php echo $reference_order_id; ?>" class="refund-btn" onclick="return confirm('Mark this full card payment group as refunded and restore product stock?');">Refund Group</a>

<?php }else{ ?>

<br>No Refund Action

<?php } ?>

</td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>

<td colspan="10">No payments found.</td>

</tr>

<?php } ?>

</table>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

</body>

</html>