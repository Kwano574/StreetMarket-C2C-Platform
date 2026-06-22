<?php
// starting php session
session_start();

// preventing mysqli fatal errors from breaking ajax response
mysqli_report(MYSQLI_REPORT_OFF);

// connecting page to database
include("includes/db.php");

// connecting admin authentication file
include("includes/admin-auth.php");

// protecting admin session
if(!isset($_SESSION['admin_id'])){
    header("Location: admin-login.php");
    exit();
}

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

// storing admin details
$admin_id = intval($_SESSION['admin_id']);
$admin_sender_id = $admin_id;
$admin_sender_type = "admin";
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// checking if table exists
function adminMessagesTableExists($conn, $table_name){

    $table_name = mysqli_real_escape_string($conn, $table_name);

    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");

    return ($result && mysqli_num_rows($result) > 0);
}

// checking if column exists
function adminMessagesColumnExists($conn, $table_name, $column_name){

    $table_name = mysqli_real_escape_string($conn, $table_name);
    $column_name = mysqli_real_escape_string($conn, $column_name);

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table_name` LIKE '$column_name'");

    return ($result && mysqli_num_rows($result) > 0);
}

// getting notification title column safely
function getAdminMessagesNotificationTitleColumn($conn, $table_name){

    if(adminMessagesColumnExists($conn, $table_name, "tittle")){
        return "tittle";
    }

    if(adminMessagesColumnExists($conn, $table_name, "title")){
        return "title";
    }

    return "";
}

// sending notification to user safely
function safeAdminUserNotification($conn, $user_id, $title, $message, $link){

    if(!adminMessagesTableExists($conn, "notifications")){
        return true;
    }

    // checking correct title column because some tables use tittle instead of title
    $title_column = getAdminMessagesNotificationTitleColumn($conn, "notifications");

    if($title_column == ""){
        return true;
    }

    $user_id = intval($user_id);

    // inserting user notification safely
    $insert_stmt = $conn->prepare("
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES(?,?,?,?, 'No', NOW())
    ");

    if(!$insert_stmt){
        return true;
    }

    $insert_stmt->bind_param("isss", $user_id, $title, $message, $link);
    $insert_stmt->execute();

    return true;
}

// sending notification to admin safely
function safeAdminAdminNotification($conn, $admin_id, $title, $message, $link){

    if(!adminMessagesTableExists($conn, "admin_notifications")){
        return true;
    }

    // checking correct title column because some tables use tittle instead of title
    $title_column = getAdminMessagesNotificationTitleColumn($conn, "admin_notifications");

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

    $insert_stmt->bind_param("isss", $admin_id, $title, $message, $link);
    $insert_stmt->execute();

    return true;
}

// getting current admin details
$current_admin_stmt = $conn->prepare("
SELECT *
FROM admin_users
WHERE admin_id=?
LIMIT 1
");

if(!$current_admin_stmt){
    die("Admin query failed.");
}

$current_admin_stmt->bind_param("i", $admin_id);
$current_admin_stmt->execute();
$current_admin_result = $current_admin_stmt->get_result();

if(!$current_admin_result || $current_admin_result->num_rows == 0){
    die("Admin account not found.");
}

$current_admin = $current_admin_result->fetch_assoc();
$admin_name = $current_admin['full_name'];
$admin_role = $current_admin['role'];

// updating admin online activity
if(adminMessagesColumnExists($conn, "admin_users", "last_activity")){

    $activity_stmt = $conn->prepare("
    UPDATE admin_users
    SET last_activity=NOW()
    WHERE admin_id=?
    ");

    if($activity_stmt){
        $activity_stmt->bind_param("i", $admin_id);
        $activity_stmt->execute();
    }
}

// sending message using AJAX
if(isset($_POST['ajax_action']) && $_POST['ajax_action'] == "send_message"){

    header("Content-Type: text/plain");

    $receiver_id = intval($_POST['receiver_id']);
    $receiver_type = isset($_POST['receiver_type']) ? trim($_POST['receiver_type']) : "";
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

    if($receiver_type == "admin" && $receiver_id == $admin_id){
        echo "You cannot message yourself.";
        exit();
    }

    // checking messages table before sending
    if(!adminMessagesTableExists($conn, "messages")){
        echo "Messages table does not exist.";
        exit();
    }

    // checking required message columns
    if(!adminMessagesColumnExists($conn, "messages", "sender_type") || !adminMessagesColumnExists($conn, "messages", "receiver_type")){
        echo "Messages table is missing sender_type or receiver_type columns.";
        exit();
    }

    // spam protection to stop messages being sent too quickly
    $spam_stmt = $conn->prepare("
    SELECT sent_at
    FROM messages
    WHERE sender_id=?
    AND sender_type='admin'
    ORDER BY message_id DESC
    LIMIT 1
    ");

    if(!$spam_stmt){
        echo "Message check failed.";
        exit();
    }

    $spam_stmt->bind_param("i", $admin_id);
    $spam_stmt->execute();
    $spam_result = $spam_stmt->get_result();

    if($spam_result && $spam_result->num_rows > 0){

        $last_message = $spam_result->fetch_assoc();

        if(strtotime($last_message['sent_at']) > strtotime("-3 seconds")){
            echo "Please wait a few seconds before sending another message.";
            exit();
        }
    }

    // checking receiver before inserting message
    if($receiver_type == "admin"){

        $check_receiver_stmt = $conn->prepare("
        SELECT admin_id
        FROM admin_users
        WHERE admin_id=?
        AND admin_id != ?
        LIMIT 1
        ");

        if(!$check_receiver_stmt){
            echo "Admin receiver check failed.";
            exit();
        }

        $check_receiver_stmt->bind_param("ii", $receiver_id, $admin_id);
        $check_receiver_stmt->execute();
        $check_receiver_result = $check_receiver_stmt->get_result();

        if(!$check_receiver_result || $check_receiver_result->num_rows == 0){
            echo "Admin receiver not found.";
            exit();
        }

    }else{

        $check_receiver_stmt = $conn->prepare("
        SELECT user_id
        FROM users
        WHERE user_id=?
        LIMIT 1
        ");

        if(!$check_receiver_stmt){
            echo "User receiver check failed.";
            exit();
        }

        $check_receiver_stmt->bind_param("i", $receiver_id);
        $check_receiver_stmt->execute();
        $check_receiver_result = $check_receiver_stmt->get_result();

        if(!$check_receiver_result || $check_receiver_result->num_rows == 0){
            echo "User receiver not found.";
            exit();
        }
    }

    // inserting admin message using sender type and receiver type
    $insert_stmt = $conn->prepare("
    INSERT INTO messages(
        sender_id,
        receiver_id,
        message,
        sent_at,
        product_id,
        is_read,
        is_deleted,
        sender_type,
        receiver_type
    )
    VALUES(?,?,?,NOW(),NULL,'No','No',?,?)
    ");

    if(!$insert_stmt){
        echo "Message insert failed. Please check messages table columns.";
        exit();
    }

    $insert_stmt->bind_param("iisss", $admin_id, $receiver_id, $message_text_raw, $admin_sender_type, $receiver_type);

    if($insert_stmt->execute()){

        // sending notification after message is inserted
        if($receiver_type == "user"){

            safeAdminUserNotification(
                $conn,
                $receiver_id,
                "New Message",
                $admin_name." sent you a message.",
                "messages.php?user=-".$admin_id
            );

        }else{

            safeAdminAdminNotification(
                $conn,
                $receiver_id,
                "New Admin Message",
                $admin_name." sent you a message.",
                "admin-messages.php?admin=".$admin_id
            );
        }

        echo "success";
        exit();

    }else{

        echo "Message could not be sent: " . $insert_stmt->error;
        exit();

    }
}

// deleting message for everyone using AJAX
if(isset($_POST['ajax_action']) && $_POST['ajax_action'] == "delete_message"){

    header("Content-Type: text/plain");

    $message_id = intval($_POST['message_id']);

    // deleting only messages sent by this admin
    $delete_stmt = $conn->prepare("
    UPDATE messages
    SET is_deleted='Yes', message='This message was deleted.'
    WHERE message_id=?
    AND sender_id=?
    AND sender_type='admin'
    ");

    if(!$delete_stmt){
        echo "Message delete failed.";
        exit();
    }

    $delete_stmt->bind_param("ii", $message_id, $admin_id);

    if($delete_stmt->execute()){
        echo "success";
    }else{
        echo "Message could not be deleted.";
    }

    exit();
}

// fetching messages using AJAX
if(isset($_GET['ajax_action']) && $_GET['ajax_action'] == "fetch_messages"){

    header("Content-Type: text/html");

    $chat_receiver_id = intval($_GET['chat']);
    $chat_receiver_type = isset($_GET['type']) ? trim($_GET['type']) : "";

    if($chat_receiver_id <= 0){
        echo "<div class='empty-chat-box'>Select a chat to start messaging.</div>";
        exit();
    }

    if($chat_receiver_type != "user" && $chat_receiver_type != "admin"){
        echo "<div class='empty-chat-box'>Invalid chat selected.</div>";
        exit();
    }

    // marking received messages as read
    $read_stmt = $conn->prepare("
    UPDATE messages
    SET is_read='Yes', read_at=NOW()
    WHERE sender_id=?
    AND sender_type=?
    AND receiver_id=?
    AND receiver_type='admin'
    AND is_read='No'
    ");

    if($read_stmt){
        $read_stmt->bind_param("isi", $chat_receiver_id, $chat_receiver_type, $admin_id);
        $read_stmt->execute();
    }

    // getting full chat
    $chat_stmt = $conn->prepare("
    SELECT *
    FROM messages
    WHERE
    (
        sender_id=?
        AND sender_type='admin'
        AND receiver_id=?
        AND receiver_type=?
    )
    OR
    (
        sender_id=?
        AND sender_type=?
        AND receiver_id=?
        AND receiver_type='admin'
    )
    ORDER BY sent_at ASC
    ");

    if(!$chat_stmt){
        echo "<div class='empty-chat-box'>Could not load messages.</div>";
        exit();
    }

    $chat_stmt->bind_param(
        "iisisi",
        $admin_id,
        $chat_receiver_id,
        $chat_receiver_type,
        $chat_receiver_id,
        $chat_receiver_type,
        $admin_id
    );

    $chat_stmt->execute();
    $chat_result = $chat_stmt->get_result();

    if($chat_result && $chat_result->num_rows > 0){

        while($chat = $chat_result->fetch_assoc()){

            $class = ($chat['sender_id'] == $admin_id && $chat['sender_type'] == "admin") ? "sent" : "received";
            ?>

            <div class="message <?php echo $class; ?>">

                <p><?php echo nl2br(htmlspecialchars($chat['message'])); ?></p>

                <span>
                    <?php echo date("H:i", strtotime($chat['sent_at'])); ?>

                    <?php if($chat['sender_id'] == $admin_id && $chat['sender_type'] == "admin" && (!isset($chat['is_deleted']) || $chat['is_deleted'] != "Yes")){ ?>

                    <button type="button" class="delete-message-btn" onclick="deleteMessage(<?php echo intval($chat['message_id']); ?>)">
                    Delete
                    </button>

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

// setting default receiver
$default_receiver_id = 0;
$default_receiver_type = "";

if($admin_role == "support_admin"){

    $default_stmt = $conn->prepare("
    SELECT admin_id
    FROM admin_users
    WHERE role='super_admin'
    AND admin_id != ?
    ORDER BY admin_id ASC
    LIMIT 1
    ");

}else{

    $default_stmt = $conn->prepare("
    SELECT admin_id
    FROM admin_users
    WHERE role='support_admin'
    AND admin_id != ?
    ORDER BY admin_id ASC
    LIMIT 1
    ");

}

if($default_stmt){

    $default_stmt->bind_param("i", $admin_id);
    $default_stmt->execute();
    $default_result = $default_stmt->get_result();

    if($default_result && $default_result->num_rows > 0){

        $default_admin = $default_result->fetch_assoc();
        $default_receiver_id = intval($default_admin['admin_id']);
        $default_receiver_type = "admin";

    }
}

if(isset($_GET['admin'])){
    $receiver_id = abs(intval($_GET['admin']));
    $receiver_type = "admin";
}elseif(isset($_GET['user'])){
    $receiver_id = intval($_GET['user']);
    $receiver_type = "user";
}else{
    $receiver_id = $default_receiver_id;
    $receiver_type = $default_receiver_type;
}

// getting receiver details
$receiver_exists = false;
$receiver_name = "Select a chat";
$receiver_image = "images/default-profile.png";
$receiver_status = "Search for a user or admin.";
$receiver_online_status = "";

if($receiver_id > 0 && $receiver_type == "admin"){

    $receiver_stmt = $conn->prepare("
    SELECT *
    FROM admin_users
    WHERE admin_id=?
    AND admin_id != ?
    LIMIT 1
    ");

    if($receiver_stmt){

        $receiver_stmt->bind_param("ii", $receiver_id, $admin_id);
        $receiver_stmt->execute();
        $receiver_result = $receiver_stmt->get_result();

        if($receiver_result && $receiver_result->num_rows > 0){

            $receiver = $receiver_result->fetch_assoc();

            $receiver_exists = true;
            $receiver_name = $receiver['full_name'];
            $receiver_image = "images/assistant.png";
            $receiver_status = "Admin - " . ucwords(str_replace("_", " ", $receiver['role']));

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
    }

}elseif($receiver_id > 0 && $receiver_type == "user"){

    $receiver_stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE user_id=?
    LIMIT 1
    ");

    if($receiver_stmt){

        $receiver_stmt->bind_param("i", $receiver_id);
        $receiver_stmt->execute();
        $receiver_result = $receiver_stmt->get_result();

        if($receiver_result && $receiver_result->num_rows > 0){

            $receiver = $receiver_result->fetch_assoc();

            $receiver_exists = true;
            $receiver_name = $receiver['full_name'];
            $receiver_status = "StreetMarket User";

            if(!empty($receiver['profile_image'])){
                if(file_exists("uploads/profile/" . $receiver['profile_image'])){
                    $receiver_image = "uploads/profile/" . $receiver['profile_image'];
                }else{
                    $receiver_image = "uploads/" . $receiver['profile_image'];
                }
            }

            if(isset($receiver['last_activity']) && !empty($receiver['last_activity']) && strtotime($receiver['last_activity']) >= strtotime("-2 minutes")){
                $receiver_online_status = "Online";
            }elseif(isset($receiver['last_activity']) && !empty($receiver['last_activity'])){
                $receiver_online_status = "Last seen " . date("d M Y H:i", strtotime($receiver['last_activity']));
            }else{
                $receiver_online_status = "Offline";
            }

        }else{

            $receiver_id = 0;
            $receiver_type = "";

        }
    }
}

// searching users and admins
$search_users_result = false;
$search_admins_result = false;

if($search != ""){

    $search_value = "%" . $search . "%";

    $search_users_stmt = $conn->prepare("
    SELECT user_id, full_name, profile_image
    FROM users
    WHERE full_name LIKE ?
    OR email LIKE ?
    ORDER BY full_name ASC
    LIMIT 30
    ");

    if($search_users_stmt){
        $search_users_stmt->bind_param("ss", $search_value, $search_value);
        $search_users_stmt->execute();
        $search_users_result = $search_users_stmt->get_result();
    }

    $search_admins_stmt = $conn->prepare("
    SELECT admin_id, full_name, role
    FROM admin_users
    WHERE (full_name LIKE ? OR email LIKE ?)
    AND admin_id != ?
    ORDER BY full_name ASC
    LIMIT 30
    ");

    if($search_admins_stmt){
        $search_admins_stmt->bind_param("ssi", $search_value, $search_value, $admin_id);
        $search_admins_stmt->execute();
        $search_admins_result = $search_admins_stmt->get_result();
    }
}

// getting default admins
$default_admins_stmt = $conn->prepare("
SELECT admin_id, full_name, role
FROM admin_users
WHERE admin_id != ?
AND role IN ('super_admin', 'support_admin')
ORDER BY
CASE
WHEN role='super_admin' THEN 1
WHEN role='support_admin' THEN 2
ELSE 3
END,
admin_id ASC
");

$default_admins_result = false;

if($default_admins_stmt){
    $default_admins_stmt->bind_param("i", $admin_id);
    $default_admins_stmt->execute();
    $default_admins_result = $default_admins_stmt->get_result();
}

// getting recent admin chats
$admin_conversations_stmt = $conn->prepare("
SELECT
a.admin_id,
a.full_name,
a.role,
MAX(m.sent_at) AS last_time,
(
    SELECT m2.message
    FROM messages m2
    WHERE m2.sender_type='admin'
    AND m2.receiver_type='admin'
    AND
    (
        (m2.sender_id=? AND m2.receiver_id=a.admin_id)
        OR
        (m2.sender_id=a.admin_id AND m2.receiver_id=?)
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
    AND m3.receiver_type='admin'
    AND m3.is_read='No'
) AS unread_count
FROM messages m
INNER JOIN admin_users a
ON a.admin_id =
CASE
WHEN m.sender_id=? AND m.sender_type='admin' THEN m.receiver_id
ELSE m.sender_id
END
WHERE m.sender_type='admin'
AND m.receiver_type='admin'
AND a.admin_id != ?
AND
(
    m.sender_id=?
    OR m.receiver_id=?
)
GROUP BY a.admin_id, a.full_name, a.role
ORDER BY last_time DESC
");

$admin_conversations_result = false;

if($admin_conversations_stmt){

    $admin_conversations_stmt->bind_param(
        "iiiiiii",
        $admin_id,
        $admin_id,
        $admin_id,
        $admin_id,
        $admin_id,
        $admin_id,
        $admin_id
    );

    $admin_conversations_stmt->execute();
    $admin_conversations_result = $admin_conversations_stmt->get_result();
}

// getting recent user chats
$user_conversations_stmt = $conn->prepare("
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
        AND m2.sender_type='admin'
        AND m2.receiver_id=u.user_id
        AND m2.receiver_type='user'
    )
    OR
    (
        m2.sender_id=u.user_id
        AND m2.sender_type='user'
        AND m2.receiver_id=?
        AND m2.receiver_type='admin'
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
    AND m3.receiver_type='admin'
    AND m3.is_read='No'
) AS unread_count
FROM messages m
INNER JOIN users u
ON u.user_id =
CASE
WHEN m.sender_id=? AND m.sender_type='admin' THEN m.receiver_id
ELSE m.sender_id
END
WHERE
(
    (
        m.sender_id=?
        AND m.sender_type='admin'
        AND m.receiver_type='user'
    )
    OR
    (
        m.receiver_id=?
        AND m.receiver_type='admin'
        AND m.sender_type='user'
    )
)
GROUP BY u.user_id, u.full_name, u.profile_image
ORDER BY last_time DESC
");

$user_conversations_result = false;

if($user_conversations_stmt){

    $user_conversations_stmt->bind_param(
        "iiiiii",
        $admin_id,
        $admin_id,
        $admin_id,
        $admin_id,
        $admin_id,
        $admin_id
    );

    $user_conversations_stmt->execute();
    $user_conversations_result = $user_conversations_stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Messages | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>
*{box-sizing:border-box}
html,body{height:100%;margin:0;padding:0;font-family:Arial,sans-serif;background:#d9dbd5;overflow:hidden}
.chat-page{height:100dvh;width:100%;display:flex;align-items:center;justify-content:center;padding:16px}
.chat-shell{width:100%;max-width:1400px;height:calc(100dvh - 32px);background:white;display:grid;grid-template-columns:370px minmax(0,1fr);border-radius:16px;overflow:hidden;box-shadow:0 5px 25px rgba(0,0,0,0.18)}
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
.chat-messages{flex:1;min-height:0;overflow-y:auto;padding:24px;background:#efeae2;scroll-behavior:smooth}
.chat-form{height:auto;min-height:70px;flex-shrink:0;display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f0f2f5;border-top:1px solid #ddd}
.chat-form input[type="text"]{flex:1;min-width:0;padding:14px 16px;border:none;outline:none;border-radius:24px;font-size:15px;background:white}
.chat-form button{width:48px;height:48px;border:none;border-radius:50%;background:#2563eb;color:white;font-size:18px;cursor:pointer;font-weight:bold;flex-shrink:0}
.chat-form input:disabled,.chat-form button:disabled{opacity:.55;cursor:not-allowed}
.message{max-width:65%;padding:9px 12px;border-radius:8px;margin-bottom:10px;font-size:15px;line-height:1.5;word-wrap:break-word;clear:both}
.message p{margin:0}
.message span{display:block;text-align:right;margin-top:4px;font-size:11px;color:#667781}
.delete-message-btn{border:none;background:transparent;color:#c62828;font-size:11px;cursor:pointer;margin-left:6px}
.sent{background:#dbeafe;margin-left:auto;border-top-right-radius:0}
.received{background:white;margin-right:auto;border-top-left-radius:0}
.empty-chat-box{text-align:center;color:#667781;font-size:16px;margin-top:40px}
@media(max-width:768px){.chat-page{padding:0}.chat-shell{height:100dvh;border-radius:0;display:flex;flex-direction:column}.chat-sidebar{height:38dvh;border-right:none;border-bottom:1px solid #ddd}.chat-main{height:62dvh}.message{max-width:88%}}
</style>

</head>

<body>

<div class="chat-page">

<div class="chat-shell">

<aside class="chat-sidebar">

<div class="sidebar-header">
<h2>Admin Chats</h2>
<a href="Admin-dashboard.php">Dashboard</a>
</div>

<div class="search-box">
<form method="GET" action="admin-messages.php">
<input type="text" name="search" placeholder="Search user or admin" value="<?php echo htmlspecialchars($search); ?>">
<button type="submit">Search</button>
</form>
</div>

<div class="chat-list">

<div class="chat-label">Default Admin Chats</div>

<?php if($default_admins_result && $default_admins_result->num_rows > 0){ ?>

<?php while($default_admin = $default_admins_result->fetch_assoc()){ ?>

<?php
$default_chat_id = intval($default_admin['admin_id']);

$unread_default_stmt = $conn->prepare("
SELECT COUNT(*) AS total
FROM messages
WHERE sender_id=?
AND sender_type='admin'
AND receiver_id=?
AND receiver_type='admin'
AND is_read='No'
");

$unread_default = 0;

if($unread_default_stmt){
    $unread_default_stmt->bind_param("ii", $default_chat_id, $admin_id);
    $unread_default_stmt->execute();
    $unread_default_result = $unread_default_stmt->get_result();
    $unread_default_data = $unread_default_result->fetch_assoc();
    $unread_default = intval($unread_default_data['total']);
}
?>

<a href="admin-messages.php?admin=<?php echo intval($default_admin['admin_id']); ?>" class="chat-person <?php if($receiver_id == $default_chat_id && $receiver_type == 'admin'){ echo 'active-chat'; } ?>">

<img src="images/assistant.png" alt="Admin">

<div class="chat-info">
<strong><?php echo htmlspecialchars($default_admin['full_name']); ?></strong>
<small><?php echo ucwords(str_replace("_", " ", $default_admin['role'])); ?></small>
</div>

<?php if($unread_default > 0){ ?>
<span class="unread-count"><?php echo $unread_default; ?></span>
<?php } ?>

</a>

<?php } ?>

<?php } ?>

<?php if($search != ""){ ?>

<div class="chat-label">Admin Search Results</div>

<?php if($search_admins_result && $search_admins_result->num_rows > 0){ ?>

<?php while($search_admin = $search_admins_result->fetch_assoc()){ ?>

<a href="admin-messages.php?admin=<?php echo intval($search_admin['admin_id']); ?>" class="chat-person">
<img src="images/assistant.png" alt="Admin">
<div class="chat-info">
<strong><?php echo htmlspecialchars($search_admin['full_name']); ?></strong>
<small><?php echo ucwords(str_replace("_", " ", $search_admin['role'])); ?></small>
</div>
</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">No admins found.</div>

<?php } ?>

<div class="chat-label">User Search Results</div>

<?php if($search_users_result && $search_users_result->num_rows > 0){ ?>

<?php while($search_user = $search_users_result->fetch_assoc()){ ?>

<?php
$user_img = "images/default-profile.png";

if(!empty($search_user['profile_image'])){
    if(file_exists("uploads/profile/" . $search_user['profile_image'])){
        $user_img = "uploads/profile/" . $search_user['profile_image'];
    }else{
        $user_img = "uploads/" . $search_user['profile_image'];
    }
}
?>

<a href="admin-messages.php?user=<?php echo intval($search_user['user_id']); ?>" class="chat-person">
<img src="<?php echo htmlspecialchars($user_img); ?>" alt="User">
<div class="chat-info">
<strong><?php echo htmlspecialchars($search_user['full_name']); ?></strong>
<small>StreetMarket User</small>
</div>
</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">No users found.</div>

<?php } ?>

<?php } ?>

<div class="chat-label">Recent Admin Chats</div>

<?php if($admin_conversations_result && $admin_conversations_result->num_rows > 0){ ?>

<?php while($conversation = $admin_conversations_result->fetch_assoc()){ ?>

<?php $conversation_id = intval($conversation['admin_id']); ?>

<a href="admin-messages.php?admin=<?php echo intval($conversation['admin_id']); ?>" class="chat-person <?php if($receiver_id == $conversation_id && $receiver_type == 'admin'){ echo 'active-chat'; } ?>">

<img src="images/assistant.png" alt="Admin">

<div class="chat-info">
<strong><?php echo htmlspecialchars($conversation['full_name']); ?></strong>
<small><?php echo htmlspecialchars(substr($conversation['last_message'], 0, 45)); ?></small>
</div>

<?php if(intval($conversation['unread_count']) > 0){ ?>
<span class="unread-count"><?php echo intval($conversation['unread_count']); ?></span>
<?php } ?>

</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">No recent admin chats.</div>

<?php } ?>

<div class="chat-label">Recent User Chats</div>

<?php if($user_conversations_result && $user_conversations_result->num_rows > 0){ ?>

<?php while($conversation = $user_conversations_result->fetch_assoc()){ ?>

<?php
$user_img = "images/default-profile.png";

if(!empty($conversation['profile_image'])){
    if(file_exists("uploads/profile/" . $conversation['profile_image'])){
        $user_img = "uploads/profile/" . $conversation['profile_image'];
    }else{
        $user_img = "uploads/" . $conversation['profile_image'];
    }
}
?>

<a href="admin-messages.php?user=<?php echo intval($conversation['user_id']); ?>" class="chat-person <?php if($receiver_id == $conversation['user_id'] && $receiver_type == 'user'){ echo 'active-chat'; } ?>">

<img src="<?php echo htmlspecialchars($user_img); ?>" alt="User">

<div class="chat-info">
<strong><?php echo htmlspecialchars($conversation['full_name']); ?></strong>
<small><?php echo htmlspecialchars(substr($conversation['last_message'], 0, 45)); ?></small>
</div>

<?php if(intval($conversation['unread_count']) > 0){ ?>
<span class="unread-count"><?php echo intval($conversation['unread_count']); ?></span>
<?php } ?>

</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">No recent user chats.</div>

<?php } ?>

</div>

</aside>

<main class="chat-main">

<div class="chat-header">

<img src="<?php echo htmlspecialchars($receiver_image); ?>" alt="Profile">

<div>
<h3><?php echo htmlspecialchars($receiver_name); ?></h3>
<p>
<?php echo htmlspecialchars($receiver_status); ?>
<?php if($receiver_online_status != ""){ echo " • " . htmlspecialchars($receiver_online_status); } ?>
</p>
</div>

</div>

<div class="chat-messages" id="chatMessages"></div>

<form class="chat-form" id="chatForm">

<input type="hidden" id="receiver_id" value="<?php echo intval($receiver_id); ?>">
<input type="hidden" id="receiver_type" value="<?php echo htmlspecialchars($receiver_type); ?>">

<input type="text" id="messageInput" maxlength="1000" placeholder="<?php echo $receiver_exists ? 'Type a message' : 'Select a chat first'; ?>" autocomplete="off" <?php if(!$receiver_exists){ echo "disabled"; } ?>>

<button type="submit" <?php if(!$receiver_exists){ echo "disabled"; } ?>>&#10148;</button>

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
var lastHTML = "";

// scrolling chat to the bottom
function scrollToBottom(){
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// loading messages using ajax
function loadMessages(){

    fetch("admin-messages.php?ajax_action=fetch_messages&chat=" + encodeURIComponent(receiverId) + "&type=" + encodeURIComponent(receiverType))
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

// deleting message using ajax
function deleteMessage(messageId){

    if(!confirm("Delete this message for everyone?")){
        return;
    }

    var formData = new FormData();
    formData.append("ajax_action", "delete_message");
    formData.append("message_id", messageId);

    fetch("admin-messages.php", {
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
            alert(result);
        }
    });
}

// sending message using ajax
if(chatForm){

    chatForm.addEventListener("submit", function(event){

        event.preventDefault();

        var message = messageInput.value.trim();

        if(receiverId <= 0){
            alert("Please select a chat first.");
            return;
        }

        if(receiverType != "user" && receiverType != "admin"){
            alert("Invalid chat selected.");
            return;
        }

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
        formData.append("message", message);

        fetch("admin-messages.php", {
            method:"POST",
            body:formData
        })
        .then(function(response){
            return response.text();
        })
        .then(function(result){

            result = result.trim();

            if(result == "success"){

                messageInput.value = "";
                lastHTML = "";
                loadMessages();

                setTimeout(function(){
                    scrollToBottom();
                }, 150);

            }else{

                if(result == ""){
                    alert("Message not sent: Server returned an empty response.");
                }else{
                    alert("Message not sent: " + result);
                }
            }
        })
        .catch(function(error){
            alert("Message not sent: " + error);
        });
    });
}

loadMessages();

setTimeout(function(){
    scrollToBottom();
}, 300);

// loading every 5 seconds to reduce server pressure
setInterval(loadMessages, 5000);

</script>

</body>

</html>