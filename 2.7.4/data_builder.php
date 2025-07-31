<?php

function check_sha256(string $input): string {

    $sha256_regex = '/^[a-f0-9]{64}$/'; // Regex for a 64-character hexadecimal string

    if (preg_match($sha256_regex, $input)) {
        return $input; // Input is a valid SHA256 hash
    } else {
        return hash('sha256', $input); // Input is not a valid SHA256 hash, return its hash
    }
}


    // Configuration
    $uploadDirBase = 'data'; // Base directory for all uploads
    $sourceDir = 'categories'; // Directory containing subdirectories with files to process

    // Get all subdirectories in the test directory
    $subdirectories = array_filter(glob($sourceDir . '/*'), 'is_dir');
    
    if (empty($subdirectories)) {
        die("<p class='error'>No subdirectories found in the 'test' directory.</p>");
    }

    // Process each subdirectory
    foreach ($subdirectories as $subdirectory) {
        $categoryText = basename($subdirectory); // Use subdirectory name as category
        
        $categoryText = strtolower($categoryText);
        
        // Get all files in the subdirectory (excluding directories)
        $files = array_filter(glob($subdirectory . '/*'), 'is_file');
        
        if (empty($files)) {
            echo "<p class='notice'>No files found in subdirectory: " . htmlspecialchars($categoryText) . "</p>";
            continue;
        }

        // Process each file in the subdirectory
        foreach ($files as $filePath) {
            $originalFileName = basename($filePath);
            $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            
            if (strtolower($fileExtension) === 'php') {
                echo "<p class='error'>Skipping PHP file: " . htmlspecialchars($originalFileName) . "</p>";
                continue;
            }

            // Read file content
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                echo "<p class='error'>Error reading file: " . htmlspecialchars($originalFileName) . "</p>";
                continue;
            }

            // Calculate SHA256 hashes
            $fileHash = hash('sha256', $fileContent);
            $categoryHash = check_sha256($categoryText);
            $fileNameWithExtension = $fileHash . '.' . $fileExtension;

            // Construct directory paths
            $fileUploadDir = $uploadDirBase . '/' . $fileHash;
            $categoryDir = $uploadDirBase . '/' . $categoryHash;

            // Create directories if they don't exist
            if (!is_dir($uploadDirBase)) {
                mkdir($uploadDirBase, 0777, true);
            }
            if (!is_dir($fileUploadDir)) {
                mkdir($fileUploadDir, 0777, true);
            }
            if (!is_dir($categoryDir)) {
                mkdir($categoryDir, 0777, true);
            }

            // Save the content
            $destinationFilePath = $fileUploadDir . '/' . $fileNameWithExtension;

            if (file_exists($destinationFilePath)) {
                echo "<p class='notice'>File already exists, skipping: " . htmlspecialchars($originalFileName) . "</p>";
                continue;
            }

            if (!copy($filePath, $destinationFilePath)) {
                echo "<p class='error'>Error saving file: " . htmlspecialchars($originalFileName) . "</p>";
                continue;
            }

            // Content saved successfully

            // Create empty file in category folder
            $categoryFilePath = $categoryDir . '/' . $fileNameWithExtension;
            if (!touch($categoryFilePath)) {
                echo "<p class='error'>Error creating empty file in category folder for: " . htmlspecialchars($originalFileName) . "</p>";
                continue;
            }

            $contentHead = "<link rel='stylesheet' href='../../default.css'><script src='../../default.js'></script><script src='../../ads.js'></script><div id='ads' name='ads' class='ads'></div><div id='default' name='default' class='default'></div>";

            // Handle index.html inside file hash folder
            $indexPathFileFolder = $fileUploadDir . '/index.html';
            if (!file_exists($indexPathFileFolder)) {
                file_put_contents($indexPathFileFolder, $contentHead);
            }

            $linkReply = '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "[ Reply ]" . '</a> ';
            $linkToHash = $linkReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "[ Open ]" . '</a> ';
            $linkToFileFolderIndex = $linkToHash . '<a href="' . htmlspecialchars($fileNameWithExtension) . '">' . htmlspecialchars($originalFileName) . '</a><br>';
            
            $indexContentFileFolder = file_get_contents($indexPathFileFolder);
            if (strpos($indexContentFileFolder, $linkToFileFolderIndex) === false) {
                file_put_contents($indexPathFileFolder, $indexContentFileFolder . $linkToFileFolderIndex);
            }

            // Handle index.html inside category folder
            $indexPathCategoryFolder = $categoryDir . '/index.html';
            if (!file_exists($indexPathCategoryFolder)) {
                file_put_contents($indexPathCategoryFolder, $contentHead);
            }

            $relativePathToFile = '../' . $fileHash . '/' . $fileNameWithExtension;
            $categoryReply = '<a href="../../index.php?reply=' . htmlspecialchars($fileHash) . '">' . "[ Reply ]" . '</a> ';
            $linkToHashCategory = $categoryReply . '<a href="../' . htmlspecialchars($fileHash) . '/index.html">' . "[ Open ]" . '</a> ';
            $linkToCategoryFolderIndex = $linkToHashCategory . '<a href="' . htmlspecialchars($relativePathToFile) . '">' . htmlspecialchars($originalFileName) . '</a><br>';
            
            $indexContentCategoryFolder = file_get_contents($indexPathCategoryFolder);
            if (strpos($indexContentCategoryFolder, $linkToCategoryFolderIndex) === false) {
                file_put_contents($indexPathCategoryFolder, $indexContentCategoryFolder . $linkToCategoryFolderIndex);
            }

            echo "<p class='success'>Processed file: " . htmlspecialchars($originalFileName) . " in category: " . htmlspecialchars($categoryText) . "</p>";
        }
   

    echo "<p class='success'>All files processed successfully!</p>";
}

?>