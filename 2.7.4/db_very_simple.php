<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root'; // Replace with your MySQL username
$db_pass = ''; // Replace with your MySQL password
$db_name = 'decenhash';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to generate simple ASCII line graph
function generateLineGraph($values, $height = 5) {
    if (empty($values)) return '';
    
    $max = max($values);
    if ($max == 0) return str_repeat("?", count($values));
    
    $graph = '';
    for ($i = $height; $i > 0; $i--) {
        foreach ($values as $value) {
            $scaled = ($value / $max) * $height;
            $graph .= ($scaled >= $i) ? '¦' : ' ';
        }
        $graph .= "\n";
    }
    return $graph;
}

// Get top 20 most repeated hashes
$query = "SELECT hash, COUNT(*) as count 
          FROM hash_data 
          GROUP BY hash 
          ORDER BY count DESC 
          LIMIT 20";
$result = $conn->query($query);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Hash Frequency Analysis</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .hash-item { margin-bottom: 30px; }
        .graph { line-height: 1; white-space: pre; }
        .hash { font-weight: bold; }
        .count { color: #666; }
    </style>
</head>
<body>
    <h1>Top 20 Most Frequent Hashes</h1>";

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $hash = htmlspecialchars($row['hash']);
        $count = $row['count'];
        
        // Get last 30 days data for this hash
        $daily_query = "SELECT 
                            DATE(created_at) as day, 
                            COUNT(*) as daily_count 
                        FROM hash_data 
                        WHERE hash = ? 
                          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY day 
                        ORDER BY day";
        
        $stmt = $conn->prepare($daily_query);
        $stmt->bind_param("s", $row['hash']);
        $stmt->execute();
        $daily_result = $stmt->get_result();
        
        $daily_counts = [];
        $dates = [];
        while ($daily_row = $daily_result->fetch_assoc()) {
            $dates[] = $daily_row['day'];
            $daily_counts[] = $daily_row['daily_count'];
        }
        
        // Fill in missing days with 0
        $complete_counts = [];
        $period = new DatePeriod(
            new DateTime('-29 days'),
            new DateInterval('P1D'),
            new DateTime('tomorrow')
        );
        
        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            $index = array_search($date_str, $dates);
            $complete_counts[] = ($index !== false) ? $daily_counts[$index] : 0;
        }
        
        // Generate ASCII graph
        $graph = generateLineGraph($complete_counts, 3);
        
        echo "<div class='hash-item'>
                <div class='hash'>Hash: $hash</div>
                <div class='count'>Total occurrences: $count</div>
                <div class='graph'>Last 30 days:\n" . htmlspecialchars($graph) . "</div>
              </div>";
    }
} else {
    echo "<p>No hashes found in the database.</p>";
}

echo "</body></html>";

$conn->close();
?>