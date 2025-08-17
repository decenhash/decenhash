<?php
// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if file was uploaded without errors
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        
        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            echo "Error: File is too large (max 10MB).";
            exit;
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check if extension is not php
        if ($extension === 'php') {
            echo "Error: PHP files are not allowed.";
            exit;
        }
        
        // Calculate SHA-256 hash of the file
        $fileHash = hash_file('sha256', $file['tmp_name']);
        $targetDir = 'files/';
        $targetFile = $targetDir . $fileHash . ($extension ? '.' . $extension : '');
        
        // Create files directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        // Check if file already exists
        if (file_exists($targetFile)) {
            echo "Error: File already exists.";
        } else {
            // Move the uploaded file
            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                echo "File uploaded successfully as: " . "<a href='files/" . basename($targetFile) . "' target='_blank'>" . basename($targetFile) . "</a>";
            } else {
                echo "Error: There was an error uploading your file.";
            }
        }
    } else {
        echo "Error: " . ($_FILES['file']['error'] ?? 'No file selected.');
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>File Upload</title>
</head>
<body>
    <h1>Upload a File</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <input type="submit" value="Upload">
    </form>
</body>
</html>