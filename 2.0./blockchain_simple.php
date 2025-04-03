<?php
session_start();

// Database configuration
define('DB_HOST', $db_config['host']);
define('DB_USER', $db_config['username']);
define('DB_PASS', $db_config['password']);
define('DB_NAME', $db_config['database']);

// JSON directory configuration
define('JSON_DIR', 'JSON');
if (!file_exists(JSON_DIR)) {
    mkdir(JSON_DIR, 0755, true);
}

// Connect to MySQL
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            credits DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user VARCHAR(255) NOT NULL,
            filehash VARCHAR(64) NOT NULL,
            extension VARCHAR(10) NOT NULL,
            btc VARCHAR(64),
            pix VARCHAR(64),
            deposit DECIMAL(10,2) DEFAULT 0,
            is_first BOOLEAN DEFAULT TRUE,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            previous_hash VARCHAR(64),
            block_hash VARCHAR(64) NOT NULL
        )
    ");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get the last block's hash
function getLastBlockHash($pdo) {
    $stmt = $pdo->query("SELECT block_hash FROM blocks ORDER BY id DESC LIMIT 1");
    $lastBlock = $stmt->fetch(PDO::FETCH_ASSOC);
    return $lastBlock ? $lastBlock['block_hash'] : '0';
}

// Function to calculate block hash
function calculateBlockHash($data) {
    return hash('sha256', json_encode($data));
}

// Function to save block data to JSON file
function saveBlockToJson($blockData, $blockHash) {
    $filename = JSON_DIR . '/' . $blockHash . '.json';
    file_put_contents($filename, json_encode($blockData, JSON_PRETTY_PRINT));
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        die("Error: You must be logged in to submit files.");
    }
    
    $user = $_SESSION['username'];
    $btc = $_POST['btc'] ?? '';
    $pix = $_POST['pix'] ?? '';
    $deposit = floatval($_POST['deposit'] ?? 0);
    
    // File upload handling
    $filehash = '';
    $extension = '';
    $is_first = true;
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Check file size (max 10MB)
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            die("File is too large. Maximum size is 10MB.");
        }
        
        // Get file hash and extension
        $fileContent = file_get_contents($_FILES['file']['tmp_name']);
        $filehash = hash('sha256', $fileContent);
        $extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        
        // Check if filehash already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM blocks WHERE filehash = ?");
        $stmt->execute([$filehash]);
        $is_first = $stmt->fetchColumn() == 0;
    }
    
    // Check if user has enough credits
    if ($deposit > 0) {
        $userId = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userCredits = $stmt->fetchColumn();
        
        if ($userCredits < $deposit) {
            die("Error: You don't have enough credits. Your balance: $" . number_format($userCredits, 2));
        }
    }
    
    // Get previous hash
    $previous_hash = getLastBlockHash($pdo);
    
    // Prepare block data
    $timestamp = date('Y-m-d H:i:s');
    $blockData = [
        'user' => $user,
        'filehash' => $filehash,
        'extension' => $extension,
        'btc' => $btc,
        'pix' => $pix,
        'deposit' => $deposit,
        'is_first' => $is_first,
        'timestamp' => $timestamp,
        'previous_hash' => $previous_hash
    ];
    
    // Calculate block hash
    $block_hash = calculateBlockHash($blockData);
    $blockData['block_hash'] = $block_hash;
    
    // Insert into database, update user credits, and save JSON
    try {
        $pdo->beginTransaction();
        
        // Insert block
        $stmt = $pdo->prepare("INSERT INTO blocks (user, filehash, extension, btc, pix, deposit, is_first, timestamp, previous_hash, block_hash) 
                              VALUES (:user, :filehash, :extension, :btc, :pix, :deposit, :is_first, :timestamp, :previous_hash, :block_hash)");
        $stmt->execute($blockData);
        
        // Update user credits if deposit > 0
        if ($deposit > 0) {
            $userId = $_SESSION['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
            $stmt->execute([$deposit, $userId]);
            
            // Update session credits
            $_SESSION['credits'] -= $deposit;
        }
        
        // Save block data to JSON file
        saveBlockToJson($blockData, $block_hash);
        
        $pdo->commit();
        
        $message = "Block added successfully! " . ($is_first ? "This is the first submission of this file." : "This is a new deposit for an existing file.");
        if ($deposit > 0) {
            $message .= " Deposit amount: $" . number_format($deposit, 2);
            $message .= " | Remaining credits: $" . number_format($_SESSION['credits'], 2);
        }
        
        // Calculate total deposits for this file
        $stmt = $pdo->prepare("SELECT SUM(deposit) FROM blocks WHERE filehash = ?");
        $stmt->execute([$filehash]);
        $totalDeposit = $stmt->fetchColumn();
        $message .= " | Total deposits: $" . number_format($totalDeposit, 2);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blockchain</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="file"], input[type="number"] { width: 100%; padding: 8px; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
        .message { margin: 20px 0; padding: 10px; background-color: #f8f8f8; border-left: 4px solid #4CAF50; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        tr:hover { background-color: #f5f5f5; }
        .credit-info { margin-bottom: 20px; padding: 10px; background-color: #e7f3fe; border-left: 4px solid #2196F3; }
        .user-field { padding: 8px; background-color: #f0f0f0; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Simple Blockchain File Submission</h1>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="credit-info">
            Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> | 
            Your credits: $<?php echo number_format($_SESSION['credits'], 2); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <form action="" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="user">User:</label>
            <div class="user-field" id="user"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Not logged in'; ?></div>
            <input type="hidden" name="user" value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="file">File (max 10MB):</label>
            <input type="file" id="file" name="file" accept="*" required>
        </div>
        
        <div class="form-group">
            <label for="deposit">Deposit Amount ($):</label>
            <input type="number" id="deposit" name="deposit" min="0" step="0.01" value="0">
        </div>
        
        <div class="form-group">
            <label for="btc">BTC Address (optional):</label>
            <input type="text" id="btc" name="btc">
        </div>
        
        <div class="form-group">
            <label for="pix">PIX Key (optional):</label>
            <input type="text" id="pix" name="pix">
        </div>
        
        <button type="submit">Submit to Blockchain</button>
    </form>
    
    <h2>Recent Blocks</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>File Hash</th>
                <th>Extension</th>
                <th>Deposit</th>
                <th>Is First</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT id, user, filehash, extension, deposit, is_first, timestamp FROM blocks ORDER BY id DESC LIMIT 10");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['user']}</td>";
                echo "<td title='{$row['filehash']}'>" . substr($row['filehash'], 0, 8) . "...</td>";
                echo "<td>{$row['extension']}</td>";
                echo "<td>$" . number_format($row['deposit'], 2) . "</td>";
                echo "<td>" . ($row['is_first'] ? 'Yes' : 'No') . "</td>";
                echo "<td>{$row['timestamp']}</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
    
    <h2>File Deposit Totals</h2>
    <table>
        <thead>
            <tr>
                <th>File Hash</th>
                <th>Total Deposits</th>
                <th>First Submission</th>
                <th>Last Submission</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("
                SELECT 
                    filehash,
                    SUM(deposit) as total_deposit,
                    MIN(timestamp) as first_submission,
                    MAX(timestamp) as last_submission
                FROM blocks 
                GROUP BY filehash
                ORDER BY last_submission DESC
                LIMIT 10
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td title='{$row['filehash']}'>" . substr($row['filehash'], 0, 8) . "...</td>";
                echo "<td>$" . number_format($row['total_deposit'], 2) . "</td>";
                echo "<td>{$row['first_submission']}</td>";
                echo "<td>{$row['last_submission']}</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</body>
</html>