<?php

// Function to download the file from a URL and save it with its SHA-256 hash and original extension
function downloadFile($url, $saveDir = 'files') {
    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout after 15 seconds

    // Execute the cURL request
    $fileContent = curl_exec($ch);
    
    // Get the HTTP response code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Check if the request was successful (HTTP 200 OK)
    if ($httpCode != 200 || !$fileContent) {
        echo "<p style='color:red;'>Failed to download $url (HTTP Code: $httpCode)</p>";
        return false;
    }

    // Generate the SHA-256 hash of the file content
    $fileHash = hash('sha256', $fileContent);

    // Extract the original file extension
    $fileExtension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    
    // Save the file in the 'files' directory with the hash and original extension
    if (!is_dir($saveDir)) {
        mkdir($saveDir, 0755, true); // Create directory if it doesn't exist
    }

    $filePath = $saveDir . DIRECTORY_SEPARATOR . $fileHash . '.' . $fileExtension;

    // Check if the file already exists
    if (file_exists($filePath)) {
        echo "<p style='color:orange;'>File already exists: $filePath. Skipping download.</p>";
        return true; // Return true as it "successfully" skipped downloading
    }

    // Save the content to the file
    file_put_contents($filePath, $fileContent);

    echo "<p style='color:green;'>Downloaded and saved file from $url as $fileHash.$fileExtension</p>";

    return true;
}

// Function to extract all links from a given webpage
function extractLinks($pageUrl) {
    // Initialize cURL
    $ch = curl_init($pageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout after 10 seconds

    // Execute the cURL request to get the HTML content of the page
    $htmlContent = curl_exec($ch);
    curl_close($ch);

    if (!$htmlContent) {
        echo "<p style='color:red;'>Failed to retrieve content from $pageUrl</p>";
        return [];
    }

    // Use a DOM parser to find all the links
    libxml_use_internal_errors(true);
    
    $dom = new DOMDocument;
    
    if (@$dom->loadHTML($htmlContent)) {
        libxml_clear_errors();
        
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');
            if (!empty($href)) {
                $links[] = $href;
            }
        }
        
        return $links;
        
    } else {
        echo "<p style='color:red;'>Failed to parse HTML from $pageUrl</p>";
        return [];
    }
}

// Function to download all links found on the page
function downloadLinksFromPage($pageUrl, $saveDir = 'files') {
    echo "<h2>Extracting and downloading links from " . htmlspecialchars($pageUrl) . "</h2>";

    // Get all links from the webpage
    $links = extractLinks($pageUrl);

    if (empty($links)) {
        echo "<p>No links found on the page.</p>";
        return;
    }

    // Extract base URL without script name for constructing full URLs
    $baseUrl = rtrim(dirname($pageUrl), '/') . '/';

    foreach ($links as $link) {
        // Ensure the link is a full URL
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            downloadFile($link, $saveDir);
        } else {
            // If it's a relative URL, construct the full URL using base URL
            $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($link, '/');
            downloadFile($fullUrl, $saveDir);
        }
    }
}

// Example: Use the function to download all files linked from a page
$pageUrl = filter_var($_GET['url'], FILTER_VALIDATE_URL);
if (!$pageUrl) {
   die("<p style='color:red;'>Invalid URL provided.</p>");
}
downloadLinksFromPage($pageUrl);

?>