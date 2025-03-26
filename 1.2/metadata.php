<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Create the `file_search` table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS file_search (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    image_hash VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($createTableQuery)) {
    die("Error creating table: " . $conn->error);
}

// Configuration
$jsonDirectory = 'JSON/';
$thumbsDirectory = 'thumbs/';

// Create directories if they don't exist
if (!file_exists($jsonDirectory)) {
    mkdir($jsonDirectory, 0755, true);
}
if (!file_exists($thumbsDirectory)) {
    mkdir($thumbsDirectory, 0755, true);
}

// Initialize variables
$message = '';
$alertClass = '';
$finalHash = '';
$title = '';
$username = '';
$description = '';
$imageHash = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = isset($_POST['title']) ? trim($_POST['title']) : ''; // New field
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $userHash = isset($_POST['file_hash']) ? trim($_POST['file_hash']) : '';
    
    // Validate title, username, and description
    if (empty($title)) {
        $message = 'Title is required.';
        $alertClass = 'alert-danger';
    } elseif (empty($username)) {
        $message = 'Username is required.';
        $alertClass = 'alert-danger';
    } elseif (empty($description)) {
        $message = 'Description is required.';
        $alertClass = 'alert-danger';
    } else {
        // Determine the hash - either directly entered or calculated from file
        if (!empty($userHash)) {
            // Validate hash format
            if (!preg_match('/^[a-f0-9]{64}$/i', $userHash)) {
                $message = 'Please enter a valid SHA256 hash (64 hexadecimal characters).';
                $alertClass = 'alert-danger';
            } else {
                $finalHash = $userHash;
            }
        } elseif (isset($_FILES['hash_file']) && $_FILES['hash_file']['error'] === UPLOAD_ERR_OK) {
            $tempFilePath = $_FILES['hash_file']['tmp_name'];
            $finalHash = hash_file('sha256', $tempFilePath);
            
            // Delete the file after calculating the hash
            unlink($tempFilePath);
        } else {
            $message = 'Please either enter a SHA256 hash or upload a file to calculate the hash.';
            $alertClass = 'alert-danger';
        }
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageTempPath = $_FILES['image']['tmp_name'];
            $imageHash = hash_file('sha256', $imageTempPath);
            $imagePath = $thumbsDirectory . $imageHash . '.jpg';
            
            // Move the uploaded image to the thumbs directory
            if (!move_uploaded_file($imageTempPath, $imagePath)) {
                $message = 'Error saving the image. Please check file permissions.';
                $alertClass = 'alert-danger';
            }
        }
        
        // If we have a valid hash, proceed with saving the data
        if (!empty($finalHash)) {
            // Insert data into the database
            $stmt = $conn->prepare("INSERT INTO file_search (title, username, description, file_hash, image_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $title, $username, $description, $finalHash, $imageHash);
            
            if ($stmt->execute()) {
                $message = 'Record saved successfully with hash: ' . $finalHash;
                $alertClass = 'alert-success';
                
                // Clear form fields on success
                $title = '';
                $username = '';
                $description = '';
                $finalHash = '';
                $imageHash = '';
            } else {
                $message = 'Error saving the record. Please check database permissions.';
                $alertClass = 'alert-danger';
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
    <title>Simple Metadata System</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Simple Metadata System</h1>
        
        <?php if ($message): ?>
        <div class="alert <?php echo $alertClass; ?>" role="alert">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Create New Record</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <!-- New Title Field -->
                    <div class="form-group">
                        <label for="title" class="form-label">Title:</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($title); ?>" 
                               placeholder="Enter title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">User:</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               placeholder="Enter username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description:</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3" placeholder="Enter description" required><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="file_hash" class="form-label">SHA256 Hash:</label>
                        <input type="text" class="form-control" id="file_hash" name="file_hash" 
                               value="<?php echo htmlspecialchars($finalHash); ?>" 
                               placeholder="Enter the SHA256 hash">
                        <small class="form-text text-muted">Enter a valid SHA256 hash (64 hexadecimal characters).</small>
                    </div>
                    
                    <div class="or-divider">
                        <span>OR</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="hash_file" class="form-label">Calculate Hash from File:</label>
                        <input type="file" class="form-control" id="hash_file" name="hash_file">
                        <small class="form-text text-muted">Upload a file to calculate its SHA256 hash. The file will be deleted after hash calculation.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="image" class="form-label">Upload Image:</label>
                        <input type="file" class="form-control" id="image" name="image">
                        <small class="form-text text-muted">Upload an image to be saved in the thumbs directory.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Record</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Client-side validation and form handling
        document.addEventListener('DOMContentLoaded', function() {
            const hashInput = document.getElementById('file_hash');
            const fileInput = document.getElementById('hash_file');
            
            // When a file is selected, clear the manual hash input
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    hashInput.value = '';
                }
            });
            
            // When hash is entered, clear the file input
            hashInput.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    fileInput.value = '';
                }
            });
            
            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const username = document.getElementById('username').value.trim();
                const description = document.getElementById('description').value.trim();
                const hashValue = hashInput.value.trim();
                const fileSelected = fileInput.files.length > 0;
                
                if (!title) {
                    alert('Please enter a title.');
                    e.preventDefault();
                    return;
                }
                
                if (!username) {
                    alert('Please enter a username.');
                    e.preventDefault();
                    return;
                }
                
                if (!description) {
                    alert('Please enter a description.');
                    e.preventDefault();
                    return;
                }
                
                if (!hashValue && !fileSelected) {
                    alert('Please either enter a SHA256 hash or upload a file to calculate the hash.');
                    e.preventDefault();
                    return;
                }
                
                if (hashValue && !hashValue.match(/^[a-f0-9]{64}$/i)) {
                    alert('Please enter a valid SHA256 hash (64 hexadecimal characters).');
                    e.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>
</html>