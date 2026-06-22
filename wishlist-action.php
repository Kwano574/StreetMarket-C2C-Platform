<?php
// starting php session
session_start();

// connecting to database
include("includes/db.php");

// protecting user session
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

// storing logged-in user id
$user_id = intval($_SESSION['user_id']);

// adding product to wishlist
if(isset($_GET['add'])){
    $product_id = intval($_GET['add']);

    // checking if product is available and approved
    $check_product = "
    SELECT *
    FROM product
    WHERE product_id='$product_id'
    AND status='available'
    AND moderation_status='approved'
    AND quantity > 0
    LIMIT 1
    ";

    $product_result = mysqli_query($conn, $check_product);

    if($product_result && mysqli_num_rows($product_result) > 0){
        $product = mysqli_fetch_assoc($product_result);

        // stopping user from saving their own product
        if(intval($product['user_id']) == $user_id){
            header("Location: product-details.php?id=".$product_id."&wishlist=own_product");
            exit();
        }

        // checking if product is already in wishlist
        $check_wishlist = "
        SELECT wishlist_id
        FROM wishlist
        WHERE user_id='$user_id'
        AND product_id='$product_id'
        LIMIT 1
        ";

        $wishlist_result = mysqli_query($conn, $check_wishlist);

        if($wishlist_result && mysqli_num_rows($wishlist_result) == 0){
            $insert_query = "
            INSERT INTO wishlist(user_id, product_id)
            VALUES('$user_id', '$product_id')
            ";

            if(mysqli_query($conn, $insert_query)){
                header("Location: product-details.php?id=".$product_id."&wishlist=added");
                exit();
            }else{
                header("Location: product-details.php?id=".$product_id."&wishlist=error");
                exit();
            }
        }else{
            header("Location: product-details.php?id=".$product_id."&wishlist=exists");
            exit();
        }
    }else{
        header("Location: products.php?wishlist=unavailable");
        exit();
    }
}

// removing product from wishlist
if(isset($_GET['remove_product'])){
    $product_id = intval($_GET['remove_product']);

    $delete_query = "
    DELETE FROM wishlist
    WHERE user_id='$user_id'
    AND product_id='$product_id'
    ";

    mysqli_query($conn, $delete_query);

    header("Location: product-details.php?id=".$product_id."&wishlist=removed");
    exit();
}

// redirecting user if page is opened without action
header("Location: products.php");
exit();
?>