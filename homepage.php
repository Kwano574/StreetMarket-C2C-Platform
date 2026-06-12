<?php

session_start();

unset($_SESSION['user_id']);
unset($_SESSION['full_name']);

include("includes/db.php");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StreetMarket | Buy & Sell Safely</title>
<link rel="stylesheet" href="css/style.css">

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<div>

<h1>StreetMarket</h1>

<p>South African C2C Marketplace</p>

</div>

</div>

<nav>
<a href="register.php">Register</a>
<a href="login.php">Login</a>
</nav>

</div>

</header>

<section class="search-section section-spacing">

<div class="container">
<!-- Search section -->
<h2>Search Products</h2>
<p>Search for approved electronics, fashion, furniture, vehicles and more.</p>
<form class="search-row" action="login.php" method="GET">
<input type="hidden" name="redirect" value="products.php">
<input type="search" name="search" placeholder="Search products, categories or sellers" id="searchInput" required>
<button type="submit">Search</button>
</form>
</div>

</section>

<section class="hero">

<div class="container hero-content">

<div class="hero-text">

<!--Intro message-->
<h2>Buy &amp; Sell Safely Across South Africa</h2>

<p>
StreetMarket helps buyers and verified sellers trade products safely using account verification, admin product approval, secure communication, delivery tracking and monitored marketplace activity.
</p>

<div class="hero-buttons">

<a href="register.php" class="primary-btn">Create Account</a>

<a href="login.php?redirect=products.php" class="secondary-btn">Browse Products</a>

</div>

</div>

<div class="hero-image">

<img src="images/banner.png" alt="Marketplace Banner">

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>Why Use StreetMarket?</h2>

</div>

<div class="feature-grid">

<div class="feature-card">

<div class="feature-icon">&#9989;</div>

<h3>Verified Sellers</h3>

<p>
Seller verification helps buyers identify trusted sellers and reduces unsafe trading.
</p>

</div>

<div class="feature-card">

<div class="feature-icon">&#128274;</div>

<h3>Admin Approved Products</h3>

<p>
Products are reviewed before being displayed to buyers on the marketplace.
</p>

</div>

<div class="feature-card">

<div class="feature-icon">&#128666;</div>

<h3>Delivery Tracking</h3>

<p>
Buyers and sellers can track delivery progress until an order is confirmed as received.
</p>

</div>

<div class="feature-card">

<div class="feature-icon">&#128172;</div>

<h3>Secure Communication</h3>

<p>
Users can communicate through the built-in StreetMarket messaging system.
</p>

</div>

</div>

</div>

</section>

<section class="section-spacing category-section">

<div class="container">

<div class="section-title">

<h2>Shop By Category</h2>

<a href="login.php?redirect=products.php" class="view-products-link">View All Products</a>

</div>

<div class="category-grid">
<!-- Displaying products from database-->
<?php

$category_sql = "
SELECT DISTINCT category
FROM product
WHERE status='available'
AND moderation_status='approved'
AND quantity > 0
AND category IS NOT NULL
AND category != ''
ORDER BY category ASC
";

$category_result = mysqli_query($conn, $category_sql);

$category_images = [
    "Electronics" => "images/electronics.jpg",
    "Fashion" => "images/clothing.jpg",
    "Furniture" => "images/home.jpg",
    "Home" => "images/home.jpg",
    "Home Appliances" => "images/home.jpg",
    "Motors" => "images/motors.jpg",
    "Vehicles" => "images/motors.jpg",
    "Sports" => "images/sports.jpg"
];

if($category_result && mysqli_num_rows($category_result) > 0){

    while($catRow = mysqli_fetch_assoc($category_result)){

        $catNameRaw = $catRow['category'];
        $catName = htmlspecialchars($catNameRaw);
        $catImg = isset($category_images[$catNameRaw]) ? $category_images[$catNameRaw] : "images/placeholder.jpg";

?>

<a href="login.php?redirect=products.php?category=<?php echo urlencode($catNameRaw); ?>" class="category-card">

<img src="<?php echo htmlspecialchars($catImg); ?>" alt="<?php echo $catName; ?>">

<h3><?php echo $catName; ?></h3>

<p>Explore approved <?php echo $catName; ?> products.</p>

</a>

<?php

    }

}else{

?>

<div class="info-box">

<p>No approved categories are available yet.</p>

</div>

<?php

}

?>

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>Latest Approved Products</h2>

<a href="login.php?redirect=products.php" class="view-products-link">View All Products</a>

</div>

<div class="product-grid">

<?php

$latest_sql = "
SELECT
product.product_id,
product.product_name,
product.price,
product.image,
product.category,
product.quantity,
users.full_name
FROM product
INNER JOIN users ON product.user_id = users.user_id
WHERE product.status='available'
AND product.moderation_status='approved'
AND product.quantity > 0
ORDER BY product.created_at DESC
LIMIT 6
";

$latest_result = mysqli_query($conn, $latest_sql);

if($latest_result && mysqli_num_rows($latest_result) > 0){

    while($prod = mysqli_fetch_assoc($latest_result)){

        $prodId = intval($prod['product_id']);
        $prodName = htmlspecialchars($prod['product_name']);
        $price = number_format((float)$prod['price'], 2);
        $image = htmlspecialchars($prod['image']);
        $category = htmlspecialchars($prod['category']);
        $seller = htmlspecialchars($prod['full_name']);
        $quantity = intval($prod['quantity']);

?>

<div class="product-card">

<span class="product-badge">Approved Product</span>

<img src="uploads/<?php echo $image; ?>" alt="<?php echo $prodName; ?>">

<h3><?php echo $prodName; ?></h3>

<p class="product-price">R<?php echo $price; ?></p>

<p>Category: <?php echo $category; ?></p>

<p>Seller: <?php echo $seller; ?></p>

<p>Stock: <?php echo $quantity; ?></p>

<a href="login.php?redirect=product-details.php?id=<?php echo $prodId; ?>">

<button>View Product</button>

</a>

</div>

<?php

    }

}else{

?>

<div class="info-box">

<p>No approved products are currently available.</p>

</div>

<?php

}

?>

</div>

</div>

</section>

<section class="section-spacing how-section">

<div class="container">

<div class="section-title">

<h2>How StreetMarket Works</h2>

</div>

<div class="steps-grid">

<div class="step-card">

<div class="step-number">Step 1</div>

<h3>Create Account</h3>

<p>Register and login to access marketplace products and user features.</p>

</div>

<div class="step-card">

<div class="step-number">Step 2</div>

<h3>Browse or Sell</h3>

<p>Buyers can browse approved products, while verified sellers can upload listings for admin approval.</p>

</div>

<div class="step-card">

<div class="step-number">Step 3</div>

<h3>Order and Track</h3>

<p>Users can place orders, communicate, track delivery, confirm receipt and review products.</p>

</div>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>

<a href="safety.php">Safety Centre</a>

<a href="sellercenter.php">Seller Support</a>

<a href="shipping-guide.php">Shipping Guide</a>

<a href="policies.php">Policies</a>

<a href="help.php">Help &amp; Contact</a>

</nav>

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

<script src="js/script.js"></script>

</body>

</html>