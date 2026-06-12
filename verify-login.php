<?php

session_start();

$message = "";

if(!isset($_SESSION['pending_user_id']) || !isset($_SESSION['login_otp'])){

    header("Location: login.php");
    exit();

}

$masked_email = "registered email";

if(isset($_SESSION['pending_email'])){

    $email = $_SESSION['pending_email'];
    $parts = explode("@", $email);

    if(count($parts) == 2){

        $name_part = $parts[0];
        $domain_part = $parts[1];

        $masked_email = substr($name_part, 0, 2) . "****@" . $domain_part;

    }

}

if(isset($_POST['verify_code'])){

    $entered_code = trim($_POST['otp_code']);

    if(time() > $_SESSION['login_otp_expiry']){

        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_full_name']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['login_otp']);
        unset($_SESSION['login_otp_expiry']);
        unset($_SESSION['pending_redirect']);

        $_SESSION['success_message'] = "Verification code expired. Please login again.";
        header("Location: login.php");
        exit();

    }elseif($entered_code == $_SESSION['login_otp']){

        session_regenerate_id(true);

        $_SESSION['user_id'] = $_SESSION['pending_user_id'];
        $_SESSION['full_name'] = $_SESSION['pending_full_name'];

        $redirect = "dashboard.php";

        if(isset($_SESSION['pending_redirect']) && $_SESSION['pending_redirect'] != ""){
            $redirect = $_SESSION['pending_redirect'];
        }

        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_full_name']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['login_otp']);
        unset($_SESSION['login_otp_expiry']);
        unset($_SESSION['pending_redirect']);

        header("Location: " . $redirect);
        exit();

    }else{

        $message = "Invalid verification code.";

    }

}

if(isset($_POST['cancel_login'])){

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

                <label>Verification Code</label>

                <input
                type="text"
                name="otp_code"
                maxlength="6"
                placeholder="Enter 6-digit code"
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

        <p>
            Copyright © 2026 StreetMarket.
            All Rights Reserved.
        </p>

    </div>

</footer>

</body>

</html>