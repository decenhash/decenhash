<?php
// All-in-one User Dashboard
// Handles display, update, and deletion of user data.
session_start();

// --- 1. Database Configuration & Connection ---
// The 'db.php' include is removed, and connection details are here.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'decenhash'); // Using the specified database name

// Create Connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");


// --- SIMULATION: Manually set a session ID for demonstration ---
// In a real application, this would be set after a successful login.
if (!isset($_SESSION['id'])) {
    // Let's assume user with ID 1 is logged in for this example.
    // In a real scenario, you would redirect to a login page.
     header('Location: login.php');
     exit();    
}
// --- END SIMULATION ---

$userId = $_SESSION['id'];
$message = ''; // To store feedback messages for the user

// --- 2. Handle POST Requests (Update or Delete Actions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- UPDATE ACTION ---
    if ($_POST['action'] === 'update') {
        // Sanitize input from the form
        $username = trim($_POST['username']);
        $mail = trim($_POST['mail']);
        $btc = trim($_POST['btc']);

        // Basic Validation
        if (empty($username) || empty($mail)) {
            $_SESSION['message'] = "Error: Username and Email cannot be empty.";
        } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = "Error: Invalid email format.";
        } else {
            // Check for uniqueness of username and email (excluding the current user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR mail = ?) AND id != ?");
            $stmt->bind_param("ssi", $username, $mail, $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['message'] = "Error: Username or email is already taken by another user.";
            } else {
                // Prepare and execute the UPDATE statement
                $updateStmt = $conn->prepare("UPDATE users SET username = ?, mail = ?, btc = ? WHERE id = ?");
                $btcValue = !empty($btc) ? $btc : NULL; // Store empty BTC as NULL
                $updateStmt->bind_param("sssi", $username, $mail, $btcValue, $userId);

                if ($updateStmt->execute()) {
                    $_SESSION['message'] = "Profile updated successfully!";
                } else {
                    $_SESSION['message'] = "Error: Failed to update profile. " . $updateStmt->error;
                }
                $updateStmt->close();
            }
            $stmt->close();
        }
        // Redirect to the same page to prevent form resubmission on refresh
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // --- DELETE ACTION ---
    if ($_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            // Deletion successful, destroy the session and log the user out.
            session_unset();
            session_destroy();
            // Redirect to a simple confirmation page (you would need to create this file)
            // For this example, we'll just show a simple message.
            echo "<!DOCTYPE html><html><head><title>Account Deleted</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-gray-100 flex items-center justify-center h-screen'><div class='text-center'><h1 class='text-2xl font-bold'>Your account has been permanently deleted.</h1><p class='mt-2 text-gray-600'>We're sorry to see you go.</p></div></body></html>";
            $stmt->close();
            $conn->close();
            exit();
        } else {
            $_SESSION['message'] = "Error: Could not delete account. " . $stmt->error;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}


// --- 3. Fetch User Data for Display ---
// Check for messages set by the actions above
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying it
}

// Prepare to fetch user data from the database
$stmt = $conn->prepare("SELECT username, balance, btc, mail, date FROM users WHERE id = ?");
if ($stmt === false) {
    die("Error preparing the statement: " . $conn->error);
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    // If no user is found, something is wrong. Destroy session and stop.
    session_destroy();
    die("User not found. Please log in again.");
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-4xl">
        <div class="bg-white shadow-lg rounded-2xl p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                    <p class="text-gray-500 mt-1">Manage your profile information and account settings.</p>
                </div>
                <div class="text-sm text-gray-500 mt-4 sm:mt-0">
                    Member since: <?php echo date("F j, Y", strtotime($user['date'])); ?>
                </div>
            </div>

            <!-- Display Message/Notification -->
            <?php if ($message): ?>
                <div id="message-box" class="mb-6 p-4 rounded-lg text-center font-medium
                    <?php echo (strpos(strtolower($message), 'error') !== false) ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Edit User Details Form -->
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="space-y-6">
                <input type="hidden" name="action" value="update">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label for="mail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" id="mail" name="mail" value="<?php echo htmlspecialchars($user['mail']); ?>" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                </div>
                <div>
                    <label for="btc" class="block text-sm font-medium text-gray-700 mb-1">BTC Address (Optional)</label>
                    <input type="text" id="btc" name="btc" value="<?php echo htmlspecialchars($user['btc']); ?>" placeholder="Enter your BTC address" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Balance</label>
                    <div class="w-full px-4 py-3 bg-gray-200 border border-gray-300 rounded-lg text-gray-600">
                        $<?php echo number_format($user['balance'], 2); ?>
                    </div>
                </div>
                <div class="pt-4">
                    <button type="submit" class="w-full sm:w-auto bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition-transform transform hover:scale-105">
                        Save Changes
                    </button>
                </div>
            </form>

            <!-- Delete Account Section -->
            <div class="border-t border-gray-200 mt-8 pt-6">
                <h2 class="text-xl font-bold text-red-600">Delete Account</h2>
                <p class="text-gray-600 mt-2">Permanently delete your account. This action cannot be undone.</p>
                <div class="mt-4">
                     <button onclick="confirmDelete()" class="w-full sm:w-auto bg-red-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-4 focus:ring-red-300 transition-transform transform hover:scale-105">
                        Delete My Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-sm w-full text-center transform transition-all" id="modal-content">
            <h3 class="text-2xl font-bold mb-4">Are you sure?</h3>
            <p class="text-gray-600 mb-6">This will permanently delete your account. You cannot reverse this action.</p>
            <div class="flex justify-center gap-4">
                <button onclick="closeModal()" class="py-2 px-6 bg-gray-200 text-gray-800 font-semibold rounded-lg hover:bg-gray-300">Cancel</button>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="py-2 px-6 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700">Yes, Delete It</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('delete-modal');
        const modalContent = document.getElementById('modal-content');

        function confirmDelete() {
            modal.classList.remove('hidden');
            modalContent.classList.add('scale-100', 'opacity-100');
            modalContent.classList.remove('scale-95', 'opacity-0');
        }

        function closeModal() {
            modalContent.classList.add('scale-95', 'opacity-0');
            modalContent.classList.remove('scale-100', 'opacity-100');
            setTimeout(() => modal.classList.add('hidden'), 200);
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        
        const messageBox = document.getElementById('message-box');
        if (messageBox) {
            setTimeout(() => {
                messageBox.style.transition = 'opacity 0.5s ease';
                messageBox.style.opacity = '0';
                setTimeout(() => messageBox.style.display = 'none', 500);
            }, 5000);
        }
    </script>
</body>
</html>
<?php
// --- 4. Close the database connection ---
$conn->close();
?>
