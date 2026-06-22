<?php

// starting php session
session_start();

// connecting to database
include("includes/db.php");

// preventing mysqli errors from breaking the page
mysqli_report(MYSQLI_REPORT_OFF);

$message = "";
$message_type = "success";

// creating sandbox accounts table if it does not exist
mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS sandbox_accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    account_name VARCHAR(100) NOT NULL UNIQUE,
    account_type VARCHAR(50) NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00
)
");

// creating sandbox transactions table if it does not exist
mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS sandbox_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    from_account VARCHAR(100) NOT NULL,
    to_account VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_note VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
");

// inserting default sandbox accounts if they do not exist
mysqli_query($conn, "
INSERT IGNORE INTO sandbox_accounts(account_name, account_type, balance)
VALUES('Buyer Sandbox Card', 'buyer', 5000.00)
");

mysqli_query($conn, "
INSERT IGNORE INTO sandbox_accounts(account_name, account_type, balance)
VALUES('StreetMarket Escrow', 'platform', 0.00)
");

mysqli_query($conn, "
INSERT IGNORE INTO sandbox_accounts(account_name, account_type, balance)
VALUES('Seller Payout Account', 'seller', 800.00)
");

// demo order amount
$demo_amount = 350.00;

// resetting the demo balances
if(isset($_POST['reset_demo'])){

    mysqli_query($conn, "
    UPDATE sandbox_accounts
    SET balance=5000.00
    WHERE account_name='Buyer Sandbox Card'
    ");

    mysqli_query($conn, "
    UPDATE sandbox_accounts
    SET balance=0.00
    WHERE account_name='StreetMarket Escrow'
    ");

    mysqli_query($conn, "
    UPDATE sandbox_accounts
    SET balance=800.00
    WHERE account_name='Seller Payout Account'
    ");

    mysqli_query($conn, "DELETE FROM sandbox_transactions");

    $message = "Demo balances reset successfully.";
    $message_type = "success";
}

// simulating buyer payment into StreetMarket escrow
if(isset($_POST['simulate_payment'])){

    mysqli_begin_transaction($conn);

    $buyer_result = mysqli_query($conn, "
    SELECT balance
    FROM sandbox_accounts
    WHERE account_name='Buyer Sandbox Card'
    FOR UPDATE
    ");

    $buyer = mysqli_fetch_assoc($buyer_result);

    if(!$buyer){

        mysqli_rollback($conn);
        $message = "Buyer sandbox account was not found.";
        $message_type = "error";

    }elseif(floatval($buyer['balance']) < $demo_amount){

        mysqli_rollback($conn);
        $message = "Buyer sandbox card has insufficient balance.";
        $message_type = "error";

    }else{

        mysqli_query($conn, "
        UPDATE sandbox_accounts
        SET balance = balance - '$demo_amount'
        WHERE account_name='Buyer Sandbox Card'
        ");

        mysqli_query($conn, "
        UPDATE sandbox_accounts
        SET balance = balance + '$demo_amount'
        WHERE account_name='StreetMarket Escrow'
        ");

        mysqli_query($conn, "
        INSERT INTO sandbox_transactions(from_account, to_account, amount, transaction_note)
        VALUES(
            'Buyer Sandbox Card',
            'StreetMarket Escrow',
            '$demo_amount',
            'Buyer paid for order. Money moved into StreetMarket escrow.'
        )
        ");

        mysqli_commit($conn);

        $message = "Payment simulated successfully. Buyer balance decreased and StreetMarket escrow increased.";
        $message_type = "success";
    }
}

// simulating seller payout from escrow
if(isset($_POST['simulate_payout'])){

    mysqli_begin_transaction($conn);

    $escrow_result = mysqli_query($conn, "
    SELECT balance
    FROM sandbox_accounts
    WHERE account_name='StreetMarket Escrow'
    FOR UPDATE
    ");

    $escrow = mysqli_fetch_assoc($escrow_result);

    if(!$escrow){

        mysqli_rollback($conn);
        $message = "StreetMarket escrow account was not found.";
        $message_type = "error";

    }elseif(floatval($escrow['balance']) < $demo_amount){

        mysqli_rollback($conn);
        $message = "StreetMarket escrow does not have enough money to pay the seller.";
        $message_type = "error";

    }else{

        mysqli_query($conn, "
        UPDATE sandbox_accounts
        SET balance = balance - '$demo_amount'
        WHERE account_name='StreetMarket Escrow'
        ");

        mysqli_query($conn, "
        UPDATE sandbox_accounts
        SET balance = balance + '$demo_amount'
        WHERE account_name='Seller Payout Account'
        ");

        mysqli_query($conn, "
        INSERT INTO sandbox_transactions(from_account, to_account, amount, transaction_note)
        VALUES(
            'StreetMarket Escrow',
            'Seller Payout Account',
            '$demo_amount',
            'Seller payout released from escrow.'
        )
        ");

        mysqli_commit($conn);

        $message = "Seller payout simulated successfully. Escrow decreased and seller balance increased.";
        $message_type = "success";
    }
}

// getting accounts
$accounts_result = mysqli_query($conn, "
SELECT *
FROM sandbox_accounts
ORDER BY account_id ASC
");

// getting transactions
$transactions_result = mysqli_query($conn, "
SELECT *
FROM sandbox_transactions
ORDER BY transaction_id DESC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Sandbox Balance Demo | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.demo-container{
max-width:1100px;
margin:40px auto;
padding:20px;
}

.page-title{
margin-bottom:20px;
}

.page-title h2{
margin-bottom:10px;
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

.demo-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
gap:20px;
margin:25px 0;
}

.account-card{
background:white;
padding:25px;
border-radius:14px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
text-align:center;
border:1px solid #eee;
}

.account-card h3{
margin-bottom:10px;
}

.account-type{
font-size:14px;
color:#555;
margin-bottom:12px;
text-transform:uppercase;
font-weight:bold;
}

.balance{
font-size:32px;
font-weight:bold;
color:#16a34a;
}

.demo-actions{
display:flex;
gap:12px;
flex-wrap:wrap;
margin:25px 0;
}

.demo-actions button{
padding:14px 18px;
border:none;
border-radius:10px;
background:#111;
color:white;
font-weight:bold;
cursor:pointer;
}

.payment-btn{
background:#2563eb !important;
}

.payout-btn{
background:#16a34a !important;
}

.reset-btn{
background:#c62828 !important;
}

.demo-note{
background:#f8fafc;
border-left:5px solid #111;
padding:18px;
border-radius:10px;
margin:25px 0;
line-height:1.6;
}

.table-wrapper{
overflow-x:auto;
background:white;
border-radius:12px;
box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

table{
width:100%;
border-collapse:collapse;
background:white;
min-width:800px;
}

th,
td{
padding:13px;
border-bottom:1px solid #ddd;
text-align:left;
}

th{
background:#111;
color:white;
}

.back-links{
margin-top:25px;
display:flex;
gap:12px;
flex-wrap:wrap;
}

.back-links a{
padding:12px 16px;
background:#111;
color:white;
border-radius:8px;
text-decoration:none;
font-weight:bold;
}

@media(max-width:768px){

.demo-actions{
flex-direction:column;
}

.demo-actions button{
width:100%;
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
<a href="checkout.php">Checkout</a>
<a href="orders.php">Orders</a>
<a href="logout.php">Logout</a>

</nav>

</div>

</header>

<div class="demo-container">

<div class="page-title">

<h2>StreetMarket Sandbox Payment Balance Demo</h2>

<p>
This page is only for presentation. It shows how money moves from the buyer card, to StreetMarket escrow, and then to the seller payout account.
</p>

</div>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type == 'success' ? 'success-message' : 'error-message'; ?>">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<div class="demo-note">

<strong>Demo Amount:</strong>
R<?php echo number_format($demo_amount, 2); ?>

<br>

<strong>Flow:</strong>
Buyer Sandbox Card → StreetMarket Escrow → Seller Payout Account

</div>

<div class="demo-grid">

<?php if($accounts_result && mysqli_num_rows($accounts_result) > 0){ ?>

<?php while($account = mysqli_fetch_assoc($accounts_result)){ ?>

<div class="account-card">

<h3><?php echo htmlspecialchars($account['account_name']); ?></h3>

<div class="account-type">
<?php echo htmlspecialchars($account['account_type']); ?>
</div>

<div class="balance">
R<?php echo number_format($account['balance'], 2); ?>
</div>

</div>

<?php } ?>

<?php } ?>

</div>

<form method="POST" class="demo-actions">

<button type="submit" name="simulate_payment" class="payment-btn">
1. Simulate Buyer Payment
</button>

<button type="submit" name="simulate_payout" class="payout-btn">
2. Simulate Seller Payout
</button>

<button type="submit" name="reset_demo" class="reset-btn">
Reset Demo
</button>

</form>

<h3>Transaction History</h3>

<div class="table-wrapper">

<table>

<tr>
<th>From</th>
<th>To</th>
<th>Amount</th>
<th>Note</th>
<th>Date</th>
</tr>

<?php if($transactions_result && mysqli_num_rows($transactions_result) > 0){ ?>

<?php while($transaction = mysqli_fetch_assoc($transactions_result)){ ?>

<tr>

<td><?php echo htmlspecialchars($transaction['from_account']); ?></td>

<td><?php echo htmlspecialchars($transaction['to_account']); ?></td>

<td>R<?php echo number_format($transaction['amount'], 2); ?></td>

<td><?php echo htmlspecialchars($transaction['transaction_note']); ?></td>

<td><?php echo htmlspecialchars($transaction['created_at']); ?></td>

</tr>

<?php } ?>

<?php }else{ ?>

<tr>
<td colspan="5">No sandbox transactions yet.</td>
</tr>

<?php } ?>

</table>

</div>

<div class="back-links">

<a href="checkout.php">Back to Checkout</a>

<a href="orders.php">View Orders</a>

</div>

</div>

<footer>

<div class="container footer-container">

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

</body>

</html>