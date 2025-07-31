<?php
// ALWAYS start the session at the very top of the script.
session_start();

header('Content-Type: application/json');

// Define the directory where user files will be saved
$usersDirectory = __DIR__ . '/users/';

// Ensure the users directory exists
if (!is_dir($usersDirectory)) {
    mkdir($usersDirectory, 0755, true);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Action not specified.']);
    exit;
}

function generateUserHash($username) {
    return hash('sha256', strtolower($username));
}

// Server-side password validation
function validatePassword($password) {
    $minLength = 8;
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?~]/', $password) || strlen($password) < $minLength) {
        return false;
    }
    return true;
}

switch ($input['action']) {
    case 'register':
        // (Registration logic remains the same as before)
        if (!isset($input['username'], $input['password'], $input['email'], $input['key'])) {
            echo json_encode(['success' => false, 'message' => 'Incomplete registration data.']);
            exit;
        }

        $username = trim($input['username']);
        $password = $input['password'];
        $email = trim($input['email']);
        $key = trim($input['key']);

        if (empty($username) || empty($password) || empty($email) || empty($key)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        if (!validatePassword($password)) {
            echo json_encode(['success' => false, 'message' => 'Password must have at least 8 characters, one uppercase letter, one number, and one special symbol.']);
            exit;
        }

        $userHash = generateUserHash($username);
        $userFile = $usersDirectory . $userHash . '.json';

        if (file_exists($userFile)) {
            echo json_encode(['success' => false, 'message' => 'User already exists.']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $userData = [
            'username' => $username,
            'password' => $hashedPassword,
            'email' => $email,
            'key' => $key
        ];

        $fileHandle = fopen($userFile, 'w');
        if ($fileHandle) {
            fwrite($fileHandle, json_encode($userData, JSON_PRETTY_PRINT));
            fclose($fileHandle);
            echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving user data.']);
        }
        break;

    case 'login':
        if (!isset($input['username'], $input['password'])) {
            echo json_encode(['success' => false, 'message' => 'Incomplete login data.']);
            exit;
        }

        $username = trim($input['username']);
        $password = $input['password'];

        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
            exit;
        }

        $userHash = generateUserHash($username);
        $userFile = $usersDirectory . $userHash . '.json';

        if (!file_exists($userFile)) {
            echo json_encode(['success' => false, 'message' => 'Incorrect username or password.']);
            exit;
        }

        $userData = json_decode(file_get_contents($userFile), true);

        if (password_verify($password, $userData['password'])) {
            // --- CORRECTED LOGIC ---
            // 1. Set the session variable
            $_SESSION['user'] = $userData['username'];

            // 2. Send a success response with the redirect URL for JavaScript to handle
            echo json_encode(['success' => true, 'redirect' => 'index.php']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect username or password.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>