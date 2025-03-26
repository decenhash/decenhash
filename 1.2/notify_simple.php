<?php
header('Content-Type: text/plain');

// Configuration
$urlsFile = 'urls.txt';
$connectionTimeout = 5; // seconds

// Function to validate URL format
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Function to check if URL is online
function isUrlOnline($url) {
    global $connectionTimeout;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectionTimeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $connectionTimeout);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode >= 200 && $httpCode < 400);
}

// Function to remove duplicates from URLs file
function removeDuplicates() {
    global $urlsFile;
    
    if (file_exists($urlsFile)) {
        $urls = file($urlsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $uniqueUrls = array_unique($urls);
        file_put_contents($urlsFile, implode(PHP_EOL, $uniqueUrls));
    }
}

// Function to add URL to file
function addUrlToFile($url) {
    global $urlsFile;
    
    file_put_contents($urlsFile, $url . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if this is a notification
    if (isset($_GET['url'])) {
        $receivedUrl = urldecode($_GET['url']);
        
        if (isValidUrl($receivedUrl)) {
            // Add to file if not already present
            $existingUrls = file_exists($urlsFile) ? file($urlsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
            
            if (!in_array($receivedUrl, $existingUrls)) {
                addUrlToFile($receivedUrl);
                echo "URL received and added: $receivedUrl";
            } else {
                echo "URL already exists: $receivedUrl";
            }
        } else {
            http_response_code(400);
            echo "Invalid URL format";
        }
    } 
    // When no parameters are provided, just confirm the site is working
    else {
        echo "Site is online and working";
        
        // Optional: Uncomment below if you want to still perform URL checks on empty requests
        
        removeDuplicates();
        
        $urls = file_exists($urlsFile) ? file($urlsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $onlineUrls = [];
        
        foreach ($urls as $url) {
            if (isUrlOnline($url)) {
                $onlineUrls[] = $url;
                echo "\nOnline: $url";
            } else {
                echo "\nOffline: $url";
            }
        }
        
        // Notify all online URLs about each other
        foreach ($onlineUrls as $senderUrl) {
            foreach ($onlineUrls as $receiverUrl) {
                if ($senderUrl !== $receiverUrl) {
                    $notificationUrl = $receiverUrl . (strpos($receiverUrl, '?') === false ? '?' : '&') . 'url=' . urlencode($senderUrl);
                    
                    $ch = curl_init($notificationUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, $connectionTimeout);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode !== 200) {
                        echo "\nFailed to notify $receiverUrl about $senderUrl (HTTP $httpCode)";
                    }
                }
            }
        }
        
    }
} else {
    http_response_code(405);
    echo "Method Not Allowed";
}
?>