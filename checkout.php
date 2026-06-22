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

// protecting user session by checking if they are logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$message = "";
$delivery_fee = 25.00;
$tax_percentage = 0.15;
$selected_delivery_method = "delivery";

// getting buyer delivery details
$user_result = mysqli_query($conn, "SELECT * FROM users WHERE user_id='$user_id' LIMIT 1");
$user_data = mysqli_fetch_assoc($user_result);

if(!$user_data){
    header("Location: logout.php");
    exit();
}

$delivery_address = trim($user_data['address'] . ", " . $user_data['province']);

// checking if column exists
function checkoutColumnExists($conn, $table, $column){

    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    if($check && mysqli_num_rows($check) > 0){
        return true;
    }

    return false;
}

// sending notifications safely
function sendCheckoutNotification($conn, $user_id, $notification_title, $notification_message, $link){

    $user_id = intval($user_id);

    if($user_id <= 0){
        return false;
    }

    if(function_exists("createNotification")){
        createNotification($conn, $user_id, $notification_title, $notification_message, $link);
        return true;
    }

    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");

    if(!$table_check || mysqli_num_rows($table_check) == 0){
        return false;
    }

    $title_column = "";

    if(checkoutColumnExists($conn, "notifications", "tittle")){
        $title_column = "tittle";
    }elseif(checkoutColumnExists($conn, "notifications", "title")){
        $title_column = "title";
    }else{
        return false;
    }

    $notification_title = mysqli_real_escape_string($conn, $notification_title);
    $notification_message = mysqli_real_escape_string($conn, $notification_message);
    $link = mysqli_real_escape_string($conn, $link);

    $insert_notification = "
    INSERT INTO notifications(user_id, `$title_column`, message, link, is_read, created_at)
    VALUES('$user_id', '$notification_title', '$notification_message', '$link', 'No', NOW())
    ";

    return mysqli_query($conn, $insert_notification);
}

// checking if product table has delivery_option column
$has_delivery_option_column = checkoutColumnExists($conn, "product", "delivery_option");

// getting cart products with seller and selected size details
if($has_delivery_option_column){

    $cart_query = "
    SELECT
    cart.cart_id,
    cart.quantity AS cart_quantity,
    cart.selected_size,
    product.product_id,
    product.product_name,
    product.available_sizes,
    product.price,
    product.quantity AS stock_quantity,
    product.image,
    product.user_id AS seller_id,
    product.delivery_option,
    users.business_name,
    users.business_bank_name,
    users.business_account_holder,
    users.business_account_number,
    users.business_branch_code
    FROM cart
    INNER JOIN product ON cart.product_id = product.product_id
    INNER JOIN users ON product.user_id = users.user_id
    WHERE cart.user_id='$user_id'
    AND product.status='available'
    AND product.moderation_status='approved'
    AND product.quantity > 0
    ORDER BY users.business_name ASC
    ";

}else{

    $cart_query = "
    SELECT
    cart.cart_id,
    cart.quantity AS cart_quantity,
    cart.selected_size,
    product.product_id,
    product.product_name,
    product.available_sizes,
    product.price,
    product.quantity AS stock_quantity,
    product.image,
    product.user_id AS seller_id,
    'Delivery And Pickup' AS delivery_option,
    users.business_name,
    users.business_bank_name,
    users.business_account_holder,
    users.business_account_number,
    users.business_branch_code
    FROM cart
    INNER JOIN product ON cart.product_id = product.product_id
    INNER JOIN users ON product.user_id = users.user_id
    WHERE cart.user_id='$user_id'
    AND product.status='available'
    AND product.moderation_status='approved'
    AND product.quantity > 0
    ORDER BY users.business_name ASC
    ";

}

$cart_result = mysqli_query($conn, $cart_query);

$grouped_products = [];
$products_subtotal = 0;
$has_pickup_only_item = false;

// grouping cart items by seller
if($cart_result){

    while($row = mysqli_fetch_assoc($cart_result)){

        $seller_id = intval($row['seller_id']);
        $cart_quantity = intval($row['cart_quantity']);
        $stock_quantity = intval($row['stock_quantity']);

        if($cart_quantity < 1){
            $cart_quantity = 1;
        }

        if($cart_quantity > $stock_quantity){
            $cart_quantity = $stock_quantity;
        }

        $delivery_option = trim($row['delivery_option']);

        if($delivery_option == ""){
            $delivery_option = "Delivery And Pickup";
        }

        if(
            strtolower($delivery_option) == "collection only" ||
            strtolower($delivery_option) == "pickup only" ||
            strtolower($delivery_option) == "pick up only"
        ){
            $delivery_option = "Collection Only";
            $has_pickup_only_item = true;
        }

        $line_total = floatval($row['price']) * $cart_quantity;

        if(!isset($grouped_products[$seller_id])){

            $grouped_products[$seller_id] = [
                "business_name" => $row['business_name'],
                "business_bank_name" => $row['business_bank_name'],
                "business_account_holder" => $row['business_account_holder'],
                "business_account_number" => $row['business_account_number'],
                "business_branch_code" => $row['business_branch_code'],
                "seller_subtotal" => 0,
                "has_pickup_only" => false,
                "products" => []
            ];

        }

        if($delivery_option == "Collection Only"){
            $grouped_products[$seller_id]["has_pickup_only"] = true;
        }

        $row['delivery_option'] = $delivery_option;
        $row['cart_quantity'] = $cart_quantity;
        $row['line_total'] = $line_total;

        $grouped_products[$seller_id]['products'][] = $row;
        $grouped_products[$seller_id]['seller_subtotal'] += $line_total;
        $products_subtotal += $line_total;
    }
}

// if pickup-only item exists, default checkout to pickup
if($has_pickup_only_item){
    $selected_delivery_method = "pickup";
}

// calculating totals
$total_delivery_fee = ($selected_delivery_method == "delivery") ? $delivery_fee : 0;
$tax_amount = $products_subtotal * $tax_percentage;
$final_total = $products_subtotal + $total_delivery_fee + $tax_amount;

// validating card number with Luhn algorithm
function luhnCheck($number){

    $number = preg_replace('/\D/', '', $number);
    $sum = 0;
    $alternate = false;

    for($i = strlen($number) - 1; $i >= 0; $i--){

        $digit = intval($number[$i]);

        if($alternate){

            $digit *= 2;

            if($digit > 9){
                $digit -= 9;
            }
        }

        $sum += $digit;
        $alternate = !$alternate;
    }

    return ($sum % 10 == 0);
}

// checking card brand
function getCardBrand($card_number){

    if(preg_match("/^4[0-9]{12,18}$/", $card_number)){
        return "Visa";
    }

    if(preg_match("/^5[1-5][0-9]{14}$/", $card_number)){
        return "Mastercard";
    }

    return "Unknown";
}

// validating sandbox card details
function validateSandboxCard($card_number, $expiry_date, $cvv){

    $card_number = preg_replace('/\D/', '', $card_number);

   $sandbox_failures = [
    "4000000000000002" => "Card was declined.",
    "4000000000009995" => "Card has insufficient funds.",
    "4000000000009987" => "Card was reported lost.",
    "4000000000009979" => "Card was reported stolen.",
    "4000000000000069" => "Card has expired.",
    "4000000000000127" => "Incorrect CVC.",
    "4000000000000119" => "Processing error. Please try again.",
    "4242424242424241" => "Incorrect card number."
];

    if(isset($sandbox_failures[$card_number])){
        return $sandbox_failures[$card_number];
    }

    $successful_cards = [
        "4242424242424242",
        "5555555555554444"
    ];

    if(!in_array($card_number, $successful_cards)){
        return "Use a valid sandbox test card such as 4242 4242 4242 4242 or 5555 5555 5555 4444.";
    }

    if(!preg_match("/^[0-9]{13,19}$/", $card_number)){
        return "Card number must contain between 13 and 19 digits.";
    }

    if(!luhnCheck($card_number)){
        return "Invalid card number.";
    }

    if(getCardBrand($card_number) == "Unknown"){
        return "Invalid card issuer.";
    }

    if(!preg_match("/^(0[1-9]|1[0-2])\/[0-9]{2}$/", $expiry_date)){
        return "Expiry date must be in MM/YY format.";
    }

    $parts = explode("/", $expiry_date);
    $month = intval($parts[0]);
    $year = intval("20" . $parts[1]);

    if($year < intval(date("Y")) || ($year == intval(date("Y")) && $month < intval(date("m")))){
        return "Card expiry date cannot be in the past.";
    }

    if(!preg_match("/^[0-9]{3}$/", $cvv)){
        return "CVV must contain exactly 3 digits.";
    }

    return "";
}

// processing checkout details when user clicks confirm order
if(isset($_POST['confirm_order'])){

    $payment_method = mysqli_real_escape_string($conn, trim($_POST['payment_method']));
    $selected_delivery_method = mysqli_real_escape_string($conn, trim($_POST['delivery_method']));

    if($selected_delivery_method != "delivery" && $selected_delivery_method != "pickup"){
        $message = "Please select a valid delivery option.";
    }

    if($message == "" && $has_pickup_only_item && $selected_delivery_method == "delivery"){
        $message = "This order contains pickup-only products. Please select Pick Up to continue.";
    }

    $total_delivery_fee = ($selected_delivery_method == "delivery") ? $delivery_fee : 0;
    $tax_amount = $products_subtotal * $tax_percentage;
    $final_total = $products_subtotal + $total_delivery_fee + $tax_amount;

    if(empty($grouped_products)){
        $message = "Your cart is empty.";
    }elseif($selected_delivery_method == "delivery" && (empty($delivery_address) || $delivery_address == ",")){
        $message = "Please update your delivery address before checkout.";
    }elseif($payment_method != "cash" && $payment_method != "card"){
        $message = "Please select a valid payment method.";
    }elseif($payment_method == "cash" && $final_total > 1000){
        $message = "Cash payment is not allowed for orders above R1000 for safety reasons. Please use card payment.";
    }

    // checking that each seller has payout details
    if($message == ""){

        foreach($grouped_products as $seller_data){

            if(
                empty($seller_data['business_bank_name']) ||
                empty($seller_data['business_account_holder']) ||
                empty($seller_data['business_account_number']) ||
                empty($seller_data['business_branch_code'])
            ){
                $message = "One seller does not have payout details. Checkout cannot continue.";
                break;
            }
        }
    }

    // checking that size-based products have selected sizes
    if($message == ""){

        foreach($grouped_products as $seller_data){

            foreach($seller_data['products'] as $product){

                if(!empty($product['available_sizes']) && empty($product['selected_size'])){
                    $message = "Please select a size or option for " . $product['product_name'] . " in your cart before checkout.";
                    break 2;
                }
            }
        }
    }

    // validating card payment details
    if($payment_method == "card" && $message == ""){

        $cardholder_name = mysqli_real_escape_string($conn, trim($_POST['cardholder_name']));
        $card_number = preg_replace("/\s+/", "", $_POST['card_number']);
        $expiry_date = trim($_POST['expiry_date']);
        $cvv = trim($_POST['cvv']);

        if(empty($cardholder_name) || empty($card_number) || empty($expiry_date) || empty($cvv)){
            $message = "Please complete all card details.";
        }else{
            $message = validateSandboxCard($card_number, $expiry_date, $cvv);
        }
    }

    if($message == ""){

        mysqli_begin_transaction($conn);

        $order_success = true;
        $seller_totals = [];
        $seller_order_counts = [];
        $order_group_id = "SMG" . date("YmdHis") . rand(100,999);
        $delivery_fee_allocated = false;

        foreach($grouped_products as $seller_id => $seller_data){

            $seller_totals[$seller_id] = floatval($seller_data['seller_subtotal']);
            $seller_order_counts[$seller_id] = 0;

            foreach($seller_data['products'] as $product){

                $product_id = intval($product['product_id']);
                $cart_quantity = intval($product['cart_quantity']);
                $selected_size = mysqli_real_escape_string($conn, trim($product['selected_size']));
                $order_total = floatval($product['line_total']);
                $line_delivery_fee = 0;

                if($selected_delivery_method == "delivery" && !$delivery_fee_allocated){
                    $order_total += $delivery_fee;
                    $line_delivery_fee = $delivery_fee;
                    $delivery_fee_allocated = true;
                }

                // locking stock before order is inserted
                $stock_result = mysqli_query($conn, "
                SELECT quantity
                FROM product
                WHERE product_id='$product_id'
                AND status='available'
                AND moderation_status='approved'
                FOR UPDATE
                ");

                $stock_data = mysqli_fetch_assoc($stock_result);

                if(!$stock_data || intval($stock_data['quantity']) < $cart_quantity){
                    $order_success = false;
                    break;
                }

                $payment_status = ($payment_method == "card") ? "paid" : "pending";
                $order_delivery_address = ($selected_delivery_method == "pickup") ? "Pickup selected" : $delivery_address;

                if($selected_delivery_method == "delivery"){
                    $estimated_time = "Estimated delivery: 45 minutes to 1 hour 30 minutes";
                    $delivery_status = "processing";
                }else{
                    $estimated_time = "Waiting for seller to set pickup readiness time";
                    $delivery_status = "processing";
                }

                // inserting order row
                $insert_order = "
                INSERT INTO orders(
                    order_group_id,
                    buyer_id,
                    seller_id,
                    product_id,
                    quantity,
                    selected_size,
                    delivery_address,
                    total_amount,
                    delivery_fee,
                    payment_method,
                    payment_status,
                    delivery_status,
                    status,
                    delivery_method,
                    estimated_time,
                    buyer_confirmed
                )
                VALUES(
                    '$order_group_id',
                    '$user_id',
                    '$seller_id',
                    '$product_id',
                    '$cart_quantity',
                    '$selected_size',
                    '$order_delivery_address',
                    '$order_total',
                    '$line_delivery_fee',
                    '$payment_method',
                    '$payment_status',
                    '$delivery_status',
                    'pending',
                    '$selected_delivery_method',
                    '$estimated_time',
                    'No'
                )
                ";

                if(!mysqli_query($conn, $insert_order)){
                    $order_success = false;
                    break;
                }

                $seller_order_counts[$seller_id]++;

                $new_quantity = intval($stock_data['quantity']) - $cart_quantity;

                if($new_quantity < 0){
                    $new_quantity = 0;
                }

                $new_status = ($new_quantity <= 0) ? "sold" : "available";

                if(!mysqli_query($conn, "
                    UPDATE product
                    SET quantity='$new_quantity', status='$new_status'
                    WHERE product_id='$product_id'
                ")){
                    $order_success = false;
                    break;
                }
            }

            if(!$order_success){
                break;
            }
        }

        if($order_success){

            // clearing buyer cart after successful order
            mysqli_query($conn, "DELETE FROM cart WHERE user_id='$user_id'");

            mysqli_commit($conn);

            // notifying all sellers in this order
            foreach($seller_totals as $seller_id => $amount){

                $item_count = isset($seller_order_counts[$seller_id]) ? intval($seller_order_counts[$seller_id]) : 0;

                sendCheckoutNotification(
                    $conn,
                    $seller_id,
                    "New Order Received",
                    "You have received a new order from " . $user_data['full_name'] . ". Order Group: " . $order_group_id . ". Items: " . $item_count . ". Amount: R" . number_format($amount, 2) . ". Please check your deliveries.",
                    "manage-deliveries.php"
                );
            }

            // notifying buyer
            sendCheckoutNotification(
                $conn,
                $user_id,
                "Order Placed Successfully",
                "Your order was placed successfully. Order Group: " . $order_group_id . ". You can view it under Orders.",
                "orders.php"
            );

            $_SESSION['success_message'] = "Order placed successfully.";
            header("Location: orders.php?success=1");
            exit();

        }else{

            mysqli_rollback($conn);
            $message = "Order failed. One or more products may not have enough stock.";

        }
    }
}

// calculating totals again for display
$total_delivery_fee = ($selected_delivery_method == "delivery") ? $delivery_fee : 0;

if($has_pickup_only_item){
    $selected_delivery_method = "pickup";
    $total_delivery_fee = 0;
}

$tax_amount = $products_subtotal * $tax_percentage;
$final_total = $products_subtotal + $total_delivery_fee + $tax_amount;
$cash_allowed = ($final_total <= 1000);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Checkout | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.payment-methods,
.delivery-methods{
display:flex;
gap:20px;
margin:20px 0;
flex-wrap:wrap;
}

.payment-option,
.delivery-option{
padding:20px;
border:1px solid #ddd;
border-radius:10px;
cursor:pointer;
flex:1;
min-width:200px;
background:#fff;
}

.payment-option.disabled-option,
.delivery-option.disabled-option{
background:#f3f4f6;
color:#777;
cursor:not-allowed;
}

.order-summary{
margin-top:30px;
}

.summary-row{
display:flex;
justify-content:space-between;
padding:12px 0;
border-bottom:1px solid #eee;
gap:15px;
}

.total-row{
font-size:20px;
font-weight:bold;
}

.seller-group{
margin-bottom:25px;
padding:20px;
border:1px solid #eee;
border-radius:10px;
background:#fff;
}

.seller-header{
display:flex;
justify-content:space-between;
gap:15px;
flex-wrap:wrap;
border-bottom:1px solid #eee;
padding-bottom:12px;
margin-bottom:18px;
}

.checkout-product{
display:flex;
gap:15px;
margin-bottom:20px;
align-items:center;
}

.checkout-product img{
width:80px;
height:80px;
object-fit:cover;
border-radius:10px;
}

.seller-total-box{
background:#f8fafc;
padding:12px;
border-radius:10px;
margin-top:12px;
}

.hidden{
display:none;
}

.error-message{
background:#fee2e2;
color:#991b1b;
border-left:5px solid #dc2626;
padding:15px;
border-radius:10px;
font-weight:bold;
margin-bottom:20px;
}

.warning-message{
background:#fef3c7;
color:#92400e;
border-left:5px solid #f59e0b;
padding:15px;
border-radius:10px;
font-weight:bold;
margin-bottom:20px;
}

.estimate-box{
background:#eff6ff;
color:#1e3a8a;
border-left:5px solid #2563eb;
padding:15px;
border-radius:10px;
margin-top:15px;
}

.size-text,
.delivery-text{
font-size:14px;
color:#555;
margin-top:5px;
}

.confirm-order-btn,
.checkout-btn{
padding:14px 22px;
background:#111;
color:white;
border:none;
border-radius:10px;
font-weight:bold;
cursor:pointer;
text-decoration:none;
display:inline-block;
}

.processing-overlay{
display:none;
position:fixed;
z-index:99999;
left:0;
top:0;
width:100%;
height:100%;
background:rgba(0,0,0,0.78);
align-items:center;
justify-content:center;
}

.processing-card{
background:white;
width:360px;
max-width:90%;
padding:35px;
border-radius:18px;
text-align:center;
box-shadow:0 10px 35px rgba(0,0,0,0.25);
}

.spinner{
width:70px;
height:70px;
border:7px solid #e5e7eb;
border-top:7px solid #111;
border-radius:50%;
animation:spin 1s linear infinite;
margin:0 auto 18px;
}

@keyframes spin{
0%{transform:rotate(0deg)}
100%{transform:rotate(360deg)}
}

@media(max-width:768px){

.checkout-product{
align-items:flex-start;
}

.summary-row,
.seller-header{
flex-direction:column;
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

<nav>

<a href="dashboard.php">Dashboard</a>
<a href="products.php">Products</a>
<a href="cart.php">Cart</a>
<a href="orders.php">Orders</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="page-intro">

<h2>Checkout</h2>

<p>
Review your order, choose delivery or pickup, then confirm payment.
</p>

</div>

<?php if($message != ""){ ?>

<div class="error-message">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<?php if($has_pickup_only_item){ ?>

<div class="warning-message">
This order contains at least one pickup-only product. Delivery has been disabled and the order must be collected from the seller.
</div>

<?php } ?>

<?php if(!$cash_allowed){ ?>

<div class="warning-message">
Cash payment is disabled because this order is above R1000. Please use card payment for safety.
</div>

<?php } ?>

</div>

</section>

<section class="section-spacing">

<div class="container">

<?php if(empty($grouped_products)){ ?>

<div class="info-box">

<h3>Your cart is empty</h3>

<p>No available products found in your cart.</p>

<br>

<a href="products.php" class="checkout-btn">Browse Products</a>

</div>

<?php }else{ ?>

<form method="POST" id="checkoutForm">

<input type="hidden" name="confirm_order" value="1">

<div class="form-container">

<fieldset>

<legend>Delivery Option</legend>

<div class="delivery-methods">

<label class="delivery-option <?php if($has_pickup_only_item){ echo 'disabled-option'; } ?>">

<input
type="radio"
name="delivery_method"
value="delivery"
<?php if(!$has_pickup_only_item && $selected_delivery_method == "delivery"){ echo "checked"; } ?>
<?php if($has_pickup_only_item){ echo "disabled"; } ?>>

Delivery

</label>

<label class="delivery-option">

<input
type="radio"
name="delivery_method"
value="pickup"
<?php if($has_pickup_only_item || $selected_delivery_method == "pickup"){ echo "checked"; } ?>>

Pick Up

</label>

</div>

<div id="deliveryEstimate" class="estimate-box <?php if($has_pickup_only_item){ echo 'hidden'; } ?>">
Estimated delivery: 45 minutes to 1 hour 30 minutes.
</div>

<div id="pickupEstimate" class="estimate-box <?php if(!$has_pickup_only_item){ echo 'hidden'; } ?>">
Pickup readiness time will be updated by the seller.
</div>

</fieldset>

<br>

<fieldset>

<legend>Payment Method</legend>

<div class="payment-methods">

<label class="payment-option <?php if(!$cash_allowed){ echo 'disabled-option'; } ?>">

<input
type="radio"
name="payment_method"
value="cash"
<?php if($cash_allowed){ echo "checked"; } ?>
<?php if(!$cash_allowed){ echo "disabled"; } ?>>

Cash

<?php if(!$cash_allowed){ ?>
<br><small>Disabled for orders above R1000</small>
<?php } ?>

</label>

<label class="payment-option">

<input
type="radio"
name="payment_method"
value="card"
<?php if(!$cash_allowed){ echo "checked"; } ?>>

Card Payment

</label>

</div>

<div id="card-section" class="<?php if($cash_allowed){ echo 'hidden'; } ?>">

<label>Cardholder Name</label>
<input type="text" name="cardholder_name" placeholder="Demo Buyer">

<label>Card Number</label>
<input type="text" id="card_number" name="card_number" maxlength="23" inputmode="numeric" placeholder="4242 4242 4242 4242">

<label>Expiry Date</label>
<input type="text" id="expiry_date" name="expiry_date" maxlength="5" inputmode="numeric" placeholder="MM/YY">

<label>CVV</label>
<input type="password" name="cvv" maxlength="3" inputmode="numeric" placeholder="123">

<small>
Successful sandbox cards: 4242 4242 4242 4242 or 5555 5555 5555 4444. Use any future expiry date and any 3-digit CVV.
</small>

</div>

</fieldset>

</div>

<div class="order-summary">

<?php foreach($grouped_products as $seller_data){ ?>

<?php $seller_subtotal = floatval($seller_data['seller_subtotal']); ?>

<div class="seller-group">

<div class="seller-header">

<h3>
<?php echo !empty($seller_data['business_name']) ? htmlspecialchars($seller_data['business_name']) : "Seller"; ?>
</h3>

<strong class="seller-total-display" data-subtotal="<?php echo $seller_subtotal; ?>">
R<?php echo number_format($seller_subtotal, 2); ?>
</strong>

<?php if($seller_data['has_pickup_only']){ ?>
<span class="delivery-text">This seller group contains pickup-only item(s).</span>
<?php } ?>

</div>

<?php foreach($seller_data['products'] as $product){ ?>

<div class="checkout-product">

<img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" alt="Product">

<div>

<h4><?php echo htmlspecialchars($product['product_name']); ?></h4>

<?php if(!empty($product['selected_size'])){ ?>
<p class="size-text">Size / Option: <strong><?php echo htmlspecialchars($product['selected_size']); ?></strong></p>
<?php } ?>

<p class="delivery-text">
Delivery Option: <strong><?php echo htmlspecialchars($product['delivery_option']); ?></strong>
</p>

<p>
R<?php echo number_format($product['price'], 2); ?> × <?php echo intval($product['cart_quantity']); ?>
</p>

<p>
R<?php echo number_format($product['line_total'], 2); ?>
</p>

</div>

</div>

<?php } ?>

<div class="seller-total-box">

<div class="summary-row">
<span>Products</span>
<span>R<?php echo number_format($seller_subtotal, 2); ?></span>
</div>

</div>

</div>

<?php } ?>

<div class="info-box">

<h3>Order Summary</h3>

<div class="summary-row">
<span>Products Subtotal</span>
<span>R<?php echo number_format($products_subtotal, 2); ?></span>
</div>

<div class="summary-row">
<span>Delivery</span>
<span id="deliveryTotal">R<?php echo number_format($total_delivery_fee, 2); ?></span>
</div>

<div class="summary-row">
<span>VAT 15%</span>
<span id="vatTotal">R<?php echo number_format($tax_amount, 2); ?></span>
</div>

<div class="summary-row total-row">
<span>Total</span>
<span id="finalTotal">R<?php echo number_format($final_total, 2); ?></span>
</div>

</div>

<br>

<button type="submit" class="confirm-order-btn">
Confirm Order
</button>

</div>

</form>

<?php } ?>

</div>

</section>

<footer>

<div class="container footer-container">

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

<div id="processingOverlay" class="processing-overlay">

<div class="processing-card">

<div class="spinner"></div>

<h3 id="paymentTitle">Processing Payment...</h3>

<p id="paymentText">
Please wait while StreetMarket validates your payment and finalises your order.
</p>

</div>

</div>

<script>

const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
const cardSection = document.getElementById('card-section');

paymentMethods.forEach(function(method){

    method.addEventListener('change', function(){

        if(this.value === "card"){
            cardSection.classList.remove('hidden');
        }else{
            cardSection.classList.add('hidden');
        }

    });

});

const cardNumber = document.getElementById("card_number");

if(cardNumber){

    cardNumber.addEventListener("input", function(){

        let value = this.value.replace(/\D/g, "");
        value = value.substring(0, 19);
        value = value.replace(/(.{4})/g, "$1 ").trim();
        this.value = value;

    });

}

const expiryDate = document.getElementById("expiry_date");

if(expiryDate){

    expiryDate.addEventListener("input", function(){

        let value = this.value.replace(/\D/g, "");
        value = value.substring(0, 4);

        if(value.length >= 3){
            value = value.substring(0, 2) + "/" + value.substring(2, 4);
        }

        this.value = value;

    });

}

const deliveryOptions = document.querySelectorAll('input[name="delivery_method"]');
const sellerTotals = document.querySelectorAll('.seller-total-display');
const deliveryTotal = document.getElementById("deliveryTotal");
const finalTotal = document.getElementById("finalTotal");
const deliveryEstimate = document.getElementById("deliveryEstimate");
const pickupEstimate = document.getElementById("pickupEstimate");

const productsSubtotal = <?php echo json_encode($products_subtotal); ?>;
const deliveryFee = <?php echo json_encode($delivery_fee); ?>;
const taxPercentage = <?php echo json_encode($tax_percentage); ?>;

function money(amount){
    return "R" + Number(amount).toFixed(2);
}

// updating checkout totals when delivery option changes
function updateDeliveryTotals(){

    let selectedOption = document.querySelector('input[name="delivery_method"]:checked');

    if(!selectedOption){
        return;
    }

    let selected = selectedOption.value;
    let totalDelivery = selected === "delivery" ? deliveryFee : 0;
    let taxAmount = productsSubtotal * taxPercentage;

    sellerTotals.forEach(function(totalBox){

        let subtotal = parseFloat(totalBox.getAttribute("data-subtotal"));
        totalBox.innerHTML = money(subtotal);

    });

    if(selected === "delivery"){
        deliveryEstimate.classList.remove("hidden");
        pickupEstimate.classList.add("hidden");
    }else{
        deliveryEstimate.classList.add("hidden");
        pickupEstimate.classList.remove("hidden");
    }

    deliveryTotal.innerHTML = money(totalDelivery);
    finalTotal.innerHTML = money(productsSubtotal + totalDelivery + taxAmount);
}

deliveryOptions.forEach(function(option){
    option.addEventListener("change", updateDeliveryTotals);
});

updateDeliveryTotals();

const checkoutForm = document.getElementById("checkoutForm");
const processingOverlay = document.getElementById("processingOverlay");
const paymentTitle = document.getElementById("paymentTitle");
const paymentText = document.getElementById("paymentText");

if(checkoutForm){

    checkoutForm.addEventListener("submit", function(event){

        event.preventDefault();

        processingOverlay.style.display = "flex";
        paymentTitle.innerHTML = "Processing Payment...";
        paymentText.innerHTML = "Please wait while StreetMarket validates your payment and finalises your order.";

        setTimeout(function(){

            paymentTitle.innerHTML = "Finalising Order...";
            paymentText.innerHTML = "Creating your order, clearing your cart and notifying the seller.";

            setTimeout(function(){
                checkoutForm.submit();
            }, 700);

        }, 1200);

    });

}

</script>

</body>

</html>