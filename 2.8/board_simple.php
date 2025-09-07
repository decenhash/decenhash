<?php
// single_file_decenhash.php - No external libraries, no graphs

// --- Database Configuration ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // <-- IMPORTANT: Change this
define('DB_PASSWORD', '');     // <-- IMPORTANT: Change this
define('DB_NAME', 'decenhash');

// --- Establish Database Connection ---
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    die("ERROR: Could not connect to the database. " . $mysqli->connect_error);
}

// --- Create Table if It Does Not Exist ---
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

// --- Get the Total Sum of All Deposits ---
$totalDeposits = 0;
$sqlTotalDeposits = "SELECT SUM(deposit) as total_deposits FROM hashstock";
if ($resultTotalDeposits = $mysqli->query($sqlTotalDeposits)) {
    $rowTotal = $resultTotalDeposits->fetch_assoc();
    $totalDeposits = (float)($rowTotal['total_deposits'] ?? 0);
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
            margin: 0;
            background-color: #f4f7f6;
            color: #333;
            line-height: 1.6;
        }
        .main-nav {
            background-color: #6f42c1;
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
            margin: 20px auto;
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
            width: 150px; /* Reduced size as there's no graph */
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
            flex-shrink: 0;
            background-color: #eee;
        }
        .hash-content {
            flex-grow: 1;
        }
        .hash-item h3 {
            margin-top: 0;
            color: #34495e;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.3em;
            margin-bottom: 15px;
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
        @media (max-width: 768px) {
            .hash-item {
                flex-direction: column;
                align-items: center;
            }
            .hash-content {
                width: 100%;
                align-items: center;
                display: flex;
                flex-direction: column;
            }
            .hash-item h3 {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            .main-nav ul {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
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
        <p>This dashboard shows the hashes with the highest total deposit values. The percentage indicates how much each hash's deposit contributes to the <strong>total of <?php echo number_format($totalDeposits, 2); ?></strong> across all hashes.</p>

        <?php if (empty($topHashes)): ?>
            <p class="no-data">No hashes found in the database. Please insert data to see results.</p>
        <?php else: ?>
            <?php foreach ($topHashes as $hashData): ?>
                <?php
                // Calculate the percentage of the total deposits
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
                        
                        <a href="deposit.php?hash=<?php echo $fileHash; ?>" class="invest-button">Invest</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Decenhash Analytics. Data retrieved from the database.</p>
    </footer>

</body>
</html>
<?php
// --- Close Database Connection ---
$mysqli->close();
?>