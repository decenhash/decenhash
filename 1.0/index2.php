<?php

function check_sha256(string $input): string {

    $sha256_regex = '/^[a-f0-9]{64}$/'; // Regex for a 64-character hexadecimal string

    if (preg_match($sha256_regex, $input)) {
        return $input; // Input is a valid SHA256 hash
    } else {
        return hash('sha256', $input); // Input is not a valid SHA256 hash, return its hash
    }
}

if(ISSET($_GET['reply'])){$reply = $_GET['reply'];}else{$reply="";}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configuration - Customize these paths and settings as needed
    $uploadDirBase = 'data'; // Base directory for all uploads

    // Check if category was provided
    if (isset($_POST['category']) && !empty($_POST['category'])) {

        $categoryText = $_POST['category'];
        $fileContent = null;
        $originalFileName = null;
        $fileExtension = 'txt'; // Default extension for text content
        $isTextContent = false; // Flag to track if content is from text area

        // Check if a file was uploaded
        if (isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['uploaded_file'];
            $fileContent = file_get_contents($uploadedFile['tmp_name']);
            $originalFileName = $uploadedFile['name'];
            $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            $isTextContent = false;

        } elseif (isset($_POST['text_content']) && !empty($_POST['text_content'])) {
            // If no file uploaded, check for text content
            $fileContent = $_POST['text_content'];
            $date = date("Y, m, d H:i:s"); // Just for naming purposes in index.html
            
            $originalFileName = hash('sha256', $fileContent);

            $fileContentLen = strlen($fileContent);

            if ($fileContentLen > 50) {

                $originalFileName = htmlspecialchars(substr($fileContent, 0, 50)) . " ($date)"; 

            } else {

                $originalFileName = htmlspecialchars(substr($fileContent, 0, 50)) . " ($date)";

            }

            $isTextContent = true;
        }

        if ($fileContent !== null) { // Proceed if we have either file content or text content

            if (strtolower($fileExtension) === 'php') {

                die('Error: PHP files are not allowed!');
   
            }


            if ($_POST['category'] == $_POST['text_content']){die ("Error: Category can't be the same of text contents.");}

            // Calculate SHA256 hashes
            $fileHash = hash('sha256', $fileContent);
            //$categoryHash = hash('sha256', $categoryText);

            $categoryHash = check_sha256($categoryText);

            // Determine file extension (already done above, default is 'txt' for text content)
            $fileNameWithExtension = $fileHash . '.' . $fileExtension; // Hash + extension as filename

            // Construct directory paths
            $fileUploadDir = $uploadDirBase . '/' . $fileHash; // Folder name is file hash
            $categoryDir = $uploadDirBase . '/' . $categoryHash; // Folder name is category hash

            // Create directories if they don't exist
            if (!is_dir($uploadDirBase)) {
                mkdir($uploadDirBase, 0777, true); // Create base upload directory if it doesn't exist
            }
            if (!is_dir($fileUploadDir)) {
                mkdir($fileUploadDir, 0777, true); // Create file hash folder
            }
            if (!is_dir($categoryDir)) {
                mkdir($categoryDir, 0777, true); // Create category hash folder
            }

            // Save the  content (either uploaded file or text content)


            $destinationFilePath = $fileUploadDir . '/' . $fileNameWithExtension;

            if (file_exists($destinationFilePath)) {
                die('Error: File already exists!');
            }


            if ($isTextContent) {
                $saveResult = file_put_contents($destinationFilePath, $fileContent); // Save text content
                if ($saveResult !== false) {
                    $fileSaved = true;
                } else {
                    $fileSaved = false;
                }

            } else {
                $fileSaved = move_uploaded_file($uploadedFile['tmp_name'], $destinationFilePath); // Save uploaded file
            }


            if ($fileSaved) {
                // Content saved successfully

                // Create empty file in category folder with hash + extension name
                $categoryFilePath = $categoryDir . '/' . $fileNameWithExtension; // Empty file name is file hash + extension inside category folder
                if (touch($categoryFilePath)) {
                    // Empty file created successfully
                    
                    $contentHead = "<link rel='stylesheet' href='../../default.css'><script src='../../default.js'></script><script src='../../ads.js'></script><div id='ads' name='ads' class='ads'></div><div id='default' name='default' class='default'></div>";
 
                    // Handle index.html inside file hash folder (for content links)
                    $indexPathFileFolder = $fileUploadDir . '/index.html';
                    if (!file_exists($indexPathFileFolder)) {
                        //touch($indexPathFileFolder); // Create index.html if it doesn't exist

                        $file = fopen($indexPathFileFolder, 'a');
                        fwrite($file, $contentHead);
                        fclose($file);
                    }
  
                    $linkReply = '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "[ Reply ]" . '</a> ';
                    $linkToHash = $linkReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "[ Open ]" . '</a> ';
                    $linkToFileFolderIndex = $linkToHash . '<a href="' . htmlspecialchars($fileNameWithExtension) . '">' . htmlspecialchars($originalFileName) . '</a><br>'; //Use original file name or 'text_content.txt' for link text
                    $indexContentFileFolder = file_get_contents($indexPathFileFolder);
                    if (strpos($indexContentFileFolder, $linkToFileFolderIndex) === false) {
                        file_put_contents($indexPathFileFolder, $indexContentFileFolder . $linkToFileFolderIndex); // Append link to index.html
                    }

                    // Handle index.html inside category folder (for link to original content)
                    $indexPathCategoryFolder = $categoryDir . '/index.html';
                    if (!file_exists($indexPathCategoryFolder)) {
                        //touch($indexPathCategoryFolder); // Create index.html if it doesn't exist    
                         
                        $file = fopen($indexPathCategoryFolder, 'a');
                        fwrite($file, $contentHead);
                        fclose($file);                 
                    }

                    // Construct relative path to the content in the content hash folder
                    $relativePathToFile = '../' . $fileHash . '/' . $fileNameWithExtension;

                    $categoryReply = '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "[ Reply ]" . '</a> ';
                    $linkToHashCategory = $categoryReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "[ Open ]" . '</a> ';
                    $linkToCategoryFolderIndex = $linkToHashCategory . '<a href="' . htmlspecialchars($relativePathToFile) . '">' . htmlspecialchars($originalFileName) . '</a><br>'; //Use original file name or 'text_content.txt' for link text
                    $indexContentCategoryFolder = file_get_contents($indexPathCategoryFolder);
                    if (strpos($indexContentCategoryFolder, $linkToCategoryFolderIndex) === false) {
                        file_put_contents($indexPathCategoryFolder, $indexContentCategoryFolder . $linkToCategoryFolderIndex); // Append link to index.html
                    }


                    echo "<p class='success'>Content processed successfully!</p>";
                    echo "<p>Content saved in: <pre><a href='" . htmlspecialchars($destinationFilePath) . "'>$destinationFilePath</a></pre></p>";
                    

                } else {
                    echo "<p class='error'>Error creating empty file in category folder.</p>";
                }

            } else {
                echo "<p class='error'>Error saving content.</p>";
            }

        } else {
            echo "<p class='error'>Please select a file or enter text content and provide a category.</p>";
        }


    } else {
        echo "<p class='error'>Please enter a category.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>File/Text Upload with Category</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        h2 {
            color: #333;
            text-align: center;
        }

        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 80%;
            max-width: 600px;
            margin: 20px auto;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input[type="file"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box; /* Important to include padding in width */
        }

        textarea {
            font-family: Arial, sans-serif; /* Ensure consistent font in textarea */
        }

        input[type="submit"] {
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        input[type="submit"]:hover {
            background-color: #4cae4c;
        }

        p.success {
            color: green;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        p.error {
            color: red;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        pre {
            background-color: #eee;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow-x: auto; /* For long paths */
        }

input[readonly],
textarea[readonly] {
  background-color: #eee; /* Example: Light gray background */
  border: 1px solid #ccc; /* Example: Slightly darker border */
  color: #777; /* Example: Darker gray text */
  cursor: default; /* Example: Change cursor to default arrow */
}

 
    </style>
</head>
<body>

<h2>Upload File</h2>

<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); if(ISSET($_GET['reply'])){echo "?reply=" . $_GET['reply'];} ?>" method="post" enctype="multipart/form-data">
    <label for="uploaded_file">Select File:</label>
    <input type="file" name="uploaded_file" id="uploaded_file"><br><br>

    <label for="text_content">Or enter text content:</label><br>
    <textarea name="text_content" id="text_content" rows="5" cols="40"></textarea><br><br>

    <label for="category">Category:</label>
    <input type="text" name="category" id="category" value="<?php if(ISSET($_GET['reply'])){echo $_GET['reply'];} ?>" required <?php if(ISSET($_GET['reply'])){echo "readonly";} ?> ><br><br>

    <input type="submit" value="Upload/Save Content">
</form>

<div align="center"><a href="index.html">Search</a></div>
</body>
</html>