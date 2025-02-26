<?php
// Specify the directory
$directory = 'files';

// Check if the directory exists
if (!is_dir($directory)) {
    die("Error: Directory '$directory' does not exist.");
}

// Get all files in the directory
$files = scandir($directory);

// Remove . and .. from the list
$files = array_diff($files, array('.', '..'));

// Start HTML output
echo "<!DOCTYPE html>\n";
echo "<html lang='en'>\n";
echo "<head>\n";
echo "    <meta charset='UTF-8'>\n";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "    <title>Files in '{$directory}' Directory</title>\n";
echo "</head>\n";
echo "<body>\n";
echo "    <h1>Files in '{$directory}' Directory</h1>\n";
echo "    <ul>\n";

// Loop through each file
foreach ($files as $file) {
    $filepath = $directory . '/' . $file;
    
    // Create a URL-encoded version of the file path
    //$encoded_filepath = urlencode($filepath);
    
    // Output the file as a link
    echo "        <li><a href='{$filepath}' target='_blank'>{$file}</a></li>\n";
}

// Close the HTML tags
echo "    </ul>\n";
echo "</body>\n";
echo "</html>";
?>