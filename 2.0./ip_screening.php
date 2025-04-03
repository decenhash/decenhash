<?php
// Function to get IP address from URL
function getIpFromUrl($url) {
    // Parse URL to extract hostname
    $parsedUrl = parse_url($url);
    
    if (!isset($parsedUrl['host'])) {
        return false;
    }
    
    // Get IP address from hostname
    $ip = gethostbyname($parsedUrl['host']);
    
    // If gethostbyname fails, it returns the hostname
    if ($ip === $parsedUrl['host']) {
        return false;
    }
    
    return $ip;
}

// Directory containing server files
$serversDir = 'servers';
$outputDir = 'servers';

// Ensure output directory exists
if (!file_exists($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Check if servers directory exists
if (!file_exists($serversDir) || !is_dir($serversDir)) {
    die("Error: '$serversDir' directory does not exist!\n");
}

// Get all files in the servers directory
$files = scandir($serversDir);

// Remove . and ..
$files = array_diff($files, array('.', '..'));

// Process each file
foreach ($files as $file) {
    $filePath = "$serversDir/$file";
    
    // Check if it's a file
    if (is_file($filePath)) {
        echo "Processing file: $file\n";
        
        // Read URL from file
        $url = trim(file_get_contents($filePath));
        
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            try {
                // Attempt to get content from URL
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10 // 10 seconds timeout
                    ]
                ]);
                
                $content = @file_get_contents($url, false, $context);
                
                if ($content !== false) {
                    // Get IP from URL
                    $ip = getIpFromUrl($url);
                    
                    if ($ip) {
                        // Create hash from IP
                        $hash = hash('sha256', $ip);
                        
                        // Save content to new file with hash name
                        $newFilePath = "$outputDir/$hash";
                        file_put_contents($newFilePath, $url);
                        
                        echo "  Success: URL processed and saved as $hash\n";
                        
                        // Delete original file
                        unlink($filePath);
                        echo "  Deleted original file: $file\n";
                    } else {
                        echo "  Error: Could not resolve IP for URL: $url\n";
                    }
                } else {
                    echo "  Error: Could not access content at URL: $url\n";
                }
            } catch (Exception $e) {
                echo "  Error processing URL: " . $e->getMessage() . "\n";
            }
        } else {
            echo "  Error: Invalid URL in file: $url\n";
        }
    }
}

echo "Processing complete!\n";
?>