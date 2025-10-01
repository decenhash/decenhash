<?php
// Configuration
$salt = "y0urS3cr3tS@ltH3r3"; // Change this to your preferred salt
$dataDir = "data";
$ipLimitDir = "ip_limit";

if (!is_dir($ipLimitDir)) {
    mkdir($ipLimitDir, 0777, true);
}

// Get user IP
$userIP = $_SERVER['REMOTE_ADDR'];

// Generate SHA256 hash (IP + salt)
$hash = hash('sha256', $userIP . $salt);

// Check if IP already exists in ip_limit
$ipFilePath = $ipLimitDir . DIRECTORY_SEPARATOR . $hash . '.txt';

if (file_exists($ipFilePath)) {
    //echo "Your already reach the limit.";
} else {

// If IP is new, proceed with file creation
// (1) Save hash in ip_limit (only non-empty file)
$fileHandle = fopen($ipFilePath, 'x');
if ($fileHandle) {
    fwrite($fileHandle, $hash);
    fclose($fileHandle);
} else {
    //die("Error: Could not save IP hash.");
}

// (2) Save empty file in data/[reply] (if reply provided)
if (isset($_GET['hash'])) {
    $subfolder = $_GET['hash']; // Sanitize

    $targetDir = $dataDir . DIRECTORY_SEPARATOR . $subfolder;

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}  
    if (is_dir($targetDir)) {
        $filePath = $targetDir . DIRECTORY_SEPARATOR . $hash . '.txt';
        if (!file_exists($filePath)) {
            $fileHandle = fopen($filePath, 'x'); // Creates empty file
            if ($fileHandle) fclose($fileHandle);
        }
    }
}

//echo "Like completed successfully.";
}
?>