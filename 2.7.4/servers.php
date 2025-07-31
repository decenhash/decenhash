<?php
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
        }
    }
}

echo "Processing complete.\n";
?>