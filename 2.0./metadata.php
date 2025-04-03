<?php
// Start session
session_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
include 'db_config.php';

// Create a database connection
$conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables with your specified structure
$createTablesQuery = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        credits INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS upload_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        file_hash VARCHAR(64) NOT NULL,
        category_hash VARCHAR(64) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB",
    
    "CREATE TABLE IF NOT EXISTS file_search (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        user_id INT NOT NULL,
        description TEXT NOT NULL,
        file_hash VARCHAR(64) NOT NULL,
        image_hash VARCHAR(64),
        extension VARCHAR(10),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
];

foreach ($createTablesQuery as $query) {
    if (!$conn->query($query)) {
        die("Error creating table: " . $conn->error);
    }
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
$description = '';
$imageHash = '';
$extension = '';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to access this page.");
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get username for display
$username = '';
$email = '';
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $user = $result->fetch_assoc();
        $username = $user['username'] ?? '';
        $email = $user['email'] ?? '';
    }
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $userHash = isset($_POST['file_hash']) ? trim($_POST['file_hash']) : '';
    $extension = isset($_POST['extension']) ? trim($_POST['extension']) : '';
    
    // Validate title and description
    if (empty($title)) {
        $message = 'Title is required.';
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
            } elseif (empty($extension)) {
                $message = 'Please enter the file extension when providing a hash directly.';
                $alertClass = 'alert-danger';
            } else {
                // Check if this file hash exists in upload_logs for this user
                $checkQuery = "SELECT id FROM upload_logs WHERE user_id = ? AND file_hash = ?";
                $stmt = $conn->prepare($checkQuery);
                
                if ($stmt === false) {
                    die("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("is", $user_id, $userHash);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $message = 'You cannot add metadata for this file hash as it doesn\'t exist in your upload history.';
                    $alertClass = 'alert-danger';
                } else {
                    $finalHash = $userHash;
                    // Clean up the extension (remove leading dot if present)
                    $extension = ltrim($extension, '.');
                }
                $stmt->close();
            }
        } elseif (isset($_FILES['hash_file']) && $_FILES['hash_file']['error'] === UPLOAD_ERR_OK) {
            $tempFilePath = $_FILES['hash_file']['tmp_name'];
            $finalHash = hash_file('sha256', $tempFilePath);
            
            // Check if this file hash exists in upload_logs for this user
            $checkQuery = "SELECT id FROM upload_logs WHERE user_id = ? AND file_hash = ?";
            $stmt = $conn->prepare($checkQuery);
            
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("is", $user_id, $finalHash);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $message = 'You cannot add metadata for this file as it doesn\'t exist in your upload history.';
                $alertClass = 'alert-danger';
                unlink($tempFilePath);
            } else {
                // Get the original filename and extract extension
                $originalFilename = $_FILES['hash_file']['name'];
                $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
                
                // Delete the file after calculating the hash
                unlink($tempFilePath);
            }
            $stmt->close();
        } else {
            $message = 'Please either enter a SHA256 hash or upload a file to calculate the hash.';
            $alertClass = 'alert-danger';
        }
        
        // Handle image upload if file hash is valid
        if (empty($message) && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageTempPath = $_FILES['image']['tmp_name'];
            $imageHash = hash_file('sha256', $imageTempPath);
            $imagePath = $thumbsDirectory . $imageHash . '.jpg';
            
            // Move the uploaded image to the thumbs directory
            if (!move_uploaded_file($imageTempPath, $imagePath)) {
                $message = 'Error saving the image. Please check file permissions.';
                $alertClass = 'alert-danger';
            }
        }
        
        // If we have a valid hash and extension, proceed with saving the data
        if (empty($message) && !empty($finalHash) && !empty($extension)) {
            // Insert data into the database
            $insertQuery = "INSERT INTO file_search (title, username, description, file_hash, image_hash, extension) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ssssss", $title, $username, $description, $finalHash, $imageHash, $extension);
            
            if ($stmt->execute()) {
                $message = 'Record saved successfully with hash: ' . $finalHash;
                $alertClass = 'alert-success';
                
                // Clear form fields on success
                $title = '';
                $description = '';
                $finalHash = '';
                $imageHash = '';
                $extension = '';
            } else {
                $message = 'Error saving the record: ' . $stmt->error;
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
    <style>
        .or-divider {
            display: flex;
            align-items: center;
            margin: 15px 0;
            color: #6c757d;
        }
        .or-divider::before, .or-divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .or-divider::before {
            margin-right: 10px;
        }
        .or-divider::after {
            margin-left: 10px;
        }
        .user-display {
            background-color: #f8f9fa;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            border: 1px solid #ced4da;
            margin-bottom: 1rem;
        }
    </style>
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
                    <!-- User display (not editable) -->
                    <div class="form-group">
                        <label class="form-label">Username:</label>
                        <div class="user-display"><?php echo htmlspecialchars($username); ?></div>
                    </div>
                    
                    <!-- Title Field -->
                    <div class="form-group">
                        <label for="title" class="form-label">Title:</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($title); ?>" 
                               placeholder="Enter title" required>
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
                    
                    <div class="form-group" id="extension-group" style="<?php echo (empty($finalHash) && empty($_FILES['hash_file']['name'])) ? 'display: none;' : '' ?>">
                        <label for="extension" class="form-label">File Extension:</label>
                        <input type="text" class="form-control" id="extension" name="extension" 
                               value="<?php echo htmlspecialchars($extension); ?>" 
                               placeholder="Enter file extension (e.g., mp4, jpg, pdf)">
                        <small class="form-text text-muted">Enter the file extension without the dot (e.g., "mp4" instead of ".mp4").</small>
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
            const extensionGroup = document.getElementById('extension-group');
            const extensionInput = document.getElementById('extension');
            
            // When a file is selected, clear the manual hash input
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    hashInput.value = '';
                    extensionGroup.style.display = 'block';
                    
                    // Try to extract extension from filename
                    const filename = this.files[0].name;
                    const lastDot = filename.lastIndexOf('.');
                    if (lastDot > 0) {
                        extensionInput.value = filename.substring(lastDot + 1);
                    }
                }
            });
            
            // When hash is entered, clear the file input
            hashInput.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    fileInput.value = '';
                    extensionGroup.style.display = 'block';
                } else {
                    extensionGroup.style.display = 'none';
                }
            });
            
            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const description = document.getElementById('description').value.trim();
                const hashValue = hashInput.value.trim();
                const fileSelected = fileInput.files.length > 0;
                const extensionValue = extensionInput.value.trim();
                
                if (!title) {
                    alert('Please enter a title.');
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
                
                if ((hashValue || fileSelected) && !extensionValue) {
                    alert('Please enter the file extension.');
                    e.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>
</html>