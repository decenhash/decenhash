<?php
// file_manager.php
// This page allows a logged-in user to view and delete their own files.
session_start();

// --- 1. Database Configuration & Connection ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'decenhash');

// Create Connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/*
-- SQL to create the necessary 'files' table for this page to work.
-- Run this in your database administration tool (like phpMyAdmin).

CREATE TABLE IF NOT EXISTS `files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL,
    `filehash` VARCHAR(64) NOT NULL UNIQUE,
    `type` VARCHAR(100),
    `filesize` VARCHAR(20),
    `date` DATETIME NOT NULL,
    `user` VARCHAR(100) NOT NULL
);

*/


// --- SIMULATION: Manually set a session ID for demonstration ---
// In a real application, this would be set after a successful login.
if (!isset($_SESSION['id'])) {
    // Let's assume user with ID '1' is logged in for this example.
    // The 'user' column in your files table should contain this ID.
    $_SESSION['id'] = '1'; 
}
// --- END SIMULATION ---

$userId = $_SESSION['id'];
$message = ''; // To store feedback messages

// --- 2. Handle POST Request for File Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    if (isset($_POST['file_id'])) {
        $fileIdToDelete = $_POST['file_id'];

        // Prepare a statement to delete the file, ensuring it belongs to the current user
        $stmt = $conn->prepare("DELETE FROM files WHERE id = ? AND user = ?");
        $stmt->bind_param("is", $fileIdToDelete, $userId);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = "File deleted successfully!";
            } else {
                // This can happen if a user tries to delete a file that isn't theirs
                $message = "Error: File not found or you do not have permission to delete it.";
            }
        } else {
            $message = "Error: Could not delete the file. " . $stmt->error;
        }
        $stmt->close();
    }
}


// --- 3. Fetch All Files for the Logged-in User ---
$files = [];
$stmt = $conn->prepare("SELECT id, filename, type, filesize, date FROM files WHERE user = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $files[] = $row;
    }
}
$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8 max-w-5xl">
        <div class="bg-white shadow-lg rounded-2xl p-6 sm:p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Your Files</h1>
            <p class="text-gray-500 mb-6">Here is a list of all files you have uploaded. You can delete them here.</p>

            <!-- Display Message/Notification -->
            <?php if ($message): ?>
                <div id="message-box" class="mb-6 p-4 rounded-lg text-center font-medium
                    <?php echo (strpos(strtolower($message), 'error') !== false) ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Files Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Upload Date</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($files)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">You have not uploaded any files.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?php echo htmlspecialchars($file['filename']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($file['type']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($file['filesize']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo date("M j, Y, g:i a", strtotime($file['date'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this file? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-semibold transition">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script>
        // Automatically hide the success/error message after a few seconds
        const messageBox = document.getElementById('message-box');
        if (messageBox) {
            setTimeout(() => {
                messageBox.style.transition = 'opacity 0.5s ease';
                messageBox.style.opacity = '0';
                setTimeout(() => messageBox.style.display = 'none', 500);
            }, 5000); // 5 seconds
        }
    </script>
</body>
</html>
