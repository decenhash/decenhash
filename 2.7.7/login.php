<?php
// Start the session
session_start();

// Check if a user is already logged in
if (isset($_SESSION['username'])) {
    header("Location: stock.php"); // Redirect to a dashboard or home page
    exit();
}

// --- CONFIGURATION ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'decenhash';

// --- DATABASE CONNECTION ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("An unexpected error occurred. Please try again later.");
}

// --- FORM PROCESSING LOGIC ---
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = trim($_POST['username_or_email']);
    $password = $_POST['password'];

    // --- VALIDATION ---
    if (empty($username_or_email) || empty($password)) {
        $message = "Please enter both a username/email and a password.";
        $message_type = 'error';
    } else {
        // --- AUTHENTICATION ---
        // Prepare the statement to fetch the user
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR mail = ?");
        $stmt->bind_param("ss", $username_or_email, $username_or_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify the hashed password
            if (password_verify($password, $user['password'])) {
                // Password is correct, start the session
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Redirect to the dashboard or a protected page
                header("Location: stock.php"); 
                exit();
            } else {
                // Invalid password
                $message = "Invalid username or password.";
                $message_type = 'error';
            }
        } else {
            // User not found
            $message = "Invalid username or password.";
            $message_type = 'error';
        }

        $stmt->close();
    }
}

// Check for success message from registration page
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = 'success';
    unset($_SESSION['success_message']); // Clear the message after displaying it
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
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
        .message.success { background-color: #e6f9f6; color: #006f54; border: 1px solid var(--success-color); }
        .message.error { background-color: #fdeaea; color: #a32222; border: 1px solid var(--error-color); }
        .register-link { margin-top: 25px; font-size: 14px; color: var(--light-text-color); }
        .register-link a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Log In to Your Account</h1>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="username_or_email">Username or Email</label>
                <input type="text" id="username_or_email" name="username_or_email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="submit-btn">Log In</button>
        </form>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>