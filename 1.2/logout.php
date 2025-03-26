<?php
session_start(); // Start the session
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout</title>
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
        .logout-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logout-container h1 {
            margin-bottom: 1.5rem;
            color: #333;
        }
        .logout-container p {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 1.5rem;
        }
        .logout-container .login-link {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        .logout-container .login-link:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <h1>Logged Out Successfully</h1>
        <p>You have been logged out. Redirecting to the login page...</p>
        <a class="login-link" href="index.php">Login Again</a>
    </div>

    <!-- Redirect to login page after 3 seconds -->
    <script>
        setTimeout(function() {
            window.location.href = "index.php";
        }, 3000); // 3 seconds
    </script>
</body>
</html>