<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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

// Query to get the most repeated file_hash values
$query = "
    SELECT category_hash, COUNT(category_hash) AS hash_count
    FROM upload_logs
    GROUP BY category_hash
    ORDER BY hash_count DESC
    LIMIT 100
";

$result = $conn->query($query);

// Fetch results
$logs = [];
$total_repetitions = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
        $total_repetitions += $row['hash_count'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Most Repeated File Hashes</title>
    <style>
        .container {
            width: 80%;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .back-link {
            display: block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Most Repeated File Hashes</h1>
        <?php if (!empty($logs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>File Hash</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['category_hash']); ?></td>
                            <td><?php echo $log['hash_count']; ?></td>
                            <td><?php echo number_format(($log['hash_count'] / $total_repetitions) * 100, 2) . '%'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No upload logs found.</p>
        <?php endif; ?>
        <a class="back-link" href="index.php">Back</a>
    </div>
</body>
</html>