<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$jsonDirectory = 'JSON/';

// Create JSON directory if it doesn't exist
if (!file_exists($jsonDirectory)) {
    mkdir($jsonDirectory, 0755, true);
}

// Initialize variables
$message = '';
$alertClass = '';
$finalHash = '';
$username = '';
$description = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $userHash = isset($_POST['file_hash']) ? trim($_POST['file_hash']) : '';
    
    // Validate username and description
    if (empty($username)) {
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
        
        // If we have a valid hash, proceed with saving the data
        if (!empty($finalHash)) {
            $jsonFilePath = $jsonDirectory . $finalHash . '.json';
            
            // Check if file already exists
            if (file_exists($jsonFilePath)) {
                $message = 'A record with this hash already exists. Please use a different hash.';
                $alertClass = 'alert-danger';
            } else {
                // Create data structure
                $data = [
                    'user' => $username,
                    'description' => $description,
                    'hash' => $finalHash,
                    'date' => date('Y-m-d H:i:s')
                ];
                
                // Save as JSON file
                if (file_put_contents($jsonFilePath, json_encode($data, JSON_PRETTY_PRINT))) {
                    $message = 'Record saved successfully with hash: ' . $finalHash;
                    $alertClass = 'alert-success';
                    
                    // Clear form fields on success
                    $username = '';
                    $description = '';
                    $finalHash = '';
                } else {
                    $message = 'Error saving the record. Please check file permissions.';
                    $alertClass = 'alert-danger';
                }
            }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 800px;
            margin-top: 50px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .or-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 15px 0;
        }
        .or-divider::before, .or-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #dee2e6;
        }
        .or-divider span {
            padding: 0 10px;
            color: #6c757d;
            font-weight: bold;
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
                const username = document.getElementById('username').value.trim();
                const description = document.getElementById('description').value.trim();
                const hashValue = hashInput.value.trim();
                const fileSelected = fileInput.files.length > 0;
                
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