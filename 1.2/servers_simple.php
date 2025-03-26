<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$urlsDirectory = 'servers_tmp/';
$message = '';
$alertClass = '';
$inputUrl = '';
$hash = '';
$content = '';

// Create urls directory if it doesn't exist
if (!file_exists($urlsDirectory)) {
    mkdir($urlsDirectory, 0755, true);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user-provided URL
    $inputUrl = isset($_POST['url']) ? trim($_POST['url']) : '';
    
    // Validate URL
    if (empty($inputUrl)) {
        $message = 'Please enter a URL.';
        $alertClass = 'alert-danger';
    } elseif (!filter_var($inputUrl, FILTER_VALIDATE_URL)) {
        $message = 'Please enter a valid URL.';
        $alertClass = 'alert-danger';
    } else {
        // Initialize a cURL session
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $inputUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For simplicity; consider enabling in production
        
        // Execute the cURL request
        $content = curl_exec($ch);
        
        // Check for errors
        if (curl_errno($ch)) {
            $message = 'Error fetching URL: ' . curl_error($ch);
            $alertClass = 'alert-danger';
        } else {
            // Calculate SHA256 hash of the content
            $hash = hash('sha256', $content);
            
            // Create file path using the hash as filename
            $filePath = $urlsDirectory . $hash;
            
            // Save the URL to the file
            if (file_put_contents($filePath, $inputUrl)) {
                $message = 'URL saved successfully!';
                $message .= '<br>Hash: ' . $hash;
                $message .= '<br>File saved as: ' . $filePath;
                $alertClass = 'alert-success';
                
                // Clear the input field on success
                $inputUrl = '';
            } else {
                $message = 'Error saving the URL. Please check file permissions.';
                $alertClass = 'alert-danger';
            }
        }
        
        // Close the cURL session
        curl_close($ch);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Content Hash System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 800px;
            margin-top: 50px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            max-height: 300px;
            overflow: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="notify.php">Notify servers</a>
        <h1 class="mb-4">Servers</h1>
        
        <?php if ($message): ?>
        <div class="alert <?php echo $alertClass; ?>" role="alert">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Servers</div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="url" class="form-label">URL:</label>
                        <input type="url" class="form-control" id="url" name="url" 
                               value="<?php echo htmlspecialchars($inputUrl); ?>" 
                               placeholder="https://example.com" required>
                        <small class="form-text text-muted">Enter a valid URL to fetch and hash its content.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save URL</button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($hash) && !empty($content)): ?>
        <div class="card mt-4">
            <div class="card-header">Content Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Content Length:</strong> <?php echo strlen($content); ?> bytes
                </div>
                <div class="mb-3">
                    <strong>Content Preview:</strong>
                    <pre><?php echo htmlspecialchars(substr($content, 0, 500)) . (strlen($content) > 500 ? '...' : ''); ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Simple client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const urlInput = document.getElementById('url').value.trim();
            
            if (!urlInput) {
                alert('Please enter a URL.');
                e.preventDefault();
                return;
            }
            
            // Basic URL validation (more comprehensive validation on the server)
            if (!urlInput.match(/^https?:\/\/.+/i)) {
                alert('Please enter a valid URL (e.g., https://example.com).');
                e.preventDefault();
            }
        });
    </script>
</body>
</html>