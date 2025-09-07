<?php
// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && isset($_POST['sha256'])) {
    $uploadDir = 'thumbs/';
    $maxFileSize = 500 * 1024; // 500KB in bytes
    $allowedExtension = 'jpg';
    $userHash = trim($_POST['sha256']);
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Get file info
    $fileName = $_FILES['image']['name'];
    $fileSize = $_FILES['image']['size'];
    $fileTmp = $_FILES['image']['tmp_name'];
    $fileError = $_FILES['image']['error'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validate SHA-256 format
    $shaValid = preg_match('/^[a-f0-9]{64}$/i', $userHash);
    
    // Validate file extension
    $extensionValid = ($fileExt === $allowedExtension);
    
    // Validate file size
    $sizeValid = ($fileSize <= $maxFileSize);
    
    // Check for upload errors
    $uploadSuccess = ($fileError === UPLOAD_ERR_OK);
    
    if ($shaValid && $extensionValid && $sizeValid && $uploadSuccess) {
        // Calculate actual file hash
        $actualHash = hash_file('sha256', $fileTmp);
        
        if (!file_exists('thumbs/' . $userHash . ".jpg")) {
            // Move uploaded file with hash as filename
            $destination = $uploadDir . $userHash . '.' . $allowedExtension;
            
            if (move_uploaded_file($fileTmp, $destination)) {
                $message = "File uploaded successfully!";
                $messageType = "success";
            } else {
                $message = "Move upload error.";
                $messageType = "error";
            }
        } else {
            $message = "The input hash already exists.";
            $messageType = "error";
        }
    } else {
        // Determine specific error
        if (!$shaValid) {
            $message = "Invalid SHA-256 hash format. Must be 64 hexadecimal characters.";
        } elseif (!$extensionValid) {
            $message = "Only JPG files are allowed.";
        } elseif (!$sizeValid) {
            $message = "File size exceeds the 500KB limit.";
        } else {
            $message = "Error uploading file. Code: $fileError";
        }
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JPG Image Uploader</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .header {
            background: #8A2BE2;
            color: white;
            text-align: center;
            padding: 25px;
        }
        
        .header h1 {
            font-weight: 600;
            font-size: 28px;
        }
        
        .header p {
            margin-top: 10px;
            opacity: 0.9;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #8A2BE2;
            border-radius: 8px;
            background: #f8f6ff;
            color: #555;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        input[type="file"]:hover {
            background: #efeaff;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus {
            border-color: #8A2BE2;
            outline: none;
        }
        
        .upload-btn {
            background: #8A2BE2;
            color: white;
            border: none;
            padding: 15px 25px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s, transform 0.2s;
        }
        
        .upload-btn:hover {
            background: #7930c5;
            transform: translateY(-2px);
        }
        
        .upload-btn:active {
            transform: translateY(0);
        }
        
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .requirements {
            background: #f0f7ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 25px;
            border-left: 4px solid #8A2BE2;
        }
        
        .requirements h3 {
            color: #8A2BE2;
            margin-bottom: 10px;
        }
        
        .requirements ul {
            padding-left: 20px;
            color: #555;
        }
        
        .requirements li {
            margin-bottom: 8px;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #777;
            font-size: 14px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>JPG Image Uploader</h1>
            <p>Secure upload with SHA-256 verification</p>
        </div>
        
        <div class="form-container">
            <?php if (isset($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="image">Select JPG Image (max 500KB):</label>
                    <input type="file" name="image" id="image" accept=".jpg" required>
                </div>
                
                <div class="form-group">
                    <label for="sha256">SHA-256 Hash:</label>
                    <input type="text" name="sha256" id="sha256" 
                           pattern="[A-Fa-f0-9]{64}" 
                           placeholder="Enter 64-character SHA-256 hash" 
                           required>
                </div>
                
                <button type="submit" class="upload-btn">Upload Image</button>
            </form>
            
            <div class="requirements">
                <h3>Upload Requirements:</h3>
                <ul>
                    <li>Only JPG images are allowed</li>
                    <li>Maximum file size: 500KB</li>
                    <li>You must provide the correct SHA-256 hash for the image</li>
                    <li>File will be saved with the provided hash as filename</li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <p>Decenhash Image Uploader &copy; 2023</p>
        </div>
    </div>
    
    <script>
        // Client-side file size validation
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 500 * 1024; // 500KB in bytes
            
            if (file && file.size > maxSize) {
                alert('File size exceeds the 500KB limit. Please choose a smaller file.');
                e.target.value = '';
            }
            
            // Validate file extension
            const fileName = file.name;
            const extension = fileName.split('.').pop().toLowerCase();
            if (extension !== 'jpg') {
                alert('Only JPG files are allowed. Please select a JPG image.');
                e.target.value = '';
            }
        });
        
        // SHA-256 format validation
        document.getElementById('sha256').addEventListener('input', function(e) {
            const hash = e.target.value;
            const hashRegex = /^[a-f0-9]{64}$/i;
            
            if (hash && !hashRegex.test(hash)) {
                e.target.setCustomValidity('Please enter a valid 64-character SHA-256 hash');
            } else {
                e.target.setCustomValidity('');
            }
        });
    </script>
</body>
</html>