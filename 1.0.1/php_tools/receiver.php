<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>File Upload Receiver</title>
</head>
<body>
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Specify the directory where files will be saved
    $uploadDirectory = "files/";

    // Create the directory if it doesn't exist
    if (!file_exists($uploadDirectory)) {
        mkdir($uploadDirectory, 0777, true);
    }

    // Check if files are uploaded
    if (isset($_FILES["uploaded_file"])) {
        $file = $_FILES["uploaded_file"];
        $fileName = $file["name"];

        // Check file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($fileExtension === 'php') {
            echo "<p>PHP files are not allowed.</p>";
            exit; // Exit script if PHP file is uploaded
        }

        // Generate unique filename based on SHA256 hash
        $fileHash = hash_file('sha256', $file["tmp_name"]);
        $newFileName = $fileHash . '.' . $fileExtension;
        $filePath = $uploadDirectory . $newFileName;

        // Check if file already exists
        if (file_exists($filePath)) {
            echo "<p>File already exists. Cannot overwrite existing files.</p>";
        } else {
            // Move uploaded file to the specified directory
            if (move_uploaded_file($file["tmp_name"], $filePath)) {
                echo "<p>File uploaded successfully. File path: $filePath</p>";
            } else {
                echo "<p>Failed to upload file.</p>";
            }
        }
    } else {
        echo "<p>No files uploaded.</p>";
    }
}
?>
</body>
</html>
