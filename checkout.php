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
$delivery_fee_per_seller = 25;
$tax_percentage = 0.00;

$user_result = mysqli_query($conn,"SELECT * FROM users WHERE user_id='$user_id' LIMIT 1");
$user_data = mysqli_fetch_assoc($user_result);
$delivery_address = trim($user_data['address'] . ", " . $user_data['province']);

$cart_query = "
SELECT
cart.cart_id,
cart.quantity AS cart_quantity,
product.product_id,
product.product_name,
product.price,
product.quantity AS stock_quantity,
product.image,
product.user_id AS seller_id,
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

$cart_result = mysqli_query($conn,$cart_query);

$grouped_products = [];
$products_subtotal = 0;

if($cart_result){
    while($row = mysqli_fetch_assoc($cart_result)){
        $seller_id = intval($row['seller_id']);
        $cart_quantity = intval($row['cart_quantity']);
        $stock_quantity = intval($row['stock_quantity']);

        if($cart_quantity < 1){ $cart_quantity = 1; }
        if($cart_quantity > $stock_quantity){ $cart_quantity = $stock_quantity; }

        $line_total = floatval($row['price']) * $cart_quantity;

        if(!isset($grouped_products[$seller_id])){
            $grouped_products[$seller_id] = [
                "business_name" => $row['business_name'],
                "business_bank_name" => $row['business_bank_name'],
                "business_account_holder" => $row['business_account_holder'],
                "business_account_number" => $row['business_account_number'],
                "business_branch_code" => $row['business_branch_code'],
                "seller_subtotal" => 0,
                "products" => []
            ];
        }

        $row['cart_quantity'] = $cart_quantity;
        $row['line_total'] = $line_total;

        $grouped_products[$seller_id]['products'][] = $row;
        $grouped_products[$seller_id]['seller_subtotal'] += $line_total;
        $products_subtotal += $line_total;
    }
}

$seller_count = count($grouped_products);
$selected_delivery_method = "delivery";
$total_delivery_fee = $seller_count * $delivery_fee_per_seller;
$tax_amount = $products_subtotal * $tax_percentage;
$final_total = $products_subtotal + $total_delivery_fee + $tax_amount;

function luhnCheck($number){
    $number = preg_replace('/\D/', '', $number);
    $sum = 0;
    $alternate = false;

    for($i = strlen($number)-1; $i >= 0; $i--){
        $digit = intval($number[$i]);
        if($alternate){
            $digit *= 2;
            if($digit > 9){ $digit -= 9; }
        }
        $sum += $digit;
        $alternate = !$alternate;
    }

    return ($sum % 10 == 0);
}

function getCardBrand($card_number){
    if(preg_match("/^4[0-9]{12,18}$/", $card_number)){ return "Visa"; }
    if(preg_match("/^5[1-5][0-9]{14}$/", $card_number)){ return "Mastercard"; }
    return "Unknown";
}

function validateSandboxCard($card_number,$expiry_date,$cvv){
    $card_number = preg_replace('/\D/','',$card_number);

    $sandbox_failures = [
        "4000000000000002" => "Card was declined.",
        "4000000000009995" => "Card has insufficient funds.",
        "4000000000009987" => "Card is expired."
    ];

    if(isset($sandbox_failures[$card_number])){
        return $sandbox_failures[$card_number];
    }

    if($card_number != "5555555555554444"){
        return "Use a valid buyer sandbox card.";
    }

    if(!preg_match("/^[0-9]{13,19}$/",$card_number)){
        return "Card number must contain between 13 and 19 digits.";
    }

    if(!luhnCheck($card_number)){
        return "Invalid card number.";
    }

    if(getCardBrand($card_number) != "Mastercard"){
        return "Invalid card issuer.";
    }

    if(!preg_match("/^(0[1-9]|1[0-2])\/[0-9]{2}$/",$expiry_date)){
        return "Expiry date must be in MM/YY format.";
    }

    $parts = explode("/",$expiry_date);
    $month = intval($parts[0]);
    $year = intval("20".$parts[1]);

    if($year < intval(date("Y")) || ($year == intval(date("Y")) && $month < intval(date("m")))){
        return "Card expiry date cannot be in the past.";
    }

    if(!preg_match("/^[0-9]{3}$/",$cvv)){
        return "CVV must contain exactly 3 digits.";
    }

    if($cvv != "123"){
        return "Invalid CVV.";
    }

    return "";
}

if(isset($_POST['confirm_order'])){

    $payment_method = mysqli_real_escape_string($conn, trim($_POST['payment_method']));
    $selected_delivery_method = mysqli_real_escape_string($conn, trim($_POST['delivery_method']));

    if($selected_delivery_method != "delivery" && $selected_delivery_method != "pickup"){
        $message = "Please select a valid delivery option.";
    }

    $total_delivery_fee = ($selected_delivery_method == "delivery") ? ($seller_count * $delivery_fee_per_seller) : 0;
    $final_total = $products_subtotal + $total_delivery_fee + $tax_amount;

    if(empty($grouped_products)){
        $message = "Your cart is empty.";
    }elseif($selected_delivery_method == "delivery" && (empty($delivery_address) || $delivery_address == ",")){
        $message = "Please update your delivery address before checkout.";
    }elseif($payment_method != "cash" && $payment_method != "card"){
        $message = "Please select a valid payment method.";
    }

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

    if($payment_method == "card" && $message == ""){
        $cardholder_name = mysqli_real_escape_string($conn, trim($_POST['cardholder_name']));
        $card_number = preg_replace("/\s+/","",$_POST['card_number']);
        $expiry_date = trim($_POST['expiry_date']);
        $cvv = trim($_POST['cvv']);

        if(empty($cardholder_name) || empty($card_number) || empty($expiry_date) || empty($cvv)){
            $message = "Please complete all card details.";
        }else{
            $message = validateSandboxCard($card_number,$expiry_date,$cvv);
        }
    }

    if($message == ""){
        mysqli_begin_transaction($conn);

        $order_success = true;
        $seller_totals = [];

        foreach($grouped_products as $seller_id => $seller_data){

            $seller_delivery_fee = ($selected_delivery_method == "delivery") ? $delivery_fee_per_seller : 0;
            $seller_total = floatval($seller_data['seller_subtotal']) + $seller_delivery_fee;

            $seller_totals[$seller_id] = $seller_total;
            $delivery_allocated = false;

            foreach($seller_data['products'] as $product){

                $product_id = intval($product['product_id']);
                $cart_quantity = intval($product['cart_quantity']);
                $order_total = floatval($product['line_total']);

                if(!$delivery_allocated){
                    $order_total += $seller_delivery_fee;
                    $delivery_allocated = true;
                }

                $stock_result = mysqli_query($conn,"
                SELECT quantity FROM product
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

                $insert_order = "
                INSERT INTO orders(
                    buyer_id,
                    seller_id,
                    product_id,
                    quantity,
                    delivery_address,
                    total_amount,
                    payment_method,
                    payment_status,
                    delivery_status,
                    status,
                    delivery_method,
                    estimated_time,
                    buyer_confirmed
                )
                VALUES(
                    '$user_id',
                    '$seller_id',
                    '$product_id',
                    '$cart_quantity',
                    '$order_delivery_address',
                    '$order_total',
                    '$payment_method',
                    '$payment_status',
                    '$delivery_status',
                    'pending',
                    '$selected_delivery_method',
                    '$estimated_time',
                    'No'
                )
                ";

                if(!mysqli_query($conn,$insert_order)){
                    $order_success = false;
                    break;
                }

                $new_quantity = intval($stock_data['quantity']) - $cart_quantity;
                $new_status = ($new_quantity <= 0) ? "sold" : "available";

                if($new_quantity < 0){ $new_quantity = 0; }

                if(!mysqli_query($conn,"
                    UPDATE product
                    SET quantity='$new_quantity', status='$new_status'
                    WHERE product_id='$product_id'
                ")){
                    $order_success = false;
                    break;
                }
            }

            if(!$order_success){ break; }
        }

        if($order_success){
            mysqli_query($conn,"DELETE FROM cart WHERE user_id='$user_id'");
            mysqli_commit($conn);

            foreach($seller_totals as $seller_id => $amount){
                if(function_exists("createNotification")){
                    createNotification(
                        $conn,
                        $seller_id,
                        "New Order Received",
                        "You have a new order. Amount: R".number_format($amount,2).".",
                        "manage-deliveries.php"
                    );
                }
            }

            $_SESSION['success_message'] = "Order placed successfully.";
            header("Location: orders.php?success=1");
            exit();

        }else{
            mysqli_rollback($conn);
            $message = "Order failed. One or more products may not have enough stock.";
        }
    }
}

$total_delivery_fee = ($selected_delivery_method == "delivery") ? ($seller_count * $delivery_fee_per_seller) : 0;
$final_total = $products_subtotal + $total_delivery_fee + $tax_amount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout | StreetMarket</title>
<link rel="stylesheet" href="css/style.css">

<style>
.payment-methods,.delivery-methods{display:flex;gap:20px;margin:20px 0;flex-wrap:wrap}
.payment-option,.delivery-option{padding:20px;border:1px solid #ddd;border-radius:10px;cursor:pointer;flex:1;min-width:200px;background:#fff}
.order-summary{margin-top:30px}
.summary-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eee;gap:15px}
.total-row{font-size:20px;font-weight:bold}
.seller-group{margin-bottom:25px;padding:20px;border:1px solid #eee;border-radius:10px;background:#fff}
.seller-header{display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap;border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:18px}
.checkout-product{display:flex;gap:15px;margin-bottom:20px;align-items:center}
.checkout-product img{width:80px;height:80px;object-fit:cover;border-radius:10px}
.seller-total-box{background:#f8fafc;padding:12px;border-radius:10px;margin-top:12px}
.hidden{display:none}
.error-message{background:#fee2e2;color:#991b1b;border-left:5px solid #dc2626;padding:15px;border-radius:10px;font-weight:bold;margin-bottom:20px}
.estimate-box{background:#eff6ff;color:#1e3a8a;border-left:5px solid #2563eb;padding:15px;border-radius:10px;margin-top:15px}
button[name='confirm_order'],.checkout-btn{padding:14px 22px;background:#111;color:white;border:none;border-radius:10px;font-weight:bold;cursor:pointer;text-decoration:none;display:inline-block}
@media(max-width:768px){.checkout-product{align-items:flex-start}.summary-row,.seller-header{flex-direction:column}}
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
</div>

<?php if($message != ""){ ?>
<div class="error-message"><?php echo htmlspecialchars($message); ?></div>
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

<form method="POST">

<div class="form-container">

<fieldset>
<legend>Delivery Option</legend>

<div class="delivery-methods">
<label class="delivery-option">
<input type="radio" name="delivery_method" value="delivery" checked>
Delivery
</label>

<label class="delivery-option">
<input type="radio" name="delivery_method" value="pickup">
Pick Up
</label>
</div>

<div id="deliveryEstimate" class="estimate-box">
Estimated delivery: 45 minutes to 1 hour 30 minutes.
</div>

<div id="pickupEstimate" class="estimate-box hidden">
Pickup readiness time will be updated by the seller.
</div>

</fieldset>

<br>

<fieldset>
<legend>Payment Method</legend>

<div class="payment-methods">
<label class="payment-option">
<input type="radio" name="payment_method" value="cash" checked>
Cash
</label>

<label class="payment-option">
<input type="radio" name="payment_method" value="card">
Card Payment
</label>
</div>

<div id="card-section" class="hidden">

<label>Cardholder Name</label>
<input type="text" name="cardholder_name">

<label>Card Number</label>
<input type="text" id="card_number" name="card_number" maxlength="23" inputmode="numeric" placeholder="5555 5555 5555 4444">

<label>Expiry Date</label>
<input type="text" id="expiry_date" name="expiry_date" maxlength="5" inputmode="numeric" placeholder="MM/YY">

<label>CVV</label>
<input type="password" name="cvv" maxlength="3" inputmode="numeric">

</div>

</fieldset>

</div>

<div class="order-summary">

<?php foreach($grouped_products as $seller_data){ 
$seller_subtotal = floatval($seller_data['seller_subtotal']);
$seller_total = $seller_subtotal + $delivery_fee_per_seller;
?>

<div class="seller-group">

<div class="seller-header">
<h3><?php echo !empty($seller_data['business_name']) ? htmlspecialchars($seller_data['business_name']) : "Seller"; ?></h3>
<strong class="seller-total-display" data-subtotal="<?php echo $seller_subtotal; ?>">
R<?php echo number_format($seller_total,2); ?>
</strong>
</div>

<?php foreach($seller_data['products'] as $product){ ?>
<div class="checkout-product">
<img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" alt="Product">
<div>
<h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
<p>R<?php echo number_format($product['price'],2); ?> × <?php echo intval($product['cart_quantity']); ?></p>
<p>R<?php echo number_format($product['line_total'],2); ?></p>
</div>
</div>
<?php } ?>

<div class="seller-total-box">
<div class="summary-row">
<span>Products</span>
<span>R<?php echo number_format($seller_subtotal,2); ?></span>
</div>

<div class="summary-row delivery-row">
<span>Delivery</span>
<span>R<?php echo number_format($delivery_fee_per_seller,2); ?></span>
</div>
</div>

</div>

<?php } ?>

<div class="info-box">
<h3>Order Summary</h3>

<div class="summary-row">
<span>Products Subtotal</span>
<span>R<?php echo number_format($products_subtotal,2); ?></span>
</div>

<div class="summary-row">
<span>Delivery</span>
<span id="deliveryTotal">R<?php echo number_format($total_delivery_fee,2); ?></span>
</div>

<div class="summary-row">
<span>Tax</span>
<span>R<?php echo number_format($tax_amount,2); ?></span>
</div>

<div class="summary-row total-row">
<span>Total</span>
<span id="finalTotal">R<?php echo number_format($final_total,2); ?></span>
</div>
</div>

<br>

<button type="submit" name="confirm_order">Confirm Order</button>

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
        value = value.substring(0,19);
        value = value.replace(/(.{4})/g, "$1 ").trim();
        this.value = value;
    });
}

const expiryDate = document.getElementById("expiry_date");
if(expiryDate){
    expiryDate.addEventListener("input", function(){
        let value = this.value.replace(/\D/g, "");
        value = value.substring(0,4);
        if(value.length >= 3){
            value = value.substring(0,2) + "/" + value.substring(2,4);
        }
        this.value = value;
    });
}

const deliveryOptions = document.querySelectorAll('input[name="delivery_method"]');
const deliveryRows = document.querySelectorAll('.delivery-row');
const sellerTotals = document.querySelectorAll('.seller-total-display');
const deliveryTotal = document.getElementById("deliveryTotal");
const finalTotal = document.getElementById("finalTotal");
const deliveryEstimate = document.getElementById("deliveryEstimate");
const pickupEstimate = document.getElementById("pickupEstimate");

const productsSubtotal = <?php echo json_encode($products_subtotal); ?>;
const sellerCount = <?php echo json_encode($seller_count); ?>;
const deliveryFee = <?php echo json_encode($delivery_fee_per_seller); ?>;
const taxAmount = <?php echo json_encode($tax_amount); ?>;

function money(amount){ return "R" + Number(amount).toFixed(2); }

function updateDeliveryTotals(){
    let selected = document.querySelector('input[name="delivery_method"]:checked').value;
    let totalDelivery = selected === "delivery" ? sellerCount * deliveryFee : 0;

    deliveryRows.forEach(function(row){
        row.style.display = selected === "delivery" ? "flex" : "none";
    });

    sellerTotals.forEach(function(totalBox){
        let subtotal = parseFloat(totalBox.getAttribute("data-subtotal"));
        totalBox.innerHTML = money(selected === "delivery" ? subtotal + deliveryFee : subtotal);
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
</script>

</body>
</html>