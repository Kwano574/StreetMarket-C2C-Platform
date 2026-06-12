<?php

session_start();

include("includes/db.php");

$message = "";

/* CHECK IF COLUMN EXISTS */
function columnExists($conn, $table, $column){
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $query = "
    SHOW COLUMNS FROM `$table`
    LIKE '$column'
    ";

    $result = mysqli_query($conn, $query);

    return ($result && mysqli_num_rows($result) > 0);
}

/* SA ID LUHN VALIDATION */
function luhnCheck($number){

    $sum = 0;
    $alternate = false;

    for($i = strlen($number) - 1; $i >= 0; $i--){

        $digit = intval($number[$i]);

        if($alternate){
            $digit *= 2;

            if($digit > 9){
                $digit -= 9;
            }
        }

        $sum += $digit;
        $alternate = !$alternate;
    }

    return ($sum % 10 == 0);
}

/* SA ID VALIDATION */
function validateSouthAfricanID($sa_id, $selected_gender = ""){

    if(!preg_match("/^[0-9]{13}$/", $sa_id)){
        return "SA ID must contain exactly 13 digits.";
    }

    $yy = intval(substr($sa_id, 0, 2));
    $mm = intval(substr($sa_id, 2, 2));
    $dd = intval(substr($sa_id, 4, 2));
    $gender_digits = intval(substr($sa_id, 6, 4));
    $citizenship = substr($sa_id, 10, 1);

    $current_year_short = intval(date("y"));
    $century = ($yy <= $current_year_short) ? 2000 : 1900;
    $full_year = $century + $yy;

    if(!checkdate($mm, $dd, $full_year)){
        return "SA ID contains an invalid date of birth.";
    }

    if($full_year > intval(date("Y"))){
        return "SA ID date of birth cannot be in the future.";
    }

    if($citizenship != "0" && $citizenship != "1"){
        return "SA ID citizenship digit must be 0 for citizen or 1 for permanent resident.";
    }

    $id_gender = ($gender_digits >= 5000) ? "Male" : "Female";

    if($selected_gender != "" && $selected_gender != $id_gender){
        return "Selected gender does not match the SA ID number.";
    }

    if(!luhnCheck($sa_id)){
        return "Invalid SA ID number. Checksum validation failed.";
    }

    return "";
}

/* REGISTRATION */
if(isset($_POST['register'])){

    $first_name = mysqli_real_escape_string($conn, trim($_POST['firstName']));
    $last_name = mysqli_real_escape_string($conn, trim($_POST['lastName']));
    $sa_id = mysqli_real_escape_string($conn, trim($_POST['saID']));
    $gender = isset($_POST['gender']) ? mysqli_real_escape_string($conn, trim($_POST['gender'])) : "";
    $email = mysqli_real_escape_string($conn, strtolower(trim($_POST['registerEmail'])));
    $phone = mysqli_real_escape_string($conn, trim($_POST['number']));
    $province = mysqli_real_escape_string($conn, trim($_POST['province']));
    $physical_address = mysqli_real_escape_string($conn, trim($_POST['physicalAddress']));

    $password = $_POST['registerPassword'];
    $confirm_password = $_POST['confirmPassword'];

    $full_name = trim($first_name . " " . $last_name);

    if($first_name == "" || $last_name == "" || $sa_id == "" || $email == "" || $phone == "" || $province == "" || $physical_address == "" || $password == "" || $confirm_password == ""){

        $message = "Please fill in all required fields.";

    }

    if($message == ""){

        $id_error = validateSouthAfricanID($sa_id, $gender);

        if($id_error != ""){
            $message = $id_error;
        }
    }

    if($message == "" && !filter_var($email, FILTER_VALIDATE_EMAIL)){

        $message = "Please enter a valid email address.";

    }

    if($message == "" && !preg_match("/^[a-zA-Z0-9._%+-]+@gmail\.com$/", $email)){

        $message = "Email address must be a Gmail address ending with @gmail.com.";

    }

    /*
       South African mobile numbers after +27 usually start with:
       60, 61, 62, 63, 64, 65, 66, 67, 68, 71, 72, 73, 74, 76, 78, 79, 81, 82, 83, 84
    */
    if($message == "" && !preg_match("/^(60|61|62|63|64|65|66|67|68|71|72|73|74|76|78|79|81|82|83|84)[0-9]{7}$/", $phone)){

        $message = "Phone number must be a valid South African mobile number after +27.";

    }

    if($message == "" && !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}$/", $password)){

        $message = "Password must contain at least 8 characters, one uppercase letter, one lowercase letter and one number.";

    }

    if($message == "" && $password != $confirm_password){

        $message = "Passwords do not match.";

    }

    if($message == ""){

        $check_duplicate = "
        SELECT user_id
        FROM users
        WHERE sa_id='$sa_id'
        OR email='$email'
        OR phone='$phone'
        LIMIT 1
        ";

        $duplicate_result = mysqli_query($conn, $check_duplicate);

        if($duplicate_result && mysqli_num_rows($duplicate_result) > 0){

            $existing = mysqli_fetch_assoc($duplicate_result);

            $check_id = mysqli_query($conn, "SELECT user_id FROM users WHERE sa_id='$sa_id' LIMIT 1");
            $check_email = mysqli_query($conn, "SELECT user_id FROM users WHERE email='$email' LIMIT 1");
            $check_phone = mysqli_query($conn, "SELECT user_id FROM users WHERE phone='$phone' LIMIT 1");

            if($check_id && mysqli_num_rows($check_id) > 0){
                $message = "An account with this SA ID already exists.";
            }elseif($check_email && mysqli_num_rows($check_email) > 0){
                $message = "Email address already registered.";
            }elseif($check_phone && mysqli_num_rows($check_phone) > 0){
                $message = "Phone number already registered.";
            }else{
                $message = "This account information already exists.";
            }

        }else{

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $has_gender_column = columnExists($conn, "users", "gender");

            if($has_gender_column){

                $insert_user = "
                INSERT INTO users(
                    full_name,
                    sa_id,
                    gender,
                    email,
                    phone,
                    province,
                    address,
                    password,
                    seller_verification_status,
                    status
                )
                VALUES(
                    '$full_name',
                    '$sa_id',
                    '$gender',
                    '$email',
                    '$phone',
                    '$province',
                    '$physical_address',
                    '$hashed_password',
                    'Not Verified',
                    'Active'
                )
                ";

            }else{

                $insert_user = "
                INSERT INTO users(
                    full_name,
                    sa_id,
                    email,
                    phone,
                    province,
                    address,
                    password,
                    seller_verification_status,
                    status
                )
                VALUES(
                    '$full_name',
                    '$sa_id',
                    '$email',
                    '$phone',
                    '$province',
                    '$physical_address',
                    '$hashed_password',
                    'Not Verified',
                    'Active'
                )
                ";

            }

            if(mysqli_query($conn, $insert_user)){

                $_SESSION['success_message'] = "Account created successfully. Please login.";

                header("Location: login.php");
                exit();

            }else{

                $message = "Registration failed: " . mysqli_error($conn);

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

<title>Create Account | StreetMarket</title>

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

.info-box{
    background:#f0f9ff;
    color:#0f172a;
    border-left:5px solid #0ea5e9;
    padding:20px;
    border-radius:12px;
    margin-top:25px;
    margin-bottom:20px;
}

.checkbox-row{
    display:flex;
    align-items:flex-start;
    gap:10px;
    margin:20px 0;
}

.checkbox-row input{
    margin-top:6px;
}

textarea{
    resize:vertical;
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

<div class="page-intro">

<h2>Create Your Account</h2>

<p>
Every StreetMarket account allows users to buy products and apply to become verified sellers.
</p>

</div>

<?php if($message != ""){ ?>

<div class="error-message">
<?php echo htmlspecialchars($message); ?>
</div>

<?php } ?>

<form method="POST">

<fieldset>

<legend>StreetMarket Registration</legend>

<label for="firstName">First Name *</label>

<input
type="text"
id="firstName"
name="firstName"
placeholder="Enter first name"
required>

<label for="lastName">Last Name *</label>

<input
type="text"
id="lastName"
name="lastName"
placeholder="Enter last name"
required>

<label for="saID">South African ID Number *</label>

<input
type="text"
id="saID"
name="saID"
placeholder="Enter 13-digit SA ID Number"
maxlength="13"
inputmode="numeric"
required>

<small>
SA ID is checked using date of birth, citizenship digit and checksum validation.
</small>

<label for="gender">Gender Optional</label>

<select id="gender" name="gender">

<option value="">Prefer not to say</option>
<option value="Female">Female</option>
<option value="Male">Male</option>

</select>

<small>
If selected, gender must match the gender digits inside the SA ID number.
</small>

<label for="registerEmail">Email Address *</label>

<input
type="email"
id="registerEmail"
name="registerEmail"
placeholder="example@gmail.com"
required>

<small>
Only Gmail addresses ending with @gmail.com are allowed.
</small>

<label for="number">Phone Number *</label>

<div class="phone-group">

<span class="phone-code">+27</span>

<input
type="tel"
id="number"
name="number"
placeholder="712345678"
maxlength="9"
inputmode="numeric"
required>

</div>

<small>
Enter a valid South African mobile number without the first 0.
</small>

<label for="province">Province *</label>

<select id="province" name="province" required>

<option value="">Select Province</option>
<option value="Gauteng">Gauteng</option>
<option value="KwaZulu-Natal">KwaZulu-Natal</option>
<option value="Western Cape">Western Cape</option>
<option value="Eastern Cape">Eastern Cape</option>
<option value="Limpopo">Limpopo</option>
<option value="Mpumalanga">Mpumalanga</option>
<option value="North West">North West</option>
<option value="Free State">Free State</option>
<option value="Northern Cape">Northern Cape</option>

</select>

<label for="physicalAddress">Physical Address *</label>

<textarea
id="physicalAddress"
name="physicalAddress"
placeholder="Enter your physical address"
rows="4"
required></textarea>

<label for="registerPassword">Password *</label>

<input
type="password"
id="registerPassword"
name="registerPassword"
placeholder="Create password"
required>

<small>
Password must contain at least 8 characters, one uppercase letter, one lowercase letter and one number.
</small>

<label for="confirmPassword">Confirm Password *</label>

<input
type="password"
id="confirmPassword"
name="confirmPassword"
placeholder="Confirm password"
required>

<div class="checkbox-row">

<input type="checkbox" required>

<span>
I agree to the StreetMarket Terms, Privacy Policy and Trading Rules.
</span>

</div>

<button type="submit" name="register">
Create Account
</button>

</fieldset>

</form>

<div class="info-box">

<h3>Registration Validation</h3>

<ul>
<li>One account per SA ID.</li>
<li>Duplicate emails and phone numbers are blocked.</li>
<li>SA ID uses date, citizenship and checksum validation.</li>
<li>Only Gmail email addresses are allowed.</li>
<li>Passwords are securely hashed before storage.</li>
</ul>

</div>

</div>

</div>

</section>

<footer>

<div class="container footer-container">

<nav>

<a href="about.php">About</a>
<a href="safety.php">Safety Centre</a>
<a href="help.php">Contact</a>

</nav>

<p>Copyright © 2026 StreetMarket.</p>

</div>

</footer>

<script src="js/script.js"></script>

</body>

</html>