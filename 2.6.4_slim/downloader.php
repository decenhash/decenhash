<?php
// Function to fetch content from a URL with improved error handling
function get_content($url, &$error_message) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $data = curl_exec($ch);

    if(curl_errno($ch)) {
        $error_message = curl_error($ch);
        curl_close($ch);
        return false;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code >= 400) {
        $error_message = "HTTP status code: " . $http_code;
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $data;
}

function replace_dotdot($url) {
    return str_replace('/../', '/data/', $url);
}

function build_proper_url($server, $hash, $filename = 'index.html') {
    $server = rtrim($server, '/');
    return $server . '/data/' . $hash . '/' . $filename;
}

function sanitize_filename($filename) {
    // Remove path traversal and invalid characters
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

function get_file_extension($url) {
    $path = parse_url($url, PHP_URL_PATH);
    return pathinfo($path, PATHINFO_EXTENSION);
}

function get_filename_without_extension($url) {
    $path = parse_url($url, PHP_URL_PATH);
    $filename = pathinfo($path, PATHINFO_FILENAME);
    return $filename ? $filename : 'file';
}

function is_valid_sha256($hash) {
    return preg_match('/^[a-f0-9]{64}$/i', $hash);
}

function download_linked_files($html_content, $base_server, $hash, $data_dir) {
    $downloaded_files = [];
    $failed_downloads = [];
    
    // Create data directory if it doesn't exist
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
    }
    
    // Extract all src and href attributes
    preg_match_all('/(href|src)=["\']([^"\']+)["\']/i', $html_content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $attribute = $match[1];
        $url = $match[2];
        
        // Skip data URLs, javascript, mailto, etc.
        if (preg_match('/^(data:|javascript:|mailto:|#)/', $url)) {
            continue;
        }
        
        // Skip HTML files for href attributes (likely navigation links)
        if ($attribute === 'href' && preg_match('/\.html?$/i', $url)) {
            continue;
        }
        
        // Convert relative URLs to absolute
        if (!preg_match('/^https?:\/\//', $url)) {
            $base_url = rtrim($base_server, '/');
            if (substr($url, 0, 1) === '/') {
                $url = $base_url . $url;
            } else {
                $url = $base_url . '/data/' . $hash . '/' . $url;
            }
        }
        
        // Get filename and create subdirectory
        $filename = sanitize_filename(basename(parse_url($url, PHP_URL_PATH)));
        if (empty($filename) || $filename === '.') {
            $filename = 'index.html';
        }
        
        $filename_without_ext = get_filename_without_extension($url);
        $file_dir = $data_dir . '/' . sanitize_filename($filename_without_ext);
        
        if (!is_dir($file_dir)) {
            mkdir($file_dir, 0755, true);
        }
        
        $file_path = $file_dir . '/' . $filename;
        
        // Skip if already downloaded
        if (in_array($url, array_column($downloaded_files, 'url'))) {
            continue;
        }
        
        // Check if file already exists
        if (file_exists($file_path)) {
            $downloaded_files[] = [
                'url' => $url,
                'local_path' => $file_path,
                'size' => filesize($file_path),
                'status' => 'already_exists'
            ];
            continue;
        }
        
        // Download the file
        $error_message = '';
        $file_content = get_content($url, $error_message);
        
        if ($file_content !== false) {
            if (file_put_contents($file_path, $file_content)) {
                $downloaded_files[] = [
                    'url' => $url,
                    'local_path' => $file_path,
                    'size' => strlen($file_content),
                    'status' => 'downloaded'
                ];
            } else {
                $failed_downloads[] = [
                    'url' => $url,
                    'error' => 'Failed to save file'
                ];
            }
        } else {
            $failed_downloads[] = [
                'url' => $url,
                'error' => $error_message
            ];
        }
    }
    
    return ['downloaded' => $downloaded_files, 'failed' => $failed_downloads];
}

$output_log = '';
$found_servers = [];
$download_results = [];

if (isset($_POST['user_text']) && !empty($_POST['user_text'])) {
    ob_start();

    $user_text = $_POST['user_text'];
    
    // Check if user input is already a valid SHA256 hash
    if (is_valid_sha256($user_text)) {
        $hash = strtolower($user_text);
        echo "<div class='mb-4'><strong>Using provided Hash:</strong> <code>$hash</code></div>";
    } else {
        $hash = hash('sha256', $user_text);
        echo "<div class='mb-4'><strong>Generated Hash:</strong> <code>$hash</code></div>";
        echo "<div class='info mb-4'><strong>Original Text:</strong> " . htmlspecialchars($user_text) . "</div>";
    }

    $data_servers_dir = 'data_servers';
    $hash_dir = $data_servers_dir . '/' . $hash;
    $index_file = $hash_dir . '/index.html';
    $data_dir = 'data';

    if (!is_dir($data_servers_dir)) {
        mkdir($data_servers_dir, 0755, true);
    }

    if (file_exists($index_file)) {
        echo "<div class='success mb-4'>Found cached version.</div>";
        
        // Still download linked files if they don't exist
        $html_content = file_get_contents($index_file);
        $download_results = download_linked_files($html_content, '', $hash, $data_dir);
        
    } else {
        $servers_file = 'servers.txt';
        if (!file_exists($servers_file)) {
            die("Error: 'servers.txt' file not found.");
        }

        $servers = file($servers_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $content_saved = false;
        $original_content = '';
        $successful_server = '';

        foreach ($servers as $server) {
            $server = trim($server);
            $original_server = $server;
            $url_to_check = build_proper_url($server, $hash, 'index.html');

            $curl_error = null;
            $page_content = get_content($url_to_check, $curl_error);

            if ($page_content !== false) {
                $found_servers[] = [
                    'original' => $original_server,
                    'processed' => $server,
                    'full_url' => $url_to_check
                ];

                if (!$content_saved) {
                    if (!is_dir($hash_dir)) {
                        mkdir($hash_dir, 0755, true);
                    }

                    $original_content = $page_content;
                    $successful_server = $server;

                    // Process the content for local viewing
                    $page_content = preg_replace_callback(
                        '/(href|src)=["\'](?!https?:\/\/|\/\/|data:)([^"\']+)["\']/i',
                        function($matches) use ($server, $hash) {
                            $url = $matches[2];
                            $base_url = rtrim($server, '/');
                            if (substr($url, 0, 1) === '/') {
                                $absolute_url = $base_url . $url;
                            } else {
                                $absolute_url = $base_url . '/data/' . $hash . '/' . $url;
                            }
                            return $matches[1] . '="' . $absolute_url . '"';
                        },
                        $page_content
                    );

                    file_put_contents($index_file, $page_content);
                    $content_saved = true;
                    
                    echo "<div class='success mb-4'>File found and saved locally.</div>";
                    
                    // Download all linked files
                    echo "<div class='info mb-4'>Downloading linked files...</div>";
                    $download_results = download_linked_files($original_content, $server, $hash, $data_dir);
                }
            }
        }

        if (empty($found_servers)) {
            echo "<div class='error'>File not found on any server.</div>";
        }
    }

    $output_log = ob_get_clean();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloader</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 20px;
            background: #f8fafc;
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        h1, h2, h3 {
            font-weight: 600;
            color: #111;
            text-align: center;
        }
        h1 { 
            font-size: 1.8rem; 
            margin-bottom: 2rem; 
        }
        h2 { 
            font-size: 1.4rem; 
            margin: 1.5rem 0 1rem 0; 
        }
        h3 { 
            font-size: 1.2rem; 
            margin: 1rem 0 0.5rem 0; 
        }
        
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        label {
            display: block;
            margin-bottom: 1rem;
            font-weight: 500;
            color: #374151;
        }
        
        .input-container {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
        }
        
        input[type="text"] {
            flex: 1;
            max-width: 400px;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            font-size: 1rem;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            font-size: 1rem;
            white-space: nowrap;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .btn-success {
            background: #059669;
            margin-top: 1rem;
        }
        .btn-success:hover {
            background: #047857;
        }
        
        .tip-text {
            color: #6b7280;
            font-size: 0.9em;
            margin-top: 1rem;
            text-align: center;
        }
        
        .results {
            width: 100%;
            max-width: 1000px;
        }
        
        .success {
            color: #065f46;
            background: #d1fae5;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .error {
            color: #b91c1c;
            background: #fee2e2;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .info {
            color: #1e40af;
            background: #dbeafe;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .servers-list {
            margin-top: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .server-item {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            background: #f9fafb;
        }
        .server-url {
            font-family: Menlo, Monaco, Consolas, monospace;
            font-size: 0.9em;
            color: #1f2937;
            word-break: break-all;
        }
        .downloads-section {
            margin-top: 2rem;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .download-item {
            padding: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .download-item:last-child {
            border-bottom: none;
        }
        .download-url {
            font-family: Menlo, Monaco, Consolas, monospace;
            font-size: 0.85em;
            color: #374151;
            flex: 1;
            margin-right: 1rem;
            word-break: break-all;
        }
        .download-size {
            font-size: 0.8em;
            color: #6b7280;
            white-space: nowrap;
        }
        .failed-item {
            background: #fef2f2;
            color: #b91c1c;
        }
        .already-exists {
            background: #fef7cd;
        }
        .status-badge {
            font-size: 0.75em;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            margin-left: 0.5rem;
            font-weight: 500;
        }
        .status-badge.downloaded {
            background: #d1fae5;
            color: #065f46;
        }
        .status-badge:not(.downloaded) {
            background: #fef3c7;
            color: #92400e;
        }
        code {
            font-family: Menlo, Monaco, Consolas, monospace;
            background: #f3f4f6;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.9em;
        }
        .stats {
            display: flex;
            gap: 2rem;
            margin: 1rem 0;
            justify-content: center;
            flex-wrap: wrap;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2563eb;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .copyright {
            margin-top: auto;
            padding-top: 2rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .open-file-container {
            text-align: center;
            margin-top: 2rem;
        }
        
        @media (max-width: 600px) {
            .input-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            input[type="text"] {
                max-width: 100%;
            }
            
            .stats {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Downloader</h1>
        
        <div class="form-container">
            <form action="" method="post">
                <div class="form-group">
                    <label for="user_text">Enter Text or SHA256 Hash:</label>
                    <div class="input-container">
                        <input type="text" id="user_text" name="user_text" required placeholder="Enter any text to generate SHA256 hash, or paste an existing SHA256 hash" value="<?= htmlspecialchars($_POST['user_text'] ?? '') ?>">
                        <button type="submit" class="btn">Check Servers</button>
                    </div>
                    <div class="tip-text">
                        💡 Tip: You can enter either plain text (will be hashed) or a valid SHA256 hash (will be used directly)
                    </div>
                </div>
            </form>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="results">
                <?= $output_log ?>
                
                <?php if (!empty($found_servers)): ?>
                    <div class="servers-list">
                        <h2>File Found on These Servers:</h2>
                        <?php foreach ($found_servers as $server): ?>
                            <div class="server-item">
                                <div><strong>Server:</strong> <?= htmlspecialchars($server['original']) ?></div>
                                <div class="server-url"><strong>Full URL:</strong> <?= htmlspecialchars($server['full_url']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($index_file) && file_exists($index_file)): ?>
                    <div class="open-file-container">
                        <a href="<?= htmlspecialchars($index_file) ?>" target="_blank" class="btn btn-success">Open Local HTML File</a>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($download_results)): ?>
                    <div class="downloads-section">
                        <h2>Downloaded Files</h2>
                        
                        <div class="stats">
                            <div class="stat-item">
                                <div class="stat-number"><?= count(array_filter($download_results['downloaded'], function($f) { return $f['status'] === 'downloaded'; })) ?></div>
                                <div class="stat-label">Downloaded</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= count(array_filter($download_results['downloaded'], function($f) { return $f['status'] === 'already_exists'; })) ?></div>
                                <div class="stat-label">Already Existed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= count($download_results['failed']) ?></div>
                                <div class="stat-label">Failed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= number_format(array_sum(array_column($download_results['downloaded'], 'size')) / 1024, 1) ?> KB</div>
                                <div class="stat-label">Total Size</div>
                            </div>
                        </div>
                        
                        <?php if (!empty($download_results['downloaded'])): ?>
                            <h3>File Status:</h3>
                            <?php foreach ($download_results['downloaded'] as $file): ?>
                                <div class="download-item <?= $file['status'] === 'already_exists' ? 'already-exists' : '' ?>">
                                    <div class="download-url">
                                        <?= htmlspecialchars($file['url']) ?>
                                        <?php if ($file['status'] === 'already_exists'): ?>
                                            <span class="status-badge">Already Exists</span>
                                        <?php else: ?>
                                            <span class="status-badge downloaded">Downloaded</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="download-size"><?= number_format($file['size'] / 1024, 1) ?> KB</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($download_results['failed'])): ?>
                            <h3>Failed Downloads:</h3>
                            <?php foreach ($download_results['failed'] as $file): ?>
                                <div class="download-item failed-item">
                                    <div class="download-url"><?= htmlspecialchars($file['url']) ?></div>
                                    <div class="download-size"><?= htmlspecialchars($file['error']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="copyright">
            All rights reserved
        </div>
    </div>
</body>
</html>