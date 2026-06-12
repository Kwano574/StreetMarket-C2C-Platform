<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

requireAdminRole(["order_manager"]);

$message = "";
$message_type = "success";

/* CANCEL ORDER */

if(isset($_GET['cancel'])){

    $order_id = intval($_GET['cancel']);

    $order_query = "
    SELECT
    orders.*,
    product.product_name
    FROM orders
    INNER JOIN product ON orders.product_id = product.product_id
    WHERE orders.order_id='$order_id'
    AND orders.status='pending'
    AND orders.delivery_status='processing'
    LIMIT 1
    ";

    $order_result = mysqli_query($conn, $order_query);

    if($order_result && mysqli_num_rows($order_result) > 0){

        $order = mysqli_fetch_assoc($order_result);

        $product_id = intval($order['product_id']);
        $quantity = isset($order['quantity']) ? intval($order['quantity']) : 1;

        if($quantity < 1){
            $quantity = 1;
        }

        mysqli_begin_transaction($conn);

        $restore_stock = "
        UPDATE product
        SET quantity = quantity + '$quantity',
        status='available'
        WHERE product_id='$product_id'
        ";

        $cancel_order = "
        UPDATE orders
        SET
        status='cancelled',
        payment_status='refunded',
        cancelled_by='admin',
        delivery_status='cancelled'
        WHERE order_id='$order_id'
        ";

        if(mysqli_query($conn, $restore_stock) && mysqli_query($conn, $cancel_order)){

            mysqli_commit($conn);

            if(function_exists("createNotification")){

                createNotification(
                    $conn,
                    $order['buyer_id'],
                    "Order Cancelled",
                    "Your order for ".$order['product_name']." was cancelled by admin and refunded.",
                    "orders.php"
                );

                createNotification(
                    $conn,
                    $order['seller_id'],
                    "Order Cancelled",
                    "Admin cancelled an order for ".$order['product_name'].". Stock has been restored.",
                    "manage-deliveries.php"
                );

            }

            $message = "Order cancelled successfully and product stock restored.";
            $message_type = "success";

        }else{

            mysqli_rollback($conn);

            $message = "Failed to cancel order.";
            $message_type = "error";

        }

    }else{

        $message = "Order cannot be cancelled. It may already be processed, completed, delivered or cancelled.";
        $message_type = "error";

    }

}

/* GET ALL ORDERS */

$orders_query = "
SELECT
orders.*,
product.product_name,
product.image,
product.category,
product.price,
buyer.full_name AS buyer_name,
seller.business_name AS seller_business_name,
seller.full_name AS seller_name
FROM orders
INNER JOIN product ON orders.product_id = product.product_id
INNER JOIN users AS buyer ON orders.buyer_id = buyer.user_id
INNER JOIN users AS seller ON orders.seller_id = seller.user_id
ORDER BY orders.order_date DESC
";

$orders_result = mysqli_query($conn, $orders_query);

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
min-width:1100px;
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
font-weight:bold;
}

.product-cell{
display:flex;
align-items:center;
gap:12px;
min-width:230px;
}

.product-cell img{
width:65px;
height:65px;
object-fit:cover;
border-radius:10px;
}

.action-btn{
padding:9px 14px;
background:#111;
color:#fff;
text-decoration:none;
border-radius:8px;
display:inline-block;
font-weight:bold;
margin:3px 0;
}

.cancel-btn{
background:#c62828;
}

.status{
font-weight:bold;
}

.pending-status{
color:#92400e;
}

.completed-status{
color:#166534;
}

.cancelled-status{
color:#991b1b;
}

.estimate-box{
background:#eff6ff;
color:#1e3a8a;
border-left:4px solid #2563eb;
padding:8px;
border-radius:8px;
font-size:13px;
margin-top:6px;
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

<nav>

<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-payments.php">Payments</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<h2>Manage Orders</h2>

<p>
View buyer orders, seller businesses, payment status, delivery method and order progress.
</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="table-wrapper">

<table>

<tr>
<th>Order</th>
<th>Product</th>
<th>Buyer</th>
<th>Seller</th>
<th>Qty</th>
<th>Total</th>
<th>Payment</th>
<th>Method</th>
<th>Order Progress</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php if($orders_result && mysqli_num_rows($orders_result) > 0){ ?>

<?php while($order = mysqli_fetch_assoc($orders_result)){ ?>

<?php

$status_class = "pending-status";

if($order['status'] == "completed"){
    $status_class = "completed-status";
}

if($order['status'] == "cancelled"){
    $status_class = "cancelled-status";
}

$quantity = isset($order['quantity']) ? intval($order['quantity']) : 1;
$delivery_method = !empty($order['delivery_method']) ? $order['delivery_method'] : "delivery";
$estimated_time = !empty($order['estimated_time']) ? $order['estimated_time'] : "";

$seller_display = !empty($order['seller_business_name'])
? $order['seller_business_name']
: $order['seller_name'];

?>

<tr>

<td>
#<?php echo $order['order_id']; ?>
<br>
<small><?php echo date("d M Y H:i", strtotime($order['order_date'])); ?></small>
</td>

<td>

<div class="product-cell">

<img src="uploads/<?php echo htmlspecialchars($order['image']); ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>">

<div>

<strong><?php echo htmlspecialchars($order['product_name']); ?></strong>

<br>

<small><?php echo htmlspecialchars($order['category']); ?></small>

<br>

<small>Unit: R<?php echo number_format($order['price'], 2); ?></small>

</div>

</div>

</td>

<td>
<?php echo htmlspecialchars($order['buyer_name']); ?>
</td>

<td>
<?php echo htmlspecialchars($seller_display); ?>
</td>

<td>
<?php echo $quantity; ?>
</td>

<td>
R<?php echo number_format($order['total_amount'], 2); ?>
</td>

<td>
<?php echo ucwords(str_replace("_", " ", $order['payment_method'])); ?>
<br>
<b><?php echo ucfirst($order['payment_status']); ?></b>
</td>

<td>
<?php echo ucwords($delivery_method); ?>

<?php if($estimated_time != ""){ ?>

<div class="estimate-box">
<?php echo htmlspecialchars($estimated_time); ?>
</div>

<?php } ?>

</td>

<td>
<?php echo ucwords(str_replace("_", " ", $order['delivery_status'])); ?>
</td>

<td class="status <?php echo $status_class; ?>">
<?php echo ucfirst($order['status']); ?>
</td>

<td>

<a href="order-tracking.php?id=<?php echo $order['order_id']; ?>" class="action-btn">
Track
</a>

<?php if($order['status'] == "pending" && $order['delivery_status'] == "processing"){ ?>

<a
href="admin-orders.php?cancel=<?php echo $order['order_id']; ?>"
class="action-btn cancel-btn"
onclick="return confirm('Cancel this order and refund payment?');">

Cancel

</a>

<?php } ?>

</td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>
<td colspan="11">No orders found.</td>
</tr>

<?php } ?>

</table>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<p>
Copyright © 2026 StreetMarket. All Rights Reserved.
</p>

</div>

</footer>

</body>

</html>