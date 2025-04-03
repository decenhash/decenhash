<?php
session_start();

// Mode selection - Set this variable to 'default' or 'sql_pay'
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
        credits INT DEFAULT 0,
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configuration - Customize these paths and settings as needed
    $uploadDirBase = 'data'; // Base directory for all uploads

    // Handle payment in SQL mode
    if ($mode === 'sql_pay') {
        if (!$current_user) {
            die("Error: You must be logged in to upload in SQL payment mode.");
        }
        
        if ($user_credits < 1) {
            die("Error: Insufficient credits. You need at least 1 credit to upload.");
        }
    }

    // Check if category was provided
    if (isset($_POST['category']) && !empty($_POST['category'])) {
        $categoryText = $_POST['category'];
        $fileContent = null;
        $originalFileName = null;
        $fileExtension = 'txt'; // Default extension for text content
        $isTextContent = false; // Flag to track if content is from text area

        // Check if a file was uploaded
        if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['uploaded_file'];
            $fileContent = file_get_contents($uploadedFile['tmp_name']);
            $originalFileName = $uploadedFile['name'];
            $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            $isTextContent = false;

        } elseif (isset($_POST['text_content']) && !empty($_POST['text_content'])) {
            // If no file uploaded, check for text content
            $fileContent = $_POST['text_content'];
            $date = date("Y.m.d H:i:s"); // Just for naming purposes in index.html
            
            $originalFileName = hash('sha256', $fileContent);

            $fileContentLen = strlen($fileContent);

            if ($fileContentLen > 50) {
                $originalFileName = htmlspecialchars(substr($fileContent, 0, 50)) . " ($date)"; 
            } else {
                $originalFileName = htmlspecialchars(substr($fileContent, 0, 50)) . " ($date)";
            }

            $isTextContent = true;
        }

        if ($fileContent !== null) { // Proceed if we have either file content or text content
            if (strtolower($fileExtension) === 'php') {
                die('Error: PHP files are not allowed!');
            }

            if ($_POST['category'] == $_POST['text_content']){
                die ("Error: Category can't be the same of text contents.");
            }

            // Calculate SHA256 hashes
            $fileHash = hash('sha256', $fileContent);
            $categoryHash = check_sha256($categoryText);

            // Determine file extension (already done above, default is 'txt' for text content)
            $fileNameWithExtension = $fileHash . '.' . $fileExtension; // Hash + extension as filename

            // Construct directory paths
            $fileUploadDir = $uploadDirBase . '/' . $fileHash; // Folder name is file hash
            $categoryDir = $uploadDirBase . '/' . $categoryHash; // Folder name is category hash

            // Create directories if they don't exist
            if (!is_dir($uploadDirBase)) {
                mkdir($uploadDirBase, 0777, true); // Create base upload directory if it doesn't exist
            }
            if (!is_dir($fileUploadDir)) {
                mkdir($fileUploadDir, 0777, true); // Create file hash folder
            }
            if (!is_dir($categoryDir)) {
                mkdir($categoryDir, 0777, true); // Create category hash folder
            }

            // Save the content (either uploaded file or text content)
            $destinationFilePath = $fileUploadDir . '/' . $fileNameWithExtension;

            if (file_exists($destinationFilePath)) {
                die('Error: File already exists!');
            }

            if ($isTextContent) {
                $saveResult = file_put_contents($destinationFilePath, $fileContent); // Save text content
                if ($saveResult !== false) {
                    $fileSaved = true;
                } else {
                    $fileSaved = false;
                }
            } else {
                $fileSaved = move_uploaded_file($uploadedFile['tmp_name'], $destinationFilePath); // Save uploaded file
            }

            if ($fileSaved) {
                // Content saved successfully

                // Create empty file in category folder with hash + extension name
                $categoryFilePath = $categoryDir . '/' . $fileNameWithExtension; // Empty file name is file hash + extension inside category folder
                if (touch($categoryFilePath)) {
                    // Empty file created successfully
                    
                    $contentHead = "<link rel='stylesheet' href='../../default.css'><script src='../../default.js'></script><script src='../../ads.js'></script><div id='ads' name='ads' class='ads'></div><div id='default' name='default' class='default'></div>";
 
                    // Handle index.html inside file hash folder (for content links)
                    $indexPathFileFolder = $fileUploadDir . '/index.html';
                    if (!file_exists($indexPathFileFolder)) {
                        $file = fopen($indexPathFileFolder, 'a');
                        fwrite($file, $contentHead);
                        fclose($file);
                    }
  
                    $linkReply = '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "[ Reply ]" . '</a> ';
                    $linkToHash = $linkReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "[ Open ]" . '</a> ';
                    $linkToFileFolderIndex = $linkToHash . '<a href="' . htmlspecialchars($fileNameWithExtension) . '">' . htmlspecialchars($originalFileName) . '</a><br>'; //Use original file name or 'text_content.txt' for link text
                    $indexContentFileFolder = file_get_contents($indexPathFileFolder);
                    if (strpos($indexContentFileFolder, $linkToFileFolderIndex) === false) {
                        file_put_contents($indexPathFileFolder, $indexContentFileFolder . $linkToFileFolderIndex); // Append link to index.html
                    }

                    // Handle index.html inside category folder (for link to original content)
                    $indexPathCategoryFolder = $categoryDir . '/index.html';
                    if (!file_exists($indexPathCategoryFolder)) {
                        $file = fopen($indexPathCategoryFolder, 'a');
                        fwrite($file, $contentHead);
                        fclose($file);                 
                    }

                    // Construct relative path to the content in the content hash folder
                    $relativePathToFile = '../' . $fileHash . '/' . $fileNameWithExtension;

                    $categoryReply = '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "[ Reply ]" . '</a> ';
                    $linkToHashCategory = $categoryReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "[ Open ]" . '</a> ';
                    $linkToCategoryFolderIndex = $linkToHashCategory . '<a href="' . htmlspecialchars($relativePathToFile) . '">' . htmlspecialchars($originalFileName) . '</a><br>'; //Use original file name or 'text_content.txt' for link text
                    $indexContentCategoryFolder = file_get_contents($indexPathCategoryFolder);
                    if (strpos($indexContentCategoryFolder, $linkToCategoryFolderIndex) === false) {
                        file_put_contents($indexPathCategoryFolder, $indexContentCategoryFolder . $linkToCategoryFolderIndex); // Append link to index.html
                    }

                    // If in SQL payment mode, subtract credit and log the upload
                    if ($mode === 'sql_pay') {
                        // Subtract one credit
                        $stmt = $conn->prepare("UPDATE users SET credits = credits - 1 WHERE id = ?");
                        $stmt->bind_param("i", $current_user);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Log the upload
                        $stmt = $conn->prepare("INSERT INTO upload_logs (user_id, file_hash, category_hash) VALUES (?, ?, ?)");
                        $stmt->bind_param("iss", $current_user, $fileHash, $categoryHash);
                        $stmt->execute();
                        $stmt->close();
                        
                        echo "<p class='success'>Content processed successfully! One credit has been deducted from your account.</p>";
                    } else {
                        echo "<p class='success'>Content processed successfully!</p>";
                    }
                    
                    echo "<p>Content saved in: <pre><a href='" . htmlspecialchars($destinationFilePath) . "'>$destinationFilePath</a></pre></p>";
                } else {
                    echo "<p class='error'>Error creating empty file in category folder.</p>";
                }
            } else {
                echo "<p class='error'>Error saving content.</p>";
            }
        } else {
            echo "<p class='error'>Please select a file or enter text content and provide a category.</p>";
        }
    } else {
        echo "<p class='error'>Please enter a category.</p>";
    }
}

// Close database connection if in SQL payment mode
if ($mode === 'sql_pay' && $conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DecentHash - Upload File</title>
    <style>
        /**
         * DecentHash - Modern Professional CSS
         * A clean, responsive design with a professional color scheme
         */

        :root {
          /* Color Scheme */
          --primary: #3a7bd5;
          --primary-light: #6fa1ff;
          --primary-dark: #0d47a1;
          --secondary: #f5f7fa;
          --dark: #2d3748;
          --light: #f8fafc;
          --success: #38a169;
          --warning: #e9b949;
          --error: #e53e3e;
          --gray-100: #f7fafc;
          --gray-200: #edf2f7;
          --gray-300: #e2e8f0;
          --gray-400: #cbd5e0;
          --gray-500: #a0aec0;
          --gray-600: #718096;
          --gray-700: #4a5568;
          --gray-800: #2d3748;
          
          /* Spacing */
          --spacing-xs: 0.25rem;
          --spacing-sm: 0.5rem;
          --spacing-md: 1rem;
          --spacing-lg: 1.5rem;
          --spacing-xl: 2rem;
          
          /* Typography */
          --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
          --font-size-small: 0.875rem;
          --font-size-base: 1rem;
          --font-size-large: 1.125rem;
          --font-size-xl: 1.25rem;
          --font-size-2xl: 1.5rem;
          --font-size-3xl: 1.875rem;
          
          /* Border Radius */
          --radius-sm: 0.25rem;
          --radius-md: 0.375rem;
          --radius-lg: 0.5rem;
          
          /* Shadows */
          --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
          --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
          --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Base styles */
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }

        html {
          font-size: 16px;
        }

        body {
          font-family: var(--font-family);
          background-color: var(--secondary);
          color: var(--dark);
          line-height: 1.5;
          padding: var(--spacing-md);
          max-width: 1200px;
          margin: 0 auto;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
          margin-top: var(--spacing-xl);
          margin-bottom: var(--spacing-lg);
          font-weight: 600;
          color: var(--primary-dark);
        }

        h1 {
          font-size: var(--font-size-3xl);
          border-bottom: 2px solid var(--primary-light);
          padding-bottom: var(--spacing-sm);
        }

        h2 {
          font-size: var(--font-size-2xl);
          border-bottom: 1px solid var(--gray-300);
          padding-bottom: var(--spacing-sm);
        }

        p {
          margin-bottom: var(--spacing-md);
        }

        a {
          color: var(--primary);
          text-decoration: none;
          transition: color 0.3s ease;
        }

        a:hover {
          color: var(--primary-dark);
          text-decoration: underline;
        }

        /* Layout components */
        .container {
          background-color: var(--light);
          border-radius: var(--radius-lg);
          box-shadow: var(--shadow-md);
          padding: var(--spacing-xl);
          margin-bottom: var(--spacing-xl);
        }

        .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: var(--spacing-xl);
          padding-bottom: var(--spacing-md);
          border-bottom: 1px solid var(--gray-300);
        }

        .navbar {
          display: flex;
          gap: var(--spacing-md);
          margin-bottom: var(--spacing-xl);
          background-color: var(--primary);
          padding: var(--spacing-md);
          border-radius: var(--radius-md);
        }

        .navbar a {
          color: white;
          padding: var(--spacing-sm) var(--spacing-md);
          border-radius: var(--radius-sm);
          transition: background-color 0.3s ease;
        }

        .navbar a:hover {
          background-color: rgba(255, 255, 255, 0.1);
          text-decoration: none;
        }

        .footer {
          margin-top: var(--spacing-xl);
          padding-top: var(--spacing-md);
          border-top: 1px solid var(--gray-300);
          color: var(--gray-600);
          font-size: var(--font-size-small);
          text-align: center;
        }

        /* Forms */
        form {
          background-color: white;
          padding: var(--spacing-lg);
          border-radius: var(--radius-md);
          box-shadow: var(--shadow-sm);
          margin-bottom: var(--spacing-lg);
        }

        label {
          display: block;
          margin-bottom: var(--spacing-xs);
          font-weight: 500;
          color: var(--gray-700);
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="number"],
        textarea {
          width: 100%;
          padding: var(--spacing-md);
          margin-bottom: var(--spacing-md);
          border: 1px solid var(--gray-300);
          border-radius: var(--radius-md);
          font-family: inherit;
          font-size: var(--font-size-base);
          transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        textarea:focus {
          outline: none;
          border-color: var(--primary);
          box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.2);
        }

        input[type="file"] {
          margin-bottom: var(--spacing-md);
          width: 0.1px;
          height: 0.1px;
          opacity: 0;
          overflow: hidden;
          position: absolute;
          z-index: -1;
        }

        textarea {
          min-height: 120px;
          resize: vertical;
        }

        input[type="submit"],
        button,
        .file-label {
          background-color: var(--primary);
          color: white;
          border: none;
          border-radius: var(--radius-md);
          padding: var(--spacing-md) var(--spacing-lg);
          font-size: var(--font-size-base);
          font-weight: 500;
          cursor: pointer;
          transition: background-color 0.3s ease, transform 0.1s ease;
          text-align: center;
          display: inline-block;
        }

        input[type="submit"]:hover,
        button:hover,
        .file-label:hover {
          background-color: var(--primary-dark);
        }

        input[type="submit"]:active,
        button:active,
        .file-label:active {
          transform: translateY(1px);
        }

        input[type="submit"]:disabled,
        button:disabled {
          background-color: var(--gray-400);
          cursor: not-allowed;
        }

        /* File upload area */
        .upload-area {
          border: 2px dashed var(--gray-400);
          padding: var(--spacing-xl);
          margin-bottom: var(--spacing-lg);
          border-radius: var(--radius-md);
          text-align: center;
          transition: border-color 0.3s ease, background-color 0.3s ease;
          cursor: pointer;
        }

        .upload-area:hover, .upload-area.dragover {
          border-color: var(--primary);
          background-color: var(--gray-100);
        }

        .upload-area p {
          color: var(--gray-600);
          margin-top: var(--spacing-md);
        }

        .file-info-display {
          margin-top: var(--spacing-sm);
          font-size: var(--font-size-small);
          color: var(--primary-dark);
        }

        /* Messages */
        .message {
          padding: var(--spacing-md);
          border-radius: var(--radius-md);
          margin-bottom: var(--spacing-lg);
        }

        .success {
          background-color: rgba(56, 161, 105, 0.1);
          border-left: 4px solid var(--success);
          color: var(--success);
        }

        .error {
          background-color: rgba(229, 62, 62, 0.1);
          border-left: 4px solid var(--error);
          color: var(--error);
        }

        .warning {
          background-color: rgba(233, 185, 73, 0.1);
          border-left: 4px solid var(--warning);
          color: var(--warning);
        }

        /* Info card */
        .info-card {
          background-color: white;
          border-radius: var(--radius-md);
          box-shadow: var(--shadow-sm);
          padding: var(--spacing-lg);
          margin-bottom: var(--spacing-lg);
          border-top: 4px solid var(--primary);
        }

        .info-card h3 {
          margin-top: 0;
          color: var(--primary);
        }

        /* Credits display */
        .credits-display {
          background-color: var(--primary-dark);
          color: white;
          padding: var(--spacing-md);
          border-radius: var(--radius-md);
          margin-bottom: var(--spacing-lg);
          text-align: center;
        }

        .credits-display p {
          margin: 0;
          font-weight: 500;
        }

        /* Navigation menu */
        .menu-links {
          display: flex;
          flex-wrap: wrap;
          gap: var(--spacing-md);
          justify-content: center;
          margin: var(--spacing-lg) 0;
          padding: var(--spacing-md);
          background-color: var(--gray-100);
          border-radius: var(--radius-md);
        }

        .menu-links a {
          padding: var(--spacing-sm) var(--spacing-md);
          border-radius: var(--radius-sm);
          transition: background-color 0.3s ease;
        }

        .menu-links a:hover {
          background-color: var(--gray-200);
          text-decoration: none;
        }

        /* Tables */
        table {
          width: 100%;
          border-collapse: collapse;
          margin-bottom: var(--spacing-lg);
          background-color: white;
          box-shadow: var(--shadow-sm);
          border-radius: var(--radius-md);
          overflow: hidden;
        }

        th, td {
          padding: var(--spacing-md);
          text-align: left;
          border-bottom: 1px solid var(--gray-300);
        }

        th {
          background-color: var(--primary);
          color: white;
          font-weight: 500;
        }

        tr:nth-child(even) {
          background-color: var(--gray-100);
        }

        tr:hover {
          background-color: var(--gray-200);
        }

        /* Code and pre areas */
        pre {
          background-color: var(--gray-800);
          color: var(--gray-100);
          padding: var(--spacing-md);
          border-radius: var(--radius-md);
          overflow-x: auto;
          margin-bottom: var(--spacing-lg);
          font-family: 'Fira Code', 'Menlo', 'Monaco', 'Courier New', monospace;
          font-size: var(--font-size-small);
        }

        pre a {
          color: var(--primary-light);
        }

        /* Responsive design */
        @media (max-width: 768px) {
          body {
            padding: var(--spacing-sm);
          }
          
          .container {
            padding: var(--spacing-md);
          }
          
          .navbar {
            flex-direction: column;
            gap: var(--spacing-xs);
          }
          
          input[type="text"],
          input[type="password"],
          input[type="email"],
          input[type="number"],
          textarea {
            padding: var(--spacing-sm);
          }
          
          .menu-links {
            flex-direction: column;
            align-items: center;
          }
        }

        /* Custom elements for DecentHash */
        .file-entry {
          display: flex;
          align-items: center;
          padding: var(--spacing-md);
          background-color: white;
          border-radius: var(--radius-md);
          margin-bottom: var(--spacing-sm);
          box-shadow: var(--shadow-sm);
          transition: box-shadow 0.3s ease;
        }

        .file-entry:hover {
          box-shadow: var(--shadow-md);
        }

        .file-icon {
          margin-right: var(--spacing-md);
          color: var(--primary);
          font-size: var(--font-size-xl);
        }

        .file-info {
          flex: 1;
        }

        .file-name {
          font-weight: 500;
          margin-bottom: var(--spacing-xs);
        }

        .file-meta {
          font-size: var(--font-size-small);
          color: var(--gray-600);
        }

        .file-actions {
          display: flex;
          gap: var(--spacing-sm);
        }

        /* Specific styles for ads and default divs */
        .ads, .default {
          padding: var(--spacing-md);
          margin-bottom: var(--spacing-lg);
          border-radius: var(--radius-md);
        }

        .ads {
          background-color: var(--gray-100);
          border: 1px solid var(--gray-300);
        }

        .default {
          background-color: white;
          box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>DecentHash</h1>
        </div>

        <h2>Upload File</h2>

        <?php if ($mode === 'sql_pay' && isset($current_user)): ?>
            <div class="credits-display">
                <p>Your current credits: <?php echo $user_credits; ?></p>
                <p>Each upload or text costs 1 credit.</p>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); if(ISSET($_GET['reply'])){echo "?reply=" . $_GET['reply'];} ?>" method="post" enctype="multipart/form-data">
            <div id="upload-area" class="upload-area">
                <label for="uploaded_file" class="file-label">Choose File</label>
                <input type="file" name="uploaded_file" id="uploaded_file">
                <p>or drag files here</p>
                <div id="file-info" class="file-info-display"></div>
            </div>

            <label for="text_content">Or enter text content:</label>
            <textarea name="text_content" id="text_content" rows="5" cols="40"></textarea>

            <label for="category">Category:</label>
            <input type="text" name="category" id="category" value="<?php if(ISSET($_GET['reply'])){echo $_GET['reply'];} ?>" required <?php if(ISSET($_GET['reply'])){echo "readonly";} ?> >

            <?php if ($mode === 'sql_pay'): ?>
                <input type="submit" value="Upload (1 credit)" <?php if (!$current_user || $user_credits < 1) echo 'disabled'; ?>>
                
                <?php if (!$current_user): ?>
                    <div class="message error">
                        <p>You must be logged in to upload files.</p>
                    </div>
                <?php elseif ($user_credits < 1): ?>
                    <div class="message error">
                        <p>Insufficient credits. Please add more credits to your account.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <input type="submit" value="Upload">
            <?php endif; ?>
        </form>

        <div class="menu-links">
            <a href="login.php">Login</a>
            <a href="index_simple.php">Temporary</a>
            <a href="register.php">Owner</a>
            <a href="metadata.php">Metadata</a>
            <a href="rank.php">Rank</a>
            <a href="search.php">Search</a>
            <a href="menu.html">Menu</a>            
            <a href="index.html">Home</a>
            <a href="logout.php">Logout</a>
        </div>

        <div class="footer">
            <p>&copy; 2025 DecentHash - All rights reserved</p>
        </div>
    </div>

    <script>
        // Get DOM elements
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('uploaded_file');
        const fileInfo = document.getElementById('file-info');

        // Handle file selection through input
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files.length > 0) {
                displayFileInfo(this.files[0]);
            }
        });

        // Handle drag and drop events
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Add visual feedback when dragging files over the area
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, function() {
                uploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, function() {
                uploadArea.classList.remove('dragover');
            }, false);
        });

        // Handle the actual drop
        uploadArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files && files.length > 0) {
                fileInput.files = files; // Transfer the dropped files to the file input
                displayFileInfo(files[0]);
            }
        }, false);

        // Handle click on the upload area
        uploadArea.addEventListener('click', function() {
            fileInput.click();
        });

        // Display file information
        function displayFileInfo(file) {
            const fileSizeInKB = (file.size / 1024).toFixed(2);
            fileInfo.innerHTML = `
                <strong>${file.name}</strong><br>
                Type: ${file.type || 'unknown'}<br>
                Size: ${fileSizeInKB} KB
            `;
            
            // Change the upload area style to indicate successful selection
            uploadArea.style.borderColor = 'var(--success)';
        }
    </script>
</body>
</html>