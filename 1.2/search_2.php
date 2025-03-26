<?php
// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Initialize variables
$searchQuery = '';
$results = [];

// Process search form submission
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    
    if (!empty($searchQuery)) {
        // Prepare the search query
        $stmt = $conn->prepare("SELECT title, description, username, file_hash, image_hash FROM file_search WHERE title LIKE ? OR description LIKE ?");
        $searchParam = "%$searchQuery%";
        $stmt->bind_param("ss", $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Fetch all matching results
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Records</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .result-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .result-image {
            flex: 0 0 150px;
            margin-right: 20px;
        }
        .result-image img {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
        }
        .result-info {
            flex: 1;
        }
        .file-hash-link {
            word-break: break-all; /* Ensure long hashes don't overflow */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Search Records</h1>
        
        <!-- Search Form -->
        <form method="GET" action="" class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search by title or description" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <div class="input-group-append">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </div>
        </form>
        
        <!-- Display Results -->
        <?php if (!empty($results)): ?>
            <h2 class="mb-3">Search Results</h2>
            <?php foreach ($results as $row): ?>
                <div class="result-container">
                    <!-- Image on the Left -->
                    <div class="result-image">
                        <?php if (!empty($row['image_hash'])): ?>
                            <img src="thumbs/<?php echo htmlspecialchars($row['image_hash']); ?>.jpg" alt="Uploaded Image">
                        <?php else: ?>
                            <p>No Image</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Information on the Right -->
                    <div class="result-info">
                        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($row['description']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                        <p><strong>File Hash:</strong> 
                            <a href="view_file.php?hash=<?php echo urlencode($row['file_hash']); ?>" class="file-hash-link" target="_blank">
                                <?php echo htmlspecialchars($row['file_hash']); ?>
                            </a>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])): ?>
            <div class="alert alert-info" role="alert">
                No results found for "<?php echo htmlspecialchars($searchQuery); ?>".
            </div>
        <?php endif; ?>
    </div>
</body>
</html>