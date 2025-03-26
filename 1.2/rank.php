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

// Query 1: Most repeated in the current week
$sql_current_week = "
    SELECT
        category_hash,
        COUNT(*) AS current_week_count
    FROM
        upload_logs
    WHERE
        YEARWEEK(uploaded_at, 1) = YEARWEEK(CURDATE(), 1)
    GROUP BY
        category_hash
    ORDER BY
        current_week_count DESC
    LIMIT 100;
";

// Query 2: Most repeated all time
$sql_all_time = "
    SELECT
        category_hash,
        COUNT(*) AS all_time_count
    FROM
        upload_logs
    GROUP BY
        category_hash
    ORDER BY
        all_time_count DESC
    LIMIT 100;
";

// Execute queries
$result_current_week = $conn->query($sql_current_week);
$result_all_time = $conn->query($sql_all_time);

if ($result_current_week === false || $result_all_time === false) {
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
    .section-title {
        font-size: 18px;
        font-weight: bold;
        margin-top: 30px;
        margin-bottom: 10px;
    }
</style>
";

// Display results for the current week
echo "<div class='section-title'>Top 100 Most Repeated Category Hashes (Current Week)</div>";
echo "<table border='1'>
        <tr>
            <th>Category Hash</th>
            <th>Current Week Count</th>
        </tr>";

while ($row = $result_current_week->fetch_assoc()) {
    echo "<tr>
            <td>{$row['category_hash']}</td>
            <td>{$row['current_week_count']}</td>
          </tr>";
}

echo "</table>";

// Display results for all time
echo "<div class='section-title'>Top 100 Most Repeated Category Hashes (All Time)</div>";
echo "<table border='1'>
        <tr>
            <th>Category Hash</th>
            <th>All Time Count</th>
        </tr>";

while ($row = $result_all_time->fetch_assoc()) {
    echo "<tr>
            <td>{$row['category_hash']}</td>
            <td>{$row['all_time_count']}</td>
          </tr>";
}

echo "</table>";

// Close connection
$conn->close();
?>