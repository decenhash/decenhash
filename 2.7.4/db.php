<?php
// single_file_decenhash.php - With Percentage of Total

// --- Database Configuration ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // <--- IMPORTANT: Change this
define('DB_PASSWORD', ''); // <--- IMPORTANT: Change this
define('DB_NAME', 'decenhash');

// --- Establish Database Connection ---
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    die("ERROR: Could not connect to database. " . $mysqli->connect_error);
}

// --- Create Table if Not Exists ---
$createTableSQL = "
CREATE TABLE IF NOT EXISTS hash_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hash VARCHAR(255) NOT NULL,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";

// Add an index to the 'hash' column for better performance
$createIndexSQL = "CREATE INDEX idx_hash ON hash_data (hash);";

if ($mysqli->query($createTableSQL) === TRUE) {
    // Attempt to create index only if the table exists or was just created
    // Suppress errors for index creation as it might already exist or fail on first attempt without specific check
    @$mysqli->query($createIndexSQL);
} else {
    echo "Error creating table: " . $mysqli->error;
}

// --- Function to Insert Data ---
function insertHash($hash, $data) {
    global $mysqli;
    // Prepare the statement to prevent SQL injection
    $stmt = $mysqli->prepare("INSERT INTO hash_data (hash, data) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $hash, $data);
        $stmt->execute();
        $stmt->close();
    } else {
        // In a production environment, you'd log this error, not echo it
        error_log("Error preparing insert statement: " . $mysqli->error);
    }
}

// --- Data Insertion Example (Uncomment to add sample data) ---
/*
// You can run these once to populate your database with some test data.
// After the first run, comment them out or remove them to avoid duplicate entries on every page load.
// If you already ran the previous script with sample data, you might not need to run this again unless you want more.
insertHash('d4c3b2a1', 'Transaction record for user A');
insertHash('e5f6g7h8', 'Log entry from server B');
insertHash('d4c3b2a1', 'Another transaction for user A');
insertHash('a1b2c3d4', 'Security event notification');
insertHash('e5f6g7h8', 'System startup message');
insertHash('d4c3b2a1', 'User A\'s last action');
insertHash('x9y8z7w6', 'Network packet data');
insertHash('a1b2c3d4', 'Authentication success');
insertHash('e5f6g7h8', 'Error log details');
insertHash('x9y8z7w6', 'More network traffic');
insertHash('d4c3b2a1', 'Hash from a new file upload');
insertHash('a1b2c3d4', 'Login attempt from IP 192.168.1.100');
insertHash('f2e1d0c9', 'Payment confirmation hash');
insertHash('d4c3b2a1', 'Another instance of file upload hash');
insertHash('e5f6g7h8', 'Application heartbeat signal');
insertHash('f2e1d0c9', 'Second payment related hash');
insertHash('a1b2c3d4', 'Failed login attempt');
insertHash('h1j2k3l4', 'Blockchain transaction hash');
insertHash('d4c3b2a1', 'Yet another transaction');
insertHash('h1j2k3l4', 'Another blockchain record');
insertHash('m5n6o7p8', 'Document fingerprint');
insertHash('f2e1d0c9', 'Third payment confirmation');
insertHash('h1j2k3l4', 'More blockchain data');
insertHash('m5n6o7p8', 'Revised document fingerprint');
insertHash('abc12345', 'New unique hash');
insertHash('abc12345', 'Another new unique hash');
insertHash('d4c3b2a1', 'Final test for hash A');
insertHash('e5f6g7h8', 'Final test for hash B');
*/

// --- Get Total Number of Entries ---
$totalEntries = 0;
$sqlTotalEntries = "SELECT COUNT(*) as total_count FROM hash_data";
$resultTotalEntries = $mysqli->query($sqlTotalEntries);
if ($resultTotalEntries) {
    $rowTotal = $resultTotalEntries->fetch_assoc();
    $totalEntries = (int)$rowTotal['total_count'];
}

// --- Retrieve Top 20 Most Repeated Hashes ---
$topHashes = [];
$sqlTopHashes = "SELECT hash, COUNT(*) as count FROM hash_data GROUP BY hash ORDER BY count DESC LIMIT 20";
$resultTopHashes = $mysqli->query($sqlTopHashes);

if ($resultTopHashes) {
    while ($row = $resultTopHashes->fetch_assoc()) {
        $topHashes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decenhash Analytics Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .hash-item {
            border: 1px solid #e0e0e0;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 6px;
            background-color: #fcfcfc;
            transition: transform 0.2s ease-in-out;
        }
        .hash-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        .hash-item h3 {
            margin-top: 0;
            color: #34495e;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.3em;
            margin-bottom: 10px;
        }
        .hash-item h3 span.count-info {
            display: flex;
            align-items: center;
            gap: 10px; /* Space between count and percentage */
        }
        .hash-item h3 span.count {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: normal;
        }
        .hash-item h3 span.percentage {
            background-color: #28a745; /* Green for percentage */
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: normal;
        }
        .graph-label {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .graph-container {
            width: 100%;
            height: 100px; /* Fixed height for consistency */
            margin-top: 10px;
            background-color: #ffffff;
            border-radius: 4px;
        }
        p.no-data {
            text-align: center;
            color: #777;
            font-style: italic;
            margin-top: 50px;
        }
        footer {
            text-align: center;
            margin-top: 40px;
            font-size: 0.85em;
            color: #7f8c8d;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Top 20 Most Repeated Hashes</h1>
        <p>This dashboard shows the most frequently occurring hashes in your 'decenhash' database, along with their daily activity over the last 30 days. The percentage indicates the proportion of each hash's count relative to the **total of <?php echo number_format($totalEntries); ?> entries** in the database.</p>

        <?php if (empty($topHashes)): ?>
            <p class="no-data">No hashes found in the database. Please insert some data to see results.</p>
        <?php else: ?>
            <?php foreach ($topHashes as $hashData): ?>
                <?php
                $percentage = ($totalEntries > 0) ? ($hashData['count'] / $totalEntries) * 100 : 0;
                ?>
                <div class="hash-item">
                    <h3>
                        <span>Hash: **<?php echo htmlspecialchars($hashData['hash']); ?>**</span>
                        <span class="count-info">
                            <span class="count">Count: <?php echo $hashData['count']; ?></span>
                            <span class="percentage"><?php echo number_format($percentage, 2); ?>% of Total</span>
                        </span>
                    </h3>

                    <div class="graph-label">Occurrences in Last 30 Days:</div>
                    <div class="graph-container">
                        <canvas id="chart-<?php echo htmlspecialchars($hashData['hash']); ?>" width="400" height="100"></canvas>
                    </div>

                    <?php
                    // --- Get daily counts for the last 30 days for the current hash ---
                    $dailyCounts = [];
                    $labels = [];
                    // Populate with last 30 days, initialized to 0
                    for ($i = 29; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days"));
                        $dailyCounts[$date] = 0; // Initialize with 0
                        $labels[] = date('M j', strtotime("-$i days")); // Format for display on chart
                    }

                    $sqlDailyCounts = "SELECT DATE(created_at) as date, COUNT(*) as daily_count
                                       FROM hash_data
                                       WHERE hash = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                                       GROUP BY DATE(created_at)
                                       ORDER BY date ASC";

                    $stmtDailyCounts = $mysqli->prepare($sqlDailyCounts);
                    if ($stmtDailyCounts) {
                        $stmtDailyCounts->bind_param("s", $hashData['hash']);
                        $stmtDailyCounts->execute();
                        $resultDailyCounts = $stmtDailyCounts->get_result();

                        if ($resultDailyCounts) {
                            while ($rowDaily = $resultDailyCounts->fetch_assoc()) {
                                $dailyCounts[$rowDaily['date']] = (int)$rowDaily['daily_count'];
                            }
                        }
                        $stmtDailyCounts->close();
                    } else {
                        error_log("Error preparing daily counts statement: " . $mysqli->error);
                    }

                    // Prepare data points for Chart.js
                    $dataPoints = array_values($dailyCounts);
                    ?>

                    <script>
                        // Sanitize hash for use in JavaScript variable name
                        var chartId = 'chart-<?php echo htmlspecialchars($hashData['hash']); ?>';
                        var ctx = document.getElementById(chartId).getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode($labels); ?>, // Dates for X-axis
                                datasets: [{
                                    label: 'Daily Occurrences',
                                    data: <?php echo json_encode($dataPoints); ?>, // Counts for Y-axis
                                    borderColor: '#007bff', // Blue line color
                                    backgroundColor: 'rgba(0, 123, 255, 0.1)', // Light blue fill under line
                                    borderWidth: 2,
                                    fill: true, // Fill area under the line
                                    pointRadius: 3, // Size of data points
                                    pointBackgroundColor: '#007bff',
                                    pointBorderColor: '#fff',
                                    pointHoverRadius: 5
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, // Allows setting a fixed height
                                scales: {
                                    x: {
                                        display: false, // Hide X-axis labels for minimalist view
                                        grid: {
                                            display: false // Hide X-axis grid lines
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0, // Ensure integer ticks on Y-axis
                                            font: {
                                                size: 10 // Smaller font for Y-axis ticks
                                            }
                                        },
                                        grid: {
                                            color: '#e9ecef', // Light gray Y-axis grid lines
                                            drawBorder: false
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false // Hide dataset legend
                                    },
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        backgroundColor: 'rgba(0,0,0,0.7)',
                                        titleFont: {
                                            size: 12
                                        },
                                        bodyFont: {
                                            size: 12
                                        },
                                        padding: 10,
                                        callbacks: {
                                            title: function(context) {
                                                // Display the actual date (from original dailyCounts keys)
                                                var index = context[0].dataIndex;
                                                var originalLabels = <?php echo json_encode(array_keys($dailyCounts)); ?>;
                                                return originalLabels[index];
                                            }
                                        }
                                    }
                                },
                                elements: {
                                    line: {
                                        tension: 0.3 // Slightly smooth the line
                                    }
                                }
                            }
                        });
                    </script>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Decenhash Analytics. Data retrieved from MySQL database.</p>
    </footer>
</body>
</html>

<?php
// --- Close Database Connection ---
$mysqli->close();
?>