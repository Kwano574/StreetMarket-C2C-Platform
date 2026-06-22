<?php

session_start();

/* =========================================
   CREATE CART SESSION
========================================= */

if(!isset($_SESSION['cart'])){

    $_SESSION['cart'] = [];

}

/* =========================================
   ADD PRODUCT
========================================= */

if(isset($_GET['id'])){

    $product_id =
    $_GET['id'];

    if(!in_array(
    $product_id,
    $_SESSION['cart']
    )){

        $_SESSION['cart'][] =
        $product_id;

    }

}

/* =========================================
   REDIRECT
========================================= */

header("Location: cart.php");

exit();

?>