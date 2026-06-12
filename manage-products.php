<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

requireAdminRole(["product_manager"]);

$message = "";
$message_type = "success";

/* SAFE TABLE CHECK */

function tableExists($conn, $table_name){

    $table_name = mysqli_real_escape_string($conn, $table_name);

    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");

    return ($result && mysqli_num_rows($result) > 0);
}

/* SAFE NOTIFICATION */

function sendUserNotification($conn, $user_id, $title, $message, $link){

    $user_id = intval($user_id);

    if(!tableExists($conn, "notifications")){
        return;
    }

    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    mysqli_query($conn, "
    INSERT INTO notifications(user_id, title, message, link, is_read, created_at)
    VALUES('$user_id', '$title', '$message', '$link', 'No', NOW())
    ");
}

/* GET PRODUCT OWNER */

function getProductData($conn, $product_id){

    $product_id = intval($product_id);

    $query = "
    SELECT product_id, user_id, product_name
    FROM product
    WHERE product_id='$product_id'
    LIMIT 1
    ";

    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){
        return mysqli_fetch_assoc($result);
    }

    return false;
}

/* APPROVE PRODUCT */

if(isset($_GET['approve'])){

    $product_id = intval($_GET['approve']);

    $product_data = getProductData($conn, $product_id);

    $approve_query = "
    UPDATE product
    SET moderation_status='approved',
    status='available'
    WHERE product_id='$product_id'
    ";

    if(mysqli_query($conn, $approve_query)){

        if($product_data){
            sendUserNotification(
                $conn,
                $product_data['user_id'],
                "Product Approved",
                "Your product ".$product_data['product_name']." has been approved and is now visible to buyers.",
                "my-listings.php"
            );
        }

        $message = "Product approved successfully.";
        $message_type = "success";

    }else{
        $message = "Failed to approve product: " . mysqli_error($conn);
        $message_type = "error";
    }
}

/* REJECT PRODUCT */

if(isset($_GET['reject'])){

    $product_id = intval($_GET['reject']);

    $product_data = getProductData($conn, $product_id);

    $reject_query = "
    UPDATE product
    SET moderation_status='rejected',
    status='sold'
    WHERE product_id='$product_id'
    ";

    if(mysqli_query($conn, $reject_query)){

        if(tableExists($conn, "cart")){
            mysqli_query($conn, "DELETE FROM cart WHERE product_id='$product_id'");
        }

        if(tableExists($conn, "wishlist")){
            mysqli_query($conn, "DELETE FROM wishlist WHERE product_id='$product_id'");
        }

        if($product_data){
            sendUserNotification(
                $conn,
                $product_data['user_id'],
                "Product Rejected",
                "Your product ".$product_data['product_name']." was rejected and hidden from buyers.",
                "my-listings.php"
            );
        }

        $message = "Product rejected successfully.";
        $message_type = "success";

    }else{
        $message = "Failed to reject product: " . mysqli_error($conn);
        $message_type = "error";
    }
}

/* REMOVE PRODUCT */

if(isset($_GET['remove'])){

    $product_id = intval($_GET['remove']);

    $product_data = getProductData($conn, $product_id);

    mysqli_begin_transaction($conn);

    $delete_ok = true;

    if(tableExists($conn, "product_images")){
        if(!mysqli_query($conn, "DELETE FROM product_images WHERE product_id='$product_id'")){
            $delete_ok = false;
        }
    }

    if($delete_ok && tableExists($conn, "cart")){
        if(!mysqli_query($conn, "DELETE FROM cart WHERE product_id='$product_id'")){
            $delete_ok = false;
        }
    }

    if($delete_ok && tableExists($conn, "wishlist")){
        if(!mysqli_query($conn, "DELETE FROM wishlist WHERE product_id='$product_id'")){
            $delete_ok = false;
        }
    }

    if($delete_ok && tableExists($conn, "product_reviews")){
        if(!mysqli_query($conn, "DELETE FROM product_reviews WHERE product_id='$product_id'")){
            $delete_ok = false;
        }
    }

    if($delete_ok && tableExists($conn, "recently_viewed")){
        if(!mysqli_query($conn, "DELETE FROM recently_viewed WHERE product_id='$product_id'")){
            $delete_ok = false;
        }
    }

    if($delete_ok && tableExists($conn, "messages")){
        if(!mysqli_query($conn, "DELETE FROM messages WHERE product_id='$product_id'")){
            $delete_ok = false;
        }
    }

    if($delete_ok){

        $delete_product = "
        DELETE FROM product
        WHERE product_id='$product_id'
        ";

        if(mysqli_query($conn, $delete_product)){

            mysqli_commit($conn);

            if($product_data){
                sendUserNotification(
                    $conn,
                    $product_data['user_id'],
                    "Product Removed",
                    "Your product ".$product_data['product_name']." was removed by admin.",
                    "my-listings.php"
                );
            }

            $message = "Product listing removed permanently.";
            $message_type = "success";

        }else{
            mysqli_rollback($conn);
            $message = "Failed to remove product: " . mysqli_error($conn);
            $message_type = "error";
        }

    }else{
        mysqli_rollback($conn);
        $message = "Failed to remove product because related records could not be deleted.";
        $message_type = "error";
    }
}

/* GET ALL PRODUCTS */

$products_query = "
SELECT
product.*,
users.full_name
FROM product
INNER JOIN users ON product.user_id = users.user_id
ORDER BY
CASE
WHEN product.moderation_status='pending' THEN 1
WHEN product.moderation_status='' THEN 2
WHEN product.moderation_status IS NULL THEN 3
WHEN product.moderation_status='approved' THEN 4
WHEN product.moderation_status='rejected' THEN 5
ELSE 6
END,
product.product_id DESC
";

$products_result = mysqli_query($conn, $products_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Products | StreetMarket</title>

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

table{
width:100%;
border-collapse:collapse;
background:#fff;
margin-top:20px;
box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

table th,
table td{
padding:15px;
border-bottom:1px solid #ddd;
text-align:left;
vertical-align:top;
}

table th{
background:#111;
color:#fff;
font-weight:bold;
}

.admin-actions{
display:flex;
gap:10px;
flex-wrap:wrap;
}

.admin-actions a{
padding:9px 14px;
border-radius:6px;
text-decoration:none;
color:white;
font-size:14px;
font-weight:bold;
}

.approve-btn{background:green;}
.reject-btn{background:orange;}
.remove-btn{background:red;}

.pending-status{color:orange;font-weight:bold;}
.approved-status{color:green;font-weight:bold;}
.rejected-status{color:red;font-weight:bold;}
.available-status{color:green;font-weight:bold;}
.sold-status{color:red;font-weight:bold;}

.image-carousel{
position:relative;
width:150px;
height:110px;
overflow:hidden;
border-radius:10px;
background:#f5f5f5;
}

.carousel-image{
width:150px;
height:110px;
object-fit:cover;
display:none;
border-radius:10px;
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
width:28px;
height:28px;
border-radius:50%;
cursor:pointer;
font-size:16px;
z-index:5;
}

.carousel-prev{left:5px;}
.carousel-next{right:5px;}

.carousel-count{
position:absolute;
bottom:5px;
right:5px;
background:rgba(0,0,0,0.7);
color:white;
padding:3px 7px;
border-radius:20px;
font-size:11px;
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

@media(max-width:900px){

table{
display:block;
overflow-x:auto;
white-space:nowrap;
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
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>Manage Products</h1>
</div>

<nav>
<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-logout.php">Logout</a>
</nav>

</div>

</header>

<main>

<section class="section-spacing">

<div class="container">

<h2>Marketplace Product Listings</h2>

<p>
Administrators can approve, reject, or remove seller listings. Click any product image to view it in a larger format.
</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<table>

<tr>
<th>Images</th>
<th>Product</th>
<th>Seller</th>
<th>Price</th>
<th>Category</th>
<th>Stock</th>
<th>Moderation</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php if($products_result && mysqli_num_rows($products_result) > 0){ ?>

<?php while($product = mysqli_fetch_assoc($products_result)){ ?>

<tr>

<td>

<?php

$admin_images = [];

if(!empty($product['image'])){
    $admin_images[] = $product['image'];
}

$admin_images_query = "
SELECT image_name
FROM product_images
WHERE product_id='".intval($product['product_id'])."'
ORDER BY image_id ASC
";

$admin_images_result = false;

if(tableExists($conn, "product_images")){
    $admin_images_result = mysqli_query($conn, $admin_images_query);
}

if($admin_images_result && mysqli_num_rows($admin_images_result) > 0){

    while($admin_image = mysqli_fetch_assoc($admin_images_result)){

        if(!in_array($admin_image['image_name'], $admin_images)){
            $admin_images[] = $admin_image['image_name'];
        }

    }

}

if(count($admin_images) == 0){
    $admin_images[] = "default-product.jpg";
}

$carousel_id = "adminProductCarousel_" . intval($product['product_id']);

?>

<div class="image-carousel" id="<?php echo $carousel_id; ?>">

<?php foreach($admin_images as $index => $image_name){ ?>

<img
src="uploads/<?php echo htmlspecialchars($image_name); ?>"
alt="<?php echo htmlspecialchars($product['product_name']); ?>"
class="carousel-image <?php if($index == 0){ echo 'active'; } ?>"
onclick="openImageModal('uploads/<?php echo htmlspecialchars($image_name); ?>', '<?php echo htmlspecialchars($product['product_name']); ?>')">

<?php } ?>

<?php if(count($admin_images) > 1){ ?>

<button type="button" class="carousel-btn carousel-prev" onclick="moveCarousel('<?php echo $carousel_id; ?>', -1)">‹</button>
<button type="button" class="carousel-btn carousel-next" onclick="moveCarousel('<?php echo $carousel_id; ?>', 1)">›</button>

<div class="carousel-count">
1 / <?php echo count($admin_images); ?>
</div>

<?php } ?>

</div>

</td>

<td>
<b><?php echo htmlspecialchars($product['product_name']); ?></b>
<br>
<small><?php echo htmlspecialchars(substr($product['description'], 0, 80)); ?>...</small>
</td>

<td><?php echo htmlspecialchars($product['full_name']); ?></td>

<td>R<?php echo number_format($product['price'], 2); ?></td>

<td><?php echo htmlspecialchars($product['category']); ?></td>

<td><?php echo isset($product['quantity']) ? intval($product['quantity']) : 0; ?></td>

<td>

<?php if($product['moderation_status'] == "approved"){ ?>

<span class="approved-status">Approved</span>

<?php }elseif($product['moderation_status'] == "rejected"){ ?>

<span class="rejected-status">Rejected</span>

<?php }else{ ?>

<span class="pending-status">Pending</span>

<?php } ?>

</td>

<td>

<?php if($product['status'] == "available"){ ?>

<span class="available-status">Available</span>

<?php }else{ ?>

<span class="sold-status">Sold / Hidden</span>

<?php } ?>

</td>

<td>

<div class="admin-actions">

<?php if($product['moderation_status'] != "approved"){ ?>

<a href="manage-products.php?approve=<?php echo intval($product['product_id']); ?>" class="approve-btn">
Approve
</a>

<?php } ?>

<?php if($product['moderation_status'] != "rejected"){ ?>

<a
href="manage-products.php?reject=<?php echo intval($product['product_id']); ?>"
class="reject-btn"
onclick="return confirm('Reject this product and hide it from buyers?');">
Reject
</a>

<?php } ?>

<a
href="manage-products.php?remove=<?php echo intval($product['product_id']); ?>"
class="remove-btn"
onclick="return confirm('Permanently remove this product listing? This cannot be undone.');">
Remove
</a>

</div>

</td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>
<td colspan="9">No products found.</td>
</tr>

<?php } ?>

</table>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="info-box">

<h2>Product Moderation Rules</h2>

<ul>
<li>Pending products are visible to admins but hidden from buyers.</li>
<li>Approved products become visible on the products page and product details page.</li>
<li>Rejected products are hidden from buyers.</li>
<li>Removed products are permanently deleted from the database.</li>
<li>Admins can click product images to inspect them in a larger format.</li>
</ul>

</div>

</div>

</section>

</main>

<footer>

<div class="container footer-container">

<p>
Copyright © 2026 StreetMarket. All Rights Reserved.
</p>

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