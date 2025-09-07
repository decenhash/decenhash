<?php
// Start the session to store messages
session_start();

// --- CONFIGURATION ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'decenhash';

// --- DATABASE CONNECTION ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    // Use a generic error for security
    die("An unexpected error occurred. Please try again later.");
}

// --- FORM PROCESSING LOGIC ---
$message = '';
$message_type = '';
$username = ''; // Retain username on error
$mail = '';     // Retain email on error
$btc = '';      // Retain BTC wallet on error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize inputs
    $username = trim($_POST['username']);
    $mail = trim($_POST['mail']);
    $btc = trim($_POST['btc']); // BTC wallet field
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // --- VALIDATION ---
    $errors = [];

    // 1. Check for required fields
    if (empty($username) || empty($mail) || empty($password) || empty($password_confirm)) {
        $errors[] = "Username, email, and password fields are required.";
    }

    // 2. Validate email format
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // 3. Advanced Password Validation (length, uppercase, lowercase, number, special char)
    $password_regex = "/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{12,}$/";
    if (!preg_match($password_regex, $password)) {
        $errors[] = "Password must be at least 12 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special symbol (#?!@$%^&*-).";
    }

    // 4. Check if passwords match
    if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }

    // --- DATABASE CHECKS (only if initial validation passes) ---
    if (empty($errors)) {
        // 5. Check for duplicate username
        $stmt_check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check_user->bind_param("s", $username);
        $stmt_check_user->execute();
        $stmt_check_user->store_result();
        if ($stmt_check_user->num_rows > 0) {
            $errors[] = "This username is already taken.";
        }
        $stmt_check_user->close();

        // 6. Check for duplicate email
        $stmt_check_mail = $conn->prepare("SELECT id FROM users WHERE mail = ?");
        $stmt_check_mail->bind_param("s", $mail);
        $stmt_check_mail->execute();
        $stmt_check_mail->store_result();
        if ($stmt_check_mail->num_rows > 0) {
            $errors[] = "This email address is already registered.";
        }
        $stmt_check_mail->close();
    }

    // --- PROCESS REGISTRATION ---
    if (empty($errors)) {
        // All checks passed, proceed to insert the new user

        // Securely hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Handle optional BTC field
        $btc_to_insert = !empty($btc) ? $btc : null;

        // Prepare the insert statement
        $stmt_insert = $conn->prepare("INSERT INTO users (username, mail, password, balance, btc) VALUES (?, ?, ?, ?, ?)");
        $default_balance = 0.00; // Give new users a starting balance
        $stmt_insert->bind_param("ssssd", $username, $mail, $hashed_password, $btc_to_insert, $default_balance);

        if ($stmt_insert->execute()) {
            // Success! Redirect to login page with a success message
            $_SESSION['success_message'] = "Registration successful! You can now log in.";
            header("Location: login.php"); // Assuming you have a login.php
            exit();
        } else {
            $message = "An error occurred during registration. Please try again.";
            $message_type = 'error';
        }
        $stmt_insert->close();
    } else {
        // Display validation errors
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        :root { --primary-color: #4a90e2; --success-color: #50e3c2; --error-color: #e35050; --background-color: #f4f7f6; --card-background: #ffffff; --text-color: #333; --light-text-color: #777; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .container { width: 100%; max-width: 480px; background-color: var(--card-background); border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); padding: 30px 40px; text-align: center; }
        .header h1 { margin: 0 0 25px 0; font-size: 24px; font-weight: 700; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; transition: border-color 0.3s, box-shadow 0.3s; }
        input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1); outline: none; }
        .submit-btn { width: 100%; padding: 15px; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background-color 0.3s, transform 0.2s; margin-top: 10px; }
        .submit-btn:hover { background-color: #3a7ac8; transform: translateY(-2px); }
        .message { padding: 15px; margin-top: 20px; border-radius: 8px; font-size: 14px; text-align: left; }
        .message.error { background-color: #fdeaea; color: #a32222; border: 1px solid var(--error-color); }
        .login-link { margin-top: 25px; font-size: 14px; color: var(--light-text-color); }
        .login-link a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .login-link a:hover { text-decoration: underline; }
        #password-reqs { list-style-type: none; padding-left: 5px; font-size: 12px; margin-top: 8px; color: var(--light-text-color); }
        #password-reqs li { margin-bottom: 4px; transition: color 0.3s; }
        #password-reqs li.valid { color: #006f54; text-decoration: line-through; }
        #password-reqs li.invalid { color: var(--error-color); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Create an Account</h1>
        </div>

        <form id="registerForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="form-group">
                <label for="mail">Email Address</label>
                <input type="email" id="mail" name="mail" value="<?php echo htmlspecialchars($mail); ?>" required>
            </div>
             <div class="form-group">
                <label for="btc">BTC Wallet (Optional)</label>
                <input type="text" id="btc" name="btc" value="<?php echo htmlspecialchars($btc); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <ul id="password-reqs">
                    <li id="req-length">At least 12 characters</li>
                    <li id="req-uppercase">An uppercase letter</li>
                    <li id="req-lowercase">A lowercase letter</li>
                    <li id="req-number">A number</li>
                    <li id="req-special">A special symbol (#?!@$%^&*-)</li>
                </ul>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <button type="submit" class="submit-btn">Register</button>
        </form>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="login-link">
            Already have an account? <a href="login.php">Log In</a>
        </div>
    </div>

    <script>
        // --- JAVASCRIPT FOR REAL-TIME PASSWORD VALIDATION ---
        const passwordInput = document.getElementById('password');
        const reqs = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };

        function validatePasswordRealtime() {
            const pass = passwordInput.value;

            // Length check
            pass.length >= 12 ? setValid(reqs.length) : setInvalid(reqs.length);
            // Uppercase check
            /[A-Z]/.test(pass) ? setValid(reqs.uppercase) : setInvalid(reqs.uppercase);
            // Lowercase check
            /[a-z]/.test(pass) ? setValid(reqs.lowercase) : setInvalid(reqs.lowercase);
            // Number check
            /[0-9]/.test(pass) ? setValid(reqs.number) : setInvalid(reqs.number);
            // Special character check
            /[#?!@$%^&*-]/.test(pass) ? setValid(reqs.special) : setInvalid(reqs.special);
        }

        function setValid(element) {
            element.classList.remove('invalid');
            element.classList.add('valid');
        }

        function setInvalid(element) {
            element.classList.remove('valid');
            element.classList.add('invalid');
        }

        passwordInput.addEventListener('keyup', validatePasswordRealtime);
    </script>
</body>
</html>
