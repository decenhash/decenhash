<?php
// Start session to access session variables
session_start();

// Configuration
define('MAX_JSON_SIZE_KB', 20); // Maximum size per JSON file in KB
define('JSON_DIR', 'data_json/');
define('PRIMARY_JSON_FILE', JSON_DIR . 'data.json');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure directory exists
    if (!file_exists(JSON_DIR)) {
        mkdir(JSON_DIR, 0777, true);
    }
    
    // Process media information
    $artist = str_replace(' ', '_', $_POST['artist']);
    $title = str_replace(' ', '_', $_POST['title']);
    $filename = $artist . '-' . $title;
    
    // Handle media URL
    $mediaPath = '';
    $mediaType = '';
    if (!empty($_POST['media_url'])) {
        $mediaUrl = filter_var($_POST['media_url'], FILTER_SANITIZE_URL);
        
        // Validate URL and extract file extension
        if (filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
            $path = parse_url($mediaUrl, PHP_URL_PATH);
            $mediaExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            
            // Validate file type
            $allowedTypes = ['mp3', 'mp4', 'jpg'];
            if (!in_array($mediaExt, $allowedTypes)) {
                $error = "Invalid file type in URL. Only MP3 and MP4 files are allowed.";
            } else {
                $mediaPath = $mediaUrl;
                $mediaType = ($mediaExt === 'mp3') ? 'audio' : 'video';
            }
        } else {
            $error = "Invalid media URL provided.";
        }
    } else {
        $error = "Media URL is required.";
    }
    
    // Handle cover art URL
    $thumbPath = '';
    if (empty($error) && !empty($_POST['cover_url'])) {
        $coverUrl = filter_var($_POST['cover_url'], FILTER_SANITIZE_URL);
        
        if (filter_var($coverUrl, FILTER_VALIDATE_URL)) {
            $path = parse_url($coverUrl, PHP_URL_PATH);
            $coverExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            
            $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($coverExt, $allowedImageTypes)) {
                $error = "Invalid image type in URL. Only JPG, PNG, and GIF are allowed.";
            } else {
                $thumbPath = $coverUrl;
            }
        } else {
            $error = "Invalid cover art URL provided.";
        }
    }
    
    // If no errors, update JSON
    if (empty($error)) {
        // Load existing data from primary file
        $jsonData = [];
        if (file_exists(PRIMARY_JSON_FILE)) {
            $jsonData = json_decode(file_get_contents(PRIMARY_JSON_FILE), true);
        }
        
        // Create new entry with user field
        $newEntry = [
            'id' => countAllEntries() + 1,
            'filename' => $filename . '.' . $mediaExt,
            'filePath' => $mediaPath,
            'title' => $title,
            'artist' => str_replace('_', ' ', $artist),
            'thumbPath' => $thumbPath,
            'type' => $mediaType,
            'user' => isset($_SESSION['user']) ? $_SESSION['user'] : '',
            'timestamp' => time()
        ];
        
        // Add to JSON array
        $jsonData[] = $newEntry;
        
        // Check if primary file exceeds size limit
        $currentSize = file_exists(PRIMARY_JSON_FILE) ? filesize(PRIMARY_JSON_FILE) : 0;
        $estimatedNewSize = strlen(json_encode($jsonData));
        
        if ($estimatedNewSize / 1024 > MAX_JSON_SIZE_KB) {
            // Split the data
            $splitFiles = splitJsonData($jsonData);
            
            // Save the split files
            foreach ($splitFiles as $index => $data) {
                $filename = ($index === 0) ? PRIMARY_JSON_FILE : JSON_DIR . 'data_' . $index . '.json';
                file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
            }
            
            $success = "Media added successfully! Data was split into multiple files.";
        } else {
            // Save to primary file
            file_put_contents(PRIMARY_JSON_FILE, json_encode($jsonData, JSON_PRETTY_PRINT));
            $success = "Media added successfully!";
        }
    }
}

/**
 * Splits JSON data when it exceeds size limit
 */
function splitJsonData($data) {
    $half = ceil(count($data) / 2);
    return [
        array_slice($data, 0, $half),
        array_slice($data, $half)
    ];
}

/**
 * Counts all entries across all JSON files
 */
function countAllEntries() {
    $count = 0;
    
    // Count from primary file
    if (file_exists(PRIMARY_JSON_FILE)) {
        $data = json_decode(file_get_contents(PRIMARY_JSON_FILE), true);
        $count += is_array($data) ? count($data) : 0;
    }
    
    // Count from split files
    $index = 1;
    while (true) {
        $filename = JSON_DIR . 'data_' . $index . '.json';
        if (!file_exists($filename)) {
            break;
        }
        
        $data = json_decode(file_get_contents($filename), true);
        $count += is_array($data) ? count($data) : 0;
        $index++;
    }
    
    return $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media URL Collector</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="url"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .success {
            background-color: #2ecc71;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .error {
            background-color: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .file-info {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div align="right"><a href="music.html" style="color: #333;">Music</a></div>
    <div class="container">
        <h1>Media URL Collector</h1>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="" method="post">
            <div class="form-group">
                <label for="artist">Artist Name</label>
                <input type="text" id="artist" name="artist" required>
                <div class="file-info">Spaces will be replaced with underscores (_)</div>
            </div>
            
            <div class="form-group">
                <label for="title">Track/Video Title</label>
                <input type="text" id="title" name="title" required>
                <div class="file-info">Spaces will be replaced with underscores (_)</div>
            </div>
            
            <div class="form-group">
                <label for="media_url">Media File URL (MP3 or MP4)</label>
                <input type="url" id="media_url" name="media_url" placeholder="https://example.com/path/to/file.mp3" required>
                <div class="file-info">Must be a direct URL to an MP3 or MP4 file</div>
            </div>
            
            <div class="form-group">
                <label for="cover_url">Cover Art URL (Optional)</label>
                <input type="url" id="cover_url" name="cover_url" placeholder="https://example.com/path/to/image.jpg">
                <div class="file-info">Must be a direct URL to a JPG, PNG, or GIF image</div>
            </div>
            
            <button type="submit">Add Media</button>
        </form>
    </div>
</body>
</html>