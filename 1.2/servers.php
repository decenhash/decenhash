<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$urlsDirectory = 'servers/';
$message = '';
$alertClass = '';
$inputUrl = '';
$hash = '';
$ipAddress = '';

// Create urls directory if it doesn't exist
if (!file_exists($urlsDirectory)) {
    mkdir($urlsDirectory, 0755, true);
}

// Function to get IP address from URL
function getUrlIpAddress($url) {
    // Parse the URL to extract the host
    $host = parse_url($url, PHP_URL_HOST);
    
    if (!$host) {
        return false;
    }
    
    // Resolve the IP address
    $ip = gethostbyname($host);
    
    // Check if IP resolution was successful
    return ($ip !== $host) ? $ip : false;
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
        // Get IP address of the URL
        $ipAddress = getUrlIpAddress($inputUrl);
        
        if ($ipAddress === false) {
            $message = 'Could not resolve IP address for the given URL.';
            $alertClass = 'alert-danger';
        } else {
            // Calculate SHA256 hash of the IP address
            $hash = hash('sha256', $ipAddress);
            
            // Create file path using the hash as filename
            $filePath = $urlsDirectory . $hash;
            
            // Save the URL to the file
            if (file_put_contents($filePath, $inputUrl)) {
                $message = 'URL saved successfully!';
                $message .= '<br>IP Address: ' . $ipAddress;
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
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL IP Hash System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container {
            max-width: 800px;
            margin-top: 50px;
        }
        .form-group {
            margin-bottom: 20px;
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
                        <small class="form-text text-muted">Enter a valid URL to resolve its IP and generate a hash.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save URL</button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($hash) && !empty($ipAddress)): ?>
        <div class="card mt-4">
            <div class="card-header">URL Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>IP Address:</strong> <?php echo htmlspecialchars($ipAddress); ?>
                </div>
                <div class="mb-3">
                    <strong>IP Hash (SHA256):</strong> <?php echo $hash; ?>
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