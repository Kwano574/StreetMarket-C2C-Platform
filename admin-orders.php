<?php
// starting php session
session_start();

// preventing mysqli fatal errors from breaking the page
mysqli_report(MYSQLI_REPORT_OFF);

// connecting page to database for admin order management
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// connecting notification functions
if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

// allowing super admin and order manager to manage orders
requireAdminRole(["super_admin","order_manager"]);

// page variables
$message = "";
$message_type = "success";

// checking if database table exists
function adminOrdersTableExists($conn, $table){

    $table = mysqli_real_escape_string($conn, $table);

    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// checking if database column exists
function adminOrdersColumnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// sending notifications safely
function sendAdminOrderNotification($conn, $user_id, $notification_title, $notification_message, $link){

    $user_id = intval($user_id);

    if($user_id <= 0){
        return false;
    }

    // checking if notifications table exists
    if(!adminOrdersTableExists($conn, "notifications")){
        return false;
    }

    // checking correct title column name because some databases use tittle instead of title
    $title_column = "";

    if(adminOrdersColumnExists($conn, "notifications", "tittle")){
        $title_column = "tittle";
    }elseif(adminOrdersColumnExists($conn, "notifications", "title")){
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

// cancelling full order group
if(isset($_GET['cancel_group'])){

    $reference_order_id = intval($_GET['cancel_group']);

    // getting reference order before cancelling group
    $reference_stmt = $conn->prepare("
    SELECT *
    FROM orders
    WHERE order_id=?
    AND status='pending'
    AND delivery_status='processing'
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
        $payment_method = strtolower(trim($reference_order['payment_method']));

        // setting correct payment status when admin cancels order
        if($payment_method == "card"){
            $new_payment_status = "refunded";
            $buyer_cancel_message = "Your order group #".$order_group_id." was cancelled by admin and refunded.";
        }else{
            $new_payment_status = "cancelled";
            $buyer_cancel_message = "Your order group #".$order_group_id." was cancelled by admin. No refund was needed because this was a cash payment.";
        }

        if(empty($order_group_id)){

            $_SESSION['error_message'] = "Order group reference is missing.";
            header("Location: admin-orders.php");
            exit();

        }

        // getting all orders inside this group
        $group_stmt = $conn->prepare("
        SELECT orders.*, product.product_name
        FROM orders
        INNER JOIN product ON orders.product_id = product.product_id
        WHERE orders.buyer_id=?
        AND orders.seller_id=?
        AND orders.order_group_id=?
        AND orders.status='pending'
        AND orders.delivery_status='processing'
        ");

        $group_stmt->bind_param("iis", $buyer_id, $seller_id, $order_group_id);
        $group_stmt->execute();
        $group_result = $group_stmt->get_result();

        if($group_result && $group_result->num_rows > 0){

            // starting database transaction
            mysqli_begin_transaction($conn);

            $cancel_ok = true;
            $cancelled_item_count = 0;

            while($order = $group_result->fetch_assoc()){

                $product_id = intval($order['product_id']);
                $quantity = intval($order['quantity']);

                if($quantity < 1){
                    $quantity = 1;
                }

                // restoring product stock
                $restore_stmt = $conn->prepare("
                UPDATE product
                SET quantity = quantity + ?, status='available'
                WHERE product_id=?
                ");

                $restore_stmt->bind_param("ii", $quantity, $product_id);

                if(!$restore_stmt->execute()){
                    $cancel_ok = false;
                    break;
                }

                $cancelled_item_count++;
            }

            if($cancel_ok){

                // cancelling all rows in this order group
                $cancel_stmt = $conn->prepare("
                UPDATE orders
                SET status='cancelled',
                    payment_status=?,
                    cancelled_by='admin',
                    delivery_status='cancelled'
                WHERE buyer_id=?
                AND seller_id=?
                AND order_group_id=?
                AND status='pending'
                AND delivery_status='processing'
                ");

                $cancel_stmt->bind_param("siis", $new_payment_status, $buyer_id, $seller_id, $order_group_id);

                if(!$cancel_stmt->execute()){
                    $cancel_ok = false;
                }
            }

            if($cancel_ok){

                mysqli_commit($conn);

                // notifying buyer safely
                sendAdminOrderNotification(
                    $conn,
                    $buyer_id,
                    "Order Cancelled",
                    $buyer_cancel_message,
                    "orders.php"
                );

                // notifying seller safely
                sendAdminOrderNotification(
                    $conn,
                    $seller_id,
                    "Order Cancelled",
                    "Admin cancelled order group #".$order_group_id.". Items cancelled: ".$cancelled_item_count.". Product stock has been restored.",
                    "manage-deliveries.php"
                );

                $_SESSION['success_message'] = "Order group cancelled successfully and product stock restored.";

            }else{

                mysqli_rollback($conn);

                $_SESSION['error_message'] = "Failed to cancel order group.";

            }

        }else{

            $_SESSION['error_message'] = "Order group cannot be cancelled. It may already be processed, completed, delivered or cancelled.";

        }

    }else{

        $_SESSION['error_message'] = "Order cannot be cancelled. It may already be processed, completed, delivered or cancelled.";

    }

    header("Location: admin-orders.php");
    exit();
}

// getting search and filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : "";
$delivery_filter = isset($_GET['delivery_filter']) ? trim($_GET['delivery_filter']) : "";

// getting order statistics
$total_orders_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE order_group_id IS NOT NULL
AND order_group_id != ''
");

$total_orders_data = mysqli_fetch_assoc($total_orders_result);
$total_orders = $total_orders_data ? intval($total_orders_data['total']) : 0;

$pending_orders_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE status='pending'
AND order_group_id IS NOT NULL
AND order_group_id != ''
");

$pending_orders_data = mysqli_fetch_assoc($pending_orders_result);
$pending_orders = $pending_orders_data ? intval($pending_orders_data['total']) : 0;

$completed_orders_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE status='completed'
AND order_group_id IS NOT NULL
AND order_group_id != ''
");

$completed_orders_data = mysqli_fetch_assoc($completed_orders_result);
$completed_orders = $completed_orders_data ? intval($completed_orders_data['total']) : 0;

$cancelled_orders_result = mysqli_query($conn, "
SELECT COUNT(DISTINCT order_group_id) AS total
FROM orders
WHERE status='cancelled'
AND order_group_id IS NOT NULL
AND order_group_id != ''
");

$cancelled_orders_data = mysqli_fetch_assoc($cancelled_orders_result);
$cancelled_orders = $cancelled_orders_data ? intval($cancelled_orders_data['total']) : 0;

// building grouped orders query
$orders_query = "
SELECT
MIN(orders.order_id) AS reference_order_id,
orders.order_group_id,
orders.buyer_id,
orders.seller_id,
MIN(orders.order_date) AS order_date,
orders.payment_method,
orders.payment_status,
orders.delivery_status,
orders.status,
orders.delivery_method,
orders.estimated_time,
orders.delivery_address,
SUM(orders.total_amount) AS group_total,
COUNT(orders.order_id) AS item_count,
buyer.full_name AS buyer_name,
seller.business_name AS seller_business_name,
seller.full_name AS seller_name
FROM orders
INNER JOIN users AS buyer ON orders.buyer_id = buyer.user_id
INNER JOIN users AS seller ON orders.seller_id = seller.user_id
WHERE 1
";

$params = [];
$types = "";

if($search != ""){
    $search_value = "%" . $search . "%";
    $orders_query .= " AND (orders.order_group_id LIKE ? OR buyer.full_name LIKE ? OR seller.full_name LIKE ? OR seller.business_name LIKE ?)";
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "ssss";
}

if($status_filter != ""){
    $orders_query .= " AND orders.status=?";
    $params[] = $status_filter;
    $types .= "s";
}

if($delivery_filter != ""){
    $orders_query .= " AND orders.delivery_status=?";
    $params[] = $delivery_filter;
    $types .= "s";
}

$orders_query .= "
GROUP BY
orders.order_group_id,
orders.buyer_id,
orders.seller_id,
orders.payment_method,
orders.payment_status,
orders.delivery_status,
orders.status,
orders.delivery_method,
orders.estimated_time,
orders.delivery_address,
buyer.full_name,
seller.business_name,
seller.full_name
ORDER BY order_date DESC
";

$orders_stmt = $conn->prepare($orders_query);

if(count($params) > 0){
    $orders_stmt->bind_param($types, ...$params);
}

$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Orders | StreetMarket</title>

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

.order-group-card{
background:white;
padding:25px;
border-radius:16px;
box-shadow:0 2px 12px rgba(0,0,0,0.08);
margin-bottom:25px;
}

.order-header{
display:flex;
justify-content:space-between;
gap:15px;
flex-wrap:wrap;
border-bottom:1px solid #eee;
padding-bottom:15px;
margin-bottom:18px;
}

.order-item{
display:flex;
gap:15px;
align-items:center;
background:#f8f8f8;
padding:14px;
border-radius:12px;
margin-bottom:12px;
}

.order-item img{
width:80px;
height:80px;
object-fit:cover;
border-radius:10px;
}

.group-details{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:15px;
margin-top:15px;
}

.detail-box{
background:#f8fafc;
padding:12px;
border-radius:10px;
border:1px solid #e2e8f0;
}

.action-btn{
padding:10px 14px;
background:#111;
color:#fff;
text-decoration:none;
border-radius:8px;
display:inline-block;
font-weight:bold;
margin-top:12px;
}

.cancel-btn{
background:#c62828;
}

.pending-status{
color:#92400e;
font-weight:bold;
}

.completed-status{
color:#166534;
font-weight:bold;
}

.cancelled-status{
color:#991b1b;
font-weight:bold;
}

.estimate-box{
background:#eff6ff;
color:#1e3a8a;
border-left:4px solid #2563eb;
padding:10px;
border-radius:8px;
font-size:13px;
margin-top:10px;
}

.size-text{
font-size:14px;
color:#555;
}

@media(max-width:768px){

.filter-form{
grid-template-columns:1fr;
}

.order-item{
flex-direction:column;
align-items:flex-start;
}

.order-item img{
width:100%;
height:auto;
}

.action-btn{
width:100%;
text-align:center;
}

}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img src="images/logo.png" alt="Logo">

<h1>Admin Orders</h1>

</div>

<!-- Admin navigation links -->
<nav>

<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-seller-verification.php">Seller Verification</a>
<a href="admin-payments.php">Payments</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-notifications.php">Notifications</a>
<a href="admin-logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<h2>Manage Orders</h2>

<p>View buyer orders, seller businesses, payment status, delivery method and grouped order progress.</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<!-- Order statistics cards -->
<div class="stats-grid">

<div class="stat-card">

<h3><?php echo $total_orders; ?></h3>

<p>Total Order Groups</p>

</div>

<div class="stat-card">

<h3><?php echo $pending_orders; ?></h3>

<p>Pending Groups</p>

</div>

<div class="stat-card">

<h3><?php echo $completed_orders; ?></h3>

<p>Completed Groups</p>

</div>

<div class="stat-card">

<h3><?php echo $cancelled_orders; ?></h3>

<p>Cancelled Groups</p>

</div>

</div>

<!-- Search and filter form -->
<div class="filter-box">

<form method="GET" class="filter-form">

<div>

<label>Search Order</label>

<input type="text" name="search" placeholder="Search by group ID, buyer or seller" value="<?php echo htmlspecialchars($search); ?>">

</div>

<div>

<label>Order Status</label>

<select name="status_filter">

<option value="">All Statuses</option>

<option value="pending" <?php if($status_filter == "pending"){ echo "selected"; } ?>>Pending</option>

<option value="completed" <?php if($status_filter == "completed"){ echo "selected"; } ?>>Completed</option>

<option value="cancelled" <?php if($status_filter == "cancelled"){ echo "selected"; } ?>>Cancelled</option>

</select>

</div>

<div>

<label>Delivery Status</label>

<select name="delivery_filter">

<option value="">All Delivery</option>

<option value="processing" <?php if($delivery_filter == "processing"){ echo "selected"; } ?>>Processing</option>

<option value="packed" <?php if($delivery_filter == "packed"){ echo "selected"; } ?>>Packed</option>

<option value="ready_for_pickup" <?php if($delivery_filter == "ready_for_pickup"){ echo "selected"; } ?>>Ready For Pickup</option>

<option value="shipped" <?php if($delivery_filter == "shipped"){ echo "selected"; } ?>>Shipped</option>

<option value="out_for_delivery" <?php if($delivery_filter == "out_for_delivery"){ echo "selected"; } ?>>Out For Delivery</option>

<option value="delivered" <?php if($delivery_filter == "delivered"){ echo "selected"; } ?>>Delivered</option>

<option value="cancelled" <?php if($delivery_filter == "cancelled"){ echo "selected"; } ?>>Cancelled</option>

</select>

</div>

<button type="submit">Filter</button>

<a href="admin-orders.php" class="clear-btn">Clear</a>

</form>

</div>

<?php if($orders_result && $orders_result->num_rows > 0){ ?>

<?php while($group = $orders_result->fetch_assoc()){ ?>

<?php

$status_class = "pending-status";

if($group['status'] == "completed"){
    $status_class = "completed-status";
}

if($group['status'] == "cancelled"){
    $status_class = "cancelled-status";
}

$reference_order_id = intval($group['reference_order_id']);
$raw_order_group_id = trim($group['order_group_id']);
$order_group_id = !empty($raw_order_group_id) ? $raw_order_group_id : "SM".$reference_order_id;
$buyer_id = intval($group['buyer_id']);
$seller_id = intval($group['seller_id']);
$seller_display = !empty($group['seller_business_name']) ? $group['seller_business_name'] : $group['seller_name'];

$items_stmt = $conn->prepare("
SELECT
orders.*,
product.product_name,
product.image,
product.category,
product.price
FROM orders
INNER JOIN product ON orders.product_id = product.product_id
WHERE orders.buyer_id=?
AND orders.seller_id=?
AND orders.order_group_id=?
ORDER BY orders.order_id ASC
");

$items_stmt->bind_param("iis", $buyer_id, $seller_id, $group['order_group_id']);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

?>

<div class="order-group-card">

<div class="order-header">

<div>

<h3>Order Group #<?php echo htmlspecialchars($order_group_id); ?></h3>

<p>Buyer: <b><?php echo htmlspecialchars($group['buyer_name']); ?></b></p>

<p>Seller: <b><?php echo htmlspecialchars($seller_display); ?></b></p>

<p>Ordered On: <?php echo date("d M Y H:i", strtotime($group['order_date'])); ?></p>

</div>

<div>

<p><b><?php echo intval($group['item_count']); ?></b> product(s)</p>

<p class="<?php echo $status_class; ?>">Status: <?php echo ucfirst($group['status']); ?></p>

<p><b>Total:</b> R<?php echo number_format($group['group_total'], 2); ?></p>

</div>

</div>

<?php if($items_result && $items_result->num_rows > 0){ ?>

<?php while($item = $items_result->fetch_assoc()){ ?>

<div class="order-item">

<img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">

<div>

<h4><?php echo htmlspecialchars($item['product_name']); ?></h4>

<p>Category: <?php echo htmlspecialchars($item['category']); ?></p>

<?php if(!empty($item['selected_size'])){ ?>

<p class="size-text">Size / Option: <b><?php echo htmlspecialchars($item['selected_size']); ?></b></p>

<?php } ?>

<p>Quantity: <?php echo intval($item['quantity']); ?></p>

<p>Unit Price: R<?php echo number_format($item['price'], 2); ?></p>

<p>Order Line Total: R<?php echo number_format($item['total_amount'], 2); ?></p>

</div>

</div>

<?php } ?>

<?php } ?>

<div class="group-details">

<div class="detail-box">

<p>Payment Method</p>

<b><?php echo ucwords(str_replace("_", " ", $group['payment_method'])); ?></b>

</div>

<div class="detail-box">

<p>Payment Status</p>

<b><?php echo ucfirst($group['payment_status']); ?></b>

</div>

<div class="detail-box">

<p>Delivery Method</p>

<b><?php echo ucwords($group['delivery_method']); ?></b>

</div>

<div class="detail-box">

<p>Delivery Status</p>

<b><?php echo ucwords(str_replace("_", " ", $group['delivery_status'])); ?></b>

</div>

</div>

<?php if(!empty($group['estimated_time'])){ ?>

<div class="estimate-box">
<?php echo htmlspecialchars($group['estimated_time']); ?>
</div>

<?php } ?>

<a href="order-tracking.php?id=<?php echo $reference_order_id; ?>" class="action-btn">Track Order Group</a>

<?php if($group['status'] == "pending" && $group['delivery_status'] == "processing" && !empty($raw_order_group_id)){ ?>

<a href="admin-orders.php?cancel_group=<?php echo $reference_order_id; ?>" class="action-btn cancel-btn" onclick="return confirm('Cancel this full order group? Product stock will be restored and payment status will be updated correctly.');">Cancel Full Group</a>

<?php } ?>

</div>

<?php } ?>

<?php }else{ ?>

<div class="info-box">

<h3>No Orders Found</h3>

<p>No order records match your search or filter.</p>

</div>

<?php } ?>

</div>

</section>

<footer>

<div class="container footer-container">

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

</body>

</html>