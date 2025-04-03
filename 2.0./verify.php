<?php
/**
 * HTTP Server File Hash Validator
 * 
 * This program checks multiple HTTP servers for the existence of a file,
 * verifies file hashes across servers, and determines which servers have the most consistent hash.
 */


/**
 * List all files in the 'urls' directory and return their contents
 * 
 * @return array An associative array with filenames as keys and file contents as values
 */
function listUrlFiles() {
    // Path to the urls directory
    $urlsDirectory = 'urls/';
    
    // Initialize the result array
    $result = [];
    
    // Check if directory exists
    if (!file_exists($urlsDirectory)) {
        return $result; // Return empty array if directory doesn't exist
    }
    
    // Get all files in the directory
    $files = scandir($urlsDirectory);
    
    // Process each file
    foreach ($files as $file) {
        // Skip . and .. directory entries
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        // Build the full file path
        $filePath = $urlsDirectory . $file;
        
        // Check if it's a file (not a directory)
        if (is_file($filePath)) {
            // Read the file content
            $content = file_get_contents($filePath);
            
            // Add to result array using the filename as key
            $result[$file] = $content;
        }
    }
    
    return $result;
}

$servers = listUrlFiles();

// Process form submission
$results = [];
$majorityHash = '';
$majorityServers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filename'])) {
    $filename = trim($_POST['filename']);
    
    if (empty($filename)) {
        $error = "Please enter a valid filename.";
    } else {
        // Extract subdirectory from filename (part before the first dot)
        $parts = explode('.', $filename);
        $subdirectory = $parts[0];
        
        // Connect to each server and check file hash
        $hashes = [];
        $serverResults = [];
        
        foreach ($servers as $serverIndex => $serverUrl) {
            $serverId = 'server' . ($serverIndex + 1);
            $serverName = parse_url($serverUrl, PHP_URL_HOST);
            
            $serverResult = [
                'name' => $serverName,
                'url' => $serverUrl,
                'status' => 'Error',
                'message' => 'Failed to connect',
                'hash' => null
            ];
            
            // Construct the file URL
            $fileUrl = rtrim($serverUrl, '/') . "/data/{$subdirectory}/{$filename}";
            
            // Attempt to retrieve the file from the server
            $fileContent = fetchFileFromServer($fileUrl);
            
            if ($fileContent !== false) {
                // Calculate file hash
                $hash = hash('sha256', $fileContent);
                
                $serverResult['status'] = 'Success';
                $serverResult['message'] = 'File found';
                $serverResult['hash'] = $hash;
                
                // Count hash occurrences
                if (!isset($hashes[$hash])) {
                    $hashes[$hash] = [];
                }
                $hashes[$hash][] = $serverId;
            } else {
                $serverResult['status'] = 'Error';
                $serverResult['message'] = 'File not found or unable to access';
            }
            
            $serverResults[$serverId] = $serverResult;
        }
        
        // Find the hash with the majority of servers
        $maxCount = 0;
        $majorityHash = '';
        $majorityServers = [];
        
        foreach ($hashes as $hash => $serverIds) {
            if (count($serverIds) > $maxCount) {
                $maxCount = count($serverIds);
                $majorityHash = $hash;
                $majorityServers = $serverIds;
            }
        }
        
        $results = $serverResults;
    }
}

/**
 * Fetch file from HTTP/HTTPS server
 * Returns file content on success, false on failure
 */
function fetchFileFromServer($url) {
    // Initialize cURL session
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 seconds timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing - should be true in production
    
    // Execute cURL session
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Close cURL session
    curl_close($ch);
    
    // Return file content if successful, otherwise false
    if ($httpCode == 200 && !empty($response)) {
        return $response;
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Hash Validator</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f7f9fc;
        }
        
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        
        input[type="submit"] {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        input[type="submit"]:hover {
            background-color: #2980b9;
        }
        
        .help-btn {
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: #3498db;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            cursor: pointer;
            margin-left: 8px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .tooltip {
            visibility: hidden;
            position: absolute;
            background-color: #2c3e50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            width: 250px;
            z-index: 1;
            opacity: 0;
            transition: opacity 0.3s;
            left: 0;
            top: 100%;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .help-btn:hover + .tooltip {
            visibility: visible;
            opacity: 1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .status-success {
            color: #27ae60;
            font-weight: bold;
        }
        
        .status-error {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .majority-server {
            background-color: #d4edda;
        }
        
        .summary-box {
            background-color: #e8f4fd;
            border: 1px solid #3498db;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .error-message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .hash-display {
            font-family: monospace;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <h2>HTTP Server File Hash Validator</h2>
    
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
        <div class="form-group">
            <label for="filename">Enter Filename:</label>
            <span class="help-btn">?</span>
            <div class="tooltip">Enter the filename to check across servers. The part before the first dot (.) will be used as the subdirectory path in the data folder.</div>
            <input type="text" name="filename" id="filename" placeholder="example.txt" required>
        </div>

        <div class="form-group">
            <input type="submit" value="Check File">
        </div>
    </form>
    
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($results)): ?>
        <h3>Results</h3>
        
        <?php if (!empty($majorityHash)): ?>
            <div class="summary-box">
                <p><strong>Majority Hash (SHA-256):</strong> <span class="hash-display"><?php echo $majorityHash; ?></span></p>
                <p><strong>Number of Servers with Matching Hash:</strong> <?php echo count($majorityServers); ?> out of <?php echo count($servers); ?></p>
            </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Server</th>
                    <th>Status</th>
                    <th>Message</th>
                    <th>Hash (SHA-256)</th>
                    <th>Majority?</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $serverId => $result): ?>
                    <tr class="<?php echo in_array($serverId, $majorityServers) ? 'majority-server' : ''; ?>">
                        <td><?php echo htmlspecialchars($result['name']); ?></td>
                        <td class="status-<?php echo strtolower($result['status']); ?>">
                            <?php echo htmlspecialchars($result['status']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($result['message']); ?></td>
                        <td class="hash-display"><?php echo $result['hash'] ? htmlspecialchars($result['hash']) : 'N/A'; ?></td>
                        <td><?php echo in_array($serverId, $majorityServers) ? '?' : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>