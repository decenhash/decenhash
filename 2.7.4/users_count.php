<?php
// Define directories
$dataCountDir = 'data_count';
$filesDir = 'files';
$usersCountDir = 'users_count';

// Check if directories exist
if (!is_dir($dataCountDir)) {
    die("Error: Directory '$dataCountDir' does not exist.\n");
}

if (!is_dir($filesDir)) {
    die("Error: Directory '$filesDir' does not exist.\n");
}

// Create users_count directory if it doesn't exist
if (!is_dir($usersCountDir)) {
    if (!mkdir($usersCountDir, 0755, true)) {
        die("Error: Failed to create directory '$usersCountDir'\n");
    }
}

// Get all files from data_count directory
$dataCountFiles = array_diff(scandir($dataCountDir), array('.', '..'));
$results = array();

foreach ($dataCountFiles as $filename) {
    $dataCountPath = $dataCountDir . '/' . $filename;
    $filesPath = $filesDir . '/' . $filename;
    
    // Check if matching file exists in files directory
    if (file_exists($filesPath)) {
        // Get value from data_count file
        $countValue = intval(file_get_contents($dataCountPath));
        
        // Get content from files directory file
        $fileContent = trim(file_get_contents($filesPath));
        
        // Sum the values
        if (!isset($results[$fileContent])) {
            $results[$fileContent] = 0;
        }
        $results[$fileContent] += $countValue;
    }
}

// Save results to users_count directory using fopen/fwrite
foreach ($results as $name => $total) {
    // Create hash filename
    $hash = $name;
    $outputPath = $usersCountDir . '/' . $hash . '.txt';
    
    // Open file for writing
    $fileHandle = fopen($outputPath, 'w');
    if ($fileHandle === false) {
        echo "Error: Could not open file $outputPath for writing\n";
        continue;
    }
    
    // Write the total count to the file
    $bytesWritten = fwrite($fileHandle, (string)$total);
    if ($bytesWritten === false) {
        echo "Error: Failed to write to file $outputPath\n";
    } else {
        //echo "Saved: $hash.txt ($name: $total)\n";
    }
    
    // Close the file handle
    fclose($fileHandle);
}

//echo "Processing complete. Results saved to '$usersCountDir' directory.\n";
?>