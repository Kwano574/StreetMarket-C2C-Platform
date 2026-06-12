<?php

session_start();

include("includes/db.php");

/* =========================================
   SESSION PROTECTION
========================================= */

if(!isset($_SESSION['user_id'])){

    header("Location: login.php");

    exit();

}

/* =========================================
   USER SESSION DATA
========================================= */

$user_id =
$_SESSION['user_id'];

$user_name =
$_SESSION['full_name'];

/* =========================================
   TOTAL PRODUCTS
========================================= */

$product_count_query = "

SELECT COUNT(*) AS total_products

FROM product

WHERE user_id='$user_id'

";

$product_count_result =
mysqli_query(
$conn,
$product_count_query
);

$product_count =
mysqli_fetch_assoc(
$product_count_result
);

/* =========================================
   TOTAL ORDERS
========================================= */

$order_count_query = "

SELECT COUNT(*) AS total_orders

FROM orders

WHERE buyer_id='$user_id'

";

$order_count_result =
mysqli_query(
$conn,
$order_count_query
);

$order_count =
mysqli_fetch_assoc(
$order_count_result
);

/* =========================================
   DELIVERIES IN PROGRESS
========================================= */

$delivery_query = "

SELECT COUNT(*) AS total_deliveries

FROM orders

WHERE buyer_id='$user_id'
AND status='pending'

";

$delivery_result =
mysqli_query(
$conn,
$delivery_query
);

$delivery_count =
mysqli_fetch_assoc(
$delivery_result
);

/* =========================================
   TOTAL SALES
========================================= */

$sales_query = "

SELECT SUM(total_amount)
AS total_sales

FROM orders

WHERE buyer_id='$user_id'

";

$sales_result =
mysqli_query(
$conn,
$sales_query
);

$sales =
mysqli_fetch_assoc(
$sales_result
);

$total_sales =
$sales['total_sales'];

if($total_sales == NULL){

    $total_sales = 0;

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
    content="width=device-width, initial-scale=1.0">

    <title>
        Dashboard | StreetMarket
    </title>

    <link rel="stylesheet"
    href="css/style.css">

</head>

<body>

<!-- =========================================
     HEADER
========================================= -->

<header>

    <div class="container header-container">

        <div class="logo-section">

            <img
            src="images/logo.png"
            alt="StreetMarket Logo">

            <h1>
                StreetMarket
            </h1>

        </div>

        <nav>

            

            <a href="products.php">
                Products
            </a>
         

            <a href="cart.php">
                Cart
            </a>

            <a href="orders.php">
                Orders
            </a>
            
             <a href="manage-deliveries.php">
                Manage Deliveries
            </a>
            

            <a href="messages.php">
                &#128172; Chat
            </a>
            
            <a href="user-profile.php">

                    &#128100; Profile

                </a>
             

            <a href="login.php">
                Logout
            </a>
            <a href="notifications.php">Notifications</a>

        </nav>

    </div>

</header>

<!-- =========================================
     DASHBOARD HERO
========================================= -->

<section class="hero dashboard-hero">

    <div class="container">

        <div class="dashboard-welcome">

            <span class="product-badge">

                ✔ Verified StreetMarket Account

            </span>

            <h1>

                Welcome, 

                <?php

                echo htmlspecialchars(
                $user_name
                );

                ?>

            </h1>

            <p>

                Manage your profile,products,
                purchases, orders,
                deliveries and conversations
                from your StreetMarket dashboard.

            </p>

            <div class="hero-buttons">
                
                <a href="user-profile.php">

                    View My Profile

                </a>
                

                <a href="products.php">

                    Browse Products

                </a>

                <a href="add-products.php">

                    Sell Product

                </a>
                <a href="my-listings.php">

                  Manage My Products

                </a>
                
                <a href="manage-deliveries.php">

                  Manage My Deliveries

                </a>

            </div>

        </div>

    </div>

</section>

<!-- =========================================
     DASHBOARD OVERVIEW
========================================= -->

<section class="section-spacing dashboard-section">

    <div class="container">

        <div class="section-title">

            <h2>
                Dashboard Overview
            </h2>

        </div>

        <div class="dashboard-cards">

            <!-- ACTIVE PRODUCTS -->

            <div class="dashboard-card">

                <div class="dashboard-icon">
                    📦
                </div>

                <h3>

                    <?php

                    echo $product_count['total_products'];

                    ?>

                </h3>

                <p>
                    Active Listings
                </p>

            </div>

            <!-- ORDERS -->

            <div class="dashboard-card">

                <div class="dashboard-icon">
                    🛒
                </div>

                <h3>

                    <?php

                    echo $order_count['total_orders'];

                    ?>

                </h3>

                <p>
                    Orders Received
                </p>

            </div>

            <!-- DELIVERIES -->

            <div class="dashboard-card">

                <div class="dashboard-icon">
                    🚚
                </div>

                <h3>

                    <?php

                    echo $delivery_count['total_deliveries'];

                    ?>

                </h3>

                <p>
                    Deliveries In Progress
                </p>

            </div>

            <!-- SALES -->

            <div class="dashboard-card">

                <div class="dashboard-icon">
                    💰
                </div>

                <h3>

                    R

                    <?php

                    echo number_format(
                    $total_sales,
                    2
                    );

                    ?>

                </h3>

                <p>
                    Total Sales
                </p>

            </div>

        </div>

    </div>

</section>

<!-- =========================================
     QUICK ACTIONS
========================================= -->

<section class="section-spacing">

    <div class="container">

        <div class="section-title">

            <h2>
                Quick Actions
            </h2>

            <p>

                Access your most important
                features quickly.

            </p>

        </div>

        <div class="feature-grid">

            <!-- ADD PRODUCT -->

            <a href="add-products.php"
            class="feature-card">

                <div class="feature-icon">
                    ➕
                </div>

                <h3>
                    Add Product
                </h3>

                <p>

                    Upload products for
                    buyers to purchase.

                </p>

            </a>

            <!-- MY LISTINGS -->

            <a href="my-listings.php"
            class="feature-card">

                <div class="feature-icon">
                    🏪
                </div>

                <h3>
                    My Products
                </h3>

                <p>

                    View and manage
                    your uploaded products.

                </p>

            </a>

            <!-- ORDERS -->

            <a href="orders.php"
            class="feature-card">

                <div class="feature-icon">
                    📦
                </div>

                <h3>
                    Track Orders
                </h3>

                <p>

                    Monitor buyer
                    purchases and deliveries.

                </p>

            </a>

            <!-- MESSAGES -->

            <a href="messages.php"
            class="feature-card">

                <div class="feature-icon">
                    💬
                </div>

                <h3>
                    Chat With Assistant or Sellers
                </h3>

                <p>

                    Chat with buyers
                    and sellers securely.

                </p>

            </a>

        </div>

    </div>

</section>

<!-- =========================================
     RECENT ORDERS
========================================= -->

<section class="section-spacing">

    <div class="container">

        <div class="section-title">

            <h2>
                Recent Orders
            </h2>

            <p>

                Monitor recent purchases
                and delivery activity.

            </p>

        </div>

        <table>

            <thead>

                <tr>

                    <th>
                        Order ID
                    </th>

                    <th>
                        Product
                    </th>

                    <th>
                        Buyer
                    </th>

                    <th>
                        Payment Status
                    </th>

                    <th>
                        Delivery Status
                    </th>

                </tr>

            </thead>

            <tbody>

<?php

$recent_orders_query = "

SELECT

orders.order_id,
orders.payment_status,
orders.delivery_status,
orders.order_date,

product.product_name,

users.full_name

FROM orders

INNER JOIN product
ON orders.product_id = product.product_id

INNER JOIN users
ON orders.buyer_id = users.user_id

WHERE orders.seller_id='$user_id'

ORDER BY orders.order_date DESC

LIMIT 5

";

$recent_orders_result =
mysqli_query(
$conn,
$recent_orders_query
);

if(mysqli_num_rows(
$recent_orders_result
) > 0){

while($row =
mysqli_fetch_assoc(
$recent_orders_result
)){

?>

<tr>

    <td>

        #SM<?php echo $row['order_id']; ?>

    </td>

    <td>

        <?php

        echo htmlspecialchars(
        $row['product_name']
        );

        ?>

    </td>

    <td>

        <?php

        echo htmlspecialchars(
        $row['full_name']
        );

        ?>

    </td>

<td>

<?php

echo ucfirst(
$row['payment_status']
);

?>

</td>

 <td>

<?php

echo ucwords(
str_replace(
"_",
" ",
$row['delivery_status']
)
);

?>

</td>

</tr>

<?php

}

}

else{

?>

<tr>

    <td colspan="5">

        No recent orders found.

    </td>

</tr>

<?php

}

?>

            </tbody>

        </table>

    </div>

</section>

<!-- =========================================
     DELIVERY COMMUNICATION
========================================= -->

<section class="section-spacing">

    <div class="container">

        <div class="info-box">

            <h2>
                Delivery Communication
            </h2>

            <p>

                Buyers and sellers can
                communicate during delivery.
                Sellers can update order status
                while buyers can confirm delivery.

            </p>

            <ul>

                <li>
                    Real-time delivery updates
                </li>

                <li>
                    Buyer delivery confirmation
                </li>

                <li>
                    Order tracking support
                </li>

                <li>
                    Secure communication system
                </li>

            </ul>

        </div>

    </div>

</section>

<!-- =========================================
     ACCOUNT SECURITY
========================================= -->

<section class="section-spacing">

    <div class="container">

        <div class="info-box">

            <h2>
                Account Security
            </h2>

            <p>

                StreetMarket protects users
                through identity verification
                and account monitoring systems.

            </p>

            <ul>

                <li>
                    South African ID verification
                    during registration
                </li>

                <li>
                    Duplicate account prevention system
                </li>

                <li>
                    Email and password authentication
                </li>

                <li>
                    Secure sandbox payment integration
                </li>

                <li>
                    Admin monitoring and fraud prevention
                </li>

            </ul>

        </div>

    </div>

</section>

<!-- =========================================
     ACCESSIBILITY FEATURES
========================================= -->

<section class="section-spacing">

    <div class="container">

        <div class="info-box">

            <h2>
                Accessibility Features
            </h2>

            <p>

                StreetMarket is designed
                to support users with
                different accessibility needs.

            </p>

            <ul>

                <li>
                    Responsive layouts for all devices
                </li>

                <li>
                    Large readable text and buttons
                </li>

                <li>
                    Keyboard-friendly navigation
                </li>

                <li>
                    Image alternative text support
                </li>

                <li>
                    Simple and clear interface design
                </li>

            </ul>

        </div>

    </div>

</section>

<!-- =========================================
     FOOTER
========================================= -->

<footer>

    <div class="container footer-container">

        <nav>

            <a href="about.php">
                About
            </a>

            <a href="safety.php">
                Safety Centre
            </a>

            <a href="sellercenter.php">
                Seller Support
            </a>
            <a href="shipping-guide.php">Shipping Guide</a>

            <a href="policies.php">
                Policies
            </a>

            <a href="help.php">
                Help
            </a>

        </nav>

        <p>

            Copyright © 2026 StreetMarket.
            All Rights Reserved.

        </p>

    </div>

</footer>

<script src="js/script.js"></script>

</body>
</html>