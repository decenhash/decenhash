<?php
// Define the paths
$dataDir = 'data';
$countDir = 'data_count';

// Create the data_count directory if it doesn't exist
if (!file_exists($countDir)) {
    mkdir($countDir, 0755, true);
}

// Get all subdirectories of the data folder
$subdirectories = array_filter(glob($dataDir . '/*'), 'is_dir');

foreach ($subdirectories as $subdir) {
    // Get the folder name (last part of the path)
    $folderName = basename($subdir);
    
    // Define the output file path
    $outputFile = $countDir . '/' . $folderName . '.txt';
    
    // Skip if the output file already exists
    if (file_exists($outputFile)) {
        //echo "Skipping $folderName - output file already exists\n";
        continue;
    }
    
    // Count files more accurately
    $fileCount = 0;
    $dirHandle = opendir($subdir);
    if ($dirHandle) {
        while (false !== ($file = readdir($dirHandle))) {
            if ($file != "." && $file != ".." && is_file($subdir . '/' . $file)) {
                $fileCount++;
            }
        }
        closedir($dirHandle);
    }
    
    // Write the count to the output file
    $fileHandle = fopen($outputFile, 'w');
    if ($fileHandle) {
        fwrite($fileHandle, $fileCount);
        fclose($fileHandle);
        //echo "Processed folder: $folderName - found $fileCount files<br>";
    } else {
        echo "Error: Could not create file for $folderName\n";
    }
}

echo "Processing complete.\n";
?>