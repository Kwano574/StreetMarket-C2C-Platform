<?php
session_start();
include("includes/db.php");
include("includes/admin-auth.php");

requireAdminRole(["payment_manager"]);

$message = "";

if(isset($_GET['refund'])){
    $order_id = intval($_GET['refund']);

    $refund_query = "
    UPDATE orders
    SET payment_status='refunded', status='cancelled', cancelled_by='admin'
    WHERE order_id='$order_id'
    AND payment_status='paid'
    ";

    if(mysqli_query($conn,$refund_query)){
        $message = "Payment marked as refunded.";
    }else{
        $message = "Refund failed.";
    }
}

$payments_query = "
SELECT orders.*, product.product_name, users.full_name AS buyer_name, seller.full_name AS seller_name
FROM orders
INNER JOIN product ON orders.product_id = product.product_id
INNER JOIN users ON orders.buyer_id = users.user_id
INNER JOIN users AS seller ON orders.seller_id = seller.user_id
ORDER BY orders.order_date DESC
";
$payments_result = mysqli_query($conn,$payments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Payments | StreetMarket</title>
<link rel="stylesheet" href="css/style.css">
<style>
table{width:100%;border-collapse:collapse;background:#fff;margin-top:20px}
th,td{padding:14px;border-bottom:1px solid #ddd;text-align:left}
th{background:#f5f5f5}
.refund-btn{padding:9px 14px;background:#c62828;color:#fff;text-decoration:none;border-radius:8px}
.status-paid{color:green;font-weight:bold}
.status-refunded{color:#c62828;font-weight:bold}
.status-pending{color:orange;font-weight:bold}
</style>
</head>
<body>

<header>
<div class="container header-container">
<div class="logo-section">
<img src="images/logo.png" alt="Logo">
<h1>Admin Payments</h1>
</div>
<nav>
<a href="admin-dashboard.php">Dashboard</a>
<a href="admin-orders.php">Orders</a>
<a href="manage-users.php">Users</a>
<a href="manage-products.php">Products</a>
<a href="admin-reports.php">Reports</a>
<a href="admin-logout.php">Logout</a>
</nav>
</div>
</header>

<section class="section-spacing">
<div class="container">
<h2>Manage Payments</h2>
<p>Monitor paid, pending and refunded marketplace payments.</p>

<?php if($message!=""){ ?>
<div class="auth-message"><?php echo htmlspecialchars($message); ?></div>
<?php } ?>

<table>
<tr>
<th>Order</th>
<th>Product</th>
<th>Buyer</th>
<th>Seller</th>
<th>Method</th>
<th>Amount</th>
<th>Payment Status</th>
<th>Order Status</th>
<th>Action</th>
</tr>

<?php if($payments_result && mysqli_num_rows($payments_result)>0){ while($payment=mysqli_fetch_assoc($payments_result)){ ?>
<tr>
<td>#<?php echo $payment['order_id']; ?></td>
<td><?php echo htmlspecialchars($payment['product_name']); ?></td>
<td><?php echo htmlspecialchars($payment['buyer_name']); ?></td>
<td><?php echo htmlspecialchars($payment['seller_name']); ?></td>
<td><?php echo isset($payment['payment_method']) ? ucfirst($payment['payment_method']) : "Cash"; ?></td>
<td>R<?php echo number_format($payment['total_amount'],2); ?></td>
<td>
<?php if($payment['payment_status']=="paid"){ ?>
<span class="status-paid">Paid</span>
<?php }elseif($payment['payment_status']=="refunded"){ ?>
<span class="status-refunded">Refunded</span>
<?php }else{ ?>
<span class="status-pending">Pending</span>
<?php } ?>
</td>
<td><?php echo ucfirst($payment['status']); ?></td>
<td>
<?php if($payment['payment_status']=="paid" && $payment['status']!="completed"){ ?>
<a href="admin-payments.php?refund=<?php echo $payment['order_id']; ?>" class="refund-btn" onclick="return confirm('Mark this payment as refunded?')">Refund</a>
<?php }else{ ?>
No Action
<?php } ?>
</td>
</tr>
<?php }}else{ ?>
<tr><td colspan="9">No payments found.</td></tr>
<?php } ?>
</table>
</div>
</section>

</body>
</html>