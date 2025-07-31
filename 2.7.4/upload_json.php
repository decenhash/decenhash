<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload and Indexer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-2xl">
        <div class="bg-white shadow-lg rounded-xl p-6 sm:p-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-center text-gray-900 mb-6">File Upload and Indexer</h1>

            <?php
            // --- PHP PROCESSING LOGIC ---

            // Directory paths
            $json_search_dir = 'json_search';
            $json_hash_dir = 'json_search_hash';
            $files_dir = 'files';
            $data_dir = 'files';

            // Create directories if they don't exist
            if (!is_dir($json_search_dir)) {
                mkdir($json_search_dir, 0777, true);
            }
            if (!is_dir($json_hash_dir)) {
                mkdir($json_hash_dir, 0777, true);
            }
            if (!is_dir($files_dir)) {
                mkdir($files_dir, 0777, true);
            }
            if (!is_dir($data_dir)) {
                mkdir($data_dir, 0777, true);
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

            // --- Handle form submission ---
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';

                // Basic validation
                if (empty($title)) {
                    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> Title is required.</p></div>';
                } elseif (!isset($_FILES['file'])) {
                    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> No file was uploaded.</p></div>';
                } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    // Handle different upload errors
                    $error_message = '';
                    switch ($_FILES['file']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                            $error_message = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
                            break;
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = 'The uploaded file was only partially uploaded.';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message = 'No file was uploaded.';
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error_message = 'Missing a temporary folder.';
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error_message = 'Failed to write file to disk.';
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = 'A PHP extension stopped the file upload.';
                            break;
                        default:
                            $error_message = 'Unknown upload error.';
                            break;
                    }
                    echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Upload Error:</strong> ' . $error_message . '</p></div>';
                } else {
                    $uploaded_file = $_FILES['file'];
                    $original_filename = $uploaded_file['name'];
                    $file_size = $uploaded_file['size'];
                    $file_tmp = $uploaded_file['tmp_name'];
                    
                    // Get file extension
                    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                    
                    // Validate file size (10MB = 10 * 1024 * 1024 bytes)
                    if ($file_size > 10 * 1024 * 1024) {
                        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> File size exceeds 10MB limit.</p></div>';
                    } elseif ($file_extension === 'php') {
                        echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> PHP files are not allowed.</p></div>';
                    } else {
                        // --- Main Logic ---

                        // 1. Calculate SHA-256 hash of the file content
                        $file_content = file_get_contents($file_tmp);
                        $file_hash = hash('sha256', $file_content);
                        $hash_file_path = $json_hash_dir . '/' . $file_hash;

                        // 2. Check if the hash file exists
                        if (file_exists($hash_file_path)) {
                            // Show error message if file is already indexed
                            echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-6" role="alert"><p><strong>Info:</strong> This file has already been processed.</p></div>';
                        } else {
                            // 3. Generate new filename using SHA-256 hash + extension
                            $new_filename = $file_hash . '.' . $file_extension;
                            $file_destination = $files_dir . '/' . $new_filename;
                            
                            // 4. Move uploaded file to files directory
                            if (move_uploaded_file($file_tmp, $file_destination)) {
                                // 5. Sanitize the title
                                // Replace non-alphanumeric characters (except underscore) with an underscore
                                $sanitized_title = preg_replace('/[^\p{L}\p{N}_]+/u', '_', $title);
                                // Replace multiple underscores with a single one
                                $sanitized_title = preg_replace('/__+/', '_', $sanitized_title);

                                // 6. Sanitize the original filename (remove extension for processing)
                                $filename_without_ext = pathinfo($original_filename, PATHINFO_FILENAME);
                                $sanitized_filename = preg_replace('/[^\p{L}\p{N}_]+/u', '_', $filename_without_ext);
                                $sanitized_filename = preg_replace('/__+/', '_', $sanitized_filename);

                                // 7. Combine title and filename for processing
                                $combined_text = $sanitized_title . '_' . $sanitized_filename;
                                
                                // 8. Explode the combined text into words
                                $words = explode('_', $combined_text);

                                $all_substrings = [];
                                foreach ($words as $word) {

                                    // Avoid filename with only numbers
                                    if (is_numeric($word)) {
                                        continue;
                                    }

                                    // Avoid filename with numbers 
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

                                // 9. Create JSON files for each subword
                                // Get user from session, or set to blank if not set
                                $user = isset($_SESSION['user']) ? $_SESSION['user'] : '';
                                
                                // Get current date in YYYYMMDD format
                                $current_date = date('Ymd');
                                
                                $data_to_store = [
                                    'link' => $data_dir . '/' . $new_filename,
                                    'title' => $title,
                                    'original_filename' => $original_filename,
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

                                // 10. Create the hash file to mark this file as processed
                                touch($hash_file_path);

                                // Show success message
                                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6" role="alert">';
                                echo '<p class="font-bold">Success!</p>';
                                echo "<p>The file '<strong>" . htmlspecialchars($original_filename) . "</strong>' has been indexed.</p>";
                                echo "<p>Saved in: <strong> ";
                                echo "<a href='files/$new_filename' target='_blank'>$new_filename</a></strong></p>";
                                echo "<p>Generated <strong>" . count($all_substrings) . "</strong> unique search keys.</p>";
                                if (!empty($user)) {
                                    echo "<p>Indexed by user: <strong>" . htmlspecialchars($user) . "</strong></p>";
                                }
                                echo "<p>Index date: <strong>" . $current_date . "</strong></p>";
                                echo '</div>';
                            } else {
                                echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><strong>Error:</strong> Failed to save the uploaded file.</p></div>';
                            }
                        }
                    }
                }
            }
            ?>

            <!-- HTML FORM -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
                <div class="mb-6">
                    <label for="file" class="block mb-2 text-sm font-medium text-gray-700">Select File to Upload</label>
                    <input type="file" id="file" name="file" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                    <p class="mt-1 text-sm text-gray-500">Maximum file size: 10MB. PHP files are not allowed.</p>                    
                </div>
                <div class="mb-6">
                    <label for="title" class="block mb-2 text-sm font-medium text-gray-700">Title</label>
                    <input type="text" id="title" name="title" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" placeholder="e.g., Important Document for Computing" required>
                </div>
                <button type="submit" class="text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center transition-colors duration-200">
                    Upload and Index
                </button>
            </form>
        </div>
        <footer class="text-center text-gray-500 text-xs mt-6">
            <p>All rights reserved</p>           
        </footer>
    </div>

</body>
</html>