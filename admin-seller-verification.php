<?php

// starting php session
session_start();

// preventing mysqli fatal errors from breaking the page
mysqli_report(MYSQLI_REPORT_OFF);

// connecting page to database for seller verification management
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// connecting notification function for user notifications
if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

// allowing super admin, user manager and product manager to review sellers
requireAdminRole(["super_admin","user_manager","product_manager"]);

$message = "";
$message_type = "success";

// displaying success message from previous action
if(isset($_SESSION['success_message'])){
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}

// displaying error message from previous action
if(isset($_SESSION['error_message'])){
    $message = $_SESSION['error_message'];
    $message_type = "error";
    unset($_SESSION['error_message']);
}

// checking if column exists in a table
function columnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// safe helper for user notifications
function sendUserNotification($conn, $user_id, $notification_title, $notification_message, $link){

    $user_id = intval($user_id);

    if($user_id <= 0){
        return false;
    }

    // checking if notifications table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");

    if(!$table_check || mysqli_num_rows($table_check) == 0){
        return false;
    }

    // your notifications table uses tittle, but this also supports title if it exists
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

    $insert_query = "
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES('$user_id', '$notification_title', '$notification_message', '$link', 'No', NOW())
    ";

    return mysqli_query($conn, $insert_query);
}

// approving seller verification request
if(isset($_GET['approve'])){

    $seller_id = intval($_GET['approve']);

    // checking seller details before approval
    $seller_stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE user_id=?
    LIMIT 1
    ");

    if(!$seller_stmt){
        $_SESSION['error_message'] = "Seller verification could not be processed.";
        header("Location: admin-seller-verification.php");
        exit();
    }

    $seller_stmt->bind_param("i", $seller_id);
    $seller_stmt->execute();
    $seller_result = $seller_stmt->get_result();

    if(!$seller_result || $seller_result->num_rows == 0){
        $_SESSION['error_message'] = "Seller account not found.";
        header("Location: admin-seller-verification.php");
        exit();
    }

    $seller = $seller_result->fetch_assoc();

    // checking if seller submitted all required verification details
    if(
        empty($seller['business_name']) ||
        empty($seller['business_type']) ||
        empty($seller['business_location']) ||
        empty($seller['business_bank_name']) ||
        empty($seller['business_account_holder']) ||
        empty($seller['business_account_number']) ||
        empty($seller['business_branch_code']) ||
        empty($seller['id_document']) ||
        empty($seller['proof_of_residence'])
    ){

        $_SESSION['error_message'] = "Seller cannot be approved because some verification details or documents are missing.";
        header("Location: admin-seller-verification.php");
        exit();

    }

    // updating seller status to verified
    $approve_stmt = $conn->prepare("
    UPDATE users
    SET seller_verification_status='Verified',
        seller_verified_at=NOW()
    WHERE user_id=?
    ");

    if(!$approve_stmt){
        $_SESSION['error_message'] = "Seller approval failed.";
        header("Location: admin-seller-verification.php");
        exit();
    }

    $approve_stmt->bind_param("i", $seller_id);

    if($approve_stmt->execute()){

        sendUserNotification(
            $conn,
            $seller_id,
            "Seller Verification Approved",
            "Your seller account has been verified. You can now upload and sell products on StreetMarket.",
            "user-profile.php"
        );

        $_SESSION['success_message'] = "Seller verified successfully and user was notified.";

    }else{

        $_SESSION['error_message'] = "Failed to verify seller.";

    }

    header("Location: admin-seller-verification.php");
    exit();
}

// rejecting seller verification request with reason
if(isset($_POST['reject_seller'])){

    $seller_id = intval($_POST['seller_id']);
    $rejection_reason = trim($_POST['rejection_reason']);

    if($rejection_reason == ""){

        $_SESSION['error_message'] = "Please provide a reason before rejecting seller verification.";
        header("Location: admin-seller-verification.php");
        exit();

    }

    // checking if seller exists before rejection
    $seller_stmt = $conn->prepare("
    SELECT user_id, full_name
    FROM users
    WHERE user_id=?
    LIMIT 1
    ");

    if(!$seller_stmt){
        $_SESSION['error_message'] = "Seller verification could not be processed.";
        header("Location: admin-seller-verification.php");
        exit();
    }

    $seller_stmt->bind_param("i", $seller_id);
    $seller_stmt->execute();
    $seller_result = $seller_stmt->get_result();

    if(!$seller_result || $seller_result->num_rows == 0){
        $_SESSION['error_message'] = "Seller account not found.";
        header("Location: admin-seller-verification.php");
        exit();
    }

    // updating seller status to rejected
    $reject_stmt = $conn->prepare("
    UPDATE users
    SET seller_verification_status='Rejected',
        seller_verified_at=NULL
    WHERE user_id=?
    ");

    if(!$reject_stmt){
        $_SESSION['error_message'] = "Seller rejection failed.";
        header("Location: admin-seller-verification.php");
        exit();
    }

    $reject_stmt->bind_param("i", $seller_id);

    if($reject_stmt->execute()){

        sendUserNotification(
            $conn,
            $seller_id,
            "Seller Verification Rejected",
            "Your seller verification was rejected. Reason: " . $rejection_reason . " Please review your business details, ID document and proof of residence, then submit again.",
            "seller-verification.php"
        );

        $_SESSION['success_message'] = "Seller verification rejected and user was notified with the reason.";

    }else{

        $_SESSION['error_message'] = "Failed to reject seller.";

    }

    header("Location: admin-seller-verification.php");
    exit();
}

// blocking old reject links without reason
if(isset($_GET['reject'])){

    $_SESSION['error_message'] = "Please reject seller verification using the Reject button and provide a reason.";
    header("Location: admin-seller-verification.php");
    exit();

}

// getting search and filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : "";

// getting verification statistics
$pending_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE seller_verification_status='Pending'");
$pending_data = $pending_result ? mysqli_fetch_assoc($pending_result) : false;
$pending_total = $pending_data ? intval($pending_data['total']) : 0;

$verified_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE seller_verification_status='Verified' OR seller_verification_status='Approved'");
$verified_data = $verified_result ? mysqli_fetch_assoc($verified_result) : false;
$verified_total = $verified_data ? intval($verified_data['total']) : 0;

$rejected_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE seller_verification_status='Rejected'");
$rejected_data = $rejected_result ? mysqli_fetch_assoc($rejected_result) : false;
$rejected_total = $rejected_data ? intval($rejected_data['total']) : 0;

// building seller verification query
$users_query = "
SELECT *
FROM users
WHERE id_document IS NOT NULL
AND id_document != ''
";

$params = [];
$types = "";

if($search != ""){
    $search_value = "%" . $search . "%";
    $users_query .= " AND (full_name LIKE ? OR email LIKE ? OR business_name LIKE ?)";
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "sss";
}

if($status_filter != ""){
    $users_query .= " AND seller_verification_status=?";
    $params[] = $status_filter;
    $types .= "s";
}

$users_query .= "
ORDER BY
CASE
WHEN seller_verification_status='Pending' THEN 1
WHEN seller_verification_status='Rejected' THEN 2
WHEN seller_verification_status='Verified' THEN 3
WHEN seller_verification_status='Approved' THEN 4
ELSE 5
END,
user_id DESC
";

// preparing seller verification query
$users_stmt = $conn->prepare($users_query);

if(!$users_stmt){
    $users_result = false;
}else{

    if(count($params) > 0){
        $users_stmt->bind_param($types, ...$params);
    }

    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Seller Verification Requests | StreetMarket Admin</title>

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

.stats-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:15px;
margin-bottom:25px;
}

.stat-card{
background:white;
padding:20px;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
text-align:center;
}

.stat-card h3{
font-size:30px;
margin-bottom:8px;
color:#111;
}

.filter-box{
background:white;
padding:20px;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
margin-bottom:25px;
}

.filter-form{
display:grid;
grid-template-columns:2fr 1fr auto auto;
gap:12px;
align-items:end;
}

.filter-form input,
.filter-form select{
padding:12px;
border:1px solid #ddd;
border-radius:8px;
width:100%;
}

.filter-form button,
.clear-btn{
padding:12px 16px;
border:none;
border-radius:8px;
background:#111;
color:white;
text-decoration:none;
font-weight:bold;
cursor:pointer;
text-align:center;
}

.clear-btn{
background:#555;
}

.table-wrapper{
overflow-x:auto;
background:#fff;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

table{
width:100%;
border-collapse:collapse;
background:#fff;
min-width:1350px;
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

.profile-image,
.document-image,
.business-image{
width:70px;
height:70px;
border-radius:12px;
object-fit:contain;
object-position:center;
background:#f8fafc;
border:2px solid #e2e8f0;
cursor:pointer;
}

.action-buttons{
display:flex;
gap:10px;
flex-wrap:wrap;
}

.action-buttons a,
.action-buttons button,
.view-document-btn{
padding:9px 14px;
border-radius:8px;
text-decoration:none;
color:white;
font-size:14px;
font-weight:bold;
display:inline-block;
border:none;
cursor:pointer;
}

.approve-btn{
background:green;
}

.reject-btn{
background:#c62828;
}

.view-document-btn{
background:#111;
margin-top:8px;
}

.status-pending{
color:orange;
font-weight:bold;
}

.status-verified{
color:green;
font-weight:bold;
}

.status-rejected{
color:red;
font-weight:bold;
}

.status-not{
color:#555;
font-weight:bold;
}

.small-text{
font-size:13px;
color:#64748b;
}

.business-box{
font-size:14px;
line-height:1.6;
}

.business-box strong{
display:block;
color:#111;
margin-top:8px;
}

.masked-data{
font-weight:bold;
color:#111;
}

.document-block{
margin-bottom:14px;
}

.image-modal{
display:none;
position:fixed;
z-index:9999;
left:0;
top:0;
width:100%;
height:100%;
background:rgba(0,0,0,0.85);
align-items:center;
justify-content:center;
padding:30px;
}

.image-modal img{
max-width:90%;
max-height:90%;
object-fit:contain;
background:white;
border-radius:12px;
padding:10px;
}

.close-modal{
position:absolute;
top:25px;
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

@media(max-width:900px){

.filter-form{
grid-template-columns:1fr;
}

table{
min-width:1200px;
}

}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img src="images/logo.png" alt="StreetMarket Logo">

<h1>Seller Verification</h1>

</div>

<nav>

<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-reports.php">Reports</a>
 <a href="admin-messages.php">&#x1F4AC; Chat</a>   
<a href="admin-notifications.php">&#x1F514; Notifications</a>
<a href="admin-logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<h2>Seller Verification Requests</h2>

<p>
Review seller identity documents, proof of residence, business profile image and payout information before approving or rejecting verification.
</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="stats-grid">

<div class="stat-card">
<h3><?php echo $pending_total; ?></h3>
<p>Pending Requests</p>
</div>

<div class="stat-card">
<h3><?php echo $verified_total; ?></h3>
<p>Verified Sellers</p>
</div>

<div class="stat-card">
<h3><?php echo $rejected_total; ?></h3>
<p>Rejected Requests</p>
</div>

</div>

<div class="filter-box">

<form method="GET" class="filter-form">

<div>

<label>Search Seller</label>

<input
type="text"
name="search"
placeholder="Search by name, email or business name"
value="<?php echo htmlspecialchars($search); ?>">

</div>

<div>

<label>Verification Status</label>

<select name="status_filter">

<option value="">All Statuses</option>

<option value="Pending" <?php if($status_filter == "Pending"){ echo "selected"; } ?>>
Pending
</option>

<option value="Verified" <?php if($status_filter == "Verified"){ echo "selected"; } ?>>
Verified
</option>

<option value="Approved" <?php if($status_filter == "Approved"){ echo "selected"; } ?>>
Approved
</option>

<option value="Rejected" <?php if($status_filter == "Rejected"){ echo "selected"; } ?>>
Rejected
</option>

</select>

</div>

<button type="submit">Filter</button>

<a href="admin-seller-verification.php" class="clear-btn">Clear</a>

</form>

</div>

<div class="table-wrapper">

<table>

<tr>
<th>User ID</th>
<th>Profile Image</th>
<th>Seller Details</th>
<th>Business Details</th>
<th>Private Payout Details</th>
<th>ID Document</th>
<th>Proof of Residence</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php if($users_result && $users_result->num_rows > 0){ ?>

<?php while($seller = $users_result->fetch_assoc()){ ?>

<?php

$profile_path = "images/default-profile.png";

if(!empty($seller['profile_image'])){
    if(file_exists("uploads/profile/" . $seller['profile_image'])){
        $profile_path = "uploads/profile/" . $seller['profile_image'];
    }elseif(file_exists("uploads/verification/" . $seller['profile_image'])){
        $profile_path = "uploads/verification/" . $seller['profile_image'];
    }
}

$business_image_path = "";

if(!empty($seller['business_profile_image'])){
    if(file_exists("uploads/verification/" . $seller['business_profile_image'])){
        $business_image_path = "uploads/verification/" . $seller['business_profile_image'];
    }elseif(file_exists("uploads/business/" . $seller['business_profile_image'])){
        $business_image_path = "uploads/business/" . $seller['business_profile_image'];
    }
}

$id_document_path = "";

if(!empty($seller['id_document'])){
    if(file_exists("uploads/verification/" . $seller['id_document'])){
        $id_document_path = "uploads/verification/" . $seller['id_document'];
    }elseif(file_exists("uploads/verifications/" . $seller['id_document'])){
        $id_document_path = "uploads/verifications/" . $seller['id_document'];
    }
}

$proof_residence_path = "";

if(!empty($seller['proof_of_residence'])){
    if(file_exists("uploads/verification/" . $seller['proof_of_residence'])){
        $proof_residence_path = "uploads/verification/" . $seller['proof_of_residence'];
    }elseif(file_exists("uploads/verifications/" . $seller['proof_of_residence'])){
        $proof_residence_path = "uploads/verifications/" . $seller['proof_of_residence'];
    }
}

$masked_account = "Not provided";

if(!empty($seller['business_account_number'])){
    $account_number = $seller['business_account_number'];
    $masked_account = str_repeat("*", max(strlen($account_number) - 4, 0)) . substr($account_number, -4);
}

$verification_status = !empty($seller['seller_verification_status']) ? $seller['seller_verification_status'] : "Not Submitted";

$seller_name_js = htmlspecialchars(addslashes($seller['full_name']));

?>

<tr>

<td>
#<?php echo intval($seller['user_id']); ?>
</td>

<td>

<img
src="<?php echo htmlspecialchars($profile_path); ?>"
class="profile-image"
alt="Profile Image"
onclick="openImageModal('<?php echo htmlspecialchars($profile_path); ?>')">

<div class="small-text">Click to view</div>

</td>

<td>

<strong><?php echo htmlspecialchars($seller['full_name']); ?></strong>
<br>

<?php echo htmlspecialchars($seller['email']); ?>
<br>

<?php if(!empty($seller['phone'])){ ?>
+27 <?php echo htmlspecialchars($seller['phone']); ?>
<?php }else{ ?>
Phone not provided
<?php } ?>

<br>

<?php echo !empty($seller['province']) ? htmlspecialchars($seller['province']) : "Province not provided"; ?>

</td>

<td>

<div class="business-box">

<strong>Business Name</strong>
<?php echo !empty($seller['business_name']) ? htmlspecialchars($seller['business_name']) : "Not provided"; ?>

<strong>Type</strong>
<?php echo !empty($seller['business_type']) ? htmlspecialchars($seller['business_type']) : "Not provided"; ?>

<strong>Location</strong>
<?php echo !empty($seller['business_location']) ? htmlspecialchars($seller['business_location']) : "Not provided"; ?>

<strong>Business Profile Image</strong>

<?php if($business_image_path != ""){ ?>

<img
src="<?php echo htmlspecialchars($business_image_path); ?>"
class="business-image"
alt="Business Profile"
onclick="openImageModal('<?php echo htmlspecialchars($business_image_path); ?>')">

<?php }else{ ?>

Not provided

<?php } ?>

</div>

</td>

<td>

<div class="business-box">

<strong>Bank</strong>
<?php echo !empty($seller['business_bank_name']) ? htmlspecialchars($seller['business_bank_name']) : "Not provided"; ?>

<strong>Account Holder</strong>
<?php echo !empty($seller['business_account_holder']) ? htmlspecialchars($seller['business_account_holder']) : "Not provided"; ?>

<strong>Account Number</strong>
<span class="masked-data"><?php echo htmlspecialchars($masked_account); ?></span>

<strong>Branch Code</strong>
<?php echo !empty($seller['business_branch_code']) ? htmlspecialchars($seller['business_branch_code']) : "Not provided"; ?>

<strong>CVV</strong>
<span class="masked-data">Hidden for security</span>

</div>

</td>

<td>

<?php if($id_document_path != ""){ ?>

<?php $id_extension = strtolower(pathinfo($id_document_path, PATHINFO_EXTENSION)); ?>

<?php if($id_extension == "pdf"){ ?>

<a href="<?php echo htmlspecialchars($id_document_path); ?>" target="_blank" class="view-document-btn">
Open PDF
</a>

<?php }else{ ?>

<img
src="<?php echo htmlspecialchars($id_document_path); ?>"
class="document-image"
alt="ID Document"
onclick="openImageModal('<?php echo htmlspecialchars($id_document_path); ?>')">

<br>

<a href="<?php echo htmlspecialchars($id_document_path); ?>" target="_blank" class="view-document-btn">
Open Document
</a>

<?php } ?>

<?php }else{ ?>

<span class="status-rejected">No document</span>

<?php } ?>

</td>

<td>

<?php if($proof_residence_path != ""){ ?>

<?php $proof_extension = strtolower(pathinfo($proof_residence_path, PATHINFO_EXTENSION)); ?>

<?php if($proof_extension == "pdf"){ ?>

<a href="<?php echo htmlspecialchars($proof_residence_path); ?>" target="_blank" class="view-document-btn">
Open PDF
</a>

<?php }else{ ?>

<img
src="<?php echo htmlspecialchars($proof_residence_path); ?>"
class="document-image"
alt="Proof of Residence"
onclick="openImageModal('<?php echo htmlspecialchars($proof_residence_path); ?>')">

<br>

<a href="<?php echo htmlspecialchars($proof_residence_path); ?>" target="_blank" class="view-document-btn">
Open Document
</a>

<?php } ?>

<?php }else{ ?>

<span class="status-rejected">No proof</span>

<?php } ?>

</td>

<td>

<?php if($verification_status == "Verified" || $verification_status == "Approved"){ ?>

<span class="status-verified">Verified</span>

<?php }elseif($verification_status == "Pending"){ ?>

<span class="status-pending">Pending</span>

<?php }elseif($verification_status == "Rejected"){ ?>

<span class="status-rejected">Rejected</span>

<?php }else{ ?>

<span class="status-not">Not Submitted</span>

<?php } ?>

<?php if(!empty($seller['seller_verified_at'])){ ?>

<br>

<span class="small-text">
Verified: <?php echo date("d M Y H:i", strtotime($seller['seller_verified_at'])); ?>
</span>

<?php } ?>

</td>

<td>

<div class="action-buttons">

<?php if($verification_status != "Verified" && $verification_status != "Approved"){ ?>

<a
href="admin-seller-verification.php?approve=<?php echo intval($seller['user_id']); ?>"
class="approve-btn"
onclick="return confirm('Approve this seller verification?');">
Approve
</a>

<?php } ?>

<?php if($verification_status != "Rejected"){ ?>

<button
type="button"
class="reject-btn"
onclick="openReasonModal(<?php echo intval($seller['user_id']); ?>, '<?php echo $seller_name_js; ?>')">
Reject
</button>

<?php } ?>

</div>

</td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>
<td colspan="9">No seller verification requests found.</td>
</tr>

<?php } ?>

</table>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<p>
Copyright © 2026 StreetMarket. All Rights Reserved.
</p>

</div>

</footer>

<div id="imageModal" class="image-modal">

<span class="close-modal" onclick="closeImageModal()">&times;</span>

<img id="modalImage" src="" alt="Image Preview">

</div>

<div id="reasonModal" class="reason-modal">

<div class="reason-box">

<h3>Reject Seller Verification</h3>

<p id="reasonText">
Please provide a clear reason. This reason will be sent to the seller in their notification.
</p>

<form method="POST" action="admin-seller-verification.php" id="reasonForm">

<input type="hidden" name="seller_id" id="reasonSellerId">

<textarea
name="rejection_reason"
id="rejectionReason"
placeholder="Enter rejection reason here..."
required></textarea>

<div class="reason-actions">

<button type="button" class="cancel-reason" onclick="closeReasonModal()">
Cancel
</button>

<button type="submit" name="reject_seller" class="submit-reason" onclick="return validateReasonForm()">
Reject Seller
</button>

</div>

</form>

</div>

</div>

<script>

function openImageModal(imageSrc){
    document.getElementById("modalImage").src = imageSrc;
    document.getElementById("imageModal").style.display = "flex";
}

function closeImageModal(){
    document.getElementById("imageModal").style.display = "none";
    document.getElementById("modalImage").src = "";
}

function openReasonModal(sellerId, sellerName){

    document.getElementById("reasonSellerId").value = sellerId;
    document.getElementById("rejectionReason").value = "";
    document.getElementById("reasonText").innerHTML = "Please provide the reason for rejecting " + sellerName + "'s seller verification. This reason will be sent to the seller.";
    document.getElementById("reasonModal").style.display = "flex";

}

function closeReasonModal(){

    document.getElementById("reasonModal").style.display = "none";
    document.getElementById("reasonSellerId").value = "";
    document.getElementById("rejectionReason").value = "";

}

function validateReasonForm(){

    let reason = document.getElementById("rejectionReason").value.trim();

    if(reason == ""){
        alert("Please enter a reason before rejecting this seller verification.");
        return false;
    }

    return confirm("Are you sure you want to reject this seller verification? The reason will be sent to the seller.");

}

window.onclick = function(event){

    let imageModal = document.getElementById("imageModal");
    let reasonModal = document.getElementById("reasonModal");

    if(event.target === imageModal){
        closeImageModal();
    }

    if(event.target === reasonModal){
        closeReasonModal();
    }

}

document.addEventListener("keydown", function(event){

    if(event.key === "Escape"){

        if(document.getElementById("imageModal").style.display === "flex"){
            closeImageModal();
        }

        if(document.getElementById("reasonModal").style.display === "flex"){
            closeReasonModal();
        }
    }

});

</script>

</body>

</html>