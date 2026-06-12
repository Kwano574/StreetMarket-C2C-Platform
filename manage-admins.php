<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

requireAdminRole(["super_admin"]);

$message = "";

$current_admin_id = $_SESSION['admin_id'];

/* CHANGE ROLE */

if(isset($_POST['change_role'])){

    $admin_id = intval($_POST['admin_id']);
    $new_role = mysqli_real_escape_string($conn,$_POST['new_role']);

    $allowed_roles = [
        "super_admin",
        "user_manager",
        "product_manager",
        "order_manager",
        "payment_manager",
        "report_manager",
        "support_admin"
    ];

    if(in_array($new_role,$allowed_roles)){

        $update = "
        UPDATE admin_users
        SET role='$new_role',
        updated_at=NOW()
        WHERE admin_id='$admin_id'
        ";

        mysqli_query($conn,$update);

        $message = "Admin role updated.";

    }

}

/* SUSPEND */

if(isset($_GET['suspend'])){

    $admin_id = intval($_GET['suspend']);

    if($admin_id != $current_admin_id){

        mysqli_query($conn,"
        UPDATE admin_users
        SET status='Suspended'
        WHERE admin_id='$admin_id'
        ");

        $message = "Admin suspended.";

    }

}

/* ACTIVATE */

if(isset($_GET['activate'])){

    $admin_id = intval($_GET['activate']);

    mysqli_query($conn,"
    UPDATE admin_users
    SET status='Active'
    WHERE admin_id='$admin_id'
    ");

    $message = "Admin activated.";

}

/* DELETE */

if(isset($_GET['delete'])){

    $admin_id = intval($_GET['delete']);

    if($admin_id != $current_admin_id){

        mysqli_query($conn,"
        DELETE FROM admin_users
        WHERE admin_id='$admin_id'
        ");

        $message = "Admin removed.";

    }

}

/* GET ADMINS */

$admins_query = "
SELECT *
FROM admin_users
ORDER BY role ASC, full_name ASC
";

$admins_result = mysqli_query($conn,$admins_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Admins</title>

<link rel="stylesheet" href="css/style.css">

<style>

table{
width:100%;
border-collapse:collapse;
background:white;
margin-top:20px;
}

th,td{
padding:14px;
border-bottom:1px solid #ddd;
text-align:left;
}

th{
background:#111;
color:white;
}

.action-btn{
padding:8px 12px;
border-radius:8px;
color:white;
text-decoration:none;
font-size:13px;
display:inline-block;
margin:2px;
}

.activate-btn{
background:#16a34a;
}

.suspend-btn{
background:#ea580c;
}

.delete-btn{
background:#dc2626;
}

.role-select{
padding:8px;
border-radius:6px;
}

.status-active{
color:green;
font-weight:bold;
}

.status-suspended{
color:red;
font-weight:bold;
}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">
<img src="images/logo.png">
<h1>Manage Admins</h1>
</div>

<nav>

<a href="Admin-dashboard.php">Dashboard</a>
<a href="create-admin.php">Create Admin</a>
<a href="manage-admins.php">Manage Admins</a>
<a href="admin-logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<h2>Admin Management</h2>

<p>Manage administrator accounts and permissions.</p>

<?php if($message!=""){ ?>

<div class="auth-message">

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

<?php

if($admins_result && mysqli_num_rows($admins_result)>0){

while($admin = mysqli_fetch_assoc($admins_result)){

?>

<tr>

<td>#<?php echo $admin['admin_id']; ?></td>

<td><?php echo htmlspecialchars($admin['full_name']); ?></td>

<td><?php echo htmlspecialchars($admin['email']); ?></td>

<td><?php echo htmlspecialchars($admin['work_phone']); ?></td>

<td><?php echo htmlspecialchars($admin['department']); ?></td>

<td>

<form method="POST">

<input
type="hidden"
name="admin_id"
value="<?php echo $admin['admin_id']; ?>">

<select
name="new_role"
class="role-select">

<option value="super_admin" <?php if($admin['role']=="super_admin") echo "selected"; ?>>Super Admin</option>

<option value="user_manager" <?php if($admin['role']=="user_manager") echo "selected"; ?>>User Manager</option>

<option value="product_manager" <?php if($admin['role']=="product_manager") echo "selected"; ?>>Product Manager</option>

<option value="order_manager" <?php if($admin['role']=="order_manager") echo "selected"; ?>>Order Manager</option>

<option value="payment_manager" <?php if($admin['role']=="payment_manager") echo "selected"; ?>>Payment Manager</option>

<option value="report_manager" <?php if($admin['role']=="report_manager") echo "selected"; ?>>Report Manager</option>

<option value="support_admin" <?php if($admin['role']=="support_admin") echo "selected"; ?>>Support Admin</option>

</select>

<button
type="submit"
name="change_role">

Update

</button>

</form>

</td>

<td>

<?php if($admin['status']=="Active"){ ?>

<span class="status-active">
Active
</span>

<?php }else{ ?>

<span class="status-suspended">
Suspended
</span>

<?php } ?>

</td>

<td>

<?php

echo !empty($admin['last_login'])
? date("d M Y H:i",strtotime($admin['last_login']))
: "Never";

?>

</td>

<td>

<?php if($admin['status']=="Active"){ ?>

<a
href="manage-admins.php?suspend=<?php echo $admin['admin_id']; ?>"
class="action-btn suspend-btn">

Suspend

</a>

<?php }else{ ?>

<a
href="manage-admins.php?activate=<?php echo $admin['admin_id']; ?>"
class="action-btn activate-btn">

Activate

</a>

<?php } ?>

<?php if($admin['admin_id'] != $current_admin_id){ ?>

<a
href="manage-admins.php?delete=<?php echo $admin['admin_id']; ?>"
class="action-btn delete-btn"
onclick="return confirm('Delete this admin account?')">

Delete

</a>

<?php } ?>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="9">

No admins found.

</td>

</tr>

<?php } ?>

</table>

</div>

</section>

<footer>

<div class="container footer-container">

<p>

Copyright © 2026 StreetMarket.

</p>

</div>

</footer>

</body>

</html>