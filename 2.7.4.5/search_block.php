<?php
/**
 * Blockchain File Search
 */
define('BLOCKS_DIR', __DIR__ . '/blocks/');

// Ensure blocks directory exists
if (!file_exists(BLOCKS_DIR)) {
    mkdir(BLOCKS_DIR, 0755, true);
}

// Process search if form submitted
$searchTerm = $_GET['search'] ?? '';
$results = [];
$totalFiles = 0;

if (!empty($searchTerm)) {
    $files = glob(BLOCKS_DIR . '*.json');
    $totalFiles = count($files);
    
    foreach ($files as $file) {
        $content = json_decode(file_get_contents($file), true);
        
        // Search through these fields
        $searchFields = ['title', 'description', 'extension', 'userbtc', 'filehash'];
        $matchFound = false;
        
        foreach ($searchFields as $field) {
            if (isset($content[$field]) && 
                stripos($content[$field], $searchTerm) !== false) {
                $matchFound = true;
                break;
            }
        }
        
        if ($matchFound) {
            $results[] = $content;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Search</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .search-card {
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .search-highlight {
            background-color: #fffde7;
            font-weight: bold;
        }
        .hash-value {
            font-family: monospace;
            word-break: break-all;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4">Blockchain Search</h1>
                <p class="lead">Search through blockchain files</p>
                
                <form method="get" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control form-control-lg" 
                               placeholder="Search by title, description, hash, etc..." 
                               value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            Search
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($searchTerm)): ?>
                    <div class="alert alert-info mt-3">
                        Found <?= count($results) ?> matches out of <?= $totalFiles ?> files
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="row">
                <?php foreach ($results as $block): ?>
                    <div class="col-md-6">
                        <div class="card search-card">
                            <div class="card-header bg-primary text-white">
                                <?= highlightMatch($block['title'], $searchTerm) ?>
                                <span class="badge bg-light text-dark float-end">
                                    <?= $block['extension'] ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p><?= highlightMatch($block['description'], $searchTerm) ?></p>
                                
                                <div class="mb-2">
                                    <strong>File Hash:</strong>
                                    <div class="hash-value">
                                        <?= highlightMatch($block['filehash'], $searchTerm) ?>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Size:</strong> <?= $block['filesize'] ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Date:</strong> <?= $block['date'] ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($block['userbtc'])): ?>
                                    <div class="mt-2">
                                        <strong>BTC Address:</strong>
                                        <span class="hash-value">
                                            <?= highlightMatch($block['userbtc'], $searchTerm) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <a href="<?= htmlspecialchars($block['url']) ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        View Original File
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($searchTerm)): ?>
            <div class="alert alert-warning">
                No results found for "<?= htmlspecialchars($searchTerm) ?>"
            </div>
        <?php endif; ?>
    </div>

    <?php
    /**
     * Highlight search matches in text
     */
    function highlightMatch($text, $search) {
        if (empty($search)) return htmlspecialchars($text);
        
        $pattern = '/(' . preg_quote($search, '/') . ')/i';
        return preg_replace(
            $pattern,
            '<span class="search-highlight">$1</span>',
            htmlspecialchars($text)
        );
    }
    ?>
</body>
</html>