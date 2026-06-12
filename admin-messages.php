
<?php

session_start();

include("includes/db.php");
include("includes/admin-auth.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: admin-login.php");
    exit();
}

$admin_id = intval($_SESSION['admin_id']);
$admin_sender_id = 0 - $admin_id;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : "";

/* CURRENT ADMIN */

$current_admin_query = "
SELECT *
FROM admin_users
WHERE admin_id='$admin_id'
LIMIT 1
";

$current_admin_result = mysqli_query($conn, $current_admin_query);

if(!$current_admin_result || mysqli_num_rows($current_admin_result) == 0){
    die("Admin account not found.");
}

$current_admin = mysqli_fetch_assoc($current_admin_result);
$admin_name = $current_admin['full_name'];
$admin_role = $current_admin['role'];

/* SAFE USER NOTIFICATION */

function safeUserNotification($conn, $user_id, $title, $message, $link){

    $check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");

    if(!$check || mysqli_num_rows($check) == 0){
        return true;
    }

    $user_id = intval($user_id);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    mysqli_query($conn, "
    INSERT INTO notifications(user_id, title, message, link, is_read, created_at)
    VALUES('$user_id', '$title', '$message', '$link', 'No', NOW())
    ");

    return true;
}

/* SAFE ADMIN NOTIFICATION */

function safeAdminNotification($conn, $admin_id, $title, $message, $link){

    $check = mysqli_query($conn, "SHOW TABLES LIKE 'admin_notifications'");

    if(!$check || mysqli_num_rows($check) == 0){
        return true;
    }

    $admin_id = intval($admin_id);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $link = mysqli_real_escape_string($conn, $link);

    mysqli_query($conn, "
    INSERT INTO admin_notifications(admin_id, title, message, link, is_read, created_at)
    VALUES('$admin_id', '$title', '$message', '$link', 'No', NOW())
    ");

    return true;
}

/* SEND MESSAGE AJAX */

if(isset($_POST['ajax_action']) && $_POST['ajax_action'] == "send_message"){

    header("Content-Type: text/plain");

    $receiver_id = intval($_POST['receiver_id']);
    $message_text_raw = trim($_POST['message']);

    if($message_text_raw == ""){
        echo "Message cannot be empty.";
        exit();
    }

    if($receiver_id == 0){
        echo "Please select a valid chat.";
        exit();
    }

    if($receiver_id == $admin_sender_id){
        echo "You cannot message yourself.";
        exit();
    }

    $message_text = mysqli_real_escape_string($conn, $message_text_raw);

    /* CHECK RECEIVER */

    if($receiver_id < 0){

        $receiver_admin_id = abs($receiver_id);

        $check_receiver = "
        SELECT admin_id
        FROM admin_users
        WHERE admin_id='$receiver_admin_id'
        AND admin_id != '$admin_id'
        LIMIT 1
        ";

        $check_receiver_result = mysqli_query($conn, $check_receiver);

        if(!$check_receiver_result || mysqli_num_rows($check_receiver_result) == 0){
            echo "Admin receiver not found.";
            exit();
        }

    }else{

        $check_receiver = "
        SELECT user_id
        FROM users
        WHERE user_id='$receiver_id'
        LIMIT 1
        ";

        $check_receiver_result = mysqli_query($conn, $check_receiver);

        if(!$check_receiver_result || mysqli_num_rows($check_receiver_result) == 0){
            echo "User receiver not found.";
            exit();
        }

    }

    /* INSERT MESSAGE */

    $insert_message = "
    INSERT INTO messages(
        sender_id,
        receiver_id,
        message,
        sent_at,
        product_id,
        is_read
    )
    VALUES(
        '$admin_sender_id',
        '$receiver_id',
        '$message_text',
        NOW(),
        NULL,
        'No'
    )
    ";

    if(mysqli_query($conn, $insert_message)){

        if($receiver_id > 0){

            safeUserNotification(
                $conn,
                $receiver_id,
                "New Message",
                $admin_name." sent you a message.",
                "messages.php?user=".$admin_sender_id
            );

        }else{

            safeAdminNotification(
                $conn,
                abs($receiver_id),
                "New Admin Message",
                $admin_name." sent you a message.",
                "admin-messages.php?admin=".$admin_id
            );

        }

        echo "success";
        exit();

    }else{

        echo mysqli_error($conn);
        exit();

    }
}

/* FETCH MESSAGES AJAX */

if(isset($_GET['ajax_action']) && $_GET['ajax_action'] == "fetch_messages"){

    header("Content-Type: text/html");

    $chat_receiver_id = intval($_GET['chat']);

    if($chat_receiver_id == 0){
        echo "<div class='empty-chat-box'>Select a chat to start messaging.</div>";
        exit();
    }

    mysqli_query($conn, "
    UPDATE messages
    SET is_read='Yes', read_at=NOW()
    WHERE sender_id='$chat_receiver_id'
    AND receiver_id='$admin_sender_id'
    AND is_read='No'
    ");

    $chat_query = "
    SELECT *
    FROM messages
    WHERE
    (
        sender_id='$admin_sender_id'
        AND receiver_id='$chat_receiver_id'
    )
    OR
    (
        sender_id='$chat_receiver_id'
        AND receiver_id='$admin_sender_id'
    )
    ORDER BY sent_at ASC
    ";

    $chat_result = mysqli_query($conn, $chat_query);

    if($chat_result && mysqli_num_rows($chat_result) > 0){

        while($chat = mysqli_fetch_assoc($chat_result)){

            $class = ($chat['sender_id'] == $admin_sender_id) ? "sent" : "received";
            ?>

            <div class="message <?php echo $class; ?>">
                <p><?php echo nl2br(htmlspecialchars($chat['message'])); ?></p>
                <span><?php echo date("H:i", strtotime($chat['sent_at'])); ?></span>
            </div>

            <?php
        }

    }else{
        echo "<div class='empty-chat-box'>No messages yet. Start the conversation.</div>";
    }

    exit();
}

/* DEFAULT RECEIVER */

$default_receiver_id = 0;

if($admin_role == "support_admin"){

    $default_query = "
    SELECT admin_id
    FROM admin_users
    WHERE role='super_admin'
    AND admin_id != '$admin_id'
    ORDER BY admin_id ASC
    LIMIT 1
    ";

}else{

    $default_query = "
    SELECT admin_id
    FROM admin_users
    WHERE role='support_admin'
    AND admin_id != '$admin_id'
    ORDER BY admin_id ASC
    LIMIT 1
    ";

}

$default_result = mysqli_query($conn, $default_query);

if($default_result && mysqli_num_rows($default_result) > 0){
    $default_admin = mysqli_fetch_assoc($default_result);
    $default_receiver_id = 0 - intval($default_admin['admin_id']);
}

if(isset($_GET['admin'])){
    $receiver_id = 0 - abs(intval($_GET['admin']));
}elseif(isset($_GET['user'])){
    $receiver_id = intval($_GET['user']);
}else{
    $receiver_id = $default_receiver_id;
}

/* RECEIVER DETAILS */

$receiver_exists = false;
$receiver_name = "Select a chat";
$receiver_image = "images/default-profile.png";
$receiver_status = "Search for a user or admin.";

if($receiver_id < 0){

    $receiver_admin_id = abs($receiver_id);

    $receiver_query = "
    SELECT *
    FROM admin_users
    WHERE admin_id='$receiver_admin_id'
    AND admin_id != '$admin_id'
    LIMIT 1
    ";

    $receiver_result = mysqli_query($conn, $receiver_query);

    if($receiver_result && mysqli_num_rows($receiver_result) > 0){

        $receiver = mysqli_fetch_assoc($receiver_result);

        $receiver_exists = true;
        $receiver_name = $receiver['full_name'];
        $receiver_image = "images/assistant.png";
        $receiver_status = "Admin - " . ucwords(str_replace("_", " ", $receiver['role']));

    }else{
        $receiver_id = 0;
    }

}elseif($receiver_id > 0){

    $receiver_query = "
    SELECT *
    FROM users
    WHERE user_id='$receiver_id'
    LIMIT 1
    ";

    $receiver_result = mysqli_query($conn, $receiver_query);

    if($receiver_result && mysqli_num_rows($receiver_result) > 0){

        $receiver = mysqli_fetch_assoc($receiver_result);

        $receiver_exists = true;
        $receiver_name = $receiver['full_name'];
        $receiver_status = "StreetMarket User";

        if(!empty($receiver['profile_image'])){
            $receiver_image = "uploads/" . $receiver['profile_image'];
        }

    }else{
        $receiver_id = 0;
    }

}

/* SEARCH RESULTS */

$search_users_result = false;
$search_admins_result = false;

if($search != ""){

    $search_users_result = mysqli_query($conn, "
    SELECT user_id, full_name, profile_image
    FROM users
    WHERE full_name LIKE '%$search%'
    ORDER BY full_name ASC
    ");

    $search_admins_result = mysqli_query($conn, "
    SELECT admin_id, full_name, role
    FROM admin_users
    WHERE full_name LIKE '%$search%'
    AND admin_id != '$admin_id'
    ORDER BY full_name ASC
    ");

}

/* DEFAULT ADMINS */

$default_admins_result = mysqli_query($conn, "
SELECT admin_id, full_name, role
FROM admin_users
WHERE admin_id != '$admin_id'
AND role IN ('super_admin', 'support_admin')
ORDER BY
CASE
WHEN role='super_admin' THEN 1
WHEN role='support_admin' THEN 2
ELSE 3
END,
admin_id ASC
");

/* RECENT ADMIN CHATS */

$admin_conversations_result = mysqli_query($conn, "
SELECT
a.admin_id,
a.full_name,
a.role,
MAX(m.sent_at) AS last_time,

(
SELECT m2.message
FROM messages m2
WHERE
(m2.sender_id='$admin_sender_id' AND m2.receiver_id=(0 - a.admin_id))
OR
(m2.sender_id=(0 - a.admin_id) AND m2.receiver_id='$admin_sender_id')
ORDER BY m2.sent_at DESC
LIMIT 1
) AS last_message,

(
SELECT COUNT(*)
FROM messages m3
WHERE m3.sender_id=(0 - a.admin_id)
AND m3.receiver_id='$admin_sender_id'
AND m3.is_read='No'
) AS unread_count

FROM messages m
INNER JOIN admin_users a
ON (0 - a.admin_id) =
CASE
WHEN m.sender_id='$admin_sender_id' THEN m.receiver_id
ELSE m.sender_id
END

WHERE
a.admin_id != '$admin_id'
AND m.sender_id < 0
AND m.receiver_id < 0
AND
(
m.sender_id='$admin_sender_id'
OR m.receiver_id='$admin_sender_id'
)

GROUP BY a.admin_id, a.full_name, a.role
ORDER BY last_time DESC
");

/* RECENT USER CHATS */

$user_conversations_result = mysqli_query($conn, "
SELECT
u.user_id,
u.full_name,
u.profile_image,
MAX(m.sent_at) AS last_time,

(
SELECT m2.message
FROM messages m2
WHERE
(m2.sender_id='$admin_sender_id' AND m2.receiver_id=u.user_id)
OR
(m2.sender_id=u.user_id AND m2.receiver_id='$admin_sender_id')
ORDER BY m2.sent_at DESC
LIMIT 1
) AS last_message,

(
SELECT COUNT(*)
FROM messages m3
WHERE m3.sender_id=u.user_id
AND m3.receiver_id='$admin_sender_id'
AND m3.is_read='No'
) AS unread_count

FROM messages m
INNER JOIN users u
ON u.user_id =
CASE
WHEN m.sender_id='$admin_sender_id' THEN m.receiver_id
ELSE m.sender_id
END

WHERE
(
m.sender_id='$admin_sender_id'
OR m.receiver_id='$admin_sender_id'
)
AND
(
m.sender_id > 0
OR m.receiver_id > 0
)

GROUP BY u.user_id, u.full_name, u.profile_image
ORDER BY last_time DESC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Messages | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

*{box-sizing:border-box;}

html,
body{
height:100%;
margin:0;
padding:0;
font-family:Arial, sans-serif;
background:#d9dbd5;
overflow:hidden;
}

.chat-page{
height:100dvh;
width:100%;
display:flex;
align-items:center;
justify-content:center;
padding:16px;
}

.chat-shell{
width:100%;
max-width:1400px;
height:calc(100dvh - 32px);
background:white;
display:grid;
grid-template-columns:370px minmax(0, 1fr);
border-radius:16px;
overflow:hidden;
box-shadow:0 5px 25px rgba(0,0,0,0.18);
}

.chat-sidebar{
height:100%;
min-height:0;
display:flex;
flex-direction:column;
background:#fff;
border-right:1px solid #ddd;
}

.sidebar-header{
height:68px;
flex-shrink:0;
display:flex;
align-items:center;
justify-content:space-between;
padding:0 18px;
background:#f0f2f5;
border-bottom:1px solid #ddd;
}

.sidebar-header h2{
margin:0;
font-size:20px;
}

.sidebar-header a{
text-decoration:none;
color:#111;
font-size:14px;
font-weight:bold;
}

.search-box{
flex-shrink:0;
padding:12px;
background:#fff;
border-bottom:1px solid #eee;
}

.search-box form{
display:flex;
gap:8px;
}

.search-box input{
width:100%;
padding:12px 14px;
border:none;
outline:none;
background:#f0f2f5;
border-radius:10px;
font-size:14px;
}

.search-box button{
padding:12px 14px;
border:none;
background:#111;
color:#fff;
border-radius:10px;
font-weight:bold;
cursor:pointer;
}

.chat-list{
flex:1;
min-height:0;
overflow-y:auto;
}

.chat-label{
padding:10px 16px;
background:#f8f8f8;
color:#667781;
font-size:13px;
font-weight:bold;
}

.chat-person{
display:flex;
align-items:center;
gap:12px;
padding:14px 16px;
text-decoration:none;
color:#111;
border-bottom:1px solid #f0f0f0;
}

.chat-person:hover{
background:#f5f6f6;
}

.chat-person.active-chat{
background:#e9edef;
}

.chat-person img{
width:50px;
height:50px;
border-radius:50%;
object-fit:cover;
background:#ddd;
flex-shrink:0;
}

.chat-info{
min-width:0;
flex:1;
}

.chat-info strong{
display:block;
font-size:15px;
margin-bottom:5px;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.chat-info small{
display:block;
font-size:13px;
color:#667781;
white-space:nowrap;
overflow:hidden;
text-overflow:ellipsis;
}

.unread-count{
background:#2563eb;
color:#ffffff;
font-size:12px;
font-weight:700;
min-width:22px;
height:22px;
display:flex;
align-items:center;
justify-content:center;
padding:0 7px;
border-radius:999px;
margin-left:auto;
box-shadow:0 2px 6px rgba(37,99,235,0.25);
flex-shrink:0;
}

.no-result{
padding:16px;
color:#667781;
font-size:14px;
}

.chat-main{
height:100%;
min-height:0;
display:flex;
flex-direction:column;
background:#efeae2;
}

.chat-header{
height:68px;
flex-shrink:0;
display:flex;
align-items:center;
gap:12px;
padding:0 18px;
background:#f0f2f5;
border-bottom:1px solid #ddd;
}

.chat-header img{
width:48px;
height:48px;
border-radius:50%;
object-fit:cover;
background:#ddd;
flex-shrink:0;
}

.chat-header h3{
margin:0;
font-size:17px;
}

.chat-header p{
margin:4px 0 0;
font-size:13px;
color:#667781;
}

.chat-messages{
flex:1;
min-height:0;
overflow-y:auto;
padding:24px;
background:#efeae2;
scroll-behavior:smooth;
}

.chat-form{
height:auto;
min-height:70px;
flex-shrink:0;
display:flex;
align-items:center;
gap:10px;
padding:12px 16px;
background:#f0f2f5;
border-top:1px solid #ddd;
}

.chat-form input[type="text"]{
flex:1;
min-width:0;
padding:14px 16px;
border:none;
outline:none;
border-radius:24px;
font-size:15px;
background:white;
}

.chat-form button{
width:48px;
height:48px;
border:none;
border-radius:50%;
background:#2563eb;
color:white;
font-size:18px;
cursor:pointer;
font-weight:bold;
flex-shrink:0;
}

.message{
max-width:65%;
padding:9px 12px;
border-radius:8px;
margin-bottom:10px;
font-size:15px;
line-height:1.5;
word-wrap:break-word;
clear:both;
}

.message p{margin:0;}

.message span{
display:block;
text-align:right;
margin-top:4px;
font-size:11px;
color:#667781;
}

.sent{
background:#dbeafe;
margin-left:auto;
border-top-right-radius:0;
}

.received{
background:white;
margin-right:auto;
border-top-left-radius:0;
}

.empty-chat-box{
text-align:center;
color:#667781;
font-size:16px;
margin-top:40px;
}

@media(max-width:768px){

.chat-page{padding:0;}

.chat-shell{
height:100dvh;
border-radius:0;
display:flex;
flex-direction:column;
}

.chat-sidebar{
height:38dvh;
border-right:none;
border-bottom:1px solid #ddd;
}

.chat-main{
height:62dvh;
}

.message{
max-width:88%;
}

}

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

<input
type="text"
name="search"
placeholder="Search user or admin"
value="<?php echo htmlspecialchars($search); ?>">

<button type="submit">Search</button>

</form>
</div>

<div class="chat-list">

<div class="chat-label">Default Admin Chats</div>

<?php if($default_admins_result && mysqli_num_rows($default_admins_result) > 0){ ?>

<?php while($default_admin = mysqli_fetch_assoc($default_admins_result)){ ?>

<?php
$default_chat_id = 0 - intval($default_admin['admin_id']);

$unread_default_result = mysqli_query($conn, "
SELECT COUNT(*) AS total
FROM messages
WHERE sender_id='$default_chat_id'
AND receiver_id='$admin_sender_id'
AND is_read='No'
");

$unread_default_data = mysqli_fetch_assoc($unread_default_result);
$unread_default = intval($unread_default_data['total']);
?>

<a href="admin-messages.php?admin=<?php echo intval($default_admin['admin_id']); ?>"
class="chat-person <?php if($receiver_id == $default_chat_id){ echo 'active-chat'; } ?>">

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

<?php if($search_admins_result && mysqli_num_rows($search_admins_result) > 0){ ?>

<?php while($search_admin = mysqli_fetch_assoc($search_admins_result)){ ?>

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

<?php if($search_users_result && mysqli_num_rows($search_users_result) > 0){ ?>

<?php while($search_user = mysqli_fetch_assoc($search_users_result)){ ?>

<?php
$user_img = "images/default-profile.png";

if(!empty($search_user['profile_image'])){
    $user_img = "uploads/" . $search_user['profile_image'];
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

<?php if($admin_conversations_result && mysqli_num_rows($admin_conversations_result) > 0){ ?>

<?php while($conversation = mysqli_fetch_assoc($admin_conversations_result)){ ?>

<?php $conversation_id = 0 - intval($conversation['admin_id']); ?>

<a href="admin-messages.php?admin=<?php echo intval($conversation['admin_id']); ?>"
class="chat-person <?php if($receiver_id == $conversation_id){ echo 'active-chat'; } ?>">

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

<?php if($user_conversations_result && mysqli_num_rows($user_conversations_result) > 0){ ?>

<?php while($conversation = mysqli_fetch_assoc($user_conversations_result)){ ?>

<?php
$user_img = "images/default-profile.png";

if(!empty($conversation['profile_image'])){
    $user_img = "uploads/" . $conversation['profile_image'];
}
?>

<a href="admin-messages.php?user=<?php echo intval($conversation['user_id']); ?>"
class="chat-person <?php if($receiver_id == $conversation['user_id']){ echo 'active-chat'; } ?>">

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
<p><?php echo htmlspecialchars($receiver_status); ?></p>
</div>

</div>

<div class="chat-messages" id="chatMessages"></div>

<form class="chat-form" id="chatForm">

<input type="hidden" id="receiver_id" value="<?php echo intval($receiver_id); ?>">

<input
type="text"
id="messageInput"
placeholder="<?php echo $receiver_exists ? 'Type a message' : 'Select a chat first'; ?>"
autocomplete="off"
<?php if(!$receiver_exists){ echo "disabled"; } ?>>

<button type="submit" <?php if(!$receiver_exists){ echo "disabled"; } ?>>
➤
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
var lastHTML = "";

function scrollToBottom(){
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function loadMessages(){

    fetch("admin-messages.php?ajax_action=fetch_messages&chat=" + encodeURIComponent(receiverId))
    .then(function(response){
        return response.text();
    })
    .then(function(data){

        if(data !== lastHTML){

            var wasNearBottom =
            chatMessages.scrollTop +
            chatMessages.clientHeight >=
            chatMessages.scrollHeight - 150;

            chatMessages.innerHTML = data;
            lastHTML = data;

            if(wasNearBottom || lastHTML === ""){
                scrollToBottom();
            }

        }

    });

}

chatForm.addEventListener("submit", function(event){

    event.preventDefault();

    var message = messageInput.value.trim();

    if(receiverId == 0){
        alert("Please select a chat first.");
        return;
    }

    if(message == ""){
        return;
    }

    var formData = new FormData();

    formData.append("ajax_action", "send_message");
    formData.append("receiver_id", receiverId);
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

            alert("Message not sent: " + result);

        }

    })
    .catch(function(error){

        alert("Message not sent: " + error);

    });

});

loadMessages();

setTimeout(function(){
    scrollToBottom();
}, 300);

setInterval(loadMessages, 4000);

</script>

</body>

</html>