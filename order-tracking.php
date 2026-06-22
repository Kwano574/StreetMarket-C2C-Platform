<?php
// starting php session
session_start();

// preventing mysqli fatal errors from breaking the page
mysqli_report(MYSQLI_REPORT_OFF);

// connecting to database
include("includes/db.php");

// including notification functions
if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

// protecting user session by checking if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

// storing user's session data
$user_id = intval($_SESSION['user_id']);

// page variables
$message = "";
$message_type = "success";

// checking if database table exists
function trackingTableExists($conn, $table){

    $table = mysqli_real_escape_string($conn, $table);

    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// checking if database column exists
function trackingColumnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// sending notifications safely
function sendTrackingNotification($conn, $user_id, $notification_title, $notification_message, $link){

    $user_id = intval($user_id);

    if($user_id <= 0){
        return false;
    }

    // checking if notifications table exists
    if(!trackingTableExists($conn, "notifications")){
        return false;
    }

    // checking correct title column name because some databases use tittle instead of title
    $title_column = "";

    if(trackingColumnExists($conn, "notifications", "tittle")){
        $title_column = "tittle";
    }elseif(trackingColumnExists($conn, "notifications", "title")){
        $title_column = "title";
    }else{
        return false;
    }

    $notification_title = mysqli_real_escape_string($conn, $notification_title);
    $notification_message = mysqli_real_escape_string($conn, $notification_message);
    $link = mysqli_real_escape_string($conn, $link);

    // inserting notification
    $insert_notification = "
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES('$user_id', '$notification_title', '$notification_message', '$link', 'No', NOW())
    ";

    return mysqli_query($conn, $insert_notification);
}

// notifying admins when report is submitted
function notifyAdminsAboutReport($conn, $notification_title, $notification_message, $link){

    $admin_ids = [];

    // checking admins inside users table
    if(trackingTableExists($conn, "users") && trackingColumnExists($conn, "users", "role")){

        $users_admin_query = "
        SELECT user_id
        FROM users
        WHERE LOWER(REPLACE(role, ' ', '_')) IN ('admin','super_admin','main_super_admin','user_manager','product_manager')
        ";

        $users_admin_result = mysqli_query($conn, $users_admin_query);

        if($users_admin_result && mysqli_num_rows($users_admin_result) > 0){

            while($admin = mysqli_fetch_assoc($users_admin_result)){
                $admin_ids[] = intval($admin['user_id']);
            }
        }
    }

    // checking admins inside admin_users table
    if(trackingTableExists($conn, "admin_users") && trackingColumnExists($conn, "admin_users", "role")){

        $admin_id_column = "";

        if(trackingColumnExists($conn, "admin_users", "user_id")){
            $admin_id_column = "user_id";
        }elseif(trackingColumnExists($conn, "admin_users", "admin_id")){
            $admin_id_column = "admin_id";
        }elseif(trackingColumnExists($conn, "admin_users", "id")){
            $admin_id_column = "id";
        }

        if($admin_id_column != ""){

            $admin_users_query = "
            SELECT `$admin_id_column` AS admin_user_id
            FROM admin_users
            WHERE LOWER(REPLACE(role, ' ', '_')) IN ('admin','super_admin','main_super_admin','user_manager','product_manager')
            ";

            $admin_users_result = mysqli_query($conn, $admin_users_query);

            if($admin_users_result && mysqli_num_rows($admin_users_result) > 0){

                while($admin = mysqli_fetch_assoc($admin_users_result)){
                    $admin_ids[] = intval($admin['admin_user_id']);
                }
            }
        }
    }

    // removing duplicated admin ids
    $admin_ids = array_unique($admin_ids);

    // sending report notification to each admin
    foreach($admin_ids as $admin_id){

        if($admin_id > 0){
            sendTrackingNotification($conn, $admin_id, $notification_title, $notification_message, $link);
        }
    }
}

// sending a notification to an admin account
function sendTrackingAdminNotification($conn, $admin_id, $notification_title, $notification_message, $link){

    $admin_id = intval($admin_id);

    if($admin_id <= 0 || !trackingTableExists($conn, "admin_notifications")){
        return false;
    }

    $title_column = "";

    if(trackingColumnExists($conn, "admin_notifications", "title")){
        $title_column = "title";
    }elseif(trackingColumnExists($conn, "admin_notifications", "tittle")){
        $title_column = "tittle";
    }elseif(trackingColumnExists($conn, "admin_notifications", "notification_title")){
        $title_column = "notification_title";
    }else{
        return false;
    }

    $notification_title = mysqli_real_escape_string($conn, $notification_title);
    $notification_message = mysqli_real_escape_string($conn, $notification_message);
    $link = mysqli_real_escape_string($conn, $link);

    $insert_notification = "
    INSERT INTO admin_notifications(admin_id, `$title_column`, message, link, is_read, created_at)
    VALUES('$admin_id', '$notification_title', '$notification_message', '$link', 'No', NOW())
    ";

    return mysqli_query($conn, $insert_notification);
}

// notifying only the order manager and main super admin
function notifyCashManagementAdmins($conn, $notification_title, $notification_message, $link){

    $admin_ids = [];
    $admin_tables = ["admins", "admin", "admin_users"];

    foreach($admin_tables as $admin_table){

        if(!trackingTableExists($conn, $admin_table)){
            continue;
        }

        $admin_id_column = "";
        $role_column = "";

        foreach(["admin_id", "user_id", "id"] as $possible_id_column){
            if(trackingColumnExists($conn, $admin_table, $possible_id_column)){
                $admin_id_column = $possible_id_column;
                break;
            }
        }

        foreach(["role", "admin_role"] as $possible_role_column){
            if(trackingColumnExists($conn, $admin_table, $possible_role_column)){
                $role_column = $possible_role_column;
                break;
            }
        }

        if($admin_id_column == "" || $role_column == ""){
            continue;
        }

        $admin_query = "
        SELECT `$admin_id_column` AS selected_admin_id
        FROM `$admin_table`
        WHERE LOWER(REPLACE(REPLACE(`$role_column`, ' ', '_'), '-', '_'))
        IN ('main_super_admin', 'main_superadmin', 'super_admin', 'main_admin', 'order_manager', 'orders_manager')
        ";

        $admin_result = mysqli_query($conn, $admin_query);

        if($admin_result){
            while($admin = mysqli_fetch_assoc($admin_result)){
                $admin_ids[] = intval($admin['selected_admin_id']);
            }
        }
    }

    foreach(array_unique($admin_ids) as $admin_id){
        sendTrackingAdminNotification(
            $conn,
            $admin_id,
            $notification_title,
            $notification_message,
            $link
        );
    }
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

// getting order id
if(!isset($_GET['id'])){
    header("Location: orders.php");
    exit();
}

$reference_order_id = intval($_GET['id']);

// getting the reference order
$reference_query = "
SELECT *
FROM orders
WHERE order_id='$reference_order_id'
AND (buyer_id='$user_id' OR seller_id='$user_id')
LIMIT 1
";

$reference_result = mysqli_query($conn, $reference_query);

if(!$reference_result || mysqli_num_rows($reference_result) == 0){
    die("Order not found.");
}

$reference_order = mysqli_fetch_assoc($reference_result);
$buyer_id = intval($reference_order['buyer_id']);
$seller_id = intval($reference_order['seller_id']);
$order_group_id = mysqli_real_escape_string($conn, $reference_order['order_group_id']);

if(empty($order_group_id)){
    $order_group_id = "SM".$reference_order_id;
}

// getting full order group
$group_query = "
SELECT
MIN(orders.order_id) AS reference_order_id,
orders.order_group_id,
orders.buyer_id,
orders.seller_id,
MIN(orders.order_date) AS order_date,
orders.payment_method,
orders.payment_status,
orders.delivery_status,
orders.status,
orders.delivery_method,
orders.estimated_time,
orders.delivery_address,
orders.buyer_confirmed,
SUM(orders.total_amount) AS group_total,
COUNT(orders.order_id) AS item_count,
seller.business_name AS seller_business_name,
seller.full_name AS seller_name,
buyer.full_name AS buyer_name,
buyer.phone AS buyer_phone,
buyer.province AS buyer_province,
buyer.address AS buyer_address
FROM orders
INNER JOIN users AS seller ON orders.seller_id = seller.user_id
INNER JOIN users AS buyer ON orders.buyer_id = buyer.user_id
WHERE orders.buyer_id='$buyer_id'
AND orders.seller_id='$seller_id'
AND orders.order_group_id='$order_group_id'
AND orders.status!='cancelled'
GROUP BY
orders.order_group_id,
orders.buyer_id,
orders.seller_id,
orders.payment_method,
orders.payment_status,
orders.delivery_status,
orders.status,
orders.delivery_method,
orders.estimated_time,
orders.delivery_address,
orders.buyer_confirmed,
seller.business_name,
seller.full_name,
buyer.full_name,
buyer.phone,
buyer.province,
buyer.address
LIMIT 1
";

$group_result = mysqli_query($conn, $group_query);

if(!$group_result || mysqli_num_rows($group_result) == 0){
    die("Order group not found.");
}

$order_group = mysqli_fetch_assoc($group_result);

// getting all order ids in this group for report checking
$order_ids = [];

$order_ids_query = "
SELECT order_id
FROM orders
WHERE buyer_id='$buyer_id'
AND seller_id='$seller_id'
AND order_group_id='$order_group_id'
AND status!='cancelled'
";

$order_ids_result = mysqli_query($conn, $order_ids_query);

if($order_ids_result && mysqli_num_rows($order_ids_result) > 0){

    while($order_id_row = mysqli_fetch_assoc($order_ids_result)){
        $order_ids[] = intval($order_id_row['order_id']);
    }
}

if(empty($order_ids)){
    $order_ids[] = $reference_order_id;
}

$order_ids_string = implode(",", $order_ids);

// seller updates the full order group status
if(isset($_POST['update_status'])){

    if($user_id == $seller_id){

        $new_status = mysqli_real_escape_string($conn, $_POST['delivery_status']);
        $estimated_time = mysqli_real_escape_string($conn, trim($_POST['estimated_time']));
        $allowed_status = ["processing","packed","ready_for_pickup","shipped","out_for_delivery","delivered"];

        if(in_array($new_status, $allowed_status)){

            // updating full order group status
            $update_query = "
            UPDATE orders
            SET delivery_status='$new_status', estimated_time='$estimated_time'
            WHERE buyer_id='$buyer_id'
            AND seller_id='$seller_id'
            AND order_group_id='$order_group_id'
            AND status!='cancelled'
            ";

            if(mysqli_query($conn, $update_query)){

                // notifying buyer about delivery status update
                sendTrackingNotification(
                    $conn,
                    $buyer_id,
                    "Order Group Updated",
                    "Your order group #".$order_group_id." is now ".ucwords(str_replace("_", " ", $new_status)).".",
                    "order-tracking.php?id=".$reference_order_id
                );

                $_SESSION['success_message'] = "Order group status updated.";
                header("Location: order-tracking.php?id=".$reference_order_id);
                exit();

            }else{

                $_SESSION['error_message'] = "Failed to update order group.";
                header("Location: order-tracking.php?id=".$reference_order_id);
                exit();

            }
        }
    }
}

// seller confirms receiving cash for the full order group
if(isset($_POST['confirm_cash_payment'])){

    if($user_id != $seller_id){
        $_SESSION['error_message'] = "You are not allowed to confirm this payment.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }

    $current_payment_method = strtolower(trim($order_group['payment_method']));
    $current_payment_status = strtolower(trim($order_group['payment_status']));

    if($current_payment_method != "cash"){
        $_SESSION['error_message'] = "Cash confirmation is only available for cash orders.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }

    if($current_payment_status == "paid"){
        $_SESSION['error_message'] = "Cash payment has already been confirmed.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }

    $confirm_cash_query = "
    UPDATE orders
    SET payment_status='paid'
    WHERE buyer_id='$buyer_id'
    AND seller_id='$seller_id'
    AND order_group_id='$order_group_id'
    AND payment_method='cash'
    AND payment_status!='paid'
    AND status!='cancelled'
    ";

    if(mysqli_query($conn, $confirm_cash_query) && mysqli_affected_rows($conn) > 0){

        $seller_display = !empty($order_group['seller_business_name']) ? $order_group['seller_business_name'] : $order_group['seller_name'];
        $buyer_display = !empty($order_group['buyer_name']) ? $order_group['buyer_name'] : "the buyer";

        // notifying buyer that the seller received the cash
        sendTrackingNotification(
            $conn,
            $buyer_id,
            "Cash Payment Confirmed",
            "The seller confirmed receiving your cash payment for order group #".$order_group_id.".",
            "order-tracking.php?id=".$reference_order_id
        );

        // notifying the order manager and main super admin
        notifyCashManagementAdmins(
            $conn,
            "Cash Payment Received",
            $seller_display." confirmed receiving cash from ".$buyer_display." for order group #".$order_group_id.".",
            "admin-orders.php"
        );

        $_SESSION['success_message'] = "Cash payment confirmed successfully.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();

    }else{

        $_SESSION['error_message'] = "Cash payment could not be confirmed.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }
}

// buyer confirms the full order group
if(isset($_POST['confirm_delivery'])){

    if($user_id == $buyer_id){

        // confirming order group as completed
        $confirm_query = "
        UPDATE orders
        SET delivery_status='delivered', status='completed', buyer_confirmed='Yes'
        WHERE buyer_id='$buyer_id'
        AND seller_id='$seller_id'
        AND order_group_id='$order_group_id'
        AND status!='cancelled'
        ";

        if(mysqli_query($conn, $confirm_query)){

            // notifying seller that buyer confirmed the order group
            sendTrackingNotification(
                $conn,
                $seller_id,
                "Order Group Completed",
                "The buyer confirmed completion for order group #".$order_group_id.".",
                "manage-deliveries.php"
            );

            // notifying the order manager and main super admin about buyer confirmation
            notifyCashManagementAdmins(
                $conn,
                "Buyer Confirmed Delivery",
                $order_group['buyer_name']." confirmed receiving order group #".$order_group_id.".",
                "admin-orders.php"
            );

            $_SESSION['success_message'] = "Order group confirmed successfully. You can now review products or submit a report.";
            header("Location: order-tracking.php?id=".$reference_order_id);
            exit();

        }else{

            $_SESSION['error_message'] = "Failed to confirm order group.";
            header("Location: order-tracking.php?id=".$reference_order_id);
            exit();

        }
    }
}

// buyer submits report after confirming order
if(isset($_POST['submit_report'])){

    // refreshing order group before submitting report
    $group_result = mysqli_query($conn, $group_query);
    $order_group = mysqli_fetch_assoc($group_result);

    if($user_id == $buyer_id && $order_group['status'] == "completed" && $order_group['buyer_confirmed'] == "Yes"){

        // checking if buyer already reported this order group
        $check_report = mysqli_query($conn, "
        SELECT report_id
        FROM reports
        WHERE order_id IN ($order_ids_string)
        AND user_id='$user_id'
        AND seller_id='$seller_id'
        LIMIT 1
        ");

        if($check_report && mysqli_num_rows($check_report) == 0){

            $reason = mysqli_real_escape_string($conn, trim($_POST['report_reason']));
            $details = mysqli_real_escape_string($conn, trim($_POST['report_details']));
            $reported_user = mysqli_real_escape_string($conn, $order_group['seller_business_name']);

            if($reason == "" || $details == ""){

                $_SESSION['error_message'] = "Please complete all report details.";
                header("Location: order-tracking.php?id=".$reference_order_id);
                exit();

            }

            // inserting report
            $insert_report = "
            INSERT INTO reports(user_id, reported_user, report_reason, report_details, report_status, order_id, product_id, seller_id)
            VALUES('$user_id', '$reported_user', '$reason', '$details', 'pending', '$reference_order_id', NULL, '$seller_id')
            ";

            if(mysqli_query($conn, $insert_report)){

                $seller_display = !empty($order_group['seller_business_name']) ? $order_group['seller_business_name'] : $order_group['seller_name'];
                $buyer_name = !empty($order_group['buyer_name']) ? $order_group['buyer_name'] : "A buyer";

                // notifying seller with actual report details
                sendTrackingNotification(
                    $conn,
                    $seller_id,
                    "New Report Submitted",
                    $buyer_name . " submitted a report for order group #" . $order_group_id . ". Reason: " . $reason . ". Details: " . $details,
                    "order-tracking.php?id=".$reference_order_id
                );

                // notifying admins with actual report details
                notifyAdminsAboutReport(
                    $conn,
                    "New Delivery Report",
                    $buyer_name . " submitted a report against seller " . $seller_display . " for order group #" . $order_group_id . ". Reason: " . $reason . ". Details: " . $details,
                    "admin-reports.php"
                );

                $_SESSION['success_message'] = "Report submitted successfully.";
                header("Location: order-tracking.php?id=".$reference_order_id);
                exit();

            }else{

                $_SESSION['error_message'] = "Failed to submit report.";
                header("Location: order-tracking.php?id=".$reference_order_id);
                exit();

            }

        }else{

            $_SESSION['error_message'] = "You already reported this order group.";
            header("Location: order-tracking.php?id=".$reference_order_id);
            exit();

        }
    }
}

// seller reports a buyer who did not pay for a cash order
if(isset($_POST['report_cash_buyer'])){

    // refreshing the group before checking its payment and delivery status
    $group_result = mysqli_query($conn, $group_query);
    $order_group = mysqli_fetch_assoc($group_result);

    $is_cash_order = strtolower(trim($order_group['payment_method'])) == "cash";
    $cash_is_unpaid = strtolower(trim($order_group['payment_status'])) != "paid";
    $products_were_handed_over = $order_group['delivery_status'] == "delivered" || $order_group['buyer_confirmed'] == "Yes";

    if($user_id != $seller_id || !$is_cash_order || !$cash_is_unpaid || !$products_were_handed_over){
        $_SESSION['error_message'] = "This buyer report is only available for a delivered cash order that has not been paid.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }

    $seller_report_check = mysqli_query($conn, "
    SELECT report_id
    FROM reports
    WHERE order_id IN ($order_ids_string)
    AND user_id='$seller_id'
    LIMIT 1
    ");

    if($seller_report_check && mysqli_num_rows($seller_report_check) > 0){
        $_SESSION['error_message'] = "You already reported this buyer for this order group.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }

    $reason = mysqli_real_escape_string($conn, trim($_POST['buyer_report_reason']));
    $details = mysqli_real_escape_string($conn, trim($_POST['buyer_report_details']));
    $allowed_buyer_reasons = ["Buyer Left Without Paying", "Buyer Refused to Pay", "Cash Payment Dispute", "Buyer Misconduct", "Other"];

    if(!in_array($reason, $allowed_buyer_reasons) || $details == ""){
        $_SESSION['error_message'] = "Please select a valid reason and explain what happened.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }

    $buyer_display = !empty($order_group['buyer_name']) ? $order_group['buyer_name'] : "Buyer #".$buyer_id;
    $seller_display = !empty($order_group['seller_business_name']) ? $order_group['seller_business_name'] : $order_group['seller_name'];
    $reported_user = mysqli_real_escape_string($conn, $buyer_display);

    $insert_buyer_report = "
    INSERT INTO reports(user_id, reported_user, report_reason, report_details, report_status, order_id, product_id, seller_id)
    VALUES('$seller_id', '$reported_user', '$reason', '$details', 'pending', '$reference_order_id', NULL, '$seller_id')
    ";

    if(mysqli_query($conn, $insert_buyer_report)){

        sendTrackingNotification(
            $conn,
            $buyer_id,
            "Cash Order Report Submitted",
            $seller_display." reported a cash payment problem for order group #".$order_group_id.". Reason: ".$reason.".",
            "order-tracking.php?id=".$reference_order_id
        );

        notifyCashManagementAdmins(
            $conn,
            "Buyer Cash Payment Report",
            $seller_display." reported ".$buyer_display." for order group #".$order_group_id.". Reason: ".$reason.". Details: ".$details,
            "admin-reports.php"
        );

        $_SESSION['success_message'] = "Buyer report submitted successfully.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();

    }else{

        $_SESSION['error_message'] = "Buyer report could not be submitted.";
        header("Location: order-tracking.php?id=".$reference_order_id);
        exit();
    }
}

// refreshing order group after updates
$group_result = mysqli_query($conn, $group_query);
$order_group = mysqli_fetch_assoc($group_result);

$delivery_method = !empty($order_group['delivery_method']) ? $order_group['delivery_method'] : "delivery";
$current_status = $order_group['delivery_status'];

if($delivery_method == "pickup"){
    $status_steps = ["processing","packed","ready_for_pickup","delivered"];
}else{
    $status_steps = ["processing","packed","shipped","out_for_delivery","delivered"];
}

// getting order group products
$items_query = "
SELECT
orders.*,
product.product_name,
product.image,
product.price,
product.category
FROM orders
INNER JOIN product ON orders.product_id = product.product_id
WHERE orders.buyer_id='$buyer_id'
AND orders.seller_id='$seller_id'
AND orders.order_group_id='$order_group_id'
AND orders.status!='cancelled'
ORDER BY orders.order_id ASC
";

$items_result = mysqli_query($conn, $items_query);

// checking if buyer already reported the order group
$already_reported = false;

$check_report = mysqli_query($conn, "
SELECT report_id
FROM reports
WHERE order_id IN ($order_ids_string)
AND user_id='$user_id'
AND seller_id='$seller_id'
LIMIT 1
");

if($check_report && mysqli_num_rows($check_report) > 0){
    $already_reported = true;
}

// checking whether the seller already reported this buyer
$seller_already_reported = false;

$seller_report_check = mysqli_query($conn, "
SELECT report_id
FROM reports
WHERE order_id IN ($order_ids_string)
AND user_id='$seller_id'
LIMIT 1
");

if($seller_report_check && mysqli_num_rows($seller_report_check) > 0){
    $seller_already_reported = true;
}

// getting submitted reports for this order group
$reports_query = "
SELECT
reports.*,
users.full_name AS reporter_name
FROM reports
INNER JOIN users ON reports.user_id = users.user_id
WHERE reports.order_id IN ($order_ids_string)
AND reports.seller_id='$seller_id'
ORDER BY reports.report_id DESC
";

$reports_result = mysqli_query($conn, $reports_query);

// preparing buyer address display
$buyer_address_display = "";

if(!empty($order_group['delivery_address']) && $order_group['delivery_address'] != "Pickup selected"){
    $buyer_address_display = $order_group['delivery_address'];
}else{
    $buyer_address_display = trim($order_group['buyer_address']);
}

if($buyer_address_display == ""){
    $buyer_address_display = "No physical address provided.";
}

$display_group_id = !empty($order_group['order_group_id']) ? $order_group['order_group_id'] : "SM".$reference_order_id;
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Track Order Group | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.tracking-container{
max-width:1000px;
margin:auto;
}

.tracking-card{
background:white;
border-radius:18px;
padding:30px;
box-shadow:0 2px 15px rgba(0,0,0,0.08);
}

.order-header{
display:flex;
justify-content:space-between;
gap:15px;
flex-wrap:wrap;
border-bottom:1px solid #eee;
padding-bottom:20px;
margin-bottom:25px;
}

.order-item{
display:flex;
gap:15px;
align-items:center;
background:#f8f8f8;
padding:14px;
border-radius:12px;
margin-bottom:12px;
}

.order-item img{
width:90px;
height:90px;
object-fit:cover;
border-radius:10px;
}

.timeline{
margin-top:40px;
}

.timeline-step{
display:flex;
align-items:center;
margin-bottom:30px;
position:relative;
}

.timeline-circle{
width:45px;
height:45px;
border-radius:50%;
display:flex;
align-items:center;
justify-content:center;
font-weight:bold;
margin-right:20px;
background:#ddd;
color:#555;
}

.timeline-active{
background:#111;
color:white;
}

.timeline-line{
position:absolute;
left:22px;
top:45px;
width:2px;
height:40px;
background:#ddd;
}

.status-update-box{
margin-top:40px;
background:#f8f8f8;
padding:25px;
border-radius:14px;
}

.status-update-box select,
.status-update-box input,
.status-update-box textarea{
width:100%;
padding:14px;
border-radius:10px;
border:1px solid #ccc;
margin-top:10px;
margin-bottom:20px;
}

.status-update-box textarea{
min-height:120px;
resize:vertical;
}

.status-update-box button{
padding:14px 22px;
border:none;
background:#111;
color:white;
border-radius:10px;
font-weight:bold;
cursor:pointer;
}

.confirm-btn{
background:#16a34a !important;
}

.report-btn{
background:#c62828 !important;
}

.cash-btn{
background:#15803d !important;
}

.cash-payment-note{
background:#f0fdf4;
color:#166534;
border-left:5px solid #16a34a;
padding:15px;
border-radius:10px;
margin-bottom:20px;
}

.review-btn{
background:#2563eb !important;
margin-top:10px;
}

.status-badge{
display:inline-block;
padding:10px 18px;
border-radius:50px;
background:#111;
color:white;
font-size:14px;
font-weight:bold;
margin-top:10px;
}

.estimate-box{
background:#eff6ff;
color:#1e3a8a;
border-left:5px solid #2563eb;
padding:15px;
border-radius:10px;
margin-top:15px;
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

.error-message{
background:#fee2e2;
color:#991b1b;
border-left:5px solid #dc2626;
padding:15px;
border-radius:10px;
margin-bottom:20px;
font-weight:bold;
}

.reported-box,
.reviewed-box{
background:#eff6ff;
color:#1e3a8a;
border-left:5px solid #2563eb;
padding:15px;
border-radius:10px;
margin-top:15px;
}

.review-display-box{
background:#ecfdf5;
color:#14532d;
border-left:5px solid #16a34a;
padding:15px;
border-radius:10px;
margin-top:15px;
}

.report-display-box{
background:#fff7ed;
color:#9a3412;
border-left:5px solid #f97316;
padding:15px;
border-radius:10px;
margin-top:15px;
}

.group-details{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:15px;
margin-top:15px;
}

.detail-box{
background:#f8fafc;
padding:12px;
border-radius:10px;
border:1px solid #e2e8f0;
}

.address-box{
grid-column:1 / -1;
}

.size-text{
font-size:14px;
color:#555;
}

@media(max-width:768px){

.order-item{
flex-direction:column;
align-items:flex-start;
}

.order-item img{
width:100%;
height:auto;
}

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

<!-- Navigation bar links -->
<nav>

<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="orders.php">Orders</a>
<a href="messages.php">Messages</a>
<a href="notifications.php">Notifications</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container tracking-container">

<div class="page-intro">

<h2>Track Order Group</h2>

<p>Monitor all products bought from this seller in the same checkout.</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="tracking-card">

<div class="order-header">

<div>

<h2>Order Group #<?php echo htmlspecialchars($display_group_id); ?></h2>

<p>Seller: <b><?php echo htmlspecialchars(!empty($order_group['seller_business_name']) ? $order_group['seller_business_name'] : $order_group['seller_name']); ?></b></p>

<p>Buyer: <b><?php echo htmlspecialchars($order_group['buyer_name']); ?></b></p>

<p>Ordered On: <?php echo date("d M Y H:i", strtotime($order_group['order_date'])); ?></p>

</div>

<div>

<div class="status-badge"><?php echo ucwords(str_replace("_", " ", $current_status)); ?></div>

<p><b><?php echo intval($order_group['item_count']); ?></b> product(s)</p>

<p><b>Total:</b> R<?php echo number_format($order_group['group_total'], 2); ?></p>

</div>

</div>

<?php if($items_result && mysqli_num_rows($items_result) > 0){ ?>

<?php while($item = mysqli_fetch_assoc($items_result)){ ?>

<div class="order-item">

<img src="uploads/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">

<div>

<h3><?php echo htmlspecialchars($item['product_name']); ?></h3>

<p>Category: <?php echo htmlspecialchars($item['category']); ?></p>

<?php if(!empty($item['selected_size'])){ ?>

<p class="size-text">Size / Option: <b><?php echo htmlspecialchars($item['selected_size']); ?></b></p>

<?php } ?>

<p>Quantity: <?php echo intval($item['quantity']); ?></p>

<p>Unit Price: R<?php echo number_format($item['price'], 2); ?></p>

<p>Order Line Total: R<?php echo number_format($item['total_amount'], 2); ?></p>

<?php
// checking if this product has already been reviewed
$product_id = intval($item['product_id']);
$item_order_id = intval($item['order_id']);

$review_check = mysqli_query($conn, "
SELECT
product_reviews.*,
users.full_name AS reviewer_name
FROM product_reviews
INNER JOIN users ON product_reviews.user_id = users.user_id
WHERE product_reviews.order_id='$item_order_id'
AND product_reviews.product_id='$product_id'
LIMIT 1
");

$review_data = null;

if($review_check && mysqli_num_rows($review_check) > 0){
    $review_data = mysqli_fetch_assoc($review_check);
}
?>

<?php if($review_data){ ?>

<div class="review-display-box">

<h4>Buyer Review</h4>

<p><b>Rating:</b> <?php echo intval($review_data['rating']); ?>/5</p>

<p><b>Comment:</b> <?php echo htmlspecialchars($review_data['review']); ?></p>

<p><b>Reviewed By:</b> <?php echo htmlspecialchars($review_data['reviewer_name']); ?></p>

<?php if(!empty($review_data['created_at'])){ ?>
<p><b>Date:</b> <?php echo date("d M Y H:i", strtotime($review_data['created_at'])); ?></p>
<?php } ?>

</div>

<?php } ?>

<?php if($user_id == $buyer_id && $order_group['status'] == "completed" && $order_group['buyer_confirmed'] == "Yes"){ ?>

<?php if($review_data){ ?>

<div class="reviewed-box">You already reviewed this product.</div>

<?php }else{ ?>

<a href="review-product.php?order_id=<?php echo $item_order_id; ?>&product_id=<?php echo $product_id; ?>">
<button type="button" class="review-btn">Review This Product</button>
</a>

<?php } ?>

<?php } ?>

</div>

</div>

<?php } ?>

<?php } ?>

<div class="group-details">

<div class="detail-box">

<p>Delivery Method:</p>

<b><?php echo ucwords($delivery_method); ?></b>

</div>

<div class="detail-box">

<p>Payment Method:</p>

<b><?php echo ucwords(str_replace("_", " ", $order_group['payment_method'])); ?></b>

</div>

<div class="detail-box">

<p>Payment Status:</p>

<b><?php echo ucfirst($order_group['payment_status']); ?></b>

</div>

<div class="detail-box">

<p>Buyer Province:</p>

<b><?php echo !empty($order_group['buyer_province']) ? htmlspecialchars($order_group['buyer_province']) : "Not provided"; ?></b>

</div>

<div class="detail-box address-box">

<p>Buyer Physical Address:</p>

<b><?php echo nl2br(htmlspecialchars($buyer_address_display)); ?></b>

</div>

</div>

<?php if(!empty($order_group['estimated_time'])){ ?>

<div class="estimate-box">
<?php echo htmlspecialchars($order_group['estimated_time']); ?>
</div>

<?php } ?>

<?php if($reports_result && mysqli_num_rows($reports_result) > 0){ ?>

<div class="report-display-box">

<h3>Report Details</h3>

<?php while($report = mysqli_fetch_assoc($reports_result)){ ?>

<p><b>Reported By:</b> <?php echo htmlspecialchars($report['reporter_name']); ?></p>

<p><b>Reason:</b> <?php echo htmlspecialchars($report['report_reason']); ?></p>

<p><b>Details:</b> <?php echo nl2br(htmlspecialchars($report['report_details'])); ?></p>

<p><b>Status:</b> <?php echo ucfirst($report['report_status']); ?></p>

<?php if(isset($report['created_at']) && !empty($report['created_at'])){ ?>
<p><b>Date:</b> <?php echo date("d M Y H:i", strtotime($report['created_at'])); ?></p>
<?php } ?>

<hr>

<?php } ?>

</div>

<?php } ?>

<div class="timeline">

<?php foreach($status_steps as $index => $step){ ?>

<?php $is_active = array_search($current_status, $status_steps) >= $index; ?>

<div class="timeline-step">

<div class="timeline-circle <?php if($is_active){ echo "timeline-active"; } ?>">
<?php echo $index + 1; ?>
</div>

<?php if($index != count($status_steps)-1){ ?>

<div class="timeline-line"></div>

<?php } ?>

<div class="timeline-content">

<h3><?php echo ucwords(str_replace("_", " ", $step)); ?></h3>

<p><?php echo $is_active ? "Completed" : "Pending"; ?></p>

</div>

</div>

<?php } ?>

</div>

<?php if($user_id == $seller_id && $order_group['status'] != "completed" && $order_group['status'] != "cancelled"){ ?>

<div class="status-update-box">

<h3>Update Full Order Group</h3>

<form method="POST">

<select name="delivery_status" required>

<option value="">Select Status</option>
<option value="processing">Processing</option>
<option value="packed">Packed</option>

<?php if($delivery_method == "pickup"){ ?>

<option value="ready_for_pickup">Ready For Pickup</option>

<?php }else{ ?>

<option value="shipped">Shipped</option>
<option value="out_for_delivery">Out For Delivery</option>

<?php } ?>

<option value="delivered">Completed / Delivered</option>

</select>

<label>Estimated Time</label>

<input
type="text"
name="estimated_time"
value="<?php echo htmlspecialchars($order_group['estimated_time']); ?>"
placeholder="<?php echo $delivery_method == 'pickup' ? 'Example: Ready for pickup in 30 minutes' : 'Example: Delivery in 45 minutes'; ?>">

<button type="submit" name="update_status">Update Full Order Group</button>

</form>

</div>

<?php } ?>

<?php if($user_id == $seller_id && strtolower($order_group['payment_method']) == "cash" && $order_group['status'] != "cancelled"){ ?>

<div class="status-update-box">

<h3>Cash Payment</h3>

<?php if(strtolower($order_group['payment_status']) == "paid"){ ?>

<div class="cash-payment-note">
Cash payment has been confirmed as received for this full order group.
</div>

<?php }else{ ?>

<p>Confirm only after you have physically received the full cash payment from the buyer.</p>

<form method="POST" onsubmit="return confirm('Have you received the full cash payment from the buyer?');">
<button type="submit" name="confirm_cash_payment" class="cash-btn">Confirm Cash Received</button>
</form>

<?php if(($current_status == "delivered" || $order_group['buyer_confirmed'] == "Yes")){ ?>

<hr>

<h3>Report Buyer for Unpaid Cash Order</h3>

<?php if($seller_already_reported){ ?>

<div class="reported-box">You have already reported this buyer for this order group.</div>

<?php }else{ ?>

<p>Use this report if the products were handed to the buyer but the buyer did not provide the cash payment.</p>

<form method="POST">

<select name="buyer_report_reason" required>
<option value="">Select Report Reason</option>
<option value="Buyer Left Without Paying">Buyer Left Without Paying</option>
<option value="Buyer Refused to Pay">Buyer Refused to Pay</option>
<option value="Cash Payment Dispute">Cash Payment Dispute</option>
<option value="Buyer Misconduct">Buyer Misconduct</option>
<option value="Other">Other</option>
</select>

<textarea name="buyer_report_details" placeholder="Explain what happened and include important order details." required></textarea>

<button type="submit" name="report_cash_buyer" class="report-btn">Report Buyer</button>

</form>

<?php } ?>

<?php } ?>

<?php } ?>

</div>

<?php } ?>

<?php if($user_id == $buyer_id && $current_status == "delivered" && $order_group['buyer_confirmed'] == "No"){ ?>

<div class="status-update-box">

<h3>Confirm Full Order Group</h3>

<p>Please confirm once you have received or collected all products in this order group.</p>

<form method="POST">

<button type="submit" name="confirm_delivery" class="confirm-btn">Confirm Order Group Received</button>

</form>

</div>

<?php } ?>

<?php if($user_id == $buyer_id && $order_group['status'] == "completed" && $order_group['buyer_confirmed'] == "Yes"){ ?>

<div class="status-update-box">

<h3>Report Seller or Order Group</h3>

<?php if($already_reported){ ?>

<div class="reported-box">You have already submitted a report for this order group.</div>

<?php }else{ ?>

<form method="POST">

<select name="report_reason" required>

<option value="">Select Report Reason</option>
<option value="Fake Product">Fake Product</option>
<option value="Wrong Product">Wrong Product</option>
<option value="Damaged Product">Damaged Product</option>
<option value="Scam">Scam</option>
<option value="Seller Misconduct">Seller Misconduct</option>
<option value="Other">Other</option>

</select>

<textarea name="report_details" placeholder="Explain the problem clearly." required></textarea>

<button type="submit" name="submit_report" class="report-btn">Submit Report</button>

</form>

<?php } ?>

</div>

<?php } ?>

</div>

</div>

</section>

<!-- Footer -->
<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>
<a href="help.php">Help</a>
<a href="sellercenter.php">Seller Support</a>

</nav>

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

</body>

</html>