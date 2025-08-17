<?php
// single_file_decenhash.php - With Percentage of Total Deposit

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
CREATE TABLE IF NOT EXISTS `hashstock` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filehash` VARCHAR(64) NOT NULL,
  `extension` VARCHAR(10) NOT NULL,
  `deposit` DECIMAL(10, 2) NOT NULL,
  `date` DATETIME NOT NULL,
  `user_id` INT(11) NOT NULL,
  INDEX `hash_index` (`filehash`)
);";
// Add an index to the 'hash' column for better performance
$createIndexSQL = "CREATE INDEX idx_hash ON hashstock (filehash);";

if ($mysqli->query($createTableSQL) === TRUE) {
    // Attempt to create index only if the table exists or was just created
    @$mysqli->query($createIndexSQL);
} else {
    echo "Error creating table: " . $mysqli->error;
}

// --- Function to Insert Data ---
function insertHash($hash, $data) {
    global $mysqli;
    $stmt = $mysqli->prepare("INSERT INTO hashstock (filehash, date) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $hash, $data);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Error preparing insert statement: " . $mysqli->error);
    }
}

// --- Get Total Sum of All Deposits ---
$totalDeposits = 0;
$sqlTotalDeposits = "SELECT SUM(deposit) as total_deposits FROM hashstock";
$resultTotalDeposits = $mysqli->query($sqlTotalDeposits);
if ($resultTotalDeposits) {
    $rowTotal = $resultTotalDeposits->fetch_assoc();
    $totalDeposits = (float)$rowTotal['total_deposits'];
}

// --- Retrieve Top 20 Hashes by Sum of Deposits ---
$topHashes = [];
$sqlTopHashes = "SELECT filehash, COUNT(*) as count, SUM(deposit) as total_deposit 
                 FROM hashstock 
                 GROUP BY filehash 
                 ORDER BY total_deposit DESC 
                 LIMIT 20";
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
            gap: 10px;
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
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: normal;
        }
        .hash-item h3 span.deposit {
            background-color: #6f42c1;
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
            height: 100px;
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
        <h1>Top 20 Hashes by Total Deposit</h1>
        <p>This dashboard shows the hashes with the highest total deposit values in your 'decenhash' database, along with their daily activity over the last 30 days. The percentage indicates the proportion of each hash's total deposit relative to the <strong>total of <?php echo number_format($totalDeposits, 2); ?> in deposits</strong> across all hashes.</p>

        <?php if (empty($topHashes)): ?>
            <p class="no-data">No hashes found in the database. Please insert some data to see results.</p>
        <?php else: ?>
            <?php foreach ($topHashes as $hashData): ?>
                <?php
                $percentage = ($totalDeposits > 0) ? ($hashData['total_deposit'] / $totalDeposits) * 100 : 0;
                ?>
                <div class="hash-item">
                    <h3>
                        <span>Hash: <strong><?php echo htmlspecialchars($hashData['filehash']); ?></strong></span>
                        <span class="count-info">
                            <span class="count">Count: <?php echo $hashData['count']; ?></span>
                            <span class="deposit">Deposit: <?php echo number_format($hashData['total_deposit'], 2); ?></span>
                            <span class="percentage"><?php echo number_format($percentage, 2); ?>% of Total</span>
                        </span>
                    </h3>

                    <div class="graph-label">Deposits in Last 30 Days:</div>
                    <div class="graph-container">
                        <canvas id="chart-<?php echo htmlspecialchars($hashData['filehash']); ?>" width="400" height="100"></canvas>
                    </div>

                    <?php
                    // --- Get daily deposit sums for the last 30 days for the current hash ---
                    $dailyDeposits = [];
                    $labels = [];
                    // Populate with last 30 days, initialized to 0
                    for ($i = 29; $i >= 0; $i--) {
                        $date = date('Y-m-d', strtotime("-$i days"));
                        $dailyDeposits[$date] = 0;
                        $labels[] = date('M j', strtotime("-$i days"));
                    }

                    $sqlDailyDeposits = "SELECT DATE(date) as date, SUM(deposit) as daily_deposit
                                       FROM hashstock
                                       WHERE filehash = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                                       GROUP BY DATE(date)
                                       ORDER BY date ASC";

                    $stmtDailyDeposits = $mysqli->prepare($sqlDailyDeposits);
                    if ($stmtDailyDeposits) {
                        $stmtDailyDeposits->bind_param("s", $hashData['filehash']);
                        $stmtDailyDeposits->execute();
                        $resultDailyDeposits = $stmtDailyDeposits->get_result();

                        if ($resultDailyDeposits) {
                            while ($rowDaily = $resultDailyDeposits->fetch_assoc()) {
                                $dailyDeposits[$rowDaily['date']] = (float)$rowDaily['daily_deposit'];
                            }
                        }
                        $stmtDailyDeposits->close();
                    } else {
                        error_log("Error preparing daily deposits statement: " . $mysqli->error);
                    }

                    // Prepare data points for Chart.js
                    $dataPoints = array_values($dailyDeposits);
                    ?>

                    <script>
                        var chartId = 'chart-<?php echo htmlspecialchars($hashData['filehash']); ?>';
                        var ctx = document.getElementById(chartId).getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode($labels); ?>,
                                datasets: [{
                                    label: 'Daily Deposits',
                                    data: <?php echo json_encode($dataPoints); ?>,
                                    borderColor: '#6f42c1',
                                    backgroundColor: 'rgba(111, 66, 193, 0.1)',
                                    borderWidth: 2,
                                    fill: true,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#6f42c1',
                                    pointBorderColor: '#fff',
                                    pointHoverRadius: 5
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    x: {
                                        display: false,
                                        grid: {
                                            display: false
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0,
                                            font: {
                                                size: 10
                                            }
                                        },
                                        grid: {
                                            color: '#e9ecef',
                                            drawBorder: false
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
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
                                                var index = context[0].dataIndex;
                                                var originalLabels = <?php echo json_encode(array_keys($dailyDeposits)); ?>;
                                                return originalLabels[index];
                                            },
                                            label: function(context) {
                                                return 'Deposit: ' + context.parsed.y.toFixed(2);
                                            }
                                        }
                                    }
                                },
                                elements: {
                                    line: {
                                        tension: 0.3
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