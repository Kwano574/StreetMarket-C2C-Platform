<?php

// starting session to allow access to login verification data
session_start();

// variable to store error messages
$message = "";

// checking if user is coming from login page
if(!isset($_SESSION['pending_user_id']) || !isset($_SESSION['login_otp']) || !isset($_SESSION['login_otp_expiry'])){

    header("Location: login.php");
    exit();

}

// function to check if redirect page is safe
function safeRedirectPage($redirect){

    $redirect = trim($redirect);

    // default page if redirect is empty
    if($redirect == ""){
        return "dashboard.php";
    }

    // blocking external links
    if(strpos($redirect, "http://") !== false || strpos($redirect, "https://") !== false || strpos($redirect, "//") !== false){
        return "dashboard.php";
    }

    // blocking directory movement
    if(strpos($redirect, "../") !== false || strpos($redirect, "..\\") !== false){
        return "dashboard.php";
    }

    // allow only pages inside this project
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

    // extracting the page name before query string
    $page_name = parse_url($redirect, PHP_URL_PATH);

    if(!in_array($page_name, $allowed_pages)){
        return "dashboard.php";
    }

    return $redirect;

}

// masking user email for display
$masked_email = "registered email";

if(isset($_SESSION['pending_email'])){

    $email = $_SESSION['pending_email'];
    $parts = explode("@", $email);

    if(count($parts) == 2){

        $name_part = $parts[0];
        $domain_part = $parts[1];

        if(strlen($name_part) >= 2){
            $masked_email = substr($name_part, 0, 2) . "****@" . $domain_part;
        }else{
            $masked_email = substr($name_part, 0, 1) . "****@" . $domain_part;
        }

    }

}

// processing OTP when user clicks verify button
if(isset($_POST['verify_code'])){

    $entered_code = trim($_POST['otp_code']);

    // checking if OTP input is empty
    if($entered_code == ""){

        $message = "Please enter the verification code.";

    // checking if OTP contains 6 digits
    }elseif(!preg_match("/^[0-9]{6}$/", $entered_code)){

        $message = "Verification code must contain 6 digits.";

    // checking if OTP is expired
    }elseif(time() > $_SESSION['login_otp_expiry']){

        // clearing temporary login data
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_full_name']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['login_otp']);
        unset($_SESSION['login_otp_expiry']);
        unset($_SESSION['pending_redirect']);

        $_SESSION['success_message'] = "Verification code expired. Please login again.";
        header("Location: login.php");
        exit();

    // checking if OTP is correct
    }elseif($entered_code == $_SESSION['login_otp']){

        // storing redirect before clearing temporary session data
        $redirect = "dashboard.php";

        if(isset($_SESSION['pending_redirect']) && $_SESSION['pending_redirect'] != ""){
            $redirect = safeRedirectPage($_SESSION['pending_redirect']);
        }

        // regenerating session id after successful verification
        session_regenerate_id(true);

        // creating real logged in user session
        $_SESSION['user_id'] = $_SESSION['pending_user_id'];
        $_SESSION['full_name'] = $_SESSION['pending_full_name'];

        if(isset($_SESSION['pending_email'])){
            $_SESSION['email'] = $_SESSION['pending_email'];
        }

        // storing session activity time
        $_SESSION['last_activity'] = time();

        // clearing temporary login data
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_full_name']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['login_otp']);
        unset($_SESSION['login_otp_expiry']);
        unset($_SESSION['pending_redirect']);

        // redirecting user to the page they wanted before login
        header("Location: " . $redirect);
        exit();

    }else{

        $message = "Invalid verification code.";

    }

}

// processing cancel login button
if(isset($_POST['cancel_login'])){

    // clearing temporary login data
    unset($_SESSION['pending_user_id']);
    unset($_SESSION['pending_full_name']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['login_otp']);
    unset($_SESSION['login_otp_expiry']);
    unset($_SESSION['pending_redirect']);

    header("Location: login.php");
    exit();

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login Verification | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.error-message{
    background:#fee2e2;
    color:#991b1b;
    border-left:5px solid #dc2626;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
    font-weight:bold;
}

.demo-code-box{
    background:#eff6ff;
    color:#1e3a8a;
    border-left:5px solid #2563eb;
    padding:18px;
    border-radius:10px;
    margin-bottom:20px;
    font-weight:bold;
}

.demo-code{
    font-size:28px;
    letter-spacing:4px;
    margin-top:10px;
    display:inline-block;
}

.cancel-btn{
    background:#c62828 !important;
    margin-top:10px;
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
<a href="login.php">Login</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="form-container">

<h2>Login Verification</h2>

<p>
A one-time verification code was generated for <?php echo htmlspecialchars($masked_email); ?>.
</p>

<div class="demo-code-box">

MFA Demo Code:

<br>

<span class="demo-code">
<?php echo htmlspecialchars($_SESSION['login_otp']); ?>
</span>

<br><br>

<small>
This simulates an email-based verification code for project demonstration.
The code expires after 5 minutes.
</small>

</div>

<?php if($message != ""){ ?>

<div class="error-message">

<?php echo htmlspecialchars($message); ?>

</div>

<?php } ?>

<form method="POST">

<label for="otp_code">Verification Code</label>

<input
type="text"
id="otp_code"
name="otp_code"
maxlength="6"
placeholder="Enter 6-digit code"
inputmode="numeric"
required>

<button type="submit" name="verify_code">
Verify Login
</button>

</form>

<form method="POST">

<button type="submit" name="cancel_login" class="cancel-btn">
Cancel Login
</button>

</form>

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

<p>Copyright © 2026 StreetMarket. All Rights Reserved.</p>

</div>

</footer>

</body>

</html>