<?php
session_start();

// Create thumbs directory if it doesn't exist
if (!file_exists('thumbs')) {
    mkdir('thumbs', 0755, true);
}

// Initialize message variables
$message = '';
$messageType = ''; // 'success' or 'error'

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = array(
        'filename' => isset($_POST['filename']) ? $_POST['filename'] : '',
        'user' => isset($_POST['user']) ? $_POST['user'] : 'guest',
        'date' => date('Y-m-d'),
        'hash' => isset($_POST['hash']) ? $_POST['hash'] : '',
        'extension' => isset($_POST['extension']) ? $_POST['extension'] : '',
        'title' => isset($_POST['title']) ? $_POST['title'] : ''
    );

    // Validate required fields
    if (empty($data['filename'])) {
        $message = 'Filename is required';
        $messageType = 'error';
    } elseif (empty($data['hash'])) {
        $message = 'File hash could not be generated';
        $messageType = 'error';
    } elseif (empty($data['title'])) {
        $message = 'Title is required';
        $messageType = 'error';
    } else {
        // Handle file upload if present
        $fileUploadSuccess = true;
        if (!empty($_FILES['fileInput']['tmp_name']) && !empty($_POST['hash'])) {
            $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $_FILES['fileInput']['tmp_name']);
            finfo_close($fileInfo);

            if (in_array($mimeType, $allowedTypes)) {
                $hashFilename = $_POST['hash'] . $_POST['extension'];
                $destination = 'thumbs/' . $hashFilename;
                
                if (!move_uploaded_file($_FILES['fileInput']['tmp_name'], $destination)) {
                    $fileUploadSuccess = false;
                    $message = 'Error uploading thumbnail file';
                    $messageType = 'error';
                }
            } else {
                $fileUploadSuccess = false;
                $message = 'Invalid file type. Only JPEG, PNG, GIF, or WEBP allowed';
                $messageType = 'error';
            }
        }

        if ($fileUploadSuccess) {
            try {
                // Read existing data
                $jsonData = array();
                if (file_exists('files.json')) {
                    $jsonContent = file_get_contents('files.json');
                    $jsonData = json_decode($jsonContent, true);
                    if ($jsonData === null) {
                        $jsonData = array();
                    }
                }

                // Append new data
                $jsonData[] = $data;

                // Save back to file
                if (file_put_contents('files.json', json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                    $message = 'File information saved successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error saving file information';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error processing your request: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Set username based on session or default
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Information</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .locked {
            background-color: #f2f2f2;
        }
        #jsonPreview {
            background: #f5f5f5;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .error {
            color: red;
            margin-top: 5px;
        }
        .message {
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .message.success {
            background-color: #dff0d8;
            color: #3c763d;
            border: 1px solid #d6e9c6;
        }
        .message.error {
            background-color: #f2dede;
            color: #a94442;
            border: 1px solid #ebccd1;
        }
    </style>
</head>
<body>
    <h1>File Information</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <form id="fileForm" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="filename">URL:</label>
            <input type="text" id="filename" name="filename" required>
        </div>
        
        <div class="form-group">
            <label for="user">User:</label>
            <input type="text" id="user" name="user" value="<?php echo htmlspecialchars($username); ?>" 
                   <?php echo isset($_SESSION['username']) ? 'readonly class="locked"' : 'readonly class="locked"'; ?>>
        </div>
        
        <div class="form-group">
            <label for="fileInput">Select image file (JPEG, PNG, GIF, WEBP):</label>
            <input type="file" id="fileInput" name="fileInput" accept="image/*">
            <div id="fileError" class="error"></div>
        </div>
        
        <div class="form-group">
            <label for="hash">Hash:</label>
            <input type="text" id="hash" name="hash" readonly class="locked">
        </div>
        
        <div class="form-group">
            <label for="extension">Extension:</label>
            <input type="text" id="extension" name="extension" readonly class="locked">
        </div>
        
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required>
        </div>
        
        <button type="submit">Save Information</button>
    </form>

    <script>
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const errorElement = document.getElementById('fileError');
            errorElement.textContent = '';
            
            if (!file) return;

            // Validate image type
            if (!file.type.match('image.*')) {
                errorElement.textContent = 'Please select an image file (JPEG, PNG, GIF, or WEBP)';
                return;
            }

            // Set filename in filename field
            document.getElementById('filename').value = file.name.split('.').slice(0, -1).join('.');

            // Get file extension (ensure it includes the dot)
            let fileNameParts = file.name.split('.');
            let extension = fileNameParts.length > 1 ? 
                          '.' + fileNameParts.pop().toLowerCase() : 
                          '';
            document.getElementById('extension').value = extension;

            // Calculate file hash (SHA-256)
            const reader = new FileReader();
            reader.onload = function(e) {
                const content = e.target.result;
                
                // Create SHA-256 hash
                crypto.subtle.digest('SHA-256', new Uint8Array(content))
                    .then(hashBuffer => {
                        const hashArray = Array.from(new Uint8Array(hashBuffer));
                        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                        document.getElementById('hash').value = hashHex;
                    });
            };
            reader.readAsArrayBuffer(file);
        });

        // Update JSON preview after form submission
        document.getElementById('fileForm').addEventListener('submit', function() {
            setTimeout(() => {
                fetch('files.json')
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('jsonPreview').innerHTML = 
                            '<h3>Current files.json content:</h3>' + 
                            data.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    });
            }, 500);
        });
    </script>
</body>
</html>