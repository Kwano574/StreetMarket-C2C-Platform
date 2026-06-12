<?php

session_start();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Shipping Guide | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.shipping-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
gap:25px;
margin-top:30px;
}

.shipping-card{
background:#fff;
padding:25px;
border-radius:16px;
box-shadow:0 2px 12px rgba(0,0,0,0.08);
}

.shipping-card h3{
margin-bottom:12px;
}

.shipping-price{
font-size:22px;
font-weight:bold;
margin:15px 0;
color:#111;
}

.guide-list{
background:#fff;
padding:25px;
border-radius:16px;
box-shadow:0 2px 12px rgba(0,0,0,0.08);
margin-top:25px;
}

.guide-list li{
margin-bottom:12px;
line-height:1.6;
}

.notice-box{
background:#fff3cd;
color:#856404;
padding:20px;
border-radius:14px;
margin-top:25px;
line-height:1.6;
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

<a href="index.php">Home</a>
<a href="products.php">Products</a>
<a href="sellercenter.php">Seller Support</a>
<a href="help.php">Help</a>

<?php if(isset($_SESSION['user_id'])){ ?>

<a href="dashboard.php">Dashboard</a>
<a href="logout.php">Logout</a>

<?php }else{ ?>

<a href="login.php">Login</a>
<a href="register.php">Register</a>

<?php } ?>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="page-intro">

<h2>Shipping Guidance</h2>

<p>
StreetMarket provides delivery guidance to help buyers and sellers complete transactions safely.
This page explains common courier options, packaging tips, and delivery steps.
</p>

</div>

<div class="notice-box">

<strong>Important:</strong>
StreetMarket does not directly integrate with courier companies. Buyers and sellers must agree on the delivery method before completing the order. The information below is provided as guidance only.

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>Recommended Delivery Options</h2>

<p>
These are common delivery services used in South Africa for small and medium parcels.
</p>

</div>

<div class="shipping-grid">

<div class="shipping-card">

<h3>PAXI</h3>

<p>
Useful for affordable store-to-store parcel delivery through selected retail locations.
</p>

<p class="shipping-price">
From ± R60
</p>

<p>
Best for clothing, small accessories, shoes, and lightweight items.
</p>

</div>

<div class="shipping-card">

<h3>PostNet</h3>

<p>
Useful for counter-to-counter parcel delivery between PostNet branches.
</p>

<p class="shipping-price">
From ± R109
</p>

<p>
Best for documents, electronics accessories, clothing, and medium parcels.
</p>

</div>

<div class="shipping-card">

<h3>The Courier Guy</h3>

<p>
Useful for door-to-door courier delivery across many areas in South Africa.
</p>

<p class="shipping-price">
From ± R100
</p>

<p>
Best for electronics, appliances, business items, and fragile products.
</p>

</div>

<div class="shipping-card">

<h3>Aramex</h3>

<p>
Useful for parcel delivery using drop boxes and courier collection options.
</p>

<p class="shipping-price">
From ± R99
</p>

<p>
Best for small parcels, documents, and packaged marketplace items.
</p>

</div>

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>Seller Packaging Guide</h2>

<p>
Sellers should package products carefully before handing them to a courier.
</p>

</div>

<div class="guide-list">

<ul>

<li>Clean the product before packaging it.</li>
<li>Use strong packaging material such as bubble wrap, cardboard boxes, or padded envelopes.</li>
<li>For fragile products, add extra protection and clearly mark the parcel as fragile.</li>
<li>Take photos of the product before shipping as proof of condition.</li>
<li>Confirm the buyer’s delivery details before sending the parcel.</li>
<li>Send the tracking number to the buyer through StreetMarket messages.</li>

</ul>

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="section-title">

<h2>Buyer Delivery Safety Tips</h2>

<p>
Buyers should confirm delivery details before payment and track the parcel after shipment.
</p>

</div>

<div class="guide-list">

<ul>

<li>Confirm the seller’s verification status before purchasing.</li>
<li>Confirm the delivery method and expected cost before checkout.</li>
<li>Use StreetMarket messages to keep a record of delivery communication.</li>
<li>Ask for the tracking number once the seller ships the product.</li>
<li>Only confirm delivery after receiving and checking the product.</li>
<li>Report suspicious behaviour to StreetMarket support.</li>

</ul>

</div>

</div>

</section>

<section class="section-spacing">

<div class="container">

<div class="info-box">

<h2>How Delivery Works on StreetMarket</h2>

<ul>

<li>The buyer places an order on StreetMarket.</li>
<li>The seller prepares and packages the product.</li>
<li>The buyer and seller agree on the delivery method.</li>
<li>The seller updates the delivery status from the order tracking page.</li>
<li>The buyer tracks the order and confirms once the product is received.</li>
<li>After confirmation, the buyer can review the product.</li>

</ul>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>
<a href="safety.php">Safety Centre</a>
<a href="sellercenter.php">Seller Support</a>
<a href="help.php">Help</a>

</nav>

<p>
Copyright © 2026 StreetMarket.
All Rights Reserved.
</p>

</div>

</footer>

</body>

</html>