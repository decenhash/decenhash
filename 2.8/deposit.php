<?php
// Start the session at the very beginning
session_start();

// --- CONFIGURATION ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'decenhash';

// --- DATABASE CONNECTION ---
$conn = new mysqli($db_host, $db_user, $db_pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->query("CREATE DATABASE IF NOT EXISTS $db_name");
$conn->select_db($db_name);

// --- TABLE SETUP ---
// SQL to create a 'users' table
$users_table_sql = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `btc` VARCHAR(100) DEFAULT NULL,
  `mail` VARCHAR(100) NOT NULL UNIQUE
);";
if (!$conn->query($users_table_sql)) {
    die("Error creating users table: " . $conn->error);
}

// SQL to create 'hashstock' table
$hashstock_table_sql = "
CREATE TABLE IF NOT EXISTS `hashstock` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filehash` VARCHAR(64) NOT NULL,
  `extension` VARCHAR(10) NOT NULL,
  `deposit` DECIMAL(10, 2) NOT NULL,
  `date` DATETIME NOT NULL,
  `user_id` INT(11) NOT NULL,
  INDEX `hash_index` (`filehash`)
);";
if (!$conn->query($hashstock_table_sql)) {
    die("Error creating hashstock table: " . $conn->error);
}

// --- DEMO USER SETUP ---
// For demonstration: if no user exists, create one.
$result = $conn->query("SELECT id FROM users LIMIT 1");
if ($result->num_rows == 0) {
    $demo_user = 'demouser';
    $demo_pass = password_hash('password123', PASSWORD_DEFAULT);
    $demo_mail = 'demouser@example.com';
    $demo_balance = 1000.00;
    $stmt_insert_demo = $conn->prepare("INSERT INTO users (username, password, mail, balance) VALUES (?, ?, ?, ?)");
    $stmt_insert_demo->bind_param("sssd", $demo_user, $demo_pass, $demo_mail, $demo_balance);
    $stmt_insert_demo->execute();
    $stmt_insert_demo->close();
}
if (!isset($_SESSION['id'])) {
    // Default to the first user for this demo
    $result = $conn->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $user = $result->fetch_assoc();
    $_SESSION['id'] = $user['id'];
}

// --- FETCH USER DATA ---
$user_id = $_SESSION['id'] ?? 0;
$username = 'Guest';
$balance = 0.00;
if ($user_id > 0) {
    $stmt_user = $conn->prepare("SELECT username, balance FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_result = $stmt_user->get_result();
    if ($user_data = $user_result->fetch_assoc()) {
        $username = $user_data['username'];
        $balance = (float)$user_data['balance'];
    } else {
        session_destroy();
        die("Invalid session. Please log in again.");
    }
    $stmt_user->close();
}
$display_balance = $balance;

// --- HANDLE GET PARAMETER FOR HASH ---
$get_hash = '';
if (isset($_GET['hash'])) {
    // Validate that the hash is a 64-character hex string (SHA-256)
    if (preg_match('/^[a-f0-9]{64}$/i', $_GET['hash'])) {
        $get_hash = strtolower($_GET['hash']);
    }
}

// --- FORM PROCESSING LOGIC ---
$message = '';
$message_type = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($user_id <= 0) {
        $message = "Error: You must be logged in to make a deposit.";
        $message_type = 'error';
    } else {
        $filehash = $_POST['filehash'] ?? '';
        // If hash came from GET param, extension will be empty. Default to 'N/A' or ''.
        $extension = $_POST['extension'] ?? 'get';
        $deposit = filter_input(INPUT_POST, 'deposit', FILTER_VALIDATE_FLOAT);

        // --- VALIDATION ---
        if (empty($filehash) || !preg_match('/^[a-f0-9]{64}$/i', $filehash)) {
            $message = "Error: A valid SHA-256 hash is required.";
            $message_type = 'error';
        } elseif ($deposit === false || $deposit <= 0) {
            $message = "Error: Please enter a deposit amount greater than zero.";
            $message_type = 'error';
        } elseif ($deposit > $balance) {
            $message = "Error: Insufficient balance. Your current balance is $" . number_format($balance, 2) . ".";
            $message_type = 'error';
        } else {
            // --- REVISED LOGIC: Allow any deposit if balance is sufficient ---
            $conn->begin_transaction();
            try {
                // 1. Insert hash record
                $stmt_insert = $conn->prepare("INSERT INTO hashstock (filehash, extension, deposit, date, user_id) VALUES (?, ?, ?, NOW(), ?)");
                $stmt_insert->bind_param("ssdi", $filehash, $extension, $deposit, $user_id);
                $stmt_insert->execute();

                // 2. Update user's balance
                $new_balance = $balance - $deposit;
                $stmt_update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt_update->bind_param("di", $new_balance, $user_id);
                $stmt_update->execute();

                $conn->commit();

                $message = "Success! Your deposit of $" . number_format($deposit, 2) . " has been recorded.";
                $message_type = 'success';
                $display_balance = $new_balance; // Update the balance shown on the page

            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $message = "Error: Database transaction failed. Please try again.";
                $message_type = 'error';
            } finally {
                if (isset($stmt_insert)) $stmt_insert->close();
                if (isset($stmt_update)) $stmt_update->close();
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hash Stock Depositor</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        :root { --primary-color: #4a90e2; --success-color: #50e3c2; --error-color: #e35050; --background-color: #f4f7f6; --card-background: #ffffff; --text-color: #333; --light-text-color: #777; }
        body { font-family: 'Inter', sans-serif; background-color: var(--background-color); color: var(--text-color); margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; box-sizing: border-box; }
        .container { width: 100%; max-width: 500px; background-color: var(--card-background); border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); padding: 30px 40px; text-align: center; }
        .header { margin-bottom: 30px; }
        .header h1 { margin: 0 0 10px 0; font-size: 24px; font-weight: 700; }
        .user-info { background-color: var(--background-color); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 16px; }
        .user-info span { font-weight: 500; }
        .balance { color: var(--success-color); font-weight: 700 !important; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        input[type="number"], .file-input-wrapper { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; transition: border-color 0.3s, box-shadow 0.3s; }
        input[type="number"]:focus, .file-input-wrapper:hover { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1); outline: none; }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; cursor: pointer; }
        .file-input-wrapper input[type="file"] { position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; }
        .file-input-wrapper.disabled { background-color: #eee; cursor: not-allowed; color: #999; }
        #fileName { color: var(--light-text-color); }
        .hash-display { font-family: monospace; font-size: 12px; word-break: break-all; margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 4px; color: #555; min-height: 16px; }
        .submit-btn { width: 100%; padding: 15px; background-color: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background-color 0.3s, transform 0.2s; }
        .submit-btn:disabled { background-color: #ccc; cursor: not-allowed; }
        .submit-btn:not(:disabled):hover { background-color: #3a7ac8; transform: translateY(-2px); }
        .message { padding: 15px; margin-top: 20px; border-radius: 8px; font-size: 14px; text-align: center; display: <?php echo empty($message) ? 'none' : 'block'; ?>; }
        .message.success { background-color: #e6f9f5; color: #006f54; border: 1px solid var(--success-color); }
        .message.error { background-color: #fdeaea; color: #a32222; border: 1px solid var(--error-color); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Deposit</h1>
            <div class="user-info">
                <span>User: <strong><?php echo htmlspecialchars($username); ?></strong></span>
                <span>Balance: <strong class="balance">$<?php echo number_format($display_balance, 2); ?></strong></span>
            </div>
        </div>
        <form id="hashForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label for="fileInput">1. Provide a Hash</label>
                <div class="file-input-wrapper" id="fileInputWrapper">
                    <span id="fileName">Click to choose a file...</span>
                    <input type="file" id="fileInput">
                </div>
                <div class="hash-display" id="hashOutput"></div>
            </div>
            <div class="form-group">
                <label for="deposit">2. Enter Deposit Amount</label>
                <input type="number" id="deposit" name="deposit" min="0.01" step="0.01" placeholder="e.g., 50.00" required>
            </div>
            <input type="hidden" id="filehash" name="filehash" value="<?php echo htmlspecialchars($get_hash); ?>">
            <input type="hidden" id="extension" name="extension">
            <button type="submit" id="submitBtn" class="submit-btn" disabled>Provide a Hash to Enable</button>
        </form>
        <div id="messageBox" class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('fileInput');
        const fileInputWrapper = document.getElementById('fileInputWrapper');
        const hashOutput = document.getElementById('hashOutput');
        const fileNameDisplay = document.getElementById('fileName');
        const hiddenHashInput = document.getElementById('filehash');
        const hiddenExtInput = document.getElementById('extension');
        const submitBtn = document.getElementById('submitBtn');

        // --- HASH FROM FILE SELECTION ---
        fileInput.addEventListener('change', async function(event) {
            const file = event.target.files[0];
            if (!file) {
                resetForm();
                return;
            }
            fileNameDisplay.textContent = file.name;
            hashOutput.textContent = 'Calculating hash...';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Calculating Hash...';

            try {
                const extension = file.name.split('.').pop() || '';
                hiddenExtInput.value = extension.toLowerCase();

                const buffer = await file.arrayBuffer();
                const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
                const hashArray = Array.from(new Uint8Array(hashBuffer));
                const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                
                updateFormWithHash(hashHex);

            } catch (error) {
                console.error('Error calculating hash:', error);
                hashOutput.textContent = 'Error calculating hash.';
                resetForm();
            }
        });

        function updateFormWithHash(hash) {
            hashOutput.textContent = hash;
            hiddenHashInput.value = hash;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Deposit';
        }

        function resetForm() {
            fileNameDisplay.textContent = 'Click to choose a file...';
            hashOutput.textContent = '';
            hiddenHashInput.value = '';
            hiddenExtInput.value = '';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Provide a Hash to Enable';
            fileInput.disabled = false;
            fileInputWrapper.classList.remove('disabled');
        }

        // --- CHECK FOR PRE-FILLED HASH FROM GET PARAMETER ---
        const initialHash = hiddenHashInput.value;
        if (initialHash) {
            updateFormWithHash(initialHash);
            fileNameDisplay.textContent = 'Hash provided via URL';
            fileInput.disabled = true;
            fileInputWrapper.classList.add('disabled');
            hiddenExtInput.value = 'get'; // Set extension for GET-based submissions
        }
    });
</script>
</body>
</html>