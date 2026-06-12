<?php

session_start();

include("includes/db.php");

/* =========================================
   SESSION PROTECTION
========================================= */

if(!isset($_SESSION['user_id'])){

    header("Location: login.php");
    exit();

}

$user_id = $_SESSION['user_id'];
$message = "";

/* =========================================
   UPDATE PRODUCT QUANTITY
========================================= */

if(isset($_POST['update_quantity'])){

    $product_id = intval($_POST['product_id']);
    $new_quantity = intval($_POST['quantity']);

    if($new_quantity < 0){

        $message = "Quantity cannot be less than zero.";

    }else{

        $check_product = "

        SELECT *

        FROM product

        WHERE product_id='$product_id'
        AND user_id='$user_id'

        ";

        $check_result = mysqli_query($conn, $check_product);

        if(mysqli_num_rows($check_result) > 0){

            $new_status = "available";

            if($new_quantity == 0){

                $new_status = "sold";

            }

            $update_query = "

            UPDATE product

            SET
            quantity='$new_quantity',
            status='$new_status'

            WHERE product_id='$product_id'
            AND user_id='$user_id'

            ";

            if(mysqli_query($conn, $update_query)){

                $message = "Product quantity updated successfully.";

            }else{

                $message = "Failed to update product quantity.";

            }

        }else{

            $message = "Product not found or you are not allowed to update it.";

        }

    }

}

/* =========================================
   REMOVE PRODUCT
========================================= */

if(isset($_POST['remove_product'])){

    $product_id = intval($_POST['product_id']);

    $check_product = "

    SELECT *

    FROM product

    WHERE product_id='$product_id'
    AND user_id='$user_id'

    ";

    $check_result = mysqli_query($conn, $check_product);

    if(mysqli_num_rows($check_result) > 0){

        $delete_query = "

        DELETE FROM product

        WHERE product_id='$product_id'
        AND user_id='$user_id'

        ";

        if(mysqli_query($conn, $delete_query)){

            $message = "Product removed successfully.";

        }else{

            $message = "Failed to remove product.";

        }

    }else{

        $message = "Product not found or you are not allowed to remove it.";

    }

}

/* =========================================
   FETCH USER PRODUCTS
========================================= */

$listings_query = "

SELECT *

FROM product

WHERE user_id='$user_id'

ORDER BY created_at DESC

";

$listings_result = mysqli_query(
$conn,
$listings_query
);

/* =========================================
   SELLER PERFORMANCE
========================================= */

$count_listings_query = "

SELECT COUNT(*) AS total_listings

FROM product

WHERE user_id='$user_id'

";

$count_listings_result = mysqli_query(
$conn,
$count_listings_query
);

$count_listings_data = mysqli_fetch_assoc(
$count_listings_result
);

$count_listings = $count_listings_data['total_listings'];

$count_sold_query = "

SELECT COUNT(*) AS total_sold

FROM product

WHERE user_id='$user_id'
AND status='sold'

";

$count_sold_result = mysqli_query(
$conn,
$count_sold_query
);

$count_sold_data = mysqli_fetch_assoc(
$count_sold_result
);

$count_sold = $count_sold_data['total_sold'];

$total_earnings_query = "

SELECT SUM(total_amount) AS total_earnings

FROM orders

WHERE seller_id='$user_id'
AND payment_status='paid'
AND status='completed'

";

$total_earnings_result = mysqli_query(
$conn,
$total_earnings_query
);

$earnings_data = mysqli_fetch_assoc(
$total_earnings_result
);

$total_earnings = $earnings_data['total_earnings'];

if($total_earnings == NULL){

    $total_earnings = 0;

}

$rating_query = "

SELECT AVG(product_reviews.rating) AS avg_rating

FROM product_reviews

INNER JOIN product
ON product_reviews.product_id = product.product_id

WHERE product.user_id='$user_id'

";

$rating_result = mysqli_query(
$conn,
$rating_query
);

$rating_row = mysqli_fetch_assoc(
$rating_result
);

$avg_rating = $rating_row['avg_rating'];

if($avg_rating == NULL){

    $seller_rating = "No Rating";

}else{

    $seller_rating = round($avg_rating, 1) . "★";

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Listings | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.quantity-form{

display:flex;
gap:8px;
align-items:center;
margin-top:10px;
margin-bottom:10px;

}

.quantity-form input{

width:90px;
padding:10px;
border:1px solid #ddd;
border-radius:8px;

}

.quantity-form button{

padding:10px 14px;
border:none;
border-radius:8px;
background:#111;
color:#fff;
cursor:pointer;
font-weight:bold;

}

.remove-form button{

background:#b00020;
color:#fff;
border:none;
padding:12px;
border-radius:8px;
cursor:pointer;
font-weight:bold;

}

.stock-badge{

display:inline-block;
padding:8px 12px;
border-radius:30px;
background:#f2f2f2;
font-weight:bold;
margin-top:8px;

}

</style>

</head>

<body>

<!-- HEADER -->

<header>

<div class="container header-container">

<div class="logo-section">

<img src="images/logo.png" alt="StreetMarket Logo">

<h1>StreetMarket</h1>

</div>

<nav>

<a href="index.php">Home</a>

<a href="add-products.php">Add My Product</a>

<a href="products.php">Products</a>

<a href="orders.php">Orders</a>

<a href="messages.php">Messages</a>

<a href="login.php">Logout</a>

</nav>

</div>

</header>

<!-- PAGE TITLE -->

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>🏪 My Listings</h2>

<p>
Manage your uploaded products, update quantity and monitor buyer activity.
</p>

</div>

</div>

</section>

<?php

if($message != ""){

?>

<section class="section-spacing">

<div class="container">

<div class="auth-message">

<?php echo htmlspecialchars($message); ?>

</div>

</div>

</section>

<?php

}

?>

<!-- PRODUCT LISTINGS -->

<section class="section-spacing">

<div class="container">

<div class="product-grid">

<?php

if($listings_result && mysqli_num_rows($listings_result) > 0){

    while($listing = mysqli_fetch_assoc($listings_result)){

        $product_id = $listing['product_id'];

        $quantity = 0;

        if(isset($listing['quantity'])){

            $quantity = intval($listing['quantity']);

        }

        $msg_count_query = "

        SELECT COUNT(*) AS msg_count

        FROM messages

        WHERE product_id='$product_id'

        ";

        $msg_count_result = mysqli_query(
        $conn,
        $msg_count_query
        );

        $msg_count_data = mysqli_fetch_assoc(
        $msg_count_result
        );

        $msg_count = $msg_count_data['msg_count'];

        $order_result = mysqli_query(
        $conn,
        "

        SELECT order_id, delivery_status

        FROM orders

        WHERE product_id='$product_id'
        AND seller_id='$user_id'

        ORDER BY order_date DESC

        LIMIT 1

        "
        );

        $order_info = mysqli_fetch_assoc(
        $order_result
        );

?>

<div class="product-card">

<img
src="uploads/<?php echo htmlspecialchars($listing['image']); ?>"
alt="<?php echo htmlspecialchars($listing['product_name']); ?>">

<div class="product-badge">

<?php

if($listing['status'] == "available" && $quantity > 0){

    echo "Active Listing";

}elseif($listing['status'] == "sold" || $quantity == 0){

    echo "Sold / Out Of Stock";

}else{

    echo ucfirst($listing['status']);

}

?>

</div>

<h3>

<?php echo htmlspecialchars($listing['product_name']); ?>

</h3>

<p class="product-price">

R<?php echo number_format($listing['price'], 2); ?>

</p>

<p>
Category: <?php echo htmlspecialchars($listing['category']); ?>
</p>

<p>
Condition: <?php echo htmlspecialchars($listing['product_condition']); ?>
</p>

<p>
Location: <?php echo htmlspecialchars($listing['location']); ?>
</p>

<p>
Messages: <?php echo $msg_count; ?>
</p>

<span class="stock-badge">

Quantity: <?php echo $quantity; ?>

</span>

<form method="POST" class="quantity-form">

<input
type="hidden"
name="product_id"
value="<?php echo $product_id; ?>">

<input
type="number"
name="quantity"
min="0"
value="<?php echo $quantity; ?>"
required>

<button
type="submit"
name="update_quantity">

Update

</button>

</form>

<div class="details-buttons">

<?php

if($listing['status'] == "available" && $quantity > 0){

?>

<a href="edit-product.php?id=<?php echo $product_id; ?>">

<button>Edit Product</button>

</a>

<form method="POST" class="remove-form" onsubmit="return confirm('Remove this listing?');">

<input
type="hidden"
name="product_id"
value="<?php echo $product_id; ?>">

<button
type="submit"
name="remove_product">

Remove Listing

</button>

</form>

<?php

}else{

    if($order_info){

?>

<a href="order-tracking.php?id=<?php echo $order_info['order_id']; ?>">

<button>View Order</button>

</a>

<span class="status-badge">

<?php

echo ucwords(
str_replace(
"_",
" ",
$order_info['delivery_status']
)
);

?>

</span>

<?php

    }

}

?>

</div>

</div>

<?php

    }

}else{

?>

<div class="info-box">

<p>
No listings found.
<a href="add-products.php">Add your first product</a>.
</p>

</div>

<?php

}

?>

</div>

</div>

</section>

<!-- SELLER PERFORMANCE -->

<section class="section-spacing">

<div class="container">

<div class="dashboard-cards">

<div class="dashboard-card">

<h3><?php echo $count_listings; ?></h3>

<p>Total Listings</p>

</div>

<div class="dashboard-card">

<h3><?php echo $count_sold; ?></h3>

<p>Products Sold</p>

</div>

<div class="dashboard-card">

<h3>R<?php echo number_format($total_earnings, 2); ?></h3>

<p>Total Earnings</p>

</div>

<div class="dashboard-card">

<h3><?php echo $seller_rating; ?></h3>

<p>Seller Rating</p>

</div>

</div>

</div>

</section>

<!-- FOOTER -->

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>

<a href="safety.php">Safety Centre</a>

<a href="help.php">Help</a>

</nav>

<p>
Copyright © 2026 StreetMarket.
All Rights Reserved.
</p>

</div>

</footer>

<script src="js/script.js"></script>

</body>

</html>