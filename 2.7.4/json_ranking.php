<?php
function analyzeJsonFiles($folderPath) {
    // Initialize an array to count user occurrences
    $userCounts = array();
    
    // Check if folder exists
    if (!file_exists($folderPath)) {
        echo "Error: Folder '$folderPath' does not exist.\n";
        return;
    }
    
    // Get all JSON files in the folder
    $jsonFiles = array();
    foreach (scandir($folderPath) as $filename) {
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'json') {
            $jsonFiles[] = $filename;
        }
    }
    
    if (empty($jsonFiles)) {
        echo "No JSON files found in '$folderPath'.\n";
        return;
    }
    
    echo "Found " . count($jsonFiles) . " JSON files in '$folderPath':\n";
    foreach ($jsonFiles as $file) {
        //echo " - $file\n";
    }
    
    // Process each JSON file
    foreach ($jsonFiles as $filename) {
        $filepath = $folderPath . DIRECTORY_SEPARATOR . $filename;
        try {
            $content = file_get_contents($filepath);
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON: " . json_last_error_msg());
            }
            
            // Count users in this file
            foreach ($data as $entry) {
                $user = $entry['user'] ?? '';
                if ($user !== '') {  // Only count non-empty users
                    if (!isset($userCounts[$user])) {
                        $userCounts[$user] = 0;
                    }
                    $userCounts[$user]++;
                }
            }
        } catch (Exception $e) {
            echo "Warning: Could not process $filename - " . $e->getMessage() . "\n";
        }
    }
    
    // Sort users by count in descending order
    arsort($userCounts);
    
    echo "\nUser occurrence counts (descending order):\n";
    if (empty($userCounts)) {
        echo "No users found in any JSON files.\n";
    } else {
        foreach ($userCounts as $user => $count) {
            echo "<br>$user: $count";
        }
    }
}

// Main execution
$folderPath = "json_search";
analyzeJsonFiles($folderPath);
?>