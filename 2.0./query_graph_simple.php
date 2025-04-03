<?php
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

// SQL query
$sql = "
    WITH weekly_counts AS (
        SELECT
            category_hash,
            SUM(CASE WHEN YEARWEEK(uploaded_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS current_week_count,
            SUM(CASE WHEN YEARWEEK(uploaded_at, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1) THEN 1 ELSE 0 END) AS last_week_count
        FROM
            upload_logs
        GROUP BY
            category_hash
    )
    SELECT
        category_hash,
        current_week_count,
        last_week_count,
        CASE
            WHEN last_week_count = 0 THEN NULL
            ELSE ROUND(((current_week_count - last_week_count) / last_week_count) * 100, 2)
        END AS percentage_change
    FROM
        weekly_counts;
";

// Execute query
$result = $conn->query($sql);
if ($result === false) {
    die("Query failed: " . $conn->error);
}

// Fetch and display results
echo "<table border='1'>
        <tr>
            <th>Category Hash</th>
            <th>Current Week Count</th>
            <th>Last Week Count</th>
            <th>Percentage Change</th>
        </tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['category_hash']}</td>
            <td>{$row['current_week_count']}</td>
            <td>{$row['last_week_count']}</td>
            <td>{$row['percentage_change']}</td>
          </tr>";
}

echo "</table>";

// Close connection
$conn->close();
?>