<?php

// starting php session
session_start();

// connecting to database for data retrieval and storing
include("includes/db.php");

// getting current page with filters/search if user is not logged in
$current_page = "products.php";

if(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] != ""){
    $current_page .= "?" . $_SERVER['QUERY_STRING'];
}

// protecting user session and preserving the page user wanted
if(!isset($_SESSION['user_id'])){
    header("Location: login.php?redirect=" . urlencode($current_page));
    exit();
}

// converting and storing user session id
$user_id = intval($_SESSION['user_id']);
$message = "";
$message_type = "success";

// statement to run when user adds product to cart with quantity
if(isset($_POST['add_to_cart'])){

    // getting product details user is adding to cart
    $product_id = intval($_POST['product_id']);
    $cart_quantity = intval($_POST['cart_quantity']);

    if($cart_quantity < 1){
        $cart_quantity = 1;
    }

    // retrieving product
    $product_query = "
    SELECT *
    FROM product
    WHERE product_id='$product_id'
    AND status='available'
    AND moderation_status='approved'
    AND quantity > 0
    LIMIT 1
    ";

    $product_result = mysqli_query($conn, $product_query);

    if($product_result && mysqli_num_rows($product_result) > 0){

        $product = mysqli_fetch_assoc($product_result);
        $available_stock = intval($product['quantity']);

        // checking if product belongs to same user adding to cart
        if($product['user_id'] == $user_id){

            $message = "You cannot buy your own product.";
            $message_type = "error";

        }elseif($cart_quantity > $available_stock){

            $message = "You cannot add more than the available stock.";
            $message_type = "error";

        }else{

            // checking if product already exists in cart
            $check_cart = "
            SELECT *
            FROM cart
            WHERE user_id='$user_id'
            AND product_id='$product_id'
            LIMIT 1
            ";

            $cart_result = mysqli_query($conn, $check_cart);

            if($cart_result && mysqli_num_rows($cart_result) > 0){

                $cart_item = mysqli_fetch_assoc($cart_result);
                $new_quantity = intval($cart_item['quantity']) + $cart_quantity;

                if($new_quantity > $available_stock){
                    $new_quantity = $available_stock;
                }

                $update_cart = "
                UPDATE cart
                SET quantity='$new_quantity'
                WHERE user_id='$user_id'
                AND product_id='$product_id'
                ";

                if(mysqli_query($conn, $update_cart)){
                    $message = "Cart quantity updated successfully.";
                    $message_type = "success";
                }else{
                    $message = "Failed to update cart quantity.";
                    $message_type = "error";
                }

            }else{

                $insert_cart = "
                INSERT INTO cart(user_id, product_id, quantity)
                VALUES('$user_id', '$product_id', '$cart_quantity')
                ";

                if(mysqli_query($conn, $insert_cart)){
                    $message = "Product added to cart successfully.";
                    $message_type = "success";
                }else{
                    $message = "Failed to add product to cart.";
                    $message_type = "error";
                }

            }

        }

    }else{

        $message = "Product not found, unavailable, out of stock, or not approved yet.";
        $message_type = "error";

    }

}

// search, category filtering and sorting
$search = "";
$category = "";
$sort = "";
$conditions = [];

if(isset($_GET['search'])){

    $search = mysqli_real_escape_string($conn, trim($_GET['search']));

    if(!empty($search)){

        $search_words = explode(" ", $search);
        $search_parts = [];

        foreach($search_words as $word){

            $word = mysqli_real_escape_string($conn, trim($word));

            if($word != ""){
                $search_parts[] = "product.product_name LIKE '%$word%'";
                $search_parts[] = "product.category LIKE '%$word%'";
                $search_parts[] = "product.description LIKE '%$word%'";
                $search_parts[] = "users.business_name LIKE '%$word%'";
                $search_parts[] = "users.business_type LIKE '%$word%'";
                $search_parts[] = "users.business_location LIKE '%$word%'";
            }

        }

        if(count($search_parts) > 0){
            $conditions[] = "(" . implode(" OR ", $search_parts) . ")";
        }

    }

}

if(isset($_GET['category'])){

    $category = mysqli_real_escape_string($conn, trim($_GET['category']));

    if(!empty($category)){
        $conditions[] = "product.category='$category'";
    }

}

// displaying only approved and available products
$conditions[] = "product.status='available'";
$conditions[] = "product.moderation_status='approved'";
$conditions[] = "product.quantity > 0";

$where = "WHERE " . implode(" AND ", $conditions);

$order_by = "ORDER BY product.created_at DESC";

if(isset($_GET['sort'])){

    $sort = mysqli_real_escape_string($conn, trim($_GET['sort']));

    if($sort == "low"){
        $order_by = "ORDER BY product.price ASC";
    }elseif($sort == "high"){
        $order_by = "ORDER BY product.price DESC";
    }elseif($sort == "rating"){
        $order_by = "ORDER BY average_rating DESC";
    }elseif($sort == "newest"){
        $order_by = "ORDER BY product.created_at DESC";
    }

}

$products_query = "
SELECT
product.*,
users.business_name,
users.business_type,
users.business_location,
users.business_profile,
users.seller_verification_status,
IFNULL(AVG(product_reviews.rating), 0) AS average_rating,
COUNT(product_reviews.review_id) AS total_reviews
FROM product
INNER JOIN users ON product.user_id = users.user_id
LEFT JOIN product_reviews ON product.product_id = product_reviews.product_id
$where
GROUP BY product.product_id
$order_by
";

$products_result = mysqli_query($conn, $products_query);

$categories_query = "
SELECT DISTINCT category
FROM product
WHERE category IS NOT NULL
AND category != ''
AND status='available'
AND moderation_status='approved'
AND quantity > 0
ORDER BY category ASC
";

$categories_result = mysqli_query($conn, $categories_query);
$categories_result2 = mysqli_query($conn, $categories_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Browse Products | StreetMarket</title>
<link rel="stylesheet" href="css/style.css">

<style>

.products-top-bar{
display:flex;
gap:10px;
margin-top:20px;
margin-bottom:30px;
align-items:center;
flex-wrap:wrap;
}

.products-top-bar input{
flex:1;
min-width:260px;
padding:14px 18px;
border:1px solid #ddd;
border-radius:10px;
font-size:15px;
height:50px;
}

.products-top-bar select{
padding:14px;
border:1px solid #ddd;
border-radius:10px;
background:white;
min-width:170px;
height:50px;
}

.products-top-bar button{
width:50px;
height:50px;
border:none;
background:black;
color:white;
border-radius:10px;
cursor:pointer;
font-size:18px;
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

.error-message{
background:#fee2e2;
color:#991b1b;
border-left:5px solid #dc2626;
padding:15px;
border-radius:10px;
margin-bottom:20px;
font-weight:bold;
}

.category-tags{
display:flex;
gap:10px;
margin-bottom:25px;
flex-wrap:wrap;
}

.category-tags a{
padding:10px 18px;
background:#f2f2f2;
border-radius:30px;
text-decoration:none;
color:black;
font-size:14px;
transition:0.3s;
}

.category-tags a:hover{
background:black;
color:white;
}

.active-category{
background:black !important;
color:white !important;
}

.product-card{
padding:15px;
}

.product-card img{
height:220px;
object-fit:cover;
border-radius:12px;
}

.product-card h3{
margin-top:12px;
margin-bottom:10px;
font-size:18px;
}

.product-card p{
margin:6px 0;
}

.product-price{
font-weight:bold;
font-size:20px;
}

.rating{
color:orange;
font-size:15px;
margin-top:8px;
}

.business-mini-box{
background:#f8fafc;
border:1px solid #e2e8f0;
padding:12px;
border-radius:10px;
margin-top:10px;
font-size:14px;
}

.business-mini-box strong{
display:block;
color:#0f172a;
margin-bottom:3px;
}

.verified-badge{
display:inline-block;
background:#16a34a;
color:white;
padding:6px 10px;
border-radius:20px;
font-size:12px;
font-weight:bold;
margin-top:8px;
}

.unverified-badge{
display:inline-block;
background:#f59e0b;
color:white;
padding:6px 10px;
border-radius:20px;
font-size:12px;
font-weight:bold;
margin-top:8px;
}

.view-btn{
display:inline-block;
margin-top:15px;
padding:12px;
background:black;
color:white;
text-decoration:none;
border-radius:8px;
width:100%;
text-align:center;
font-weight:bold;
}

.cart-form{
margin-top:10px;
}

.cart-quantity-row{
display:flex;
gap:8px;
align-items:center;
margin-top:10px;
}

.cart-quantity-row input{
width:80px;
padding:10px;
border:1px solid #ddd;
border-radius:8px;
text-align:center;
}

.cart-btn{
display:inline-block;
padding:12px;
background:#2563eb;
color:white;
border:none;
border-radius:8px;
width:100%;
text-align:center;
font-weight:bold;
cursor:pointer;
margin-top:10px;
}

.disabled-cart-btn{
display:inline-block;
margin-top:10px;
padding:12px;
background:#999;
color:white;
border-radius:8px;
width:100%;
text-align:center;
font-weight:bold;
cursor:not-allowed;
}

.stock-text{
font-size:14px;
font-weight:bold;
color:#444;
}

@media(max-width:768px){

.products-top-bar{
flex-direction:column;
align-items:stretch;
}

.products-top-bar input,
.products-top-bar select,
.products-top-bar button{
width:100%;
}

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

<a href="index.php">&#x1F3E0; Home</a>
<a href="wishlist.php">Wishlist</a>
<a href="cart.php">Cart</a>
<a href="orders.php">Orders</a>
<a href="messages.php">&#x1F4AC; Chat</a>
   <a href="notifications.php" title="Notifications">&#x1F514; Notifications</a>
<a href="user-profile.php">&#128100; Profile</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="page-intro">

<h2>Browse Products</h2>

<p>
Discover approved products from verified StreetMarket businesses across South Africa.
</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="category-tags">

<a href="products.php" class="<?php if($category == ""){ echo 'active-category'; } ?>">
All Products
</a>

<?php if($categories_result && mysqli_num_rows($categories_result) > 0){ ?>

<?php while($cat = mysqli_fetch_assoc($categories_result)){ ?>

<a href="products.php?category=<?php echo urlencode($cat['category']); ?>"
class="<?php if($category == $cat['category']){ echo 'active-category'; } ?>">

<?php echo htmlspecialchars($cat['category']); ?>

</a>

<?php } ?>

<?php } ?>

</div>

<form method="GET" action="products.php">

<div class="products-top-bar">

<input
type="search"
name="search"
placeholder="Search products, categories, business names or locations..."
value="<?php echo htmlspecialchars($search); ?>">

<select name="category">

<option value="">All Categories</option>

<?php if($categories_result2 && mysqli_num_rows($categories_result2) > 0){ ?>

<?php while($cat2 = mysqli_fetch_assoc($categories_result2)){ ?>

<option value="<?php echo htmlspecialchars($cat2['category']); ?>"
<?php if($category == $cat2['category']){ echo "selected"; } ?>>

<?php echo htmlspecialchars($cat2['category']); ?>

</option>

<?php } ?>

<?php } ?>

</select>

<select name="sort">

<option value="">Sort By</option>

<option value="newest" <?php if($sort == "newest"){ echo "selected"; } ?>>Newest</option>
<option value="low" <?php if($sort == "low"){ echo "selected"; } ?>>Lowest Price</option>
<option value="high" <?php if($sort == "high"){ echo "selected"; } ?>>Highest Price</option>
<option value="rating" <?php if($sort == "rating"){ echo "selected"; } ?>>Top Rated</option>

</select>

<button type="submit">🔍</button>

</div>

</form>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="product-grid">

<?php if($products_result && mysqli_num_rows($products_result) > 0){ ?>

<?php while($row = mysqli_fetch_assoc($products_result)){ ?>

<?php

$quantity = intval($row['quantity']);
$product_id = intval($row['product_id']);
$product_image = htmlspecialchars(basename($row['image']));
$product_name = htmlspecialchars($row['product_name']);
$product_category = htmlspecialchars($row['category']);
$product_details_link = "product-details.php?id=" . $product_id;

?>

<div class="product-card">

<img src="uploads/<?php echo $product_image; ?>" alt="<?php echo $product_name; ?>">

<h3><?php echo $product_name; ?></h3>

<p class="product-price">
R<?php echo number_format($row['price'], 2); ?>
</p>

<p>
Category: <?php echo $product_category; ?>
</p>

<p class="stock-text">
Stock: <?php echo $quantity; ?>
</p>

<div class="business-mini-box">

<strong>
<?php echo !empty($row['business_name']) ? htmlspecialchars($row['business_name']) : "Business Profile Not Completed"; ?>
</strong>

<p>
<?php echo !empty($row['business_type']) ? htmlspecialchars($row['business_type']) : "Business type not provided"; ?>
</p>

<p>
<?php echo !empty($row['business_location']) ? htmlspecialchars($row['business_location']) : "Location not provided"; ?>
</p>

<?php if(strtolower($row['seller_verification_status']) == "approved" || strtolower($row['seller_verification_status']) == "verified"){ ?>

<span class="verified-badge">✔ Verified Seller</span>

<?php }else{ ?>

<span class="unverified-badge">Unverified Seller</span>

<?php } ?>

</div>

<div class="rating">

<?php

$rounded_rating = round($row['average_rating']);

for($i = 1; $i <= 5; $i++){
    echo ($i <= $rounded_rating) ? "★" : "☆";
}

?>

(<?php echo intval($row['total_reviews']); ?> Reviews)

</div>

<a href="<?php echo htmlspecialchars($product_details_link); ?>" class="view-btn">
View Product
</a>

<?php if($quantity > 0 && $row['user_id'] != $user_id){ ?>

<form method="POST" class="cart-form">

<input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

<div class="cart-quantity-row">

<label>Qty:</label>

<input
type="number"
name="cart_quantity"
min="1"
max="<?php echo $quantity; ?>"
value="1"
required>

</div>

<button type="submit" name="add_to_cart" class="cart-btn">
Add To Cart
</button>

</form>

<?php }elseif($row['user_id'] == $user_id){ ?>

<span class="disabled-cart-btn">
Your Product
</span>

<?php }else{ ?>

<span class="disabled-cart-btn">
Out Of Stock
</span>

<?php } ?>

</div>

<?php } ?>

<?php }else{ ?>

<div class="info-box">

<p>No approved products found.</p>

</div>

<?php } ?>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>
<a href="trading-rules.php">Trading Rules</a>
<a href="about.php">About</a>
<a href="safety.php">Safety Centre</a>
<a href="help.php">Help</a>

</nav>

<p>
Copyright © 2026 StreetMarket. All Rights Reserved.
</p>

</div>

</footer>

</body>

</html>