<?php
// Start session to manage user login state
session_start();

// Configuration from the provided code
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

    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS " . $db_config['database'];
    if ($conn->query($sql) !== TRUE) {
        die("Error creating database: " . $conn->error);
    }

    // Select the database
    $conn->select_db($db_config['database']);

    // Create users table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        credits DECIMAL(10,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if ($conn->query($sql) !== TRUE) {
        die("Error creating users table: " . $conn->error);
    }

    // Create logs table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS upload_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        file_hash VARCHAR(64) NOT NULL,
        category_hash VARCHAR(64) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";

    if ($conn->query($sql) !== TRUE) {
        die("Error creating logs table: " . $conn->error);
    }
}

function check_sha256(string $input): string {
    $sha256_regex = '/^[a-f0-9]{64}$/'; // Regex for a 64-character hexadecimal string
    if (preg_match($sha256_regex, $input)) {
        return $input; // Input is a valid SHA256 hash
    } else {
        return hash('sha256', $input); // Input is not a valid SHA256 hash, return its hash
    }
}

if(ISSET($_GET['reply'])){$reply = $_GET['reply'];}else{$reply="";}
// Get current user if in SQL payment mode
$current_user = null;
$user_credits = 0;
if ($mode === 'sql_pay' && isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    // Get user and credits
    $stmt = $conn->prepare("SELECT id, credits FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $current_user = $row['id'];
        $user_credits = $row['credits'];
    }

    $stmt->close();
}

// Handle registration form submission
if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $reply = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reply = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $reply = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $reply = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $reply = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $reply = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $reply = "Password must contain at least one number.";
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $reply = "Password must contain at least one special character.";
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $reply = "Username or email already exists. Please choose a different one.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user into the database
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hashed_password);

            if ($stmt->execute()) {
                $reply = "Registration successful! You can now log in.";
                // Optionally redirect to login page
                // header("Location: login.php");
                // exit();
            } else {
                $reply = "Registration failed. Please try again later.";
            }

            $stmt->close();
        }
    }
}

// Close the database connection
if ($conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <style>
        body {
            font-family: sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 400px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"]
        {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        .reply {
            color: red;
            margin-top: 10px;
            text-align: center;
        }
        .success {
            color: green;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <?php if (!empty($reply)): ?>
            <p class="<?php echo (strpos($reply, 'success') !== false) ? 'success' : 'reply'; ?>"><?php echo $reply; ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <small>Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" name="register">Register</button>
        </form>
        <p style="margin-top: 15px; text-align: center;">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</body>
</html>