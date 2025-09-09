<?php
// PHP logic to handle the search request.
// This code must be run on a server that supports PHP.
$statusMessage = '';

// The 'hash' function in PHP is used to generate the SHA-256 hash.
// 'sha256' is not a built-in function, so we must use hash('sha256', ...).
function sha256($input) {
    return hash('sha256', $input);
}

// Function to handle the search form submission.
function performSearch() {
    global $statusMessage; // Make the status message variable accessible.

    // Check if the search parameter exists in the URL.
    if (isset($_GET['search'])) {
        $searchInput = trim($_GET['search']);

        // Do nothing if the search input is empty.
        if (empty($searchInput)) {
            return;
        }

        // Check if input is already a valid SHA-256 hash (64 hexadecimal characters).
        $isValidHash = preg_match('/^[a-fA-F0-9]{64}$/', $searchInput);

        if ($isValidHash) {
            // If the input is a valid hash, use it directly.
            $hash = $searchInput;
        } else {
            // Otherwise, generate the SHA-256 hash of the input.
            $hash = sha256($searchInput);
        }

        // Define the expected file path.
        $filePath = "data/$hash/index.html";

        // Check if the file exists on the server.
        if (file_exists($filePath)) {
            // If the file exists, redirect the user's browser to that page.
            header("Location: $filePath");
            exit(); // This is crucial to stop script execution after redirection.
        } else {
            // If the file doesn't exist, set a user-friendly error message.
            $statusMessage = "File not found!";
        }
    }
}

// Run the search function when the page loads.
performSearch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Page</title>
    <!-- Use Tailwind CSS via CDN for styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Use Inter font for a modern look */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
        }

        /* Custom styling for the logo */
        .logo-text {
            background: linear-gradient(90deg, #10b981, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen items-center justify-between p-4 sm:p-8">

    <!-- Main content container -->
    <div class="flex flex-col items-center justify-center flex-1 w-full max-w-2xl px-4">
        
        <!-- Logo/Site Title -->
        <div class="mb-10 text-center">
            <h1 class="text-5xl sm:text-7xl font-bold logo-text">MySite</h1>
            <p class="text-gray-500 mt-2 text-lg sm:text-xl">Discover and explore content.</p>
        </div>

        <!-- Search Form -->
        <form action="categories.php" method="GET" class="w-full flex flex-col sm:flex-row items-center gap-4">
            <input 
                type="text" 
                name="search" 
                placeholder="Enter search term or SHA-256 hash..." 
                class="w-full p-4 sm:p-5 text-lg border-2 border-gray-300 rounded-xl focus:outline-none focus:border-blue-500 transition-colors duration-200 shadow-md"
            >
            <button 
                type="submit" 
                class="w-full sm:w-auto px-8 py-4 sm:py-5 text-lg font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition-colors duration-200 shadow-md transform active:scale-95"
            >
                Search
            </button>
        </form>

        <!-- Status Message Display -->
        <?php if (!empty($statusMessage)): ?>
            <div class="mt-6 p-4 bg-red-100 text-red-700 rounded-lg shadow-inner text-center font-medium">
                <?= htmlspecialchars($statusMessage) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="text-center text-gray-400 text-sm mt-8">
        &copy; <?= date("Y"); ?> All rights reserved.
    </footer>
    
</body>
</html>
