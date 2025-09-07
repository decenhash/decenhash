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

if (!$mysqli->query($createTableSQL)) {
    echo "Error creating table: " . $mysqli->error;
}

// --- Function to Insert Data (not used in this script but good to have) ---
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
if ($resultTotalDeposits = $mysqli->query($sqlTotalDeposits)) {
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
if ($resultTopHashes = $mysqli->query($sqlTopHashes)) {
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
            max-width: 1200px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .hash-item {
            display: flex; /* Use flexbox for layout */
            gap: 20px; /* Space between thumbnail and content */
            align-items: flex-start; /* Align items to the top */
            border: 1px solid #e0e0e0;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 6px;
            background-color: #fcfcfc;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .hash-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        }
        /* NEW: Styles for the thumbnail image */
        .hash-thumbnail {
            width: 300px;
            height: 300px;
            object-fit: cover; /* Ensures image covers the square without distortion */
            border-radius: 4px;
            flex-shrink: 0; /* Prevents image from shrinking */
            background-color: #eee; /* Placeholder color */
        }
        /* NEW: Wrapper for the content to the right of the thumbnail */
        .hash-content {
            flex-grow: 1; /* Allows content to take up remaining space */
            display: flex;
            flex-direction: column;
        }
        .hash-item h3 {
            margin-top: 0;
            color: #34495e;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.3em;
            margin-bottom: 10px;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 10px;
        }
        .hash-item h3 span.count-info {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .hash-item h3 span.count,
        .hash-item h3 span.percentage,
        .hash-item h3 span.deposit {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: normal;
        }
        .hash-item h3 span.percentage { background-color: #28a745; }
        .hash-item h3 span.deposit { background-color: #6f42c1; }
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
        /* NEW: Style for the "Invest" button */
        .invest-button {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #6f42c1; /* Purple color */
            color: #ffffff;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            cursor: pointer;
            align-self: flex-start; /* Align to the left */
        }
        .invest-button:hover {
            background-color: #59369a; /* Darker purple on hover */
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
                $fileHash = htmlspecialchars($hashData['filehash']);
                ?>
                <div class="hash-item">
                    <img src="thumbs/<?php echo $fileHash; ?>.jpg" alt="Thumbnail for <?php echo $fileHash; ?>" class="hash-thumbnail" onerror="this.style.display='none'">
                    
                    <div class="hash-content">
                        <h3>
                            <span>Hash: <strong><?php echo $fileHash; ?></strong></span>
                            <span class="count-info">
                                <span class="count">Count: <?php echo $hashData['count']; ?></span>
                                <span class="deposit">Deposit: <?php echo number_format($hashData['total_deposit'], 2); ?></span>
                                <span class="percentage"><?php echo number_format($percentage, 2); ?>% of Total</span>
                            </span>
                        </h3>

                        <div class="graph-label">Deposits in Last 30 Days:</div>
                        <div class="graph-container">
                            <canvas id="chart-<?php echo $fileHash; ?>" width="400" height="100"></canvas>
                        </div>

                        <a href="index.php?hash=<?php echo $fileHash; ?>" class="invest-button">Invest</a>

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
                            (function() {
                                var chartId = 'chart-<?php echo $fileHash; ?>';
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
                                            pointHoverRadius: 5,
                                            tension: 0.3
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            x: { display: false, grid: { display: false } },
                                            y: {
                                                beginAtZero: true,
                                                ticks: { precision: 0, font: { size: 10 } },
                                                grid: { color: '#e9ecef', drawBorder: false }
                                            }
                                        },
                                        plugins: {
                                            legend: { display: false },
                                            tooltip: {
                                                mode: 'index',
                                                intersect: false,
                                                backgroundColor: 'rgba(0,0,0,0.7)',
                                                callbacks: {
                                                    label: function(context) {
                                                        return 'Deposit: ' + context.parsed.y.toFixed(2);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            })();
                        </script>
                    </div> </div> <?php endforeach; ?>
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