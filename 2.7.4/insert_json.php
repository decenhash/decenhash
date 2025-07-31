<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Indexer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
 
    <div align="right"><a href="index_search.html">Search</a>&nbsp;
    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-2xl">
        <div class="bg-white shadow-lg rounded-xl p-6 sm:p-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-center text-gray-900 mb-6">URL Indexer</h1>

            <?php
            // --- PHP PROCESSING LOGIC ---

            // Directory paths
            $json_search_dir = 'json_search';
            $json_hash_dir = 'json_search_hash';

            // Create directories if they don't exist
            if (!is_dir($json_search_dir)) {
                mkdir($json_search_dir, 0777, true);
            }
            if (!is_dir($json_hash_dir)) {
                mkdir($json_hash_dir, 0777, true);
            }

            // --- Function to generate substrings ---
            /**
             * Generates all substrings of a given word that are 3 or more characters long.
             * @param string $word The input word.
             * @return array An array of unique substrings.
             */
            function generate_substrings($word) {
                $substrings = [];
                $len = strlen($word);
                if ($len < 3) {
                    return [];
                }
                for ($i = 0; $i < $len; $i++) {
                    for ($j = $i + 2; $j < $len; $j++) {
                        $substrings[] = substr($word, $i, $j - $i + 1);
                    }
                }
                // Using array_unique to avoid duplicate entries if a word has repeating patterns
                return array_unique($substrings);
            }

            // --- Function to extract domain and path from URL ---
            /**
             * Extracts meaningful parts from URL for indexing
             * @param string $url The input URL
             * @return string Combined text for processing
             */
            function extract_url_parts($url) {
                // Parse the URL
                $parsed = parse_url($url);
                
                $parts = [];
                
                // Add domain (without www)
                if (isset($parsed['host'])) {
                    $domain = $parsed['host'];
                    $domain = preg_replace('/^www\./', '', $domain);
                    $parts[] = $domain;
                }
                
                // Add path parts
                if (isset($parsed['path']) && $parsed['path'] !== '/') {
                    $path = trim($parsed['path'], '/');
                    $path_parts = explode('/', $path);
                    $parts = array_merge($parts, $path_parts);
                }
                
                // Add query parameters
                if (isset($parsed['query'])) {
                    parse_str($parsed['query'], $query_params);
                    foreach ($query_params as $key => $value) {
                        if (is_string($value)) {
                            $parts[] = $key;
                            $parts[] = $value;
                        }
                    }
                }
                
                return implode('_', $parts);
            }

            // --- Handle form submission ---
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $url = isset($_POST['url']) ? trim($_POST['url']) : '';
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';

                // Basic validation
                if (empty($url)) {
                    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> URL is required.</p></div>';
                } elseif (empty($title)) {
                    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> Title is required.</p></div>';
                } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> Please enter a valid URL.</p></div>';
                } else {
                    // --- Main Logic ---

                    // 1. Calculate SHA-256 hash of the URL
                    $url_hash = hash('sha256', $url);
                    $hash_file_path = $json_hash_dir . '/' . $url_hash;

                    // 2. Check if the hash file exists
                    if (file_exists($hash_file_path)) {
                        // Show error message if URL is already indexed
                        echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-6" role="alert"><p><strong>Info:</strong> This URL has already been processed.</p></div>';
                    } else {
                        // 3. Extract and process URL parts
                        $url_parts = extract_url_parts($url);
                        
                        // 4. Sanitize the URL parts
                        $sanitized_url_parts = preg_replace('/[^\p{L}\p{N}_]+/u', '_', $url_parts);
                        // Replace multiple underscores with a single one
                        $sanitized_url_parts = preg_replace('/__+/', '_', $sanitized_url_parts);

                        // 5. Sanitize the title
                        $sanitized_title = preg_replace('/[^\p{L}\p{N}_]+/u', '_', $title);
                        $sanitized_title = preg_replace('/__+/', '_', $sanitized_title);

                        // 6. Combine title and URL parts for processing
                        $combined_text = $sanitized_title . '_' . $sanitized_url_parts;
                        
                        // 7. Explode the combined text into words
                        $words = explode('_', $combined_text);

                        $all_substrings = [];
                        foreach ($words as $word) {
                            // Avoid words with only numbers
                            if (is_numeric($word)) {
                                continue;
                            }

                            // Avoid words with numbers 
                            if (preg_match('/[0-9]/', $word)) {
                                continue;
                            }

                            // Convert to lowercase for consistent indexing
                            $word_lower = strtolower($word);
                            if (strlen($word_lower) >= 3) {
                                $substrings = generate_substrings($word_lower);
                                $all_substrings = array_merge($all_substrings, $substrings);
                            }
                        }
                        // Ensure all substrings are unique across the entire text
                        $all_substrings = array_unique($all_substrings);

                        // 8. Create JSON files for each subword
                        // Get user from session, or set to blank if not set
                        $user = isset($_SESSION['user']) ? $_SESSION['user'] : '';
                        
                        // Get current date in YYYYMMDD format
                        $current_date = date('Ymd');
                        
                        $data_to_store = [
                            'link' => $url,
                            'title' => $title,
                            'original_filename' => '',
                            'user' => $user,
                            'date' => $current_date
                        ];
                        $files_created_count = 0;

                        foreach ($all_substrings as $subword) {
                            $max_size = 100 * 1024; // 100KB in bytes
                            $current_date_for_filename = date('Ymd'); // Format: YYYYMMDD
                            
                            // Start with the base JSON file path
                            $base_json_file = $json_search_dir . '/' . $subword . '.json';
                            $json_file_path = $base_json_file;
                            
                            // Check if the base file exists and exceeds size limit
                            if (file_exists($base_json_file) && filesize($base_json_file) >= $max_size) {
                                // Use dated filename instead
                                $json_file_path = $json_search_dir . '/' . $subword . $current_date_for_filename . '.json';
                                
                                // If the dated file also exists and exceeds size, create a new one with counter
                                if (file_exists($json_file_path) && filesize($json_file_path) >= $max_size) {
                                    $counter = 1;
                                    do {
                                        $counter++;
                                        $json_file_path = $json_search_dir . '/' . $subword . $current_date_for_filename . '_' . $counter . '.json';
                                    } while (file_exists($json_file_path) && filesize($json_file_path) >= $max_size && $counter <= 100);
                                }
                            }

                            $entries = [];
                            // If file exists, read its content
                            if (file_exists($json_file_path)) {
                                $current_data = file_get_contents($json_file_path);
                                $entries = json_decode($current_data, true);
                                // Ensure it's an array
                                if (!is_array($entries)) {
                                    $entries = [];
                                }
                            }

                            // Add new data to the array
                            $entries[] = $data_to_store;

                            // Write the updated array back to the JSON file
                            $file_handle = fopen($json_file_path, 'w');
                            if ($file_handle) {
                                fwrite($file_handle, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                fclose($file_handle);
                                $files_created_count++;
                            }
                        }

                        // 9. Create the hash file to mark this URL as processed
                        touch($hash_file_path);

                        // Show success message
                        echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6" role="alert">';
                        echo '<p class="font-bold">Success!</p>';
                        echo "<p>The URL '<strong>" . htmlspecialchars($url) . "</strong>' has been indexed.</p>";
                        echo "<p>Title: <strong>" . htmlspecialchars($title) . "</strong></p>";
                        echo "<p>Generated <strong>" . count($all_substrings) . "</strong> unique search keys.</p>";
                        if (!empty($user)) {
                            echo "<p>Indexed by user: <strong>" . htmlspecialchars($user) . "</strong></p>";
                        }
                        echo "<p>Index date: <strong>" . $current_date . "</strong></p>";
                        echo '</div>';
                    }
                }
            }
            ?>

            <!-- HTML FORM -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="mb-6">
                    <label for="url" class="block mb-2 text-sm font-medium text-gray-700">URL</label>
                    <input type="url" id="url" name="url" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="https://example.com/page" required>
                    <p class="mt-1 text-sm text-gray-500">Enter the complete URL you want to index.</p>                    
                </div>
                <div class="mb-6">
                    <label for="title" class="block mb-2 text-sm font-medium text-gray-700">Title</label>
                    <input type="text" id="title" name="title" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="e.g., Important Documentation Page" required>
                    <p class="mt-1 text-sm text-gray-500">This will be used as the exact title in the JSON files.</p>
                </div>
                <button type="submit" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center transition-colors duration-200">
                    Index URL
                </button>
            </form>
        </div>
        <footer class="text-center text-gray-500 text-xs mt-6">
            <p>All rights reserved</p>           
        </footer>
    </div>

</body>
</html>