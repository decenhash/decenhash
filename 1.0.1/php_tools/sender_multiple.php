<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>File Upload Form</title>
<style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
    background-color: #f3f3f3;
}

.container {
    max-width: 600px;
    margin: 0 auto;
    background-color: #fff;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

h1 {
    text-align: center;
    margin-bottom: 20px;
}

form {
    margin-bottom: 20px;
}

label {
    font-weight: bold;
}

input[type="file"] {
    margin-bottom: 10px;
}

textarea {
    width: 100%;
    height: 100px;
    margin-bottom: 10px;
}

input[type="submit"] {
    background-color: #007bff;
    color: #fff;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
}

input[type="submit"]:hover {
    background-color: #0056b3;
}
</style>
</head>
<body>

<div class="container">
    <h1>Upload File</h1>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
        <label for="urls">Enter the URLs where the file will be sent (one URL per line):</label><br>
        <textarea id="urls" name="upload_urls" rows="5" cols="50" placeholder="http://example.com/receiver.php
http://example.net/receiver.php"></textarea><br><br>
        <input type="submit" value="Upload File">
    </form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["upload_urls"])) {
        $dir = 'files/'; // Directory containing the files
        $files = scandir($dir); // Get all files from the directory
        $urls = explode("\n", $_POST["upload_urls"]); // URLs entered by user

        foreach ($files as $fileName) {
            $filePath = $dir . $fileName;

            // Skip directories and non-files
            if (!is_file($filePath)) {
                continue;
            }

            $fileData = file_get_contents($filePath);
            $fileType = mime_content_type($filePath);

            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    // Create a new cURL handle for each URL
                    $curl = curl_init();

                    // Set the cURL options
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, [
                        'uploaded_file' => new CURLFile($filePath, $fileType, $fileName)
                    ]);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                    // Execute the cURL request
                    $response = curl_exec($curl);

                    // Check for errors
                    if ($response === false) {
                        echo "Error sending file $fileName to $url: " . curl_error($curl) . "<br>";
                    } else {
                        echo "File $fileName uploaded successfully to $url<br>";
                    }

                    // Close cURL session
                    curl_close($curl);
                }
            }
        }
    } else {
        echo "Please enter at least one URL where the files will be sent.";
    }
}
?>

</div>

</body>
</html>
