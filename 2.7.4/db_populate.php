<?php
// Database configuration (same as above)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'decenhash';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sample hashes to insert
$sample_hashes = [
    '5d41402abc4b2a76b9719d911017c592',
    '7d793037a0760186574b0282f2f435e7',
    '7d793037a0760186574b0282f2f435e7', // Repeated
    'e4da3b7fbbce2345d7772b0674a318d5',
    // Add more sample hashes as needed
];

// Insert sample data with varying dates
foreach ($sample_hashes as $hash) {
    // Vary the dates randomly within last 30 days
    $days_ago = rand(0, 29);
    $date = date('Y-m-d H:i:s', strtotime("-$days_ago days"));
    
    $stmt = $conn->prepare("INSERT INTO hash_data (hash, data, created_at) VALUES (?, ?, ?)");
    $data = "Sample data for hash $hash";
    $stmt->bind_param("sss", $hash, $data, $date);
    $stmt->execute();
}

echo "Sample data inserted successfully.";
$conn->close();
?>