<?php
// Create data directory if it doesn't exist
if (!file_exists('data')) {
    mkdir('data', 0755, true);
}

// Create servers directory if it doesn't exist
if (!file_exists('servers')) {
    mkdir('servers', 0755, true);
}

// Read servers from servers.txt
$servers = file('servers.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($servers === false) {
    die("Error reading servers.txt");
}

// Read files from files.txt
$files = file('files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($files === false) {
    die("Error reading files.txt");
}

foreach ($files as $filename) {
    // Get filename without extension
    $file_info = pathinfo($filename);
    $filename_no_ext = $file_info['filename'];
    $expected_hash = $filename_no_ext;
    $extension = isset($file_info['extension']) ? '.' . $file_info['extension'] : '';
    
    // Check if file already exists in data folder
    $target_dir = 'data/' . $expected_hash . '/';
    $target_path = $target_dir . $expected_hash . $extension;
    
    if (file_exists($target_path)) {
        continue; // File already exists, skip to next file
    }
    
    foreach ($servers as $server) {
        // Normalize server URL - ensure it ends with exactly one slash
        $server = rtrim($server, '/') . '/';
        // Construct URL with 'data' segment
        $url = $server . 'data/' . $filename_no_ext . '/' . $filename;
        
        // Download file
        $file_content = @file_get_contents($url);
        if ($file_content === false) {
            continue; // File not found on this server
        }
        
        // Calculate hash
        $actual_hash = hash('sha256', $file_content);
        
        // Check if hash matches
        if ($actual_hash === $expected_hash) {
            // Create directory for this hash if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            // Save the file
            file_put_contents($target_path, $file_content);
            
            // Create hash file in servers directory
            $hash_file = 'servers/' . $expected_hash . '.txt';
            
            // Read existing servers if file exists
            $existing_servers = [];
            if (file_exists($hash_file)) {
                $existing_servers = file($hash_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($existing_servers === false) {
                    $existing_servers = [];
                }
            }
            
            // Add server if not already present
            if (!in_array($server, $existing_servers)) {
                file_put_contents($hash_file, $server . PHP_EOL, FILE_APPEND);
            }
            
            break; // Found valid file, move to next file
        }
    }
}

echo "Processing complete.\n";
?>