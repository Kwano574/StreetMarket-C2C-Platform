<?php
// starting php session
session_start();

// connecting to database
include("includes/db.php");

// protecting user session by ensuring user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();//stop and exit page when user not logged in
}

// storing logged-in seller id
$user_id = intval($_SESSION['user_id']);//converting with intval

// getting grouped seller orders
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
buyer.phone AS buyer_phone,
buyer.province AS buyer_province,
buyer.address AS buyer_address
FROM orders
INNER JOIN users AS buyer ON orders.buyer_id = buyer.user_id
WHERE orders.seller_id='$user_id'
AND orders.status!='cancelled'
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
buyer.phone,
buyer.province,
buyer.address
ORDER BY order_date DESC
";

$orders_result = mysqli_query($conn, $orders_query);//storing SQL query result
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Deliveries | StreetMarket</title>
<link rel="stylesheet" href="css/style.css">

<style>
.delivery-group-card{background:white;padding:25px;border-radius:18px;box-shadow:0 2px 15px rgba(0,0,0,0.08);margin-bottom:25px}
.delivery-header{display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap;border-bottom:1px solid #eee;padding-bottom:15px;margin-bottom:20px}
.delivery-status{display:inline-block;padding:8px 14px;border-radius:40px;font-size:13px;font-weight:bold;margin-bottom:10px;background:#111;color:white}
.delivery-item{display:flex;gap:15px;align-items:center;background:#f8f8f8;padding:14px;border-radius:12px;margin-bottom:12px}
.delivery-item img{width:85px;height:85px;object-fit:cover;border-radius:10px}
.track-btn{display:inline-block;padding:14px 18px;background:#111;color:white;border-radius:10px;text-align:center;font-weight:bold;text-decoration:none;margin-top:18px}
.estimate-box{background:#eff6ff;color:#1e3a8a;border-left:5px solid #2563eb;padding:12px;border-radius:10px;margin:12px 0}
.empty-box{background:white;padding:50px;border-radius:18px;text-align:center;box-shadow:0 2px 15px rgba(0,0,0,0.08)}
.group-details{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;margin-top:15px}
.detail-box{background:#f8fafc;padding:12px;border-radius:10px;border:1px solid #e2e8f0}
.address-box{grid-column:1 / -1}
.address-box b{display:block;line-height:1.6}
.size-text{font-size:14px;color:#555}
@media(max-width:768px){.delivery-item{flex-direction:column;align-items:flex-start}.delivery-item img{width:100%;height:auto}.track-btn{width:100%}}
</style>
</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>StreetMarket</h1>
</div>
<!- Navigation bar links -->
<nav>
<a href="dashboard.php">Dashboard</a>
<a href="my-listings.php">My Listings</a>
<a href="messages.php">&#x1F4AC; Chat</a>
<a href="notifications.php">&#x1F514; Notifications</a>
<a href="logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">
<div class="container">
<div class="page-intro">
<h2>Manage Deliveries and Pickups</h2>
<p>Orders are grouped by checkout reference so products bought together appear as one delivery.</p>
</div>
</div>
</section>

<section class="section-spacing">
<div class="container">
<!-- Fetching seller order and thier details and productas-->
<?php if($orders_result && mysqli_num_rows($orders_result) > 0){ ?>

<?php while($group = mysqli_fetch_assoc($orders_result)){ ?>

<?php
$reference_order_id = intval($group['reference_order_id']);
$order_group_id = mysqli_real_escape_string($conn, $group['order_group_id']);
$buyer_id = intval($group['buyer_id']);

if(empty($order_group_id)){
    $order_group_id = "SM".$reference_order_id;
}

$delivery_method = !empty($group['delivery_method']) ? $group['delivery_method'] : "delivery";
$estimated_time = !empty($group['estimated_time']) ? $group['estimated_time'] : "";

$buyer_address_display = "";

if(!empty($group['delivery_address']) && $group['delivery_address'] != "Pickup selected"){
    $buyer_address_display = $group['delivery_address'];
}else{
    $buyer_address_display = trim($group['buyer_address']);
}

if($buyer_address_display == ""){
    $buyer_address_display = "No physical address provided.";
}

// getting all products inside this order group
$items_query = "
SELECT
orders.*,
product.product_name,
product.image,
product.price,
product.category
FROM orders
INNER JOIN product ON orders.product_id = product.product_id
WHERE orders.seller_id='$user_id'
AND orders.buyer_id='$buyer_id'
AND orders.order_group_id='$order_group_id'
AND orders.status!='cancelled'
ORDER BY orders.order_id ASC
";

$items_result = mysqli_query($conn, $items_query);
?>

<div class="delivery-group-card">

<div class="delivery-header">
<div>
<h3>Delivery Group #<?php echo htmlspecialchars($order_group_id); ?></h3>
<p>Buyer: <b><?php echo htmlspecialchars($group['buyer_name']); ?></b></p>
<p>Ordered On: <?php echo date("d M Y H:i", strtotime($group['order_date'])); ?></p>
</div>

<div>
<div class="delivery-status">
<?php echo ucwords(str_replace("_", " ", $group['delivery_status'])); ?>
</div>
<p><b><?php echo intval($group['item_count']); ?></b> product(s)</p>
</div>
</div>

<?php if($items_result && mysqli_num_rows($items_result) > 0){ ?>

<?php while($item = mysqli_fetch_assoc($items_result)){ ?>

<div class="delivery-item">
<img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">

<div>
<h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
<p>Category: <?php echo htmlspecialchars($item['category']); ?></p>

<?php if(!empty($item['selected_size'])){ ?>
<p class="size-text">Size / Option: <b><?php echo htmlspecialchars($item['selected_size']); ?></b></p>
<?php } ?>

<p>Quantity: <?php echo intval($item['quantity']); ?></p>
<p>Unit Price: R<?php echo number_format($item['price'], 2); ?></p>
<p>Line Total: R<?php echo number_format($item['total_amount'], 2); ?></p>
</div>
</div>

<?php } ?>

<?php } ?>

<div class="group-details">
<div class="detail-box">
<p>Method:</p>
<b><?php echo ucwords($delivery_method); ?></b>
</div>

<div class="detail-box">
<p>Payment:</p>
<b><?php echo ucfirst($group['payment_status']); ?></b>
</div>

<div class="detail-box">
<p>Total:</p>
<b>R<?php echo number_format($group['group_total'], 2); ?></b>
</div>

<div class="detail-box">
<p>Buyer Province:</p>
<b><?php echo !empty($group['buyer_province']) ? htmlspecialchars($group['buyer_province']) : "Not provided"; ?></b>
</div>

<div class="detail-box address-box">
<p>Buyer Physical Address:</p>
<b><?php echo nl2br(htmlspecialchars($buyer_address_display)); ?></b>
</div>
</div>

<?php if($delivery_method == "delivery"){ ?>
<div class="estimate-box">
<?php echo $estimated_time != "" ? htmlspecialchars($estimated_time) : "Set delivery estimate from tracking page."; ?>
</div>
<?php }else{ ?>
<div class="estimate-box">
<?php echo $estimated_time != "" ? htmlspecialchars($estimated_time) : "Set pickup readiness time from tracking page."; ?>
</div>
<?php } ?>

<a href="order-tracking.php?id=<?php echo $reference_order_id; ?>" class="track-btn">
Manage This Order Group
</a>

</div>

<?php } ?>

<?php }else{ ?>

<div class="empty-box">
<h3>No Orders Yet</h3>
<p>No customers have purchased your products yet.</p>
</div>

<?php } ?>

</div>
</section>

    <!--Footer-->
<footer>
<div class="container footer-container">
<nav>
<a href="shipping-guide.php"> Shipping Guide</a>
<a href="about.php">About</a>
<a href="help.php">Help</a>
<a href="sellercenter.php">Seller Centre</a>
</nav>
<p>Copyright © 2026 StreetMarket</p>
</div>
</footer>

</body>
</html>