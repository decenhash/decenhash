<?php
// Start session and initialize message variables
session_start();
$successMessage = $errorMessage = "";


// Database configuration
include 'db_config.php';

// Create a database connection
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

// Process form submission - only when POST request is made
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if all fields are provided
    if (!isset($_POST['public_key'], $_POST['password'], $_POST['user'], $_FILES['file'])) {
        $errorMessage = "Error: Missing required fields.";
    } else {
        $publicKey = trim($_POST['public_key']);
        $password = trim($_POST['password']);
        $username = trim($_POST['user']);
        $altName = isset($_POST['altName']) ? trim($_POST['altName']) : '';
        $altKey = isset($_POST['altKey']) ? trim($_POST['altKey']) : '';
        $file = $_FILES['file'];

        // Ensure upload is successful
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = "Error: File upload failed with code " . $file['error'] . ".";
        } else {
            // Read file content and compute SHA-256 hash
            $fileContent = file_get_contents($file['tmp_name']);
            $fileHash = hash('sha256', $fileContent);
            $fileName = basename($file['name']);

            // Check if the username exists in the 'users' table
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 0) {
                $errorMessage = "Error: Username does not exist.";
            } else {
                // Check if the file hash exists in the 'upload_logs' table
                $stmt = $conn->prepare("SELECT id FROM upload_logs WHERE file_hash = ?");
                if ($stmt === false) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("s", $fileHash);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 0) {
                    $errorMessage = "Error: File hash does not exist in the system.";
                } else {
                    // Create blocks directory if it doesn't exist
                    if (!file_exists('blocks')) {
                        mkdir('blocks', 0755, true);
                    }

                    // Check if the file already exists
                    $blockFile = "blocks/$fileHash";
                    if (file_exists($blockFile)) {
                        $errorMessage = "This file has already been registered in the system.";
                    } else {
                        // Generate timestamp
                        $timestamp = time();

                        // Create signature using password as salt
                        $dataToSign = $fileHash . $timestamp . $password;
                        $signature = hash_hmac('sha256', $dataToSign, $password);

                        // Create JSON block
                        $blockData = [
                            "file_name" => $fileName,
                            "file_hash" => $fileHash,
                            "public_key" => $publicKey,
                            "signature" => $signature,
                            "alt_name" => $altName,
                            "alt_key" => $altKey,
                            "timestamp" => $timestamp
                        ];

                        // Save JSON block in "blocks" folder with file hash as filename
                        if (file_put_contents($blockFile, json_encode($blockData, JSON_PRETTY_PRINT))) {
                            // Delete the uploaded file
                            unlink($file['tmp_name']);
                            $successMessage = "File registered successfully! Block saved as: $blockFile";
                        } else {
                            $errorMessage = "Error: Failed to save block file.";
                        }
                    }
                }
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decentralized File Ownership Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .success-message {
            color: green;
            margin-bottom: 10px;
        }
        .error-message {
            color: red;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="file"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .form-group input[type="submit"] {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        .form-group input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .tooltip {
            display: none;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 5px;
        }
        .help-btn:hover + .tooltip {
            display: block;
        }
    </style>
</head>
<body>
    <h2>Decentralized File Ownership Registration</h2>

    <?php
    // Display messages if any
    if (!empty($successMessage)) {
        echo "<div class='success-message'>$successMessage</div>";
    }
    if (!empty($errorMessage)) {
        echo "<div class='error-message'>$errorMessage</div>";
    }
    ?>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="public_key">Bitcoin Public Key:</label>
            <span class="help-btn">?</span>
            <div class="tooltip">Enter your cryptocurrency public key or wallet address. This will be used to verify ownership of the file.</div>
            <input type="text" name="public_key" id="public_key" required>
        </div>

        <div class="form-group">
            <label for="user">User:</label>
            <span class="help-btn">?</span>
            <div class="tooltip">Enter your username</div>
            <input type="text" name="user" id="user" required>
        </div>

        <div class="form-group">
            <label for="altName">Alt Coin Name:</label>
            <span class="help-btn">?</span>
            <div class="tooltip">Optional: Enter the name of an alternative cryptocurrency if you're not using the default one.</div>
            <input type="text" name="altName" id="altName">
        </div>

        <div class="form-group">
            <label for="altKey">Alt Coin Public Key:</label>
            <span class="help-btn">?</span>
            <div class="tooltip">Optional: Enter your public key or wallet address for the alternative cryptocurrency specified above.</div>
            <input type="text" name="altKey" id="altKey">
        </div>

        <div class="form-group">
            <label for="password">Password (Salt):</label>
            <span class="help-btn">?</span>
            <div class="tooltip">Enter a secure password that will be used as a salt for signature generation. Keep this password safe as it will be needed to verify ownership later.</div>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-group">
            <label for="file">Select File:</label>
            <span class="help-btn">?</span>
            <div class="tooltip">Choose the file you want to register ownership for. The system will generate a unique hash of this file.</div>
            <input type="file" name="file" id="file" required>
        </div>

        <div class="form-group">
            <input type="submit" value="Upload & Register">
        </div>
    </form>
</body>
</html>