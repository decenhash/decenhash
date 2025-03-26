<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$thumbsDirectory = 'thumbs/';

// Create thumbs directory if it doesn't exist
if (!file_exists($thumbsDirectory)) {
    mkdir($thumbsDirectory, 0755, true);
}

// Process form submission
$message = '';
$alertClass = '';
$finalHash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user-provided hash or calculate from file
    $userHash = isset($_POST['file_hash']) ? trim($_POST['file_hash']) : '';
    $hashCalculated = false;
    
    // If a file was uploaded for hash calculation
    if (isset($_FILES['hash_file']) && $_FILES['hash_file']['error'] === UPLOAD_ERR_OK) {
        $tempFilePath = $_FILES['hash_file']['tmp_name'];
        $calculatedHash = hash_file('sha256', $tempFilePath);
        $finalHash = $calculatedHash;
        $hashCalculated = true;
        
        // Delete the file after calculating the hash
        unlink($tempFilePath);
        
        $message = "Hash calculated from file: " . $finalHash;
        $alertClass = 'alert-info';
    } 
    // Otherwise use the manually entered hash
    elseif (!empty($userHash)) {
        // Validate hash format (SHA256 is 64 hex characters)
        if (!preg_match('/^[a-f0-9]{64}$/i', $userHash)) {
            $message = 'Please enter a valid SHA256 hash (64 hexadecimal characters).';
            $alertClass = 'alert-danger';
        } else {
            $finalHash = $userHash;
            $hashCalculated = true;
        }
    } else {
        $message = 'Please either enter a SHA256 hash or upload a file to calculate the hash.';
        $alertClass = 'alert-danger';
    }
    
    // Handle thumbnail upload if we have a valid hash
    if ($hashCalculated && isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $thumbnailTmpPath = $_FILES['thumbnail']['tmp_name'];
        $thumbnailExtension = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
        $thumbnailNewName = $finalHash . '.' . $thumbnailExtension;
        $thumbnailDestination = $thumbsDirectory . $thumbnailNewName;
        
        // Move uploaded thumbnail to destination with hash as filename
        if (move_uploaded_file($thumbnailTmpPath, $thumbnailDestination)) {
            $message .= ($message ? '<br>' : '') . 'Thumbnail uploaded successfully as: ' . $thumbnailNewName;
            $alertClass = 'alert-success';
        } else {
            $message .= ($message ? '<br>' : '') . 'Error saving thumbnail file.';
            $alertClass = 'alert-danger';
        }
    } elseif ($hashCalculated) {
        $message .= ($message ? '<br>' : '') . 'Please select a thumbnail file to upload.';
        $alertClass = 'alert-warning';
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
        <h1 class="mb-4">Thumbnail Upload System</h1>
        
        <?php if ($message): ?>
        <div class="alert <?php echo $alertClass; ?>" role="alert">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Upload Thumbnail with Hash Filename</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
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
                    
                    <hr class="my-4">
                    
                    <div class="form-group">
                        <label for="thumbnail" class="form-label">Thumbnail File:</label>
                        <input type="file" class="form-control" id="thumbnail" name="thumbnail" required>
                        <small class="form-text text-muted">Upload a thumbnail image. It will be saved with the hash as its filename.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit</button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($finalHash)): ?>
        <div class="mt-4">
            <div class="card">
                <div class="card-header">Result</div>
                <div class="card-body">
                    <p><strong>Hash used:</strong> <?php echo htmlspecialchars($finalHash); ?></p>
                    <?php if (isset($thumbnailNewName)): ?>
                    <p><strong>Thumbnail saved as:</strong> <?php echo htmlspecialchars($thumbnailNewName); ?></p>
                    <p><strong>Thumbnail path:</strong> <?php echo htmlspecialchars($thumbnailDestination); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
                const hashValue = hashInput.value.trim();
                const fileSelected = fileInput.files.length > 0;
                const thumbnailSelected = document.getElementById('thumbnail').files.length > 0;
                
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
                
                if (!thumbnailSelected) {
                    alert('Please select a thumbnail file to upload.');
                    e.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>
</html>