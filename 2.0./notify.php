<?php
header('Content-Type: text/plain');

// Configuration
$serversDir = 'servers';
$connectionTimeout = 5; // seconds

// Create servers directory if it doesn't exist
if (!file_exists($serversDir)) {
    mkdir($serversDir, 0755, true);
}

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

// Function to get all server URLs from the directory
function getServerUrls() {
    global $serversDir;
    
    $urls = [];
    if (file_exists($serversDir)) {
        $files = scandir($serversDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $filePath = $serversDir . '/' . $file;
                $url = file_get_contents($filePath);
                if ($url !== false && isValidUrl($url)) {
                    $urls[] = trim($url);
                }
            }
        }
    }
    return $urls;
}

// Function to add URL to servers directory
function addServerUrl($url) {
    global $serversDir;
    
    $url = trim($url);
    if (!isValidUrl($url)) {
        return false;
    }
    
    // Create SHA256 hash of the URL as filename
    $filename = hash('sha256', $url);
    $filePath = $serversDir . '/' . $filename;
    
    // Check if URL already exists
    $existingUrls = getServerUrls();
    if (!in_array($url, $existingUrls)) {
        file_put_contents($filePath, $url);
        return true;
    }
    return false;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if this is a notification
    if (isset($_GET['url'])) {
        $receivedUrl = urldecode($_GET['url']);
        
        if (isValidUrl($receivedUrl)) {
            // Add to servers directory if not already present
            if (addServerUrl($receivedUrl)) {
                echo "URL received and added: $receivedUrl";
            } else {
                echo "URL already exists: $receivedUrl";
            }
        } else {
            http_response_code(400);
            echo "Invalid URL format";
        }
    } 
    // When no parameters are provided, check all servers
    else {
        echo "Site is online and working";
        
        $urls = getServerUrls();
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