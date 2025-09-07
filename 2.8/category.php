<?php
// --- PHP LOGIC ---

// Configuration
$files_directory = 'files';
$data_directory = 'data';
$max_categories = 5;

// Initialize variables
$message = '';
$message_type = ''; // 'success' or 'error'
$show_category_form = false;
$valid_filename = '';

// Ensure base directories exist
if (!is_dir($files_directory)) {
    mkdir($files_directory, 0777, true);
}
if (!is_dir($data_directory)) {
    mkdir($data_directory, 0777, true);
}

// --- FORM PROCESSING ---

// Check if the script is handling a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Step 1: Handle Filename Submission ---
    if (isset($_POST['filename'])) {
        // Sanitize filename to prevent directory traversal attacks
        $filename = basename($_POST['filename']);
        $filepath = $files_directory . '/' . $filename;

        if (!empty($filename) && file_exists($filepath)) {
            $message = "File '$filename' found. Please enter up to $max_categories categories below.";
            $message_type = 'success';
            $show_category_form = true;
            $valid_filename = $filename;
        } else {
            $message = "Error: File '$filename' does not exist in the '$files_directory' directory.";
            $message_type = 'error';
        }
    }

    // --- Step 2: Handle Categories Submission ---
    elseif (isset($_POST['categories']) && isset($_POST['original_filename'])) {
        $original_filename = basename($_POST['original_filename']);
        $categories_input = trim($_POST['categories']);
        
        // Restore state for the form view
        $show_category_form = true;
        $valid_filename = $original_filename;

        if (empty($categories_input)) {
            $message = "Please enter at least one category.";
            $message_type = 'error';
        } else {
            // Split categories by comma, trim whitespace from each
            $categories = array_map('trim', explode(',', $categories_input));
            
            // Remove any empty values that might result from extra commas
            $categories = array_filter($categories);

            // Limit to the maximum number of categories
            $categories = array_slice($categories, 0, $max_categories);

            if (empty($categories)) {
                 $message = "No valid categories provided.";
                 $message_type = 'error';
            } else {
                $processed_categories = [];
                $skipped_categories = [];
                // Generate a hash for the filename itself to use as a marker
                $file_hash = hash('sha256', $original_filename);

                foreach ($categories as $category) {
                    $category_hash = hash('sha256', $category);
                    $category_path = $data_directory . '/' . $category_hash;
                    $file_marker_path = $category_path . '/' . $file_hash;

                    // Ensure the category directory exists. If not, create it.
                    if (!is_dir($category_path)) {
                        mkdir($category_path, 0777, true);
                    }

                    // Check if the marker file for this specific file exists in the category folder.
                    if (!file_exists($file_marker_path)) {
                        // If it doesn't exist, we can proceed.
                        
                        // 1. Create the empty marker file to prevent this file from being added again.
                        touch($file_marker_path);

                        // 2. Prepare to add the link to the index.html file for this category.
                        $index_file_path = $category_path . '/index.html';
                        $relative_file_path = '../../' . $files_directory . '/' . htmlspecialchars($original_filename, ENT_QUOTES, 'UTF-8');
                        $link_html = "<a href=\"$relative_file_path\" target=\"_blank\">" . htmlspecialchars($original_filename, ENT_QUOTES, 'UTF-8') . "</a><br>\n";

                        // If index.html doesn't exist yet, create it with full HTML structure.
                        if (!file_exists($index_file_path)) {
                            $html_content = "<!DOCTYPE html>\n"
                                          . "<html lang=\"en\">\n"
                                          . "<head>\n"
                                          . "    <meta charset=\"UTF-8\">\n"
                                          . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
                                          . "    <title>Category: " . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . "</title>\n"
                                          . "    <style>body { font-family: sans-serif; padding: 20px; background-color: #f0f2f5; } h1 { color: #333; } a { display: block; margin-bottom: 10px; font-size: 1.1em; color: #007bff; text-decoration: none; } a:hover { text-decoration: underline; }</style>\n"
                                          . "</head>\n"
                                          . "<body>\n"
                                          . "<h1>" . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . "</h1>\n"
                                          . $link_html
                                          . "</body>\n"
                                          . "</html>";
                            file_put_contents($index_file_path, $html_content);
                        } else {
                            // If index.html exists, read its content, insert the new link before </body>, and write it back.
                            $current_content = file_get_contents($index_file_path);
                            // Use case-insensitive replace to be more robust against formatting changes (e.g., <BODY>)
                            $new_content = str_ireplace('</body>', $link_html . '</body>', $current_content);
                            file_put_contents($index_file_path, $new_content);
                        }
                        $processed_categories[] = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
                    } else {
                        // The marker file already exists, so this file is already in this category. Skip it.
                        $skipped_categories[] = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');
                    }
                }

                // Build the final success message
                $final_message = '';
                if (!empty($processed_categories)) {
                    $final_message .= 'File added to categories: ' . implode(', ', $processed_categories) . '. ';
                }
                if (!empty($skipped_categories)) {
                    $final_message .= 'File was already in categories: ' . implode(', ', $skipped_categories) . '.';
                }
                
                $message = trim($final_message);
                $message_type = 'success';
                // Reset form after successful submission
                $show_category_form = false; 
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Categorizer</title>
    <!-- CSS STYLES -->
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --success-color: #28a745;
            --error-color: #dc3545;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --border-radius: 8px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #e9ecef;
            color: var(--dark-gray);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        h1 {
            color: var(--dark-gray);
            margin-bottom: 25px;
        }

        p {
            color: #6c757d;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            box-sizing: border-box; /* Important for padding and width calculation */
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="text"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            outline: none;
        }

        button {
            width: 100%;
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        button:hover {
            background-color: var(--primary-hover);
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>File Categorizer</h1>
        <p>Enter a filename to check its existence and assign categories.</p>

        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($show_category_form): ?>
            <!-- Form for Categories -->
            <form id="categoryForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <input type="hidden" name="original_filename" value="<?php echo htmlspecialchars($valid_filename, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="categories">Categories (comma-separated, max 5)</label>
                    <input type="text" id="categories" name="categories" placeholder="e.g., invoices, 2024, urgent" required>
                    <small id="category-hint" style="color: #6c757d; margin-top: 5px; display: block;"></small>
                </div>
                <button type="submit">Create Categories</button>
            </form>
        <?php else: ?>
            <!-- Form for Filename -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="form-group">
                    <label for="filename">Filename</label>
                    <input type="text" id="filename" name="filename" placeholder="e.g., report.pdf" required>
                </div>
                <button type="submit">Check File</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        // Wait for the document to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            const categoryInput = document.getElementById('categories');
            const categoryHint = document.getElementById('category-hint');
            const maxCategories = <?php echo $max_categories; ?>;

            // Only add the event listener if the category input exists on the page
            if (categoryInput) {
                categoryInput.addEventListener('input', function() {
                    // Split input value by comma and filter out empty strings
                    const categories = this.value.split(',').filter(cat => cat.trim() !== '');
                    const count = categories.length;

                    if (count > maxCategories) {
                        categoryHint.textContent = `Warning: You have entered ${count} categories. Only the first ${maxCategories} will be used.`;
                        categoryHint.style.color = 'var(--error-color)';
                    } else if (count > 0) {
                        categoryHint.textContent = `${count} / ${maxCategories} categories entered.`;
                        categoryHint.style.color = '#6c757d'; // Default text color
                    } else {
                        categoryHint.textContent = ''; // Clear hint if input is empty
                    }
                });
            }
        });
    </script>

</body>
</html>
