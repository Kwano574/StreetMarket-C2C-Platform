<?php
// starting php session
session_start();

// connecting page to database for admin notifications
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// protecting admin session
if(!isset($_SESSION['admin_id'])){
    header("Location: admin-login.php");
    exit();
}

// storing logged-in admin id
$admin_id = intval($_SESSION['admin_id']);
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

// marking one notification as read
if(isset($_GET['read'])){
    $notification_id = intval($_GET['read']);

    $read_stmt = $conn->prepare("
    UPDATE admin_notifications
    SET is_read='Yes'
    WHERE admin_notification_id=?
    AND admin_id=?
    ");

    $read_stmt->bind_param("ii", $notification_id, $admin_id);
    $read_stmt->execute();

    // safe redirect after clicking view
    if(isset($_GET['go']) && $_GET['go'] != ""){
        $go = trim($_GET['go']);

        if(strpos($go, "http") === false && strpos($go, "//") === false){
            header("Location: " . $go);
            exit();
        }else{
            $_SESSION['error_message'] = "Unsafe redirect blocked.";
            header("Location: admin-notifications.php");
            exit();
        }
    }

    $_SESSION['success_message'] = "Notification marked as read.";
    header("Location: admin-notifications.php");
    exit();
}

// marking all notifications as read
if(isset($_GET['mark_all'])){
    $mark_all_stmt = $conn->prepare("
    UPDATE admin_notifications
    SET is_read='Yes'
    WHERE admin_id=?
    ");

    $mark_all_stmt->bind_param("i", $admin_id);

    if($mark_all_stmt->execute()){
        $_SESSION['success_message'] = "All notifications marked as read.";
    }else{
        $_SESSION['error_message'] = "Failed to mark notifications as read.";
    }

    header("Location: admin-notifications.php");
    exit();
}

// deleting notification
if(isset($_GET['delete'])){
    $notification_id = intval($_GET['delete']);

    $delete_stmt = $conn->prepare("
    DELETE FROM admin_notifications
    WHERE admin_notification_id=?
    AND admin_id=?
    ");

    $delete_stmt->bind_param("ii", $notification_id, $admin_id);

    if($delete_stmt->execute()){
        $_SESSION['success_message'] = "Notification deleted.";
    }else{
        $_SESSION['error_message'] = "Failed to delete notification.";
    }

    header("Location: admin-notifications.php");
    exit();
}

// getting search and filter values
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$read_filter = isset($_GET['read_filter']) ? trim($_GET['read_filter']) : "";

// getting unread notification count
$unread_stmt = $conn->prepare("
SELECT COUNT(*) AS unread_total
FROM admin_notifications
WHERE admin_id=?
AND is_read='No'
");

$unread_stmt->bind_param("i", $admin_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_data = $unread_result->fetch_assoc();
$unread_total = $unread_data ? intval($unread_data['unread_total']) : 0;

// getting total notification count
$total_stmt = $conn->prepare("
SELECT COUNT(*) AS total_notifications
FROM admin_notifications
WHERE admin_id=?
");

$total_stmt->bind_param("i", $admin_id);
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_data = $total_result->fetch_assoc();
$total_notifications = $total_data ? intval($total_data['total_notifications']) : 0;

// building notifications query
$notifications_query = "
SELECT *
FROM admin_notifications
WHERE admin_id=?
";

$params = [$admin_id];
$types = "i";

if($search != ""){
    $search_value = "%" . $search . "%";
    $notifications_query .= " AND (title LIKE ? OR message LIKE ?)";
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "ss";
}

if($read_filter != ""){
    $notifications_query .= " AND is_read=?";
    $params[] = $read_filter;
    $types .= "s";
}

$notifications_query .= " ORDER BY created_at DESC";

// preparing notifications query
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param($types, ...$params);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Notifications | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>
.notifications-container{max-width:900px;margin:auto}
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
.notification-card{background:#fff;padding:20px;border-radius:14px;margin-bottom:18px;box-shadow:0 2px 10px rgba(0,0,0,0.08);border-left:6px solid #ccc}
.notification-unread{border-left-color:#2563eb;background:#f8fafc}
.notification-header{display:flex;justify-content:space-between;gap:15px;align-items:flex-start}
.notification-header h3{margin:0 0 8px 0;font-size:18px}
.notification-card p{line-height:1.6;margin-bottom:10px}
.notification-time{font-size:13px;color:#777;margin-top:10px}
.notification-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.notification-actions a{padding:9px 13px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:bold}
.view-btn{background:#111;color:white}
.read-btn{background:#16a34a;color:white}
.delete-btn{background:#c62828;color:white}
.top-actions{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:25px}
.mark-all-btn{background:#111;color:#fff;padding:11px 16px;border-radius:8px;text-decoration:none;font-weight:bold}
.unread-badge,.new-badge{display:inline-block;background:#2563eb;color:#fff;padding:6px 12px;border-radius:30px;font-size:14px;font-weight:bold}
.read-badge{display:inline-block;background:#16a34a;color:#fff;padding:6px 12px;border-radius:30px;font-size:13px;font-weight:bold}
@media(max-width:800px){.filter-form{grid-template-columns:1fr}}
</style>
</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>Admin Notifications</h1>
</div>

<!-- Admin navigation links -->
<nav>
<a href="Admin-dashboard.php">Dashboard</a>
<a href="admin-messages.php">Messages</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-orders.php">Orders</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">
<div class="container notifications-container">

<div class="page-intro">
<h2>Admin Notifications</h2>
<p>View admin messages, reports, order updates, product moderation and user activity.</p>
</div>

<?php if($message != ""){ ?>
<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<!-- Notification statistics -->
<div class="stats-grid">
<div class="stat-card">
<h3><?php echo $total_notifications; ?></h3>
<p>Total Notifications</p>
</div>

<div class="stat-card">
<h3><?php echo $unread_total; ?></h3>
<p>Unread Notifications</p>
</div>
</div>

<div class="top-actions">
<h3>
Unread Notifications:
<span class="unread-badge"><?php echo $unread_total; ?></span>
</h3>

<a href="admin-notifications.php?mark_all=1" class="mark-all-btn">
Mark All As Read
</a>
</div>

<!-- Search and filter form -->
<div class="filter-box">
<form method="GET" class="filter-form">
<div>
<label>Search Notification</label>
<input type="text" name="search" placeholder="Search by title or message" value="<?php echo htmlspecialchars($search); ?>">
</div>

<div>
<label>Read Status</label>
<select name="read_filter">
<option value="">All</option>
<option value="No" <?php if($read_filter == "No"){ echo "selected"; } ?>>Unread</option>
<option value="Yes" <?php if($read_filter == "Yes"){ echo "selected"; } ?>>Read</option>
</select>
</div>

<button type="submit">Filter</button>
<a href="admin-notifications.php" class="clear-btn">Clear</a>
</form>
</div>

<?php if($notifications_result && $notifications_result->num_rows > 0){ ?>
<?php while($notification = $notifications_result->fetch_assoc()){ ?>
<?php
$card_class = "";

if($notification['is_read'] == "No"){
    $card_class = "notification-unread";
}
?>

<div class="notification-card <?php echo $card_class; ?>">

<div class="notification-header">
<div>
<h3><?php echo htmlspecialchars($notification['title']); ?></h3>
<p><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
</div>

<?php if($notification['is_read'] == "No"){ ?>
<span class="new-badge">New</span>
<?php }else{ ?>
<span class="read-badge">Read</span>
<?php } ?>
</div>

<div class="notification-time">
<?php echo date("d M Y H:i", strtotime($notification['created_at'])); ?>
</div>

<div class="notification-actions">

<?php if(!empty($notification['link'])){ ?>
<a href="admin-notifications.php?read=<?php echo intval($notification['admin_notification_id']); ?>&go=<?php echo urlencode($notification['link']); ?>" class="view-btn">
View
</a>
<?php } ?>

<?php if($notification['is_read'] == "No"){ ?>
<a href="admin-notifications.php?read=<?php echo intval($notification['admin_notification_id']); ?>" class="read-btn">
Mark Read
</a>
<?php } ?>

<a href="admin-notifications.php?delete=<?php echo intval($notification['admin_notification_id']); ?>" class="delete-btn" onclick="return confirm('Delete this notification?');">
Delete
</a>

</div>
</div>

<?php } ?>
<?php }else{ ?>

<div class="info-box">
<h3>No Notifications</h3>
<p>No admin notifications match your search or filter.</p>
</div>

<?php } ?>

</div>
</section>

<footer>
<div class="container footer-container">
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>
</footer>

</body>
</html>