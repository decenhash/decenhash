<?php
// --- Database Configuration ---
$dbHost = 'localhost';
$dbUsername = 'root';
$dbPassword = '';
$dbName = 'decenhash';

// --- Pagination Configuration ---
$results_per_page = 100;

// Create database connection
$conn = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    // Use a more user-friendly error message on a live site
    die("Connection failed: " . $conn->connect_error);
}

// --- Search Logic ---
$search_term = '';
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    // Sanitize the search term to prevent XSS
    $search_term = htmlspecialchars(trim($_GET['search']));
    $search_query_part = " WHERE `filename` LIKE ? OR `filehash` LIKE ? OR `user` LIKE ?";
    $search_param = "%" . $search_term . "%";
} else {
    $search_query_part = "";
}

// --- Pagination Logic ---
// 1. Determine the current page number
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $page = (int)$_GET['page'];
} else {
    $page = 1;
}

// 2. Calculate the starting record for the query
$start_from = ($page - 1) * $results_per_page;

// 3. Get the total number of records (with search filter if applied)
$count_sql = "SELECT COUNT(*) FROM `files`" . $search_query_part;
$count_stmt = $conn->prepare($count_sql);
if (!empty($search_term)) {
    $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_row()[0];
$total_pages = ceil($total_records / $results_per_page);
$count_stmt->close();


// --- Fetch Data for the Current Page ---
$data_sql = "SELECT `filename`, `filehash`, `type`, `filesize`, `date`, `user` FROM `files`" . $search_query_part . " LIMIT ?, ?";
$data_stmt = $conn->prepare($data_sql);

if (!empty($search_term)) {
    $data_stmt->bind_param("sssii", $search_param, $search_param, $search_param, $start_from, $results_per_page);
} else {
    $data_stmt->bind_param("ii", $start_from, $results_per_page);
}

$data_stmt->execute();
$result = $data_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Search</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1c1e21;
            border-bottom: 2px solid #e7e7e7;
            padding-bottom: 10px;
        }
        .search-form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-form input[type="text"] {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
        }
        .search-form input[type="submit"] {
            padding: 10px 20px;
            border: none;
            background-color: #007bff;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        .search-form input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .results-table th, .results-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }
        .results-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .results-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .results-table tr:hover {
            background-color: #e9ecef;
        }
        /* Style for the new links */
        .results-table td a {
            color: #0056b3;
            text-decoration: none;
            font-weight: 500;
        }
        .results-table td a:hover {
            text-decoration: underline;
        }
        .action-buttons a {
            display: inline-block;
            text-decoration: none;
            color: white;
            padding: 6px 12px;
            margin-right: 5px;
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
            min-width: 70px;
        }
        .btn-comment { background-color: #CCC; }
        .btn-comment:hover { background-color: #138496; }
        .btn-like { background-color: #CCC; }
        .btn-like:hover { background-color: #138496; }
        .pagination {
            text-align: center;
            padding: 10px 0;
        }
        .pagination a {
            color: #007bff;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .pagination a.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .pagination a:hover:not(.active) {
            background-color: #f0f0f0;
        }
        .no-results {
            text-align: center;
            padding: 20px;
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>File Search</h1>
        
        <form class="search-form" action="" method="GET">
            <input type="text" name="search" placeholder="Search by filename, hash, or user..." value="<?= $search_term ?>">
            <input type="submit" value="Search">
        </form>

        <?php if ($result->num_rows > 0): ?>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>File Hash</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Date</th>
                        <th>User</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <?php
                            // Get the file extension from the original filename
                            $extension = pathinfo($row['filename'], PATHINFO_EXTENSION);
                            // Construct the target file path using the hash and extension
                            $filePath = 'files/' . $row['filehash'] . ($extension ? '.' . $extension : '');
                        ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($filePath) ?>" target="_blank">
                                    <?= htmlspecialchars($row['filename']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= htmlspecialchars($filePath) ?>" target="_blank">
                                    <?= htmlspecialchars($row['filehash']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($row['type']) ?></td>
                            <td><?= htmlspecialchars($row['filesize']) ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['user']) ?></td>
                            <td class="action-buttons">
                                <a href="index_simple.php?reply=<?= urlencode($row['filehash']) ?>" class="btn-comment">Comment</a>
                                <a href="index_simple.php?reply=<?= urlencode($row['filehash']) ?>" class="btn-like">Like</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search_term) ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>

        <?php else: ?>
            <p class="no-results">No files found.</p>
        <?php endif; ?>
    </div>

</body>
</html>

<?php
// Close the statement and connection
$data_stmt->close();
$conn->close();
?>
