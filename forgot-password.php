<?php

session_start();

include("includes/db.php");

$message = "";
$message_type = "";

if(isset($_POST['reset_password'])){

    $email = trim($_POST['email']);
    $sa_id = trim($_POST['sa_id']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if(empty($email) || empty($sa_id) || empty($new_password) || empty($confirm_password)){

        $message = "Please complete all fields.";
        $message_type = "error-message";

    }elseif($new_password !== $confirm_password){

        $message = "Passwords do not match.";
        $message_type = "error-message";

    }elseif(strlen($new_password) < 6){

        $message = "Password must be at least 6 characters long.";
        $message_type = "error-message";

    }else{

        $check_user = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND sa_id = ?");
        $check_user->bind_param("ss", $email, $sa_id);
        $check_user->execute();
        $result = $check_user->get_result();

        if($result->num_rows == 0){

            $message = "No account found with those details.";
            $message_type = "error-message";

        }else{

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $update_password = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND sa_id = ?");
            $update_password->bind_param("sss", $hashed_password, $email, $sa_id);

            if($update_password->execute()){

                echo "
                <script>
                    alert('Password reset successful. You can now login.');
                    window.location='login.php';
                </script>
                ";
                exit();

            }else{

                $message = "Password reset failed. Please try again.";
                $message_type = "error-message";

            }

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Forgot Password | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

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
<a href="register.php">Register</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="form-container">

<h2>Forgot Password</h2>

<p>
Enter your registered email and South African ID number to reset your password.
</p>

<?php if($message != ""){ ?>

<div class="<?php echo $message_type; ?>">
<?php echo $message; ?>
</div>

<?php } ?>

<form method="POST">

<label>Email Address</label>

<input type="email" name="email" placeholder="Enter your registered email" required>

<label>South African ID Number</label>

<input type="text" name="sa_id" placeholder="Enter your SA ID number" required>

<label>New Password</label>

<input type="password" name="new_password" placeholder="Enter new password" required>

<label>Confirm New Password</label>

<input type="password" name="confirm_password" placeholder="Confirm new password" required>

<button type="submit" name="reset_password">
Reset Password
</button>

</form>

<br>

<p>
Remember your password?
<a href="login.php">Login here</a>
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