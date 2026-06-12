<?php

session_start();

include("includes/db.php");

if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = "";

/* CANCEL GROUPED ORDER */

if(isset($_GET['cancel_group'])){

    $reference_order_id = intval($_GET['cancel_group']);

    $reference_query = "
    SELECT *
    FROM orders
    WHERE order_id='$reference_order_id'
    AND buyer_id='$user_id'
    AND status='pending'
    AND delivery_status='processing'
    LIMIT 1
    ";

    $reference_result = mysqli_query($conn, $reference_query);

    if($reference_result && mysqli_num_rows($reference_result) > 0){

        $reference_order = mysqli_fetch_assoc($reference_result);

        $seller_id = intval($reference_order['seller_id']);
        $order_time = mysqli_real_escape_string($conn, $reference_order['order_date']);

        $group_orders_query = "
        SELECT *
        FROM orders
        WHERE buyer_id='$user_id'
        AND seller_id='$seller_id'
        AND order_date='$order_time'
        AND status='pending'
        AND delivery_status='processing'
        ";

        $group_orders_result = mysqli_query($conn, $group_orders_query);

        mysqli_begin_transaction($conn);

        $success = true;

        if($group_orders_result && mysqli_num_rows($group_orders_result) > 0){

            while($order = mysqli_fetch_assoc($group_orders_result)){

                $product_id = intval($order['product_id']);
                $quantity = intval($order['quantity']);

                if($quantity < 1){
                    $quantity = 1;
                }

                $restore_stock = "
                UPDATE product
                SET quantity = quantity + '$quantity',
                status='available'
                WHERE product_id='$product_id'
                ";

                if(!mysqli_query($conn, $restore_stock)){
                    $success = false;
                    break;
                }
            }

            if($success){

                $cancel_orders = "
                UPDATE orders
                SET status='cancelled',
                payment_status='refunded',
                cancelled_by='buyer'
                WHERE buyer_id='$user_id'
                AND seller_id='$seller_id'
                AND order_date='$order_time'
                AND status='pending'
                AND delivery_status='processing'
                ";

                if(mysqli_query($conn, $cancel_orders)){

                    mysqli_commit($conn);

                    if(function_exists("createNotification")){
                        createNotification(
                            $conn,
                            $seller_id,
                            "Order Cancelled",
                            "A buyer cancelled an order group. Product stock has been restored.",
                            "manage-deliveries.php"
                        );
                    }

                    header("Location: orders.php?cancelled=success");
                    exit();

                }else{
                    mysqli_rollback($conn);
                    $message = "Failed to cancel order group.";
                }

            }else{
                mysqli_rollback($conn);
                $message = "Failed to restore product stock.";
            }

        }else{
            mysqli_rollback($conn);
            $message = "Order group not found.";
        }

    }else{
        $message = "This order cannot be cancelled anymore.";
    }
}

if(isset($_GET['cancelled']) && $_GET['cancelled'] == "success"){
    $message = "Order cancelled successfully.";
}

/* GET GROUPED ORDERS */

$orders_query = "
SELECT
MIN(orders.order_id) AS reference_order_id,
orders.buyer_id,
orders.seller_id,
orders.order_date,
orders.payment_method,
orders.payment_status,
orders.delivery_status,
orders.status,
orders.delivery_method,
orders.estimated_time,
orders.delivery_address,
SUM(orders.total_amount) AS group_total,
COUNT(orders.order_id) AS item_count,
seller.business_name AS seller_business_name,
seller.full_name AS seller_name
FROM orders
INNER JOIN users AS seller ON orders.seller_id = seller.user_id
WHERE orders.buyer_id='$user_id'
AND orders.status!='cancelled'
GROUP BY
orders.buyer_id,
orders.seller_id,
orders.order_date,
orders.payment_method,
orders.payment_status,
orders.delivery_status,
orders.status,
orders.delivery_method,
orders.estimated_time,
orders.delivery_address,
seller.business_name,
seller.full_name
ORDER BY orders.order_date DESC
";

$orders_result = mysqli_query($conn, $orders_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Orders | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.order-group-card{
background:white;
padding:25px;
border-radius:18px;
box-shadow:0 2px 15px rgba(0,0,0,0.08);
margin-bottom:25px;
}

.order-header{
display:flex;
justify-content:space-between;
gap:15px;
flex-wrap:wrap;
border-bottom:1px solid #eee;
padding-bottom:15px;
margin-bottom:20px;
}

.order-status{
display:inline-block;
padding:8px 15px;
border-radius:30px;
font-size:13px;
font-weight:bold;
background:#f2f2f2;
}

.pending-status{
background:#fff3cd;
color:#856404;
}

.completed-status{
background:#d4edda;
color:#155724;
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
width:85px;
height:85px;
object-fit:cover;
border-radius:10px;
}

.order-actions{
display:flex;
gap:10px;
flex-wrap:wrap;
margin-top:18px;
}

.track-btn,
.cancel-btn{
display:inline-block;
padding:12px 18px;
color:white;
border-radius:10px;
text-decoration:none;
font-weight:bold;
}

.track-btn{
background:#111;
}

.cancel-btn{
background:#c62828;
}

.estimate-box{
background:#eff6ff;
color:#1e3a8a;
border-left:5px solid #2563eb;
padding:12px;
border-radius:10px;
margin:12px 0;
}

.success-message{
background:#dcfce7;
color:#166534;
border-left:5px solid #16a34a;
padding:15px;
border-radius:10px;
margin-bottom:20px;
font-weight:bold;
}

@media(max-width:768px){
.order-item{flex-direction:column;align-items:flex-start;}
.order-item img{width:100%;height:auto;}
.track-btn,.cancel-btn{width:100%;text-align:center;}
}

</style>

</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>StreetMarket</h1>
</div>
<nav>
<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="messages.php">Messages</a>
<a href="notifications.php">Notifications</a>
<a href="cart.php">Cart</a>
<a href="logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">
<div class="container">

<div class="page-intro">
<h2>My Orders</h2>
<p>Orders are grouped by seller so products bought together appear as one order.</p>
</div>

<?php if($message != ""){ ?>
<div class="success-message">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<?php if($orders_result && mysqli_num_rows($orders_result) > 0){ ?>

<?php while($group = mysqli_fetch_assoc($orders_result)){ ?>

<?php
$status_class = "pending-status";

if($group['status'] == "completed"){
    $status_class = "completed-status";
}

$reference_order_id = intval($group['reference_order_id']);
$seller_id = intval($group['seller_id']);
$order_time = mysqli_real_escape_string($conn, $group['order_date']);

$items_query = "
SELECT
orders.*,
product.product_name,
product.image,
product.price,
product.category
FROM orders
INNER JOIN product ON orders.product_id = product.product_id
WHERE orders.buyer_id='$user_id'
AND orders.seller_id='$seller_id'
AND orders.order_date='$order_time'
AND orders.status!='cancelled'
ORDER BY orders.order_id ASC
";

$items_result = mysqli_query($conn, $items_query);

$seller_display = !empty($group['seller_business_name'])
? $group['seller_business_name']
: $group['seller_name'];

?>

<div class="order-group-card">

<div class="order-header">

<div>
<h3>Order Group #<?php echo $reference_order_id; ?></h3>
<p>Seller: <b><?php echo htmlspecialchars($seller_display); ?></b></p>
<p>Ordered On: <?php echo date("d M Y H:i", strtotime($group['order_date'])); ?></p>
</div>

<div>
<div class="order-status <?php echo $status_class; ?>">
<?php echo ucfirst($group['status']); ?>
</div>
<p><b><?php echo intval($group['item_count']); ?></b> product(s)</p>
</div>

</div>

<?php if($items_result && mysqli_num_rows($items_result) > 0){ ?>

<?php while($item = mysqli_fetch_assoc($items_result)){ ?>

<div class="order-item">

<img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">

<div>
<h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
<p>Category: <?php echo htmlspecialchars($item['category']); ?></p>
<p>Quantity: <?php echo intval($item['quantity']); ?></p>
<p>Unit Price: R<?php echo number_format($item['price'], 2); ?></p>
<p>Line Total: R<?php echo number_format($item['total_amount'], 2); ?></p>
</div>

</div>

<?php } ?>

<?php } ?>

<p>Payment Method: <b><?php echo ucwords(str_replace("_", " ", $group['payment_method'])); ?></b></p>
<p>Payment Status: <b><?php echo ucfirst($group['payment_status']); ?></b></p>
<p>Method: <b><?php echo ucwords($group['delivery_method']); ?></b></p>
<p>Delivery Status: <b><?php echo ucwords(str_replace("_", " ", $group['delivery_status'])); ?></b></p>

<?php if(!empty($group['estimated_time'])){ ?>
<div class="estimate-box">
<?php echo htmlspecialchars($group['estimated_time']); ?>
</div>
<?php } ?>

<p class="product-price">
Group Total: R<?php echo number_format($group['group_total'], 2); ?>
</p>

<div class="order-actions">

<a href="order-tracking.php?id=<?php echo $reference_order_id; ?>" class="track-btn">
Track Order Group
</a>

<?php if($group['delivery_status'] == "processing" && $group['status'] == "pending"){ ?>

<a
href="orders.php?cancel_group=<?php echo $reference_order_id; ?>"
class="cancel-btn"
onclick="return confirm('Cancel this full order group? Product stock will be restored.');">
Cancel Full Order
</a>

<?php } ?>

</div>

</div>

<?php } ?>

<?php }else{ ?>

<div class="info-box">
<h3>No Active Orders</h3>
<p>You have no active orders.</p>
<br>
<a href="products.php" class="track-btn">Browse Products</a>
</div>

<?php } ?>

</div>
</section>

<footer>
<div class="container footer-container">
<p>Copyright © 2026 StreetMarket</p>
</div>
</footer>

</body>
</html>