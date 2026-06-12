<?php

session_start();

include("includes/db.php");

/* =========================================
   SESSION PROTECTION
========================================= */

if(!isset($_SESSION['user_id'])){

    header("Location: login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

$message = "";

/* =========================================
   SUBMIT REPORT
========================================= */

if(isset($_POST['submit_report'])){

    $reported_user = mysqli_real_escape_string(
    $conn,
    trim($_POST['reported_user'])
    );

    $report_reason = mysqli_real_escape_string(
    $conn,
    trim($_POST['report_reason'])
    );

    $report_details = mysqli_real_escape_string(
    $conn,
    trim($_POST['report_details'])
    );

    $insert_report = "

    INSERT INTO reports(

        user_id,
        reported_user,
        report_reason,
        report_details,
        report_status

    )

    VALUES(

        '$user_id',
        '$reported_user',
        '$report_reason',
        '$report_details',
        'pending'

    )

    ";

    if(mysqli_query(
        $conn,
        $insert_report
    )){

        $message =
        "Report submitted successfully.";

    }

    else{

        $message =
        "Failed to submit report.";

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>
Report User | StreetMarket
</title>

<link rel="stylesheet"
href="css/style.css">

</head>

<body>

<header>

<div class="container header-container">

<div class="logo-section">

<img
src="images/logo.png"
alt="Logo">

<h1>
StreetMarket
</h1>

</div>

<nav>

<a href="dashboard.php">
Dashboard
</a>

<a href="products.php">
Products
</a>

<a href="orders.php">
Orders
</a>

<a href="messages.php">
Messages
</a>

<a href="logout.php">
Logout
</a>

</nav>

</div>

</header>

<section class="section-spacing">

<div class="container">

<div class="form-container">

<h2>
Report User
</h2>

<p>
Report suspicious users, scams or fake products.
</p>

<?php

if($message != ""){

echo "

<div class='success-message'>

$message

</div>

";

}

?>

<form method="POST">

<label>
Reported Username / Seller
</label>

<input
type="text"
name="reported_user"
placeholder="Enter username or seller"
required>

<label>
Reason
</label>

<select
name="report_reason"
required>

<option value="">
Select Reason
</option>

<option value="Fake Product">
Fake Product
</option>

<option value="Scam">
Scam
</option>

<option value="Abusive Behaviour">
Abusive Behaviour
</option>

<option value="Fraud">
Fraud
</option>

<option value="Other">
Other
</option>

</select>

<label>
Report Details
</label>

<textarea
name="report_details"
placeholder="Explain the issue..."
required></textarea>

<button
type="submit"
name="submit_report">

Submit Report

</button>

</form>

</div>

</div>

</section>

</body>
</html>