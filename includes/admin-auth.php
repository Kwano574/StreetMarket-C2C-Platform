<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

if(!isset($_SESSION['admin_id'])){
    header("Location: admin-login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? "Admin";
$admin_role = $_SESSION['admin_role'] ?? "";

function hasAdminRole($allowed_roles){
    $current_role = $_SESSION['admin_role'] ?? "";

    if($current_role == "super_admin"){
        return true;
    }

    return in_array($current_role, $allowed_roles);
}

function requireAdminRole($allowed_roles){
    if(!hasAdminRole($allowed_roles)){
        die("Access denied. You do not have permission to access this admin page.");
    }
}

?>