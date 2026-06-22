<?php

// starting php session
session_start();

// preventing mysqli fatal errors from causing 500 page
mysqli_report(MYSQLI_REPORT_OFF);

// connecting to database
if(file_exists("../includes/db.php")){
    include("../includes/db.php");
    $base_path = "../";
}else{
    include("includes/db.php");
    $base_path = "";
}

// protecting admin session
if(!isset($_SESSION['admin_id'])){
    header("Location: admin-login.php");
    exit();
}

$message = "";
$message_type = "success";

// showing message after redirect
if(isset($_SESSION['admin_message'])){
    $message = $_SESSION['admin_message'];
    $message_type = isset($_SESSION['admin_message_type']) ? $_SESSION['admin_message_type'] : "success";

    unset($_SESSION['admin_message']);
    unset($_SESSION['admin_message_type']);
}

// checking if column exists
function columnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// sending seller notification using the existing notifications table
function sendSellerNotification($conn, $seller_id, $notification_title, $notification_message, $link){

    $seller_id = intval($seller_id);

    if($seller_id <= 0){
        return false;
    }

    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");

    if(!$table_check || mysqli_num_rows($table_check) == 0){
        return false;
    }

    $title_column = "";

    if(columnExists($conn, "notifications", "tittle")){
        $title_column = "tittle";
    }elseif(columnExists($conn, "notifications", "title")){
        $title_column = "title";
    }else{
        return false;
    }

    $notification_title = mysqli_real_escape_string($conn, $notification_title);
    $notification_message = mysqli_real_escape_string($conn, $notification_message);
    $link = mysqli_real_escape_string($conn, $link);

    $insert_notification = "
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES('$seller_id', '$notification_title', '$notification_message', '$link', 'No', NOW())
    ";

    return mysqli_query($conn, $insert_notification);
}

// approving, rejecting, removing or restoring products
if(isset($_POST['product_id']) && isset($_POST['action'])){

    $product_id = intval($_POST['product_id']);
    $action = trim($_POST['action']);
    $admin_reason = "";

    if(isset($_POST['admin_reason'])){
        $admin_reason = trim($_POST['admin_reason']);
    }

    $product_query = "
    SELECT product_id, product_name, user_id
    FROM product
    WHERE product_id='$product_id'
    LIMIT 1
    ";

    $product_result = mysqli_query($conn, $product_query);

    if($product_result && mysqli_num_rows($product_result) > 0){

        $product = mysqli_fetch_assoc($product_result);

        $seller_id = intval($product['user_id']);
        $product_name = $product['product_name'];

        if($action == "approve"){

            $update_query = "
            UPDATE product
            SET moderation_status='approved',
                status='available'
            WHERE product_id='$product_id'
            ";

            if(mysqli_query($conn, $update_query)){

                sendSellerNotification(
                    $conn,
                    $seller_id,
                    "Product Approved",
                    "Your product '" . $product_name . "' has been approved and is now available on StreetMarket.",
                    "my-listings.php"
                );

                $_SESSION['admin_message'] = "Product approved successfully and seller was notified.";
                $_SESSION['admin_message_type'] = "success";

            }else{

                $_SESSION['admin_message'] = "Product could not be approved.";
                $_SESSION['admin_message_type'] = "error";

            }

        }elseif($action == "reject"){

            if($admin_reason == ""){

                $_SESSION['admin_message'] = "Please provide a reason before rejecting this product.";
                $_SESSION['admin_message_type'] = "error";

            }else{

                $update_query = "
                UPDATE product
                SET moderation_status='rejected',
                    status='unavailable'
                WHERE product_id='$product_id'
                ";

                if(mysqli_query($conn, $update_query)){

                    sendSellerNotification(
                        $conn,
                        $seller_id,
                        "Product Rejected",
                        "Your product '" . $product_name . "' was rejected by admin. Reason: " . $admin_reason,
                        "my-listings.php"
                    );

                    $_SESSION['admin_message'] = "Product rejected successfully and seller was notified with the reason.";
                    $_SESSION['admin_message_type'] = "success";

                }else{

                    $_SESSION['admin_message'] = "Product could not be rejected.";
                    $_SESSION['admin_message_type'] = "error";

                }
            }

        }elseif($action == "remove"){

            if($admin_reason == ""){

                $_SESSION['admin_message'] = "Please provide a reason before removing this product.";
                $_SESSION['admin_message_type'] = "error";

            }else{

                $update_query = "
                UPDATE product
                SET moderation_status='removed',
                    status='removed'
                WHERE product_id='$product_id'
                ";

                if(mysqli_query($conn, $update_query)){

                    sendSellerNotification(
                        $conn,
                        $seller_id,
                        "Product Removed",
                        "Your product '" . $product_name . "' has been removed from StreetMarket by admin. Reason: " . $admin_reason,
                        "my-listings.php"
                    );

                    $_SESSION['admin_message'] = "Product removed successfully and seller was notified with the reason.";
                    $_SESSION['admin_message_type'] = "success";

                }else{

                    $_SESSION['admin_message'] = "Product could not be removed.";
                    $_SESSION['admin_message_type'] = "error";

                }
            }

        }elseif($action == "restore"){

            $update_query = "
            UPDATE product
            SET moderation_status='pending',
                status='unavailable'
            WHERE product_id='$product_id'
            ";

            if(mysqli_query($conn, $update_query)){

                sendSellerNotification(
                    $conn,
                    $seller_id,
                    "Product Restored",
                    "Your product '" . $product_name . "' has been restored and is now pending admin review again.",
                    "my-listings.php"
                );

                $_SESSION['admin_message'] = "Product restored successfully and seller was notified.";
                $_SESSION['admin_message_type'] = "success";

            }else{

                $_SESSION['admin_message'] = "Product could not be restored.";
                $_SESSION['admin_message_type'] = "error";

            }

        }else{

            $_SESSION['admin_message'] = "Invalid product action.";
            $_SESSION['admin_message_type'] = "error";

        }

    }else{

        $_SESSION['admin_message'] = "Product not found.";
        $_SESSION['admin_message_type'] = "error";

    }

    header("Location: manage-products.php");
    exit();
}

// filtering and searching products
$filter = "";
$search = "";

if(isset($_GET['filter'])){
    $filter = mysqli_real_escape_string($conn, trim($_GET['filter']));
}

if(isset($_GET['search'])){
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
}

$where_parts = [];

if($filter == "pending"){
    $where_parts[] = "product.moderation_status='pending'";
}elseif($filter == "approved"){
    $where_parts[] = "product.moderation_status='approved'";
}elseif($filter == "rejected"){
    $where_parts[] = "product.moderation_status='rejected'";
}elseif($filter == "removed"){
    $where_parts[] = "product.moderation_status='removed'";
}

if($search != ""){
    $where_parts[] = "
    (
        product.product_name LIKE '%$search%'
        OR product.category LIKE '%$search%'
        OR users.full_name LIKE '%$search%'
        OR users.email LIKE '%$search%'
    )
    ";
}

$where_sql = "";

if(count($where_parts) > 0){
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

// getting products
$products_query = "
SELECT
product.product_id,
product.product_name,
product.description,
product.category,
product.price,
product.quantity,
product.image,
product.status,
product.moderation_status,
product.created_at,
users.user_id,
users.full_name,
users.email
FROM product
INNER JOIN users ON product.user_id = users.user_id
$where_sql
ORDER BY product.created_at DESC
";

$products_result = mysqli_query($conn, $products_query);

// getting product images
$product_images = [];

$images_query = "
SELECT product_id, image_name
FROM product_images
ORDER BY product_id ASC
";

$images_result = mysqli_query($conn, $images_query);

if($images_result){
    while($img = mysqli_fetch_assoc($images_result)){
        $pid = intval($img['product_id']);

        if(!isset($product_images[$pid])){
            $product_images[$pid] = [];
        }

        $product_images[$pid][] = $img['image_name'];
    }
}

// getting product statistics
$total_products = 0;
$pending_products = 0;
$approved_products = 0;
$rejected_products = 0;
$removed_products = 0;

$stats_result = mysqli_query($conn, "
SELECT
COUNT(*) AS total_products,
SUM(CASE WHEN moderation_status='pending' THEN 1 ELSE 0 END) AS pending_products,
SUM(CASE WHEN moderation_status='approved' THEN 1 ELSE 0 END) AS approved_products,
SUM(CASE WHEN moderation_status='rejected' THEN 1 ELSE 0 END) AS rejected_products,
SUM(CASE WHEN moderation_status='removed' THEN 1 ELSE 0 END) AS removed_products
FROM product
");

if($stats_result){
    $stats = mysqli_fetch_assoc($stats_result);

    $total_products = intval($stats['total_products']);
    $pending_products = intval($stats['pending_products']);
    $approved_products = intval($stats['approved_products']);
    $rejected_products = intval($stats['rejected_products']);
    $removed_products = intval($stats['removed_products']);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Products | StreetMarket Admin</title>

<link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">

<style>

.admin-container{
max-width:1400px;
margin:auto;
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

.stats-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
gap:15px;
margin-bottom:25px;
}

.stat-card{
background:white;
padding:18px;
border-radius:14px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

.stat-card h3{
margin:0;
font-size:15px;
color:#555;
}

.stat-card p{
font-size:28px;
font-weight:bold;
margin:10px 0 0;
}

.filter-box{
background:white;
padding:18px;
border-radius:14px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
margin-bottom:25px;
display:flex;
gap:10px;
flex-wrap:wrap;
align-items:center;
}

.filter-box input,
.filter-box select{
padding:12px;
border:1px solid #ddd;
border-radius:8px;
min-width:220px;
}

.filter-box button{
padding:12px 18px;
border:none;
background:#111;
color:white;
border-radius:8px;
font-weight:bold;
cursor:pointer;
}

.clear-btn{
padding:12px 18px;
background:#777;
color:white;
border-radius:8px;
text-decoration:none;
font-weight:bold;
}

.table-wrapper{
overflow-x:auto;
background:#fff;
border-radius:14px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

.products-table{
width:100%;
border-collapse:collapse;
min-width:1250px;
background:#fff;
}

.products-table th,
.products-table td{
padding:14px;
border-bottom:1px solid #e5e7eb;
text-align:left;
vertical-align:top;
}

.products-table th{
background:#111;
color:#fff;
font-weight:bold;
}

.product-thumb{
width:95px;
height:95px;
object-fit:cover;
border-radius:10px;
border:1px solid #ddd;
cursor:pointer;
background:#f5f5f5;
display:block;
}

.image-hint{
font-size:12px;
color:#64748b;
margin-top:6px;
}

.product-name{
font-weight:bold;
font-size:16px;
margin-bottom:6px;
}

.small-text{
font-size:13px;
color:#64748b;
line-height:1.5;
}

.description-text{
max-width:260px;
line-height:1.5;
}

.status-badge{
display:inline-block;
padding:6px 12px;
border-radius:20px;
font-size:13px;
font-weight:bold;
}

.status-pending{
background:#fef3c7;
color:#92400e;
}

.status-approved{
background:#dcfce7;
color:#166534;
}

.status-rejected{
background:#fee2e2;
color:#991b1b;
}

.status-removed{
background:#e5e7eb;
color:#374151;
}

.action-buttons{
display:flex;
gap:8px;
flex-wrap:wrap;
}

.action-buttons form{
display:inline;
}

.action-btn{
border:none;
padding:9px 12px;
border-radius:8px;
font-weight:bold;
cursor:pointer;
font-size:13px;
}

.approve-btn{
background:#16a34a;
color:white;
}

.reject-btn{
background:#f59e0b;
color:white;
}

.remove-btn{
background:#c62828;
color:white;
}

.restore-btn{
background:#2563eb;
color:white;
}

.disabled-btn{
background:#9ca3af;
color:white;
cursor:not-allowed;
}

.empty-box{
background:white;
padding:25px;
border-radius:14px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

.image-modal{
display:none;
position:fixed;
z-index:9999;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,0.88);
align-items:center;
justify-content:center;
flex-direction:column;
padding:30px;
}

.image-modal img{
max-width:90%;
max-height:75vh;
object-fit:contain;
background:white;
border-radius:12px;
padding:10px;
}

.modal-controls{
margin-top:15px;
display:flex;
gap:12px;
align-items:center;
justify-content:center;
}

.modal-controls button{
border:none;
background:white;
color:#111;
padding:10px 16px;
border-radius:8px;
font-weight:bold;
cursor:pointer;
}

.image-count{
color:white;
font-weight:bold;
}

.close-modal{
position:absolute;
top:22px;
right:35px;
color:white;
font-size:40px;
font-weight:bold;
cursor:pointer;
}

.reason-modal{
display:none;
position:fixed;
z-index:10000;
top:0;
left:0;
width:100%;
height:100%;
background:rgba(0,0,0,0.75);
align-items:center;
justify-content:center;
padding:20px;
}

.reason-box{
background:white;
max-width:520px;
width:100%;
border-radius:14px;
padding:22px;
box-shadow:0 10px 35px rgba(0,0,0,0.25);
}

.reason-box h3{
margin-top:0;
margin-bottom:10px;
}

.reason-box p{
color:#555;
line-height:1.5;
}

.reason-box textarea{
width:100%;
height:120px;
border:1px solid #ddd;
border-radius:10px;
padding:12px;
resize:vertical;
font-size:14px;
}

.reason-actions{
display:flex;
justify-content:flex-end;
gap:10px;
margin-top:15px;
}

.reason-actions button{
border:none;
padding:10px 14px;
border-radius:8px;
font-weight:bold;
cursor:pointer;
}

.cancel-reason{
background:#777;
color:white;
}

.submit-reason{
background:#111;
color:white;
}

@media(max-width:768px){

.filter-box{
flex-direction:column;
align-items:stretch;
}

.filter-box input,
.filter-box select,
.filter-box button,
.clear-btn{
width:100%;
}

}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img src="<?php echo $base_path; ?>images/logo.png" alt="StreetMarket Logo">

<h1>StreetMarket Admin</h1>

</div>

<nav>

<a href="Admin-dashboard.php">Dashboard</a>

<a href="manage-users.php">Users</a>
<a href="admin-seller-verification.php">Seller Verification</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container admin-container">

<div class="page-intro">

<h2>Manage Products</h2>

<p>Review, approve, reject, remove or restore seller product listings from StreetMarket.</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="stats-grid">

<div class="stat-card">
<h3>Total Products</h3>
<p><?php echo $total_products; ?></p>
</div>

<div class="stat-card">
<h3>Pending</h3>
<p><?php echo $pending_products; ?></p>
</div>

<div class="stat-card">
<h3>Approved</h3>
<p><?php echo $approved_products; ?></p>
</div>

<div class="stat-card">
<h3>Rejected</h3>
<p><?php echo $rejected_products; ?></p>
</div>

<div class="stat-card">
<h3>Removed</h3>
<p><?php echo $removed_products; ?></p>
</div>

</div>

<form method="GET" action="manage-products.php" class="filter-box">

<input
type="search"
name="search"
placeholder="Search product, seller or email"
value="<?php echo htmlspecialchars($search); ?>">

<select name="filter">

<option value="">All Products</option>

<option value="pending" <?php if($filter == "pending"){ echo "selected"; } ?>>
Pending
</option>

<option value="approved" <?php if($filter == "approved"){ echo "selected"; } ?>>
Approved
</option>

<option value="rejected" <?php if($filter == "rejected"){ echo "selected"; } ?>>
Rejected
</option>

<option value="removed" <?php if($filter == "removed"){ echo "selected"; } ?>>
Removed
</option>

</select>

<button type="submit">Filter</button>

<a href="manage-products.php" class="clear-btn">Clear</a>

</form>

<?php if($products_result && mysqli_num_rows($products_result) > 0){ ?>

<div class="table-wrapper">

<table class="products-table">

<tr>
<th>ID</th>
<th>Image</th>
<th>Product</th>
<th>Seller</th>
<th>Price & Stock</th>
<th>Status</th>
<th>Description</th>
<th>Created</th>
<th>Action</th>
</tr>

<?php while($product = mysqli_fetch_assoc($products_result)){ ?>

<?php

$product_id = intval($product['product_id']);

$all_images = [];

if(isset($product_images[$product_id]) && count($product_images[$product_id]) > 0){
    $all_images = $product_images[$product_id];
}else{
    if(!empty($product['image'])){
        $all_images[] = $product['image'];
    }
}

$main_image = "";

if(count($all_images) > 0){
    $main_image = $all_images[0];
}

$moderation_status = strtolower($product['moderation_status']);

$status_class = "status-pending";

if($moderation_status == "approved"){
    $status_class = "status-approved";
}elseif($moderation_status == "rejected"){
    $status_class = "status-rejected";
}elseif($moderation_status == "removed"){
    $status_class = "status-removed";
}

$image_js_array = [];

foreach($all_images as $img){
    $image_js_array[] = $base_path . "uploads/" . basename($img);
}

$image_json = htmlspecialchars(json_encode($image_js_array), ENT_QUOTES, 'UTF-8');

?>

<tr>

<td>
#<?php echo $product_id; ?>
</td>

<td>

<?php if($main_image != ""){ ?>

<?php $main_img_path = $base_path . "uploads/" . basename($main_image); ?>

<img
src="<?php echo htmlspecialchars($main_img_path); ?>"
class="product-thumb"
alt="Product Image"
onclick='openImageGallery(<?php echo $image_json; ?>, 0)'>

<div class="image-hint">
Click to view <?php echo count($all_images); ?> image(s)
</div>

<?php }else{ ?>

<span class="small-text">No image</span>

<?php } ?>

</td>

<td>

<div class="product-name">
<?php echo htmlspecialchars($product['product_name']); ?>
</div>

<div class="small-text">
Category: <?php echo htmlspecialchars($product['category']); ?>
</div>

</td>

<td>

<strong><?php echo htmlspecialchars($product['full_name']); ?></strong>
<br>
<span class="small-text">
<?php echo htmlspecialchars($product['email']); ?>
</span>

</td>

<td>

<strong>R<?php echo number_format((float)$product['price'], 2); ?></strong>
<br>
<span class="small-text">
Stock: <?php echo intval($product['quantity']); ?>
</span>

</td>

<td>

<span class="status-badge <?php echo $status_class; ?>">
<?php echo ucfirst(htmlspecialchars($product['moderation_status'])); ?>
</span>

<br><br>

<span class="small-text">
Product status: <?php echo htmlspecialchars($product['status']); ?>
</span>

</td>

<td>

<div class="description-text">
<?php echo nl2br(htmlspecialchars(substr($product['description'], 0, 180))); ?>
<?php if(strlen($product['description']) > 180){ echo "..."; } ?>
</div>

</td>

<td>

<span class="small-text">
<?php echo !empty($product['created_at']) ? date("d M Y H:i", strtotime($product['created_at'])) : "Not available"; ?>
</span>

</td>

<td>

<div class="action-buttons">

<?php if($moderation_status != "approved"){ ?>

<form method="POST" action="manage-products.php" onsubmit="return confirm('Approve this product?');">
<input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
<input type="hidden" name="action" value="approve">
<button type="submit" class="action-btn approve-btn">Approve</button>
</form>

<?php }else{ ?>

<button class="action-btn disabled-btn" disabled>Approved</button>

<?php } ?>

<?php if($moderation_status != "rejected"){ ?>

<button
type="button"
class="action-btn reject-btn"
onclick="openReasonModal('reject', <?php echo $product_id; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>')">
Reject
</button>

<?php }else{ ?>

<button class="action-btn disabled-btn" disabled>Rejected</button>

<?php } ?>

<?php if($moderation_status != "removed"){ ?>

<button
type="button"
class="action-btn remove-btn"
onclick="openReasonModal('remove', <?php echo $product_id; ?>, '<?php echo htmlspecialchars(addslashes($product['product_name'])); ?>')">
Remove
</button>

<?php }else{ ?>

<form method="POST" action="manage-products.php" onsubmit="return confirm('Restore this product for review?');">
<input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
<input type="hidden" name="action" value="restore">
<button type="submit" class="action-btn restore-btn">Restore</button>
</form>

<?php } ?>

</div>

</td>

</tr>

<?php } ?>

</table>

</div>

<?php }else{ ?>

<div class="empty-box">

<h3>No products found</h3>

<p>No products match the selected filter or search.</p>

</div>

<?php } ?>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>

<a href="admin-dashboard.php">Dashboard</a>
<a href="manage-products.php">Products</a>
<a href="admin-logout.php">Logout</a>

</nav>

<p>Copyright © 2026 StreetMarket Admin.</p>

</div>

</footer>

<div id="imageModal" class="image-modal">

<span class="close-modal" onclick="closeImageGallery()">&times;</span>

<img id="modalImage" src="" alt="Product Image Preview">

<div class="modal-controls">

<button type="button" onclick="previousImage()">Previous</button>

<span class="image-count" id="imageCount">1 / 1</span>

<button type="button" onclick="nextImage()">Next</button>

</div>

</div>

<div id="reasonModal" class="reason-modal">

<div class="reason-box">

<h3 id="reasonTitle">Reason Required</h3>

<p id="reasonText">
Please provide a clear reason. This reason will be sent to the seller in their notification.
</p>

<form method="POST" action="manage-products.php" id="reasonForm">

<input type="hidden" name="product_id" id="reasonProductId">
<input type="hidden" name="action" id="reasonAction">

<textarea
name="admin_reason"
id="adminReason"
placeholder="Enter the reason here..."
required></textarea>

<div class="reason-actions">

<button type="button" class="cancel-reason" onclick="closeReasonModal()">
Cancel
</button>

<button type="submit" class="submit-reason" onclick="return validateReasonForm()">
Submit Action
</button>

</div>

</form>

</div>

</div>

<script>

let galleryImages = [];
let currentImageIndex = 0;

function openImageGallery(images, startIndex){

    galleryImages = images;
    currentImageIndex = startIndex;

    if(!galleryImages || galleryImages.length === 0){
        return;
    }

    document.getElementById("imageModal").style.display = "flex";
    showCurrentImage();

}

function showCurrentImage(){

    if(galleryImages.length === 0){
        return;
    }

    document.getElementById("modalImage").src = galleryImages[currentImageIndex];
    document.getElementById("imageCount").innerHTML = (currentImageIndex + 1) + " / " + galleryImages.length;

}

function nextImage(){

    if(galleryImages.length === 0){
        return;
    }

    currentImageIndex++;

    if(currentImageIndex >= galleryImages.length){
        currentImageIndex = 0;
    }

    showCurrentImage();

}

function previousImage(){

    if(galleryImages.length === 0){
        return;
    }

    currentImageIndex--;

    if(currentImageIndex < 0){
        currentImageIndex = galleryImages.length - 1;
    }

    showCurrentImage();

}

function closeImageGallery(){

    document.getElementById("imageModal").style.display = "none";
    document.getElementById("modalImage").src = "";
    galleryImages = [];
    currentImageIndex = 0;

}

function openReasonModal(actionName, productId, productName){

    document.getElementById("reasonAction").value = actionName;
    document.getElementById("reasonProductId").value = productId;
    document.getElementById("adminReason").value = "";

    if(actionName == "reject"){
        document.getElementById("reasonTitle").innerHTML = "Reject Product";
        document.getElementById("reasonText").innerHTML = "Please provide the reason for rejecting '" + productName + "'. This reason will be sent to the seller.";
    }else{
        document.getElementById("reasonTitle").innerHTML = "Remove Product";
        document.getElementById("reasonText").innerHTML = "Please provide the reason for removing '" + productName + "'. This reason will be sent to the seller.";
    }

    document.getElementById("reasonModal").style.display = "flex";

}

function closeReasonModal(){

    document.getElementById("reasonModal").style.display = "none";
    document.getElementById("reasonAction").value = "";
    document.getElementById("reasonProductId").value = "";
    document.getElementById("adminReason").value = "";

}

function validateReasonForm(){

    let reason = document.getElementById("adminReason").value.trim();
    let actionName = document.getElementById("reasonAction").value;

    if(reason == ""){
        alert("Please enter a reason before continuing.");
        return false;
    }

    return confirm("Are you sure you want to " + actionName + " this product? The reason will be sent to the seller.");

}

window.onclick = function(event){

    let imageModal = document.getElementById("imageModal");
    let reasonModal = document.getElementById("reasonModal");

    if(event.target === imageModal){
        closeImageGallery();
    }

    if(event.target === reasonModal){
        closeReasonModal();
    }

}

document.addEventListener("keydown", function(event){

    if(document.getElementById("imageModal").style.display === "flex"){

        if(event.key === "ArrowRight"){
            nextImage();
        }

        if(event.key === "ArrowLeft"){
            previousImage();
        }

        if(event.key === "Escape"){
            closeImageGallery();
        }

    }

    if(document.getElementById("reasonModal").style.display === "flex"){

        if(event.key === "Escape"){
            closeReasonModal();
        }

    }

});

</script>

</body>

</html>