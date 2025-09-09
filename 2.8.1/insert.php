<?php
session_start();

// --- Database Configuration ---
// Replace with your actual database credentials
$dbHost = 'localhost';
$dbUsername = 'root';
$dbPassword = '';
$dbName = 'decenhash';

// --- User Information ---
// In a real application, you would get this from a user session.
// For this example, we'll use a static username.
$username = $_SESSION['id'] ?? 'anon';

// --- Helper function to format file size ---
function formatSizeUnits($bytes)
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 0) {
        return $bytes . ' B';
    } else {
        return '0 B';
    }
}


// --- Database and Table Setup ---
// 1. Connect to MySQL server (without selecting a database)
$conn = new mysqli($dbHost, $dbUsername, $dbPassword);
if ($conn->connect_error) {
    die("Server Connection failed: " . $conn->connect_error);
}

// 2. Create the database if it doesn't exist
$sqlCreateDb = "CREATE DATABASE IF NOT EXISTS `$dbName`";
if (!$conn->query($sqlCreateDb)) {
    die("Error creating database: " . $conn->error);
}

// 3. Select the database
$conn->select_db($dbName);

// 4. Create the 'files' table if it doesn't exist
$sqlCreateTable = "CREATE TABLE IF NOT EXISTS `files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL,
    `filehash` VARCHAR(64) NOT NULL UNIQUE,
    `type` VARCHAR(100),
    `filesize` VARCHAR(20),
    `date` DATETIME NOT NULL,
    `user` VARCHAR(100) NOT NULL
)";

if (!$conn->query($sqlCreateTable)) {
    die("Error creating table: " . $conn->error);
}


// --- File Upload Logic ---
// Check if file was uploaded without errors
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo "Error: File is too large (max 10MB).";
        exit;
    }

    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Check if extension is not php
    if ($extension === 'php') {
        echo "Error: PHP files are not allowed.";
        exit;
    }

    // Calculate SHA-256 hash of the file
    $fileHash = hash_file('sha256', $file['tmp_name']);
    $targetDir = 'files/';
    $targetFile = $targetDir . $fileHash . ($extension ? '.' . $extension : '');

    // Create files directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // --- Database Interaction ---

    // 1. Check if the file hash already exists in the database
    $stmt = $conn->prepare("SELECT id FROM files WHERE filehash = ?");
    $stmt->bind_param("s", $fileHash);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "Error: A file with the same content already exists.";
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // 2. If hash doesn't exist, move the uploaded file and insert into DB
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        // File moved successfully, now insert its info into the database.
        $filename = $file['name'];
        // Format the filesize before inserting
        $filesize = formatSizeUnits($file['size']);
        $filetype = $file['type'];
        $uploadDate = date('Y-m-d H:i:s');

        // Prepare the INSERT statement
        $stmt = $conn->prepare("INSERT INTO files (filename, filehash, type, filesize, date, user) VALUES (?, ?, ?, ?, ?, ?)");
        // Bind the variables to the statement's parameters (note: filesize is now a string 's')
        $stmt->bind_param("ssssss", $filename, $fileHash, $filetype, $filesize, $uploadDate, $username);

        if ($stmt->execute()) {
            echo "File uploaded successfully and record created. View file: " . "<a href='" . htmlspecialchars($targetFile) . "' target='_blank'>" . htmlspecialchars(basename($targetFile)) . "</a>";
        } else {
            echo "Error: Could not save file information to the database. " . $stmt->error;
            // Optional: remove the file if DB insert fails
            // unlink($targetFile);
        }
        $stmt->close();

    } else {
        echo "Error: There was an error uploading your file.";
    }

} else {
    // Provide a more specific error message if a file was selected but failed
    if (isset($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        echo "Error during file upload: " . $_FILES['file']['error'];
    } else {
        echo "Error: No file selected or an unknown error occurred.";
    }
}
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>File Upload</title>
</head>
<body>
    <h1>Upload a File</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <input type="submit" value="Upload">
    </form>
</body>
</html>