<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: admin-login.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$message = "";

if(isset($_GET['read'])){

    $notification_id = intval($_GET['read']);

    mysqli_query($conn, "
    UPDATE admin_notifications
    SET is_read='Yes'
    WHERE admin_notification_id='$notification_id'
    AND admin_id='$admin_id'
    ");

    if(isset($_GET['go']) && $_GET['go'] != ""){
        header("Location: ".$_GET['go']);
        exit();
    }
}

if(isset($_GET['mark_all'])){

    mysqli_query($conn, "
    UPDATE admin_notifications
    SET is_read='Yes'
    WHERE admin_id='$admin_id'
    ");

    $message = "All notifications marked as read.";
}

if(isset($_GET['delete'])){

    $notification_id = intval($_GET['delete']);

    if(mysqli_query($conn, "
    DELETE FROM admin_notifications
    WHERE admin_notification_id='$notification_id'
    AND admin_id='$admin_id'
    ")){
        $message = "Notification deleted.";
    }
}

$notifications_query = "
SELECT *
FROM admin_notifications
WHERE admin_id='$admin_id'
ORDER BY created_at DESC
";

$notifications_result = mysqli_query($conn, $notifications_query);

$unread_query = "
SELECT COUNT(*) AS unread_total
FROM admin_notifications
WHERE admin_id='$admin_id'
AND is_read='No'
";

$unread_result = mysqli_query($conn, $unread_query);
$unread_data = mysqli_fetch_assoc($unread_result);
$unread_total = intval($unread_data['unread_total']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Notifications | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.notifications-container{
max-width:900px;
margin:auto;
}

.success-message{
background:#dcfce7;
color:#166534;
border-left:5px solid #16a34a;
padding:15px;
border-radius:10px;
margin-bottom:20px;
font-weight:bold;
}

.notification-card{
background:#fff;
padding:20px;
border-radius:14px;
margin-bottom:18px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
border-left:6px solid #ccc;
}

.notification-unread{
border-left-color:#2563eb;
background:#f8fafc;
}

.notification-header{
display:flex;
justify-content:space-between;
gap:15px;
align-items:flex-start;
}

.notification-header h3{
margin:0 0 8px 0;
font-size:18px;
}

.notification-card p{
line-height:1.6;
margin-bottom:10px;
}

.notification-time{
font-size:13px;
color:#777;
margin-top:10px;
}

.notification-actions{
display:flex;
gap:10px;
flex-wrap:wrap;
margin-top:12px;
}

.notification-actions a{
padding:9px 13px;
border-radius:8px;
text-decoration:none;
font-size:14px;
font-weight:bold;
}

.view-btn{
background:#111;
color:white;
}

.read-btn{
background:#16a34a;
color:white;
}

.delete-btn{
background:#c62828;
color:white;
}

.top-actions{
display:flex;
justify-content:space-between;
align-items:center;
gap:15px;
flex-wrap:wrap;
margin-bottom:25px;
}

.mark-all-btn{
background:#111;
color:#fff;
padding:11px 16px;
border-radius:8px;
text-decoration:none;
font-weight:bold;
}

.unread-badge,
.new-badge{
display:inline-block;
background:#2563eb;
color:#fff;
padding:6px 12px;
border-radius:30px;
font-size:14px;
font-weight:bold;
}

</style>
</head>

<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>Admin Notifications</h1>
</div>

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
<div class="success-message">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<div class="top-actions">

<h3>
Unread Notifications:
<span class="unread-badge"><?php echo $unread_total; ?></span>
</h3>

<a href="admin-notifications.php?mark_all=1" class="mark-all-btn">
Mark All As Read
</a>

</div>

<?php if($notifications_result && mysqli_num_rows($notifications_result) > 0){ ?>

<?php while($notification = mysqli_fetch_assoc($notifications_result)){ ?>

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
<?php } ?>

</div>

<div class="notification-time">
<?php echo date("d M Y H:i", strtotime($notification['created_at'])); ?>
</div>

<div class="notification-actions">

<?php if(!empty($notification['link'])){ ?>

<a
href="admin-notifications.php?read=<?php echo $notification['admin_notification_id']; ?>&go=<?php echo urlencode($notification['link']); ?>"
class="view-btn">
View
</a>

<?php } ?>

<?php if($notification['is_read'] == "No"){ ?>

<a
href="admin-notifications.php?read=<?php echo $notification['admin_notification_id']; ?>"
class="read-btn">
Mark Read
</a>

<?php } ?>

<a
href="admin-notifications.php?delete=<?php echo $notification['admin_notification_id']; ?>"
class="delete-btn"
onclick="return confirm('Delete this notification?');">
Delete
</a>

</div>

</div>

<?php } ?>

<?php }else{ ?>

<div class="info-box">
<h3>No Notifications</h3>
<p>You currently have no admin notifications.</p>
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