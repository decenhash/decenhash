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

// SQL query to get percentage change for each category_hash
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
    .graph {
        position: relative;
        width: 100%;
        height: 100px;
        margin-bottom: 20px;
    }
    .graph-line {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: flex-end;
    }
    .graph-point {
        position: absolute;
        width: 10px;
        height: 10px;
        background-color: #007bff;
        border-radius: 50%;
        transform: translateX(-50%);
    }
    .graph-label {
        position: absolute;
        bottom: -20px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 12px;
    }
</style>
";

// Display results in a table
echo "<table border='1'>
        <tr>
            <th>Category Hash</th>
            <th>Current Week Count</th>
            <th>Last Week Count</th>
            <th>Percentage Change</th>
            <th>Current Week Daily Graph</th>
        </tr>";

while ($row = $result->fetch_assoc()) {
    $category_hash = $row['category_hash'];
    $current_week_count = $row['current_week_count'];
    $last_week_count = $row['last_week_count'];
    $percentage_change = $row['percentage_change'];

    // Query to get daily counts for the current week
    $daily_sql = "
        SELECT
            DAYOFWEEK(uploaded_at) AS day_of_week,
            COUNT(*) AS daily_count
        FROM
            upload_logs
        WHERE
            category_hash = '$category_hash'
            AND YEARWEEK(uploaded_at, 1) = YEARWEEK(CURDATE(), 1)
        GROUP BY
            DAYOFWEEK(uploaded_at)
        ORDER BY
            day_of_week;
    ";
    $daily_result = $conn->query($daily_sql);

    // Initialize daily counts array
    $daily_counts = array_fill(1, 7, 0); // 1 = Monday, 7 = Sunday
    while ($daily_row = $daily_result->fetch_assoc()) {
        $daily_counts[$daily_row['day_of_week']] = $daily_row['daily_count'];
    }

    // Normalize daily counts for graph height
    $max_count = max($daily_counts);
    $normalized_counts = array_map(function ($count) use ($max_count) {
        return $max_count > 0 ? ($count / $max_count) * 100 : 0;
    }, $daily_counts);

    // Display table row
    echo "<tr>
            <td>{$category_hash}</td>
            <td>{$current_week_count}</td>
            <td>{$last_week_count}</td>
            <td>{$percentage_change}</td>
            <td>
                <div class='graph'>
                    <div class='graph-line'>";

    // Display line graph for each day of the week
    $day_names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    foreach ($normalized_counts as $day => $count) {
        $x_position = (($day - 1) / 6) * 100; // Spread points evenly across the graph
        $y_position = 100 - $count; // Invert Y-axis for bottom alignment
        $day_name = $day_names[$day - 1]; // Get day name

        echo "<div class='graph-point' style='left: {$x_position}%; bottom: {$y_position}%'></div>
              <div class='graph-label' style='left: {$x_position}%'>{$day_name}</div>";
    }

    echo "</div>
                </div>
            </td>
          </tr>";
}

echo "</table>";

// Close connection
$conn->close();
?>