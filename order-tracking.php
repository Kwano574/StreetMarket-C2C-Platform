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
$message = "";
$message_type = "success";

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

if(!isset($_GET['id'])){
    header("Location: orders.php");
    exit();
}

$reference_order_id = intval($_GET['id']);

/* GET REFERENCE ORDER */

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
$order_time = mysqli_real_escape_string($conn, $reference_order['order_date']);

/* GET ORDER GROUP */

$group_query = "
SELECT
MIN(orders.order_id) AS reference_order_id,
orders.buyer_id,
orders.seller_id,
orders.order_date,
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
AND orders.order_date='$order_time'
AND orders.status!='cancelled'
GROUP BY
orders.buyer_id,
orders.seller_id,
orders.order_date,
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

/* SELLER UPDATE FULL ORDER GROUP STATUS */

if(isset($_POST['update_status'])){

    if($user_id == $seller_id){

        $new_status = mysqli_real_escape_string($conn, $_POST['delivery_status']);
        $estimated_time = mysqli_real_escape_string($conn, trim($_POST['estimated_time']));

        $allowed_status = [
            "processing",
            "packed",
            "ready_for_pickup",
            "shipped",
            "out_for_delivery",
            "delivered"
        ];

        if(in_array($new_status, $allowed_status)){

            $update_query = "
            UPDATE orders
            SET delivery_status='$new_status',
            estimated_time='$estimated_time'
            WHERE buyer_id='$buyer_id'
            AND seller_id='$seller_id'
            AND order_date='$order_time'
            AND status!='cancelled'
            ";

            if(mysqli_query($conn, $update_query)){

                if(function_exists("createNotification")){
                    createNotification(
                        $conn,
                        $buyer_id,
                        "Order Group Updated",
                        "Your order group #".$reference_order_id." is now ".ucwords(str_replace("_", " ", $new_status)).".",
                        "order-tracking.php?id=".$reference_order_id
                    );
                }

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

/* BUYER CONFIRM FULL ORDER GROUP */

if(isset($_POST['confirm_delivery'])){

    if($user_id == $buyer_id){

        $confirm_query = "
        UPDATE orders
        SET
        delivery_status='delivered',
        status='completed',
        buyer_confirmed='Yes'
        WHERE buyer_id='$buyer_id'
        AND seller_id='$seller_id'
        AND order_date='$order_time'
        AND status!='cancelled'
        ";

        if(mysqli_query($conn, $confirm_query)){

            if(function_exists("createNotification")){
                createNotification(
                    $conn,
                    $seller_id,
                    "Order Group Completed",
                    "The buyer confirmed completion for order group #".$reference_order_id.".",
                    "manage-deliveries.php"
                );
            }

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

/* SUBMIT REPORT FOR ORDER GROUP */

if(isset($_POST['submit_report'])){

    $group_result = mysqli_query($conn, $group_query);
    $order_group = mysqli_fetch_assoc($group_result);

    if(
        $user_id == $buyer_id &&
        $order_group['status'] == "completed" &&
        $order_group['buyer_confirmed'] == "Yes"
    ){

        $check_report = mysqli_query($conn, "
        SELECT report_id
        FROM reports
        WHERE order_id='$reference_order_id'
        AND user_id='$user_id'
        LIMIT 1
        ");

        if($check_report && mysqli_num_rows($check_report) == 0){

            $reason = mysqli_real_escape_string($conn, $_POST['report_reason']);
            $details = mysqli_real_escape_string($conn, trim($_POST['report_details']));
            $reported_user = mysqli_real_escape_string($conn, $order_group['seller_business_name']);

            $insert_report = "
            INSERT INTO reports(
                user_id,
                reported_user,
                report_reason,
                report_details,
                report_status,
                order_id,
                product_id,
                seller_id
            )
            VALUES(
                '$user_id',
                '$reported_user',
                '$reason',
                '$details',
                'pending',
                '$reference_order_id',
                NULL,
                '$seller_id'
            )
            ";

            if(mysqli_query($conn, $insert_report)){

                if(function_exists("createNotification")){
                    createNotification(
                        $conn,
                        $seller_id,
                        "New Report Submitted",
                        "A buyer submitted a report related to order group #".$reference_order_id.".",
                        "notifications.php"
                    );
                }

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

/* REFRESH GROUP */

$group_result = mysqli_query($conn, $group_query);
$order_group = mysqli_fetch_assoc($group_result);

$delivery_method = !empty($order_group['delivery_method']) ? $order_group['delivery_method'] : "delivery";
$current_status = $order_group['delivery_status'];

if($delivery_method == "pickup"){
    $status_steps = ["processing", "packed", "ready_for_pickup", "delivered"];
}else{
    $status_steps = ["processing", "packed", "shipped", "out_for_delivery", "delivered"];
}

/* GET GROUP ITEMS */

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
AND orders.order_date='$order_time'
AND orders.status!='cancelled'
ORDER BY orders.order_id ASC
";

$items_result = mysqli_query($conn, $items_query);

/* CHECK REPORT */

$already_reported = false;

$check_report = mysqli_query($conn, "
SELECT report_id
FROM reports
WHERE order_id='$reference_order_id'
AND user_id='$user_id'
LIMIT 1
");

if($check_report && mysqli_num_rows($check_report) > 0){
    $already_reported = true;
}

/* BUYER ADDRESS */

$buyer_address_display = "";

if(!empty($order_group['delivery_address']) && $order_group['delivery_address'] != "Pickup selected"){
    $buyer_address_display = $order_group['delivery_address'];
}else{
    $buyer_address_display = trim($order_group['buyer_address']);
}

if($buyer_address_display == ""){
    $buyer_address_display = "No physical address provided.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Track Order Group | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.tracking-container{max-width:1000px;margin:auto;}
.tracking-card{background:white;border-radius:18px;padding:30px;box-shadow:0 2px 15px rgba(0,0,0,0.08);}
.order-header{display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap;border-bottom:1px solid #eee;padding-bottom:20px;margin-bottom:25px;}
.order-item{display:flex;gap:15px;align-items:center;background:#f8f8f8;padding:14px;border-radius:12px;margin-bottom:12px;}
.order-item img{width:90px;height:90px;object-fit:cover;border-radius:10px;}
.timeline{margin-top:40px;}
.timeline-step{display:flex;align-items:center;margin-bottom:30px;position:relative;}
.timeline-circle{width:45px;height:45px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;margin-right:20px;background:#ddd;color:#555;}
.timeline-active{background:#111;color:white;}
.timeline-line{position:absolute;left:22px;top:45px;width:2px;height:40px;background:#ddd;}
.status-update-box{margin-top:40px;background:#f8f8f8;padding:25px;border-radius:14px;}
.status-update-box select,
.status-update-box input,
.status-update-box textarea{width:100%;padding:14px;border-radius:10px;border:1px solid #ccc;margin-top:10px;margin-bottom:20px;}
.status-update-box textarea{min-height:120px;resize:vertical;}
.status-update-box button{padding:14px 22px;border:none;background:#111;color:white;border-radius:10px;font-weight:bold;cursor:pointer;}
.confirm-btn{background:#16a34a !important;}
.report-btn{background:#c62828 !important;}
.review-btn{background:#2563eb !important;margin-top:10px;}
.status-badge{display:inline-block;padding:10px 18px;border-radius:50px;background:#111;color:white;font-size:14px;font-weight:bold;margin-top:10px;}
.estimate-box{background:#eff6ff;color:#1e3a8a;border-left:5px solid #2563eb;padding:15px;border-radius:10px;margin-top:15px;}
.success-message{background:#dcfce7;color:#166534;border-left:5px solid #16a34a;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold;}
.error-message{background:#fee2e2;color:#991b1b;border-left:5px solid #dc2626;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold;}
.reported-box,.reviewed-box{background:#eff6ff;color:#1e3a8a;border-left:5px solid #2563eb;padding:15px;border-radius:10px;margin-top:15px;}
.group-details{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;margin-top:15px;}
.detail-box{background:#f8fafc;padding:12px;border-radius:10px;border:1px solid #e2e8f0;}
.address-box{grid-column:1 / -1;}
@media(max-width:768px){.order-item{flex-direction:column;align-items:flex-start;}.order-item img{width:100%;height:auto;}}

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
<h2>Order Group #<?php echo $reference_order_id; ?></h2>
<p>Seller: <b><?php echo htmlspecialchars(!empty($order_group['seller_business_name']) ? $order_group['seller_business_name'] : $order_group['seller_name']); ?></b></p>
<p>Buyer: <b><?php echo htmlspecialchars($order_group['buyer_name']); ?></b></p>
<p>Ordered On: <?php echo date("d M Y H:i", strtotime($order_group['order_date'])); ?></p>
</div>

<div>
<div class="status-badge">
<?php echo ucwords(str_replace("_", " ", $current_status)); ?>
</div>
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
<p>Quantity: <?php echo intval($item['quantity']); ?></p>
<p>Unit Price: R<?php echo number_format($item['price'], 2); ?></p>
<p>Line Total: R<?php echo number_format($item['total_amount'], 2); ?></p>

<?php if($user_id == $buyer_id && $order_group['status'] == "completed" && $order_group['buyer_confirmed'] == "Yes"){ ?>

<?php
$product_id = intval($item['product_id']);

$review_check = mysqli_query($conn, "
SELECT review_id
FROM product_reviews
WHERE order_id='".intval($item['order_id'])."'
AND user_id='$user_id'
AND product_id='$product_id'
LIMIT 1
");

$reviewed_item = ($review_check && mysqli_num_rows($review_check) > 0);
?>

<?php if($reviewed_item){ ?>

<div class="reviewed-box">
You already reviewed this product.
</div>

<?php }else{ ?>

<a href="review-product.php?order_id=<?php echo intval($item['order_id']); ?>&product_id=<?php echo $product_id; ?>">
<button type="button" class="review-btn">
Review This Product
</button>
</a>

<?php } ?>

<?php } ?>

</div>

</div>

<?php } ?>

<?php } ?>

<div class="group-details">

<div class="detail-box">
<p>Method:</p>
<b><?php echo ucwords($delivery_method); ?></b>
</div>

<div class="detail-box">
<p>Payment:</p>
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

<?php if($user_id == $buyer_id && $current_status == "delivered" && $order_group['buyer_confirmed'] == "No"){ ?>

<div class="status-update-box">

<h3>Confirm Full Order Group</h3>

<p>Please confirm once you have received or collected all products in this order group.</p>

<form method="POST">
<button type="submit" name="confirm_delivery" class="confirm-btn">
Confirm Order Group Received
</button>
</form>

</div>

<?php } ?>

<?php if($user_id == $buyer_id && $order_group['status'] == "completed" && $order_group['buyer_confirmed'] == "Yes"){ ?>

<div class="status-update-box">

<h3>Report Seller or Order Group</h3>

<?php if($already_reported){ ?>

<div class="reported-box">
You have already submitted a report for this order group.
</div>

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

<button type="submit" name="submit_report" class="report-btn">
Submit Report
</button>

</form>

<?php } ?>

</div>

<?php } ?>

</div>

</div>

</section>

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