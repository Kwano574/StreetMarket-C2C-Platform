<?php

session_start();

// Connecting to the database.
include("includes/db.php");

$message = "";

// These are the only two ID numbers allowed by this academic project.
const DEMO_TEST_ID = "0001015000902";
const DEMO_PRESENTATION_ID = "0001010000907";

function columnExists($conn, $table, $column){
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);

    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($result && mysqli_num_rows($result) > 0);
}

// Returns the gender assigned to a project demo ID.
function getDemoIDGender($sa_id){
    $demo_ids = [
        DEMO_TEST_ID => "Male",
        DEMO_PRESENTATION_ID => "Female"
    ];

    return isset($demo_ids[$sa_id]) ? $demo_ids[$sa_id] : "";
}

// Checks the final digit used by a valid South African ID number.
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

// Validates a real SA ID or one of the two fake project IDs.
function validateSouthAfricanID($sa_id, $selected_gender = ""){
    if(!preg_match("/^[0-9]{13}$/", $sa_id)){
        return "SA ID must contain exactly 13 digits.";
    }

    $demo_gender = getDemoIDGender($sa_id);

    // The two fake IDs are allowed only as controlled testing exceptions.
    if($demo_gender != ""){
        if($selected_gender != "" && $selected_gender != $demo_gender){
            return "Selected gender does not match this project demonstration ID.";
        }

        return "";
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

if(isset($_POST['register'])){
    $first_name = trim($_POST['firstName'] ?? "");
    $last_name = trim($_POST['lastName'] ?? "");
    $sa_id = trim($_POST['saID'] ?? "");
    $gender = trim($_POST['gender'] ?? "");
    $email = strtolower(trim($_POST['registerEmail'] ?? ""));
    $phone = trim($_POST['number'] ?? "");
    $province = trim($_POST['province'] ?? "");
    $physical_address = trim($_POST['physicalAddress'] ?? "");
    $password = $_POST['registerPassword'] ?? "";
    $confirm_password = $_POST['confirmPassword'] ?? "";
    $terms_accepted = isset($_POST['terms']);
    $full_name = trim($first_name . " " . $last_name);

    if($first_name == "" || $last_name == "" || $sa_id == "" || $email == "" || $phone == "" || $province == "" || $physical_address == "" || $password == "" || $confirm_password == ""){
        $message = "Please fill in all required fields.";
    }

    if($message == ""){
        $message = validateSouthAfricanID($sa_id, $gender);
    }

    if($message == "" && !filter_var($email, FILTER_VALIDATE_EMAIL)){
        $message = "Please enter a valid email address.";
    }

    if($message == "" && !preg_match("/^[a-zA-Z0-9._%+-]+@gmail\.com$/", $email)){
        $message = "Email address must be a Gmail address ending with @gmail.com.";
    }

    if($message == "" && !preg_match("/^(60|61|62|63|64|65|66|67|68|71|72|73|74|76|78|79|81|82|83|84)[0-9]{7}$/", $phone)){
        $message = "Phone number must be a valid South African mobile number after +27.";
    }

    if($message == "" && !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}$/", $password)){
        $message = "Password must contain at least 8 characters, one uppercase letter, one lowercase letter and one number.";
    }

    if($message == "" && $password != $confirm_password){
        $message = "Passwords do not match.";
    }

    if($message == "" && !$terms_accepted){
        $message = "You must agree to the Terms and Conditions, Privacy Policy and Trading Rules.";
    }

    // Duplicate checking also applies to both demonstration IDs.
    if($message == ""){
        $duplicate_query = "SELECT sa_id, email, phone FROM users WHERE sa_id = ? OR email = ? OR phone = ? LIMIT 1";
        $duplicate_statement = mysqli_prepare($conn, $duplicate_query);

        if(!$duplicate_statement){
            $message = "Registration could not be processed. Please try again.";
        }else{
            mysqli_stmt_bind_param($duplicate_statement, "sss", $sa_id, $email, $phone);
            mysqli_stmt_execute($duplicate_statement);
            $duplicate_result = mysqli_stmt_get_result($duplicate_statement);

            if($duplicate_result && mysqli_num_rows($duplicate_result) > 0){
                $existing = mysqli_fetch_assoc($duplicate_result);

                if($existing['sa_id'] == $sa_id){
                    $message = "An account with this SA ID already exists.";
                }elseif($existing['email'] == $email){
                    $message = "Email address already registered.";
                }elseif($existing['phone'] == $phone){
                    $message = "Phone number already registered.";
                }
            }

            mysqli_stmt_close($duplicate_statement);
        }
    }

    if($message == ""){
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $has_gender_column = columnExists($conn, "users", "gender");

        if($has_gender_column){
            $insert_query = "INSERT INTO users (full_name, sa_id, gender, email, phone, province, address, password, seller_verification_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Not Verified', 'Active')";
            $insert_statement = mysqli_prepare($conn, $insert_query);

            if($insert_statement){
                mysqli_stmt_bind_param($insert_statement, "ssssssss", $full_name, $sa_id, $gender, $email, $phone, $province, $physical_address, $hashed_password);
            }
        }else{
            $insert_query = "INSERT INTO users (full_name, sa_id, email, phone, province, address, password, seller_verification_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Not Verified', 'Active')";
            $insert_statement = mysqli_prepare($conn, $insert_query);

            if($insert_statement){
                mysqli_stmt_bind_param($insert_statement, "sssssss", $full_name, $sa_id, $email, $phone, $province, $physical_address, $hashed_password);
            }
        }

        if(!$insert_statement){
            $message = "Registration could not be processed. Please try again.";
        }elseif(mysqli_stmt_execute($insert_statement)){
            mysqli_stmt_close($insert_statement);
            $_SESSION['success_message'] = "Account created successfully. Please login.";
            header("Location: login.php");
            exit();
        }else{
            $message = "Registration failed. Please try again.";
            mysqli_stmt_close($insert_statement);
        }
    }
}

function oldValue($name){
    return htmlspecialchars($_POST[$name] ?? "", ENT_QUOTES, "UTF-8");
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

.demo-id-box{
    background:#f0f9ff;
    color:#0f172a;
    border-left:5px solid #0ea5e9;
    padding:16px;
    border-radius:10px;
    margin:12px 0 20px;
}

.demo-id-box p{
    margin:0 0 8px;
}

.demo-id-box p:last-child{
    margin-bottom:0;
}

.demo-id-box code{
    background:#e0f2fe;
    padding:3px 6px;
    border-radius:4px;
    font-weight:bold;
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
<p>Every StreetMarket account allows users to buy products and apply to become verified sellers.</p>
</div>

<?php if($message != ""){ ?>
<div class="error-message"><?php echo htmlspecialchars($message); ?></div>
<?php } ?>

<form method="POST">
<fieldset>
<legend>StreetMarket Registration</legend>

<label for="firstName">First Name *</label>
<input type="text" id="firstName" name="firstName" placeholder="Enter first name" value="<?php echo oldValue('firstName'); ?>" required>

<label for="lastName">Last Name *</label>
<input type="text" id="lastName" name="lastName" placeholder="Enter last name" value="<?php echo oldValue('lastName'); ?>" required>

<label for="saID">South African ID Number *</label>
<input type="text" id="saID" name="saID" placeholder="Enter 13-digit SA ID Number" maxlength="13" inputmode="numeric" pattern="[0-9]{13}" value="<?php echo oldValue('saID'); ?>" required>
<small>Real SA IDs are validated normally. The two fake IDs below are allowed for project testing.</small>



<label for="gender">Gender (Optional)</label>
<select id="gender" name="gender">
<option value="" <?php echo oldValue('gender') == "" ? "selected" : ""; ?>>Prefer not to say</option>
<option value="Female" <?php echo oldValue('gender') == "Female" ? "selected" : ""; ?>>Female</option>
<option value="Male" <?php echo oldValue('gender') == "Male" ? "selected" : ""; ?>>Male</option>
</select>
<small>If selected, gender must match the gender digits in the ID number.</small>

<label for="registerEmail">Email Address *</label>
<input type="email" id="registerEmail" name="registerEmail" placeholder="example@gmail.com" value="<?php echo oldValue('registerEmail'); ?>" required>
<small>Only Gmail addresses ending with @gmail.com are allowed.</small>

<label for="number">Phone Number *</label>
<div class="phone-group">
<span class="phone-code">+27</span>
<input type="tel" id="number" name="number" placeholder="712345678" maxlength="9" inputmode="numeric" pattern="[0-9]{9}" value="<?php echo oldValue('number'); ?>" required>
</div>
<small>Enter a valid South African mobile number without the first 0.</small>

<label for="province">Province *</label>
<select id="province" name="province" required>
<option value="">Select Province</option>
<?php
$provinces = ["Gauteng", "KwaZulu-Natal", "Western Cape", "Eastern Cape", "Limpopo", "Mpumalanga", "North West", "Free State", "Northern Cape"];
foreach($provinces as $province_option){
    $selected = oldValue('province') == $province_option ? "selected" : "";
    echo '<option value="' . htmlspecialchars($province_option) . '" ' . $selected . '>' . htmlspecialchars($province_option) . '</option>';
}
?>
</select>

<label for="physicalAddress">Physical Address *</label>
<textarea id="physicalAddress" name="physicalAddress" placeholder="Enter your physical address" rows="4" required><?php echo oldValue('physicalAddress'); ?></textarea>

<label for="registerPassword">Password *</label>
<input type="password" id="registerPassword" name="registerPassword" placeholder="Create password" required>
<small>Password must contain at least 8 characters, one uppercase letter, one lowercase letter and one number.</small>

<label for="confirmPassword">Confirm Password *</label>
<input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm password" required>

<div class="checkbox-row">
<input type="checkbox" id="terms" name="terms" <?php echo isset($_POST['terms']) ? "checked" : ""; ?> required>
<label for="terms">
I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a>,
<a href="privacy-policy.php" target="_blank">Privacy Policy</a> and
<a href="trading-rules.php" target="_blank">Trading Rules</a>.
</label>
</div>

<button type="submit" name="register">Create Account</button>
</fieldset>
</form>
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
<p>Copyright &copy; 2026 StreetMarket.</p>
</div>
</footer>
<script src="js/script.js"></script>
</body>
</html>
