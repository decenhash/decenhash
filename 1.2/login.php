<?php
session_start();

$mode = 'sql_pay'; // Change this to 'sql_pay' to enable SQL payment mode

// Database configuration for SQL payment mode
include 'db_config.php';

// Initialize database connection if in SQL payment mode
$conn = null;
if ($mode === 'sql_pay') {
    $conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password']);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Select the database
    $conn->select_db($db_config['database']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Fetch user from the database
    $stmt = $conn->prepare("SELECT id, username, password, credits FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['credits'] = $user['credits'];

            // Redirect to a dashboard or home page
            header("Location: index.php");
            exit();
        } else {
            $reply = "Invalid username or password.";
        }
    } else {
        $reply = "Invalid username or password.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-container h1 {
            margin-bottom: 1.5rem;
            color: #333;
        }
        .login-container label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            text-align: left;
        }
        .login-container input {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .login-container button {
            width: 100%;
            padding: 0.75rem;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        .login-container button:hover {
            background-color: #0056b3;
        }
        .login-container .error-message {
            color: #ff0000;
            margin-bottom: 1rem;
        }
        .login-container .register-link {
            margin-top: 1rem;
            color: #007bff;
            text-decoration: none;
        }
        .login-container .register-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <?php if (isset($reply)): ?>
            <p class="error-message"><?php echo $reply; ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Login</button>
        </form>
        <p>Don't have an account? <a class="register-link" href="registration.php">Register here</a>.</p>
    </div>
</body>
</html>