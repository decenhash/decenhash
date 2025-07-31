<?php
// Define directories
$dataCountDir = 'data_count';
$filesDir = 'files';

// Check if directories exist
if (!is_dir($dataCountDir)) {
    die("Error: Directory '$dataCountDir' does not exist.\n");
}

if (!is_dir($filesDir)) {
    die("Error: Directory '$filesDir' does not exist.\n");
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

// Display the results
if (empty($results)) {
    echo "No matching files found between directories.\n";
} else {
    echo "Results:\n";
    foreach ($results as $name => $total) {
        echo "- $name: $total\n";
    }
}
?>