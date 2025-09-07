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
            margin: 0; /* Removed default margin */
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
        }
        
        /* NEW: Minimalist Navigation Menu */
        .main-nav {
            background-color: #6f42c1; /* Purple background */
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .main-nav ul {
            display: flex;
            justify-content: center;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .main-nav a {
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            display: block;
            font-weight: bold;
            transition: background-color 0.2s ease-in-out;
            border-radius: 4px;
        }
        .main-nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto; /* Added top/bottom margin */
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .hash-item {
            display: flex;
            gap: 20px;
            align-items: flex-start;
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
        .hash-thumbnail {
            width: 400px;
            height: 400px;
            object-fit: cover;
            border-radius: 4px;
            flex-shrink: 0;
            background-color: #eee;
        }
        .hash-content {
            flex-grow: 1;
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
            flex-wrap: wrap;
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
        .invest-button {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background-color: #6f42c1;
            color: #ffffff;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            cursor: pointer;
            align-self: flex-start;
        }
        .invest-button:hover {
            background-color: #59369a;
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
            padding: 20px;
            font-size: 0.85em;
            color: #7f8c8d;
        }

        /* NEW: Responsive Media Query */
        @media (max-width: 768px) {
            .hash-item {
                flex-direction: column; /* Stack items vertically */
                align-items: center; /* Center the thumbnail */
            }
            .hash-content {
                width: 100%; /* Ensure content takes full width */
                align-items: center; /* Center the button */
            }
            .hash-item h3 {
                flex-direction: column; /* Stack title and badges */
                align-items: flex-start; /* Align to the left */
                width: 100%;
            }
            .invest-button {
                align-self: center; /* Center the button in its container */
            }
            .main-nav ul {
                flex-direction: column;
                align-items: center;
            }
            .main-nav a {
                padding: 0.5rem 1rem;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

    <nav class="main-nav">
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="upload.php">Upload</a></li>
            <li><a href="upload_thumb.php">Thumb</a></li>
            <li><a href="deposit.php">Deposit</a></li> 
            <li><a href="stock.php">Owner</a></li>  
            <li><a href="register.php">Register</a></li>
            <li><a href="about.php">About</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

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

                        <a href="deposit.php?hash=<?php echo $fileHash; ?>" class="invest-button">Invest</a>

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