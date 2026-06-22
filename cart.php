<?php
// starting PHP session
session_start();

// connecting page to database
include("includes/db.php");

// protecting user's session by checking if they are logged in 
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);//getting user id from their session
$message = "";
$message_type = "success";

// removing product from cart when butoon reomove is clicked
if(isset($_GET['remove'])){
    $cart_id = intval($_GET['remove']);
    $delete_query = "DELETE FROM cart WHERE cart_id='$cart_id' AND user_id='$user_id'";
    mysqli_query($conn, $delete_query);
    header("Location: cart.php");
    exit();
}

// updating cart quantities and selected sizes
if(isset($_POST['update_cart'])){
    if(isset($_POST['quantities']) && is_array($_POST['quantities'])){
        foreach($_POST['quantities'] as $cart_id => $quantity){
            $cart_id = intval($cart_id);
            $quantity = intval($quantity);
            $selected_size = "";

            if(isset($_POST['selected_sizes'][$cart_id])){
                $selected_size = mysqli_real_escape_string($conn, trim($_POST['selected_sizes'][$cart_id]));
            }

            if($quantity < 1){
                $quantity = 1;
            }

            $stock_query = "
            SELECT product.quantity, product.available_sizes
            FROM cart
            INNER JOIN product ON cart.product_id = product.product_id
            WHERE cart.cart_id='$cart_id'
            AND cart.user_id='$user_id'
            LIMIT 1
            ";

            $stock_result = mysqli_query($conn, $stock_query);

            if($stock_result && mysqli_num_rows($stock_result) > 0){
                $stock_data = mysqli_fetch_assoc($stock_result);
                $available_stock = intval($stock_data['quantity']);
                $available_sizes = trim($stock_data['available_sizes']);

                if($quantity > $available_stock){
                    $quantity = $available_stock;
                }

                if($quantity < 1){
                    $quantity = 1;
                }

                // validating selected size against seller available sizes
                if($available_sizes != ""){
                    $valid_size = false;
                    $sizes_array = explode(",", $available_sizes);

                    foreach($sizes_array as $size_option){
                        if(strtolower(trim($size_option)) == strtolower($selected_size)){
                            $valid_size = true;
                        }
                    }

                    if($selected_size == "" || !$valid_size){
                        $message = "Please select a valid size for all size-based products.";
                        $message_type = "error";
                        continue;
                    }
                }

                if($message == ""){
                    $update_cart = "
                    UPDATE cart
                    SET quantity='$quantity', selected_size='$selected_size'
                    WHERE cart_id='$cart_id'
                    AND user_id='$user_id'
                    ";

                    mysqli_query($conn, $update_cart);
                }
            }
        }

        if($message == ""){
            $message = "Cart updated successfully.";
            $message_type = "success";
        }
    }
}

// getting user delivery information 
$user_query = "SELECT * FROM users WHERE user_id='$user_id' LIMIT 1";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

// updating  and validating delivery information when user clicks update button
if(isset($_POST['update_delivery'])){
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $province = mysqli_real_escape_string($conn, trim($_POST['province']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));

    if(!preg_match("/^[6-8][0-9]{8}$/", $phone)){
        $message = "Phone number must be a valid South African number after +27.";
        $message_type = "error";
    }else{
        $update_user = "
        UPDATE users
        SET full_name='$full_name', phone='$phone', province='$province', address='$address'
        WHERE user_id='$user_id'
        ";

        if(mysqli_query($conn, $update_user)){
            $_SESSION['full_name'] = $full_name;
            header("Location: cart.php");
            exit();
        }else{
            $message = "Failed to update delivery information.";
            $message_type = "error";
        }
    }
}

// getting products inside user's cart
$cart_query = "
SELECT
cart.cart_id,
cart.quantity AS cart_quantity,
cart.selected_size,
product.product_id,
product.product_name,
product.available_sizes,
product.price,
product.image,
product.quantity AS stock_quantity,
product.status,
product.moderation_status,
product.user_id AS seller_id,
users.full_name AS seller_name
FROM cart
INNER JOIN product ON cart.product_id = product.product_id
INNER JOIN users ON product.user_id = users.user_id
WHERE cart.user_id='$user_id'
ORDER BY users.full_name ASC
";

$cart_result = mysqli_query($conn, $cart_query);

// grouping products by seller
$grouped_cart = [];
$grand_total = 0;
$unavailable_items = 0;

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

        $is_available = true;

        if($row['status'] != "available" || $row['moderation_status'] != "approved" || $stock_quantity <= 0){
            $is_available = false;
            $unavailable_items++;
        }

        $line_total = 0;

        if($is_available){
            $line_total = floatval($row['price']) * $cart_quantity;
            $grand_total += $line_total;
        }

        if(!isset($grouped_cart[$seller_id])){
            $grouped_cart[$seller_id] = [
                "seller_name" => $row['seller_name'],
                "products" => [],
                "subtotal" => 0
            ];
        }

        $row['cart_quantity'] = $cart_quantity;
        $row['line_total'] = $line_total;
        $row['is_available'] = $is_available;
        $grouped_cart[$seller_id]['products'][] = $row;

        if($is_available){
            $grouped_cart[$seller_id]['subtotal'] += $line_total;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart | StreetMarket</title>
<link rel="stylesheet" href="css/style.css">
<style>
.success-message{background:#dcfce7;color:#166534;border-left:5px solid #16a34a;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.error-message{background:#fee2e2;color:#991b1b;border-left:5px solid #dc2626;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.warning-message{background:#fef3c7;color:#92400e;border-left:5px solid #f59e0b;padding:15px;border-radius:10px;margin-bottom:20px;font-weight:bold}
.seller-group{margin-bottom:40px;background:white;padding:25px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08)}
.seller-title{font-size:22px;margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid #eee}
.seller-subtotal{margin-top:20px;font-size:18px;font-weight:bold;text-align:right}
.grand-total-box{margin-top:40px;background:#f8f8f8;padding:30px;border-radius:12px;text-align:center}
.grand-total-box h2{margin-bottom:15px}
.checkout-btn{display:inline-block;margin-top:20px;text-decoration:none}
.checkout-btn button,.update-cart-btn{padding:15px 30px;font-size:16px;cursor:pointer;border:none;border-radius:10px;background:#111;color:white;font-weight:bold}
.update-cart-btn{margin-top:20px;background:#2563eb}
.cart-product{display:flex;align-items:center;gap:15px}
.cart-product img{width:80px;height:80px;object-fit:cover;border-radius:10px}
.quantity-input{width:90px;padding:10px;border:1px solid #ddd;border-radius:8px;text-align:center;font-size:15px}
.size-select{padding:10px;border:1px solid #ddd;border-radius:8px;width:160px}
.remove-btn{background:#dc2626;color:white;padding:9px 14px;border-radius:8px;text-decoration:none;font-weight:bold}
.unavailable-row{background:#fff1f2}
.unavailable-text{color:#dc2626;font-weight:bold;font-size:14px}
.stock-text,.size-text{font-size:13px;color:#555}
.cart-table{width:100%;border-collapse:collapse}
.cart-table th,.cart-table td{padding:14px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
.cart-table th{background:#111;color:white}
@media(max-width:900px){.cart-table{display:block;overflow-x:auto;white-space:nowrap}.cart-product{min-width:260px}}
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
<a href="messages.php">&#x1F4AC; Chat</a>
<a href="logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">
<div class="container">
<div class="page-intro">
<h2>Your Shopping Cart</h2>
<p>Products are grouped by seller for easier checkout and seller payment splitting.</p>
</div>

<?php if($message != ""){ ?>
<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>
<?php } ?>

<?php if($unavailable_items > 0){ ?>
<div class="warning-message">Some products in your cart are no longer available or approved. Remove them before checkout.</div>
<?php } ?>
</div>
</section>

<section class="section-spacing">
<div class="container">

<?php if(count($grouped_cart) > 0){ ?>
<form method="POST">

<?php foreach($grouped_cart as $seller_id => $seller_data){ ?>
<div class="seller-group">
<h3 class="seller-title">Seller: <?php echo htmlspecialchars($seller_data['seller_name']); ?></h3>

<table class="cart-table">
<thead>
<tr>
<th>Product</th>
<th>Unit Price</th>
<th>Size / Option</th>
<th>Quantity</th>
<th>Stock</th>
<th>Line Total</th>
<th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach($seller_data['products'] as $product){ ?>
<tr class="<?php if(!$product['is_available']){ echo 'unavailable-row'; } ?>">
<td>
<div class="cart-product">
<img src="uploads/<?php echo htmlspecialchars($product['image']); ?>" alt="Product Image">
<div>
<strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
<?php if(!$product['is_available']){ ?>
<br><span class="unavailable-text">Unavailable / Not Approved / Out of Stock</span>
<?php } ?>
<?php if(!empty($product['selected_size'])){ ?>
<br><span class="size-text">Selected: <strong><?php echo htmlspecialchars($product['selected_size']); ?></strong></span>
<?php } ?>
</div>
</div>
</td>

<td>R<?php echo number_format($product['price'], 2); ?></td>

<td>
<?php if(!empty($product['available_sizes'])){ ?>
<select name="selected_sizes[<?php echo $product['cart_id']; ?>]" class="size-select" <?php if(!$product['is_available']){ echo "disabled"; } ?>>
<option value="">Select Size</option>
<?php
$sizes = explode(",", $product['available_sizes']);
foreach($sizes as $size){
    $size = trim($size);
    if($size != ""){
?>
<option value="<?php echo htmlspecialchars($size); ?>" <?php if(strtolower($product['selected_size']) == strtolower($size)){ echo "selected"; } ?>>
<?php echo htmlspecialchars($size); ?>
</option>
<?php }} ?>
</select>
<?php }else{ ?>
<span class="stock-text">Not required</span>
<?php } ?>
</td>

<td>
<input type="number" name="quantities[<?php echo $product['cart_id']; ?>]" class="quantity-input" min="1" max="<?php echo intval($product['stock_quantity']); ?>" value="<?php echo intval($product['cart_quantity']); ?>" <?php if(!$product['is_available']){ echo "disabled"; } ?>>
</td>

<td><span class="stock-text"><?php echo intval($product['stock_quantity']); ?> available</span></td>
<td>R<?php echo number_format($product['line_total'], 2); ?></td>

<td>
<a href="cart.php?remove=<?php echo $product['cart_id']; ?>" class="remove-btn" onclick="return confirm('Remove this product from cart?')">Remove</a>
</td>
</tr>
<?php } ?>

</tbody>
</table>

<div class="seller-subtotal">Seller Subtotal: R<?php echo number_format($seller_data['subtotal'], 2); ?></div>
</div>
<?php } ?>

<button type="submit" name="update_cart" class="update-cart-btn">Update Cart</button>
</form>

<div class="grand-total-box">
<h2>Grand Total: R<?php echo number_format($grand_total, 2); ?></h2>
<?php if($grand_total > 0 && $unavailable_items == 0){ ?>
<a href="checkout.php" class="checkout-btn"><button>Proceed To Checkout</button></a>
<?php }elseif($unavailable_items > 0){ ?>
<p>Please remove unavailable products before checkout.</p>
<?php }else{ ?>
<p>Your available cart total is zero.</p>
<?php } ?>
</div>

<?php }else{ ?>
<div class="info-box">
<p>Your cart is empty.</p>
<br>
<a href="products.php"><button>Browse Products</button></a>
</div>
<?php } ?>

</div>
</section>

<section class="section-spacing">
<div class="container">
<div class="form-container">
<form method="POST">
<fieldset>
<legend>Delivery Information</legend>

<label>Full Name *</label>
<input type="text" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>

<label>Phone Number *</label>
<div class="phone-group">
<span class="phone-code">+27</span>
<input type="tel" name="phone" maxlength="9" value="<?php echo htmlspecialchars($user_data['phone']); ?>" required>
</div>

<label>Province *</label>
<select name="province" required>
<?php
$provinces = ["Gauteng","Limpopo","Mpumalanga","KwaZulu-Natal","Western Cape","Eastern Cape","North West","Free State","Northern Cape"];
foreach($provinces as $province){
?>
<option value="<?php echo $province; ?>" <?php if($user_data['province'] == $province){ echo "selected"; } ?>>
<?php echo $province; ?>
</option>
<?php } ?>
</select>

<label>Delivery Address *</label>
<textarea name="address" required><?php echo htmlspecialchars($user_data['address']); ?></textarea>

<button type="submit" name="update_delivery">Update Delivery Information</button>
</fieldset>
</form>
</div>
</div>
</section>

<footer>
<div class="container footer-container">
<nav>
<a href="about.php">About</a>
<a href="safety.php">Safety Centre</a>
<a href="help.php">Help</a>
</nav>
<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>
</div>
</footer>

</body>
</html>