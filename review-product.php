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
$message_type = "error";

if(!isset($_GET['product_id']) || !isset($_GET['order_id'])){
    header("Location: orders.php");
    exit();
}

$product_id = intval($_GET['product_id']);
$order_id = intval($_GET['order_id']);

/* CHECK COMPLETED AND CONFIRMED ORDER */

$order_query = "
SELECT *
FROM orders
WHERE order_id='$order_id'
AND product_id='$product_id'
AND buyer_id='$user_id'
AND status='completed'
AND buyer_confirmed='Yes'
LIMIT 1
";

$order_result = mysqli_query($conn, $order_query);

if(!$order_result || mysqli_num_rows($order_result) == 0){
    die("You can only review this product after the order is completed and confirmed.");
}

$order = mysqli_fetch_assoc($order_result);

/* CHECK EXISTING REVIEW */

$check_review = "
SELECT review_id
FROM product_reviews
WHERE user_id='$user_id'
AND product_id='$product_id'
AND order_id='$order_id'
LIMIT 1
";

$review_result = mysqli_query($conn, $check_review);

if($review_result && mysqli_num_rows($review_result) > 0){
    die("You already reviewed this product for this order.");
}

/* GET PRODUCT */

$product_query = "
SELECT *
FROM product
WHERE product_id='$product_id'
LIMIT 1
";

$product_result = mysqli_query($conn, $product_query);

if(!$product_result || mysqli_num_rows($product_result) == 0){
    die("Product not found.");
}

$product = mysqli_fetch_assoc($product_result);

/* SUBMIT REVIEW */

if(isset($_POST['submit_review'])){

    $rating = intval($_POST['rating']);
    $review = mysqli_real_escape_string($conn, trim($_POST['review']));

    if($rating < 1 || $rating > 5 || $review == ""){

        $message = "Please complete all review fields.";

    }else{

        $insert_review = "
        INSERT INTO product_reviews(
            product_id,
            user_id,
            order_id,
            rating,
            review,
            created_at
        )
        VALUES(
            '$product_id',
            '$user_id',
            '$order_id',
            '$rating',
            '$review',
            NOW()
        )
        ";

        if(mysqli_query($conn, $insert_review)){

            if(function_exists("createNotification")){

                createNotification(
                    $conn,
                    $order['seller_id'],
                    "New Product Review",
                    "A buyer reviewed your product: ".$product['product_name'].".",
                    "product-details.php?id=".$product_id
                );

            }

            $_SESSION['success_message'] = "Review submitted successfully.";
            header("Location: order-tracking.php?id=".$order_id);
            exit();

        }else{

            $message = "Failed to submit review: " . mysqli_error($conn);

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Review Product | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.review-card{
max-width:750px;
margin:auto;
background:white;
padding:30px;
border-radius:18px;
box-shadow:0 2px 15px rgba(0,0,0,0.08);
}

.review-product{
display:flex;
gap:20px;
align-items:center;
margin-bottom:25px;
flex-wrap:wrap;
}

.review-product img{
width:180px;
height:180px;
object-fit:cover;
border-radius:14px;
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

textarea{
min-height:130px;
resize:vertical;
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

<a href="orders.php">Orders</a>
<a href="order-tracking.php?id=<?php echo $order_id; ?>">Back To Tracking</a>
<a href="dashboard.php">Dashboard</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="review-card">

<h2>Review Product</h2>

<br>

<div class="review-product">

<img
src="uploads/<?php echo htmlspecialchars($product['image']); ?>"
alt="<?php echo htmlspecialchars($product['product_name']); ?>">

<div>

<h3><?php echo htmlspecialchars($product['product_name']); ?></h3>

<p>Order #<?php echo $order_id; ?></p>

<p>You are reviewing a completed and confirmed order.</p>

</div>

</div>

<?php if($message != ""){ ?>

<div class="error-message">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<form method="POST">

<label>Rating</label>

<select name="rating" required>

<option value="">Select Rating</option>
<option value="5">★★★★★ Excellent</option>
<option value="4">★★★★ Very Good</option>
<option value="3">★★★ Good</option>
<option value="2">★★ Fair</option>
<option value="1">★ Poor</option>

</select>

<label>Review</label>

<textarea
name="review"
placeholder="Write your honest product review..."
required></textarea>

<br><br>

<button type="submit" name="submit_review">
Submit Review
</button>

</form>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

</body>

</html>