<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

requireAdminRole(["user_manager"]);

$message = "";

if(isset($_GET['suspend'])){
    $selected_user_id = intval($_GET['suspend']);
    $query = "UPDATE users SET status='Suspended' WHERE user_id='$selected_user_id'";
    $message = mysqli_query($conn, $query) ? "User suspended successfully." : "Failed to suspend user.";
}

if(isset($_GET['activate'])){
    $selected_user_id = intval($_GET['activate']);
    $query = "UPDATE users SET status='Active' WHERE user_id='$selected_user_id'";
    $message = mysqli_query($conn, $query) ? "User activated successfully." : "Failed to activate user.";
}

if(isset($_GET['delete'])){
    $selected_user_id = intval($_GET['delete']);
    $query = "UPDATE users SET status='Deleted' WHERE user_id='$selected_user_id'";
    $message = mysqli_query($conn, $query) ? "User marked as deleted successfully." : "Failed to delete user.";
}

if(isset($_GET['restore'])){
    $selected_user_id = intval($_GET['restore']);
    $query = "UPDATE users SET status='Active' WHERE user_id='$selected_user_id'";
    $message = mysqli_query($conn, $query) ? "User restored successfully." : "Failed to restore user.";
}

$users_query = "SELECT * FROM users ORDER BY user_id DESC";
$users_result = mysqli_query($conn, $users_query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Users | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>
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
    vertical-align:middle;
}

table th{
    background:#111;
    color:#fff;
}

.profile-image{
    width:65px;
    height:65px;
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

.action-buttons a{
    padding:8px 14px;
    border-radius:6px;
    text-decoration:none;
    color:#fff;
    font-size:14px;
}

.activate-btn{background:green;}
.suspend-btn{background:orange;}
.delete-btn{background:red;}
.restore-btn{background:#111;}

.status-active{
    color:green;
    font-weight:bold;
}

.status-suspended{
    color:red;
    font-weight:bold;
}

.status-deleted{
    color:#555;
    font-weight:bold;
}

.verified-badge{
    display:inline-block;
    background:#16a34a;
    color:white;
    padding:6px 12px;
    border-radius:20px;
    font-size:13px;
    font-weight:bold;
}

.not-verified{
    color:#777;
    font-weight:bold;
}

.auth-message{
    background:#dcfce7;
    color:#166534;
    border-left:5px solid #16a34a;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    font-weight:bold;
}

/* IMAGE MODAL */
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
<h1>Manage Users</h1>
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

<h2>Registered Users</h2>

<p>
Monitor buyer and seller accounts across StreetMarket.
</p>

<?php if($message != ""){ ?>
<div class="auth-message">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<table>

<tr>
<th>Profile</th>
<th>Full Name</th>
<th>Email</th>
<th>Phone</th>
<th>Province</th>
<th>Seller Status</th>
<th>Account Status</th>
<th>Action</th>
</tr>

<?php

if($users_result && mysqli_num_rows($users_result) > 0){

while($user = mysqli_fetch_assoc($users_result)){

    $profile_path = "images/default-profile.png";

    if(!empty($user['profile_image'])){
        $profile_path = "uploads/profile/" . $user['profile_image'];
    }

    $seller_status = "Not Verified";

    if(isset($user['seller_verification_status']) && $user['seller_verification_status'] != ""){
        $seller_status = $user['seller_verification_status'];
    }

?>

<tr>

<td>
<img
src="<?php echo htmlspecialchars($profile_path); ?>"
class="profile-image"
alt="Profile"
onclick="openImageModal('<?php echo htmlspecialchars($profile_path); ?>')">
</td>

<td><?php echo htmlspecialchars($user['full_name']); ?></td>

<td><?php echo htmlspecialchars($user['email']); ?></td>

<td>+27 <?php echo htmlspecialchars($user['phone']); ?></td>

<td><?php echo htmlspecialchars($user['province']); ?></td>

<td>
<?php if($seller_status == "Verified"){ ?>
<span class="verified-badge">&#9989; Verified Seller</span>
<?php }else{ ?>
<span class="not-verified"><?php echo htmlspecialchars($seller_status); ?></span>
<?php } ?>
</td>

<td>
<?php if($user['status'] == "Suspended"){ ?>
<span class="status-suspended">Suspended</span>
<?php }elseif($user['status'] == "Deleted"){ ?>
<span class="status-deleted">Deleted</span>
<?php }else{ ?>
<span class="status-active">Active</span>
<?php } ?>
</td>

<td>
<div class="action-buttons">

<?php if($user['status'] == "Suspended"){ ?>

<a href="manage-users.php?activate=<?php echo $user['user_id']; ?>" class="activate-btn">
Activate
</a>

<?php }elseif($user['status'] == "Deleted"){ ?>

<a href="manage-users.php?restore=<?php echo $user['user_id']; ?>" class="restore-btn">
Restore
</a>

<?php }else{ ?>

<a href="manage-users.php?suspend=<?php echo $user['user_id']; ?>" class="suspend-btn">
Suspend
</a>

<a
href="manage-users.php?delete=<?php echo $user['user_id']; ?>"
class="delete-btn"
onclick="return confirm('Mark this user as deleted? They will not be able to login.');">
Delete
</a>

<?php } ?>

</div>
</td>

</tr>

<?php

}

}else{

?>

<tr>
<td colspan="8">No users found.</td>
</tr>

<?php } ?>

</table>

</div>
</section>

<section class="section-spacing">
<div class="container">

<div class="info-box">

<h2>Security Measures</h2>

<ul>
<li>Suspended users cannot login.</li>
<li>Deleted users cannot login unless restored by admin.</li>
<li>Soft delete keeps order, report and marketplace records safe.</li>
<li>Administrators can restore accounts when needed.</li>
<li>Accounts can be suspended for suspicious activity.</li>
</ul>

</div>

</div>
</section>

</main>

<footer>
<div class="container footer-container">
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>
</footer>

<div id="imageModal" class="image-modal">
<span class="close-modal" onclick="closeImageModal()">&times;</span>
<img id="modalImage" src="" alt="Profile Preview">
</div>

<script>
function openImageModal(imageSrc){
    document.getElementById("modalImage").src = imageSrc;
    document.getElementById("imageModal").style.display = "flex";
}

function closeImageModal(){
    document.getElementById("imageModal").style.display = "none";
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