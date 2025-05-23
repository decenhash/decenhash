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
<html>
<head>
    <title>File/Text Upload with Category</title>
<style>
:root {
  /* Color variables */
  --primary: #2563eb;
  --primary-hover: #1d4ed8;
  --success: #22c55e;
  --error: #ef4444;
  --background: #f8fafc;
  --surface: #ffffff;
  --text: #0f172a;
  --text-secondary: #64748b;
  --border: #e2e8f0;
  
  /* Spacing variables */
  --spacing-sm: 0.5rem;
  --spacing-md: 1rem;
  --spacing-lg: 1.5rem;
  
  /* Border radius */
  --radius-sm: 0.375rem;
  --radius-md: 0.5rem;
  
  /* Shadows */
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  
  /* Transitions */
  --transition: 150ms cubic-bezier(0.4, 0, 0.2, 1);
}

body {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  background-color: var(--background);
  color: var(--text);
  margin: 0;
  padding: var(--spacing-lg);
  line-height: 1.5;
}

h2 {
  color: var(--text);
  text-align: center;
  font-weight: 600;
  margin-bottom: var(--spacing-lg);
}

form {
  background-color: var(--surface);
  padding: var(--spacing-lg);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
  width: 100%;
  max-width: 600px;
  margin: var(--spacing-lg) auto;
  border: 1px solid var(--border);
}

label {
  display: block;
  margin-bottom: var(--spacing-sm);
  font-weight: 500;
  color: var(--text);
}

input[type="file"],
input[type="text"],
textarea {
  width: 100%;
  padding: var(--spacing-md);
  margin-bottom: var(--spacing-md);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  box-sizing: border-box;
  transition: var(--transition);
  outline: none;
}

input[type="text"]:focus,
textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

textarea {
  font-family: inherit;
  min-height: 120px;
  resize: vertical;
}

input[type="submit"] {
  background-color: var(--primary);
  color: white;
  padding: var(--spacing-md) var(--spacing-lg);
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  font-size: 1rem;
  font-weight: 500;
  transition: var(--transition);
  width: 100%;
}

input[type="submit"]:hover {
  background-color: var(--primary-hover);
}

p.success {
  color: var(--success);
  background-color: rgb(34 197 94 / 0.1);
  border: 1px solid rgb(34 197 94 / 0.2);
  padding: var(--spacing-md);
  border-radius: var(--radius-sm);
  margin-bottom: var(--spacing-md);
}

p.error {
  color: var(--error);
  background-color: rgb(239 68 68 / 0.1);
  border: 1px solid rgb(239 68 68 / 0.2);
  padding: var(--spacing-md);
  border-radius: var(--radius-sm);
  margin-bottom: var(--spacing-md);
}

pre {
  background-color: rgb(15 23 42 / 0.03);
  padding: var(--spacing-md);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  overflow-x: auto;
  font-family: ui-monospace, monospace;
  font-size: 0.875rem;
}

input[readonly],
textarea[readonly] {
  background-color: rgb(15 23 42 / 0.03);
  border: 1px solid var(--border);
  color: var(--text-secondary);
  cursor: not-allowed;
}

@media (max-width: 640px) {
  body {
    padding: var(--spacing-md);
  }
  
  form {
    padding: var(--spacing-md);
  }
}

input[readonly],
textarea[readonly] {
  background-color: #eee; /* Example: Light gray background */
  border: 1px solid #ccc; /* Example: Slightly darker border */
  color: #777; /* Example: Darker gray text */
  cursor: default; /* Example: Change cursor to default arrow */
}

</style>
</head>
<body>

<div align="right"><a href="index_2.php">Click here for drag files</a></div>

<h2>Upload File</h2>

<?php if ($mode === 'sql_pay' && isset($current_user)): ?>
    <p>Your current credits: <?php echo $user_credits; ?></p>
    <p>Each upload or text costs 1 credit.</p>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); if(ISSET($_GET['reply'])){echo "?reply=" . $_GET['reply'];} ?>" method="post" enctype="multipart/form-data">
    <label for="uploaded_file">Select File:</label>
    <input type="file" name="uploaded_file" id="uploaded_file"><br><br>

    <label for="text_content">Or enter text content:</label><br>
    <textarea name="text_content" id="text_content" rows="5" cols="40"></textarea><br><br>

    <label for="category">Category:</label>
    <input type="text" name="category" id="category" value="<?php if(ISSET($_GET['reply'])){echo $_GET['reply'];} ?>" required <?php if(ISSET($_GET['reply'])){echo "readonly";} ?> ><br><br>

    <?php if ($mode === 'sql_pay'): ?>
        <input type="submit" value="Upload (1 credit)" <?php if (!$current_user || $user_credits < 1) echo 'disabled'; ?>>
        <?php if (!$current_user): ?>
            <p class="error">You must be logged in to upload files.</p>
        <?php elseif ($user_credits < 1): ?>
            <p class="error">Insufficient credits. Please add more credits to your account.</p>
        <?php endif; ?>
    <?php else: ?>
        <input type="submit" value="Upload">
    <?php endif; ?>
</form>

<div align="center"><a href="blockchain.php">Blockchain</a> <div align="center"><a href="index.html">Home</a> <a href="login.php">Login</a> <a href="index_simple.php">Temporary</a> <a href="register.php">Owner</a> <a href="metadata.php">Metadata</a> <a href="search.php">Search</a> <a href="rank.php">Rank</a> <a href="menu.html">Menu</a> <a href="logout.php">Logout</a> <div align="center"><a href="about.html">About</a></div>
</body>
</html>