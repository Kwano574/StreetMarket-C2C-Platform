<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

requireAdminRole(["report_manager"]);

$message = "";
$message_type = "success";

/* SESSION MESSAGES */

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

/* SAFE TABLE CHECK */

function adminTableExists($conn, $table_name){

    $table_name = mysqli_real_escape_string($conn, $table_name);

    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");

    return ($result && mysqli_num_rows($result) > 0);
}

/* SAFE ADMIN NOTIFICATION */

function createAdminNotificationSafe($conn, $admin_id, $title, $message, $link){

    if(!adminTableExists($conn, "admin_notifications")){
        return true;
    }

    $admin_id = intval($admin_id);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    mysqli_query($conn, "
    INSERT INTO admin_notifications(
        admin_id,
        title,
        message,
        link,
        is_read,
        created_at
    )
    VALUES(
        '$admin_id',
        '$title',
        '$message',
        '$link',
        'No',
        NOW()
    )
    ");

    return true;
}

/* NOTIFY SUPER ADMIN AND REPORT MANAGER */

function notifyReportAdmins($conn, $title, $message, $link){

    if(!adminTableExists($conn, "admin_users")){
        return true;
    }

    $admin_query = "
    SELECT admin_id
    FROM admin_users
    WHERE role IN ('super_admin', 'report_manager')
    ";

    $admin_result = mysqli_query($conn, $admin_query);

    if($admin_result && mysqli_num_rows($admin_result) > 0){

        while($admin = mysqli_fetch_assoc($admin_result)){

            createAdminNotificationSafe(
                $conn,
                $admin['admin_id'],
                $title,
                $message,
                $link
            );

        }

    }

    return true;
}

/* GET REPORT DATA */

function getReportData($conn, $report_id){

    $report_id = intval($report_id);

    $query = "
    SELECT
    reports.*,
    reporter.full_name AS reporter_name,
    seller.full_name AS seller_name,
    seller.business_name AS seller_business_name,
    product.product_name
    FROM reports

    INNER JOIN users AS reporter
    ON reports.user_id = reporter.user_id

    LEFT JOIN users AS seller
    ON reports.seller_id = seller.user_id

    LEFT JOIN product
    ON reports.product_id = product.product_id

    WHERE reports.report_id='$report_id'
    LIMIT 1
    ";

    $result = mysqli_query($conn, $query);

    if($result && mysqli_num_rows($result) > 0){
        return mysqli_fetch_assoc($result);
    }

    return false;
}

/* MARK REPORT AS REVIEWED */

if(isset($_GET['review'])){

    $report_id = intval($_GET['review']);
    $report = getReportData($conn, $report_id);

    if($report){

        $update = "
        UPDATE reports
        SET
        report_status='reviewed',
        admin_response='Report reviewed by StreetMarket admin.'
        WHERE report_id='$report_id'
        ";

        if(mysqli_query($conn, $update)){

            if(function_exists("createNotification")){

                createNotification(
                    $conn,
                    $report['user_id'],
                    "Report Reviewed",
                    "Your report for Order #".$report['order_id']." has been reviewed by StreetMarket.",
                    "notifications.php"
                );

                if(!empty($report['seller_id'])){

                    createNotification(
                        $conn,
                        $report['seller_id'],
                        "Report Reviewed",
                        "A report related to Order #".$report['order_id']." has been reviewed by StreetMarket.",
                        "notifications.php"
                    );

                }

            }

            notifyReportAdmins(
                $conn,
                "Report Reviewed",
                "Report #".$report_id." has been marked as reviewed.",
                "admin-reports.php"
            );

            $_SESSION['success_message'] = "Report marked as reviewed.";
            header("Location: admin-reports.php");
            exit();

        }else{

            $_SESSION['error_message'] = "Failed to update report: " . mysqli_error($conn);
            header("Location: admin-reports.php");
            exit();

        }

    }else{

        $_SESSION['error_message'] = "Report not found.";
        header("Location: admin-reports.php");
        exit();

    }

}

/* RESOLVE / DISMISS REPORT */

if(isset($_GET['dismiss'])){

    $report_id = intval($_GET['dismiss']);
    $report = getReportData($conn, $report_id);

    if($report){

        /*
        Your database enum is:
        pending, reviewed, resolved

        So we use resolved instead of dismissed to avoid 500 errors.
        */

        $update = "
        UPDATE reports
        SET
        report_status='resolved',
        admin_response='Report resolved/dismissed by StreetMarket admin.'
        WHERE report_id='$report_id'
        ";

        if(mysqli_query($conn, $update)){

            if(function_exists("createNotification")){

                createNotification(
                    $conn,
                    $report['user_id'],
                    "Report Resolved",
                    "Your report for Order #".$report['order_id']." has been resolved by StreetMarket.",
                    "notifications.php"
                );

                if(!empty($report['seller_id'])){

                    createNotification(
                        $conn,
                        $report['seller_id'],
                        "Report Resolved",
                        "A report related to Order #".$report['order_id']." has been resolved by StreetMarket.",
                        "notifications.php"
                    );

                }

            }

            notifyReportAdmins(
                $conn,
                "Report Resolved",
                "Report #".$report_id." has been resolved/dismissed.",
                "admin-reports.php"
            );

            $_SESSION['success_message'] = "Report resolved successfully.";
            header("Location: admin-reports.php");
            exit();

        }else{

            $_SESSION['error_message'] = "Failed to resolve report: " . mysqli_error($conn);
            header("Location: admin-reports.php");
            exit();

        }

    }else{

        $_SESSION['error_message'] = "Report not found.";
        header("Location: admin-reports.php");
        exit();

    }

}

/* GET REPORTS */

$reports_query = "
SELECT
reports.*,
reporter.full_name AS reporter_name,
seller.full_name AS seller_name,
seller.business_name AS seller_business_name,
product.product_name
FROM reports

INNER JOIN users AS reporter
ON reports.user_id = reporter.user_id

LEFT JOIN users AS seller
ON reports.seller_id = seller.user_id

LEFT JOIN product
ON reports.product_id = product.product_id

ORDER BY
CASE
WHEN reports.report_status='pending' THEN 1
WHEN reports.report_status='reviewed' THEN 2
WHEN reports.report_status='resolved' THEN 3
ELSE 4
END,
reports.report_id DESC
";

$reports_result = mysqli_query($conn, $reports_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Reports | StreetMarket</title>

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
background:white;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

table{
width:100%;
border-collapse:collapse;
background:#fff;
min-width:1150px;
}

th,
td{
padding:14px;
border-bottom:1px solid #ddd;
text-align:left;
vertical-align:top;
}

th{
background:#111;
color:white;
font-weight:bold;
}

.review-btn{
padding:9px 14px;
background:green;
color:#fff;
text-decoration:none;
border-radius:8px;
display:inline-block;
margin-bottom:6px;
font-weight:bold;
}

.dismiss-btn{
padding:9px 14px;
background:#c62828;
color:#fff;
text-decoration:none;
border-radius:8px;
display:inline-block;
font-weight:bold;
}

.pending{
color:orange;
font-weight:bold;
}

.reviewed{
color:green;
font-weight:bold;
}

.resolved{
color:#2563eb;
font-weight:bold;
}

.small-text{
font-size:13px;
color:#64748b;
}

.details-box{
max-width:280px;
white-space:normal;
line-height:1.5;
}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img src="images/logo.png" alt="Logo">

<h1>Admin Reports</h1>

</div>

<nav>

<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-payments.php">Payments</a>
<a href="admin-logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<h2>Manage User Reports</h2>

<p>
Review marketplace reports related to sellers, products, completed orders, scams and suspicious activity.
</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="table-wrapper">

<table>

<tr>
<th>ID</th>
<th>Order</th>
<th>Reporter</th>
<th>Reported Seller</th>
<th>Product</th>
<th>Reason</th>
<th>Details</th>
<th>Admin Response</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php if($reports_result && mysqli_num_rows($reports_result) > 0){ ?>

<?php while($report = mysqli_fetch_assoc($reports_result)){ ?>

<tr>

<td>
#<?php echo intval($report['report_id']); ?>
<br>
<span class="small-text">
<?php echo date("d M Y H:i", strtotime($report['created_at'])); ?>
</span>
</td>

<td>
<?php if(!empty($report['order_id'])){ ?>
#<?php echo intval($report['order_id']); ?>
<?php }else{ ?>
N/A
<?php } ?>
</td>

<td>
<?php echo htmlspecialchars($report['reporter_name']); ?>
</td>

<td>
<?php
if(!empty($report['seller_business_name'])){
    echo htmlspecialchars($report['seller_business_name']);
}elseif(!empty($report['seller_name'])){
    echo htmlspecialchars($report['seller_name']);
}else{
    echo htmlspecialchars($report['reported_user']);
}
?>
</td>

<td>
<?php echo !empty($report['product_name']) ? htmlspecialchars($report['product_name']) : "N/A"; ?>
</td>

<td>
<?php echo htmlspecialchars($report['report_reason']); ?>
</td>

<td>
<div class="details-box">
<?php echo nl2br(htmlspecialchars($report['report_details'])); ?>
</div>
</td>

<td>
<div class="details-box">
<?php echo !empty($report['admin_response']) ? nl2br(htmlspecialchars($report['admin_response'])) : "No response yet."; ?>
</div>
</td>

<td>

<?php if($report['report_status'] == "pending"){ ?>

<span class="pending">Pending</span>

<?php }elseif($report['report_status'] == "reviewed"){ ?>

<span class="reviewed">Reviewed</span>

<?php }else{ ?>

<span class="resolved">Resolved</span>

<?php } ?>

</td>

<td>

<?php if($report['report_status'] == "pending"){ ?>

<a
href="admin-reports.php?review=<?php echo intval($report['report_id']); ?>"
class="review-btn"
onclick="return confirm('Mark this report as reviewed?');">
Mark Reviewed
</a>

<br>

<a
href="admin-reports.php?dismiss=<?php echo intval($report['report_id']); ?>"
class="dismiss-btn"
onclick="return confirm('Resolve/dismiss this report?');">
Resolve
</a>

<?php }elseif($report['report_status'] == "reviewed"){ ?>

<a
href="admin-reports.php?dismiss=<?php echo intval($report['report_id']); ?>"
class="dismiss-btn"
onclick="return confirm('Resolve this reviewed report?');">
Resolve
</a>

<?php }else{ ?>

No Action

<?php } ?>

</td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>
<td colspan="10">No reports found.</td>
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

</body>

</html>