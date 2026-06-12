<?php

session_start();

include("includes/db.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "success";

/* GET USER INFORMATION */

$user_query = "
SELECT *
FROM users
WHERE user_id='$user_id'
LIMIT 1
";

$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

/* CHECK SELLER VERIFICATION STATUS */

$seller_status = "Not Verified";

if(isset($user['seller_verification_status']) && $user['seller_verification_status'] != ""){
    $seller_status = $user['seller_verification_status'];
}

$is_verified_seller = false;

if(
    strtolower($seller_status) == "approved" ||
    strtolower($seller_status) == "verified"
){
    $is_verified_seller = true;
}

/* UPLOAD PRODUCT ONLY IF USER IS VERIFIED */

if(isset($_POST['upload_product'])){

    if(!$is_verified_seller){

        $message = "You must be a verified seller before you can add products.";
        $message_type = "error";

    }else{

        $product_name = mysqli_real_escape_string($conn, trim($_POST['product_name']));
        $product_category = mysqli_real_escape_string($conn, trim($_POST['product_category']));
        $product_price = mysqli_real_escape_string($conn, trim($_POST['product_price']));
        $product_quantity = intval($_POST['product_quantity']);
        $product_condition = mysqli_real_escape_string($conn, trim($_POST['product_condition']));
        $delivery_option = mysqli_real_escape_string($conn, trim($_POST['delivery_option']));
        $product_location = mysqli_real_escape_string($conn, trim($_POST['product_location']));
        $product_description = mysqli_real_escape_string($conn, trim($_POST['product_description']));

        $delivery_available = "No";

        if($delivery_option == "Delivery Available"){
            $delivery_available = "Yes";
        }

        if(
            empty($product_name) ||
            empty($product_category) ||
            empty($product_price) ||
            empty($product_description) ||
            empty($product_condition) ||
            empty($delivery_option) ||
            empty($product_location)
        ){

            $message = "Please fill in all required fields.";
            $message_type = "error";

        }elseif(!is_numeric($product_price)){

            $message = "Product price must contain numbers only.";
            $message_type = "error";

        }elseif($product_price <= 0){

            $message = "Product price must be greater than zero.";
            $message_type = "error";

        }elseif($product_quantity < 1){

            $message = "Product quantity must be at least 1.";
            $message_type = "error";

        }elseif(!isset($_FILES['product_images'])){

            $message = "Please upload at least 3 product images.";
            $message_type = "error";

        }else{

            $image_count = count($_FILES['product_images']['name']);
            $allowed_extensions = ["jpg", "jpeg", "png", "webp"];
            $uploaded_images = [];

            if($image_count < 3){

                $message = "Please upload at least 3 product images.";
                $message_type = "error";

            }elseif($image_count > 5){

                $message = "You can upload a maximum of 5 product images.";
                $message_type = "error";

            }else{

                if(!is_dir("uploads")){
                    mkdir("uploads", 0777, true);
                }

                for($i = 0; $i < $image_count; $i++){

                    $image_name = $_FILES['product_images']['name'][$i];
                    $image_tmp = $_FILES['product_images']['tmp_name'][$i];
                    $image_size = $_FILES['product_images']['size'][$i];
                    $image_error = $_FILES['product_images']['error'][$i];

                    if($image_error != 0){

                        $message = "All selected images must upload successfully.";
                        $message_type = "error";
                        break;

                    }

                    $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

                    if(!in_array($image_extension, $allowed_extensions)){

                        $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                        $message_type = "error";
                        break;

                    }

                    if($image_size > 5000000){

                        $message = "Each image must be less than 5MB.";
                        $message_type = "error";
                        break;

                    }

                    $new_image_name = time() . "_" . $user_id . "_" . $i . "_" . basename($image_name);
                    $upload_path = "uploads/" . $new_image_name;

                    if(move_uploaded_file($image_tmp, $upload_path)){

                        $uploaded_images[] = $new_image_name;

                    }else{

                        $message = "Failed to upload one or more images.";
                        $message_type = "error";
                        break;

                    }

                }

                if($message == "" && count($uploaded_images) >= 3){

                    $main_image = $uploaded_images[0];

                    mysqli_begin_transaction($conn);

                    $insert_product = "
                    INSERT INTO product(
                        product_name,
                        description,
                        price,
                        quantity,
                        image,
                        location,
                        product_condition,
                        delivery_option,
                        status,
                        user_id,
                        delivery_available,
                        category,
                        moderation_status
                    )
                    VALUES(
                        '$product_name',
                        '$product_description',
                        '$product_price',
                        '$product_quantity',
                        '$main_image',
                        '$product_location',
                        '$product_condition',
                        '$delivery_option',
                        'available',
                        '$user_id',
                        '$delivery_available',
                        '$product_category',
                        'pending'
                    )
                    ";

                    if(mysqli_query($conn, $insert_product)){

                        $product_id = mysqli_insert_id($conn);
                        $images_saved = true;

                        foreach($uploaded_images as $image){

                            $safe_image = mysqli_real_escape_string($conn, $image);

                            $insert_image = "
                            INSERT INTO product_images(
                                product_id,
                                image_name
                            )
                            VALUES(
                                '$product_id',
                                '$safe_image'
                            )
                            ";

                            if(!mysqli_query($conn, $insert_image)){
                                $images_saved = false;
                                break;
                            }

                        }

                        if($images_saved){

                            mysqli_commit($conn);

                            $message = "Product uploaded successfully. It is now pending admin approval.";
                            $message_type = "success";

                        }else{

                            mysqli_rollback($conn);

                            $message = "Product upload failed while saving images.";
                            $message_type = "error";

                        }

                    }else{

                        mysqli_rollback($conn);

                        $message = "Database error while uploading product: " . mysqli_error($conn);
                        $message_type = "error";

                    }

                }

            }

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Add Product | StreetMarket</title>

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

.verify-box{
    background:#fef3c7;
    color:#92400e;
    border-left:5px solid #f59e0b;
    padding:20px;
    border-radius:12px;
    margin-bottom:25px;
}

.verify-box h3,
.pending-box h3,
.rejected-box h3{
    margin-bottom:10px;
}

.verify-box a{
    display:inline-block;
    margin-top:10px;
    background:#f59e0b;
    color:white;
    padding:12px 18px;
    border-radius:10px;
    text-decoration:none;
    font-weight:bold;
}

.pending-box{
    background:#e0f2fe;
    color:#075985;
    border-left:5px solid #0ea5e9;
    padding:20px;
    border-radius:12px;
    margin-bottom:25px;
}

.rejected-box{
    background:#fee2e2;
    color:#991b1b;
    border-left:5px solid #dc2626;
    padding:20px;
    border-radius:12px;
    margin-bottom:25px;
}

.rejected-box a{
    display:inline-block;
    margin-top:10px;
    background:#dc2626;
    color:white;
    padding:12px 18px;
    border-radius:10px;
    text-decoration:none;
    font-weight:bold;
}

.locked-form{
    opacity:0.45;
    pointer-events:none;
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

<a href="my-listings.php">My Listings</a>

<a href="orders.php">Orders</a>

<a href="messages.php">&#128172; Chat</a>

<a href="user-profile.php">Profile</a>

<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>➕ Add New Product</h2>

<p>
Upload a product for buyers to browse and purchase.
</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<?php if(!$is_verified_seller){ ?>

<?php if(strtolower($seller_status) == "pending"){ ?>

<div class="pending-box">

<h3>&#128337; Seller Verification Pending</h3>

<p>
Your seller verification documents have been submitted and are currently being reviewed by StreetMarket administrators. You will be able to add products once your seller account is approved.
</p>

</div>

<?php }elseif(strtolower($seller_status) == "rejected"){ ?>

<div class="rejected-box">

<h3>&#10060; Seller Verification Rejected</h3>

<p>
Your previous seller verification request was rejected. Please check your documents and submit a new verification request before adding products.
</p>

<a href="seller-verification.php">Resubmit Verification</a>

</div>

<?php }else{ ?>

<div class="verify-box">

<h3>&#10004; Become a Verified Seller</h3>

<p>
You must verify your seller account before you can upload products on StreetMarket. Upload your verification documents to increase buyer trust and receive a Verified Seller badge.
</p>

<a href="seller-verification.php">Verify My Seller Account</a>

</div>

<?php } ?>

<?php } ?>

</div>

</section>

<?php if($is_verified_seller){ ?>

<section class="section-spacing">

<div class="container form-container">

<form method="POST" enctype="multipart/form-data">

<fieldset>

<legend>Product Information</legend>

<label for="product_name">Product Name *</label>

<input
type="text"
id="product_name"
name="product_name"
placeholder="Enter product name"
required>

<label for="product_category">Category *</label>

<select id="product_category" name="product_category" required>

<option value="">Select Category</option>

<option value="Electronics">Electronics</option>

<option value="Fashion">Fashion</option>

<option value="Home">Home</option>

<option value="Furniture">Furniture</option>

<option value="Home Appliances">Home Appliances</option>

<option value="Vehicles">Vehicles</option>

<option value="Sports">Sports</option>

</select>

<label for="product_price">Product Price (R) *</label>

<input
type="number"
step="0.01"
id="product_price"
name="product_price"
placeholder="Enter price"
required>

<label for="product_quantity">Quantity Available *</label>

<input
type="number"
id="product_quantity"
name="product_quantity"
placeholder="Enter available quantity"
min="1"
value="1"
required>

<label for="product_condition">Product Condition *</label>

<select id="product_condition" name="product_condition" required>

<option value="">Select Condition</option>

<option value="New">New</option>

<option value="Excellent">Excellent</option>

<option value="Good">Good</option>

<option value="Used">Used</option>

</select>

<label for="delivery_option">Delivery Option *</label>

<select id="delivery_option" name="delivery_option" required>

<option value="">Select Delivery Option</option>

<option value="Delivery Available">Delivery Available</option>

<option value="Collection Only">Collection Only</option>

</select>

<label for="product_location">Location *</label>

<input
type="text"
id="product_location"
name="product_location"
placeholder="Example: Johannesburg, Gauteng"
required>

<label for="product_description">Product Description *</label>

<textarea
id="product_description"
name="product_description"
placeholder="Describe your product"
required></textarea>

<label for="product_images">Upload Product Images * (Minimum 3, Maximum 5)</label>

<input
type="file"
id="product_images"
name="product_images[]"
accept=".jpg,.jpeg,.png,.webp"
multiple
required>

<small>
Upload at least 3 clear product images. Each image must be less than 5MB.
</small>

<br><br>

<button type="submit" name="upload_product">
Upload Product
</button>

</fieldset>

</form>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="info-box">

<h3>✔ Product Upload Rules</h3>

<ul>

<li>Only verified sellers can upload products.</li>

<li>Upload at least 3 clear product images.</li>

<li>Enter the correct available quantity.</li>

<li>Use accurate descriptions.</li>

<li>Do not upload prohibited products.</li>

<li>Products must be approved by an admin before appearing to buyers.</li>

<li>Delivery information must be accurate.</li>

</ul>

</div>

</div>

</section>

<?php } ?>

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>

<a href="safety.php">Safety Centre</a>

<a href="help.php">Help</a>

</nav>

<p>
Copyright © 2026 StreetMarket. All Rights Reserved.
</p>

</div>

</footer>

<script src="js/script.js"></script>

</body>

</html>