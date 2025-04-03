<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the file hash from the URL
$fileHash = isset($_GET['hash']) ? trim($_GET['hash']) : '';

if (empty($fileHash)) {
    die("Invalid file hash.");
}

// Directory where files are stored
$dataDirectory = 'data/' . $fileHash . '/';

// Check if the directory exists
if (!is_dir($dataDirectory)) {
    die("No files found for the provided file hash.");
}

// Supported file extensions
$supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'pdf', 'zip'];

// Find the file with the same hash and supported extension
$foundFile = null;
foreach ($supportedExtensions as $ext) {
    $filePath = $dataDirectory . $fileHash . '.' . $ext;
    if (file_exists($filePath)) {
        $foundFile = $filePath;
        break;
    }
}

if (!$foundFile) {
    die("No supported file found for the provided file hash.");
}

// Get the file type
$fileType = mime_content_type($foundFile);
$fileExtension = pathinfo($foundFile, PATHINFO_EXTENSION);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Details</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .file-preview {
            max-width: 100%;
            height: auto;
            margin-bottom: 20px;
        }
        .file-download {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">File Details</h1>
        
        <div class="card">
            <div class="card-body">
                <h3>File Hash: <?php echo htmlspecialchars($fileHash); ?></h3>
                
                <!-- Display or provide download link for the file -->
                <div class="mt-4">
                    <h4>File Preview</h4>
                    <?php if (strpos($fileType, 'image/') === 0): ?>
                        <!-- Display image -->
                        <img src="<?php echo $foundFile; ?>" alt="File Preview" class="file-preview">
                    <?php elseif ($fileExtension === 'mp4'): ?>
                        <!-- Display video -->
                        <video controls class="file-preview">
                            <source src="<?php echo $foundFile; ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    <?php elseif ($fileExtension === 'mp3'): ?>
                        <!-- Display audio -->
                        <audio controls class="file-preview">
                            <source src="<?php echo $foundFile; ?>" type="audio/mpeg">
                            Your browser does not support the audio tag.
                        </audio>
                    <?php else: ?>
                        <!-- Provide download link for unsupported preview files -->
                        <p>This file type cannot be previewed. Please download the file.</p>
                    <?php endif; ?>
                    
                    <!-- Download link -->
                    <div class="file-download">
                        <a href="<?php echo $foundFile; ?>" download class="btn btn-primary">Download File</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>