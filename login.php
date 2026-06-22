<?php

// starting php session
session_start();

// connecting to database
include("includes/db.php");

$message = "";
$success_message = "";

// displaying registration or OTP success message
if(isset($_SESSION['success_message'])){
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// function to check if redirect page is safe
function safeRedirectPage($redirect){

    $redirect = trim($redirect);

    if($redirect == ""){
        return "dashboard.php";
    }

    // blocking external redirect links
    if(strpos($redirect, "http://") !== false || strpos($redirect, "https://") !== false || strpos($redirect, "//") !== false){
        return "dashboard.php";
    }

    // blocking directory movement
    if(strpos($redirect, "../") !== false || strpos($redirect, "..\\") !== false){
        return "dashboard.php";
    }

    // pages users are allowed to be redirected to after login
    $allowed_pages = [
        "dashboard.php",
        "products.php",
        "product-details.php",
        "cart.php",
        "wishlist.php",
        "orders.php",
        "profile.php",
        "verify-seller.php",
        "messages.php"
    ];

    // getting only the page name before the query string
    $page_name = parse_url($redirect, PHP_URL_PATH);

    if(!in_array($page_name, $allowed_pages)){
        return "dashboard.php";
    }

    return $redirect;
}

// storing page user wanted before login
$redirect_page = "dashboard.php";

if(isset($_GET['redirect']) && $_GET['redirect'] != ""){
    $redirect_page = safeRedirectPage($_GET['redirect']);
}

// if user is already logged in, send them to requested page
if(isset($_SESSION['user_id'])){
    header("Location: " . $redirect_page);
    exit();
}

// keeping redirect in form action when login form is submitted
$form_action = "login.php";

if($redirect_page != "dashboard.php"){
    $form_action = "login.php?redirect=" . urlencode($redirect_page);
}

// processing login details when login button is clicked
if(isset($_POST['login'])){

    // getting form data
    $email = mysqli_real_escape_string($conn, strtolower(trim($_POST['email'])));
    $password = trim($_POST['password']);

    // checking if login details are empty
    if(empty($email) || empty($password)){

        $message = "Please enter your email and password.";

    }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){

        $message = "Please enter a valid email address.";

    }else{

        // searching for user in database table users
        $user_query = "
        SELECT *
        FROM users
        WHERE email='$email'
        LIMIT 1
        ";

        $user_result = mysqli_query($conn, $user_query);

        if($user_result && mysqli_num_rows($user_result) > 0){

            $user = mysqli_fetch_assoc($user_result);

            // checking if account is suspended
            if(isset($user['status']) && $user['status'] == "Suspended"){

                $message = "Your account has been suspended. Please contact StreetMarket support.";

            // checking if account is deleted
            }elseif(isset($user['status']) && $user['status'] == "Deleted"){

                $message = "This account no longer exists.";

            // checking entered password against hashed password
            }elseif(password_verify($password, $user['password'])){

                // randomly generating OTP code
                $otp_code = rand(100000, 999999);

                // storing user details temporarily before OTP verification
                $_SESSION['pending_user_id'] = $user['user_id'];
                $_SESSION['pending_full_name'] = $user['full_name'];
                $_SESSION['pending_email'] = $user['email'];
                $_SESSION['login_otp'] = $otp_code;
                $_SESSION['login_otp_expiry'] = time() + 300;

                // storing safe redirect page for after OTP verification
                $_SESSION['pending_redirect'] = $redirect_page;

                // redirecting user to OTP verification page
                header("Location: verify-login.php");
                exit();

            }else{

                $message = "Incorrect password or email.";

            }

        }else{

            $message = "Incorrect password or email.";

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

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

.security-note{
    background:#eff6ff;
    color:#1e3a8a;
    border-left:5px solid #2563eb;
    padding:15px;
    border-radius:10px;
    margin-top:20px;
    font-size:14px;
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

<a href="register.php">Register</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="form-container">

<h2>Login</h2>

<p>
Login to buy, sell and manage your StreetMarket account.
</p>

<?php if($success_message != ""){ ?>

<div class="success-message">

<?php echo htmlspecialchars($success_message); ?>

</div>

<?php } ?>

<?php if($message != ""){ ?>

<div class="error-message">

<?php echo htmlspecialchars($message); ?>

</div>

<?php } ?>

<?php if($redirect_page != "dashboard.php"){ ?>

<div class="security-note">
Please login first. After verification, you will be taken back to the page you wanted to access.
</div>

<?php } ?>

<!-- login form -->
<form method="POST" action="<?php echo htmlspecialchars($form_action); ?>">

<label for="email">Email Address</label>

<input
type="email"
id="email"
name="email"
placeholder="Enter your email"
required>

<label for="password">Password</label>

<input
type="password"
id="password"
name="password"
placeholder="Enter your password"
required>

<button type="submit" name="login">
Login
</button>

</form>

<br>

<p>
<a href="forgot-password.php">Forgot Password?</a>
</p>

<p>
Do not have an account?
<a href="register.php">Register here</a>
</p>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>
<a href="safety.php">Safety Centre</a>
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