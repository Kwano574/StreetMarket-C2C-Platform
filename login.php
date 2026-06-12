<?php

session_start();

include("includes/db.php");

$message = "";
$success_message = "";

if(isset($_SESSION['success_message'])){

    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);

}

if(isset($_POST['login'])){

    $email = mysqli_real_escape_string($conn, strtolower(trim($_POST['email'])));
    $password = trim($_POST['password']);

    if(empty($email) || empty($password)){

        $message = "Please enter your email and password.";

    }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){

        $message = "Please enter a valid email address.";

    }else{

        $user_query = "
        SELECT *
        FROM users
        WHERE email='$email'
        LIMIT 1
        ";

        $user_result = mysqli_query($conn, $user_query);

        if($user_result && mysqli_num_rows($user_result) > 0){

            $user = mysqli_fetch_assoc($user_result);

            if(isset($user['status']) && $user['status'] == "Suspended"){

                $message = "Your account has been suspended. Please contact StreetMarket support.";

            }elseif(isset($user['status']) && $user['status'] == "Deleted"){

                $message = "This account no longer exists.";

            }elseif(password_verify($password, $user['password'])){

                $otp_code = rand(100000, 999999);

                $_SESSION['pending_user_id'] = $user['user_id'];
                $_SESSION['pending_full_name'] = $user['full_name'];
                $_SESSION['pending_email'] = $user['email'];
                $_SESSION['login_otp'] = $otp_code;
                $_SESSION['login_otp_expiry'] = time() + 300;

                if(isset($_GET['redirect']) && $_GET['redirect'] != ""){

                    $redirect = $_GET['redirect'];

                    if(strpos($redirect, "http") === false && strpos($redirect, "//") === false){
                        $_SESSION['pending_redirect'] = $redirect;
                    }

                }else{

                    $_SESSION['pending_redirect'] = "dashboard.php";

                }

                header("Location: verify-login.php");
                exit();

            }else{

                $message = "Incorrect password.";

            }

        }else{

            $message = "Account not found.";

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

            <form method="POST">

                <label>Email Address</label>

                <input
                type="email"
                name="email"
                placeholder="Enter your email"
                required>

                <label>Password</label>

                <input
                type="password"
                name="password"
                placeholder="Enter your password"
                required>

                <button
                type="submit"
                name="login">

                    Login

                </button>

            </form>

            <div class="security-note">
                After entering the correct password, you will be asked to enter a one-time verification code.
            </div>

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