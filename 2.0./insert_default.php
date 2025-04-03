<?php
session_start();

// Available categories
$categories = [
    'code', 'images', 'videos', 'audio', 
    'crypto', 'memes', 'WhatsApp Group', 'Telegram Group'
];

// Create thumbs directory if it doesn't exist
if (!file_exists('thumbs')) {
    mkdir('thumbs', 0755, true);
}

// Initialize message variables
$message = '';
$messageType = ''; // 'success' or 'error'

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    
    // Validate category
    if (!in_array($category, $categories)) {
        $message = 'Invalid category selected';
        $messageType = 'error';
    } else {
        $data = array(
            'url' => isset($_POST['url']) ? $_POST['url'] : '',
            'user' => isset($_POST['user']) ? $_POST['user'] : 'guest',
            'date' => date('Y-m-d'),
            'hash' => isset($_POST['hash']) ? $_POST['hash'] : '',
            'extension' => isset($_POST['extension']) ? $_POST['extension'] : '',
            'title' => isset($_POST['title']) ? $_POST['title'] : '',
            'category' => $category
        );

        // Validate required fields
        if (empty($data['url'])) {
            $message = 'URL is required';
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
        $categoryFile = $category . '.json';
        $mainFile = 'files.json';
        
        // 1. Update category-specific file
        $categoryData = array();
        if (file_exists($categoryFile)) {
            $categoryContent = file_get_contents($categoryFile);
            $categoryData = json_decode($categoryContent, true);
            if ($categoryData === null) {
                $categoryData = array();
            }
        }
        $categoryData[] = $data;
        file_put_contents($categoryFile, json_encode($categoryData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Update main files.json
        $mainData = array();
        if (file_exists($mainFile)) {
            $mainContent = file_get_contents($mainFile);
            $mainData = json_decode($mainContent, true);
            if ($mainData === null) {
                $mainData = array();
            }
        }
        $mainData[] = $data;
        
        if (file_put_contents($mainFile, json_encode($mainData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
            $message = 'File information saved successfully in ' . $category . ' category and main files list!';
            $messageType = 'success';
        } else {
            $message = 'Error saving file information to main files list';
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $message = 'Error processing your request: ' . $e->getMessage();
        $messageType = 'error';
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --error-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        h1 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        input[type="text"], 
        input[type="url"],
        input[type="file"],
        select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        input[type="text"]:focus, 
        input[type="url"]:focus,
        input[type="file"]:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .locked {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
            display: inline-block;
            text-align: center;
            width: 100%;
        }
        
        button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .error {
            color: var(--error-color);
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        
        .message {
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: var(--border-radius);
            font-weight: 500;
        }
        
        .message.success {
            background-color: rgba(76, 201, 240, 0.2);
            color: #0a9396;
            border-left: 4px solid var(--success-color);
        }
        
        .message.error {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }
        
        #jsonPreview {
            background: #f8f9fa;
            padding: 1.5rem;
            margin-top: 2rem;
            border-radius: var(--border-radius);
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            border: 1px solid #e9ecef;
            overflow-x: auto;
        }
        
        .category-select {
            position: relative;
        }
        
        .category-select select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>File Information</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form id="fileForm" method="post" enctype="multipart/form-data">
            <div class="form-group category-select">
                <label for="category">Category:</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : ''; ?>>
                            <?php echo ucfirst(htmlspecialchars($cat)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="url">URL:</label>
                <input type="url" id="url" name="url" required placeholder="https://example.com">
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
            
            <button type="submit">Save</button>
        </form>
             
    </div>

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
        document.getElementById('fileForm').addEventListener('submit', function(e) {
            const category = document.getElementById('category').value;
            if (!category) return;
            
            setTimeout(() => {
                fetch(category + '.json')
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('jsonPreview').innerHTML = 
                            '<h3>Current ' + category + '.json content:</h3>' + 
                            '<pre>' + data.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
                    })
                    .catch(() => {
                        document.getElementById('jsonPreview').innerHTML = 
                            '<h3>Current ' + category + '.json content:</h3>' + 
                            '<pre>No data yet or file not found</pre>';
                    });
            }, 500);
        });
    </script>    
    <div align="center"><a href="index.html">Back</a></div><br>

</body>
</html>