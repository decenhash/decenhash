<?php
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'filename' => $_POST['filename'] ?? '',
        'user' => $_POST['user'] ?? 'guest',
        'date' => date('Y-m-d'),
        'hash' => $_POST['hash'] ?? '',
        'extension' => $_POST['extension'] ?? '',
        'title' => $_POST['title'] ?? ''
    ];

    // Read existing data
    $jsonData = [];
    if (file_exists('files.json')) {
        $jsonData = json_decode(file_get_contents('files.json'), true) ?: [];
    }

    // Append new data
    $jsonData[] = $data;

    // Save back to file with JSON_UNESCAPED_SLASHES to keep clean format
    file_put_contents('files.json', json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Set username based on session or default
$username = $_SESSION['username'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Information Collector</title>
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
    </style>
</head>
<body>
    <h1>File Information Collector</h1>
    <form id="fileForm" method="post">
        <div class="form-group">
            <label for="filename">Filename:</label>
            <input type="text" id="filename" name="filename" required>
        </div>
        
        <div class="form-group">
            <label for="user">User:</label>
            <input type="text" id="user" name="user" value="<?php echo htmlspecialchars($username); ?>" 
                   <?php echo isset($_SESSION['username']) ? 'readonly class="locked"' : 'readonly class="locked"'; ?>>
        </div>
        
        <div class="form-group">
            <label for="fileInput">Select file to get hash and extension:</label>
            <input type="file" id="fileInput">
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

    <div id="jsonPreview">
        <h3>Current files.json content:</h3>
        <?php
        if (file_exists('files.json')) {
            echo htmlspecialchars(file_get_contents('files.json'));
        } else {
            echo "No data yet. Submit a form to create files.json";
        }
        ?>
    </div>

    <script>
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

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
                crypto.subtle.digest('SHA-256', new TextEncoder().encode(content))
                    .then(hashBuffer => {
                        const hashArray = Array.from(new Uint8Array(hashBuffer));
                        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                        document.getElementById('hash').value = hashHex;
                    });
            };
            reader.readAsText(file);
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