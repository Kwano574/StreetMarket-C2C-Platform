<?php

session_start();

include("includes/db.php");

if(!isset($_SESSION['user_id'])){

    header("Location: login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

if(!isset($_GET['id'])){

    header("Location: orders.php");
    exit();

}

$order_id = intval($_GET['id']);

$get_order = "

SELECT *

FROM orders

WHERE order_id='$order_id'

AND buyer_id='$user_id'

";

$result = mysqli_query($conn, $get_order);

if(mysqli_num_rows($result) == 0){

    die("Order not found.");

}

$order = mysqli_fetch_assoc($result);

if($order['delivery_status'] != "processing"){

    die("Cannot cancel shipped order.");

}

$cancel_query = "

UPDATE orders

SET

status='cancelled',
cancelled_by='buyer'

WHERE order_id='$order_id'

";

mysqli_query($conn, $cancel_query);

header("Location: orders.php");

exit();

?>