<?php

session_start();

include("includes/db.php");

if(!isset($_SESSION['user_id'])){

header("Location: login.php");
exit();

}

$user_id = $_SESSION['user_id'];

$product_id = intval($_GET['id']);

$check = mysqli_query($conn,"

SELECT *

FROM wishlist

WHERE user_id='$user_id'
AND product_id='$product_id'

");

if(mysqli_num_rows($check) == 0){

mysqli_query($conn,"

INSERT INTO wishlist(

user_id,
product_id

)

VALUES(

'$user_id',
'$product_id'

)

");

}

header("Location: products.php");

?>