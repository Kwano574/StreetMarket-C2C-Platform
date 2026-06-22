<?php

// starting php session
session_start();

// preventing mysqli errors from causing 500 page
mysqli_report(MYSQLI_REPORT_OFF);

// connecting to database
include("includes/db.php");

// protecting session by checking if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = "";
$message_type = "error";

// checking if a column exists before using it
function columnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// adding required columns safely if they do not exist
if(!columnExists($conn, "users", "business_profile_image")){
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN business_profile_image VARCHAR(255) NULL");
}

if(!columnExists($conn, "users", "proof_of_residence")){
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN proof_of_residence VARCHAR(255) NULL");
}

if(!columnExists($conn, "users", "business_cvv")){
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN business_cvv VARCHAR(10) NULL");
}

// getting user details
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

// uploading document files
function uploadDocumentFile($file_input_name, $user_id, $prefix, &$message){

    if(!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['name'] == ""){
        return "";
    }

    $allowed_extensions = ["jpg", "jpeg", "png", "pdf"];

    $file_name = $_FILES[$file_input_name]['name'];
    $temp_name = $_FILES[$file_input_name]['tmp_name'];
    $file_size = $_FILES[$file_input_name]['size'];
    $file_error = $_FILES[$file_input_name]['error'];

    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if($file_error != 0){
        $message = "Document upload failed. Please try again.";
        return "";
    }

    if(!in_array($file_extension, $allowed_extensions)){
        $message = "Only JPG, JPEG, PNG and PDF documents are allowed.";
        return "";
    }

    if($file_size > 5000000){
        $message = "Each document must be less than 5MB.";
        return "";
    }

    if(!is_dir("uploads/verification")){
        mkdir("uploads/verification", 0777, true);
    }

    $new_file_name = $prefix . "_" . $user_id . "_" . time() . "." . $file_extension;
    $upload_path = "uploads/verification/" . $new_file_name;

    if(move_uploaded_file($temp_name, $upload_path)){
        return $new_file_name;
    }else{
        $message = "Failed to save uploaded document.";
        return "";
    }
}

// uploading business image file
function uploadImageFile($file_input_name, $user_id, $prefix, &$message){

    if(!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['name'] == ""){
        return "";
    }

    $allowed_extensions = ["jpg", "jpeg", "png"];

    $file_name = $_FILES[$file_input_name]['name'];
    $temp_name = $_FILES[$file_input_name]['tmp_name'];
    $file_size = $_FILES[$file_input_name]['size'];
    $file_error = $_FILES[$file_input_name]['error'];

    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if($file_error != 0){
        $message = "Business image upload failed. Please try again.";
        return "";
    }

    if(!in_array($file_extension, $allowed_extensions)){
        $message = "Only JPG, JPEG and PNG images are allowed for business profile image.";
        return "";
    }

    if($file_size > 5000000){
        $message = "Business image must be less than 5MB.";
        return "";
    }

    if(!is_dir("uploads/verification")){
        mkdir("uploads/verification", 0777, true);
    }

    $new_file_name = $prefix . "_" . $user_id . "_" . time() . "." . $file_extension;
    $upload_path = "uploads/verification/" . $new_file_name;

    if(move_uploaded_file($temp_name, $upload_path)){
        return $new_file_name;
    }else{
        $message = "Failed to save business profile image.";
        return "";
    }
}

// notifying main super admin and user manager
function notifyAdminUsers($conn, $seller_id, $seller_name){

    $seller_id = intval($seller_id);
    $seller_name_safe = mysqli_real_escape_string($conn, $seller_name);

    // checking if admin notifications table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'admin_notifications'");

    if(!$table_check || mysqli_num_rows($table_check) == 0){
        return false;
    }

    // checking if admin users table exists
    $admin_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'admin_users'");

    if(!$admin_table_check || mysqli_num_rows($admin_table_check) == 0){
        return false;
    }

    $admin_query = "
    SELECT admin_id
    FROM admin_users
    WHERE LOWER(REPLACE(role, ' ', '_')) IN ('super_admin', 'main_super_admin', 'user_manager')
    ";

    $admin_result = mysqli_query($conn, $admin_query);

    if($admin_result && mysqli_num_rows($admin_result) > 0){

        while($admin = mysqli_fetch_assoc($admin_result)){

            $admin_id = intval($admin['admin_id']);

            $title = mysqli_real_escape_string($conn, "New Seller Verification");
            $notification_message = mysqli_real_escape_string($conn, $seller_name_safe . " submitted a seller verification request.");
            $link = mysqli_real_escape_string($conn, "seller-verification.php");

            mysqli_query($conn, "
            INSERT INTO admin_notifications(admin_id, title, message, link, is_read, created_at)
            VALUES('$admin_id', '$title', '$notification_message', '$link', 'No', NOW())
            ");
        }
    }

    return true;
}

// submitting seller verification
if(isset($_POST['submit_verification'])){

    // business details
    $business_name = mysqli_real_escape_string($conn, trim($_POST['business_name']));
    $business_type = mysqli_real_escape_string($conn, trim($_POST['business_type']));
    $business_location = mysqli_real_escape_string($conn, trim($_POST['business_location']));

    // private banking details
    $business_bank_name = mysqli_real_escape_string($conn, trim($_POST['business_bank_name']));
    $business_account_holder = mysqli_real_escape_string($conn, trim($_POST['business_account_holder']));
    $business_account_number = mysqli_real_escape_string($conn, trim($_POST['business_account_number']));
    $business_branch_code = mysqli_real_escape_string($conn, trim($_POST['business_branch_code']));
    $business_cvv = mysqli_real_escape_string($conn, trim($_POST['business_cvv']));

    // existing uploads
    $business_profile_image = isset($user['business_profile_image']) ? $user['business_profile_image'] : "";
    $id_document = isset($user['id_document']) ? $user['id_document'] : "";
    $proof_of_residence = isset($user['proof_of_residence']) ? $user['proof_of_residence'] : "";

    // validating required fields
    if(
        empty($business_name) ||
        empty($business_type) ||
        empty($business_location) ||
        empty($business_bank_name) ||
        empty($business_account_holder) ||
        empty($business_account_number) ||
        empty($business_branch_code) ||
        empty($business_cvv)
    ){

        $message = "Please complete all business and bank account details.";

    }elseif(!preg_match("/^[0-9]{6,20}$/", $business_account_number)){

        $message = "Account number must contain numbers only and be between 6 and 20 digits.";

    }elseif(!preg_match("/^[0-9]{4,10}$/", $business_branch_code)){

        $message = "Branch code must contain numbers only and be between 4 and 10 digits.";

    }elseif(!preg_match("/^[0-9]{3,4}$/", $business_cvv)){

        $message = "CVV must contain 3 or 4 numbers only.";

    }elseif(empty($id_document) && (!isset($_FILES['id_document']) || $_FILES['id_document']['name'] == "")){

        $message = "Please upload your ID document.";

    }elseif(empty($proof_of_residence) && (!isset($_FILES['proof_of_residence']) || $_FILES['proof_of_residence']['name'] == "")){

        $message = "Please upload your proof of residence.";

    }

    // uploading business profile image if selected
    if($message == ""){

        $new_business_profile_image = uploadImageFile("business_profile_image", $user_id, "business_profile", $message);

        if($message == "" && $new_business_profile_image != ""){
            $business_profile_image = $new_business_profile_image;
        }
    }

    // uploading ID document if selected
    if($message == ""){

        $new_id_document = uploadDocumentFile("id_document", $user_id, "id", $message);

        if($message == "" && $new_id_document != ""){
            $id_document = $new_id_document;
        }
    }

    // uploading proof of residence if selected
    if($message == ""){

        $new_proof_of_residence = uploadDocumentFile("proof_of_residence", $user_id, "residence", $message);

        if($message == "" && $new_proof_of_residence != ""){
            $proof_of_residence = $new_proof_of_residence;
        }
    }

    // updating database
    if($message == ""){

        $business_profile_image = mysqli_real_escape_string($conn, $business_profile_image);
        $id_document = mysqli_real_escape_string($conn, $id_document);
        $proof_of_residence = mysqli_real_escape_string($conn, $proof_of_residence);

        $update_query = "
        UPDATE users
        SET
        business_name='$business_name',
        business_type='$business_type',
        business_location='$business_location',
        business_profile_image='$business_profile_image',
        business_bank_name='$business_bank_name',
        business_account_holder='$business_account_holder',
        business_account_number='$business_account_number',
        business_branch_code='$business_branch_code',
        business_cvv='$business_cvv',
        id_document='$id_document',
        proof_of_residence='$proof_of_residence',
        seller_verification_status='Pending'
        WHERE user_id='$user_id'
        ";

        if(mysqli_query($conn, $update_query)){

            notifyAdminUsers($conn, $user_id, $user['full_name']);

            $message = "Seller verification submitted successfully. Your application is now pending admin approval.";
            $message_type = "success";

            $user_result = mysqli_query($conn, $user_query);
            $user = mysqli_fetch_assoc($user_result);
            $seller_status = "Pending";

        }else{

            $message = "Failed to submit verification. Please check that all seller verification columns exist in the users table.";

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

.current-file{
background:#f8fafc;
border:1px solid #e2e8f0;
padding:10px;
border-radius:8px;
margin-top:8px;
font-size:14px;
}

.preview-img{
width:130px;
height:130px;
object-fit:cover;
border-radius:12px;
border:1px solid #ddd;
margin-top:8px;
display:block;
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
Submit your business details, private bank account details and verification documents for admin approval.
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
Your seller account has been approved. You can now list and sell products on StreetMarket.
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
Your verification was rejected. Please update your business details, bank account details and documents before resubmitting.
</p>

</div>

<?php } ?>

<div class="security-note">

<strong>Privacy Notice:</strong>
Your bank account details, CVV, ID document and proof of residence are private and are only visible to StreetMarket administrators for seller verification and payout processing.

</div>

<div class="form-container">

<form method="POST" enctype="multipart/form-data">

<fieldset>

<legend>Business Details</legend>

<div class="form-grid">

<div>

<label>Business Name *</label>

<input
type="text"
name="business_name"
value="<?php echo htmlspecialchars($user['business_name'] ?? ''); ?>"
placeholder="Example: P's Fish and Chips"
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

<option value="<?php echo htmlspecialchars($type); ?>" <?php if(($user['business_type'] ?? '') == $type){ echo "selected"; } ?>>
<?php echo htmlspecialchars($type); ?>
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
placeholder="Example: 25 Gloria Hills, Western Cape"
required>

</div>

<div class="full-width">

<label>Business Profile Image</label>

<input
type="file"
name="business_profile_image"
accept=".jpg,.jpeg,.png">

<small>Accepted formats: JPG, JPEG or PNG. Maximum size: 5MB.</small>

<?php if(!empty($user['business_profile_image'])){ ?>

<img
class="preview-img"
src="uploads/verification/<?php echo htmlspecialchars($user['business_profile_image']); ?>"
alt="Business Profile Image">

<?php } ?>

</div>

</div>

</fieldset>

<br>

<fieldset>

<legend>Private Bank Account Details</legend>

<div class="form-grid">

<div>

<label>Bank Name *</label>

<input
type="text"
name="business_bank_name"
value="<?php echo htmlspecialchars($user['business_bank_name'] ?? ''); ?>"
placeholder="Example: FNB"
required>

</div>

<div>

<label>Account Holder Name *</label>

<input
type="text"
name="business_account_holder"
value="<?php echo htmlspecialchars($user['business_account_holder'] ?? ''); ?>"
placeholder="Example: P Nkabi"
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

<div>

<label>CVV *</label>

<input
type="password"
name="business_cvv"
value="<?php echo htmlspecialchars($user['business_cvv'] ?? ''); ?>"
placeholder="CVV"
maxlength="4"
required>

<small>CVV is hidden for security.</small>

</div>

</div>

</fieldset>

<br>

<fieldset>

<legend>ID Documents</legend>

<div class="form-grid">

<div>

<label>ID Document *</label>

<input
type="file"
name="id_document"
accept=".jpg,.jpeg,.png,.pdf"
<?php if(empty($user['id_document'])){ echo "required"; } ?>>

<small>Accepted formats: JPG, JPEG, PNG or PDF. Maximum size: 5MB.</small>

<?php if(!empty($user['id_document'])){ ?>

<div class="current-file">
Current ID document uploaded:
<strong><?php echo htmlspecialchars($user['id_document']); ?></strong>
</div>

<?php } ?>

</div>

<div>

<label>Proof of Residence *</label>

<input
type="file"
name="proof_of_residence"
accept=".jpg,.jpeg,.png,.pdf"
<?php if(empty($user['proof_of_residence'])){ echo "required"; } ?>>

<small>Accepted formats: JPG, JPEG, PNG or PDF. Maximum size: 5MB.</small>

<?php if(!empty($user['proof_of_residence'])){ ?>

<div class="current-file">
Current proof of residence uploaded:
<strong><?php echo htmlspecialchars($user['proof_of_residence']); ?></strong>
</div>

<?php } ?>

</div>

</div>

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