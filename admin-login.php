<?php


//starting php session
session_start();

//connecting page to database for data retrieval and storing
include("includes/db.php");

$message = "";
$message_type = "error";

//protecting page session by allowing user to accesss dashbpard if they are already logged in
if(isset($_SESSION['admin_id'])){
    header("Location: Admin-dashboard.php");
    exit();
}


//validating thorugh admin login details 
if(isset($_POST['admin_login'])){

    $email = mysqli_real_escape_string($conn, strtolower(trim($_POST['email'])));
    $password = trim($_POST['password']);

    if($email == "" || $password == ""){

        $message = "Please enter your admin email and password.";

    }elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){

        $message = "Please enter a valid admin email address.";

    }else{

        $admin_query = "
        SELECT *
        FROM admin_users
        WHERE email='$email'
        LIMIT 1
        ";

        $admin_result = mysqli_query($conn, $admin_query);

        if($admin_result && mysqli_num_rows($admin_result) > 0){

            $admin = mysqli_fetch_assoc($admin_result);

            $admin_id = intval($admin['admin_id']);
            $status = isset($admin['status']) ? $admin['status'] : "Active";
            $failed_attempts = isset($admin['failed_attempts']) ? intval($admin['failed_attempts']) : 0;
            $locked_until = isset($admin['locked_until']) ? $admin['locked_until'] : NULL;

            if($status != "Active"){

                $message = "This admin account is suspended. Contact the Super Admin.";

            }elseif(!empty($locked_until) && strtotime($locked_until) > time()){

                $message = "This admin account is temporarily locked. Try again after " . date("H:i", strtotime($locked_until)) . ".";

            }elseif(password_verify($password, $admin['password'])){

                mysqli_query($conn, "
                UPDATE admin_users
                SET
                failed_attempts=0,
                locked_until=NULL,
                last_login=NOW()
                WHERE admin_id='$admin_id'
                ");

                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];

                header("Location: Admin-dashboard.php");
                exit();

            }else{

                $failed_attempts++;

                if($failed_attempts >= 5){

                    mysqli_query($conn, "
                    UPDATE admin_users
                    SET
                    failed_attempts='$failed_attempts',
                    locked_until=DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    WHERE admin_id='$admin_id'
                    ");

                    $message = "Too many failed login attempts. Account locked for 15 minutes.";

                }else{

                    mysqli_query($conn, "
                    UPDATE admin_users
                    SET failed_attempts='$failed_attempts'
                    WHERE admin_id='$admin_id'
                    ");

                    $remaining = 5 - $failed_attempts;

                    $message = "Incorrect password. Attempts remaining: " . $remaining . ".";

                }

            }

        }else{

            $message = "Admin account not found.";

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Login | StreetMarket</title>

<link rel="stylesheet" href="css/style.css">

<style>

.login-wrapper{
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
background:#f4f4f4;
padding:20px;
}

.admin-login-card{
width:100%;
max-width:480px;
background:white;
padding:35px;
border-radius:18px;
box-shadow:0 2px 20px rgba(0,0,0,0.12);
}

.admin-login-logo{
text-align:center;
margin-bottom:25px;
}

.admin-login-logo img{
width:90px;
height:90px;
object-fit:contain;
}

.admin-login-logo h1{
margin-top:10px;
font-size:28px;
}

.admin-login-logo p{
color:#666;
margin-top:5px;
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

.admin-security-note{
background:#f8fafc;
border-left:5px solid #2563eb;
padding:15px;
border-radius:10px;
margin-top:20px;
font-size:14px;
color:#1e3a8a;
}

</style>

</head>

<body>

<div class="login-wrapper">
<div class="admin-login-card">
<div class="admin-login-logo">
<img src="images/logo.png" alt="StreetMarket Logo">
<h1>Admin Login</h1>
<p>StreetMarket administration access</p>
</div>
    
    
<?php if($message != ""){ ?>
<div class="error-message">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>
<!--login form-->
<form method="POST">
<label>Admin Email</label>
<input type="email" name="email" placeholder="Enter admin email" required>
<label>Password</label> 
<input type="password" name="password" placeholder="Enter password" required>
<button type="submit" name="admin_login"> Login </button> 
</form>

<div class="admin-security-note">
For security, admin accounts are temporarily locked after 5 failed login attempts.
</div>
</div>
</div>
</body>
</html>