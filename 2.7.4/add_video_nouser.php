<?php
// Configuration
$jsonDir = 'json_videos';
$baseFilename = 'videos';
$maxSizeKB = 20; // 20KB max file size
$maxEntries = 100; // 100 entries max per file

// Create directory if it doesn't exist
if (!file_exists($jsonDir)) {
    mkdir($jsonDir, 0755, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $videoUrl = filter_input(INPUT_POST, 'video_url', FILTER_SANITIZE_URL);
    $thumbnailUrl = filter_input(INPUT_POST, 'thumbnail_url', FILTER_SANITIZE_URL);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    
    if ($videoUrl && $thumbnailUrl && $title) {
        $newEntry = [
            'title' => $title,
            'filename' => basename($videoUrl),
            'video' => $videoUrl,
            'thumbnail' => $thumbnailUrl,
            'timestamp' => time()
        ];
        
        addEntryToJson($newEntry);
        $success = "Video added successfully!";
    } else {
        $error = "Please fill all fields with valid data!";
    }
}

// Function to find the current JSON file to use
function getCurrentJsonFile() {
    global $jsonDir, $baseFilename, $maxSizeKB, $maxEntries;
    
    $counter = 0;
    $currentFile = "$jsonDir/$baseFilename.json";
    
    while (true) {
        // If file doesn't exist or is empty, we can use it
        if (!file_exists($currentFile) || filesize($currentFile) === 0) {
            return $currentFile;
        }
        
        // Check if current file is within limits
        $content = file_get_contents($currentFile);
        $data = json_decode($content, true) ?: [];
        
        $fileSizeKB = filesize($currentFile) / 1024;
        $entryCount = count($data);
        
        if ($fileSizeKB < $maxSizeKB && $entryCount < $maxEntries) {
            return $currentFile;
        }
        
        // Move to next file
        $counter++;
        $currentFile = "$jsonDir/{$baseFilename}_$counter.json";
    }
}

// Function to add entry to JSON file
function addEntryToJson($newEntry) {
    $jsonFile = getCurrentJsonFile();
    
    // Read existing data
    $data = [];
    if (file_exists($jsonFile)) {
        $content = file_get_contents($jsonFile);
        $data = json_decode($content, true) ?: [];
    }
    
    // Add new entry
    $data[] = $newEntry;
    
    // Save back to file
    file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Manager</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="url"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #1e90ff; /* Blue color */
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .btn:hover {
            background-color: #187bcd;
        }
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div align="right"><a href="videos.html" style="color: #333;">Videos</a></div>
    <div class="container">
        <h1>Video Manager</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="title">Video Title:</label>
                <input type="text" id="title" name="title" required>
            </div>
            
            <div class="form-group">
                <label for="video_url">Video URL:</label>
                <input type="url" id="video_url" name="video_url" required placeholder="https://example.com/videos/video1.mp4">
            </div>
            
            <div class="form-group">
                <label for="thumbnail_url">Thumbnail URL:</label>
                <input type="url" id="thumbnail_url" name="thumbnail_url" required placeholder="https://example.com/thumbnails/thumb1.jpg">
            </div>
            
            <button type="submit" class="btn">Add Video</button>
        </form>
    </div>
</body>
</html>