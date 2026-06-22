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
   VALIDATE PRODUCT ID
========================================= */

if(!isset($_GET['id'])){

    header("Location: my-listings.php");
    exit();

}

$product_id = intval($_GET['id']);

/* =========================================
   GET PRODUCT AND CONFIRM OWNERSHIP
========================================= */

$product_query = "

SELECT *

FROM product

WHERE product_id='$product_id'
AND user_id='$user_id'

";

$product_result = mysqli_query(
$conn,
$product_query
);

if(!$product_result || mysqli_num_rows($product_result) == 0){

    die("Product not found or you are not allowed to edit it.");

}

$product = mysqli_fetch_assoc(
$product_result
);

/* =========================================
   UPDATE PRODUCT DETAILS
========================================= */

if(isset($_POST['update_product'])){

    $product_name = mysqli_real_escape_string(
    $conn,
    trim($_POST['product_name'])
    );

    $product_category = mysqli_real_escape_string(
    $conn,
    trim($_POST['product_category'])
    );

    $product_price = mysqli_real_escape_string(
    $conn,
    trim($_POST['product_price'])
    );

    $product_quantity = intval($_POST['product_quantity']);

    $product_condition = mysqli_real_escape_string(
    $conn,
    trim($_POST['product_condition'])
    );

    $delivery_option = mysqli_real_escape_string(
    $conn,
    trim($_POST['delivery_option'])
    );

    $product_location = mysqli_real_escape_string(
    $conn,
    trim($_POST['product_location'])
    );

    $product_description = mysqli_real_escape_string(
    $conn,
    trim($_POST['product_description'])
    );

    $delivery_available = "No";

    if($delivery_option == "Delivery Available"){

        $delivery_available = "Yes";

    }

    if(
        empty($product_name) ||
        empty($product_category) ||
        empty($product_price) ||
        empty($product_condition) ||
        empty($delivery_option) ||
        empty($product_location) ||
        empty($product_description)
    ){

        $message = "Please fill in all required fields.";

    }elseif(!is_numeric($product_price)){

        $message = "Product price must contain numbers only.";

    }elseif($product_price <= 0){

        $message = "Product price must be greater than zero.";

    }elseif($product_quantity < 0){

        $message = "Product quantity cannot be less than zero.";

    }else{

        $new_status = "available";

        if($product_quantity == 0){

            $new_status = "sold";

        }

        $update_query = "

        UPDATE product

        SET
        product_name='$product_name',
        description='$product_description',
        price='$product_price',
        quantity='$product_quantity',
        location='$product_location',
        product_condition='$product_condition',
        delivery_option='$delivery_option',
        delivery_available='$delivery_available',
        category='$product_category',
        status='$new_status'

        WHERE product_id='$product_id'
        AND user_id='$user_id'

        ";

        if(mysqli_query($conn, $update_query)){

            $message = "Product details updated successfully.";

            $product_query = "

            SELECT *

            FROM product

            WHERE product_id='$product_id'
            AND user_id='$user_id'

            ";

            $product_result = mysqli_query(
            $conn,
            $product_query
            );

            $product = mysqli_fetch_assoc(
            $product_result
            );

        }else{

            $message = "Failed to update product details.";

        }

    }

}

/* =========================================
   ADD NEW PRODUCT IMAGES
========================================= */

if(isset($_POST['add_images'])){

    if(isset($_FILES['product_images'])){

        $image_count = count($_FILES['product_images']['name']);
        $allowed_extensions = ["jpg", "jpeg", "png", "webp"];
        $uploaded_images = [];
        $image_error_found = false;

        if($image_count < 1){

            $message = "Please select at least one image.";

        }else{

            for($i = 0; $i < $image_count; $i++){

                $image_name = $_FILES['product_images']['name'][$i];
                $image_tmp = $_FILES['product_images']['tmp_name'][$i];
                $image_size = $_FILES['product_images']['size'][$i];
                $image_error = $_FILES['product_images']['error'][$i];

                if($image_error != 0){

                    $message = "One or more selected images failed to upload.";
                    $image_error_found = true;
                    break;

                }

                $image_extension = strtolower(
                    pathinfo(
                    $image_name,
                    PATHINFO_EXTENSION
                    )
                );

                if(!in_array($image_extension, $allowed_extensions)){

                    $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
                    $image_error_found = true;
                    break;

                }

                if($image_size > 5000000){

                    $message = "Each image must be less than 5MB.";
                    $image_error_found = true;
                    break;

                }

                $new_image_name =
                time() . "_" .
                $user_id . "_" .
                $product_id . "_" .
                $i . "_" .
                basename($image_name);

                $upload_path =
                "uploads/" .
                $new_image_name;

                if(move_uploaded_file(
                $image_tmp,
                $upload_path
                )){

                    $uploaded_images[] = $new_image_name;

                }else{

                    $message = "Failed to upload one or more images.";
                    $image_error_found = true;
                    break;

                }

            }

            if(!$image_error_found && count($uploaded_images) > 0){

                foreach($uploaded_images as $new_image){

                    $safe_image = mysqli_real_escape_string(
                    $conn,
                    $new_image
                    );

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

                    mysqli_query(
                    $conn,
                    $insert_image
                    );

                }

                if(empty($product['image'])){

                    $first_new_image = mysqli_real_escape_string(
                    $conn,
                    $uploaded_images[0]
                    );

                    $update_main_image = "

                    UPDATE product

                    SET image='$first_new_image'

                    WHERE product_id='$product_id'
                    AND user_id='$user_id'

                    ";

                    mysqli_query(
                    $conn,
                    $update_main_image
                    );

                }

                $message = "New product images added successfully.";

            }

        }

    }else{

        $message = "Please select images to upload.";

    }

}

/* =========================================
   DELETE PRODUCT IMAGE
========================================= */

if(isset($_POST['delete_image'])){

    $image_id = intval($_POST['image_id']);

    $image_query = "

    SELECT product_images.*

    FROM product_images

    INNER JOIN product
    ON product_images.product_id = product.product_id

    WHERE product_images.image_id='$image_id'
    AND product.user_id='$user_id'
    AND product.product_id='$product_id'

    ";

    $image_result = mysqli_query(
    $conn,
    $image_query
    );

    if($image_result && mysqli_num_rows($image_result) > 0){

        $image = mysqli_fetch_assoc(
        $image_result
        );

        $image_name = $image['image_name'];

        $delete_image = "

        DELETE FROM product_images

        WHERE image_id='$image_id'
        AND product_id='$product_id'

        ";

        if(mysqli_query($conn, $delete_image)){

            if(file_exists("uploads/" . $image_name)){

                unlink("uploads/" . $image_name);

            }

            if($product['image'] == $image_name){

                $new_main_query = "

                SELECT image_name

                FROM product_images

                WHERE product_id='$product_id'

                ORDER BY image_id ASC

                LIMIT 1

                ";

                $new_main_result = mysqli_query(
                $conn,
                $new_main_query
                );

                if($new_main_result && mysqli_num_rows($new_main_result) > 0){

                    $new_main = mysqli_fetch_assoc(
                    $new_main_result
                    );

                    $new_main_image = mysqli_real_escape_string(
                    $conn,
                    $new_main['image_name']
                    );

                    $update_main = "

                    UPDATE product

                    SET image='$new_main_image'

                    WHERE product_id='$product_id'
                    AND user_id='$user_id'

                    ";

                    mysqli_query($conn, $update_main);

                }

            }

            $message = "Product image removed successfully.";

        }else{

            $message = "Failed to delete product image.";

        }

    }else{

        $message = "Image not found or you are not allowed to delete it.";

    }

}

/* =========================================
   SET MAIN PRODUCT IMAGE
========================================= */

if(isset($_POST['set_main_image'])){

    $image_id = intval($_POST['image_id']);

    $image_query = "

    SELECT product_images.image_name

    FROM product_images

    INNER JOIN product
    ON product_images.product_id = product.product_id

    WHERE product_images.image_id='$image_id'
    AND product.product_id='$product_id'
    AND product.user_id='$user_id'

    ";

    $image_result = mysqli_query(
    $conn,
    $image_query
    );

    if($image_result && mysqli_num_rows($image_result) > 0){

        $image = mysqli_fetch_assoc(
        $image_result
        );

        $main_image = mysqli_real_escape_string(
        $conn,
        $image['image_name']
        );

        $update_main = "

        UPDATE product

        SET image='$main_image'

        WHERE product_id='$product_id'
        AND user_id='$user_id'

        ";

        if(mysqli_query($conn, $update_main)){

            $message = "Main product image updated successfully.";

        }else{

            $message = "Failed to update main product image.";

        }

    }else{

        $message = "Image not found or you are not allowed to use it.";

    }

}

/* =========================================
   GET UPDATED PRODUCT
========================================= */

$product_query = "

SELECT *

FROM product

WHERE product_id='$product_id'
AND user_id='$user_id'

";

$product_result = mysqli_query(
$conn,
$product_query
);

$product = mysqli_fetch_assoc(
$product_result
);

/* =========================================
   GET PRODUCT IMAGES
========================================= */

$images_query = "

SELECT *

FROM product_images

WHERE product_id='$product_id'

ORDER BY image_id ASC

";

$images_result = mysqli_query(
$conn,
$images_query
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Product | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.image-manager{

display:grid;
grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
gap:18px;
margin-top:20px;

}

.image-card{

background:white;
padding:12px;
border-radius:14px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
text-align:center;

}

.image-card img{

width:100%;
height:150px;
object-fit:cover;
border-radius:10px;
margin-bottom:10px;

}

.image-card button{

margin-top:8px;
width:100%;
padding:10px;
border:none;
border-radius:8px;
background:#111;
color:white;
cursor:pointer;
font-weight:bold;

}

.image-card .delete-btn{

background:#b00020;

}

.main-image-label{

display:inline-block;
padding:7px 12px;
background:#008000;
color:white;
border-radius:20px;
font-size:13px;
font-weight:bold;
margin-bottom:8px;

}

.quantity-actions{

display:flex;
gap:10px;
align-items:center;
flex-wrap:wrap;

}

.quantity-actions input{

width:120px;

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

<a href="index.php">Home</a>

<a href="my-listings.php">My Listings</a>

<a href="products.php">Products</a>

<a href="orders.php">Orders</a>

<a href="messages.php">Messages</a>

<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>✏ Edit Product</h2>

<p>
Update product details, stock quantity, images, price and description.
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

<section class="section-spacing">

<div class="container form-container">

<form method="POST">

<fieldset>

<legend>Product Details</legend>

<label for="product_name">Product Name *</label>

<input
type="text"
id="product_name"
name="product_name"
value="<?php echo htmlspecialchars($product['product_name']); ?>"
required>

<label for="product_category">Category *</label>

<select
id="product_category"
name="product_category"
required>

<option value="">Select Category</option>

<option value="Electronics" <?php if($product['category'] == "Electronics"){ echo "selected"; } ?>>Electronics</option>

<option value="Fashion" <?php if($product['category'] == "Fashion"){ echo "selected"; } ?>>Fashion</option>

<option value="Home" <?php if($product['category'] == "Home"){ echo "selected"; } ?>>Home</option>

<option value="Furniture" <?php if($product['category'] == "Furniture"){ echo "selected"; } ?>>Furniture</option>

<option value="Home Appliances" <?php if($product['category'] == "Home Appliances"){ echo "selected"; } ?>>Home Appliances</option>

<option value="Vehicles" <?php if($product['category'] == "Vehicles"){ echo "selected"; } ?>>Vehicles</option>

<option value="Sports" <?php if($product['category'] == "Sports"){ echo "selected"; } ?>>Sports</option>

</select>

<label for="product_price">Product Price (R) *</label>

<input
type="number"
step="0.01"
id="product_price"
name="product_price"
value="<?php echo htmlspecialchars($product['price']); ?>"
required>

<label for="product_quantity">Quantity Available *</label>

<div class="quantity-actions">

<input
type="number"
id="product_quantity"
name="product_quantity"
min="0"
value="<?php echo isset($product['quantity']) ? intval($product['quantity']) : 0; ?>"
required>

</div>

<label for="product_condition">Product Condition *</label>

<select
id="product_condition"
name="product_condition"
required>

<option value="">Select Condition</option>

<option value="New" <?php if($product['product_condition'] == "New"){ echo "selected"; } ?>>New</option>

<option value="Excellent" <?php if($product['product_condition'] == "Excellent"){ echo "selected"; } ?>>Excellent</option>

<option value="Good" <?php if($product['product_condition'] == "Good"){ echo "selected"; } ?>>Good</option>

<option value="Used" <?php if($product['product_condition'] == "Used"){ echo "selected"; } ?>>Used</option>

</select>

<label for="delivery_option">Delivery Option *</label>

<select
id="delivery_option"
name="delivery_option"
required>

<option value="">Select Delivery Option</option>

<option value="Delivery Available" <?php if($product['delivery_option'] == "Delivery Available"){ echo "selected"; } ?>>Delivery Available</option>

<option value="Collection Only" <?php if($product['delivery_option'] == "Collection Only"){ echo "selected"; } ?>>Collection Only</option>

</select>

<label for="product_location">Location *</label>

<input
type="text"
id="product_location"
name="product_location"
value="<?php echo htmlspecialchars($product['location']); ?>"
required>

<label for="product_description">Product Description *</label>

<textarea
id="product_description"
name="product_description"
required><?php echo htmlspecialchars($product['description']); ?></textarea>

<button
type="submit"
name="update_product">

Save Product Changes

</button>

</fieldset>

</form>

</div>

</section>

<section class="section-spacing">

<div class="container form-container">

<form method="POST" enctype="multipart/form-data">

<fieldset>

<legend>Add New Images</legend>

<label for="product_images">Add Product Images</label>

<input
type="file"
id="product_images"
name="product_images[]"
accept=".jpg,.jpeg,.png,.webp"
multiple>

<small>
You can add one or more product images. JPG, JPEG, PNG and WEBP only. Max 5MB each.
</small>

<br><br>

<button
type="submit"
name="add_images">

Add Images

</button>

</fieldset>

</form>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>Product Images</h2>

<p>
Choose the main image or remove unwanted product images.
</p>

</div>

<div class="image-manager">

<?php

if($images_result && mysqli_num_rows($images_result) > 0){

    while($image = mysqli_fetch_assoc($images_result)){

?>

<div class="image-card">

<?php

if($product['image'] == $image['image_name']){

?>

<div class="main-image-label">Main Image</div>

<?php

}

?>

<img
src="uploads/<?php echo htmlspecialchars($image['image_name']); ?>"
alt="Product Image">

<?php

if($product['image'] != $image['image_name']){

?>

<form method="POST">

<input
type="hidden"
name="image_id"
value="<?php echo $image['image_id']; ?>">

<button
type="submit"
name="set_main_image">

Set As Main Image

</button>

</form>

<?php

}

?>

<form method="POST" onsubmit="return confirm('Delete this image?');">

<input
type="hidden"
name="image_id"
value="<?php echo $image['image_id']; ?>">

<button
type="submit"
name="delete_image"
class="delete-btn">

Delete Image

</button>

</form>

</div>

<?php

    }

}else{

?>

<div class="info-box">

<p>
No extra product images found.
</p>

</div>

<?php

}

?>

</div>

</div>

</section>

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