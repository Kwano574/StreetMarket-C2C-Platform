<?php

session_start();

include("includes/db.php");

if(file_exists("includes/notification-functions.php")){
    include("includes/notification-functions.php");
}

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$product_id = isset($_GET['product']) ? intval($_GET['product']) : 0;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : "";

/* SUPPORT ADMIN DEFAULT CHAT */

$support_admin_id = 0;
$support_admin_name = "Platform Support";

$support_query = "
SELECT admin_id, full_name
FROM admin_users
WHERE role='support_admin'
ORDER BY admin_id ASC
LIMIT 1
";

$support_result = mysqli_query($conn, $support_query);

if($support_result && mysqli_num_rows($support_result) > 0){
    $support_admin = mysqli_fetch_assoc($support_result);
    $support_admin_id = intval($support_admin['admin_id']);
    $support_admin_name = $support_admin['full_name'];
}

$support_receiver_id = 0;

if($support_admin_id > 0){
    $support_receiver_id = 0 - $support_admin_id;
}

if(isset($_GET['user'])){
    $receiver_id = intval($_GET['user']);
}else{
    $receiver_id = $support_receiver_id;
}

/* RECEIVER DETAILS */

$receiver_exists = false;
$receiver_name = "Select a chat";
$receiver_image = "images/default-profile.png";
$receiver_status = "Search for a user or open a recent conversation.";

if($receiver_id < 0){

    $admin_id = abs($receiver_id);

    $admin_query = "
    SELECT admin_id, full_name, role
    FROM admin_users
    WHERE admin_id='$admin_id'
    AND role='support_admin'
    LIMIT 1
    ";

    $admin_result = mysqli_query($conn, $admin_query);

    if($admin_result && mysqli_num_rows($admin_result) > 0){

        $admin = mysqli_fetch_assoc($admin_result);

        $receiver_exists = true;
        $receiver_name = $admin['full_name'];
        $receiver_image = "images/assistant.png";
        $receiver_status = "StreetMarket Support Admin";

    }else{
        $receiver_id = 0;
    }

}elseif($receiver_id > 0){

    $receiver_query = "
    SELECT *
    FROM users
    WHERE user_id='$receiver_id'
    AND user_id != '$user_id'
    LIMIT 1
    ";

    $receiver_result = mysqli_query($conn, $receiver_query);

    if($receiver_result && mysqli_num_rows($receiver_result) > 0){

        $receiver = mysqli_fetch_assoc($receiver_result);

        if(!isset($receiver['status']) || strtolower($receiver['status']) == "active"){

            $receiver_exists = true;
            $receiver_name = $receiver['full_name'];
            $receiver_status = "StreetMarket User";

            if(!empty($receiver['profile_image'])){
                $receiver_image = "uploads/" . $receiver['profile_image'];
            }

        }else{
            $receiver_id = 0;
        }

    }else{
        $receiver_id = 0;
    }

}

/* SEND MESSAGE AJAX */

if(isset($_POST['ajax_action']) && $_POST['ajax_action'] == "send_message"){

    $receiver_id = intval($_POST['receiver_id']);
    $product_id = intval($_POST['product_id']);
    $message_text = mysqli_real_escape_string($conn, trim($_POST['message']));

    if($message_text == ""){
        echo "Message cannot be empty.";
        exit();
    }

    if($receiver_id == 0){
        echo "Please select a valid chat.";
        exit();
    }

    $sender_query = "
    SELECT full_name
    FROM users
    WHERE user_id='$user_id'
    LIMIT 1
    ";

    $sender_result = mysqli_query($conn, $sender_query);
    $sender_data = mysqli_fetch_assoc($sender_result);
    $sender_name = $sender_data ? $sender_data['full_name'] : "A user";

    if($receiver_id > 0){

        $check_user = "
        SELECT user_id
        FROM users
        WHERE user_id='$receiver_id'
        AND user_id != '$user_id'
        LIMIT 1
        ";

        $check_user_result = mysqli_query($conn, $check_user);

        if(!$check_user_result || mysqli_num_rows($check_user_result) == 0){
            echo "Receiver user not found.";
            exit();
        }

    }

    if($receiver_id < 0){

        $admin_id = abs($receiver_id);

        $check_admin = "
        SELECT admin_id
        FROM admin_users
        WHERE admin_id='$admin_id'
        AND role='support_admin'
        LIMIT 1
        ";

        $check_admin_result = mysqli_query($conn, $check_admin);

        if(!$check_admin_result || mysqli_num_rows($check_admin_result) == 0){
            echo "Support admin not found.";
            exit();
        }

    }

    if($product_id < 0){
        $product_id = 0;
    }

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
        '$user_id',
        '$receiver_id',
        '$message_text',
        NOW(),
        '$product_id',
        'No'
    )
    ";

    if(mysqli_query($conn, $insert_message)){

        if($receiver_id > 0 && function_exists("createNotification")){

            $chat_link = "messages.php?user=".$user_id;

            if($product_id > 0){
                $chat_link .= "&product=".$product_id;
            }

            createNotification(
                $conn,
                $receiver_id,
                "New Message",
                $sender_name." sent you a new message.",
                $chat_link
            );

        }

        echo "success";

    }else{
        echo "Message could not be sent: " . mysqli_error($conn);
    }

    exit();
}

/* FETCH MESSAGES AJAX */

if(isset($_GET['ajax_action']) && $_GET['ajax_action'] == "fetch_messages"){

    $receiver_id = intval($_GET['user']);
    $product_id = intval($_GET['product']);

    if($receiver_id == 0){

        echo "
        <div class='empty-chat-box'>
            Select a chat to start messaging.
        </div>
        ";

        exit();
    }

    mysqli_query($conn, "
    UPDATE messages
    SET is_read='Yes', read_at=NOW()
    WHERE sender_id='$receiver_id'
    AND receiver_id='$user_id'
    AND is_read='No'
    ");

    if($product_id > 0){

        $chat_query = "
        SELECT *
        FROM messages
        WHERE
        (
            (sender_id='$user_id' AND receiver_id='$receiver_id')
            OR
            (sender_id='$receiver_id' AND receiver_id='$user_id')
        )
        AND product_id='$product_id'
        ORDER BY sent_at ASC
        ";

    }else{

        $chat_query = "
        SELECT *
        FROM messages
        WHERE
        (sender_id='$user_id' AND receiver_id='$receiver_id')
        OR
        (sender_id='$receiver_id' AND receiver_id='$user_id')
        ORDER BY sent_at ASC
        ";

    }

    $chat_result = mysqli_query($conn, $chat_query);

    if($chat_result && mysqli_num_rows($chat_result) > 0){

        while($chat = mysqli_fetch_assoc($chat_result)){

            $class = ($chat['sender_id'] == $user_id) ? "sent" : "received";

?>

<div class="message <?php echo $class; ?>">
<p><?php echo nl2br(htmlspecialchars($chat['message'])); ?></p>
<span><?php echo date("H:i", strtotime($chat['sent_at'])); ?></span>
</div>

<?php

        }

    }else{

?>

<div class="empty-chat-box">
No messages yet. Start the conversation.
</div>

<?php

    }

    exit();
}

/* SEARCH USERS */

$search_users_result = false;

if($search != ""){

    $search_query = "
    SELECT user_id, full_name, profile_image
    FROM users
    WHERE full_name LIKE '%$search%'
    AND user_id != '$user_id'
    AND (status='Active' OR status='active' OR status IS NULL)
    ORDER BY full_name ASC
    ";

    $search_users_result = mysqli_query($conn, $search_query);

}

/* SUPPORT UNREAD COUNT */

$support_unread = 0;

if($support_receiver_id != 0){

    $support_unread_query = "
    SELECT COUNT(*) AS total
    FROM messages
    WHERE sender_id='$support_receiver_id'
    AND receiver_id='$user_id'
    AND is_read='No'
    ";

    $support_unread_result = mysqli_query($conn, $support_unread_query);

    if($support_unread_result){
        $support_unread_data = mysqli_fetch_assoc($support_unread_result);
        $support_unread = intval($support_unread_data['total']);
    }

}

/* RECENT USER CHATS */

$conversations_query = "
SELECT
u.user_id,
u.full_name,
u.profile_image,
MAX(m.sent_at) AS last_time,

(
    SELECT m2.message
    FROM messages m2
    WHERE
    (m2.sender_id='$user_id' AND m2.receiver_id=u.user_id)
    OR
    (m2.sender_id=u.user_id AND m2.receiver_id='$user_id')
    ORDER BY m2.sent_at DESC
    LIMIT 1
) AS last_message,

(
    SELECT COUNT(*)
    FROM messages m3
    WHERE m3.sender_id=u.user_id
    AND m3.receiver_id='$user_id'
    AND m3.is_read='No'
) AS unread_count

FROM messages m

INNER JOIN users u
ON u.user_id =
CASE
WHEN m.sender_id='$user_id' THEN m.receiver_id
ELSE m.sender_id
END

WHERE
u.user_id != '$user_id'
AND m.sender_id > 0
AND m.receiver_id > 0
AND
(
    m.sender_id='$user_id'
    OR m.receiver_id='$user_id'
)

GROUP BY u.user_id, u.full_name, u.profile_image
ORDER BY last_time DESC
";

$conversations_result = mysqli_query($conn, $conversations_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Messages | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

*{
box-sizing:border-box;
}

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
grid-template-columns:360px minmax(0, 1fr);
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

.chat-form button:disabled,
.chat-form input:disabled{
opacity:0.55;
cursor:not-allowed;
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

.message p{
margin:0;
}

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

@media(max-width:1024px){

.chat-page{
padding:10px;
}

.chat-shell{
height:calc(100dvh - 20px);
grid-template-columns:320px minmax(0, 1fr);
border-radius:12px;
}

.message{
max-width:78%;
}

}

@media(max-width:768px){

.chat-page{
padding:0;
}

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
min-height:0;
}

.sidebar-header,
.chat-header{
height:60px;
}

.chat-messages{
padding:16px;
}

.chat-form{
min-height:64px;
padding:10px;
}

.chat-form input[type="text"]{
font-size:14px;
padding:12px 14px;
}

.message{
max-width:88%;
font-size:14px;
}

}

@media(max-width:480px){

.chat-sidebar{
height:34dvh;
}

.chat-main{
height:66dvh;
}

.search-box form{
gap:6px;
}

.search-box button{
padding:10px;
font-size:13px;
}

.chat-person{
padding:12px;
}

.chat-person img{
width:44px;
height:44px;
}

.chat-header img{
width:42px;
height:42px;
}

.chat-form button{
width:44px;
height:44px;
}

}

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

<input
type="text"
name="search"
placeholder="Search user by name"
value="<?php echo htmlspecialchars($search); ?>">

<button type="submit">Search</button>

</form>

</div>

<div class="chat-list">

<div class="chat-label">Support</div>

<?php if($support_admin_id > 0){ ?>

<a href="messages.php?user=<?php echo $support_receiver_id; ?>"
class="chat-person <?php if($receiver_id == $support_receiver_id){ echo 'active-chat'; } ?>">

<img src="images/assistant.png" alt="Support">

<div class="chat-info">

<strong><?php echo htmlspecialchars($support_admin_name); ?></strong>

<small>StreetMarket Support Admin</small>

</div>

<?php if($support_unread > 0){ ?>

<span class="unread-count">
<?php echo $support_unread; ?>
</span>

<?php } ?>

</a>

<?php }else{ ?>

<div class="no-result">No support admin found.</div>

<?php } ?>

<?php if($search != ""){ ?>

<div class="chat-label">Search Results</div>

<?php if($search_users_result && mysqli_num_rows($search_users_result) > 0){ ?>

<?php while($search_user = mysqli_fetch_assoc($search_users_result)){ ?>

<?php

$search_image = "images/default-profile.png";

if(!empty($search_user['profile_image'])){
    $search_image = "uploads/" . $search_user['profile_image'];
}

?>

<a href="messages.php?user=<?php echo $search_user['user_id']; ?>"
class="chat-person <?php if($receiver_id == $search_user['user_id']){ echo 'active-chat'; } ?>">

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

<div class="chat-label">Recent Chats</div>

<?php if($conversations_result && mysqli_num_rows($conversations_result) > 0){ ?>

<?php while($conversation = mysqli_fetch_assoc($conversations_result)){ ?>

<?php

$conversation_image = "images/default-profile.png";

if(!empty($conversation['profile_image'])){
    $conversation_image = "uploads/" . $conversation['profile_image'];
}

?>

<a href="messages.php?user=<?php echo $conversation['user_id']; ?>"
class="chat-person <?php if($receiver_id == $conversation['user_id']){ echo 'active-chat'; } ?>">

<img src="<?php echo htmlspecialchars($conversation_image); ?>" alt="User">

<div class="chat-info">

<strong><?php echo htmlspecialchars($conversation['full_name']); ?></strong>

<small><?php echo htmlspecialchars(substr($conversation['last_message'], 0, 45)); ?></small>

</div>

<?php if(isset($conversation['unread_count']) && $conversation['unread_count'] > 0){ ?>

<span class="unread-count">
<?php echo $conversation['unread_count']; ?>
</span>

<?php } ?>

</a>

<?php } ?>

<?php }else{ ?>

<div class="no-result">
No recent chats yet. Search for a user to start chatting.
</div>

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

<input type="hidden" id="receiver_id" value="<?php echo $receiver_id; ?>">

<input type="hidden" id="product_id" value="<?php echo $product_id; ?>">

<input
type="text"
id="messageInput"
placeholder="<?php echo $receiver_exists ? 'Type a message' : 'Select a user first'; ?>"
autocomplete="off"
<?php if(!$receiver_exists){ echo "disabled"; } ?>
required>

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
var productId = document.getElementById("product_id").value;

var lastHTML = "";

function scrollToBottom(){
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function loadMessages(){

    fetch(
        "messages.php?ajax_action=fetch_messages&user=" +
        receiverId +
        "&product=" +
        productId
    )

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

if(chatForm){

    chatForm.addEventListener("submit", function(event){

        event.preventDefault();

        if(receiverId == 0){
            alert("Please select a chat first.");
            return;
        }

        var message = messageInput.value.trim();

        if(message == ""){
            return;
        }

        var formData = new FormData();

        formData.append("ajax_action", "send_message");
        formData.append("receiver_id", receiverId);
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
                alert(result);
            }

        });

    });

}

loadMessages();

setTimeout(function(){
    scrollToBottom();
}, 300);

setInterval(function(){
    loadMessages();
}, 4000);

</script>

</body>

</html>