<?php

$host = "sql310.infinityfree.com";
$user = "if0_41984564";
$password = "f29HBDJlaGZ";
$database = "if0_41984564_streetmarket_db";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {

    die("Database connection failed: " . mysqli_connect_error());

}

?>