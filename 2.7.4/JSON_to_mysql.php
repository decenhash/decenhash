<?php
// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'decenhash';

// Connect to MySQL
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Process folder input
$folder = '';
$message = '';
$tableCreated = false;
$importedFiles = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['folder'])) {
    $folder = rtrim($_POST['folder'], '/') . '/';
} elseif (isset($_GET['folder']) && !empty($_GET['folder'])) {
    $folder = rtrim($_GET['folder'], '/') . '/';
}

if (!empty($folder)) {
    if (!is_dir($folder)) {
        $message = "Error: The folder '$folder' doesn't exist.";
    } else {
        // Get all JSON files in the folder
        $jsonFiles = glob($folder . '*.json');
        
        if (empty($jsonFiles)) {
            $message = "No JSON files found in '$folder'";
        } else {
            // Read the first JSON file to determine table structure
            $firstFile = reset($jsonFiles);
            $jsonContent = file_get_contents($firstFile);
            $data = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $message = "Error parsing JSON file: " . json_last_error_msg();
            } else {
                // Create table name based on folder name
                $tableName = 'import_' . preg_replace('/[^a-z0-9_]/i', '_', basename($folder));
                
                // Prepare SQL to create table
                $columns = [];
                $columns[] = "`id` INT AUTO_INCREMENT PRIMARY KEY";
                
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $type = 'TEXT';
                        if (is_int($value)) {
                            $type = 'INT';
                        } elseif (is_float($value)) {
                            $type = 'FLOAT';
                        } elseif (is_bool($value)) {
                            $type = 'BOOLEAN';
                        }
                        $columns[] = "`$key` $type";
                    }
                }
                
                $createTableSQL = "CREATE TABLE IF NOT EXISTS `$tableName` (" . implode(', ', $columns) . ")";
                
                try {
                    $pdo->exec($createTableSQL);
                    $tableCreated = true;
                    $message .= "Table '$tableName' created successfully.<br>";
                    
                    // Import all JSON files
                    foreach ($jsonFiles as $file) {
                        $jsonContent = file_get_contents($file);
                        $data = json_decode($jsonContent, true);
                        
                        if (is_array($data)) {
                            $columns = array_keys($data);
                            $values = array_values($data);
                            
                            $placeholders = array_fill(0, count($values), '?');
                            $insertSQL = "INSERT INTO `$tableName` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                            
                            $stmt = $pdo->prepare($insertSQL);
                            $stmt->execute($values);
                            $importedFiles++;
                        }
                    }
                    
                    $message .= "Imported $importedFiles JSON files into '$tableName' table.";
                } catch (PDOException $e) {
                    $message = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>JSON to MySQL Importer</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        form { margin: 20px 0; }
        input[type="text"] { padding: 8px; width: 300px; }
        button { padding: 8px 15px; background-color: #337ab7; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #286090; }
    </style>
</head>
<body>
    <div class="container">
        <h1>JSON to MySQL Importer</h1>
        <p>Import all JSON files from a folder into MySQL database 'decenhash'</p>
        
        <form method="post" action="">
            <label for="folder">Folder path:</label><br>
            <input type="text" id="folder" name="folder" value="<?= htmlspecialchars($folder) ?>" required>
            <button type="submit">Import JSON Files</button>
        </form>
        
        <?php if (!empty($message)): ?>
            <div class="message <?= $tableCreated ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($folder) && is_dir($folder)): ?>
            <h2>JSON Files in <?= htmlspecialchars($folder) ?></h2>
            <ul>
                <?php foreach (glob($folder . '*.json') as $file): ?>
                    <li><?= htmlspecialchars(basename($file)) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>