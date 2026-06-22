<?php
// starting php session
session_start();

// connecting page to database for user data retrieval and updates
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// allowing super admin and user manager to manage users
requireAdminRole(["super_admin","user_manager"]);

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

// updating user account status using prepared statement
function updateUserStatus($conn, $selected_user_id, $new_status, $success_message){
    $update_stmt = $conn->prepare("
    UPDATE users
    SET status=?
    WHERE user_id=?
    ");

    $update_stmt->bind_param("si", $new_status, $selected_user_id);

    if($update_stmt->execute()){
        $_SESSION['success_message'] = $success_message;
    }else{
        $_SESSION['error_message'] = "Failed to update user account.";
    }

    header("Location: manage-users.php");
    exit();
}

// suspending user account
if(isset($_GET['suspend'])){
    $selected_user_id = intval($_GET['suspend']);
    updateUserStatus($conn, $selected_user_id, "Suspended", "User suspended successfully.");
}

// activating user account
if(isset($_GET['activate'])){
    $selected_user_id = intval($_GET['activate']);
    updateUserStatus($conn, $selected_user_id, "Active", "User activated successfully.");
}

// soft deleting user account
if(isset($_GET['delete'])){
    $selected_user_id = intval($_GET['delete']);
    updateUserStatus($conn, $selected_user_id, "Deleted", "User marked as deleted successfully.");
}

// restoring deleted user account
if(isset($_GET['restore'])){
    $selected_user_id = intval($_GET['restore']);
    updateUserStatus($conn, $selected_user_id, "Active", "User restored successfully.");
}

// getting search and filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : "";
$seller_filter = isset($_GET['seller_filter']) ? trim($_GET['seller_filter']) : "";

// building user query with filters
$users_query = "
SELECT *
FROM users
WHERE 1
";

$params = [];
$types = "";

if($search != ""){
    $search_value = "%" . $search . "%";
    $users_query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "sss";
}

if($status_filter != ""){
    $users_query .= " AND status=?";
    $params[] = $status_filter;
    $types .= "s";
}

if($seller_filter != ""){
    $users_query .= " AND seller_verification_status=?";
    $params[] = $seller_filter;
    $types .= "s";
}

$users_query .= " ORDER BY user_id DESC";

// preparing filtered users query
$users_stmt = $conn->prepare($users_query);

if(count($params) > 0){
    $users_stmt->bind_param($types, ...$params);
}

$users_stmt->execute();
$users_result = $users_stmt->get_result();

// getting user statistics
$total_users_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
$total_users_data = mysqli_fetch_assoc($total_users_result);
$total_users = $total_users_data ? intval($total_users_data['total']) : 0;

$active_users_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE status='Active' OR status IS NULL OR status=''");
$active_users_data = mysqli_fetch_assoc($active_users_result);
$active_users = $active_users_data ? intval($active_users_data['total']) : 0;

$suspended_users_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE status='Suspended'");
$suspended_users_data = mysqli_fetch_assoc($suspended_users_result);
$suspended_users = $suspended_users_data ? intval($suspended_users_data['total']) : 0;

$deleted_users_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE status='Deleted'");
$deleted_users_data = mysqli_fetch_assoc($deleted_users_result);
$deleted_users = $deleted_users_data ? intval($deleted_users_data['total']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Users | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>
.success-message{background:#dcfce7;color:#166534;border-left:5px solid #16a34a;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.error-message{background:#fee2e2;color:#991b1b;border-left:5px solid #dc2626;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:25px}
.stat-card{background:white;padding:20px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);text-align:center}
.stat-card h3{font-size:30px;margin-bottom:8px;color:#111}
.filter-box{background:white;padding:20px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:25px}
.filter-form{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end}
.filter-form input,.filter-form select{padding:12px;border:1px solid #ddd;border-radius:8px;width:100%}
.filter-form button,.clear-btn{padding:12px 16px;border:none;border-radius:8px;background:#111;color:white;text-decoration:none;font-weight:bold;cursor:pointer;text-align:center}
.clear-btn{background:#555}
table{width:100%;border-collapse:collapse;background:#fff;margin-top:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
table th,table td{padding:15px;border-bottom:1px solid #ddd;text-align:left;vertical-align:middle}
table th{background:#111;color:#fff}
.profile-image{width:65px;height:65px;border-radius:12px;object-fit:contain;object-position:center;background:#f8fafc;border:2px solid #e2e8f0;cursor:pointer}
.action-buttons{display:flex;gap:10px;flex-wrap:wrap}
.action-buttons a{padding:8px 14px;border-radius:6px;text-decoration:none;color:#fff;font-size:14px;font-weight:bold}
.activate-btn{background:green}
.suspend-btn{background:orange}
.delete-btn{background:red}
.restore-btn{background:#111}
.status-active{color:green;font-weight:bold}
.status-suspended{color:red;font-weight:bold}
.status-deleted{color:#555;font-weight:bold}
.verified-badge{display:inline-block;background:#16a34a;color:white;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:bold}
.pending-badge{display:inline-block;background:#f59e0b;color:white;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:bold}
.rejected-badge{display:inline-block;background:#dc2626;color:white;padding:6px 12px;border-radius:20px;font-size:13px;font-weight:bold}
.not-verified{color:#777;font-weight:bold}
.image-modal{display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;padding:30px}
.image-modal img{max-width:90%;max-height:90%;object-fit:contain;background:white;border-radius:12px;padding:10px}
.close-modal{position:absolute;top:25px;right:35px;color:white;font-size:40px;font-weight:bold;cursor:pointer}
@media(max-width:900px){.filter-form{grid-template-columns:1fr}table{display:block;overflow-x:auto;white-space:nowrap}}
</style>
</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>Manage Users</h1>
</div>

<!-- Admin navigation links -->
<nav>
<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-products.php">Products</a>
<a href="admin-seller-verification.php">Seller Verification</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-messages.php">&#x1F4AC; Chat</a>
<a href="admin-notifications.php">&#x1F514; Notifications</a>
<a href="admin-logout.php">Logout</a>
</nav>
</div>
</header>

<main>

<section class="section-spacing">
<div class="container">

<h2>Registered Users</h2>
<p>Monitor buyer and seller accounts across StreetMarket.</p>

<?php if($message != ""){ ?>
<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<!-- User statistics cards -->
<div class="stats-grid">
<div class="stat-card">
<h3><?php echo $total_users; ?></h3>
<p>Total Users</p>
</div>

<div class="stat-card">
<h3><?php echo $active_users; ?></h3>
<p>Active Users</p>
</div>

<div class="stat-card">
<h3><?php echo $suspended_users; ?></h3>
<p>Suspended Users</p>
</div>

<div class="stat-card">
<h3><?php echo $deleted_users; ?></h3>
<p>Deleted Users</p>
</div>
</div>

<!-- Search and filter form -->
<div class="filter-box">
<form method="GET" class="filter-form">
<div>
<label>Search User</label>
<input type="text" name="search" placeholder="Search by name, email or phone" value="<?php echo htmlspecialchars($search); ?>">
</div>

<div>
<label>Account Status</label>
<select name="status_filter">
<option value="">All Statuses</option>
<option value="Active" <?php if($status_filter == "Active"){ echo "selected"; } ?>>Active</option>
<option value="Suspended" <?php if($status_filter == "Suspended"){ echo "selected"; } ?>>Suspended</option>
<option value="Deleted" <?php if($status_filter == "Deleted"){ echo "selected"; } ?>>Deleted</option>
</select>
</div>

<div>
<label>Seller Status</label>
<select name="seller_filter">
<option value="">All Sellers</option>
<option value="Verified" <?php if($seller_filter == "Verified"){ echo "selected"; } ?>>Verified</option>
<option value="Approved" <?php if($seller_filter == "Approved"){ echo "selected"; } ?>>Approved</option>
<option value="Pending" <?php if($seller_filter == "Pending"){ echo "selected"; } ?>>Pending</option>
<option value="Rejected" <?php if($seller_filter == "Rejected"){ echo "selected"; } ?>>Rejected</option>
<option value="Not Verified" <?php if($seller_filter == "Not Verified"){ echo "selected"; } ?>>Not Verified</option>
</select>
</div>

<button type="submit">Filter</button>
<a href="manage-users.php" class="clear-btn">Clear</a>
</form>
</div>

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

<?php if($users_result && $users_result->num_rows > 0){ ?>
<?php while($user = $users_result->fetch_assoc()){ ?>
<?php
$profile_path = "images/default-profile.png";

if(!empty($user['profile_image'])){
    $profile_path = "uploads/profile/" . $user['profile_image'];
}

$seller_status = "Not Verified";

if(isset($user['seller_verification_status']) && $user['seller_verification_status'] != ""){
    $seller_status = $user['seller_verification_status'];
}

$account_status = "Active";

if(isset($user['status']) && $user['status'] != ""){
    $account_status = $user['status'];
}
?>

<tr>
<td>
<img src="<?php echo htmlspecialchars($profile_path); ?>" class="profile-image" alt="Profile" onclick="openImageModal('<?php echo htmlspecialchars($profile_path); ?>')">
</td>

<td><?php echo htmlspecialchars($user['full_name']); ?></td>
<td><?php echo htmlspecialchars($user['email']); ?></td>
<td>+27 <?php echo htmlspecialchars($user['phone']); ?></td>
<td><?php echo htmlspecialchars($user['province']); ?></td>

<td>
<?php if($seller_status == "Verified" || $seller_status == "Approved"){ ?>
<span class="verified-badge">&#9989; Verified Seller</span>
<?php }elseif($seller_status == "Pending"){ ?>
<span class="pending-badge">Pending</span>
<?php }elseif($seller_status == "Rejected"){ ?>
<span class="rejected-badge">Rejected</span>
<?php }else{ ?>
<span class="not-verified"><?php echo htmlspecialchars($seller_status); ?></span>
<?php } ?>
</td>

<td>
<?php if($account_status == "Suspended"){ ?>
<span class="status-suspended">Suspended</span>
<?php }elseif($account_status == "Deleted"){ ?>
<span class="status-deleted">Deleted</span>
<?php }else{ ?>
<span class="status-active">Active</span>
<?php } ?>
</td>

<td>
<div class="action-buttons">
<?php if($account_status == "Suspended"){ ?>
<a href="manage-users.php?activate=<?php echo intval($user['user_id']); ?>" class="activate-btn" onclick="return confirm('Activate this user account?');">Activate</a>
<?php }elseif($account_status == "Deleted"){ ?>
<a href="manage-users.php?restore=<?php echo intval($user['user_id']); ?>" class="restore-btn" onclick="return confirm('Restore this user account?');">Restore</a>
<?php }else{ ?>
<a href="manage-users.php?suspend=<?php echo intval($user['user_id']); ?>" class="suspend-btn" onclick="return confirm('Suspend this user account?');">Suspend</a>
<a href="manage-users.php?delete=<?php echo intval($user['user_id']); ?>" class="delete-btn" onclick="return confirm('Mark this user as deleted? They will not be able to login.');">Delete</a>
<?php } ?>
</div>
</td>
</tr>

<?php } ?>
<?php }else{ ?>
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
<li>Administrators can search and filter users for faster moderation.</li>
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