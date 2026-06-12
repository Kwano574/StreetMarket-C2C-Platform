<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

requireAdminRole(["super_admin"]);

$message = "";
$message_type = "success";

if(isset($_SESSION['success_message'])){
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])){
    $message = $_SESSION['error_message'];
    $message_type = "error";
    unset($_SESSION['error_message']);
}

if(isset($_POST['create_admin'])){

    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conn, strtolower(trim($_POST['email'])));
    $work_phone = mysqli_real_escape_string($conn, trim($_POST['work_phone']));
    $department = mysqli_real_escape_string($conn, trim($_POST['department']));
    $role = mysqli_real_escape_string($conn, trim($_POST['role']));
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    $allowed_roles = [
        "super_admin",
        "user_manager",
        "product_manager",
        "order_manager",
        "payment_manager",
        "report_manager",
        "support_admin"
    ];

    if($full_name == "" || $email == "" || $work_phone == "" || $department == "" || $role == "" || $password == "" || $confirm_password == ""){
        $_SESSION['error_message'] = "Please complete all fields.";
        header("Location: create-admin.php");
        exit();
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $_SESSION['error_message'] = "Please enter a valid email address.";
        header("Location: create-admin.php");
        exit();
    }

    if(!in_array($role, $allowed_roles)){
        $_SESSION['error_message'] = "Invalid admin role selected.";
        header("Location: create-admin.php");
        exit();
    }

    if(!preg_match("/^[0-9+\s-]{9,20}$/", $work_phone)){
        $_SESSION['error_message'] = "Please enter a valid work phone number.";
        header("Location: create-admin.php");
        exit();
    }

    if(!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}$/", $password)){
        $_SESSION['error_message'] = "Password must be at least 8 characters and include uppercase, lowercase and a number.";
        header("Location: create-admin.php");
        exit();
    }

    if($password !== $confirm_password){
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: create-admin.php");
        exit();
    }

    $check_query = "
    SELECT admin_id
    FROM admin_users
    WHERE email='$email'
    LIMIT 1
    ";

    $check_result = mysqli_query($conn, $check_query);

    if($check_result && mysqli_num_rows($check_result) > 0){
        $_SESSION['error_message'] = "Admin email already exists.";
        header("Location: create-admin.php");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $insert_query = "
    INSERT INTO admin_users(
        full_name,
        email,
        password,
        role,
        work_phone,
        department,
        status,
        failed_attempts,
        locked_until,
        created_at
    )
    VALUES(
        '$full_name',
        '$email',
        '$hashed_password',
        '$role',
        '$work_phone',
        '$department',
        'Active',
        0,
        NULL,
        NOW()
    )
    ";

    if(mysqli_query($conn, $insert_query)){
        $_SESSION['success_message'] = "Admin account created successfully.";
        header("Location: manage-admins.php");
        exit();
    }else{
        $_SESSION['error_message'] = "Failed to create admin account: " . mysqli_error($conn);
        header("Location: create-admin.php");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Create Admin | StreetMarket</title>

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

.info-box{
background:#f8fafc;
border-left:5px solid #2563eb;
padding:18px;
border-radius:10px;
margin-top:20px;
}
</style>
</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>StreetMarket Admin</h1>
</div>

<nav>
<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-admins.php">Manage Admins</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-messages.php">Messages</a>
<a href="admin-notifications.php">Notifications</a>
<a href="admin-logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">

<div class="container">

<div class="form-container">

<h2>Create Admin Account</h2>

<p>Only a Super Admin can create admin accounts and assign roles.</p>

<?php if($message != ""){ ?>
<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<form method="POST">

<label>Full Name *</label>
<input type="text" name="full_name" placeholder="Enter admin full name" required>

<label>Email Address *</label>
<input type="email" name="email" placeholder="admin@streetmarket.com" required>

<label>Work Phone *</label>
<input type="tel" name="work_phone" placeholder="+27 71 234 5678" required>

<label>Department *</label>
<input type="text" name="department" placeholder="Example: Support, Orders, Product Moderation" required>

<label>Admin Role *</label>
<select name="role" required>
<option value="">Select Admin Role</option>
<option value="super_admin">Super Admin - Full Access</option>
<option value="user_manager">User Manager - Manage Users</option>
<option value="product_manager">Product Manager - Manage Products</option>
<option value="order_manager">Order Manager - Manage Orders</option>
<option value="payment_manager">Payment Manager - Manage Payments</option>
<option value="report_manager">Report Manager - Manage Reports</option>
<option value="support_admin">Support Admin - Messages and Support</option>
</select>

<label>Password *</label>
<input type="password" name="password" placeholder="Create strong password" required>

<label>Confirm Password *</label>
<input type="password" name="confirm_password" placeholder="Confirm password" required>

<button type="submit" name="create_admin">
Create Admin
</button>

</form>

<div class="info-box">
<p><b>Password rule:</b> minimum 8 characters with uppercase, lowercase and number.</p>
</div>

</div>

</div>

</section>

<footer>
<div class="container footer-container">
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>
</footer>

</body>
</html>