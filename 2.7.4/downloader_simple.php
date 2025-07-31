<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Downloader</title>
</head>
<body>

    <h1>PHP Content Downloader</h1>
    <form action="" method="post">
        <label for="userInput">Enter Text or SHA256 Hash:</label>
        <input type="text" id="userInput" name="userInput" size="70" required>
        <button type="submit">Submit</button>
    </form>
    <hr>

    <div>
        <?php
        // Set higher execution time for lengthy downloads
        set_time_limit(300);

        // Main logic starts when the form is submitted
        if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['userInput'])) {
            $userInput = trim($_POST['userInput']);
            echo "<h2>Processing Request...</h2>";
            echo "<pre>"; // Use <pre> tag to format output like a console
            processRequest($userInput);
            echo "</pre>";
        }

        /**
         * Processes a single download request based on user input.
         * @param string $userText The text or SHA256 hash provided by the user.
         */
        function processRequest(string $userText): void
        {
            if (isValidSha256($userText)) {
                $hash = strtolower($userText);
                echo "Using provided Hash: " . htmlspecialchars($hash) . "\n";
            } else {
                $hash = generateSha256($userText);
                echo "Generated Hash: " . htmlspecialchars($hash) . "\n";
                echo "Original Text: " . htmlspecialchars($userText) . "\n";
            }

            $dataServersDir = 'data';
            $hashDir = $dataServersDir . DIRECTORY_SEPARATOR . $hash;
            $indexFile = $hashDir . DIRECTORY_SEPARATOR . 'index.html';
            $dataDir = 'data';

            // Create base directories if they don't exist
            if (!is_dir($dataServersDir)) mkdir($dataServersDir, 0777, true);
            if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

            // Always attempt to find and process content from all available servers.
            processAllServers($hash, $hashDir, $indexFile, $dataDir);

            if (file_exists($indexFile)) {
                echo "\nLocal HTML file is available.";
                echo "\nNote: This file reflects the content from the last successful server query.\n";
            }
        }

        /**
         * Iterates through all servers in servers.txt, downloads index files, and processes assets.
         */
        function processAllServers(string $hash, string $hashDir, string $indexFile, string $dataDir): void
        {
            $serversFile = 'servers.txt';
            if (!file_exists($serversFile)) {
                echo "Error: 'servers.txt' file not found. Please create it and add server URLs.\n";
                return;
            }
            $servers = file($serversFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $successfulServers = [];
            $allDownloadedFiles = [];
            $allFailedDownloads = [];
            $processedUrls = []; // Avoids re-processing the same asset URL in one run

            echo "\nChecking all servers for content...\n";

            foreach ($servers as $server) {
                $server = trim($server);
                if (empty($server)) continue;

                $urlToCheck = buildProperUrl($server, $hash, 'index.html');
                
                echo "---------------------------------\n";
                echo "Querying Server: " . htmlspecialchars($server) . "\n";
                
                $pageContent = getContent($urlToCheck, $errorMessage);

                if ($pageContent !== null) {
                    echo "SUCCESS: Found index.html on " . htmlspecialchars($server) . "\n";
                    $successfulServers[] = $server;

                    if (!is_dir($hashDir)) mkdir($hashDir, 0777, true);
                    
                    file_put_contents($indexFile, $pageContent);
                    
                    echo "Processing linked files from " . htmlspecialchars($server) . "...\n";
                    $results = downloadLinkedFiles($pageContent, $server, $hash, $dataDir, $processedUrls);
                    
                    $allDownloadedFiles = array_merge($allDownloadedFiles, $results['downloaded']);
                    $allFailedDownloads = array_merge($allFailedDownloads, $results['failed']);

                } else {
                    echo "FAILURE: Could not retrieve index.html. Reason: " . htmlspecialchars($errorMessage) . "\n";
                }
            }
            
            echo "\n--- Overall Download Report ---\n";
            if (empty($successfulServers)) {
                echo "Content not found on any of the specified servers.\n";
            } else {
                echo "Found index.html on " . count($successfulServers) . " server(s): " . htmlspecialchars(implode(', ', $successfulServers)) . "\n";
                printDownloadResults(['downloaded' => $allDownloadedFiles, 'failed' => $allFailedDownloads]);
            }
        }

        /**
         * Downloads files linked within HTML, skipping files that already exist.
         */
        function downloadLinkedFiles(string $htmlContent, string $baseServer, string $hash, string $dataDir, array &$processedUrls): array
        {
            $downloadedFiles = [];
            $failedDownloads = [];

            preg_match_all('/(href|src)=["\']([^"\']+)["\']/i', $htmlContent, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $attribute = $match[1];
                $originalUrl = $match[2];

                if (preg_match('/^(data:|javascript:|mailto:|#|android-app:|ios-app:)/', $originalUrl) ||
                   (strtolower($attribute) === 'href' && preg_match('/\.html?$/', $originalUrl))) {
                    continue;
                }

                if (preg_match('/^https?:\/\//', $originalUrl)) {
                    $processedUrl = $originalUrl;
                } else {
                    $baseServer = rtrim($baseServer, '/');
                    $processedUrl = ($originalUrl[0] === '/') ? $baseServer . $originalUrl : buildProperUrl($baseServer, $hash, $originalUrl);
                }
                
                if (in_array($processedUrl, $processedUrls)) {
                    continue;
                }
                $processedUrls[] = $processedUrl;

                $filename = sanitizeFilename(basename(parse_url($processedUrl, PHP_URL_PATH)));
                if (empty($filename)) $filename = 'index.html';
                
                $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                $fileDir = $dataDir . DIRECTORY_SEPARATOR . sanitizeFilename($filenameWithoutExt);
                $filePath = $fileDir . DIRECTORY_SEPARATOR . $filename;

                if (file_exists($filePath)) {
                    $downloadedFiles[] = [
                        'url' => $processedUrl,
                        'local_path' => $filePath,
                        'size' => filesize($filePath),
                        'status' => 'already_exists'
                    ];
                    continue;
                }
                
                $fileContent = getContent($processedUrl, $errorMessage);

                if ($fileContent !== null) {
                    if (!is_dir($fileDir)) mkdir($fileDir, 0777, true);
                    file_put_contents($filePath, $fileContent);
                    $downloadedFiles[] = [
                        'url' => $processedUrl,
                        'local_path' => $filePath,
                        'size' => strlen($fileContent),
                        'status' => 'downloaded'
                    ];
                } else {
                    $failedDownloads[] = [
                        'url' => $processedUrl,
                        'error' => $errorMessage
                    ];
                }
            }

            return ['downloaded' => $downloadedFiles, 'failed' => $failedDownloads];
        }
        
        // --- UTILITY AND HELPER FUNCTIONS ---

        function getContent(string $url, ?string &$errorMessage = ''): ?string
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Not recommended for production
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Not recommended for production

            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode >= 400 || $content === false) {
                $errorMessage = curl_error($ch) ?: "HTTP status code: $httpCode";
                curl_close($ch);
                return null;
            }

            curl_close($ch);
            return $content;
        }

        function buildProperUrl(string $server, string $hash, string $filename): string
        {
            return rtrim($server, '/') . '/data/' . $hash . '/' . $filename;
        }
        
        function sanitizeFilename(string $filename): string
        {
            return preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        }

        function isValidSha256(string $hash): bool
        {
            return (bool) preg_match('/^[a-f0-9]{64}$/', $hash);
        }

        function generateSha256(string $input): string
        {
            return hash('sha256', $input);
        }

        function printDownloadResults(array $results): void
        {
            $downloaded = $results['downloaded'];
            $failed = $results['failed'];

            $downloadedCount = count(array_filter($downloaded, fn($f) => $f['status'] === 'downloaded'));
            $existingCount = count(array_filter($downloaded, fn($f) => $f['status'] === 'already_exists'));
            $failedCount = count($failed);
            $totalSize = array_sum(array_column($downloaded, 'size'));

            echo "\n--- Asset Download Statistics ---\n";
            echo "New files downloaded: $downloadedCount\n";
            echo "Files already existed: $existingCount\n";
            echo "Failed to download: $failedCount\n";
            echo "Total size of local assets: " . round($totalSize / 1024) . " KB\n";

            if (!empty($failed)) {
                echo "\n--- Failed Download Details ---\n";
                foreach ($failed as $file) {
                    echo "URL: " . htmlspecialchars($file['url']) . "\n  Error: " . htmlspecialchars($file['error']) . "\n";
                }
            }
            echo "---------------------------------\n";
        }
        ?>
    </div>

</body>
</html>
