<?php
// starting php session
session_start();

// connecting page to database for report management
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// connecting notification functions
if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

// allowing super admin and report manager to manage reports
requireAdminRole(["super_admin","report_manager"]);

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

// checking if table exists before using it
function adminTableExists($conn, $table_name){
    $table_name = mysqli_real_escape_string($conn, $table_name);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
    return ($result && mysqli_num_rows($result) > 0);
}

// creating admin notification safely
function createAdminNotificationSafe($conn, $admin_id, $title, $message, $link){
    if(!adminTableExists($conn, "admin_notifications")){
        return true;
    }

    $insert_stmt = $conn->prepare("
    INSERT INTO admin_notifications(admin_id, title, message, link, is_read, created_at)
    VALUES(?,?,?,?, 'No', NOW())
    ");

    $admin_id = intval($admin_id);
    $insert_stmt->bind_param("isss", $admin_id, $title, $message, $link);
    $insert_stmt->execute();

    return true;
}

// notifying super admin and report managers
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
            createAdminNotificationSafe($conn, $admin['admin_id'], $title, $message, $link);
        }
    }

    return true;
}

// getting report data using prepared statement
function getReportData($conn, $report_id){
    $report_stmt = $conn->prepare("
    SELECT
    reports.*,
    reporter.full_name AS reporter_name,
    seller.full_name AS seller_name,
    seller.business_name AS seller_business_name,
    product.product_name
    FROM reports
    INNER JOIN users AS reporter ON reports.user_id = reporter.user_id
    LEFT JOIN users AS seller ON reports.seller_id = seller.user_id
    LEFT JOIN product ON reports.product_id = product.product_id
    WHERE reports.report_id=?
    LIMIT 1
    ");

    $report_stmt->bind_param("i", $report_id);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();

    if($report_result && $report_result->num_rows > 0){
        return $report_result->fetch_assoc();
    }

    return false;
}

// updating report status with admin response
if(isset($_POST['update_report'])){
    $report_id = intval($_POST['report_id']);
    $new_status = trim($_POST['new_status']);
    $admin_response = trim($_POST['admin_response']);

    $allowed_statuses = ["reviewed","resolved"];

    if(!in_array($new_status, $allowed_statuses)){
        $_SESSION['error_message'] = "Invalid report status selected.";
        header("Location: admin-reports.php");
        exit();
    }

    if($admin_response == ""){
        $_SESSION['error_message'] = "Please enter an admin response before updating the report.";
        header("Location: admin-reports.php");
        exit();
    }

    $report = getReportData($conn, $report_id);

    if(!$report){
        $_SESSION['error_message'] = "Report not found.";
        header("Location: admin-reports.php");
        exit();
    }

    // updating report status and admin response
    $update_stmt = $conn->prepare("
    UPDATE reports
    SET report_status=?, admin_response=?
    WHERE report_id=?
    ");

    $update_stmt->bind_param("ssi", $new_status, $admin_response, $report_id);

    if($update_stmt->execute()){
        $status_text = ($new_status == "reviewed") ? "reviewed" : "resolved";

        if(function_exists("createNotification")){
            createNotification(
                $conn,
                $report['user_id'],
                "Report " . ucfirst($status_text),
                "Your report for Order #" . $report['order_id'] . " has been " . $status_text . " by StreetMarket.",
                "notifications.php"
            );

            if(!empty($report['seller_id'])){
                createNotification(
                    $conn,
                    $report['seller_id'],
                    "Report " . ucfirst($status_text),
                    "A report related to Order #" . $report['order_id'] . " has been " . $status_text . " by StreetMarket.",
                    "notifications.php"
                );
            }
        }

        notifyReportAdmins(
            $conn,
            "Report " . ucfirst($status_text),
            "Report #" . $report_id . " has been marked as " . $status_text . ".",
            "admin-reports.php"
        );

        $_SESSION['success_message'] = "Report marked as " . $status_text . " successfully.";
    }else{
        $_SESSION['error_message'] = "Failed to update report.";
    }

    header("Location: admin-reports.php");
    exit();
}

// getting search and filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : "";

// getting report statistics
$total_reports_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM reports");
$total_reports_data = mysqli_fetch_assoc($total_reports_result);
$total_reports = $total_reports_data ? intval($total_reports_data['total']) : 0;

$pending_reports_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM reports WHERE report_status='pending'");
$pending_reports_data = mysqli_fetch_assoc($pending_reports_result);
$pending_reports = $pending_reports_data ? intval($pending_reports_data['total']) : 0;

$reviewed_reports_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM reports WHERE report_status='reviewed'");
$reviewed_reports_data = mysqli_fetch_assoc($reviewed_reports_result);
$reviewed_reports = $reviewed_reports_data ? intval($reviewed_reports_data['total']) : 0;

$resolved_reports_result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM reports WHERE report_status='resolved'");
$resolved_reports_data = mysqli_fetch_assoc($resolved_reports_result);
$resolved_reports = $resolved_reports_data ? intval($resolved_reports_data['total']) : 0;

// building report query with search and filter
$reports_query = "
SELECT
reports.*,
reporter.full_name AS reporter_name,
seller.full_name AS seller_name,
seller.business_name AS seller_business_name,
product.product_name
FROM reports
INNER JOIN users AS reporter ON reports.user_id = reporter.user_id
LEFT JOIN users AS seller ON reports.seller_id = seller.user_id
LEFT JOIN product ON reports.product_id = product.product_id
WHERE 1
";

$params = [];
$types = "";

if($search != ""){
    $search_value = "%" . $search . "%";
    $reports_query .= "
    AND (
        reporter.full_name LIKE ?
        OR seller.full_name LIKE ?
        OR seller.business_name LIKE ?
        OR product.product_name LIKE ?
        OR reports.report_reason LIKE ?
        OR reports.report_details LIKE ?
        OR reports.reported_user LIKE ?
    )
    ";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "sssssss";
}

if($status_filter != ""){
    $reports_query .= " AND reports.report_status=?";
    $params[] = $status_filter;
    $types .= "s";
}

$reports_query .= "
ORDER BY
CASE
WHEN reports.report_status='pending' THEN 1
WHEN reports.report_status='reviewed' THEN 2
WHEN reports.report_status='resolved' THEN 3
ELSE 4
END,
reports.report_id DESC
";

$reports_stmt = $conn->prepare($reports_query);

if(count($params) > 0){
    $reports_stmt->bind_param($types, ...$params);
}

$reports_stmt->execute();
$reports_result = $reports_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Reports | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>
.success-message{background:#dcfce7;color:#166534;border-left:5px solid #16a34a;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.error-message{background:#fee2e2;color:#991b1b;border-left:5px solid #dc2626;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:25px}
.stat-card{background:white;padding:20px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);text-align:center}
.stat-card h3{font-size:30px;margin-bottom:8px;color:#111}
.filter-box{background:white;padding:20px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);margin-bottom:25px}
.filter-form{display:grid;grid-template-columns:2fr 1fr auto auto;gap:12px;align-items:end}
.filter-form input,.filter-form select{padding:12px;border:1px solid #ddd;border-radius:8px;width:100%}
.filter-form button,.clear-btn{padding:12px 16px;border:none;border-radius:8px;background:#111;color:white;text-decoration:none;font-weight:bold;cursor:pointer;text-align:center}
.clear-btn{background:#555}
.table-wrapper{overflow-x:auto;background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08)}
table{width:100%;border-collapse:collapse;background:#fff;min-width:1250px}
th,td{padding:14px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}
th{background:#111;color:white;font-weight:bold}
.update-btn{padding:9px 14px;background:green;color:#fff;border:none;border-radius:8px;display:inline-block;margin-bottom:6px;font-weight:bold;cursor:pointer}
.resolve-btn{background:#2563eb}
.pending{color:orange;font-weight:bold}
.reviewed{color:green;font-weight:bold}
.resolved{color:#2563eb;font-weight:bold}
.small-text{font-size:13px;color:#64748b}
.details-box{max-width:280px;white-space:normal;line-height:1.5}
.response-form textarea{width:250px;min-height:80px;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px}
.response-form select{width:250px;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px}
@media(max-width:900px){.filter-form{grid-template-columns:1fr}table{min-width:1100px}}
</style>
</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="Logo">
<h1>Admin Reports</h1>
</div>

<!-- Admin navigation links -->
<nav>
<a href="Admin-dashboard.php">Dashboard</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-payments.php">Payments</a>
<a href="admin-notifications.php">Notifications</a>
<a href="admin-logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">
<div class="container">

<h2>Manage User Reports</h2>
<p>Review marketplace reports related to sellers, products, completed orders, scams and suspicious activity.</p>

<?php if($message != ""){ ?>
<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<!-- Report statistics cards -->
<div class="stats-grid">
<div class="stat-card">
<h3><?php echo $total_reports; ?></h3>
<p>Total Reports</p>
</div>

<div class="stat-card">
<h3><?php echo $pending_reports; ?></h3>
<p>Pending Reports</p>
</div>

<div class="stat-card">
<h3><?php echo $reviewed_reports; ?></h3>
<p>Reviewed Reports</p>
</div>

<div class="stat-card">
<h3><?php echo $resolved_reports; ?></h3>
<p>Resolved Reports</p>
</div>
</div>

<!-- Search and filter form -->
<div class="filter-box">
<form method="GET" class="filter-form">
<div>
<label>Search Report</label>
<input type="text" name="search" placeholder="Search by reporter, seller, product, reason or details" value="<?php echo htmlspecialchars($search); ?>">
</div>

<div>
<label>Report Status</label>
<select name="status_filter">
<option value="">All Statuses</option>
<option value="pending" <?php if($status_filter == "pending"){ echo "selected"; } ?>>Pending</option>
<option value="reviewed" <?php if($status_filter == "reviewed"){ echo "selected"; } ?>>Reviewed</option>
<option value="resolved" <?php if($status_filter == "resolved"){ echo "selected"; } ?>>Resolved</option>
</select>
</div>

<button type="submit">Filter</button>
<a href="admin-reports.php" class="clear-btn">Clear</a>
</form>
</div>

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
<th>Update</th>
</tr>

<?php if($reports_result && $reports_result->num_rows > 0){ ?>
<?php while($report = $reports_result->fetch_assoc()){ ?>
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

<td><?php echo htmlspecialchars($report['reporter_name']); ?></td>

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

<td><?php echo !empty($report['product_name']) ? htmlspecialchars($report['product_name']) : "N/A"; ?></td>
<td><?php echo htmlspecialchars($report['report_reason']); ?></td>

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
<?php if($report['report_status'] != "resolved"){ ?>
<form method="POST" class="response-form">
<input type="hidden" name="report_id" value="<?php echo intval($report['report_id']); ?>">

<select name="new_status" required>
<option value="">Select Action</option>
<option value="reviewed">Mark Reviewed</option>
<option value="resolved">Resolve Report</option>
</select>

<textarea name="admin_response" placeholder="Write admin response..." required><?php echo !empty($report['admin_response']) ? htmlspecialchars($report['admin_response']) : ""; ?></textarea>

<button type="submit" name="update_report" class="update-btn" onclick="return confirm('Update this report status?');">
Update Report
</button>
</form>
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
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>
</footer>

</body>
</html>