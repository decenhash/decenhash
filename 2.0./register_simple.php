<?php
// Initialize message variables
$successMessage = $errorMessage = "";

// Process form submission - only when POST request is made
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if all fields are provided
    if (!isset($_POST['public_key'], $_POST['password'], $_FILES['file'])) {
        $errorMessage = "Error: Missing required fields.";
    } else {
        $publicKey = trim($_POST['public_key']);
        $password = trim($_POST['password']);
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

            // Check if this file hash is already registered
            $blockFile = "blocks_tmp/$fileHash";
            
            // Create blocks directory if it doesn't exist
            if (!file_exists('blocks_tmp')) {
                mkdir('blocks_tmp', 0755, true);
            }
            
            // Check if the file already exists
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Ownership Registration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f7f9fc;
        }
        
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        input[type="text"], 
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        
        input[type="file"] {
            padding: 10px 0;
        }
        
        input[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        input[type="submit"]:hover {
            background-color: #2980b9;
        }
        
        .help-btn {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #3498db;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            cursor: pointer;
            margin-left: 8px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .tooltip {
            visibility: hidden;
            position: absolute;
            background-color: #2c3e50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            width: 250px;
            z-index: 1;
            opacity: 0;
            transition: opacity 0.3s;
            left: 0;
            top: 100%;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .help-btn:hover + .tooltip {
            visibility: visible;
            opacity: 1;
        }
        
        .success-message, .error-message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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