<?php
// Define constants for file paths and a salt for hashing.
// In a production environment, store the salt securely (e.g., environment variable, separate config file).
const HASH_DIR = 'hash_check/';
const SERVERS_FILE = 'servers.txt';
const SALT = 'your_strong_and_unique_secret_salt_here_replace_this_!'; // IMPORTANT: Replace with a strong, unique salt.

// Ensure the hash_check directory exists.
if (!is_dir(HASH_DIR)) {
    mkdir(HASH_DIR, 0755, true); // Create directory with read/write permissions
}

/**
 * Generates a SHA256 hash of the input data with a salt.
 *
 * @param string $data The data to hash.
 * @param string $salt The salt to prepend to the data before hashing.
 * @return string The SHA256 hash.
 */
function generateHashedFileName($data, $salt) {
    return hash('sha256', $salt . $data);
}

/**
 * Checks if a given URL is online (reachable).
 * Uses cURL for a more robust check, including timeouts.
 *
 * @param string $url The URL to check.
 * @return bool True if the URL is online, false otherwise.
 */
function isUrlOnline($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10-second connection timeout
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the transfer as a string
    curl_setopt($ch, CURLOPT_NOBODY, true); // Don't download the body, just the header
    curl_setopt($ch, CURLOPT_HEADER, true); // Include header in output
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // IMPORTANT: For testing, avoid SSL certificate verification issues.
                                                  // In production, configure proper CA certificates.
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Consider 200 OK, 301 Moved Permanently, 302 Found, etc., as online.
    return ($httpCode >= 200 && $httpCode < 400);
}

// Handle AJAX POST requests from the client.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    header('Content-Type: application/json'); // Set header for JSON response

    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL); // Sanitize the URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid URL format.']);
        exit;
    }

    $userIp = $_SERVER['REMOTE_ADDR']; // Get the user's IP address
    $urlHost = parse_url($url, PHP_URL_HOST); // Extract the host from the URL
    $urlIp = '';

    // Resolve the URL's IP address.
    if ($urlHost) {
        $urlIp = gethostbyname($urlHost);
        // If gethostbyname fails, it returns the hostname itself, check if it's still a hostname.
        if ($urlIp === $urlHost && !filter_var($urlIp, FILTER_VALIDATE_IP)) {
            // Couldn't resolve IP, consider it a non-critical error for this check
            $urlIp = 'unknown'; // Assign a default to prevent empty hash
        }
    } else {
        $urlIp = 'unknown'; // Assign a default if no host is found
    }

    // Generate hashes for user IP, URL, and URL's IP.
    $userIpHash = generateHashedFileName($userIp, SALT);
    $urlHash = generateHashedFileName($url, SALT);
    $urlIpHash = generateHashedFileName($urlIp, SALT);

    $hashFiles = [
        HASH_DIR . $userIpHash,
        HASH_DIR . $urlHash,
        HASH_DIR . $urlIpHash
    ];

    $alreadyProcessed = false;
    foreach ($hashFiles as $file) {
        if (file_exists($file)) {
            $alreadyProcessed = true;
            break;
        }
    }

    if ($alreadyProcessed) {
        echo json_encode(['success' => false, 'message' => 'This URL or a related entry has already been processed.']);
        exit;
    }

    // If not already processed, check if the URL is online.
    if (isUrlOnline($url)) {
        // Append URL to servers.txt.
        if (file_put_contents(SERVERS_FILE, PHP_EOL . $url, FILE_APPEND | LOCK_EX) === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to write URL to servers.txt. Check permissions.']);
            exit;
        }

        // Create the hash marker files in hash_check directory.
        foreach ($hashFiles as $file) {
            if (file_put_contents($file, '') === false) {
                // Log this error, but don't stop the process as the URL was already added.
                // In a real app, you might want to roll back or have a more robust error handling.
                error_log("Failed to create hash file: " . $file);
            }
        }
        echo json_encode(['success' => true, 'message' => 'URL is online and added to servers.txt!', 'url' => $url]);
    } else {
        echo json_encode(['success' => false, 'message' => 'URL is offline or unreachable.']);
    }
    exit; // Terminate script after sending JSON response
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Online Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 500px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4a5568;
        }
        input[type="text"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e0;
            border-radius: 0.5rem;
            font-size: 1rem;
            color: #2d3748;
            outline: none;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        input[type="text"]:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            background-color: #6366f1;
            color: #ffffff;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        button:hover {
            background-color: #4f46e5;
            transform: translateY(-1px);
        }
        button:active {
            transform: translateY(0);
        }
        button:disabled {
            background-color: #a7a9be;
            cursor: not-allowed;
            box-shadow: none;
        }
        #message {
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-align: center;
            display: none; /* Hidden by default */
        }
        #message.success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        #message.error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .loading-spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #ffffff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin-left: 0.75rem;
            display: none; /* Hidden by default */
        }
        button:disabled .loading-spinner {
            display: block; /* Show when button is disabled */
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 640px) {
            .container {
                padding: 1.5rem;
                border-radius: 0.75rem;
            }
            input[type="text"] {
                padding: 0.625rem 0.875rem;
                font-size: 0.95rem;
            }
            button {
                padding: 0.625rem 1.25rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Servers</h1>
        <p class="text-gray-600 mb-8">Enter a URL to check if it's online and add it to our server list.</p>
        <form id="urlForm">
            <div class="form-group">
                <label for="urlInput">URL:</label>
                <input type="text" id="urlInput" name="url" placeholder="e.g., https://www.example.com" required>
            </div>
            <button type="submit" id="submitBtn">
                Check URL
                <div class="loading-spinner" id="spinner"></div>
            </button>
        </form>
        <div id="message"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const urlForm = document.getElementById('urlForm');
            const urlInput = document.getElementById('urlInput');
            const submitBtn = document.getElementById('submitBtn');
            const messageDiv = document.getElementById('message');
            const spinner = document.getElementById('spinner');

            urlForm.addEventListener('submit', async (e) => {
                e.preventDefault(); // Prevent default form submission

                const url = urlInput.value.trim();
                if (!url) {
                    showMessage('Please enter a URL.', 'error');
                    return;
                }

                // Disable button and show spinner
                submitBtn.disabled = true;
                spinner.style.display = 'block';
                messageDiv.style.display = 'none'; // Hide previous message

                try {
                    const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `url=${encodeURIComponent(url)}` // Send URL as form data
                    });

                    const data = await response.json(); // Parse JSON response

                    if (data.success) {
                        showMessage(data.message, 'success');
                        urlInput.value = ''; // Clear input on success
                    } else {
                        showMessage(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showMessage('An unexpected error occurred. Please try again.', 'error');
                } finally {
                    // Re-enable button and hide spinner
                    submitBtn.disabled = false;
                    spinner.style.display = 'none';
                }
            });

            /**
             * Displays a message in the message div.
             * @param {string} msg The message text.
             * @param {string} type 'success' or 'error' for styling.
             */
            function showMessage(msg, type) {
                messageDiv.textContent = msg;
                messageDiv.className = ''; // Clear existing classes
                messageDiv.classList.add('message', type); // Add base and type classes
                messageDiv.style.display = 'block'; // Make sure it's visible
            }
        });
    </script>
</body>
</html>
