<?php

// Define directory
$usersCountDir = 'users_count';

// Check if directory exists
if (!is_dir($usersCountDir)) {
    die("Error: Directory '$usersCountDir' does not exist.\n");
}

// Get all .txt files from users_count directory
$files = glob($usersCountDir . '/*.txt');

// Array to hold file data for sorting
$fileData = array();

foreach ($files as $filePath) {
    // Get just the filename without path
    $filename = basename($filePath);
    
    // Read the count value from the file
    $count = intval(file_get_contents($filePath));
    
    // Store data for sorting
    $fileData[] = array(
        'filename' => $filename,
        'count' => $count,
        'path' => $filePath
    );
}

// Sort files in descending order by count
usort($fileData, function($a, $b) {
    return $b['count'] - $a['count'];
});

// Output HTML with sorted results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Users Count Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; max-width: 600px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Users Count Results</h1>
    <p>Sorted in descending order</p>
    
    <table>
        <thead>
            <tr>
                <th>Ranking</th>
                <th>Count</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fileData as $file): ?>
            <tr>
                <td>
                    <a href="users/<?= str_replace('.txt', '.json', $file['filename']) ?>" target="_blank">
                        <?= htmlspecialchars($file['filename']) ?>
                    </a>
                </td>
                <td><?= $file['count'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>