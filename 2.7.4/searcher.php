<?php
// File Search System
// This program searches for files across multiple servers based on category and name matching

class FileSearchSystem {
    private $servers = [];
    
    public function __construct() {
        $this->loadServers();
    }
    
    /**
     * Load servers from servers.txt file
     */
    private function loadServers() {
        if (file_exists('servers.txt')) {
            $content = file_get_contents('servers.txt');
            $this->servers = array_filter(array_map('trim', explode("\n", $content)));
        } else {
            echo "<div class='error'>Error: servers.txt file not found!</div>";
        }
    }
    
    /**
     * Check if string is a valid SHA256 hash
     */
    private function isValidSHA256($string) {
        return preg_match('/^[a-f0-9]{64}$/i', $string);
    }
    
    /**
     * Convert input to SHA256 hash if not already a hash
     */
    private function processCategory($category) {
        if ($this->isValidSHA256($category)) {
            return strtolower($category);
        }
        return hash('sha256', $category);
    }
    
    /**
     * Extract links from HTML content
     */
    private function extractLinks($html, $baseUrl) {
        $links = [];
        $dom = new DOMDocument();
        
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $anchors = $dom->getElementsByTagName('a');
        
        foreach ($anchors as $anchor) {
            $href = $anchor->getAttribute('href');
            $text = trim($anchor->textContent);
            
            if (!empty($href) && !empty($text)) {
                // Handle relative URLs
                if (!filter_var($href, FILTER_VALIDATE_URL)) {
                    $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                }
                
                $links[] = [
                    'url' => $href,
                    'name' => $text
                ];
            }
        }
        
        return $links;
    }
    
    /**
     * Check if a name matches the search criteria (partial match, case insensitive)
     */
    private function nameMatches($searchName, $fileName) {
        return stripos($fileName, $searchName) !== false;
    }
    
    /**
     * Fetch content from URL with error handling
     */
    private function fetchUrl($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; FileSearchBot/1.0)'
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        return $content !== false ? $content : null;
    }
    
    /**
     * Search for files across all servers
     */
    public function search($category, $name) {
        $results = [];
        $hash = $this->processCategory($category);
        
        echo "<div class='search-info'>";
        echo "<h3>Search Parameters:</h3>";
        echo "<p><strong>Original Category:</strong> " . htmlspecialchars($category) . "</p>";
        echo "<p><strong>Category Hash:</strong> " . htmlspecialchars($hash) . "</p>";
        echo "<p><strong>Search Name:</strong> " . htmlspecialchars($name) . "</p>";
        echo "</div>";
        
        foreach ($this->servers as $server) {
            $server = rtrim($server, '/');
            $indexUrl = $server . '/data/' . $hash . '/index.html';
            
            echo "<div class='server-check'>";
            echo "<h4>Checking server: " . htmlspecialchars($server) . "</h4>";
            echo "<p>URL: " . htmlspecialchars($indexUrl) . "</p>";
            
            $content = $this->fetchUrl($indexUrl);
            
            if ($content === null) {
                echo "<p class='status error'>? Could not fetch content from this server</p>";
                echo "</div>";
                continue;
            }
            
            echo "<p class='status success'>? Content fetched successfully</p>";
            
            $links = $this->extractLinks($content, $server . '/data/' . $hash . '/');
            
            if (empty($links)) {
                echo "<p class='status warning'>?? No links found in index.html</p>";
                echo "</div>";
                continue;
            }
            
            $matchingLinks = [];
            foreach ($links as $link) {
                if ($this->nameMatches($name, $link['name'])) {
                    $matchingLinks[] = $link;
                }
            }
            
            if (!empty($matchingLinks)) {
                echo "<p class='status success'>? Found " . count($matchingLinks) . " matching links</p>";
                $results[$server] = $matchingLinks;
            } else {
                echo "<p class='status warning'>?? No links match the search name</p>";
            }
            
            echo "</div>";
        }
        
        return $results;
    }
    
    /**
     * Display search results
     */
    public function displayResults($results) {
        if (empty($results)) {
            echo "<div class='no-results'>";
            echo "<h3>No Results Found</h3>";
            echo "<p>No matching files were found on any server.</p>";
            echo "</div>";
            return;
        }
        
        echo "<div class='results'>";
        echo "<h3>Search Results:</h3>";
        
        foreach ($results as $server => $links) {
            echo "<div class='server-results'>";
            echo "<h4>Results from: " . htmlspecialchars($server) . "</h4>";
            echo "<ul>";
            
            foreach ($links as $link) {
                echo "<li>";
                echo "<a href='" . htmlspecialchars($link['url']) . "' target='_blank' rel='noopener'>";
                echo htmlspecialchars($link['name']);
                echo "</a>";
                echo "</li>";
            }
            
            echo "</ul>";
            echo "</div>";
        }
        
        echo "</div>";
    }
}

// Initialize the system
$searchSystem = new FileSearchSystem();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $name = $_POST['name'] ?? '';
    
    if (!empty($category) && !empty($name)) {
        $results = $searchSystem->search($category, $name);
        $showResults = true;
    } else {
        $error = "Please fill in both category and name fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Search System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .form-container {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: white;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            width: 100%;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .search-info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
        
        .search-info h3 {
            margin-bottom: 15px;
            color: #0c5460;
        }
        
        .server-check {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #6c757d;
        }
        
        .server-check h4 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .status {
            margin: 8px 0;
            padding: 8px 12px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .results {
            margin-top: 30px;
        }
        
        .results h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .server-results {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .server-results h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .server-results ul {
            list-style: none;
        }
        
        .server-results li {
            margin-bottom: 10px;
        }
        
        .server-results a {
            display: inline-block;
            padding: 10px 15px;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
            word-break: break-all;
        }
        
        .server-results a:hover {
            background: #4facfe;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .no-results h3 {
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .content {
                padding: 20px;
            }
            
            .form-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>?? File Search System</h1>
            <p>Search for files across multiple servers using category hashing and name matching</p>
        </div>
        
        <div class="content">
            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <input type="text" 
                               name="category" 
                               id="category" 
                               value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>" 
                               placeholder="Enter category name or SHA256 hash"
                               required>
                        <small style="color: #6c757d; font-size: 0.9em; margin-top: 5px; display: block;">
                            Enter a category name (will be hashed to SHA256) or a valid SHA256 hash
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               placeholder="Enter file name to search for"
                               required>
                        <small style="color: #6c757d; font-size: 0.9em; margin-top: 5px; display: block;">
                            Partial matches are supported (e.g., "ana" will match "Ana_house.jpg")
                        </small>
                    </div>
                    
                    <button type="submit" class="submit-btn">?? Search Files</button>
                </form>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($showResults)): ?>
                <?php $searchSystem->displayResults($results); ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>