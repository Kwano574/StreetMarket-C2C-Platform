<?php
// starting php session
session_start();

// connecting page to database for admin data retrieval and updates
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// only super admin can manage admin accounts
requireAdminRole(["super_admin"]);

$message = "";
$message_type = "success";

// storing current logged-in admin id
$current_admin_id = intval($_SESSION['admin_id']);

// function to count active super admins
function countActiveSuperAdmins($conn){
    $query = "
    SELECT COUNT(*) AS total
    FROM admin_users
    WHERE role='super_admin'
    AND status='Active'
    ";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    return $data ? intval($data['total']) : 0;
}

// displaying session success message
if(isset($_SESSION['success_message'])){
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}

// displaying session error message
if(isset($_SESSION['error_message'])){
    $message = $_SESSION['error_message'];
    $message_type = "error";
    unset($_SESSION['error_message']);
}

// changing admin role
if(isset($_POST['change_role'])){
    $admin_id = intval($_POST['admin_id']);
    $new_role = trim($_POST['new_role']);

    $allowed_roles = [
        "super_admin",
        "user_manager",
        "product_manager",
        "order_manager",
        "payment_manager",
        "report_manager",
        "support_admin"
    ];

    if(!in_array($new_role, $allowed_roles)){
        $_SESSION['error_message'] = "Invalid admin role selected.";
        header("Location: manage-admins.php");
        exit();
    }

    // getting selected admin details before changing role
    $check_stmt = $conn->prepare("
    SELECT admin_id, role, status
    FROM admin_users
    WHERE admin_id=?
    LIMIT 1
    ");
    $check_stmt->bind_param("i", $admin_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if(!$check_result || $check_result->num_rows == 0){
        $_SESSION['error_message'] = "Admin account not found.";
        header("Location: manage-admins.php");
        exit();
    }

    $admin = $check_result->fetch_assoc();

    // preventing last active super admin from being demoted
    if($admin['role'] == "super_admin" && $new_role != "super_admin" && $admin['status'] == "Active"){
        if(countActiveSuperAdmins($conn) <= 1){
            $_SESSION['error_message'] = "You cannot demote the last active Super Admin.";
            header("Location: manage-admins.php");
            exit();
        }
    }

    $update_stmt = $conn->prepare("
    UPDATE admin_users
    SET role=?, updated_at=NOW()
    WHERE admin_id=?
    ");
    $update_stmt->bind_param("si", $new_role, $admin_id);

    if($update_stmt->execute()){
        $_SESSION['success_message'] = "Admin role updated successfully.";
    }else{
        $_SESSION['error_message'] = "Failed to update admin role.";
    }

    header("Location: manage-admins.php");
    exit();
}

// suspending admin account
if(isset($_GET['suspend'])){
    $admin_id = intval($_GET['suspend']);

    if($admin_id == $current_admin_id){
        $_SESSION['error_message'] = "You cannot suspend your own admin account.";
        header("Location: manage-admins.php");
        exit();
    }

    // checking admin before suspension
    $check_stmt = $conn->prepare("
    SELECT admin_id, role, status
    FROM admin_users
    WHERE admin_id=?
    LIMIT 1
    ");
    $check_stmt->bind_param("i", $admin_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if(!$check_result || $check_result->num_rows == 0){
        $_SESSION['error_message'] = "Admin account not found.";
        header("Location: manage-admins.php");
        exit();
    }

    $admin = $check_result->fetch_assoc();

    // preventing last active super admin from being suspended
    if($admin['role'] == "super_admin" && $admin['status'] == "Active"){
        if(countActiveSuperAdmins($conn) <= 1){
            $_SESSION['error_message'] = "You cannot suspend the last active Super Admin.";
            header("Location: manage-admins.php");
            exit();
        }
    }

    $suspend_stmt = $conn->prepare("
    UPDATE admin_users
    SET status='Suspended', updated_at=NOW()
    WHERE admin_id=?
    ");
    $suspend_stmt->bind_param("i", $admin_id);

    if($suspend_stmt->execute()){
        $_SESSION['success_message'] = "Admin account suspended successfully.";
    }else{
        $_SESSION['error_message'] = "Failed to suspend admin account.";
    }

    header("Location: manage-admins.php");
    exit();
}

// activating admin account
if(isset($_GET['activate'])){
    $admin_id = intval($_GET['activate']);

    $activate_stmt = $conn->prepare("
    UPDATE admin_users
    SET status='Active', failed_attempts=0, locked_until=NULL, updated_at=NOW()
    WHERE admin_id=?
    ");
    $activate_stmt->bind_param("i", $admin_id);

    if($activate_stmt->execute()){
        $_SESSION['success_message'] = "Admin account activated successfully.";
    }else{
        $_SESSION['error_message'] = "Failed to activate admin account.";
    }

    header("Location: manage-admins.php");
    exit();
}

// deleting admin account
if(isset($_GET['delete'])){
    $admin_id = intval($_GET['delete']);

    if($admin_id == $current_admin_id){
        $_SESSION['error_message'] = "You cannot delete your own admin account.";
        header("Location: manage-admins.php");
        exit();
    }

    // checking admin before deleting
    $check_stmt = $conn->prepare("
    SELECT admin_id, role, status
    FROM admin_users
    WHERE admin_id=?
    LIMIT 1
    ");
    $check_stmt->bind_param("i", $admin_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if(!$check_result || $check_result->num_rows == 0){
        $_SESSION['error_message'] = "Admin account not found.";
        header("Location: manage-admins.php");
        exit();
    }

    $admin = $check_result->fetch_assoc();

    // preventing last active super admin from being deleted
    if($admin['role'] == "super_admin" && $admin['status'] == "Active"){
        if(countActiveSuperAdmins($conn) <= 1){
            $_SESSION['error_message'] = "You cannot delete the last active Super Admin.";
            header("Location: manage-admins.php");
            exit();
        }
    }

    $delete_stmt = $conn->prepare("
    DELETE FROM admin_users
    WHERE admin_id=?
    ");
    $delete_stmt->bind_param("i", $admin_id);

    if($delete_stmt->execute()){
        $_SESSION['success_message'] = "Admin account deleted successfully.";
    }else{
        $_SESSION['error_message'] = "Failed to delete admin account.";
    }

    header("Location: manage-admins.php");
    exit();
}

// getting all admin accounts
$admins_query = "
SELECT *
FROM admin_users
ORDER BY role ASC, full_name ASC
";

$admins_result = mysqli_query($conn, $admins_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Admins | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>
.success-message{background:#dcfce7;color:#166534;border-left:5px solid #16a34a;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.error-message{background:#fee2e2;color:#991b1b;border-left:5px solid #dc2626;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
table{width:100%;border-collapse:collapse;background:white;margin-top:20px}
th,td{padding:14px;border-bottom:1px solid #ddd;text-align:left;vertical-align:middle}
th{background:#111;color:white}
.action-btn{padding:8px 12px;border-radius:8px;color:white;text-decoration:none;font-size:13px;display:inline-block;margin:2px}
.activate-btn{background:#16a34a}
.suspend-btn{background:#ea580c}
.delete-btn{background:#dc2626}
.role-select{padding:8px;border-radius:6px;border:1px solid #ccc}
.role-update-btn{padding:8px 12px;border:none;border-radius:8px;background:#111;color:white;font-weight:bold;cursor:pointer;margin-top:5px}
.status-active{color:green;font-weight:bold}
.status-suspended{color:red;font-weight:bold}
.self-badge{display:inline-block;background:#2563eb;color:white;padding:6px 10px;border-radius:20px;font-size:12px;font-weight:bold;margin-top:5px}
.info-box{background:#f8fafc;border-left:5px solid #2563eb;padding:15px;border-radius:10px;margin-bottom:20px;color:#1e3a8a}
@media(max-width:900px){table{display:block;overflow-x:auto;white-space:nowrap}}
</style>
</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>Manage Admins</h1>
</div>

<!-- Admin navigation bar links -->
<nav>
<a href="Admin-dashboard.php">Dashboard</a>
<a href="create-admin.php">Create Admin</a>
<a href="manage-admins.php">Manage Admins</a>
<a href="admin-messages.php">Messages</a>
<a href="admin-notifications.php">Notifications</a>
<a href="admin-logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">
<div class="container">

<h2>Admin Management</h2>
<p>Manage administrator accounts, permissions and account statuses.</p>

<div class="info-box">
<p><b>Security rule:</b> the system prevents deleting, suspending, or demoting the last active Super Admin.</p>
</div>

<?php if($message != ""){ ?>
<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<table>
<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Department</th>
<th>Role</th>
<th>Status</th>
<th>Last Login</th>
<th>Actions</th>
</tr>

<?php if($admins_result && mysqli_num_rows($admins_result) > 0){ ?>
<?php while($admin = mysqli_fetch_assoc($admins_result)){ ?>

<tr>
<td>#<?php echo intval($admin['admin_id']); ?></td>

<td>
<?php echo htmlspecialchars($admin['full_name']); ?>
<?php if(intval($admin['admin_id']) == $current_admin_id){ ?>
<br><span class="self-badge">Current Admin</span>
<?php } ?>
</td>

<td><?php echo htmlspecialchars($admin['email']); ?></td>

<td>
<?php echo !empty($admin['work_phone']) ? htmlspecialchars($admin['work_phone']) : "Not provided"; ?>
</td>

<td>
<?php echo !empty($admin['department']) ? htmlspecialchars($admin['department']) : "Not provided"; ?>
</td>

<td>
<form method="POST">
<input type="hidden" name="admin_id" value="<?php echo intval($admin['admin_id']); ?>">

<select name="new_role" class="role-select">
<option value="super_admin" <?php if($admin['role'] == "super_admin"){ echo "selected"; } ?>>Super Admin</option>
<option value="user_manager" <?php if($admin['role'] == "user_manager"){ echo "selected"; } ?>>User Manager</option>
<option value="product_manager" <?php if($admin['role'] == "product_manager"){ echo "selected"; } ?>>Product Manager</option>
<option value="order_manager" <?php if($admin['role'] == "order_manager"){ echo "selected"; } ?>>Order Manager</option>
<option value="payment_manager" <?php if($admin['role'] == "payment_manager"){ echo "selected"; } ?>>Payment Manager</option>
<option value="report_manager" <?php if($admin['role'] == "report_manager"){ echo "selected"; } ?>>Report Manager</option>
<option value="support_admin" <?php if($admin['role'] == "support_admin"){ echo "selected"; } ?>>Support Admin</option>
</select>

<br>
<button type="submit" name="change_role" class="role-update-btn" onclick="return confirm('Update this admin role?');">
Update Role
</button>
</form>
</td>

<td>
<?php if($admin['status'] == "Active"){ ?>
<span class="status-active">Active</span>
<?php }else{ ?>
<span class="status-suspended">Suspended</span>
<?php } ?>
</td>

<td>
<?php echo !empty($admin['last_login']) ? date("d M Y H:i", strtotime($admin['last_login'])) : "Never"; ?>
</td>

<td>
<?php if($admin['status'] == "Active"){ ?>
<a href="manage-admins.php?suspend=<?php echo intval($admin['admin_id']); ?>" class="action-btn suspend-btn" onclick="return confirm('Suspend this admin account?');">Suspend</a>
<?php }else{ ?>
<a href="manage-admins.php?activate=<?php echo intval($admin['admin_id']); ?>" class="action-btn activate-btn" onclick="return confirm('Activate this admin account?');">Activate</a>
<?php } ?>

<?php if(intval($admin['admin_id']) != $current_admin_id){ ?>
<a href="manage-admins.php?delete=<?php echo intval($admin['admin_id']); ?>" class="action-btn delete-btn" onclick="return confirm('Delete this admin account permanently?');">Delete</a>
<?php } ?>
</td>
</tr>

<?php } ?>
<?php }else{ ?>
<tr>
<td colspan="9">No admins found.</td>
</tr>
<?php } ?>
</table>

</div>
</section>

<footer>
<div class="container footer-container">
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>
</footer>

</body>
</html>