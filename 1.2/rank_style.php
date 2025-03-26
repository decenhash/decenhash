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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Most Repeated File Hashes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #4a90e2;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #4a90e2;
            color: white;
            font-weight: bold;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4a90e2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .back-link:hover {
            background-color: #357abd;
        }

        .percentage {
            font-weight: bold;
            color: #2ecc71;
        }

        @media (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
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
                            <td class="percentage"><?php echo number_format(($log['hash_count'] / $total_repetitions) * 100, 2) . '%'; ?></td>
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