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
        (current_week_count - last_week_count) AS increase_in_repetitions
    FROM
        weekly_counts
    ORDER BY
        increase_in_repetitions DESC
    LIMIT 100;
";

// Execute query
$result = $conn->query($sql);
if ($result === false) {
    die("Query failed: " . $conn->error);
}

// CSS Styling
echo "
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    th, td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: center;
    }
    th {
        background-color: #f4f4f4;
    }
</style>
";

// Display results in a table
echo "<table border='1'>
        <tr>
            <th>Category Hash</th>
            <th>Current Week Count</th>
            <th>Last Week Count</th>
            <th>Increase in Repetitions</th>
        </tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['category_hash']}</td>
            <td>{$row['current_week_count']}</td>
            <td>{$row['last_week_count']}</td>
            <td>{$row['increase_in_repetitions']}</td>
          </tr>";
}

echo "</table>";

// Close connection
$conn->close();
?>