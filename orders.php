<?php
// starting php session
session_start();

// preventing mysqli fatal errors from breaking the page
mysqli_report(MYSQLI_REPORT_OFF);

// connecting to database
include("includes/db.php");

// including notification functions
if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

// protecting user session by checking if they are logged in 
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

// storing user session details
$user_id = intval($_SESSION['user_id']);

// page variables
$message = "";
$message_type = "success";

// checking if database table exists
function ordersTableExists($conn, $table){

    $table = mysqli_real_escape_string($conn, $table);

    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// checking if database column exists
function ordersColumnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// sending notifications safely
function sendOrderNotification($conn, $user_id, $notification_title, $notification_message, $link){

    $user_id = intval($user_id);

    if($user_id <= 0){
        return false;
    }

    // checking if notifications table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");

    if(!$table_check || mysqli_num_rows($table_check) == 0){
        return false;
    }

    // checking correct title column name because some databases use tittle instead of title
    $title_column = "";

    if(ordersColumnExists($conn, "notifications", "tittle")){
        $title_column = "tittle";
    }elseif(ordersColumnExists($conn, "notifications", "title")){
        $title_column = "title";
    }else{
        return false;
    }

    $notification_title = mysqli_real_escape_string($conn, $notification_title);
    $notification_message = mysqli_real_escape_string($conn, $notification_message);
    $link = mysqli_real_escape_string($conn, $link);

    // inserting notification
    $insert_notification = "
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES('$user_id', '$notification_title', '$notification_message', '$link', 'No', NOW())
    ";

    return mysqli_query($conn, $insert_notification);
}

// getting all product images for image preview
function getOrderProductImages($conn, $product_id, $fallback_image){

    $product_id = intval($product_id);
    $images = [];

    // checking if product_images table exists
    if(ordersTableExists($conn, "product_images")){

        // getting extra product images
        $images_query = "
        SELECT image_name
        FROM product_images
        WHERE product_id='$product_id'
        ORDER BY image_id ASC
        ";

        $images_result = mysqli_query($conn, $images_query);

        if($images_result && mysqli_num_rows($images_result) > 0){

            while($image = mysqli_fetch_assoc($images_result)){

                if(!empty($image['image_name'])){
                    $images[] = "uploads/" . $image['image_name'];
                }
            }
        }
    }

    // using product main image if no extra images are found
    if(empty($images) && !empty($fallback_image)){
        $images[] = "uploads/" . $fallback_image;
    }

    return $images;
}

// cancelling full order group
if(isset($_GET['cancel_group'])){

    $reference_order_id = intval($_GET['cancel_group']);

    // getting one order from the order group to confirm buyer ownership
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
        $order_group_id = mysqli_real_escape_string($conn, $reference_order['order_group_id']);
        $payment_method = strtolower(trim($reference_order['payment_method']));

        // setting correct payment status when order is cancelled
        if($payment_method == "card"){
            $new_payment_status = "refunded";
        }else{
            $new_payment_status = "cancelled";
        }

        if(empty($order_group_id)){

            $message = "Order group reference is missing.";
            $message_type = "error";

        }else{

            // getting all orders that belong to the same buyer, seller and order group
            $group_orders_query = "
            SELECT *
            FROM orders
            WHERE buyer_id='$user_id'
            AND seller_id='$seller_id'
            AND order_group_id='$order_group_id'
            AND status='pending'
            AND delivery_status='processing'
            ";

            $group_orders_result = mysqli_query($conn, $group_orders_query);

            // starting database transaction
            mysqli_begin_transaction($conn);

            $success = true;
            $cancelled_item_count = 0;

            if($group_orders_result && mysqli_num_rows($group_orders_result) > 0){

                while($order = mysqli_fetch_assoc($group_orders_result)){

                    // restoring product stock
                    $product_id = intval($order['product_id']);
                    $quantity = intval($order['quantity']);

                    if($quantity < 1){
                        $quantity = 1;
                    }

                    $restore_stock = "
                    UPDATE product
                    SET quantity = quantity + '$quantity', status='available'
                    WHERE product_id='$product_id'
                    ";

                    if(!mysqli_query($conn, $restore_stock)){
                        $success = false;
                        break;
                    }

                    $cancelled_item_count++;
                }

                if($success){

                    // updating full order group as cancelled
                    $cancel_orders = "
                    UPDATE orders
                    SET status='cancelled',
                        payment_status='$new_payment_status',
                        cancelled_by='buyer'
                    WHERE buyer_id='$user_id'
                    AND seller_id='$seller_id'
                    AND order_group_id='$order_group_id'
                    AND status='pending'
                    AND delivery_status='processing'
                    ";

                    if(mysqli_query($conn, $cancel_orders)){

                        mysqli_commit($conn);

                        // notifying seller that buyer cancelled the order
                        sendOrderNotification(
                            $conn,
                            $seller_id,
                            "Order Cancelled",
                            "A buyer cancelled order group " . $order_group_id . ". Items cancelled: " . $cancelled_item_count . ". Product stock has been restored.",
                            "manage-deliveries.php"
                        );

                        // notifying buyer that order was cancelled successfully
                        sendOrderNotification(
                            $conn,
                            $user_id,
                            "Order Cancelled Successfully",
                            "Your order group " . $order_group_id . " was cancelled successfully. Payment status: " . ucfirst($new_payment_status) . ".",
                            "orders.php"
                        );

                        header("Location: orders.php?cancelled=success");
                        exit();

                    }else{

                        mysqli_rollback($conn);

                        $message = "Failed to cancel order group.";
                        $message_type = "error";

                    }

                }else{

                    mysqli_rollback($conn);

                    $message = "Failed to restore product stock.";
                    $message_type = "error";

                }

            }else{

                mysqli_rollback($conn);

                $message = "Order group not found.";
                $message_type = "error";

            }
        }

    }else{

        $message = "This order cannot be cancelled anymore.";
        $message_type = "error";

    }
}

// displaying cancellation success message
if(isset($_GET['cancelled']) && $_GET['cancelled'] == "success"){
    $message = "Order cancelled successfully.";
    $message_type = "success";
}

// displaying checkout success message
if(isset($_SESSION['success_message'])){
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}

// getting buyer orders grouped by order group and seller
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
seller.business_name AS seller_business_name,
seller.full_name AS seller_name
FROM orders
INNER JOIN users AS seller ON orders.seller_id = seller.user_id
WHERE orders.buyer_id='$user_id'
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
seller.business_name,
seller.full_name
ORDER BY order_date DESC
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

.cancelled-status{
background:#fee2e2;
color:#991b1b;
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
cursor:pointer;
transition:0.2s;
}

.order-item img:hover{
transform:scale(1.04);
opacity:0.9;
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

.error-message{
background:#fee2e2;
color:#991b1b;
border-left:5px solid #dc2626;
padding:15px;
border-radius:10px;
margin-bottom:20px;
font-weight:bold;
}

.size-text{
font-size:14px;
color:#555;
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
padding:20px;
}

.image-modal-content{
position:relative;
max-width:850px;
width:100%;
text-align:center;
}

.image-modal-content img{
max-width:100%;
max-height:75vh;
object-fit:contain;
border-radius:12px;
background:white;
}

.close-modal{
position:absolute;
top:-45px;
right:0;
font-size:35px;
color:white;
cursor:pointer;
font-weight:bold;
}

.image-nav{
display:flex;
justify-content:space-between;
align-items:center;
margin-top:15px;
gap:15px;
}

.image-nav button{
background:white;
color:#111;
border:none;
padding:12px 18px;
border-radius:10px;
font-weight:bold;
cursor:pointer;
}

.image-counter{
color:white;
font-weight:bold;
}

@media(max-width:768px){

.order-item{
flex-direction:column;
align-items:flex-start;
}

.order-item img{
width:100%;
height:auto;
}

.track-btn,
.cancel-btn{
width:100%;
text-align:center;
}

.image-nav{
flex-direction:column;
}

.image-nav button{
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

<!-- Navigation bar link -->
<nav>

<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="cart.php">&#x1F6D2; Cart</a>
<a href="messages.php">&#x1F4AC; Chat</a>
<a href="notifications.php"> &#x1F514; Notifications</a>
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

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
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

if($group['status'] == "cancelled"){
    $status_class = "cancelled-status";
}

$reference_order_id = intval($group['reference_order_id']);
$seller_id = intval($group['seller_id']);
$order_group_id = mysqli_real_escape_string($conn, $group['order_group_id']);

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
WHERE orders.buyer_id='$user_id'
AND orders.seller_id='$seller_id'
AND orders.order_group_id='$order_group_id'
AND orders.status!='cancelled'
ORDER BY orders.order_id ASC
";

$items_result = mysqli_query($conn, $items_query);

$seller_display = !empty($group['seller_business_name']) ? $group['seller_business_name'] : $group['seller_name'];
$display_group_id = !empty($group['order_group_id']) ? $group['order_group_id'] : "SM".$reference_order_id;

?>

<div class="order-group-card">

<div class="order-header">

<div>

<h3>Order Group #<?php echo htmlspecialchars($display_group_id); ?></h3>

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

<?php
// getting image gallery for this product
$product_gallery_images = getOrderProductImages($conn, $item['product_id'], $item['image']);
$product_gallery_json = htmlspecialchars(json_encode($product_gallery_images), ENT_QUOTES, 'UTF-8');
?>

<div class="order-item">

<img
src="uploads/<?php echo htmlspecialchars($item['image']); ?>"
alt="<?php echo htmlspecialchars($item['product_name']); ?>"
onclick="openImageModal(<?php echo $product_gallery_json; ?>, 0)">

<div>

<h4><?php echo htmlspecialchars($item['product_name']); ?></h4>

<p>Category: <?php echo htmlspecialchars($item['category']); ?></p>

<?php if(!empty($item['selected_size'])){ ?>

<p class="size-text">Size / Option: <b><?php echo htmlspecialchars($item['selected_size']); ?></b></p>

<?php } ?>

<p>Quantity: <?php echo intval($item['quantity']); ?></p>

<p>Unit Price: R<?php echo number_format($item['price'], 2); ?></p>

<p>Order Line Total: R<?php echo number_format($item['total_amount'], 2); ?></p>

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

<p class="product-price">Total: R<?php echo number_format($group['group_total'], 2); ?></p>

<div class="order-actions">

<a href="order-tracking.php?id=<?php echo $reference_order_id; ?>" class="track-btn">Track Order Group</a>

<?php if($group['delivery_status'] == "processing" && $group['status'] == "pending"){ ?>

<a href="orders.php?cancel_group=<?php echo $reference_order_id; ?>" class="cancel-btn" onclick="return confirm('Cancel this full order group? Product stock will be restored.');">Cancel Full Order</a>

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

<!-- product image modal -->
<div id="imageModal" class="image-modal">

<div class="image-modal-content">

<span class="close-modal" onclick="closeImageModal()">&times;</span>

<img id="modalImage" src="" alt="Product Image">

<div class="image-nav">

<button type="button" onclick="previousImage()">Previous</button>

<span id="imageCounter" class="image-counter">1 / 1</span>

<button type="button" onclick="nextImage()">Next</button>

</div>

</div>

</div>

<footer>

<div class="container footer-container">

<p>Copyright © 2026 StreetMarket</p>

</div>

</footer>

<script>

let currentImages = [];
let currentImageIndex = 0;

// opening product image modal
function openImageModal(images, index){

    if(!images || images.length === 0){
        return;
    }

    currentImages = images;
    currentImageIndex = index;

    document.getElementById("imageModal").style.display = "flex";

    showCurrentImage();
}

// showing current image in modal
function showCurrentImage(){

    if(currentImages.length === 0){
        return;
    }

    document.getElementById("modalImage").src = currentImages[currentImageIndex];
    document.getElementById("imageCounter").innerHTML = (currentImageIndex + 1) + " / " + currentImages.length;
}

// moving to next product image
function nextImage(){

    if(currentImages.length === 0){
        return;
    }

    currentImageIndex++;

    if(currentImageIndex >= currentImages.length){
        currentImageIndex = 0;
    }

    showCurrentImage();
}

// moving to previous product image
function previousImage(){

    if(currentImages.length === 0){
        return;
    }

    currentImageIndex--;

    if(currentImageIndex < 0){
        currentImageIndex = currentImages.length - 1;
    }

    showCurrentImage();
}

// closing product image modal
function closeImageModal(){

    document.getElementById("imageModal").style.display = "none";
}

// closing modal when user clicks outside image
document.getElementById("imageModal").addEventListener("click", function(event){

    if(event.target === this){
        closeImageModal();
    }

});

// using keyboard arrows to scroll through images
document.addEventListener("keydown", function(event){

    if(document.getElementById("imageModal").style.display === "flex"){

        if(event.key === "Escape"){
            closeImageModal();
        }

        if(event.key === "ArrowRight"){
            nextImage();
        }

        if(event.key === "ArrowLeft"){
            previousImage();
        }
    }

});

</script>

</body>

</html>