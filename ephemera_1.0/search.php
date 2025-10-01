<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Decenhash</title>
    <style>
        /* Minimalist CSS Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        /* Header with top menu */
        header {
            background-color: #fff;
            border-bottom: 1px solid #eaeaea;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-weight: 600;
            font-size: 1.25rem;
            color: #2e7d32;
        }

        nav {
            display: flex;
            gap: 1.5rem;
        }

        nav a {
            text-decoration: none;
            color: #555;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        nav a:hover {
            color: #2e7d32;
        }

        .top-right-image {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .top-right-image:hover {
            opacity: 0.8;
        }

        /* Main content layout */
        main {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Search form */
        .search-section {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .search-section h1 {
            margin-bottom: 1.5rem;
            color: #2e7d32;
            font-size: 2rem;
            font-weight: 700;
        }

        .search-form {
            display: flex;
            gap: 1rem;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #2e7d32;
        }

        .search-button {
            padding: 0.75rem 1.5rem;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .search-button:hover {
            background-color: #256628;
        }

        /* Results section */
        .results-section {
            background: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .results-count {
            color: #555;
            font-size: 0.9rem;
        }

        .result-item {
            border: 1px solid #eaeaea;
            border-radius: 6px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }

        .result-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .result-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2e7d32;
        }

        .result-link {
            display: inline-block;
            background-color: #2e7d32;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            transition: background-color 0.2s;
        }

        .result-link:hover {
            background-color: #256628;
        }

        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .result-field {
            margin-bottom: 0.5rem;
        }

        .result-label {
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
        }

        .result-value {
            color: #333;
            word-break: break-word;
        }

        .no-results {
            text-align: center;
            padding: 2rem;
            color: #777;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
            color: #777;
            font-size: 0.9rem;
            border-top: 1px solid #eaeaea;
        }

        /* Message styling */
        .message {
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-weight: 500;
        }

        .message.info {
            background-color: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }

        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }
            
            .result-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">DECENHASH</div>
        <nav>
            <a href='index.php'>home</a>
            <a href=''>file rank</a>
            <a href=''>user rank</a>
            <a href='search.php'>search</a>
        </nav>
        <a href="https://example.com" target="_blank">
            <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAzMiAzMiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjMyIiBoZWlnaHQ9IjMyIiByeD0iNCIgZmlsbD0iIzJlN2QzMiIvPgo8cGF0aCBkPSJNMTYgMTBMMTAgMTZMMTYgMjJNMjIgMTZIMTBNMTYgMTBMMjIgMTZMMTYgMjJNMjIgMTZIMTBaIiBzdHJva2U9IndoaXRlIiBzdHJva2Utd2lkdGg9IjIiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIvPgo8L3N2Zz4K" 
                 alt="External Link" 
                 class="top-right-image">
        </a>
    </header>

    <main>
        <section class="search-section">
            <h1>Search Files</h1>
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Enter search terms..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="search-button">Search</button>
            </form>
        </section>

        <section class="results-section">
            <?php
            // Function to read all JSON files and search for matches
            function searchJSONFiles($searchTerm) {
                $results = [];
                $jsonDir = 'json/';
                
                // Check if json directory exists
                if (!is_dir($jsonDir)) {
                    return ['error' => 'JSON directory not found'];
                }
                
                // Get all JSON files
                $files = glob($jsonDir . '*.json');
                
                foreach ($files as $file) {
                    // Read and decode JSON file
                    $jsonContent = file_get_contents($file);
                    $data = json_decode($jsonContent, true);
                    
                    if ($data === null) {
                        continue; // Skip invalid JSON files
                    }
                    
                    // Check if any field contains the search term (case-insensitive)
                    $matchFound = false;
                    foreach ($data as $key => $value) {
                        if (is_string($value) && stripos($value, $searchTerm) !== false) {
                            $matchFound = true;
                            break;
                        }
                    }
                    
                    if ($matchFound) {
                        // Get filename without extension for the hash
                        $filename = pathinfo($file, PATHINFO_FILENAME);
                        $data['_hash'] = $filename;
                        $results[] = $data;
                        
                        // Limit to 500 results
                        if (count($results) >= 500) {
                            break;
                        }
                    }
                }
                
                return $results;
            }

            // Process search request
            $searchTerm = '';
            $results = [];
            
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search']) && !empty(trim($_GET['search']))) {
                $searchTerm = trim($_GET['search']);
                $results = searchJSONFiles($searchTerm);
                
                if (isset($results['error'])) {
                    echo '<div class="message info">' . htmlspecialchars($results['error']) . '</div>';
                    $results = [];
                }
            }
            ?>

            <?php if (!empty($searchTerm)): ?>
                <div class="results-header">
                    <h2>Search Results</h2>
                    <div class="results-count">
                        <?php echo count($results); ?> result(s) for "<?php echo htmlspecialchars($searchTerm); ?>"
                    </div>
                </div>

                <?php if (empty($results)): ?>
                    <div class="no-results">
                        <p>No results found for "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($results as $result): ?>
                        <div class="result-item">
                            <div class="result-title">
                                <?php echo !empty($result['title']) ? htmlspecialchars($result['title']) : 'Untitled'; ?>
                            </div>
                            
                            <?php if (isset($result['_hash'])): ?>
                                <a href="redirect.php?hash=<?php echo urlencode($result['_hash']); ?>" 
                                   target="_blank" 
                                   class="result-link">
                                    Open File/URL
                                </a>
                            <?php endif; ?>
                            
                            <div class="result-details">
                                <?php if (!empty($result['user'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">User</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['user']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['description'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">Description</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['description']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['category'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">Category</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['category']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['date'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">Date</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['date']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['size'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">Size</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['size']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['type'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">Type</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['type']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['url'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">URL</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['url']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['PIX'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">PIX</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['PIX']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['SOL'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">SOL</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['SOL']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['PAYPAL'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">PAYPAL</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['PAYPAL']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($result['BTC'])): ?>
                                    <div class="result-field">
                                        <div class="result-label">BTC</div>
                                        <div class="result-value"><?php echo htmlspecialchars($result['BTC']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-results">
                    <p>Enter a search term to find files and URLs</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>All rights reserved</p>
    </footer>
</body>
</html>