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

/* GET USER INFORMATION */

$user_query = "
SELECT *
FROM users
WHERE user_id='$user_id'
LIMIT 1
";

$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

if(!$user){
    header("Location: logout.php");
    exit();
}

/* UPDATE PROFILE */

if(isset($_POST['update_profile'])){

    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $province = mysqli_real_escape_string($conn, trim($_POST['province']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));

    $profile_image = $user['profile_image'];

    if(isset($_FILES['profile_image']) && $_FILES['profile_image']['name'] != ""){

        $allowed_extensions = ["jpg", "jpeg", "png", "webp"];
        $file_name = $_FILES['profile_image']['name'];
        $temp_name = $_FILES['profile_image']['tmp_name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if(in_array($file_extension, $allowed_extensions)){

            if(!is_dir("uploads/profile")){
                mkdir("uploads/profile", 0777, true);
            }

            $image_name = time() . "_" . basename($file_name);

            if(move_uploaded_file($temp_name, "uploads/profile/" . $image_name)){
                $profile_image = $image_name;
            }else{
                $message = "Failed to upload profile image.";
                $message_type = "error";
            }

        }else{
            $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
            $message_type = "error";
        }
    }

    if($message == ""){

        $update_query = "
        UPDATE users
        SET
        phone='$phone',
        province='$province',
        address='$address',
        profile_image='$profile_image'
        WHERE user_id='$user_id'
        ";

        if(mysqli_query($conn, $update_query)){

            $message = "Profile updated successfully.";
            $message_type = "success";

            $user_result = mysqli_query($conn, $user_query);
            $user = mysqli_fetch_assoc($user_result);

        }else{
            $message = "Failed to update profile.";
            $message_type = "error";
        }
    }
}

/* UPDATE BUSINESS PROFILE */

if(isset($_POST['update_business'])){

    $business_name = mysqli_real_escape_string($conn, trim($_POST['business_name']));
    $business_type = mysqli_real_escape_string($conn, trim($_POST['business_type']));
    $business_location = mysqli_real_escape_string($conn, trim($_POST['business_location']));
    $business_profile = mysqli_real_escape_string($conn, trim($_POST['business_profile']));

    $business_bank_name = mysqli_real_escape_string($conn, trim($_POST['business_bank_name']));
    $business_account_holder = mysqli_real_escape_string($conn, trim($_POST['business_account_holder']));
    $business_account_number = mysqli_real_escape_string($conn, trim($_POST['business_account_number']));
    $business_branch_code = mysqli_real_escape_string($conn, trim($_POST['business_branch_code']));

    if(
        empty($business_name) ||
        empty($business_type) ||
        empty($business_location) ||
        empty($business_profile) ||
        empty($business_bank_name) ||
        empty($business_account_holder) ||
        empty($business_account_number) ||
        empty($business_branch_code)
    ){

        $message = "Please complete all business profile and payout details.";
        $message_type = "error";

    }elseif(!preg_match("/^[0-9]{6,20}$/", $business_account_number)){

        $message = "Business account number must contain numbers only.";
        $message_type = "error";

    }elseif(!preg_match("/^[0-9]{4,10}$/", $business_branch_code)){

        $message = "Branch code must contain numbers only.";
        $message_type = "error";

    }else{

        $update_business = "
        UPDATE users
        SET
        business_name='$business_name',
        business_type='$business_type',
        business_location='$business_location',
        business_profile='$business_profile',
        business_bank_name='$business_bank_name',
        business_account_holder='$business_account_holder',
        business_account_number='$business_account_number',
        business_branch_code='$business_branch_code'
        WHERE user_id='$user_id'
        ";

        if(mysqli_query($conn, $update_business)){

            $message = "Business profile updated successfully.";
            $message_type = "success";

            $user_result = mysqli_query($conn, $user_query);
            $user = mysqli_fetch_assoc($user_result);

        }else{

            $message = "Failed to update business profile.";
            $message_type = "error";

        }
    }
}

/* CHANGE PASSWORD */

if(isset($_POST['change_password'])){

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if($new_password != $confirm_password){

        $message = "New passwords do not match.";
        $message_type = "error";

    }elseif(!preg_match("/^(?=.*[a-z])(?=.*[0-9]).{8,}$/", $new_password)){

        $message = "New password must contain at least 8 characters, one number and one lowercase letter.";
        $message_type = "error";

    }else{

        if(password_verify($current_password, $user['password'])){

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $password_query = "
            UPDATE users
            SET password='$hashed_password'
            WHERE user_id='$user_id'
            ";

            if(mysqli_query($conn, $password_query)){
                $message = "Password changed successfully.";
                $message_type = "success";
            }else{
                $message = "Failed to change password.";
                $message_type = "error";
            }

        }else{
            $message = "Current password is incorrect.";
            $message_type = "error";
        }
    }
}

$seller_status = "Not Verified";

if(isset($user['seller_verification_status']) && $user['seller_verification_status'] != ""){
    $seller_status = $user['seller_verification_status'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.profile-layout{
    display:grid;
    grid-template-columns:300px 1fr;
    gap:30px;
}

.profile-sidebar{
    background:#fff;
    padding:30px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

.profile-sidebar img{
    width:180px;
    height:180px;
    border-radius:14px;
    object-fit:contain;
    object-position:center;
    background:#f8fafc;
    margin-bottom:15px;
    border:4px solid #eee;
}

.profile-content{
    display:flex;
    flex-direction:column;
    gap:30px;
}

.profile-card{
    background:#fff;
    padding:30px;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

.profile-card h3{
    margin-bottom:20px;
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

.verify-box{
    background:#fef3c7;
    color:#92400e;
    border-left:5px solid #f59e0b;
    padding:20px;
    border-radius:12px;
    margin-bottom:25px;
}

.verify-box h3,
.verified-box h3{
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

.verified-box{
    background:#dcfce7;
    color:#166534;
    border-left:6px solid #16a34a;
    padding:20px;
    border-radius:12px;
    margin-bottom:25px;
}

.verified-badge{
    display:inline-block;
    background:#16a34a;
    color:white;
    padding:8px 14px;
    border-radius:30px;
    font-size:14px;
    font-weight:bold;
    margin-top:10px;
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

.readonly-info{
    background:#f8fafc;
    padding:12px;
    border-radius:10px;
    margin-bottom:12px;
    border:1px solid #e2e8f0;
}

.readonly-info strong{
    display:block;
    color:#0f172a;
}

.private-note{
    background:#eff6ff;
    color:#1e3a8a;
    border-left:5px solid #2563eb;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    font-weight:bold;
}

.business-public-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
}

.business-public-box h4{
    margin-bottom:10px;
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}

.full-width{
    grid-column:1 / -1;
}

@media(max-width:900px){
    .profile-layout{
        grid-template-columns:1fr;
    }

    .form-grid{
        grid-template-columns:1fr;
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

<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="wishlist.php">Wishlist</a>
<a href="cart.php">Cart</a>
<a href="orders.php">Orders</a>
<a href="messages.php">&#128172; Messages</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="page-intro">

<h2>My Profile</h2>

<p>
Manage your StreetMarket account, delivery information, business profile and security settings.
</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
    <?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<?php if($seller_status == "Approved" || $seller_status == "Verified"){ ?>

<div class="verified-box">

<h3>&#9989; Verified StreetMarket Seller</h3>

<p>
This account has been verified by StreetMarket administrators. Buyers will see your public business profile instead of your private personal information.
</p>

<span class="verified-badge">
&#9989; Verified Seller
</span>

</div>

<?php }elseif($seller_status == "Pending"){ ?>

<div class="pending-box">

<h3>&#128337; Seller Verification Pending</h3>

<p>
Your seller verification documents and business details have been submitted and are currently being reviewed by StreetMarket administrators.
</p>

</div>

<?php }elseif($seller_status == "Rejected"){ ?>

<div class="rejected-box">

<h3>&#10060; Seller Verification Rejected</h3>

<p>
Your previous seller verification request was rejected. Please check your ID document, business details and payout details before resubmitting.
</p>

<a href="seller-verification.php">Resubmit Verification</a>

</div>

<?php }else{ ?>

<div class="verify-box">

<h3>&#10004; Become a Verified Seller</h3>

<p>
Upload your ID document, business profile and payout banking details to become a verified seller on StreetMarket.
</p>

<a href="seller-verification.php">Verify My Seller Account</a>

</div>

<?php } ?>

<div class="profile-layout">

<div class="profile-sidebar">

<?php if(!empty($user['profile_image'])){ ?>

<img src="uploads/profile/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Picture">

<?php }else{ ?>

<img src="images/default-user.png" alt="Default User">

<?php } ?>

<h3><?php echo htmlspecialchars($user['full_name']); ?></h3>

<?php if($seller_status == "Approved" || $seller_status == "Verified"){ ?>

<span class="verified-badge">&#9989; Verified Seller</span>

<?php } ?>

<p><?php echo htmlspecialchars($user['email']); ?></p>

<p>+27 <?php echo htmlspecialchars($user['phone']); ?></p>

<p><?php echo htmlspecialchars($user['province']); ?></p>

</div>

<div class="profile-content">

<div class="profile-card">

<h3>Account Information</h3>

<div class="readonly-info">
    <strong>Full Name</strong>
    <?php echo htmlspecialchars($user['full_name']); ?>
</div>

<div class="readonly-info">
    <strong>Email Address</strong>
    <?php echo htmlspecialchars($user['email']); ?>
</div>

<?php if(isset($user['sa_id'])){ ?>

<div class="readonly-info">
    <strong>South African ID</strong>
    <?php echo htmlspecialchars($user['sa_id']); ?>
</div>

<?php } ?>

<div class="readonly-info">
    <strong>Seller Verification Status</strong>
    <?php echo htmlspecialchars($seller_status); ?>
</div>

<p>
For security reasons, your name, email address and SA ID cannot be edited here.
</p>

</div>

<div class="profile-card">

<h3>Public Business Profile</h3>

<div class="private-note">
Buyers will see this business profile when viewing your products. They will not see your ID document, banking details or private personal information.
</div>

<div class="business-public-box">

<h4><?php echo !empty($user['business_name']) ? htmlspecialchars($user['business_name']) : "No business name added yet"; ?></h4>

<p><strong>Business Type:</strong> <?php echo !empty($user['business_type']) ? htmlspecialchars($user['business_type']) : "Not added"; ?></p>

<p><strong>Business Location:</strong> <?php echo !empty($user['business_location']) ? htmlspecialchars($user['business_location']) : "Not added"; ?></p>

<p><strong>Business Profile:</strong><br>
<?php echo !empty($user['business_profile']) ? nl2br(htmlspecialchars($user['business_profile'])) : "No business profile added yet."; ?>
</p>

</div>

<form method="POST">

<div class="form-grid">

<div>
<label>Business Name *</label>
<input type="text" name="business_name" value="<?php echo htmlspecialchars($user['business_name'] ?? ''); ?>" required>
</div>

<div>
<label>Business Type *</label>
<select name="business_type" required>
<option value="">Select Business Type</option>

<?php

$business_types = [
"Informal Trader",
"Individual Seller",
"Clothing Seller",
"Electronics Seller",
"Furniture Seller",
"Food Seller",
"Home Goods Seller",
"Vehicle Parts Seller",
"Other"
];

foreach($business_types as $type){

?>

<option value="<?php echo $type; ?>" <?php if(($user['business_type'] ?? '') == $type){ echo "selected"; } ?>>
<?php echo $type; ?>
</option>

<?php } ?>

</select>
</div>

<div class="full-width">
<label>Business Location *</label>
<input type="text" name="business_location" value="<?php echo htmlspecialchars($user['business_location'] ?? ''); ?>" required>
</div>

<div class="full-width">
<label>Business Profile *</label>
<textarea name="business_profile" rows="5" required><?php echo htmlspecialchars($user['business_profile'] ?? ''); ?></textarea>
</div>

</div>

<br>

<h3>Private Seller Payout Details</h3>

<div class="private-note">
These banking details are private and should only be used by StreetMarket admins for seller payout processing.
</div>

<div class="form-grid">

<div>
<label>Bank Name *</label>
<input type="text" name="business_bank_name" value="<?php echo htmlspecialchars($user['business_bank_name'] ?? ''); ?>" required>
</div>

<div>
<label>Account Holder Name *</label>
<input type="text" name="business_account_holder" value="<?php echo htmlspecialchars($user['business_account_holder'] ?? ''); ?>" required>
</div>

<div>
<label>Account Number *</label>
<input type="text" name="business_account_number" value="<?php echo htmlspecialchars($user['business_account_number'] ?? ''); ?>" required>
</div>

<div>
<label>Branch Code *</label>
<input type="text" name="business_branch_code" value="<?php echo htmlspecialchars($user['business_branch_code'] ?? ''); ?>" required>
</div>

</div>

<br>

<button type="submit" name="update_business">
Update Business Details
</button>

</form>

</div>

<div class="profile-card">

<h3>Edit Contact and Delivery Details</h3>

<form method="POST" enctype="multipart/form-data">

<label>Phone Number</label>

<input
type="text"
name="phone"
value="<?php echo htmlspecialchars($user['phone']); ?>"
maxlength="9"
required>

<label>Province</label>

<select name="province" required>

<option value="">Select Province</option>

<?php

$provinces = [
"Gauteng",
"Limpopo",
"Mpumalanga",
"KwaZulu-Natal",
"Western Cape",
"Eastern Cape",
"North West",
"Free State",
"Northern Cape"
];

foreach($provinces as $province){

?>

<option
value="<?php echo $province; ?>"
<?php if($user['province'] == $province){ echo "selected"; } ?>>

<?php echo $province; ?>

</option>

<?php } ?>

</select>

<label>Delivery Address</label>

<textarea name="address" rows="4" required><?php echo htmlspecialchars($user['address']); ?></textarea>

<label>Profile Picture</label>

<input type="file" name="profile_image" accept="image/*">

<br><br>

<button type="submit" name="update_profile">
Update Profile
</button>

</form>

</div>

<div class="profile-card">

<h3>Change Password</h3>

<form method="POST">

<label>Current Password</label>

<input type="password" name="current_password" required>

<label>New Password</label>

<input type="password" name="new_password" required>

<label>Confirm New Password</label>

<input type="password" name="confirm_password" required>

<br><br>

<button type="submit" name="change_password">
Change Password
</button>

</form>

</div>

</div>

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
Copyright © 2026 StreetMarket. All Rights Reserved.
</p>

</div>

</footer>

</body>

</html>