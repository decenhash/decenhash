<?php
// Error reporting configuration - only show in development
$isDevelopment = false; // Set to false in production
if ($isDevelopment) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Initialize variables
$searchQuery = '';
$results = [];

/**
 * Connect to the database
 * @return mysqli Database connection or exit on failure
 */
function connectToDatabase($config) {
   
    // Database configuration
include 'db_config.php';

// Create a database connection
$conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password']);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . $db_config['database'];
if ($conn->query($sql) !== TRUE) {
    die("Error creating database: " . $conn->error);
}
   
// Select the database
$conn->select_db($db_config['database']);
    
    if ($conn->connect_error) {
        // Log error instead of exposing it to users in production
        error_log("Database connection failed: " . $conn->connect_error);
        exit("Database connection failed. Please try again later.");
    }
    
    return $conn;
}

/**
 * Search for records based on query
 * @param mysqli $conn Database connection
 * @param string $query Search query
 * @return array Search results
 */
function searchRecords($conn, $query) {
    $results = [];
    
    if (empty($query)) {
        return $results;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT title, description, username, file_hash, image_hash 
            FROM file_search 
            WHERE title LIKE ? OR description LIKE ?
            LIMIT 100;
        ");
        
        $searchParam = "%$query%";
        $stmt->bind_param("ss", $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
    }
    
    return $results;
}

// Process search request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if (!empty($searchQuery)) {
        $conn = connectToDatabase($config);
        $results = searchRecords($conn, $searchQuery);
        $conn->close();
    }
}

// Helper function to safely output values
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>DecenHash - Search Records</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            padding-top: 2rem;
            background-color: #f8f9fa;
        }
        .search-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .result-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .result-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
        }
        .result-image {
            flex: 0 0 150px;
            margin-right: 25px;
        }
        .result-image img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .result-info {
            flex: 1;
        }
        .file-hash-link {
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background-color: #f8f9fa;
            padding: 5px;
            border-radius: 4px;
        }
        .no-results {
            background-color: #fff;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .app-title {
            color: #343a40;
            margin-bottom: 1.5rem;
        }
        .search-form .form-control {
            border-radius: 20px 0 0 20px;
            padding-left: 1.5rem;
        }
        .search-form .btn {
            border-radius: 0 20px 20px 0;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="search-container">
            <h1 class="app-title text-center mb-4">DecenHash Search</h1>
            
            <!-- Search Form -->
            <form method="GET" action="" class="search-form mb-5">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           placeholder="Search by title or description" 
                           value="<?= e($searchQuery) ?>"
                           aria-label="Search query"
                           autocomplete="off"
                           autofocus>
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-2"></i> Search
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Results Counter -->
            <?php if (!empty($searchQuery)): ?>
                <p class="text-muted">
                    <?= count($results) ?> result<?= count($results) !== 1 ? 's' : '' ?> 
                    found for "<?= e($searchQuery) ?>"
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Display Results -->
        <?php if (!empty($results)): ?>
            <div class="results-container">
                <?php foreach ($results as $row): ?>
                    <div class="result-container">
                        <!-- Image on the Left -->
                        <div class="result-image">
                            <?php if (!empty($row['image_hash'])): ?>
                                <img src="thumbs/<?= e($row['image_hash']) ?>.jpg" 
                                     alt="Preview for <?= e($row['title']) ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <div class="text-center p-4 bg-light rounded">
                                    <i class="fas fa-file fa-3x text-secondary"></i>
                                    <p class="mt-2 mb-0 small text-muted">No Preview</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Information on the Right -->
                        <div class="result-info">
                            <h3><?= e($row['title']) ?></h3>
                            <p class="text-muted"><?= e($row['description']) ?></p>
                            
                            <div class="d-flex flex-wrap mt-3">
                                <div class="mr-4 mb-2">
                                    <i class="fas fa-user text-secondary mr-1"></i>
                                    <strong>Username:</strong> 
                                    <span><?= e($row['username']) ?></span>
                                </div>
                                
                                <div class="mb-2">
                                    <i class="fas fa-fingerprint text-secondary mr-1"></i>
                                    <strong>File Hash:</strong> 
                                    <a href="view_file.php?hash=<?= urlencode($row['file_hash']) ?>" 
                                       class="file-hash-link" 
                                       target="_blank"
                                       title="View file details">
                                        <?= e($row['file_hash']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($searchQuery)): ?>
            <div class="no-results">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h3>No results found</h3>
                <p class="text-muted">
                    We couldn't find any matches for "<?= e($searchQuery) ?>".
                </p>
                <p>Try using different keywords or check your spelling.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>