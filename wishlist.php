<?php

session_start();

include("includes/db.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = "";
$message_type = "success";

/* REMOVE ITEM */

if(isset($_GET['remove'])){

    $wishlist_id = intval($_GET['remove']);

    $delete_query = "
    DELETE FROM wishlist
    WHERE wishlist_id='$wishlist_id'
    AND user_id='$user_id'
    ";

    if(mysqli_query($conn, $delete_query)){
        $message = "Product removed from wishlist.";
        $message_type = "success";
    }else{
        $message = "Failed to remove product.";
        $message_type = "error";
    }

}

/* ADD WISHLIST PRODUCT TO CART */

if(isset($_POST['add_to_cart'])){

    $product_id = intval($_POST['product_id']);
    $cart_quantity = intval($_POST['cart_quantity']);

    if($cart_quantity < 1){
        $cart_quantity = 1;
    }

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

        if($product['user_id'] == $user_id){

            $message = "You cannot add your own product to cart.";
            $message_type = "error";

        }else{

            if($cart_quantity > $available_stock){
                $cart_quantity = $available_stock;
            }

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
                    $message = "Cart quantity updated from wishlist.";
                    $message_type = "success";
                }else{
                    $message = "Failed to update cart.";
                    $message_type = "error";
                }

            }else{

                $insert_cart = "
                INSERT INTO cart(user_id, product_id, quantity)
                VALUES('$user_id', '$product_id', '$cart_quantity')
                ";

                if(mysqli_query($conn, $insert_cart)){
                    $message = "Product added to cart from wishlist.";
                    $message_type = "success";
                }else{
                    $message = "Failed to add product to cart.";
                    $message_type = "error";
                }

            }

        }

    }else{

        $message = "Product is no longer available.";
        $message_type = "error";

    }

}

/* GET WISHLIST */

$query = "
SELECT
wishlist.wishlist_id,
wishlist.created_at AS saved_at,
product.*,
users.business_name,
users.business_type,
users.business_location,
users.seller_verification_status
FROM wishlist
INNER JOIN product ON wishlist.product_id = product.product_id
INNER JOIN users ON product.user_id = users.user_id
WHERE wishlist.user_id='$user_id'
ORDER BY wishlist.created_at DESC
";

$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Wishlist | StreetMarket</title>

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

.wishlist-actions{
display:flex;
gap:10px;
flex-wrap:wrap;
margin-top:15px;
}

.wishlist-actions a{
text-decoration:none;
}

.wishlist-actions button,
.wishlist-actions input{
padding:12px 15px;
border:none;
border-radius:8px;
font-weight:bold;
}

.view-btn{
background:#111;
color:#fff;
cursor:pointer;
}

.remove-btn{
background:#c62828;
color:#fff;
cursor:pointer;
}

.cart-btn{
background:#2563eb;
color:#fff;
cursor:pointer;
}

.quantity-input{
width:80px;
border:1px solid #ddd !important;
text-align:center;
}

.empty-wishlist{
background:#fff;
padding:35px;
border-radius:14px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
text-align:center;
}

.unavailable-card{
opacity:0.7;
border:2px solid #fee2e2;
}

.unavailable-label{
display:inline-block;
background:#fee2e2;
color:#991b1b;
padding:7px 12px;
border-radius:30px;
font-size:13px;
font-weight:bold;
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
margin-bottom:4px;
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

.saved-date{
font-size:13px;
color:#666;
margin-top:8px;
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
<a href="cart.php">Cart</a>
<a href="orders.php">Orders</a>
<a href="messages.php">Messages</a>
<a href="notifications.php">Notifications</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="page-intro">

<h2>My Wishlist</h2>

<p>
Products you saved for later. You can view, remove, or move available products to cart.
</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="product-grid">

<?php if($result && mysqli_num_rows($result) > 0){ ?>

<?php while($row = mysqli_fetch_assoc($result)){ ?>

<?php

$is_available = true;

if(
    $row['status'] != "available" ||
    $row['moderation_status'] != "approved" ||
    intval($row['quantity']) <= 0
){
    $is_available = false;
}

?>

<div class="product-card <?php if(!$is_available){ echo 'unavailable-card'; } ?>">

<img
src="uploads/<?php echo htmlspecialchars($row['image']); ?>"
alt="<?php echo htmlspecialchars($row['product_name']); ?>">

<span class="product-badge">
<?php echo htmlspecialchars($row['category']); ?>
</span>

<h3><?php echo htmlspecialchars($row['product_name']); ?></h3>

<p class="product-price">
R<?php echo number_format($row['price'], 2); ?>
</p>

<p>
Stock: <?php echo intval($row['quantity']); ?>
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

<p class="saved-date">
Saved on <?php echo date("d M Y", strtotime($row['saved_at'])); ?>
</p>

<?php if(!$is_available){ ?>

<span class="unavailable-label">
No longer available
</span>

<?php } ?>

<div class="wishlist-actions">

<a href="product-details.php?id=<?php echo $row['product_id']; ?>">
<button class="view-btn">
View
</button>
</a>

<?php if($is_available && $row['user_id'] != $user_id){ ?>

<form method="POST">

<input type="hidden" name="product_id" value="<?php echo $row['product_id']; ?>">

<input
type="number"
name="cart_quantity"
class="quantity-input"
min="1"
max="<?php echo intval($row['quantity']); ?>"
value="1"
required>

<button type="submit" name="add_to_cart" class="cart-btn">
Add To Cart
</button>

</form>

<?php } ?>

<a
href="wishlist.php?remove=<?php echo $row['wishlist_id']; ?>"
onclick="return confirm('Remove this product from your wishlist?');">

<button class="remove-btn">
Remove
</button>

</a>

</div>

</div>

<?php } ?>

<?php }else{ ?>

<div class="empty-wishlist">

<h3>No Saved Products</h3>

<p>
You have not saved any products yet.
</p>

<br>

<a href="products.php">
<button class="view-btn">
Browse Products
</button>
</a>

</div>

<?php } ?>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>
<a href="safety.php">Safety Centre</a>
<a href="shipping-guide.php">Shipping Guide</a>
<a href="help.php">Help</a>

</nav>

<p>
Copyright © 2026 StreetMarket. All Rights Reserved.
</p>

</div>

</footer>

</body>

</html>