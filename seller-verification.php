<?php

session_start();

include("includes/db.php");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = "";
$message_type = "error";

/* GET USER */

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

$seller_status = "Not Verified";

if(isset($user['seller_verification_status']) && $user['seller_verification_status'] != ""){
    $seller_status = $user['seller_verification_status'];
}

/* SUBMIT SELLER VERIFICATION */

if(isset($_POST['submit_verification'])){

    $business_name = mysqli_real_escape_string($conn, trim($_POST['business_name']));
    $business_type = mysqli_real_escape_string($conn, trim($_POST['business_type']));
    $business_location = mysqli_real_escape_string($conn, trim($_POST['business_location']));
    $business_profile = mysqli_real_escape_string($conn, trim($_POST['business_profile']));

    $business_bank_name = mysqli_real_escape_string($conn, trim($_POST['business_bank_name']));
    $business_account_holder = mysqli_real_escape_string($conn, trim($_POST['business_account_holder']));
    $business_account_number = mysqli_real_escape_string($conn, trim($_POST['business_account_number']));
    $business_branch_code = mysqli_real_escape_string($conn, trim($_POST['business_branch_code']));

    $id_document = $user['id_document'];

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

        $message = "Please complete all business and payment details.";

    }elseif(!preg_match("/^[0-9]{6,20}$/", $business_account_number)){

        $message = "Account number must contain numbers only.";

    }elseif(!preg_match("/^[0-9]{4,10}$/", $business_branch_code)){

        $message = "Branch code must contain numbers only.";

    }elseif(!isset($_FILES['id_document']) || $_FILES['id_document']['name'] == ""){

        if(empty($id_document)){
            $message = "Please upload your ID document.";
        }

    }

    if($message == ""){

        if(isset($_FILES['id_document']) && $_FILES['id_document']['name'] != ""){

            $allowed_extensions = ["jpg", "jpeg", "png", "pdf"];
            $file_name = $_FILES['id_document']['name'];
            $temp_name = $_FILES['id_document']['tmp_name'];
            $file_size = $_FILES['id_document']['size'];
            $file_error = $_FILES['id_document']['error'];

            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if($file_error != 0){

                $message = "ID document upload failed.";

            }elseif(!in_array($file_extension, $allowed_extensions)){

                $message = "Only JPG, JPEG, PNG and PDF documents are allowed.";

            }elseif($file_size > 5000000){

                $message = "ID document must be less than 5MB.";

            }else{

                if(!is_dir("uploads/verification")){
                    mkdir("uploads/verification", 0777, true);
                }

                $new_file_name = "id_" . $user_id . "_" . time() . "." . $file_extension;
                $upload_path = "uploads/verification/" . $new_file_name;

                if(move_uploaded_file($temp_name, $upload_path)){
                    $id_document = $new_file_name;
                }else{
                    $message = "Failed to save ID document.";
                }
            }
        }
    }

    if($message == ""){

        $update_query = "
        UPDATE users
        SET
        business_name='$business_name',
        business_type='$business_type',
        business_location='$business_location',
        business_profile='$business_profile',
        business_bank_name='$business_bank_name',
        business_account_holder='$business_account_holder',
        business_account_number='$business_account_number',
        business_branch_code='$business_branch_code',
        id_document='$id_document',
        seller_verification_status='Pending'
        WHERE user_id='$user_id'
        ";

        if(mysqli_query($conn, $update_query)){

            $message = "Seller verification submitted successfully. Your application is now pending admin approval.";
            $message_type = "success";

            $user_result = mysqli_query($conn, $user_query);
            $user = mysqli_fetch_assoc($user_result);
            $seller_status = "Pending";

        }else{

            $message = "Failed to submit verification: " . mysqli_error($conn);

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Seller Verification | StreetMarket</title>

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

.pending-box{
    background:#e0f2fe;
    color:#075985;
    border-left:5px solid #0ea5e9;
    padding:20px;
    border-radius:12px;
    margin-bottom:25px;
}

.verified-box{
    background:#dcfce7;
    color:#166534;
    border-left:5px solid #16a34a;
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

.security-note{
    background:#fef3c7;
    color:#92400e;
    border-left:5px solid #f59e0b;
    padding:18px;
    border-radius:12px;
    margin-bottom:25px;
}

.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.full-width{
    grid-column:1 / -1;
}

@media(max-width:800px){
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
<a href="my-listings.php">My Listings</a>
<a href="user-profile.php">Profile</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="page-intro">

<h2>Seller Verification</h2>

<p>
Submit your business profile, payout banking details and ID document for admin approval.
</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<?php if($seller_status == "Approved" || $seller_status == "Verified"){ ?>

<div class="verified-box">

<h3>✅ Verified Seller</h3>

<p>
Your seller account has been approved. Buyers will see your business profile, not your private personal details.
</p>

</div>

<?php }elseif($seller_status == "Pending"){ ?>

<div class="pending-box">

<h3>⏳ Verification Pending</h3>

<p>
Your seller verification application is currently being reviewed by StreetMarket administrators.
</p>

</div>

<?php }elseif($seller_status == "Rejected"){ ?>

<div class="rejected-box">

<h3>❌ Verification Rejected</h3>

<p>
Your verification was rejected. Please update your business information and upload a valid ID document before resubmitting.
</p>

</div>

<?php } ?>

<div class="security-note">

<strong>Privacy Notice:</strong>
Your ID document and banking details are private and are only used by StreetMarket administrators for seller verification and payout processing. Buyers will only see your public business profile.

</div>

<div class="form-container">

<form method="POST" enctype="multipart/form-data">

<fieldset>

<legend>Business Profile</legend>

<div class="form-grid">

<div>

<label>Business Name *</label>

<input
type="text"
name="business_name"
value="<?php echo htmlspecialchars($user['business_name'] ?? ''); ?>"
placeholder="Example: Street Fashion SA"
required>

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

<input
type="text"
name="business_location"
value="<?php echo htmlspecialchars($user['business_location'] ?? ''); ?>"
placeholder="Example: Midrand, Gauteng"
required>

</div>

<div class="full-width">

<label>Business Profile *</label>

<textarea
name="business_profile"
rows="5"
placeholder="Briefly describe what your business sells and how you operate."
required><?php echo htmlspecialchars($user['business_profile'] ?? ''); ?></textarea>

</div>

</div>

</fieldset>

<br>

<fieldset>

<legend>Private Payout Banking Details</legend>

<div class="form-grid">

<div>

<label>Bank Name *</label>

<input
type="text"
name="business_bank_name"
value="<?php echo htmlspecialchars($user['business_bank_name'] ?? ''); ?>"
placeholder="Example: Capitec, FNB, Standard Bank"
required>

</div>

<div>

<label>Account Holder Name *</label>

<input
type="text"
name="business_account_holder"
value="<?php echo htmlspecialchars($user['business_account_holder'] ?? ''); ?>"
placeholder="Account holder name"
required>

</div>

<div>

<label>Account Number *</label>

<input
type="text"
name="business_account_number"
value="<?php echo htmlspecialchars($user['business_account_number'] ?? ''); ?>"
placeholder="Account number"
required>

</div>

<div>

<label>Branch Code *</label>

<input
type="text"
name="business_branch_code"
value="<?php echo htmlspecialchars($user['business_branch_code'] ?? ''); ?>"
placeholder="Branch code"
required>

</div>

</div>

</fieldset>

<br>

<fieldset>

<legend>ID Verification Document</legend>

<label>Upload ID Document *</label>

<input
type="file"
name="id_document"
accept=".jpg,.jpeg,.png,.pdf"
<?php if(empty($user['id_document'])){ echo "required"; } ?>>

<small>
Accepted formats: JPG, JPEG, PNG or PDF. Maximum size: 5MB.
</small>

<?php if(!empty($user['id_document'])){ ?>

<p>
Current document uploaded:
<strong><?php echo htmlspecialchars($user['id_document']); ?></strong>
</p>

<?php } ?>

</fieldset>

<br>

<button type="submit" name="submit_verification">
Submit Seller Verification
</button>

</form>

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