<?php
/**
 * Simple Blockchain Application
 * 
 * Features:
 * - Add new blocks with URL hash verification
 * - View entire blockchain
 * - Verify blockchain integrity
 * - Display basic statistics
 */

// ==============================================
// Configuration and Initialization
// ==============================================

define('BLOCKS_DIR', __DIR__ . '/blocks/');
define('APP_NAME', 'Blockchain Manager');
define('VERSION', '1.0.0');

// Ensure blocks directory exists
if (!file_exists(BLOCKS_DIR)) {
    mkdir(BLOCKS_DIR, 0755, true);
}

// ==============================================
// Business Logic Functions
// ==============================================

/**
 * Creates a new block in the blockchain
 */
function createBlock($title, $description, $url, $userbtc) {
    try {
        // Extract filename without extension from URL
        $path = parse_url($url, PHP_URL_PATH);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        // Validate filename is a valid SHA-256 hash
        if (!preg_match('/^[a-f0-9]{64}$/', $filename)) {
            throw new Exception("Filename must be a valid SHA-256 hash (64 hexadecimal characters).");
        }
        
        // Fetch file content
        $fileContent = file_get_contents($url);
        if ($fileContent === false) {
            throw new Exception("Failed to fetch file from URL.");
        }
        
        // Calculate hash of the actual file content
        $fileHash = hash('sha256', $fileContent);
        
        // Verify filename matches file content hash
        if ($filename !== $fileHash) {
            throw new Exception("File hash mismatch. Expected $filename but got $fileHash");
        }
        
        // Get file size and format it
        $fileSize = strlen($fileContent);
        $formattedSize = formatFileSize($fileSize);

        $deposit = 0;
        
        // Prepare block data
        $blockData = [
            'extension' => $extension,
            'title' => $title,
            'description' => $description,
            'date' => date('Y-m-d H:i:s'),
            'filehash' => $fileHash,
            'filesize' => $formattedSize,
            'userbtc' => $userbtc,
            'url' => $url,
            'deposit' => $deposit,   
            'previous_hash' => getLatestBlock() ? getLatestBlock()['blockhash'] : '0' // Genesis block
        ];
        
        // Calculate block hash
        $blockData['blockhash'] = hash('sha256', json_encode($blockData));
        
        // Save block if file doesn't exist
        $filename = BLOCKS_DIR . $fileHash . '.json';
        if (file_exists($filename)) {
            throw new Exception("Block already exists with this file hash.");
        }
        
        if (!file_put_contents($filename, json_encode($blockData, JSON_PRETTY_PRINT))) {
            throw new Exception("Failed to save block to disk.");
        }
        
        return true;
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Format file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * Gets the latest block in the chain
 */
function getLatestBlock() {
    $blocks = glob(BLOCKS_DIR . '*.json');
    if (empty($blocks)) {
        return null;
    }
    
    // Sort by modification time (newest first)
    usort($blocks, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    return json_decode(file_get_contents($blocks[0]), true);
}


/**
 * Gets all blocks in the chain
 */
function getAllBlocks() {
    $blocks = glob(BLOCKS_DIR . '*.json');
    $blockData = [];
    
    foreach ($blocks as $blockFile) {
        $blockData[] = json_decode(file_get_contents($blockFile), true);
    }
    
    // Sort by date (oldest first)
    usort($blockData, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    return $blockData;
}

/**
 * Verifies the integrity of the blockchain
 */
function verifyBlockchain() {
    $blocks = getAllBlocks();
    $results = [
        'total_blocks' => count($blocks),
        'valid_blocks' => 0,
        'invalid_blocks' => 0,
        'details' => []
    ];
    
    $previousHash = '0';
    
    foreach ($blocks as $block) {
        $isValid = true;
        $issues = [];
        
        // Verify block hash
        $tempBlock = $block;
        unset($tempBlock['blockhash']);
        $calculatedHash = hash('sha256', json_encode($tempBlock));
        
        if ($calculatedHash !== $block['blockhash']) {
            $isValid = false;
            $issues[] = "Block hash mismatch";
        }
        
        // Verify previous hash link
        if ($block['previous_hash'] !== $previousHash) {
            $isValid = false;
            $issues[] = "Previous hash mismatch (Expected: $previousHash, Found: {$block['previous_hash']})";
        }
        
        // Verify file hash matches filename
        $path = parse_url($block['url'], PHP_URL_PATH);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        
        if ($filename !== $block['filehash']) {
            $isValid = false;
            $issues[] = "File hash mismatch";
        }
        
        if ($isValid) {
            $results['valid_blocks']++;
        } else {
            $results['invalid_blocks']++;
        }
        
        $results['details'][] = [
            'blockhash' => $block['blockhash'],
            'is_valid' => $isValid,
            'issues' => $issues
        ];
        
        $previousHash = $block['blockhash'];
    }
    
    return $results;
}

/**
 * Gets blockchain statistics
 */
function getBlockchainStats() {
    $blocks = getAllBlocks();
    $stats = [
        'total_blocks' => count($blocks),
        'first_block_date' => null,
        'last_block_date' => null,
        'users' => [],
        'extensions' => []
    ];
    
    if (!empty($blocks)) {
        $stats['first_block_date'] = end($blocks)['date'];
        $stats['last_block_date'] = $blocks[0]['date'];
        
        foreach ($blocks as $block) {
            if (!empty($block['userbtc'])) {
                if (!isset($stats['users'][$block['userbtc']])) {
                    $stats['users'][$block['userbtc']] = 0;
                }
                $stats['users'][$block['userbtc']]++;
            }
            
            if (!empty($block['extension'])) {
                if (!isset($stats['extensions'][$block['extension']])) {
                    $stats['extensions'][$block['extension']] = 0;
                }
                $stats['extensions'][$block['extension']]++;
            }
        }
    }
    
    return $stats;
}

// ==============================================
// Request Handling
// ==============================================

$current_section = $_GET['section'] ?? 'add';
$action = $_POST['action'] ?? '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'add_block':
                $title = $_POST['title'] ?? '';
                $description = $_POST['description'] ?? '';
                $url = $_POST['url'] ?? '';
                $userbtc = $_POST['userbtc'] ?? '';
                
                if (empty($title) || empty($description) || empty($url)) {
                    throw new Exception("All required fields must be filled.");
                }
                
                if (createBlock($title, $description, $url, $userbtc)) {
                    $success = "Block successfully added to the blockchain!";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ==============================================
// Data Preparation for Views
// ==============================================

$blockchain_stats = getBlockchainStats();
$verification_results = verifyBlockchain();
$all_blocks = getAllBlocks();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .blockchain-card {
            transition: transform 0.2s;
        }
        .blockchain-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .hash-value {
            font-family: monospace;
            font-size: 0.85rem;
            word-break: break-all;
            color: #6c757d;
        }
        .valid-badge {
            background-color: #198754;
        }
        .invalid-badge {
            background-color: #dc3545;
        }
        .stat-card {
            border-left: 4px solid #0d6efd;
        }
        .url-example {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4">
                    <i class="bi bi-link-45deg"></i> <?php echo APP_NAME; ?>
                    <small class="text-muted fs-6">v<?php echo VERSION; ?></small>
                </h1>
                <p class="lead">A simple blockchain implementation with file hash verification</p>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-menu-up"></i> Navigation
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="?section=add" class="list-group-item list-group-item-action <?php echo $current_section === 'add' ? 'active' : ''; ?>">
                            <i class="bi bi-plus-circle"></i> Add Block
                        </a>
                        <a href="?section=view" class="list-group-item list-group-item-action <?php echo $current_section === 'view' ? 'active' : ''; ?>">
                            <i class="bi bi-list-ul"></i> View Blockchain
                        </a>
                        <a href="?section=verify" class="list-group-item list-group-item-action <?php echo $current_section === 'verify' ? 'active' : ''; ?>">
                            <i class="bi bi-shield-check"></i> Verify Integrity
                        </a>
                        <a href="?section=stats" class="list-group-item list-group-item-action <?php echo $current_section === 'stats' ? 'active' : ''; ?>">
                            <i class="bi bi-graph-up"></i> Statistics
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-info text-white">
                        <i class="bi bi-info-circle"></i> Quick Stats
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Blocks
                            <span class="badge bg-primary rounded-pill"><?php echo $blockchain_stats['total_blocks']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Valid Blocks
                            <span class="badge bg-success rounded-pill"><?php echo $verification_results['valid_blocks']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Unique Users
                            <span class="badge bg-primary rounded-pill"><?php echo count($blockchain_stats['users']); ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-md-9">
                <?php if ($current_section === 'add'): ?>
                    <!-- Add Block Section -->
                    <div class="card blockchain-card">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-plus-lg"></i> Add New Block
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="action" value="add_block">
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="url" class="form-label">File URL</label>
                                    <input type="text" class="form-control" id="url" name="url" required>
                                    <div class="form-text">
                                        The filename must be the SHA-256 hash of the file contents. 
                                        Example: <span class="url-example">http://example.com/files/0a1d50af8f953bb8116a97437b57b56b83c590865ec2456f12c40c927f9eb56f.jpg</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="userbtc" class="form-label">BTC Address (optional)</label>
                                    <input type="text" class="form-control" id="userbtc" name="userbtc" placeholder="1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa">
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save"></i> Create Block
                                </button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($current_section === 'view'): ?>
                    <!-- View Blockchain Section -->
                    <div class="card blockchain-card">
                        <div class="card-header bg-primary text-white">
                            <i class="bi bi-list-ul"></i> Blockchain Contents
                            <span class="badge bg-light text-dark float-end"><?php echo count($all_blocks); ?> blocks</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($all_blocks)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> The blockchain is empty. Add your first block!
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="blockchainAccordion">
                                    <?php foreach ($all_blocks as $index => $block): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>">
                                                    Block #<?php echo $index + 1; ?>: <?php echo htmlspecialchars($block['title']); ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($block['date']); ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#blockchainAccordion">
                                                <div class="accordion-body">
                                                    <p><?php echo htmlspecialchars($block['description']); ?></p>
                                                    
                                                    <div class="mb-2">
                                                        <strong>File Hash:</strong>
                                                        <div class="hash-value"><?php echo htmlspecialchars($block['filehash']); ?></div>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong>Block Hash:</strong>
                                                        <div class="hash-value"><?php echo htmlspecialchars($block['blockhash']); ?></div>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong>Previous Hash:</strong>
                                                        <div class="hash-value"><?php echo htmlspecialchars($block['previous_hash']); ?></div>
                                                    </div>
                                                    
                                                    <div class="mb-2">
                                                        <strong>URL:</strong>
                                                        <a href="<?php echo htmlspecialchars($block['url']); ?>" target="_blank"><?php echo htmlspecialchars($block['url']); ?></a>
                                                    </div>
                                                    
                                                    <?php if (!empty($block['userbtc'])): ?>
                                                        <div class="mb-2">
                                                            <strong>BTC Address:</strong>
                                                            <?php echo htmlspecialchars($block['userbtc']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="mt-3">
                                                        <span class="badge bg-info"><?php echo strtoupper(htmlspecialchars($block['extension'])); ?></span>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($block['date']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($current_section === 'verify'): ?>
                    <!-- Verify Blockchain Section -->
                    <div class="card blockchain-card">
                        <div class="card-header bg-warning text-dark">
                            <i class="bi bi-shield-check"></i> Blockchain Verification
                        </div>
                        <div class="card-body">
                            <div class="alert alert-<?php echo $verification_results['invalid_blocks'] > 0 ? 'warning' : 'success'; ?>">
                                <h5 class="alert-heading">
                                    <?php if ($verification_results['total_blocks'] === 0): ?>
                                        No blocks to verify
                                    <?php elseif ($verification_results['invalid_blocks'] > 0): ?>
                                        <i class="bi bi-exclamation-triangle"></i> Found <?php echo $verification_results['invalid_blocks']; ?> invalid blocks
                                    <?php else: ?>
                                        <i class="bi bi-check-circle"></i> All blocks are valid
                                    <?php endif; ?>
                                </h5>
                                <p>
                                    Scanned <?php echo $verification_results['total_blocks']; ?> blocks.
                                    <?php if ($verification_results['total_blocks'] > 0): ?>
                                        Chain integrity is <?php echo $verification_results['invalid_blocks'] > 0 ? 'compromised' : 'intact'; ?>.
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <?php if ($verification_results['total_blocks'] > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Block Hash</th>
                                                <th>Status</th>
                                                <th>Issues</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($verification_results['details'] as $detail): ?>
                                                <tr>
                                                    <td class="hash-value" style="max-width: 200px;"><?php echo htmlspecialchars($detail['blockhash']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $detail['is_valid'] ? 'valid-badge' : 'invalid-badge'; ?>">
                                                            <?php echo $detail['is_valid'] ? 'Valid' : 'Invalid'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($detail['issues'])): ?>
                                                            <ul class="mb-0">
                                                                <?php foreach ($detail['issues'] as $issue): ?>
                                                                    <li><?php echo htmlspecialchars($issue); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <span class="text-muted">None</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($current_section === 'stats'): ?>
                    <!-- Statistics Section -->
                    <div class="card blockchain-card">
                        <div class="card-header bg-info text-white">
                            <i class="bi bi-graph-up"></i> Blockchain Statistics
                        </div>
                        <div class="card-body">
                            <?php if ($blockchain_stats['total_blocks'] === 0): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No blocks available for statistics.
                                </div>
                            <?php else: ?>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card stat-card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-calendar"></i> Timeline
                                                </h5>
                                                <p class="card-text">
                                                    <strong>First block:</strong> <?php echo $blockchain_stats['first_block_date']; ?><br>
                                                    <strong>Last block:</strong> <?php echo $blockchain_stats['last_block_date']; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card stat-card mb-3">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-file-earmark"></i> File Types
                                                </h5>
                                                <ul class="list-unstyled mb-0">
                                                    <?php foreach ($blockchain_stats['extensions'] as $ext => $count): ?>
                                                        <li>
                                                            <span class="badge bg-primary me-1"><?php echo strtoupper($ext); ?></span>
                                                            <?php echo $count; ?> <?php echo $count === 1 ? 'file' : 'files'; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <h5 class="mb-3"><i class="bi bi-people"></i> User Contributions</h5>
                                <?php if (!empty($blockchain_stats['users'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>BTC Address</th>
                                                    <th>Blocks Added</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($blockchain_stats['users'] as $address => $count): ?>
                                                    <tr>
                                                        <td class="hash-value"><?php echo htmlspecialchars($address); ?></td>
                                                        <td><?php echo $count; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> No user contributions recorded yet.
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="mt-5 py-3 bg-light">
        <div class="container">
            <div class="text-center text-muted">
                <small><?php echo APP_NAME; ?> v<?php echo VERSION; ?> &copy; <?php echo date('Y'); ?></small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced client-side validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('blockForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const urlInput = document.getElementById('url');
                    if (urlInput) {
                        try {
                            const url = new URL(urlInput.value);
                            const pathname = url.pathname;
                            const filename = pathname.split('/').pop().split('.')[0];
                            
                            // Validate filename looks like a SHA-256 hash
                            if (!/^[a-f0-9]{64}$/.test(filename)) {
                                alert('Error: The filename in the URL must be exactly 64 hexadecimal characters (a valid SHA-256 hash).\n\nExample: http://example.com/files/9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08.jpg');
                                e.preventDefault();
                                return false;
                            }
                        } catch (err) {
                            alert('Please enter a valid URL');
                            e.preventDefault();
                            return false;
                        }
                    }
                    return true;
                });
            }
        });
    </script>
</body>
</html>