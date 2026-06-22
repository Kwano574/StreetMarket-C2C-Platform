<?php

// starting php session
session_start();

// connecting to database
include("includes/db.php");

// protecting session by checking if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = "";

// function to check if redirect link is safe
function safeNotificationRedirect($go){

    $go = trim($go);

    if($go == ""){
        return "notifications.php";
    }

    // blocking external links
    if(strpos($go, "http://") !== false || strpos($go, "https://") !== false || strpos($go, "//") !== false){
        return "notifications.php";
    }

    // blocking folder jumping
    if(strpos($go, "../") !== false || strpos($go, "..\\") !== false){
        return "notifications.php";
    }

    return $go;
}

// enabling user to mark single notification as read and redirect
if(isset($_GET['read'])){

    $notification_id = intval($_GET['read']);

    mysqli_query($conn, "
    UPDATE notifications
    SET is_read='Yes'
    WHERE notification_id='$notification_id'
    AND user_id='$user_id'
    ");

    if(isset($_GET['go']) && $_GET['go'] != ""){

        $go = safeNotificationRedirect($_GET['go']);

        header("Location: " . $go);
        exit();

    }else{

        header("Location: notifications.php");
        exit();

    }

}

// enabling user to mark all notifications as read at the same time
if(isset($_GET['mark_all'])){

    mysqli_query($conn, "
    UPDATE notifications
    SET is_read='Yes'
    WHERE user_id='$user_id'
    ");

    $message = "All notifications marked as read.";
}

// enabling user to delete a notification
if(isset($_GET['delete'])){

    $notification_id = intval($_GET['delete']);

    if(mysqli_query($conn, "
    DELETE FROM notifications
    WHERE notification_id='$notification_id'
    AND user_id='$user_id'
    ")){
        $message = "Notification deleted.";
    }

}

// getting notifications
$notifications_query = "
SELECT *
FROM notifications
WHERE user_id='$user_id'
ORDER BY created_at DESC
";

$notifications_result = mysqli_query($conn, $notifications_query);

// getting unread notifications count
$unread_query = "
SELECT COUNT(*) AS unread_total
FROM notifications
WHERE user_id='$user_id'
AND is_read='No'
";

$unread_result = mysqli_query($conn, $unread_query);
$unread_total = 0;

if($unread_result){
    $unread_data = mysqli_fetch_assoc($unread_result);
    $unread_total = intval($unread_data['unread_total']);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Notifications | StreetMarket</title>

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

.unread-badge{
display:inline-block;
background:#2563eb;
color:#fff;
padding:6px 12px;
border-radius:30px;
font-size:14px;
font-weight:bold;
}

.new-badge{
background:#2563eb;
color:white;
padding:6px 12px;
border-radius:30px;
font-size:13px;
font-weight:bold;
}

</style>

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img src="images/logo.png" alt="StreetMarket Logo">

<h1>StreetMarket</h1>

</div>

<nav>

<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="wishlist.php">Wishlist</a>
<a href="orders.php">Orders</a>
<a href="manage-deliveries.php">Manage Deliveries</a>
<a href="my-listings.php">My Listings</a>
<a href="messages.php">&#x1F4AC; Chat</a>
<a href="user-profile.php">&#x1F464; Profile</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container notifications-container">

<div class="page-intro">

<h2>Notifications</h2>

<p>View order updates, delivery progress, product approvals, messages and marketplace activity.</p>

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

<a href="notifications.php?mark_all=1" class="mark-all-btn">
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

<p>
<?php echo nl2br(htmlspecialchars($notification['message'])); ?>
</p>

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
href="notifications.php?read=<?php echo intval($notification['notification_id']); ?>&go=<?php echo urlencode($notification['link']); ?>"
class="view-btn">
View
</a>

<?php } ?>

<?php if($notification['is_read'] == "No"){ ?>

<a
href="notifications.php?read=<?php echo intval($notification['notification_id']); ?>"
class="read-btn">
Mark Read
</a>

<?php } ?>

<a
href="notifications.php?delete=<?php echo intval($notification['notification_id']); ?>"
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

<p>You currently have no notifications.</p>

</div>

<?php } ?>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>
<a href="shipping-guide.php">Shipping Guide</a>
<a href="help.php">Help</a>

</nav>

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

</body>

</html>