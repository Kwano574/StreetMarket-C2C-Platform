<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

requireAdminRole(["user_manager", "product_manager"]);

$message = "";
$message_type = "success";

/* APPROVE SELLER */

if(isset($_GET['approve'])){

    $seller_id = intval($_GET['approve']);

    $seller_query = "
    SELECT full_name, business_name
    FROM users
    WHERE user_id='$seller_id'
    LIMIT 1
    ";

    $seller_result = mysqli_query($conn, $seller_query);
    $seller_data = mysqli_fetch_assoc($seller_result);

    $approve_query = "
    UPDATE users
    SET
    seller_verification_status='Verified',
    seller_verified_at=NOW()
    WHERE user_id='$seller_id'
    ";

    if(mysqli_query($conn, $approve_query)){

        if(function_exists("createNotification")){
            createNotification(
                $conn,
                $seller_id,
                "Seller Verification Approved",
                "Your seller account has been verified. You can now upload and sell products on StreetMarket.",
                "user-profile.php"
            );
        }

        $message = "Seller verified successfully.";
        $message_type = "success";

    }else{
        $message = "Failed to verify seller.";
        $message_type = "error";
    }
}

/* REJECT SELLER */

if(isset($_GET['reject'])){

    $seller_id = intval($_GET['reject']);

    $reject_query = "
    UPDATE users
    SET
    seller_verification_status='Rejected',
    seller_verified_at=NULL
    WHERE user_id='$seller_id'
    ";

    if(mysqli_query($conn, $reject_query)){

        if(function_exists("createNotification")){
            createNotification(
                $conn,
                $seller_id,
                "Seller Verification Rejected",
                "Your seller verification was rejected. Please review your business details and ID document, then submit again.",
                "seller-verification.php"
            );
        }

        $message = "Seller verification rejected.";
        $message_type = "success";

    }else{
        $message = "Failed to reject seller.";
        $message_type = "error";
    }
}

/* GET SELLER REQUESTS */

$users_query = "
SELECT *
FROM users
WHERE id_document IS NOT NULL
AND id_document != ''
ORDER BY
CASE
WHEN seller_verification_status='Pending' THEN 1
WHEN seller_verification_status='Rejected' THEN 2
WHEN seller_verification_status='Verified' THEN 3
ELSE 4
END,
user_id DESC
";

$users_result = mysqli_query($conn, $users_query);

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
min-width:1200px;
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
.document-image{
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
.view-document-btn{
padding:9px 14px;
border-radius:8px;
text-decoration:none;
color:white;
font-size:14px;
font-weight:bold;
display:inline-block;
}

.approve-btn{background:green;}
.reject-btn{background:#c62828;}
.view-document-btn{background:#111;}

.status-pending{color:orange;font-weight:bold;}
.status-verified{color:green;font-weight:bold;}
.status-rejected{color:red;font-weight:bold;}
.status-not{color:#555;font-weight:bold;}

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
<a href="admin-logout.php">Logout</a>
</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<h2>Seller Verification Requests</h2>

<p>
Review seller identity documents, business profile details and payout information before approving or rejecting verification.
</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="table-wrapper">

<table>

<tr>
<th>User ID</th>
<th>Profile Image</th>
<th>Seller Details</th>
<th>Business Profile</th>
<th>Private Payout Details</th>
<th>ID Document</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php if($users_result && mysqli_num_rows($users_result) > 0){ ?>

<?php while($seller = mysqli_fetch_assoc($users_result)){ ?>

<?php

$profile_path = "images/default-profile.png";

if(!empty($seller['profile_image'])){
    $profile_path = "uploads/profile/" . $seller['profile_image'];
}

$id_document_path = "";

if(!empty($seller['id_document'])){
    if(file_exists("uploads/verification/" . $seller['id_document'])){
        $id_document_path = "uploads/verification/" . $seller['id_document'];
    }else{
        $id_document_path = "uploads/verifications/" . $seller['id_document'];
    }
}

?>

<tr>

<td>#<?php echo intval($seller['user_id']); ?></td>

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
+27 <?php echo htmlspecialchars($seller['phone']); ?>
<br>
<?php echo htmlspecialchars($seller['province']); ?>

</td>

<td>

<div class="business-box">

<strong>Business Name</strong>
<?php echo !empty($seller['business_name']) ? htmlspecialchars($seller['business_name']) : "Not provided"; ?>

<strong>Type</strong>
<?php echo !empty($seller['business_type']) ? htmlspecialchars($seller['business_type']) : "Not provided"; ?>

<strong>Location</strong>
<?php echo !empty($seller['business_location']) ? htmlspecialchars($seller['business_location']) : "Not provided"; ?>

<strong>Profile</strong>
<?php echo !empty($seller['business_profile']) ? nl2br(htmlspecialchars($seller['business_profile'])) : "Not provided"; ?>

</div>

</td>

<td>

<div class="business-box">

<strong>Bank</strong>
<?php echo !empty($seller['business_bank_name']) ? htmlspecialchars($seller['business_bank_name']) : "Not provided"; ?>

<strong>Account Holder</strong>
<?php echo !empty($seller['business_account_holder']) ? htmlspecialchars($seller['business_account_holder']) : "Not provided"; ?>

<strong>Account Number</strong>
<?php echo !empty($seller['business_account_number']) ? htmlspecialchars($seller['business_account_number']) : "Not provided"; ?>

<strong>Branch Code</strong>
<?php echo !empty($seller['business_branch_code']) ? htmlspecialchars($seller['business_branch_code']) : "Not provided"; ?>

</div>

</td>

<td>

<?php if($id_document_path != ""){ ?>

<img
src="<?php echo htmlspecialchars($id_document_path); ?>"
class="document-image"
alt="ID Document"
onclick="openImageModal('<?php echo htmlspecialchars($id_document_path); ?>')">

<br><br>

<a href="<?php echo htmlspecialchars($id_document_path); ?>" target="_blank" class="view-document-btn">
Open Document
</a>

<?php }else{ ?>

<span class="status-rejected">No document</span>

<?php } ?>

</td>

<td>

<?php if($seller['seller_verification_status'] == "Verified"){ ?>

<span class="status-verified">Verified</span>

<?php }elseif($seller['seller_verification_status'] == "Pending"){ ?>

<span class="status-pending">Pending</span>

<?php }elseif($seller['seller_verification_status'] == "Rejected"){ ?>

<span class="status-rejected">Rejected</span>

<?php }else{ ?>

<span class="status-not">Not Submitted</span>

<?php } ?>

</td>

<td>

<div class="action-buttons">

<?php if($seller['seller_verification_status'] != "Verified"){ ?>

<a
href="admin-seller-verification.php?approve=<?php echo intval($seller['user_id']); ?>"
class="approve-btn"
onclick="return confirm('Approve this seller verification?');">
Approve
</a>

<?php } ?>

<?php if($seller['seller_verification_status'] != "Rejected"){ ?>

<a
href="admin-seller-verification.php?reject=<?php echo intval($seller['user_id']); ?>"
class="reject-btn"
onclick="return confirm('Reject this seller verification?');">
Reject
</a>

<?php } ?>

</div>

</td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>
<td colspan="8">No seller verification requests found.</td>
</tr>

<?php } ?>

</table>

</div>

</div>

</section>

<footer>

<div class="container footer-container">
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>

</footer>

<div id="imageModal" class="image-modal">
<span class="close-modal" onclick="closeImageModal()">&times;</span>
<img id="modalImage" src="" alt="Image Preview">
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

window.onclick = function(event){
    let modal = document.getElementById("imageModal");
    if(event.target === modal){
        closeImageModal();
    }
}

</script>

</body>

</html>