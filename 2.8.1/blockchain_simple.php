<?php
// Configuration
define('BLOCKS_DIR', 'blocks');
define('DIFFICULTY', 4); // Number of leading zeros required for proof-of-work
define('BLOCK_REWARD', 50); // Mining reward
define('GENESIS_BLOCK', [
    'url' => '',
    'extension' => '',
    'title' => 'Genesis Block',
    'description' => 'The first block in the chain',
    'date' => '2023-01-01 00:00:00',
    'filehash' => '0000000000000000000000000000000000000000000000000000000000000000',
    'blockhash' => '0000000000000000000000000000000000000000000000000000000000000000',
    'nexthash' => '0',
    'deposit' => '0',
    'userbtc' => 'system'
]);

// Initialize blockchain if needed
initBlockchain();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_block':
                handleCreateBlock();
                break;
        }
    }
}

// Initialize blockchain
function initBlockchain() {
    if (!file_exists(BLOCKS_DIR)) {
        mkdir(BLOCKS_DIR, 0755, true);
        // Create genesis block
        file_put_contents(
            BLOCKS_DIR . '/' . GENESIS_BLOCK['blockhash'] . '.json',
            json_encode(GENESIS_BLOCK, JSON_PRETTY_PRINT)
        );
    }
}

// Handle block creation
function handleCreateBlock() {
    global $message;
    
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $userbtc = sanitizeInput($_POST['userbtc'] ?? '');
    $url = sanitizeInput($_POST['url'] ?? '');

    if (empty($title) || empty($url)) {
        $message = "Please fill all required fields!";
        return;
    }

    // Extract hash from URL
    $urlParts = parse_url($url);
    $path = $urlParts['path'] ?? '';
    $filename = pathinfo($path, PATHINFO_FILENAME);
    $extension = pathinfo($path, PATHINFO_EXTENSION);

    // Validate that the URL contains a hash-like filename
    if (!preg_match('/^[a-f0-9]{64}$/i', $filename)) {
        $message = "URL must contain a 64-character hexadecimal hash as the filename!";
        return;
    }

    // Create block data
    $block = [
        'url' => $url,
        'extension' => $extension,
        'title' => $title,
        'description' => $description,
        'date' => date('Y-m-d H:i:s'),
        'filehash' => $filename,
        'userbtc' => $userbtc,
        'blockhash' => '',
        'nexthash' => getLastBlockHash() // Using 'nexthash' consistently
    ];

    // Mine the block (proof-of-work)
    $minedBlock = mineBlock($block);
    
    // Save the block
    $filename = $minedBlock['blockhash'] . '.json';
    $filepath = BLOCKS_DIR . '/' . $filename;
    
    if (!file_exists($filepath)) {
        file_put_contents($filepath, json_encode($minedBlock, JSON_PRETTY_PRINT));
        $message = "Block successfully mined and saved!";
    } else {
        $message = "Block already exists!";
    }
}

// Mine block (proof-of-work)
function mineBlock($block) {
    $difficulty_prefix = str_repeat('0', DIFFICULTY);
    $attempts = 0;
    
    while (true) {
        $block['attempts'] = $attempts;
        $blockData = $block;
        unset($blockData['blockhash']);
        $blockJson = json_encode($blockData);
        $hash = hash('sha256', $blockJson);
        
        if (substr($hash, 0, DIFFICULTY) === $difficulty_prefix) {
            $block['blockhash'] = $hash;
            return $block;
        }
        
        $attempts++;
    }
}

// Helper functions
function getLastBlockHash() {
    $files = scandir(BLOCKS_DIR, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (in_array($file, ['.', '..'])) continue;
        $content = file_get_contents(BLOCKS_DIR . '/' . $file);
        $block = json_decode($content, true);
        return $block['blockhash'];
    }
    return '0';
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function displayBlocks() {
    $files = scandir(BLOCKS_DIR);
    $blocks = [];
    
    foreach ($files as $file) {
        if (in_array($file, ['.', '..'])) continue;
        
        $content = file_get_contents(BLOCKS_DIR . '/' . $file);
        $blocks[] = json_decode($content, true);
    }
    
    // Sort by date (newest first)
    usort($blocks, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return $blocks;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional JSON Blockchain</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        header h1 {
            margin: 0;
            text-align: center;
            font-size: 2.5rem;
        }
        
        header p {
            text-align: center;
            margin: 10px 0 0;
            opacity: 0.8;
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .card-title {
            margin-top: 0;
            color: var(--primary-color);
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        textarea:focus {
            border-color: var(--secondary-color);
            outline: none;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .error {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>JSON Blockchain</h1>
            <p>A professional decentralized blockchain implementation using JSON files</p>
        </div>
    </header>
    
    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false || strpos($message, 'valid') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2 class="card-title">Create New Block</h2>
            <form id="blockForm" method="post">
                <input type="hidden" name="action" value="create_block">
                
                <div class="form-group">
                    <label for="url">File URL* (must contain a 64-character hash as the filename):</label>
                    <input type="text" id="url" name="url" required placeholder="http://example.com/data/[64-character-hash].ext">
                </div>
                
                <div class="form-group">
                    <label for="title">Title*:</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="userbtc">User BTC Address (optional):</label>
                    <input type="text" id="userbtc" name="userbtc" placeholder="1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa">
                </div>
                
                <button type="submit" class="btn btn-success">Mine Block</button>
            </form>
        </div>
        
        <div class="card">
            <h2 class="card-title">Blockchain Statistics</h2>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">Total Blocks</div>
                    <div class="stat-value"><?php echo count(displayBlocks()); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Difficulty</div>
                    <div class="stat-value"><?php echo DIFFICULTY; ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Last Block Hash</div>
                    <div class="stat-value" style="font-size: 0.9rem; word-break: break-all;">
                        <?php echo getLastBlockHash(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>