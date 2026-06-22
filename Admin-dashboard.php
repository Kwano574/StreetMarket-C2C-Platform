<?php
// starting php session
session_start();

// connecting page to database for data retrieval and validation
include("includes/db.php");

// admin session protection by checking if admin is logged in
if(!isset($_SESSION['admin_id'])){
    header("Location: admin-login.php");
    exit();
}

// storing admin session details
$admin_id = intval($_SESSION['admin_id']);
$admin_name = $_SESSION['admin_name'];
$admin_role = $_SESSION['admin_role'];

// setting admin inactivity timeout
$timeout_duration = 900;

if(isset($_SESSION['admin_last_activity'])){
    if((time() - $_SESSION['admin_last_activity']) > $timeout_duration){
        session_unset();
        session_destroy();
        header("Location: admin-login.php?timeout=1");
        exit();
    }
}

$_SESSION['admin_last_activity'] = time();

// function to check admin role permission
function adminCanAccess($allowed_roles, $admin_role){
    return in_array($admin_role, $allowed_roles);
}

// getting total users from database
$users_query = "
SELECT COUNT(*) AS total_users
FROM users
";

$users_result = mysqli_query($conn, $users_query);
$users_data = mysqli_fetch_assoc($users_result);
$total_users = $users_data ? intval($users_data['total_users']) : 0;

// getting total verified sellers from database
$sellers_query = "
SELECT COUNT(*) AS total_sellers
FROM users
WHERE seller_verification_status='Verified'
OR seller_verification_status='Approved'
";

$sellers_result = mysqli_query($conn, $sellers_query);
$sellers_data = mysqli_fetch_assoc($sellers_result);
$total_sellers = $sellers_data ? intval($sellers_data['total_sellers']) : 0;

// getting total products stored in the system
$products_query = "
SELECT COUNT(*) AS total_products
FROM product
";

$products_result = mysqli_query($conn, $products_query);
$products_data = mysqli_fetch_assoc($products_result);
$total_products = $products_data ? intval($products_data['total_products']) : 0;

// getting total pending reports from users
$reports_query = "
SELECT COUNT(*) AS total_reports
FROM reports
WHERE report_status='pending'
";

$reports_result = mysqli_query($conn, $reports_query);
$reports_data = mysqli_fetch_assoc($reports_result);
$total_reports = $reports_data ? intval($reports_data['total_reports']) : 0;

// getting pending product approvals
$pending_products_query = "
SELECT COUNT(*) AS pending_products
FROM product
WHERE moderation_status='pending'
";

$pending_products_result = mysqli_query($conn, $pending_products_query);
$pending_products_data = mysqli_fetch_assoc($pending_products_result);
$pending_products = $pending_products_data ? intval($pending_products_data['pending_products']) : 0;

// getting pending seller verification requests
$pending_sellers_query = "
SELECT COUNT(*) AS pending_sellers
FROM users
WHERE seller_verification_status='Pending'
";

$pending_sellers_result = mysqli_query($conn, $pending_sellers_query);
$pending_sellers_data = mysqli_fetch_assoc($pending_sellers_result);
$pending_sellers = $pending_sellers_data ? intval($pending_sellers_data['pending_sellers']) : 0;

// getting recent submitted reports from database
$activity_query = "
SELECT *
FROM reports
ORDER BY report_id DESC
LIMIT 5
";

$activity_result = mysqli_query($conn, $activity_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>StreetMarket Admin Dashboard</title>

<link rel="stylesheet" href="css/style.css">

<style>
.dashboard-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px}
.dashboard-card{background:#fff;padding:30px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1);text-align:center}
.dashboard-card h3{font-size:35px;margin-top:10px;color:#0d6efd}
.admin-welcome{background:#fff;padding:25px;border-radius:12px;margin-bottom:30px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
.admin-actions{display:flex;flex-wrap:wrap;gap:15px;margin-top:20px}
.admin-actions a{padding:12px 20px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold}
.admin-actions a:hover{background:#0b5ed7}
.role-badge{display:inline-block;background:#111;color:#fff;padding:8px 14px;border-radius:30px;font-size:14px;font-weight:bold;margin-top:8px}
.status-pending{color:orange;font-weight:bold}
.status-reviewed{color:green;font-weight:bold}
.status-dismissed{color:#c62828;font-weight:bold}
table{width:100%;border-collapse:collapse;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
table th,table td{padding:15px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}
table th{background:#f5f5f5}
@media(max-width:900px){nav{display:flex;flex-wrap:wrap;gap:8px}table{display:block;overflow-x:auto;white-space:nowrap}}
</style>
</head>

<body>

<!-- HEADER -->
<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>StreetMarket Admin</h1>
</div>

<!-- Navigation bar links controlled by admin role -->
<nav>


<?php if(adminCanAccess(["super_admin","user_manager"], $admin_role)){ ?>
<a href="manage-users.php">Users</a>
<a href="admin-seller-verification.php">Seller Verification</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin","product_manager"], $admin_role)){ ?>
<a href="manage-products.php">Products</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin","order_manager"], $admin_role)){ ?>
<a href="admin-orders.php">Orders</a>
<?php } ?>
    
<?php if(adminCanAccess(["super_admin","order_manager"], $admin_role)){ ?>
<a href="admin-payments.php"> Payments</a>
<?php } ?>    

<?php if(adminCanAccess(["super_admin","report_manager"], $admin_role)){ ?>
<a href="admin-reports.php">Reports</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin"], $admin_role)){ ?>
<a href="create-admin.php">Create Admin</a>
<a href="manage-admins.php">Manage Admins</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin","support_admin"], $admin_role)){ ?>
<a href="admin-messages.php">&#x1F4AC; Chat</a>
<?php } ?>

<a href="admin-notifications.php">&#x1F514; Notifications</a>

<a href="admin-logout.php">Logout</a>
</nav>
</div>
</header>

<!-- HERO section -->
<section class="hero">
<div class="container">
<h1>Administrator Dashboard</h1>
<p>Monitor users, sellers, products, reports and marketplace operations.</p>
</div>
</section>

<!-- ADMIN WELCOME section -->
<section class="section-spacing">
<div class="container">
<div class="admin-welcome">
<h2>Welcome, <?php echo htmlspecialchars($admin_name); ?></h2>
<p>Manage the StreetMarket platform securely based on your admin role.</p>
<span class="role-badge">Role: <?php echo ucwords(str_replace("_", " ", htmlspecialchars($admin_role))); ?></span>

<div class="admin-actions">
<?php if(adminCanAccess(["super_admin","user_manager"], $admin_role)){ ?>
<a href="manage-users.php">Manage Users</a>
<a href="admin-seller-verification.php">Seller Verification</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin","product_manager"], $admin_role)){ ?>
<a href="manage-products.php">Manage Products</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin","order_manager"], $admin_role)){ ?>
<a href="admin-orders.php">Manage Orders</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin","report_manager"], $admin_role)){ ?>
<a href="admin-reports.php">View Reports</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin"], $admin_role)){ ?>
<a href="create-admin.php">Create Admin</a>
<a href="manage-admins.php">Manage Admins</a>
<?php } ?>

<?php if(adminCanAccess(["super_admin","support_admin"], $admin_role)){ ?>
<a href="admin-messages.php">Chat</a>
<?php } ?>
</div>
</div>
</div>
</section>

<!-- DASHBOARD statistics section -->
<section class="section-spacing">
<div class="container">
<div class="dashboard-cards">

<div class="dashboard-card">
<p>Total Users</p>
<h3><?php echo $total_users; ?></h3>
</div>

<div class="dashboard-card">
<p>Verified Sellers</p>
<h3><?php echo $total_sellers; ?></h3>
</div>

<div class="dashboard-card">
<p>Total Products</p>
<h3><?php echo $total_products; ?></h3>
</div>

<div class="dashboard-card">
<p>Pending Reports</p>
<h3><?php echo $total_reports; ?></h3>
</div>

<div class="dashboard-card">
<p>Pending Products</p>
<h3><?php echo $pending_products; ?></h3>
</div>

<div class="dashboard-card">
<p>Pending Seller Requests</p>
<h3><?php echo $pending_sellers; ?></h3>
</div>

</div>
</div>
</section>

<!-- Recent reports section -->
<section class="section-spacing">
<div class="container">
<h2>Recent Reports</h2>

<table>
<tr>
<th>Reported User</th>
<th>Reason</th>
<th>Details</th>
<th>Status</th>
</tr>

<?php if($activity_result && mysqli_num_rows($activity_result) > 0){ ?>
<?php while($report = mysqli_fetch_assoc($activity_result)){ ?>
<tr>
<td><?php echo htmlspecialchars($report['reported_user']); ?></td>
<td><?php echo htmlspecialchars($report['report_reason']); ?></td>
<td><?php echo nl2br(htmlspecialchars($report['report_details'])); ?></td>
<td>
<?php if($report['report_status'] == "pending"){ ?>
<span class="status-pending">Pending Review</span>
<?php }elseif($report['report_status'] == "dismissed"){ ?>
<span class="status-dismissed">Dismissed</span>
<?php }else{ ?>
<span class="status-reviewed">Reviewed</span>
<?php } ?>
</td>
</tr>
<?php } ?>
<?php }else{ ?>
<tr>
<td colspan="4">No reports available.</td>
</tr>
<?php } ?>
</table>
</div>
</section>

<!-- FOOTER -->
<footer>
<div class="container footer-container">
<nav>
<a href="Admin-dashboard.php">Dashboard</a>
<a href="admin-notifications.php">Notifications</a>
<a href="admin-logout.php">Logout</a>
</nav>
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>
</footer>

</body>
</html>