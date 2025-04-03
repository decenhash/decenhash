<?php

class FileHashChecker {
    private const DATA_DIR = 'data';
    private const SERVERS_DIR = 'servers';
    private const RESULTS_FILE = 'results.html';
    private const TIMEOUT_MS = 5000;
    private const BUFFER_SIZE = 8192;

    public static function main() {
        try {
            // Set content type to HTML if running in browser
            if (php_sapi_name() !== 'cli') {
                header('Content-Type: text/html; charset=utf-8');
            }
            
            // Get server URLs from files in servers directory
            $servers = self::getServerUrlsFromDirectory();
            
            if (empty($servers)) {
                echo "No server URLs found in " . self::SERVERS_DIR . " directory.<br>";
                return;
            }
            
            echo "Found " . count($servers) . " servers to check:<br>";
            echo "<ul>";
            foreach ($servers as $server) {
                echo "<li>$server</li>";
            }
            echo "</ul>";
            
            // Get all files in data directory and subdirectories
            $localFiles = self::getAllFilesInDataDirectory();
            
            if (empty($localFiles)) {
                echo "No files found in " . self::DATA_DIR . " directory.<br>";
                return;
            }
            
            // Check each server for matching files with valid hashes
            $serverMatches = [];
            foreach ($servers as $server) {
                $matchingFiles = self::checkServerForValidFiles($server, $localFiles);
                // Add array even for servers with no matching files
                $serverMatches[$server] = $matchingFiles;
            }
            
            // Display results in an HTML table
            self::displayResultsTable($serverMatches, $localFiles);
            
            // Save results
            self::saveResults($serverMatches, $localFiles);
            
            echo "<p>Results saved to " . self::RESULTS_FILE . "</p>";
        } catch (Exception $e) {
            echo "<div style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</div>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    }
    
    private static function getServerUrlsFromDirectory() {
        $servers = [];
        $serversPath = self::SERVERS_DIR;
        
        if (!file_exists($serversPath)) {
            throw new Exception(self::SERVERS_DIR . " directory does not exist");
        }
        
        $files = glob("$serversPath/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Using substr instead of str_starts_with for PHP < 8.0 compatibility
                    if (!empty($line) && substr($line, 0, 1) !== '#') {
                        $servers[] = $line;
                    }
                }
            }
        }
        
        return $servers;
    }
    
    private static function getAllFilesInDataDirectory() {
        $files = [];
        $dataPath = self::DATA_DIR;
        
        if (!file_exists($dataPath)) {
            throw new Exception(self::DATA_DIR . " directory does not exist");
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dataPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($dataPath) + 1));
                $files[] = $relativePath;
            }
        }
        
        return $files;
    }
    
    private static function checkServerForValidFiles($server, $files) {
        $validFiles = [];
        
        echo "<p><strong>Checking server:</strong> $server</p>";
        echo "<div style='margin-left: 20px;'>";
        
        foreach ($files as $file) {
            // Using substr instead of str_ends_with for PHP < 8.0 compatibility
            $lastChar = substr($server, -1);
            $fileUrl = $server . ($lastChar === '/' ? '' : '/') . $file;
            try {
                // Get the expected hash from filename (assuming filename is the hash)
                $expectedHash = self::getHashFromFilename($file);
                
                if ($expectedHash === null) {
                    echo "<span style='color: orange;'>Skipping file $file - filename doesn't appear to be a SHA-256 hash</span><br>";
                    continue;
                }
                
                // Download file and calculate hash
                $actualHash = self::calculateRemoteFileHash($fileUrl);
                
                if ($actualHash !== null && strtolower($actualHash) === strtolower($expectedHash)) {
                    $validFiles[] = $file;
                    echo "<span style='color: green;'>? Valid file found: $file</span><br>";
                } else {
                    echo "<span style='color: red;'>? Invalid hash for file: $file (Expected: $expectedHash, Actual: $actualHash)</span><br>";
                }
            } catch (Exception $e) {
                echo "<span style='color: red;'>Error checking file $fileUrl: " . $e->getMessage() . "</span><br>";
            }
        }
        
        echo "</div>";
        return $validFiles;
    }
    
    private static function getHashFromFilename($filePath) {
        // Extract filename without extension
        $filename = basename($filePath);
        $dotIndex = strrpos($filename, '.');
        if ($dotIndex !== false) {
            $filename = substr($filename, 0, $dotIndex);
        }
        
        // Check if filename looks like a SHA-256 hash (64 hex characters)
        if (preg_match('/^[a-fA-F0-9]{64}$/', $filename)) {
            return strtolower($filename);
        }
        return null;
    }
    
    private static function calculateRemoteFileHash($fileUrl) {
        $context = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT_MS / 1000,
            ]
        ]);
        
        try {
            $handle = fopen($fileUrl, 'rb', false, $context);
            if ($handle === false) {
                throw new Exception("Could not open URL: $fileUrl");
            }
            
            $context = hash_init('sha256');
            $buffer = self::BUFFER_SIZE;
            
            while (!feof($handle)) {
                $data = fread($handle, $buffer);
                if ($data === false) {
                    throw new Exception("Error reading from URL: $fileUrl");
                }
                hash_update($context, $data);
            }
            
            fclose($handle);
            return hash_final($context);
        } catch (Exception $e) {
            throw new Exception("Failed to calculate hash: " . $e->getMessage(), 0, $e);
        }
    }
    
    private static function displayResultsTable($serverMatches, $allLocalFiles) {
        $totalFiles = count($allLocalFiles);
        
        echo "<h2>Server Results Summary</h2>";
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<thead style='background-color: #f2f2f2;'>";
        echo "<tr>";
        echo "<th>Server URL</th>";
        echo "<th style='width: 100px;'>Valid Files</th>";
        echo "<th style='width: 150px;'>Completion</th>";
        echo "<th style='width: 120px;'>Status</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($serverMatches as $server => $validFiles) {
            $fileCount = count($validFiles);
            $percentage = $totalFiles > 0 ? round(($fileCount / $totalFiles) * 100, 1) : 0;
            $status = $fileCount === $totalFiles ? "COMPLETE" : "PARTIAL";
            
            // Set row color based on status
            $rowColor = $fileCount === $totalFiles ? "#e6ffe6" : ($fileCount > 0 ? "#fff9e6" : "#ffe6e6");
            
            echo "<tr style='background-color: $rowColor;'>";
            echo "<td><a href='$server' target='_blank'>" . htmlspecialchars($server) . "</a></td>";
            echo "<td style='text-align: center;'>$fileCount/$totalFiles</td>";
            echo "<td>";
            
            // Add progress bar
            echo "<div style='width: 100%; background-color: #ddd; border-radius: 5px;'>";
            echo "<div style='height: 20px; width: $percentage%; background-color: " . 
                ($percentage == 100 ? "#4CAF50" : ($percentage > 50 ? "#FFA500" : "#FF6347")) . 
                "; border-radius: 5px; text-align: center; color: white;'>$percentage%</div>";
            echo "</div>";
            
            echo "</td>";
            echo "<td style='text-align: center; font-weight: bold; color: " . 
                ($status === "COMPLETE" ? "green" : "orange") . ";'>$status</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    }
    
    private static function saveResults($serverMatches, $allLocalFiles) {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Hash Validation Results</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
            text-align: left;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .progress-bar {
            width: 100%;
            background-color: #ddd;
            border-radius: 5px;
        }
        .progress {
            height: 20px;
            border-radius: 5px;
            text-align: center;
            color: white;
        }
        .complete {
            color: green;
            font-weight: bold;
        }
        .partial {
            color: orange;
            font-weight: bold;
        }
        .file-list {
            margin-left: 20px;
        }
        .toggle-button {
            cursor: pointer;
            color: blue;
            text-decoration: underline;
        }
        .details {
            display: none;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .server-details {
            margin-bottom: 20px;
        }
    </style>
    <script>
        function toggleSection(id) {
            var section = document.getElementById(id);
            if (section.style.display === "none" || section.style.display === "") {
                section.style.display = "block";
            } else {
                section.style.display = "none";
            }
        }
    </script>
</head>
<body>
    <h1>File Hash Validation Results</h1>
    <p>Generated: ' . date('Y-m-d H:i:s') . '</p>
    
    <h2>Server Results Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Server URL</th>
                <th style="width: 100px;">Valid Files</th>
                <th style="width: 150px;">Completion</th>
                <th style="width: 120px;">Status</th>
            </tr>
        </thead>
        <tbody>';

        $totalFiles = count($allLocalFiles);
        foreach ($serverMatches as $server => $validFiles) {
            $fileCount = count($validFiles);
            $percentage = $totalFiles > 0 ? round(($fileCount / $totalFiles) * 100, 1) : 0;
            $status = $fileCount === $totalFiles ? "COMPLETE" : "PARTIAL";
            $color = $percentage == 100 ? "#4CAF50" : ($percentage > 50 ? "#FFA500" : "#FF6347");
            
            $html .= '
            <tr>
                <td><a href="' . htmlspecialchars($server) . '" target="_blank">' . htmlspecialchars($server) . '</a></td>
                <td style="text-align: center;">' . $fileCount . '/' . $totalFiles . '</td>
                <td>
                    <div class="progress-bar">
                        <div class="progress" style="width: ' . $percentage . '%; background-color: ' . $color . ';">' . $percentage . '%</div>
                    </div>
                </td>
                <td style="text-align: center;" class="' . strtolower($status) . '">' . $status . '</td>
            </tr>';
        }
        
        $html .= '
        </tbody>
    </table>
    
    <h2>Local Files Checked (' . count($allLocalFiles) . ')</h2>
    <div class="toggle-button" onclick="toggleSection(\'file-list\')">Show/Hide File List</div>
    <div id="file-list" class="details">
        <ul class="file-list">';
        
        foreach ($allLocalFiles as $file) {
            $html .= '<li>' . htmlspecialchars($file) . '</li>';
        }
        
        $html .= '
        </ul>
    </div>
    
    <h2>Detailed Server Report</h2>';
        
        foreach ($serverMatches as $server => $validFiles) {
            $serverId = 'server-' . md5($server);
            $fileCount = count($validFiles);
            $percentage = $totalFiles > 0 ? round(($fileCount / $totalFiles) * 100, 1) : 0;
            
            $html .= '
    <div class="server-details">
        <div><strong>Server:</strong> ' . htmlspecialchars($server) . ' (' . $fileCount . '/' . $totalFiles . ' files, ' . $percentage . '%)</div>
        <div class="toggle-button" onclick="toggleSection(\'' . $serverId . '\')">Show/Hide Details</div>
        <div id="' . $serverId . '" class="details">
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th style="width: 100px;">Status</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($allLocalFiles as $file) {
                $valid = in_array($file, $validFiles);
                $html .= '
                    <tr>
                        <td>' . htmlspecialchars($file) . '</td>
                        <td style="text-align: center; color: ' . ($valid ? 'green' : 'red') . ';">' . 
                            ($valid ? '? Valid' : '? Invalid') . '</td>
                    </tr>';
            }
            
            $html .= '
                </tbody>
            </table>
        </div>
    </div>';
        }
        
        $html .= '
</body>
</html>';
        
        file_put_contents(self::RESULTS_FILE, $html);
    }
}

// Add basic HTML structure if running in browser
if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Hash Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #f2f2f2;
            text-align: left;
        }
        a {
            color: #0066cc;
        }
    </style>
</head>
<body>
    <h1>File Hash Checker</h1>';
}

// Run the main function
FileHashChecker::main();

// Close HTML if running in browser
if (php_sapi_name() !== 'cli') {
    echo '</body></html>';
}
?>