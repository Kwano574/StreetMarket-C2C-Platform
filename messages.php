<?php
// starting php session
session_start();

// preventing mysqli fatal errors from breaking ajax response
mysqli_report(MYSQLI_REPORT_OFF);

// connecting page to database
include("includes/db.php");

// including notification functions
if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

// protecting user session
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

// storing user's session details
$user_id = intval($_SESSION['user_id']);
$product_id = isset($_GET['product']) ? intval($_GET['product']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// checking if table exists
function messagesTableExists($conn, $table_name){

    $table_name = mysqli_real_escape_string($conn, $table_name);

    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");

    return ($result && mysqli_num_rows($result) > 0);
}

// checking if column exists
function messagesColumnExists($conn, $table_name, $column_name){

    $table_name = mysqli_real_escape_string($conn, $table_name);
    $column_name = mysqli_real_escape_string($conn, $column_name);

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table_name` LIKE '$column_name'");

    return ($result && mysqli_num_rows($result) > 0);
}

// getting notification title column safely
function getMessagesNotificationTitleColumn($conn, $table_name){

    if(messagesColumnExists($conn, $table_name, "tittle")){
        return "tittle";
    }

    if(messagesColumnExists($conn, $table_name, "title")){
        return "title";
    }

    return "";
}

// sending notification to user safely
function sendUserMessageNotification($conn, $user_id, $notification_title, $notification_message, $link){

    if(!messagesTableExists($conn, "notifications")){
        return true;
    }

    // checking correct title column because some databases use tittle instead of title
    $title_column = getMessagesNotificationTitleColumn($conn, "notifications");

    if($title_column == ""){
        return true;
    }

    $user_id = intval($user_id);

    // inserting notification safely
    $insert_stmt = $conn->prepare("
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES(?,?,?,?, 'No', NOW())
    ");

    if(!$insert_stmt){
        return true;
    }

    $insert_stmt->bind_param("isss", $user_id, $notification_title, $notification_message, $link);
    $insert_stmt->execute();

    return true;
}

// sending notification to admin safely
function sendAdminMessageNotification($conn, $admin_id, $notification_title, $notification_message, $link){

    if(!messagesTableExists($conn, "admin_notifications")){
        return true;
    }

    // checking correct title column because some databases use tittle instead of title
    $title_column = getMessagesNotificationTitleColumn($conn, "admin_notifications");

    if($title_column == ""){
        return true;
    }

    $admin_id = intval($admin_id);

    // inserting admin notification safely
    $insert_stmt = $conn->prepare("
    INSERT INTO admin_notifications(admin_id, `$title_column`, message, link, is_read, created_at)
    VALUES(?,?,?,?, 'No', NOW())
    ");

    if(!$insert_stmt){
        return true;
    }

    $insert_stmt->bind_param("isss", $admin_id, $notification_title, $notification_message, $link);
    $insert_stmt->execute();

    return true;
}

// updating user activity for online status
$activity_stmt = $conn->prepare("
UPDATE users
SET last_activity=NOW()
WHERE user_id=?
");

if($activity_stmt){
    $activity_stmt->bind_param("i", $user_id);
    $activity_stmt->execute();
}

// helper function for getting user's profile image
function getProfileImage($image){

    if(!empty($image)){
        return "uploads/" . basename($image);
    }

    return "images/default-profile.png";
}

// helper function for building chat links
function buildUserChatLink($receiver_id, $receiver_type, $product_id){

    if($receiver_type == "admin"){
        $link = "messages.php?user=-" . intval($receiver_id);
    }else{
        $link = "messages.php?user=" . intval($receiver_id);
    }

    if($product_id > 0){
        $link .= "&product=" . intval($product_id);
    }

    return $link;
}

// getting support admin default chat
$support_admin_id = 0;
$support_admin_name = "Platform Support";

$support_stmt = $conn->prepare("
SELECT admin_id, full_name
FROM admin_users
WHERE role='support_admin'
ORDER BY admin_id ASC
LIMIT 1
");

if($support_stmt){
    $support_stmt->execute();
    $support_result = $support_stmt->get_result();

    if($support_result && $support_result->num_rows > 0){
        $support_admin = $support_result->fetch_assoc();
        $support_admin_id = intval($support_admin['admin_id']);
        $support_admin_name = $support_admin['full_name'];
    }
}

// setting selected chat receiver
$receiver_id = 0;
$receiver_type = "";

if(isset($_GET['user'])){

    $url_receiver = intval($_GET['user']);

    if($url_receiver < 0){
        $receiver_id = abs($url_receiver);
        $receiver_type = "admin";
    }elseif($url_receiver > 0){
        $receiver_id = $url_receiver;
        $receiver_type = "user";
    }

}else{

    if($support_admin_id > 0){
        $receiver_id = $support_admin_id;
        $receiver_type = "admin";
    }
}

// getting receiver details
$receiver_exists = false;
$receiver_name = "Select a chat";
$receiver_image = "images/default-profile.png";
$receiver_status = "Search for a user or open a recent conversation.";
$receiver_online_status = "";

// admin receiver
if($receiver_id > 0 && $receiver_type == "admin"){

    // allowing user to open chat with any valid admin
    $admin_stmt = $conn->prepare("
    SELECT admin_id, full_name, role, last_activity
    FROM admin_users
    WHERE admin_id=?
    LIMIT 1
    ");

    if($admin_stmt){
        $admin_stmt->bind_param("i", $receiver_id);
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();

        if($admin_result && $admin_result->num_rows > 0){

            $admin = $admin_result->fetch_assoc();

            $receiver_exists = true;
            $receiver_name = $admin['full_name'];
            $receiver_image = "images/assistant.png";
            $receiver_status = "StreetMarket Admin - " . ucwords(str_replace("_", " ", $admin['role']));

            if(!empty($admin['last_activity']) && strtotime($admin['last_activity']) >= strtotime("-2 minutes")){
                $receiver_online_status = "Online";
            }elseif(!empty($admin['last_activity'])){
                $receiver_online_status = "Last seen " . date("d M Y H:i", strtotime($admin['last_activity']));
            }else{
                $receiver_online_status = "Offline";
            }

        }else{
            $receiver_id = 0;
            $receiver_type = "";
        }
    }

// normal user receiver
}elseif($receiver_id > 0 && $receiver_type == "user"){

    $receiver_stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE user_id=?
    AND user_id != ?
    LIMIT 1
    ");

    if($receiver_stmt){
        $receiver_stmt->bind_param("ii", $receiver_id, $user_id);
        $receiver_stmt->execute();
        $receiver_result = $receiver_stmt->get_result();

        if($receiver_result && $receiver_result->num_rows > 0){

            $receiver = $receiver_result->fetch_assoc();

            if(!isset($receiver['status']) || strtolower($receiver['status']) == "active"){

                $receiver_exists = true;
                $receiver_name = $receiver['full_name'];
                $receiver_image = getProfileImage($receiver['profile_image']);
                $receiver_status = "StreetMarket User";

                if(!empty($receiver['last_activity']) && strtotime($receiver['last_activity']) >= strtotime("-2 minutes")){
                    $receiver_online_status = "Online";
                }elseif(!empty($receiver['last_activity'])){
                    $receiver_online_status = "Last seen " . date("d M Y H:i", strtotime($receiver['last_activity']));
                }else{
                    $receiver_online_status = "Offline";
                }

            }else{
                $receiver_id = 0;
                $receiver_type = "";
            }

        }else{
            $receiver_id = 0;
            $receiver_type = "";
        }
    }
}

// getting product chat preview
$product_preview = false;

if($product_id > 0){

    $product_stmt = $conn->prepare("
    SELECT product_id, product_name, image, price
    FROM product
    WHERE product_id=?
    LIMIT 1
    ");

    if($product_stmt){
        $product_stmt->bind_param("i", $product_id);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();

        if($product_result && $product_result->num_rows > 0){
            $product_preview = $product_result->fetch_assoc();
        }
    }
}

// setting typing status using AJAX
if(isset($_POST['ajax_action']) && $_POST['ajax_action'] == "typing"){

    header("Content-Type: text/plain");

    $typing_receiver_id = intval($_POST['receiver_id']);
    $typing_receiver_type = isset($_POST['receiver_type']) ? trim($_POST['receiver_type']) : "";
    $typing_product_id = intval($_POST['product_id']);

    if($typing_receiver_id > 0 && ($typing_receiver_type == "user" || $typing_receiver_type == "admin")){

        // typing table uses receiver_id only, so admin receiver is stored as negative for typing only
        if($typing_receiver_type == "admin"){
            $typing_storage_receiver_id = 0 - $typing_receiver_id;
        }else{
            $typing_storage_receiver_id = $typing_receiver_id;
        }

        $delete_old = $conn->prepare("
        DELETE FROM user_typing
        WHERE sender_id=?
        AND receiver_id=?
        AND product_id=?
        ");

        if($delete_old){
            $delete_old->bind_param("iii", $user_id, $typing_storage_receiver_id, $typing_product_id);
            $delete_old->execute();
        }

        $insert_typing = $conn->prepare("
        INSERT INTO user_typing(sender_id, receiver_id, product_id, last_typing)
        VALUES(?,?,?,NOW())
        ");

        if($insert_typing){
            $insert_typing->bind_param("iii", $user_id, $typing_storage_receiver_id, $typing_product_id);
            $insert_typing->execute();
        }
    }

    echo "success";
    exit();
}

// fetching typing status using AJAX
if(isset($_GET['ajax_action']) && $_GET['ajax_action'] == "typing_status"){

    $typing_sender_id = intval($_GET['user']);
    $typing_sender_type = isset($_GET['type']) ? trim($_GET['type']) : "";
    $typing_product_id = intval($_GET['product']);

    if($typing_sender_id > 0 && ($typing_sender_type == "user" || $typing_sender_type == "admin")){

        // typing table uses sender_id only, so admin sender is stored as negative for typing only
        if($typing_sender_type == "admin"){
            $typing_storage_sender_id = 0 - $typing_sender_id;
        }else{
            $typing_storage_sender_id = $typing_sender_id;
        }

        $typing_stmt = $conn->prepare("
        SELECT typing_id
        FROM user_typing
        WHERE sender_id=?
        AND receiver_id=?
        AND product_id=?
        AND last_typing >= DATE_SUB(NOW(), INTERVAL 4 SECOND)
        LIMIT 1
        ");

        if($typing_stmt){
            $typing_stmt->bind_param("iii", $typing_storage_sender_id, $user_id, $typing_product_id);
            $typing_stmt->execute();
            $typing_result = $typing_stmt->get_result();

            if($typing_result && $typing_result->num_rows > 0){
                echo htmlspecialchars($receiver_name) . " is typing...";
            }
        }
    }

    exit();
}

// deleting message for everyone
if(isset($_POST['ajax_action']) && $_POST['ajax_action'] == "delete_message"){

    header("Content-Type: text/plain");

    $message_id = intval($_POST['message_id']);

    // deleting only messages sent by this user
    $delete_stmt = $conn->prepare("
    UPDATE messages
    SET is_deleted='Yes', message='This message was deleted.'
    WHERE message_id=?
    AND sender_id=?
    AND sender_type='user'
    ");

    if(!$delete_stmt){
        echo "Message delete failed: " . $conn->error;
        exit();
    }

    $delete_stmt->bind_param("ii", $message_id, $user_id);

    if($delete_stmt->execute()){
        echo "success";
    }else{
        echo "Message could not be deleted.";
    }

    exit();
}

// sending messages using AJAX
if(isset($_POST['ajax_action']) && $_POST['ajax_action'] == "send_message"){

    header("Content-Type: text/plain");

    $receiver_id = intval($_POST['receiver_id']);
    $receiver_type = isset($_POST['receiver_type']) ? trim($_POST['receiver_type']) : "";
    $product_id = intval($_POST['product_id']);
    $message_text_raw = isset($_POST['message']) ? trim($_POST['message']) : "";

    if($message_text_raw == ""){
        echo "Message cannot be empty.";
        exit();
    }

    if(strlen($message_text_raw) > 1000){
        echo "Message too long. Please keep it under 1000 characters.";
        exit();
    }

    if($receiver_id <= 0){
        echo "Please select a valid chat.";
        exit();
    }

    if($receiver_type != "user" && $receiver_type != "admin"){
        echo "Invalid receiver type.";
        exit();
    }

    if($receiver_type == "user" && $receiver_id == $user_id){
        echo "You cannot message yourself.";
        exit();
    }

    if(!messagesTableExists($conn, "messages")){
        echo "Messages table does not exist.";
        exit();
    }

    if(!messagesColumnExists($conn, "messages", "sender_type") || !messagesColumnExists($conn, "messages", "receiver_type")){
        echo "Messages table is missing sender_type or receiver_type columns.";
        exit();
    }

    // spam protection
    $spam_stmt = $conn->prepare("
    SELECT sent_at
    FROM messages
    WHERE sender_id=?
    AND sender_type='user'
    ORDER BY message_id DESC
    LIMIT 1
    ");

    if($spam_stmt){
        $spam_stmt->bind_param("i", $user_id);
        $spam_stmt->execute();
        $spam_result = $spam_stmt->get_result();

        if($spam_result && $spam_result->num_rows > 0){

            $last_message = $spam_result->fetch_assoc();

            if(strtotime($last_message['sent_at']) > strtotime("-3 seconds")){
                echo "Please wait a few seconds before sending another message.";
                exit();
            }
        }
    }

    // getting sender name for notifications
    $sender_stmt = $conn->prepare("
    SELECT full_name
    FROM users
    WHERE user_id=?
    LIMIT 1
    ");

    $sender_name = "A user";

    if($sender_stmt){
        $sender_stmt->bind_param("i", $user_id);
        $sender_stmt->execute();
        $sender_result = $sender_stmt->get_result();
        $sender_data = $sender_result->fetch_assoc();

        if($sender_data){
            $sender_name = $sender_data['full_name'];
        }
    }

    // checking receiver before inserting message
    if($receiver_type == "user"){

        $check_user_stmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE user_id=?
        AND user_id != ?
        LIMIT 1
        ");

        if(!$check_user_stmt){
            echo "Receiver check failed: " . $conn->error;
            exit();
        }

        $check_user_stmt->bind_param("ii", $receiver_id, $user_id);
        $check_user_stmt->execute();
        $check_user_result = $check_user_stmt->get_result();

        if(!$check_user_result || $check_user_result->num_rows == 0){
            echo "Receiver user not found.";
            exit();
        }

    }else{

        // allowing user to send message to any valid admin
        $check_admin_stmt = $conn->prepare("
        SELECT admin_id
        FROM admin_users
        WHERE admin_id=?
        LIMIT 1
        ");

        if(!$check_admin_stmt){
            echo "Admin check failed: " . $conn->error;
            exit();
        }

        $check_admin_stmt->bind_param("i", $receiver_id);
        $check_admin_stmt->execute();
        $check_admin_result = $check_admin_stmt->get_result();

        if(!$check_admin_result || $check_admin_result->num_rows == 0){
            echo "Admin not found.";
            exit();
        }
    }

    if($product_id < 0){
        $product_id = 0;
    }

    // inserting message using sender type and receiver type
    $insert_stmt = $conn->prepare("
    INSERT INTO messages(
        sender_id,
        sender_type,
        receiver_id,
        receiver_type,
        message,
        sent_at,
        product_id,
        is_read,
        is_deleted
    )
    VALUES(?,?,?,?,?,NOW(),?,'No','No')
    ");

    if(!$insert_stmt){
        echo "Message insert failed: " . $conn->error;
        exit();
    }

    $sender_type = "user";

    $insert_stmt->bind_param(
        "isissi",
        $user_id,
        $sender_type,
        $receiver_id,
        $receiver_type,
        $message_text_raw,
        $product_id
    );

    if($insert_stmt->execute()){

        // notification for normal user receiver
        if($receiver_type == "user"){

            $chat_link = "messages.php?user=" . $user_id;

            if($product_id > 0){
                $chat_link .= "&product=" . $product_id;
            }

            sendUserMessageNotification(
                $conn,
                $receiver_id,
                "New Message",
                $sender_name . " sent you a new message.",
                $chat_link
            );

        }else{

            // notification for admin receiver
            sendAdminMessageNotification(
                $conn,
                $receiver_id,
                "New User Message",
                $sender_name . " sent you a message.",
                "admin-messages.php?user=" . $user_id
            );
        }

        echo "success";

    }else{

        echo "Message could not be sent: " . $insert_stmt->error;

    }

    exit();
}

// fetching messages with AJAX
if(isset($_GET['ajax_action']) && $_GET['ajax_action'] == "fetch_messages"){

    header("Content-Type: text/html");

    $receiver_id = intval($_GET['user']);
    $receiver_type = isset($_GET['type']) ? trim($_GET['type']) : "";
    $product_id = intval($_GET['product']);

    if($receiver_id <= 0 || ($receiver_type != "user" && $receiver_type != "admin")){
        echo "<div class='empty-chat-box'>Select a chat to start messaging.</div>";
        exit();
    }

    // marking messages from receiver as read
    $read_stmt = $conn->prepare("
    UPDATE messages
    SET is_read='Yes', read_at=NOW()
    WHERE sender_id=?
    AND sender_type=?
    AND receiver_id=?
    AND receiver_type='user'
    AND is_read='No'
    ");

    if($read_stmt){
        $read_stmt->bind_param("isi", $receiver_id, $receiver_type, $user_id);
        $read_stmt->execute();
    }

    // adding product condition only when product chat is active
    if($product_id > 0){

        $chat_stmt = $conn->prepare("
        SELECT *
        FROM messages
        WHERE
        (
            (
                sender_id=?
                AND sender_type='user'
                AND receiver_id=?
                AND receiver_type=?
            )
            OR
            (
                sender_id=?
                AND sender_type=?
                AND receiver_id=?
                AND receiver_type='user'
            )
        )
        AND product_id=?
        ORDER BY sent_at ASC
        ");

        if($chat_stmt){
            $chat_stmt->bind_param(
                "iisisii",
                $user_id,
                $receiver_id,
                $receiver_type,
                $receiver_id,
                $receiver_type,
                $user_id,
                $product_id
            );
        }

    }else{

        $chat_stmt = $conn->prepare("
        SELECT *
        FROM messages
        WHERE
        (
            (
                sender_id=?
                AND sender_type='user'
                AND receiver_id=?
                AND receiver_type=?
            )
            OR
            (
                sender_id=?
                AND sender_type=?
                AND receiver_id=?
                AND receiver_type='user'
            )
        )
        ORDER BY sent_at ASC
        ");

        if($chat_stmt){
            $chat_stmt->bind_param(
                "iisisi",
                $user_id,
                $receiver_id,
                $receiver_type,
                $receiver_id,
                $receiver_type,
                $user_id
            );
        }
    }

    if(!$chat_stmt){
        echo "<div class='empty-chat-box'>Messages could not be loaded.</div>";
        exit();
    }

    $chat_stmt->execute();
    $chat_result = $chat_stmt->get_result();

    if($chat_result && $chat_result->num_rows > 0){

        while($chat = $chat_result->fetch_assoc()){

            $is_sent = ($chat['sender_id'] == $user_id && $chat['sender_type'] == "user");
            $class = $is_sent ? "sent" : "received";

            ?>

            <div class="message <?php echo $class; ?>">

                <p><?php echo nl2br(htmlspecialchars($chat['message'])); ?></p>

                <span>
                    <?php echo date("H:i", strtotime($chat['sent_at'])); ?>

                    <?php if($is_sent && $chat['is_deleted'] != "Yes"){ ?>
                        <button class="delete-message-btn" onclick="deleteMessage(<?php echo intval($chat['message_id']); ?>)">Delete</button>
                    <?php } ?>
                </span>

            </div>

            <?php
        }

    }else{

        echo "<div class='empty-chat-box'>No messages yet. Start the conversation.</div>";

    }

    exit();
}

// enabling and validating searching users
$search_users_result = false;

if($search != ""){

    $search_value = "%" . $search . "%";

    $search_stmt = $conn->prepare("
    SELECT user_id, full_name, profile_image
    FROM users
    WHERE full_name LIKE ?
    AND user_id != ?
    AND (status='Active' OR status='active' OR status IS NULL)
    ORDER BY full_name ASC
    LIMIT 30
    ");

    if($search_stmt){
        $search_stmt->bind_param("si", $search_value, $user_id);
        $search_stmt->execute();
        $search_users_result = $search_stmt->get_result();
    }
}

// getting unread support messages
$support_unread = 0;

if($support_admin_id > 0){

    $support_unread_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM messages
    WHERE sender_id=?
    AND sender_type='admin'
    AND receiver_id=?
    AND receiver_type='user'
    AND is_read='No'
    ");

    if($support_unread_stmt){
        $support_unread_stmt->bind_param("ii", $support_admin_id, $user_id);
        $support_unread_stmt->execute();
        $support_unread_result = $support_unread_stmt->get_result();

        if($support_unread_result){
            $support_unread_data = $support_unread_result->fetch_assoc();
            $support_unread = intval($support_unread_data['total']);
        }
    }
}

// getting recent admin conversations
$admin_conversations_stmt = $conn->prepare("
SELECT
a.admin_id,
a.full_name,
a.role,
MAX(m.sent_at) AS last_time,
(
    SELECT m2.message
    FROM messages m2
    WHERE
    (
        m2.sender_id=?
        AND m2.sender_type='user'
        AND m2.receiver_id=a.admin_id
        AND m2.receiver_type='admin'
    )
    OR
    (
        m2.sender_id=a.admin_id
        AND m2.sender_type='admin'
        AND m2.receiver_id=?
        AND m2.receiver_type='user'
    )
    ORDER BY m2.sent_at DESC
    LIMIT 1
) AS last_message,
(
    SELECT COUNT(*)
    FROM messages m3
    WHERE m3.sender_id=a.admin_id
    AND m3.sender_type='admin'
    AND m3.receiver_id=?
    AND m3.receiver_type='user'
    AND m3.is_read='No'
) AS unread_count
FROM messages m
INNER JOIN admin_users a
ON a.admin_id =
CASE
WHEN m.sender_id=? AND m.sender_type='user' THEN m.receiver_id
ELSE m.sender_id
END
WHERE
(
    (
        m.sender_id=?
        AND m.sender_type='user'
        AND m.receiver_type='admin'
    )
    OR
    (
        m.receiver_id=?
        AND m.receiver_type='user'
        AND m.sender_type='admin'
    )
)
GROUP BY a.admin_id, a.full_name, a.role
ORDER BY last_time DESC
");

$admin_conversations_result = false;

if($admin_conversations_stmt){
    $admin_conversations_stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $admin_conversations_stmt->execute();
    $admin_conversations_result = $admin_conversations_stmt->get_result();
}

// getting recent user conversations
$conversations_stmt = $conn->prepare("
SELECT
u.user_id,
u.full_name,
u.profile_image,
MAX(m.sent_at) AS last_time,
(
    SELECT m2.message
    FROM messages m2
    WHERE
    (
        m2.sender_id=?
        AND m2.sender_type='user'
        AND m2.receiver_id=u.user_id
        AND m2.receiver_type='user'
    )
    OR
    (
        m2.sender_id=u.user_id
        AND m2.sender_type='user'
        AND m2.receiver_id=?
        AND m2.receiver_type='user'
    )
    ORDER BY m2.sent_at DESC
    LIMIT 1
) AS last_message,
(
    SELECT COUNT(*)
    FROM messages m3
    WHERE m3.sender_id=u.user_id
    AND m3.sender_type='user'
    AND m3.receiver_id=?
    AND m3.receiver_type='user'
    AND m3.is_read='No'
) AS unread_count
FROM messages m
INNER JOIN users u
ON u.user_id =
CASE
WHEN m.sender_id=? AND m.sender_type='user' THEN m.receiver_id
ELSE m.sender_id
END
WHERE
u.user_id != ?
AND m.sender_type='user'
AND m.receiver_type='user'
AND
(
    m.sender_id=?
    OR m.receiver_id=?
)
GROUP BY u.user_id, u.full_name, u.profile_image
ORDER BY last_time DESC
");

$conversations_result = false;

if($conversations_stmt){
    $conversations_stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $conversations_stmt->execute();
    $conversations_result = $conversations_stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Messages | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>
*{box-sizing:border-box}
html,body{height:100%;margin:0;padding:0;font-family:Arial,sans-serif;background:#d9dbd5;overflow:hidden}
.chat-page{height:100dvh;width:100%;display:flex;align-items:center;justify-content:center;padding:16px}
.chat-shell{width:100%;max-width:1400px;height:calc(100dvh - 32px);background:white;display:grid;grid-template-columns:360px minmax(0,1fr);border-radius:16px;overflow:hidden;box-shadow:0 5px 25px rgba(0,0,0,0.18)}
.chat-sidebar{height:100%;min-height:0;display:flex;flex-direction:column;background:#fff;border-right:1px solid #ddd}
.sidebar-header{height:68px;flex-shrink:0;display:flex;align-items:center;justify-content:space-between;padding:0 18px;background:#f0f2f5;border-bottom:1px solid #ddd}
.sidebar-header h2{margin:0;font-size:20px}
.sidebar-header a{text-decoration:none;color:#111;font-size:14px;font-weight:bold}
.search-box{flex-shrink:0;padding:12px;background:#fff;border-bottom:1px solid #eee}
.search-box form{display:flex;gap:8px}
.search-box input{width:100%;padding:12px 14px;border:none;outline:none;background:#f0f2f5;border-radius:10px;font-size:14px}
.search-box button{padding:12px 14px;border:none;background:#111;color:#fff;border-radius:10px;font-weight:bold;cursor:pointer}
.chat-list{flex:1;min-height:0;overflow-y:auto}
.chat-label{padding:10px 16px;background:#f8f8f8;color:#667781;font-size:13px;font-weight:bold}
.chat-person{display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;color:#111;border-bottom:1px solid #f0f0f0}
.chat-person:hover{background:#f5f6f6}
.chat-person.active-chat{background:#e9edef}
.chat-person img{width:50px;height:50px;border-radius:50%;object-fit:cover;background:#ddd;flex-shrink:0}
.chat-info{min-width:0;flex:1}
.chat-info strong{display:block;font-size:15px;margin-bottom:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chat-info small{display:block;font-size:13px;color:#667781;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.unread-count{background:#2563eb;color:#ffffff;font-size:12px;font-weight:700;min-width:22px;height:22px;display:flex;align-items:center;justify-content:center;padding:0 7px;border-radius:999px;margin-left:auto;box-shadow:0 2px 6px rgba(37,99,235,0.25);flex-shrink:0}
.no-result{padding:16px;color:#667781;font-size:14px}
.chat-main{height:100%;min-height:0;display:flex;flex-direction:column;background:#efeae2}
.chat-header{height:68px;flex-shrink:0;display:flex;align-items:center;gap:12px;padding:0 18px;background:#f0f2f5;border-bottom:1px solid #ddd}
.chat-header img{width:48px;height:48px;border-radius:50%;object-fit:cover;background:#ddd;flex-shrink:0}
.chat-header h3{margin:0;font-size:17px}
.chat-header p{margin:4px 0 0;font-size:13px;color:#667781}
.product-preview{display:flex;align-items:center;gap:12px;background:#fff;padding:12px 18px;border-bottom:1px solid #ddd}
.product-preview img{width:55px;height:55px;object-fit:cover;border-radius:8px}
.product-preview strong{display:block;font-size:14px}
.product-preview span{font-size:13px;color:#667781}
.typing-status{min-height:22px;padding:0 24px;background:#efeae2;color:#2563eb;font-size:13px;font-weight:bold}
.chat-messages{flex:1;min-height:0;overflow-y:auto;padding:24px;background:#efeae2;scroll-behavior:smooth}
.chat-form{height:auto;min-height:70px;flex-shrink:0;display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0f2f5;border-top:1px solid #ddd}
.chat-form input[type="text"]{flex:1;min-width:0;padding:14px 16px;border:none;outline:none;border-radius:24px;font-size:15px;background:white}
.chat-form button{width:48px;height:48px;border:none;border-radius:50%;background:#2563eb;color:white;font-size:18px;cursor:pointer;font-weight:bold;flex-shrink:0}
.chat-form button:disabled,.chat-form input:disabled{opacity:0.55;cursor:not-allowed}
.emoji-btn{width:38px!important;height:38px!important;background:white!important;color:#111!important;font-size:18px!important;border:1px solid #ddd!important}
.message{max-width:65%;padding:9px 12px;border-radius:8px;margin-bottom:10px;font-size:15px;line-height:1.5;word-wrap:break-word;clear:both}
.message p{margin:0}
.message span{display:block;text-align:right;margin-top:4px;font-size:11px;color:#667781}
.delete-message-btn{border:none;background:transparent;color:#c62828;font-size:11px;cursor:pointer;margin-left:6px}
.sent{background:#dbeafe;margin-left:auto;border-top-right-radius:0}
.received{background:white;margin-right:auto;border-top-left-radius:0}
.empty-chat-box{text-align:center;color:#667781;font-size:16px;margin-top:40px}
@media(max-width:1024px){.chat-page{padding:10px}.chat-shell{height:calc(100dvh - 20px);grid-template-columns:320px minmax(0,1fr);border-radius:12px}.message{max-width:78%}}
@media(max-width:768px){.chat-page{padding:0}.chat-shell{height:100dvh;border-radius:0;display:flex;flex-direction:column}.chat-sidebar{height:38dvh;border-right:none;border-bottom:1px solid #ddd}.chat-main{height:62dvh;min-height:0}.sidebar-header,.chat-header{height:60px}.chat-messages{padding:16px}.chat-form{min-height:64px;padding:10px}.chat-form input[type="text"]{font-size:14px;padding:12px 14px}.message{max-width:88%;font-size:14px}}
</style>

</head>

<body>

<div class="chat-page">

<div class="chat-shell">

<aside class="chat-sidebar">

<div class="sidebar-header">
<h2>Chats</h2>
<a href="dashboard.php">Dashboard</a>
</div>

<div class="search-box">
<form method="GET" action="messages.php">

<?php if($product_id > 0){ ?>
<input type="hidden" name="product" value="<?php echo intval($product_id); ?>">
<?php } ?>

<input type="text" name="search" placeholder="Search user by name" value="<?php echo htmlspecialchars($search); ?>">
<button type="submit">Search</button>

</form>
</div>

<div class="chat-list">

<div class="chat-label">Support</div>

<?php if($support_admin_id > 0){ ?>

<a href="<?php echo htmlspecialchars(buildUserChatLink($support_admin_id, "admin", $product_id)); ?>" class="chat-person <?php if($receiver_id == $support_admin_id && $receiver_type == "admin"){ echo 'active-chat'; } ?>">

<img src="images/assistant.png" alt="Support">

<div class="chat-info">
<strong><?php echo htmlspecialchars($support_admin_name); ?></strong>
<small>StreetMarket Support Admin</small>
</div>

<?php if($support_unread > 0){ ?>
<span class="unread-count"><?php echo $support_unread; ?></span>
<?php } ?>

</a>

<?php }else{ ?>

<div class="no-result">No support admin found.</div>

<?php } ?>

<?php if($search != ""){ ?>

<div class="chat-label">Search Results</div>

<?php if($search_users_result && $search_users_result->num_rows > 0){ ?>

<?php while($search_user = $search_users_result->fetch_assoc()){ ?>

<?php $search_image = getProfileImage($search_user['profile_image']); ?>

<a href="<?php echo htmlspecialchars(buildUserChatLink($search_user['user_id'], "user", $product_id)); ?>" class="chat-person <?php if($receiver_id == $search_user['user_id'] && $receiver_type == "user"){ echo 'active-chat'; } ?>">

<img src="<?php echo htmlspecialchars($search_image); ?>" alt="User">

<div class="chat-info">
<strong><?php echo htmlspecialchars($search_user['full_name']); ?></strong>
<small>Click to start chatting</small>
</div>

</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">No users found.</div>

<?php } ?>

<?php } ?>

<div class="chat-label">Recent Admin Chats</div>

<?php if($admin_conversations_result && $admin_conversations_result->num_rows > 0){ ?>

<?php while($admin_conversation = $admin_conversations_result->fetch_assoc()){ ?>

<a href="<?php echo htmlspecialchars(buildUserChatLink($admin_conversation['admin_id'], "admin", $product_id)); ?>" class="chat-person <?php if($receiver_id == $admin_conversation['admin_id'] && $receiver_type == "admin"){ echo 'active-chat'; } ?>">

<img src="images/assistant.png" alt="Admin">

<div class="chat-info">
<strong><?php echo htmlspecialchars($admin_conversation['full_name']); ?></strong>
<small><?php echo htmlspecialchars(substr($admin_conversation['last_message'], 0, 45)); ?></small>
</div>

<?php if(isset($admin_conversation['unread_count']) && $admin_conversation['unread_count'] > 0){ ?>
<span class="unread-count"><?php echo intval($admin_conversation['unread_count']); ?></span>
<?php } ?>

</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">No recent admin chats yet.</div>

<?php } ?>

<div class="chat-label">Recent User Chats</div>

<?php if($conversations_result && $conversations_result->num_rows > 0){ ?>

<?php while($conversation = $conversations_result->fetch_assoc()){ ?>

<?php $conversation_image = getProfileImage($conversation['profile_image']); ?>

<a href="<?php echo htmlspecialchars(buildUserChatLink($conversation['user_id'], "user", $product_id)); ?>" class="chat-person <?php if($receiver_id == $conversation['user_id'] && $receiver_type == "user"){ echo 'active-chat'; } ?>">

<img src="<?php echo htmlspecialchars($conversation_image); ?>" alt="User">

<div class="chat-info">
<strong><?php echo htmlspecialchars($conversation['full_name']); ?></strong>
<small><?php echo htmlspecialchars(substr($conversation['last_message'], 0, 45)); ?></small>
</div>

<?php if(isset($conversation['unread_count']) && $conversation['unread_count'] > 0){ ?>
<span class="unread-count"><?php echo intval($conversation['unread_count']); ?></span>
<?php } ?>

</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">No recent user chats yet. Search for a user to start chatting.</div>

<?php } ?>

</div>

</aside>

<main class="chat-main">

<div class="chat-header">

<img src="<?php echo htmlspecialchars($receiver_image); ?>" alt="Profile">

<div>
<h3><?php echo htmlspecialchars($receiver_name); ?></h3>
<p><?php echo htmlspecialchars($receiver_status); ?> <?php if($receiver_online_status != ""){ echo "• " . htmlspecialchars($receiver_online_status); } ?></p>
</div>

</div>

<?php if($product_preview){ ?>

<div class="product-preview">

<img src="uploads/<?php echo htmlspecialchars(basename($product_preview['image'])); ?>" alt="Product">

<div>
<strong>Regarding: <?php echo htmlspecialchars($product_preview['product_name']); ?></strong>
<span>R<?php echo number_format($product_preview['price'], 2); ?></span>
</div>

</div>

<?php } ?>

<div class="typing-status" id="typingStatus"></div>

<div class="chat-messages" id="chatMessages"></div>

<form class="chat-form" id="chatForm">

<input type="hidden" id="receiver_id" value="<?php echo intval($receiver_id); ?>">
<input type="hidden" id="receiver_type" value="<?php echo htmlspecialchars($receiver_type); ?>">
<input type="hidden" id="product_id" value="<?php echo intval($product_id); ?>">

<button type="button" class="emoji-btn" onclick="addEmoji('😊')">😊</button>
<button type="button" class="emoji-btn" onclick="addEmoji('🔥')">🔥</button>

<input
type="text"
id="messageInput"
maxlength="1000"
placeholder="<?php echo $receiver_exists ? 'Type a message' : 'Select a user first'; ?>"
autocomplete="off"
<?php if(!$receiver_exists){ echo "disabled"; } ?>
required>

<button type="submit" <?php if(!$receiver_exists){ echo "disabled"; } ?>>
&#10148;
</button>

</form>

</main>

</div>

</div>

<script>

var chatMessages = document.getElementById("chatMessages");
var chatForm = document.getElementById("chatForm");
var messageInput = document.getElementById("messageInput");
var receiverId = document.getElementById("receiver_id").value;
var receiverType = document.getElementById("receiver_type").value;
var productId = document.getElementById("product_id").value;
var typingStatus = document.getElementById("typingStatus");
var lastHTML = "";
var lastTypingSent = 0;

function scrollToBottom(){
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// loading messages without refreshing the page
function loadMessages(){

    fetch("messages.php?ajax_action=fetch_messages&user=" + encodeURIComponent(receiverId) + "&type=" + encodeURIComponent(receiverType) + "&product=" + encodeURIComponent(productId))
    .then(function(response){
        return response.text();
    })
    .then(function(data){

        if(data !== lastHTML){

            var wasNearBottom = chatMessages.scrollTop + chatMessages.clientHeight >= chatMessages.scrollHeight - 150;

            chatMessages.innerHTML = data;
            lastHTML = data;

            if(wasNearBottom || lastHTML === ""){
                scrollToBottom();
            }
        }
    });
}

// showing typing indicator
function loadTypingStatus(){

    fetch("messages.php?ajax_action=typing_status&user=" + encodeURIComponent(receiverId) + "&type=" + encodeURIComponent(receiverType) + "&product=" + encodeURIComponent(productId))
    .then(function(response){
        return response.text();
    })
    .then(function(data){
        typingStatus.innerHTML = data;
    });
}

// sending typing status to database
function sendTypingStatus(){

    var now = Date.now();

    if(now - lastTypingSent < 1500){
        return;
    }

    lastTypingSent = now;

    var formData = new FormData();
    formData.append("ajax_action", "typing");
    formData.append("receiver_id", receiverId);
    formData.append("receiver_type", receiverType);
    formData.append("product_id", productId);

    fetch("messages.php", {
        method:"POST",
        body:formData
    });
}

// adding simple emoji support
function addEmoji(emoji){

    if(messageInput.disabled){
        return;
    }

    messageInput.value = messageInput.value + emoji;
    messageInput.focus();
}

// deleting message
function deleteMessage(messageId){

    if(!confirm("Delete this message for everyone?")){
        return;
    }

    var formData = new FormData();
    formData.append("ajax_action", "delete_message");
    formData.append("message_id", messageId);

    fetch("messages.php", {
        method:"POST",
        body:formData
    })
    .then(function(response){
        return response.text();
    })
    .then(function(result){

        if(result.trim() == "success"){
            lastHTML = "";
            loadMessages();
        }else{
            if(result.trim() == ""){
                alert("Message could not be deleted.");
            }else{
                alert(result);
            }
        }
    });
}

if(messageInput){

    messageInput.addEventListener("input", function(){
        sendTypingStatus();
    });
}

if(chatForm){

    chatForm.addEventListener("submit", function(event){

        event.preventDefault();

        if(receiverId <= 0){
            alert("Please select a chat first.");
            return;
        }

        if(receiverType != "user" && receiverType != "admin"){
            alert("Invalid chat selected.");
            return;
        }

        var message = messageInput.value.trim();

        if(message == ""){
            return;
        }

        if(message.length > 1000){
            alert("Message too long. Please keep it under 1000 characters.");
            return;
        }

        var formData = new FormData();
        formData.append("ajax_action", "send_message");
        formData.append("receiver_id", receiverId);
        formData.append("receiver_type", receiverType);
        formData.append("product_id", productId);
        formData.append("message", message);

        fetch("messages.php", {
            method:"POST",
            body:formData
        })
        .then(function(response){
            return response.text();
        })
        .then(function(result){

            if(result.trim() == "success"){

                messageInput.value = "";
                lastHTML = "";
                loadMessages();

                setTimeout(function(){
                    scrollToBottom();
                }, 150);

            }else{

                if(result.trim() == ""){
                    alert("Message could not be sent. Please check your chat setup.");
                }else{
                    alert(result);
                }
            }
        });
    });
}

// opening chats without adding every chat to browser history
document.querySelectorAll(".chat-person").forEach(function(link){
    link.addEventListener("click", function(event){
        event.preventDefault();
        window.location.replace(this.href);
    });
});

// loading messages and typing status without refreshing page
loadMessages();
loadTypingStatus();

setTimeout(function(){
    scrollToBottom();
}, 300);

setInterval(function(){
    loadMessages();
}, 5000);

setInterval(function(){
    loadTypingStatus();
}, 2000);

</script>

</body>

</html>