<?php
// Define file paths
$btcFile = 'btc.txt';
$descFile = 'description.txt';

// Initialize messages and flags
$btcMessage = '';
$descMessage = '';
$btcContent = '';
$descContent = '';
$showBtcField = true;
$showDescField = true;

// Function to read file content and check size
function getFileInfo($filename) {
    $content = '';
    $hasContent = false;
    
    if (file_exists($filename)) {
        $size = filesize($filename);
        if ($size > 1) {
            $content = file_get_contents($filename);
            $hasContent = true;
        }
    }
    
    return [
        'content' => $content,
        'hasContent' => $hasContent
    ];
}

// Get current file info
$btcInfo = getFileInfo($btcFile);
$descInfo = getFileInfo($descFile);
$btcContent = $btcInfo['content'];
$descContent = $descInfo['content'];
$showBtcField = !$btcInfo['hasContent'];
$showDescField = !$descInfo['hasContent'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process BTC field
    if (isset($_POST['btc']) && $showBtcField) {
        $btc = trim($_POST['btc']);
        
        if (strlen($btc) <= 1) {
            $btcMessage = "BTC input must be longer than 1 character.";
        } else {
            // Open file for writing
            $file = fopen($btcFile, 'w');
            if ($file) {
                fwrite($file, $btc);
                fclose($file);
                $btcMessage = "BTC data saved successfully.";
                $btcContent = $btc;
                $showBtcField = false;
            } else {
                $btcMessage = "Error opening BTC file for writing.";
            }
        }
    }

    // Process Description field
    if (isset($_POST['description']) && $showDescField) {
        $description = trim($_POST['description']);
        
        if (strlen($description) <= 1) {
            $descMessage = "Description must be longer than 1 character.";
        } else {
            // Open file for writing
            $file = fopen($descFile, 'w');
            if ($file) {
                fwrite($file, $description);
                fclose($file);
                $descMessage = "Description saved successfully.";
                $descContent = $description;
                $showDescField = false;
            } else {
                $descMessage = "Error opening description file for writing.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BTC and Description Saver</title>
    <style>
        .file-content {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
            background-color: #f9f9f9;
        }
        .message {
            color: #d9534f;
            margin: 5px 0;
        }
        .success {
            color: #5cb85c;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <h1>BTC and Server description</h1>
    
    <form method="post" action="">
        <!-- BTC Section -->
        <div>
            <?php if ($showBtcField): ?>
                <label for="btc">BTC:</label>
                <input type="text" id="btc" name="btc">
                <?php if ($btcMessage): ?>
                    <div class="message <?= strpos($btcMessage, 'successfully') !== false ? 'success' : '' ?>">
                        <?= $btcMessage ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="file-content">
                    <strong>BTC:</strong><br>
                    <?= htmlspecialchars($btcContent) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Description Section -->
        <div>
            <?php if ($showDescField): ?>
                <label for="description">Description:</label>
                <textarea id="description" name="description"></textarea>
                <?php if ($descMessage): ?>
                    <div class="message <?= strpos($descMessage, 'successfully') !== false ? 'success' : '' ?>">
                        <?= $descMessage ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="file-content">
                    <strong>Description:</strong><br>
                    <?= htmlspecialchars($descContent) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($showBtcField || $showDescField): ?>
            <button type="submit">Save</button>
        <?php endif; ?>
    </form>
</body>
</html>