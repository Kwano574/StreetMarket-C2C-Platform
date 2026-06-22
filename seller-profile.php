<?php

session_start();

include("includes/db.php");

/* =========================================
   GET SELLER ID
========================================= */

if(!isset($_GET['id'])){

    header("Location: products.php");
    exit();

}

$seller_id = intval($_GET['id']);

/* =========================================
   GET SELLER INFORMATION
========================================= */

$seller_query = "

SELECT *

FROM users

WHERE user_id='$seller_id'

";

$seller_result = mysqli_query(
$conn,
$seller_query
);

if(mysqli_num_rows($seller_result) == 0){

    die("Seller not found.");

}

$seller = mysqli_fetch_assoc(
$seller_result
);

/* =========================================
   COUNT SELLER PRODUCTS
========================================= */

$total_products_query = "

SELECT COUNT(*) AS total_products

FROM product

WHERE user_id='$seller_id'

";

$total_products_result = mysqli_query(
$conn,
$total_products_query
);

$total_products = mysqli_fetch_assoc(
$total_products_result
);

/* =========================================
   COUNT SOLD PRODUCTS
========================================= */

$sold_products_query = "

SELECT COUNT(*) AS sold_products

FROM product

WHERE user_id='$seller_id'
AND status='sold'

";

$sold_products_result = mysqli_query(
$conn,
$sold_products_query
);

$sold_products = mysqli_fetch_assoc(
$sold_products_result
);

/* =========================================
   GET SELLER PRODUCTS
========================================= */

$products_query = "

SELECT *

FROM product

WHERE user_id='$seller_id'

ORDER BY product_id DESC

";

$products_result = mysqli_query(
$conn,
$products_query
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

Seller Profile | StreetMarket

</title>

<link rel="stylesheet"
href="css/style.css">

</head>

<body>

<!-- =========================================
     HEADER
========================================= -->

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

<a href="cart.php">
Cart
</a>

<a href="orders.php">
Orders
</a>

<a href="messages.php">
Messages
</a>

<a href="logout.php">
Logout
</a>

</nav>

</div>

</header>

<!-- =========================================
     SELLER PROFILE
========================================= -->

<section class="section-spacing">

<div class="container">

<div class="seller-profile-box">

<div class="seller-avatar">

<?php

echo strtoupper(
substr(
$seller['full_name'],
0,
1
)
);

?>

</div>

<div class="seller-info">

<h2>

<?php

echo htmlspecialchars(
$seller['full_name']
);

?>

</h2>

<p>

Province:
<?php

echo htmlspecialchars(
$seller['province']
);

?>

</p>

<p>

Email:
<?php

echo htmlspecialchars(
$seller['email']
);

?>

</p>

<p>

Phone:
+27 <?php

echo htmlspecialchars(
$seller['phone']
);

?>

</p>

<p>

Verified South African Seller

</p>

</div>

</div>

</div>

</section>

<!-- =========================================
     SELLER STATS
========================================= -->

<section class="section-spacing">

<div class="container">

<div class="feature-grid">

<div class="feature-card">

<h2>

<?php
echo $total_products['total_products'];
?>

</h2>

<p>
Total Products
</p>

</div>

<div class="feature-card">

<h2>

<?php
echo $sold_products['sold_products'];
?>

</h2>

<p>
Products Sold
</p>

</div>

<div class="feature-card">

<h2>
4.8 / 5
</h2>

<p>
Seller Rating
</p>

</div>

</div>

</div>

</section>

<!-- =========================================
     MESSAGE SELLER
========================================= -->

<section class="section-spacing">

<div class="container">

<div class="info-box">

<h3>
Contact Seller
</h3>

<p>

You can communicate directly with
this seller using StreetMarket messaging.

</p>

<a href="messages.php?user=<?php echo $seller_id; ?>">

<button>

Message Seller

</button>

</a>

</div>

</div>

</section>

<!-- =========================================
     SELLER PRODUCTS
========================================= -->

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>

Seller Listings

</h2>

<p>

Products uploaded by this seller.

</p>

</div>

<div class="product-grid">

<?php

if(mysqli_num_rows($products_result) > 0){

while($product =
mysqli_fetch_assoc($products_result)){

?>

<div class="product-card">

<img
src="uploads/<?php echo $product['image']; ?>"
alt="<?php echo $product['product_name']; ?>">

<div class="product-badge">

<?php

echo ucfirst(
$product['status']
);

?>

</div>

<h3>

<?php

echo htmlspecialchars(
$product['product_name']
);

?>

</h3>

<p class="product-price">

R<?php

echo number_format(
$product['price'],
2
);

?>

</p>

<a
href="product-details.php?id=<?php echo $product['product_id']; ?>">

<button>

View Product

</button>

</a>

</div>

<?php

}

}

else{

?>

<div class="info-box">

<p>

This seller has no products.

</p>

</div>

<?php

}

?>

</div>

</div>

</section>

<!-- =========================================
     FOOTER
========================================= -->

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">
About
</a>

<a href="help.php">
Help
</a>

<a href="safety.php">
Safety Centre
</a>

</nav>

<p>

Copyright © 2026 StreetMarket

</p>

</div>

</footer>

</body>
</html>