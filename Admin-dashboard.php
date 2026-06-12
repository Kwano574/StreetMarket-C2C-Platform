<?php

session_start();

include("includes/db.php");

/* =========================================
   ADMIN SESSION PROTECTION
========================================= */

if(!isset($_SESSION['admin_id'])){

    header("Location: admin-login.php");
    exit();

}

$admin_name = $_SESSION['admin_name'];

/* =========================================
   TOTAL USERS
========================================= */

$users_query = "

SELECT COUNT(*) AS total_users

FROM users

";

$users_result = mysqli_query(
$conn,
$users_query
);

$users_data = mysqli_fetch_assoc(
$users_result
);

$total_users = $users_data['total_users'];

/* =========================================
   TOTAL SELLERS
========================================= */

$sellers_query = "

SELECT COUNT(DISTINCT user_id) AS total_sellers

FROM product

";

$sellers_result = mysqli_query(
$conn,
$sellers_query
);

$sellers_data = mysqli_fetch_assoc(
$sellers_result
);

$total_sellers = $sellers_data['total_sellers'];

/* =========================================
   TOTAL PRODUCTS
========================================= */

$products_query = "

SELECT COUNT(*) AS total_products

FROM product

";

$products_result = mysqli_query(
$conn,
$products_query
);

$products_data = mysqli_fetch_assoc(
$products_result
);

$total_products = $products_data['total_products'];

/* =========================================
   TOTAL REPORTS
========================================= */

$reports_query = "

SELECT COUNT(*) AS total_reports

FROM reports

WHERE report_status='pending'

";

$reports_result = mysqli_query(
$conn,
$reports_query
);

$reports_data = mysqli_fetch_assoc(
$reports_result
);

$total_reports = $reports_data['total_reports'];

/* =========================================
   RECENT REPORTS
========================================= */

$activity_query = "

SELECT *

FROM reports

ORDER BY report_id DESC

LIMIT 5

";

$activity_result = mysqli_query(
$conn,
$activity_query
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
StreetMarket Admin Dashboard
</title>

<link rel="stylesheet"
href="css/style.css">

<style>

.dashboard-cards{

display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:20px;

}

.dashboard-card{

background:#fff;
padding:30px;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.1);
text-align:center;

}

.dashboard-card h3{

font-size:35px;
margin-top:10px;
color:#0d6efd;

}

.admin-welcome{

background:#fff;
padding:25px;
border-radius:12px;
margin-bottom:30px;
box-shadow:0 2px 10px rgba(0,0,0,0.1);

}

.admin-actions{

display:flex;
flex-wrap:wrap;
gap:15px;
margin-top:20px;

}

.admin-actions a{

padding:12px 20px;
background:#0d6efd;
color:#fff;
text-decoration:none;
border-radius:8px;

}

.admin-actions a:hover{

background:#0b5ed7;

}

.status-pending{

color:orange;
font-weight:bold;

}

.status-reviewed{

color:green;
font-weight:bold;

}

table{

width:100%;
border-collapse:collapse;
background:#fff;
box-shadow:0 2px 10px rgba(0,0,0,0.1);

}

table th,
table td{

padding:15px;
border-bottom:1px solid #ddd;
text-align:left;

}

table th{

background:#f5f5f5;

}

</style>

</head>

<body>

<!-- HEADER -->

<header>

<div class="container header-container">

<div class="logo-section">

<img
src="images/logo.png"
alt="StreetMarket Logo">

<h1>
StreetMarket Admin
</h1>

</div>

<nav>



<a href="manage-users.php">
Users
</a>

<a href="manage-products.php">
Products
</a>
    <a href="admin-seller-verification.php">Seller Verification</a>
    
<a href="admin-orders.php">Orders</a>

<a href="admin-reports.php">
Reports
</a>

<a href="index.php">
Main Website
</a>
<a href="create-admin.php">Create Admin</a>

<a href="manage-admins.php">Manage Admins</a>

<a href="admin-messages.php">Messages</a>

<a href="admin-notifications.php">Notifications</a>
<a href="admin-logout.php">
Logout
</a>

</nav>

</div>

</header>

<!-- HERO -->

<section class="hero">

<div class="container">

<h1>
Administrator Dashboard
</h1>

<p>

Monitor users, products,
reports and marketplace operations.

</p>

</div>

</section>

<!-- ADMIN WELCOME -->

<section class="section-spacing">

<div class="container">

<div class="admin-welcome">

<h2>

Welcome,
<?php echo htmlspecialchars($admin_name); ?>

</h2>

<p>

Manage the StreetMarket platform securely.

</p>

<div class="admin-actions">

<a href="manage-users.php">
Manage Users
</a>

<a href="manage-products.php">
Manage Products
</a>

<a href="admin-reports.php">
View Reports
</a>

<a href="admin-orders.php">
Manage Orders
</a>
    

</div>

</div>

</div>

</section>

<!-- DASHBOARD STATS -->

<section class="section-spacing">

<div class="container">

<div class="dashboard-cards">

<div class="dashboard-card">

<p>
Total Users
</p>

<h3>

<?php echo $total_users; ?>

</h3>

</div>

<div class="dashboard-card">

<p>
Total Sellers
</p>

<h3>

<?php echo $total_sellers; ?>

</h3>

</div>

<div class="dashboard-card">

<p>
Total Products
</p>

<h3>

<?php echo $total_products; ?>

</h3>

</div>

<div class="dashboard-card">

<p>
Pending Reports
</p>

<h3>

<?php echo $total_reports; ?>

</h3>

</div>

</div>

</div>

</section>

<!-- RECENT REPORTS -->

<section class="section-spacing">

<div class="container">

<h2>
Recent Reports
</h2>

<table>

<tr>

<th>
Reported User
</th>

<th>
Reason
</th>

<th>
Details
</th>

<th>
Status
</th>

</tr>

<?php

if(mysqli_num_rows($activity_result) > 0){

while($report =
mysqli_fetch_assoc($activity_result)){

?>

<tr>

<td>

<?php

echo htmlspecialchars(
$report['reported_user']
);

?>

</td>

<td>

<?php

echo htmlspecialchars(
$report['report_reason']
);

?>

</td>

<td>

<?php

echo htmlspecialchars(
$report['report_details']
);

?>

</td>

<td>

<?php

if($report['report_status'] == "pending"){

?>

<span class="status-pending">

Pending Review

</span>

<?php

}

else{

?>

<span class="status-reviewed">

Reviewed

</span>

<?php

}

?>

</td>

</tr>

<?php

}

}

else{

?>

<tr>

<td colspan="4">

No reports available.

</td>

</tr>

<?php

}

?>

</table>

</div>

</section>

<!-- FOOTER -->

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">
About
</a>

<a href="policies.php">
Policies
</a>

<a href="help.php">
Help
</a>

</nav>

<p>

Copyright © 2026 StreetMarket.
All Rights Reserved.

</p>

</div>

</footer>

</body>
</html>

