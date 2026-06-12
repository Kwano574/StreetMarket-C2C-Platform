<?php

session_start();

include("includes/db.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php?redirect=products.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = "";
$message_type = "success";

if(!isset($_GET['id'])){
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['id']);

$product_query = "
SELECT
product.*,
users.user_id AS seller_id,
users.business_name,
users.business_type,
users.business_location,
users.business_profile,
users.seller_verification_status
FROM product
INNER JOIN users ON product.user_id = users.user_id
WHERE product.product_id='$product_id'
AND product.status='available'
AND product.moderation_status='approved'
LIMIT 1
";

$product_result = mysqli_query($conn, $product_query);

if(!$product_result || mysqli_num_rows($product_result) == 0){
    die("Product not found, unavailable, or not approved.");
}

$product = mysqli_fetch_assoc($product_result);

/* ADD TO CART WITH QUANTITY */

if(isset($_POST['add_to_cart'])){

    $cart_quantity = intval($_POST['cart_quantity']);

    if($cart_quantity < 1){
        $cart_quantity = 1;
    }

    $available_stock = intval($product['quantity']);

    if($user_id == $product['seller_id']){
        $message = "You cannot add your own product to cart.";
        $message_type = "error";
    }
    elseif($available_stock <= 0){
        $message = "This product is out of stock.";
        $message_type = "error";
    }
    elseif($cart_quantity > $available_stock){
        $message = "You cannot add more than the available stock.";
        $message_type = "error";
    }
    else{

        $check_cart = "
        SELECT *
        FROM cart
        WHERE user_id='$user_id'
        AND product_id='$product_id'
        LIMIT 1
        ";

        $check_result = mysqli_query($conn, $check_cart);

        if($check_result && mysqli_num_rows($check_result) > 0){

            $cart_item = mysqli_fetch_assoc($check_result);
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
            }else{
                $message = "Failed to add product to cart.";
                $message_type = "error";
            }
        }
    }
}

/* PRODUCT IMAGES */

$product_images = [];

if(!empty($product['image'])){
    $product_images[] = $product['image'];
}

$extra_images_query = "
SELECT image_name
FROM product_images
WHERE product_id='$product_id'
ORDER BY image_id ASC
";

$extra_images_result = mysqli_query($conn, $extra_images_query);

if($extra_images_result && mysqli_num_rows($extra_images_result) > 0){
    while($extra = mysqli_fetch_assoc($extra_images_result)){
        if(!in_array($extra['image_name'], $product_images)){
            $product_images[] = $extra['image_name'];
        }
    }
}

if(count($product_images) == 0){
    $product_images[] = "default-product.jpg";
}

/* RECENTLY VIEWED */

mysqli_query($conn, "
DELETE FROM recently_viewed
WHERE user_id='$user_id'
AND product_id='$product_id'
");

mysqli_query($conn, "
INSERT INTO recently_viewed(user_id, product_id)
VALUES('$user_id', '$product_id')
");

/* REVIEWS */

$reviews_query = "
SELECT product_reviews.*, users.full_name, users.profile_image
FROM product_reviews
INNER JOIN users ON product_reviews.user_id = users.user_id
WHERE product_reviews.product_id='$product_id'
ORDER BY product_reviews.created_at DESC
";

$reviews_result = mysqli_query($conn, $reviews_query);

$rating_query = "
SELECT AVG(rating) AS average_rating, COUNT(review_id) AS total_reviews
FROM product_reviews
WHERE product_id='$product_id'
";

$rating_result = mysqli_query($conn, $rating_query);
$rating_data = mysqli_fetch_assoc($rating_result);

$average_rating = round((float)$rating_data['average_rating'], 1);
$total_reviews = intval($rating_data['total_reviews']);

$category = mysqli_real_escape_string($conn, $product['category']);

$related_query = "
SELECT *
FROM product
WHERE category='$category'
AND product_id != '$product_id'
AND status='available'
AND moderation_status='approved'
AND quantity > 0
ORDER BY created_at DESC
LIMIT 4
";

$related_result = mysqli_query($conn, $related_query);

$recent_query = "
SELECT recently_viewed.viewed_at, product.*
FROM recently_viewed
INNER JOIN product ON recently_viewed.product_id = product.product_id
WHERE recently_viewed.user_id='$user_id'
AND product.product_id != '$product_id'
AND product.status='available'
AND product.moderation_status='approved'
AND product.quantity > 0
GROUP BY product.product_id
ORDER BY recently_viewed.viewed_at DESC
LIMIT 4
";

$recent_result = mysqli_query($conn, $recent_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?php echo htmlspecialchars($product['product_name']); ?> | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.success-message{
background:#dcfce7;
color:#166534;
border-left:5px solid #16a34a;
padding:15px;
border-radius:10px;
margin:20px auto;
font-weight:bold;
}

.error-message{
background:#fee2e2;
color:#991b1b;
border-left:5px solid #dc2626;
padding:15px;
border-radius:10px;
margin:20px auto;
font-weight:bold;
}

.product-details-layout{
display:grid;
grid-template-columns:1fr 1fr;
gap:40px;
align-items:start;
}

.details-image-box,
.details-content{
background:#fff;
padding:20px;
border-radius:18px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

.image-carousel{
position:relative;
width:100%;
overflow:hidden;
border-radius:14px;
background:#f5f5f5;
}

.carousel-image{
width:100%;
height:500px;
object-fit:cover;
display:none;
border-radius:14px;
cursor:zoom-in;
}

.carousel-image.active{
display:block;
}

.carousel-btn{
position:absolute;
top:50%;
transform:translateY(-50%);
background:rgba(0,0,0,0.65);
color:white;
border:none;
width:42px;
height:42px;
border-radius:50%;
cursor:pointer;
font-size:24px;
z-index:5;
}

.carousel-prev{left:12px;}
.carousel-next{right:12px;}

.carousel-count{
position:absolute;
bottom:12px;
right:12px;
background:rgba(0,0,0,0.7);
color:white;
padding:6px 12px;
border-radius:20px;
font-size:13px;
}

.image-modal{
display:none;
position:fixed;
z-index:9999;
left:0;
top:0;
width:100%;
height:100%;
background:rgba(0,0,0,0.88);
align-items:center;
justify-content:center;
padding:30px;
}

.image-modal img{
max-width:95%;
max-height:90vh;
border-radius:12px;
background:white;
box-shadow:0 4px 25px rgba(0,0,0,0.4);
}

.close-modal{
position:absolute;
top:20px;
right:30px;
color:white;
font-size:38px;
font-weight:bold;
cursor:pointer;
}

.modal-caption{
position:absolute;
bottom:20px;
color:white;
font-size:15px;
text-align:center;
}

.product-price{
font-size:34px;
font-weight:bold;
margin:20px 0;
}

.details-description{
line-height:1.8;
color:#555;
margin-bottom:30px;
}

.quick-info{
display:grid;
grid-template-columns:repeat(2,1fr);
gap:15px;
margin-bottom:30px;
}

.quick-box{
background:#f5f5f5;
padding:16px;
border-radius:12px;
}

.quick-box strong{
display:block;
margin-bottom:6px;
}

.rating-row{
display:flex;
align-items:center;
gap:12px;
margin-top:15px;
margin-bottom:20px;
}

.rating-stars{
color:orange;
font-size:22px;
}

.details-buttons{
display:flex;
gap:15px;
margin-top:30px;
flex-wrap:wrap;
}

.details-buttons button{
padding:14px 22px;
border:none;
border-radius:10px;
font-size:15px;
font-weight:bold;
cursor:pointer;
}

.cart-btn{
background:#111;
color:white;
}

.message-btn{
background:#2563eb;
color:white;
}

.save-btn{
background:#008000;
color:white;
}

.quantity-box{
margin-top:20px;
}

.quantity-box input{
width:100px;
padding:12px;
border:1px solid #ddd;
border-radius:8px;
text-align:center;
}

.business-box{
background:#f8fafc;
padding:22px;
border-radius:14px;
margin-top:25px;
border:1px solid #e2e8f0;
}

.business-box h3{
margin-bottom:12px;
}

.business-info{
margin-bottom:10px;
}

.business-info strong{
display:block;
color:#0f172a;
}

.verified-badge{
display:inline-block;
background:#16a34a;
color:white;
padding:8px 14px;
border-radius:30px;
font-weight:bold;
font-size:14px;
margin-top:8px;
}

.unverified-badge{
display:inline-block;
background:#f59e0b;
color:white;
padding:8px 14px;
border-radius:30px;
font-weight:bold;
font-size:14px;
margin-top:8px;
}

.private-note{
background:#eff6ff;
color:#1e3a8a;
border-left:5px solid #2563eb;
padding:12px;
border-radius:10px;
margin-top:15px;
font-size:14px;
}

.review-card{
background:white;
padding:20px;
border-radius:14px;
margin-bottom:20px;
box-shadow:0 2px 10px rgba(0,0,0,0.06);
}

.review-top{
display:flex;
align-items:center;
gap:15px;
margin-bottom:15px;
}

.review-avatar{
width:55px;
height:55px;
border-radius:50%;
object-fit:cover;
}

.review-stars{
font-size:18px;
color:orange;
}

@media(max-width:900px){

.product-details-layout{
grid-template-columns:1fr;
}

.quick-info{
grid-template-columns:1fr;
}

.details-buttons{
flex-direction:column;
}

.carousel-image{
height:350px;
}

.image-modal{
padding:15px;
}

.close-modal{
top:10px;
right:20px;
}

}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">
<img src="images/logo.png" alt="Logo">
<h1>StreetMarket</h1>
</div>

<nav>
<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="wishlist.php">Wishlist</a>
<a href="cart.php">Cart</a>
<a href="orders.php">Orders</a>
<a href="messages.php">Messages</a>
<a href="user-profile.php">Profile</a>
<a href="logout.php">Logout</a>
</nav>

</div>

</header>

<?php if($message != ""){ ?>

<div class="container">

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

</div>

<?php } ?>

<section class="section-spacing">

<div class="container">

<div class="product-details-layout">

<div class="details-image-box">

<div class="image-carousel" id="productDetailsCarousel">

<?php foreach($product_images as $index => $image_name){ ?>

<img
src="uploads/<?php echo htmlspecialchars($image_name); ?>"
alt="<?php echo htmlspecialchars($product['product_name']); ?>"
class="carousel-image <?php if($index == 0){ echo 'active'; } ?>"
onclick="openImageModal('uploads/<?php echo htmlspecialchars($image_name); ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')">

<?php } ?>

<?php if(count($product_images) > 1){ ?>

<button type="button" class="carousel-btn carousel-prev" onclick="moveCarousel('productDetailsCarousel', -1)">‹</button>
<button type="button" class="carousel-btn carousel-next" onclick="moveCarousel('productDetailsCarousel', 1)">›</button>

<div class="carousel-count">
1 / <?php echo count($product_images); ?>
</div>

<?php } ?>

</div>

</div>

<div class="details-content">

<span class="product-badge">
<?php echo htmlspecialchars($product['category']); ?>
</span>

<h2><?php echo htmlspecialchars($product['product_name']); ?></h2>

<div class="rating-row">

<div class="rating-stars">

<?php

for($i = 1; $i <= 5; $i++){
    echo ($i <= round($average_rating)) ? "★" : "☆";
}

?>

</div>

<p>
<?php echo $average_rating; ?> Rating (<?php echo $total_reviews; ?> Reviews)
</p>

</div>

<p class="product-price">
R<?php echo number_format($product['price'], 2); ?>
</p>

<p class="details-description">
<?php echo nl2br(htmlspecialchars($product['description'])); ?>
</p>

<div class="quick-info">

<div class="quick-box">
<strong>Condition</strong>
<?php echo htmlspecialchars($product['product_condition']); ?>
</div>

<div class="quick-box">
<strong>Product Location</strong>
<?php echo htmlspecialchars($product['location']); ?>
</div>

<div class="quick-box">
<strong>Delivery</strong>
<?php echo htmlspecialchars($product['delivery_available']); ?>
</div>

<div class="quick-box">
<strong>Available Stock</strong>
<?php echo intval($product['quantity']); ?>
</div>

</div>

<div class="business-box">

<h3>Seller Business Profile</h3>

<div class="business-info">
<strong>Business Name</strong>
<?php echo !empty($product['business_name']) ? htmlspecialchars($product['business_name']) : "Business profile not completed"; ?>
</div>

<div class="business-info">
<strong>Business Type</strong>
<?php echo !empty($product['business_type']) ? htmlspecialchars($product['business_type']) : "Not provided"; ?>
</div>

<div class="business-info">
<strong>Business Location</strong>
<?php echo !empty($product['business_location']) ? htmlspecialchars($product['business_location']) : "Not provided"; ?>
</div>

<div class="business-info">
<strong>Business Description</strong>
<?php echo !empty($product['business_profile']) ? nl2br(htmlspecialchars($product['business_profile'])) : "No business profile description provided."; ?>
</div>

<?php if(strtolower($product['seller_verification_status']) == "verified" || strtolower($product['seller_verification_status']) == "approved"){ ?>

<span class="verified-badge">✔ Verified Seller</span>

<?php }else{ ?>

<span class="unverified-badge">Unverified Seller</span>

<?php } ?>

<div class="private-note">
For buyer safety and seller privacy, StreetMarket displays the seller’s business profile instead of private ID, banking, address or personal account details.
</div>

</div>

<div class="details-buttons">

<?php if($product['seller_id'] != $user_id && intval($product['quantity']) > 0){ ?>

<form method="POST">

<div class="quantity-box">

<label>Quantity:</label>

<input
type="number"
name="cart_quantity"
min="1"
max="<?php echo intval($product['quantity']); ?>"
value="1"
required>

</div>

<br>

<button type="submit" name="add_to_cart" class="cart-btn">
Add To Cart
</button>

</form>

<?php }elseif($product['seller_id'] == $user_id){ ?>

<button class="cart-btn" disabled>
Your Product
</button>

<?php }else{ ?>

<button class="cart-btn" disabled>
Out Of Stock
</button>

<?php } ?>

<a href="messages.php?user=<?php echo $product['seller_id']; ?>&product=<?php echo $product_id; ?>">
<button class="message-btn">
Message Business
</button>
</a>

<a href="wishlist-action.php?add=<?php echo $product_id; ?>">
<button class="save-btn">
Save Product
</button>
</a>

</div>

</div>

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">
<h2>Customer Reviews</h2>
<p>See what buyers are saying about this product.</p>
</div>

<?php if($reviews_result && mysqli_num_rows($reviews_result) > 0){ ?>

<?php while($review = mysqli_fetch_assoc($reviews_result)){ ?>

<div class="review-card">

<div class="review-top">

<?php if(!empty($review['profile_image'])){ ?>
<img src="uploads/profile/<?php echo htmlspecialchars($review['profile_image']); ?>" class="review-avatar">
<?php }else{ ?>
<img src="images/default-profile.png" class="review-avatar">
<?php } ?>

<div>

<h4><?php echo htmlspecialchars($review['full_name']); ?></h4>

<div class="review-stars">

<?php

for($i = 1; $i <= 5; $i++){
    echo ($i <= $review['rating']) ? "★" : "☆";
}

?>

</div>

</div>

</div>

<p>
<?php echo nl2br(htmlspecialchars($review['review'])); ?>
</p>

</div>

<?php } ?>

<?php }else{ ?>

<div class="info-box">
<p>No reviews yet for this product.</p>
</div>

<?php } ?>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">
<h2>Related Products</h2>
<p>Explore similar products.</p>
</div>

<div class="product-grid">

<?php if($related_result && mysqli_num_rows($related_result) > 0){ ?>

<?php while($related = mysqli_fetch_assoc($related_result)){ ?>

<div class="product-card">

<img src="uploads/<?php echo htmlspecialchars($related['image']); ?>" alt="Product">

<span class="product-badge">
<?php echo htmlspecialchars($related['category']); ?>
</span>

<h3><?php echo htmlspecialchars($related['product_name']); ?></h3>

<p class="product-price">
R<?php echo number_format($related['price'], 2); ?>
</p>

<a href="product-details.php?id=<?php echo $related['product_id']; ?>">
<button>View Product</button>
</a>

</div>

<?php } ?>

<?php }else{ ?>

<div class="info-box">
<p>No related products found.</p>
</div>

<?php } ?>

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">
<h2>Recently Viewed</h2>
<p>Continue exploring products you viewed recently.</p>
</div>

<div class="product-grid">

<?php if($recent_result && mysqli_num_rows($recent_result) > 0){ ?>

<?php while($recent = mysqli_fetch_assoc($recent_result)){ ?>

<div class="product-card">

<img src="uploads/<?php echo htmlspecialchars($recent['image']); ?>" alt="Product">

<span class="product-badge">
<?php echo htmlspecialchars($recent['category']); ?>
</span>

<h3><?php echo htmlspecialchars($recent['product_name']); ?></h3>

<p class="product-price">
R<?php echo number_format($recent['price'], 2); ?>
</p>

<a href="product-details.php?id=<?php echo $recent['product_id']; ?>">
<button>View Product</button>
</a>

</div>

<?php } ?>

<?php }else{ ?>

<div class="info-box">
<p>No recently viewed products yet.</p>
</div>

<?php } ?>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>
<a href="about.php">About</a>
<a href="help.php">Help</a>
<a href="safety.php">Safety Centre</a>
</nav>

<p>Copyright © 2026 StreetMarket</p>

</div>

</footer>

<div class="image-modal" id="imageModal" onclick="closeImageModal()">

<span class="close-modal">&times;</span>

<img id="modalImage" src="" alt="Product Image">

<div class="modal-caption" id="modalCaption"></div>

</div>

<script>

function moveCarousel(carouselId, direction){

    const carousel = document.getElementById(carouselId);
    const images = carousel.querySelectorAll(".carousel-image");
    const countBox = carousel.querySelector(".carousel-count");
    let activeIndex = 0;

    images.forEach(function(img, index){
        if(img.classList.contains("active")){
            activeIndex = index;
        }
    });

    images[activeIndex].classList.remove("active");

    let newIndex = activeIndex + direction;

    if(newIndex < 0){
        newIndex = images.length - 1;
    }

    if(newIndex >= images.length){
        newIndex = 0;
    }

    images[newIndex].classList.add("active");

    if(countBox){
        countBox.innerHTML = (newIndex + 1) + " / " + images.length;
    }
}

function openImageModal(imageSrc, caption){

    document.getElementById("modalImage").src = imageSrc;
    document.getElementById("modalCaption").innerHTML = caption;
    document.getElementById("imageModal").style.display = "flex";

}

function closeImageModal(){

    document.getElementById("imageModal").style.display = "none";
    document.getElementById("modalImage").src = "";

}

document.addEventListener("keydown", function(event){

    if(event.key === "Escape"){
        closeImageModal();
    }

});

</script>

</body>

</html>