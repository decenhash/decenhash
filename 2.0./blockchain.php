<?php
session_start();

include 'db_config.php';

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
    <title>Secure Blockchain File Registry | DecenHash</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ffffff;
            --secondary: #f8f9fa;
            --accent: #1976d2;
            --accent-light: #e3f2fd;
            --text-primary: #212121;
            --text-secondary: #757575;
            --success: #388e3c;
            --warning: #ffa000;
            --error: #d32f2f;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif;
            background-color: var(--primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background-color: var(--accent);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo i {
            color: white;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-credits {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        h1, h2, h3 {
            color: var(--accent);
            margin-bottom: 20px;
            font-weight: 600;
        }

        h1 {
            font-size: 2rem;
            border-left: 4px solid var(--accent);
            padding-left: 15px;
        }

        h2 {
            font-size: 1.5rem;
            margin-top: 40px;
            position: relative;
            padding-bottom: 10px;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent);
        }

        .card {
            background-color: var(--secondary);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            color: var(--text-primary);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-light);
        }

        .user-field {
            padding: 12px 15px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            color: var(--text-primary);
            font-size: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn:hover {
            background-color: #1565c0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .message {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .message.success {
            background-color: #e8f5e9;
            border-left: 4px solid var(--success);
            color: var(--success);
        }

        .message.error {
            background-color: #ffebee;
            border-left: 4px solid var(--error);
            color: var(--error);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: var(--accent-light);
            color: var(--accent);
            font-weight: 600;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.success {
            background-color: #e8f5e9;
            color: var(--success);
        }

        .status-badge.warning {
            background-color: #fff8e1;
            color: var(--warning);
        }

        .file-hash {
            font-family: 'Courier New', monospace;
            color: var(--accent);
            cursor: pointer;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .container {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">
                <i class="fas fa-link"></i>
                <span>DecenHash</span>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-info">
                    <div class="user-credits">
                        <i class="fas fa-coins"></i>
                        <span>$<?php echo number_format($_SESSION['credits'], 2); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container">
        <h1>Blockchain File Registry</h1>
        <p style="color: var(--text-secondary); margin-bottom: 30px;">
            Secure and verifiable file registration on the blockchain
        </p>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'Error:') === 0 ? 'error' : 'success'; ?>">
                <i class="fas <?php echo strpos($message, 'Error:') === 0 ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3><i class="fas fa-file-upload"></i> Register New File</h3>
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="user"><i class="fas fa-user"></i> User</label>
                    <div class="user-field">
                        <?php if (isset($_SESSION['username'])): ?>
                            <i class="fas fa-lock" style="margin-right: 8px; color: var(--accent);"></i>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        <?php else: ?>
                            Not authenticated
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="user" value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="file"><i class="fas fa-file"></i> File</label>
                    <input type="file" id="file" name="file" class="form-control" required>
                    <small style="display: block; margin-top: 8px; color: var(--text-secondary);">Max 10MB. File contents will be hashed.</small>
                </div>
                
                <div class="form-group">
                    <label for="deposit"><i class="fas fa-money-bill-wave"></i> Deposit Amount ($)</label>
                    <input type="number" id="deposit" name="deposit" class="form-control" min="0" step="0.01" value="0">
                </div>
                
                <div class="form-group">
                    <label for="btc"><i class="fab fa-bitcoin"></i> BTC Address (Optional)</label>
                    <input type="text" id="btc" name="btc" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="pix"><i class="fas fa-qrcode"></i> PIX Key (Optional)</label>
                    <input type="text" id="pix" name="pix" class="form-control">
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-link"></i> Register File
                </button>
            </form>
        </div>

        <h2><i class="fas fa-cubes"></i> Recent Blocks</h2>
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>File Hash</th>
                        <th>Type</th>
                        <th>Deposit</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Make sure $pdo is defined before using it
                    if (isset($pdo)) {
                        $stmt = $pdo->query("SELECT id, user, filehash, extension, deposit, is_first, timestamp FROM blocks ORDER BY id DESC LIMIT 10");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td>#{$row['id']}</td>";
                            echo "<td>{$row['user']}</td>";
                            echo "<td class='file-hash' title='{$row['filehash']}'>" . substr($row['filehash'], 0, 8) . "...</td>";
                            echo "<td>.{$row['extension']}</td>";
                            echo "<td>$" . number_format($row['deposit'], 2) . "</td>";
                            echo "<td><span class='status-badge " . ($row['is_first'] ? 'success' : 'warning') . "'>" . ($row['is_first'] ? 'Original' : 'Update') . "</span></td>";
                            echo "<td>{$row['timestamp']}</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <h2><i class="fas fa-file-invoice-dollar"></i> File Deposit Totals</h2>
        <div class="card">
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
                    if (isset($pdo)) {
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
                            echo "<td class='file-hash' title='{$row['filehash']}'>" . substr($row['filehash'], 0, 8) . "...</td>";
                            echo "<td>$" . number_format($row['total_deposit'], 2) . "</td>";
                            echo "<td>{$row['first_submission']}</td>";
                            echo "<td>{$row['last_submission']}</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Add click-to-copy functionality for file hashes
        document.querySelectorAll('.file-hash').forEach(el => {
            el.addEventListener('click', function() {
                const hash = this.getAttribute('title');
                navigator.clipboard.writeText(hash).then(() => {
                    const originalText = this.innerText;
                    this.innerText = 'Copied!';
                    setTimeout(() => {
                        this.innerText = originalText;
                    }, 2000);
                });
            });
        });

        // Form submission loading indicator
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', () => {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            });
        }
    </script>
</body>
</html>