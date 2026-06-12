<?php

session_start();

include("includes/db.php");

if(!isset($_SESSION['user_id'])){

    header("Location: login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

$orders_query = "

SELECT
orders.*,

product.product_name,
product.image,

users.full_name

FROM orders

INNER JOIN product
ON orders.product_id = product.product_id

INNER JOIN users
ON orders.seller_id = users.user_id

WHERE orders.buyer_id='$user_id'

ORDER BY orders.order_date DESC

";

$orders_result = mysqli_query(
$conn,
$orders_query
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
My Orders
</title>

<link rel="stylesheet"
href="css/style.css">

<style>

.track-btn{

display: inline-block;
padding: 12px 18px;
background: #111;
color: white;
border-radius: 10px;
text-decoration: none;
font-weight: bold;
margin-top: 10px;

}

.track-btn:hover{

opacity: 0.9;

}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img
src="images/logo.png"
alt="Logo">

<h1>
StreetMarket
</h1>

</div>

<nav>

<a href="dashboard.php">
Dashboard
</a>

<a href="products.php">
Products
</a>

<a href="messages.php">
Messages
</a>

<a href="notifications.php">
Notifications
</a>

<a href="logout.php">
Logout
</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="page-intro">

<h2>
My Orders
</h2>

<p>

Track all your purchases
and delivery progress.

</p>

</div>

<div class="product-grid">

<?php

if(mysqli_num_rows($orders_result) > 0){

while($order =
mysqli_fetch_assoc($orders_result)){

?>

<div class="product-card">

<img
src="uploads/<?php echo $order['image']; ?>"
alt="Product">

<div class="product-badge">

<?php

echo ucwords(
str_replace(
"_",
" ",
$order['delivery_status']
)
);

?>

</div>

<h3>

<?php

echo htmlspecialchars(
$order['product_name']
);

?>

</h3>

<p class="product-price">

R<?php

echo number_format(
$order['total_amount'],
2
);

?>

</p>

<p>

Seller:
<?php

echo htmlspecialchars(
$order['full_name']
);

?>

</p>

<p>

Payment:
<b>

<?php

echo ucfirst(
$order['payment_status']
);

?>

</b>

</p>

<p>

Order Date:
<?php

echo date(
"d M Y",
strtotime(
$order['order_date']
)
);

?>

</p>

<a
href="order-tracking.php?id=<?php echo $order['order_id']; ?>"
class="track-btn">

Track Delivery

</a>

</div>

<?php

}

}

else{

?>

<div class="info-box">

<h3>
No Orders Yet
</h3>

<p>

You have not purchased anything yet.

</p>

</div>

<?php

}

?>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<p>

Copyright © 2026 StreetMarket

</p>

</div>

</footer>

</body>
</html>