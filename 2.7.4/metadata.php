<?php
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['filename'])) {
        // First form submission - check file existence
        $filename = basename($_POST['filename']);
        $filepath = 'files/' . $filename;
        $fileExists = file_exists($filepath);
        
        // Check if JSON already exists
        $jsonFilename = 'metadata/' . pathinfo($filename, PATHINFO_FILENAME) . '.json';
        $jsonExists = file_exists($jsonFilename);
    } elseif (isset($_POST['metadata'])) {
        // Second form submission - save metadata
        $filename = basename($_POST['original_filename']);
        $jsonFilename = 'metadata/' . pathinfo($filename, PATHINFO_FILENAME) . '.json';
        
        // Check again if JSON exists (in case someone bypassed client-side check)
        if (!file_exists($jsonFilename)) {
            $metadata = $_POST['metadata'];
            
            // Validate and sanitize data
            $title = filter_var($metadata['title'], FILTER_SANITIZE_STRING);
            $description = filter_var($metadata['description'], FILTER_SANITIZE_STRING);
            $btc_address = filter_var($metadata['btc_address'], FILTER_SANITIZE_STRING);
            $url = filter_var($metadata['url'], FILTER_SANITIZE_URL);
            
            // Create JSON data
            $jsonData = [
                'original_file' => $filename,
                'title' => $title,
                'description' => $description,
                'btc_address' => $btc_address,
                'url' => $url,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Save to JSON file
            file_put_contents($jsonFilename, json_encode($jsonData, JSON_PRETTY_PRINT));
            
            // Success message
            $successMessage = 'Metadata saved successfully to ' . htmlspecialchars($jsonFilename);
        } else {
            $errorMessage = 'Metadata file already exists and was not overwritten.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Metadata Collector</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="url"],
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .error {
            color: #d9534f;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #d9534f;
            border-radius: 4px;
            background-color: #f9f2f2;
        }
        .success {
            color: #3c763d;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #3c763d;
            border-radius: 4px;
            background-color: #dff0d8;
        }
        .warning {
            color: #8a6d3b;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #faebcc;
            border-radius: 4px;
            background-color: #fcf8e3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>File Metadata</h1>
        
        <?php if (isset($successMessage)): ?>
            <div class="success"><?= $successMessage ?></div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="error"><?= $errorMessage ?></div>
        <?php endif; ?>
        
        <?php if (!isset($fileExists) || (isset($fileExists) && !$fileExists)): ?>
            <!-- First form: Check file existence -->
            <form id="checkFileForm" method="POST">
                <div class="form-group">
                    <label for="filename">Enter filename:</label>
                    <input type="text" id="filename" name="filename"  
                           placeholder="example.pdf" value="<?= isset($filename) ? htmlspecialchars($filename) : '' ?>">
                </div>
                <button type="submit">Check File</button>
            </form>
            
            <?php if (isset($fileExists) && !$fileExists): ?>
                <div class="error">File not found in the 'files' directory.</div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($fileExists) && $fileExists): ?>
            <!-- Second form: Collect metadata -->
            <form id="metadataForm" method="POST">
                <input type="hidden" name="original_filename" value="<?= htmlspecialchars($filename) ?>">
                
                <?php if (isset($jsonExists) && $jsonExists): ?>
                    <div class="warning">Warning: A metadata file already exists for this file. Submitting this form will not overwrite it.</div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="metadata[title]" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="metadata[description]" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="btc_address">BTC Address:</label>
                    <input type="text" id="btc_address" name="metadata[btc_address]" >
                </div>
                
                <div class="form-group">
                    <label for="url">URL:</label>
                    <input type="url" id="url" name="metadata[url]" >
                </div>
                
                <button type="submit" <?= isset($jsonExists) && $jsonExists ? 'disabled' : '' ?>>Save Metadata</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Basic BTC address validation (simple format check)
            const btcAddressInput = document.getElementById('btc_address');
            if (btcAddressInput) {
                btcAddressInput.addEventListener('blur', function() {
                    const btcAddress = this.value.trim();
                    if (btcAddress && !isValidBTCAddress(btcAddress)) {
                        alert('Please enter a valid BTC address (starts with 1, 3, or bc1)');
                        this.focus();
                    }
                });
            }
            
            // URL validation
            const urlInput = document.getElementById('url');
            if (urlInput) {
                urlInput.addEventListener('blur', function() {
                    const url = this.value.trim();
                    if (url && !isValidURL(url)) {
                        alert('Please enter a valid URL (e.g., https://example.com)');
                        this.focus();
                    }
                });
            }
            
            // Prevent form submission if JSON exists
            const metadataForm = document.getElementById('metadataForm');
            if (metadataForm) {
                metadataForm.addEventListener('submit', function(e) {
                    const submitButton = this.querySelector('button[type="submit"]');
                    if (submitButton.disabled) {
                        e.preventDefault();
                        alert('Metadata file already exists and cannot be overwritten.');
                    }
                });
            }
        });

        function isValidBTCAddress(address) {
            // Simple BTC address format validation
            return /^(1|3|bc1)[a-zA-HJ-NP-Z0-9]{25,39}$/.test(address);
        }

        function isValidURL(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        }
    </script>
</body>
</html>