<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root'; // Replace with your MySQL username
$db_pass = ''; // Replace with your MySQL password
$db_name = 'decenhash';

// Create connection (without specifying database)
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if not exists
$create_db_sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($create_db_sql) === TRUE) {
    echo "Database checked/created successfully<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($db_name);

// Create table if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS hash_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hash VARCHAR(64) NOT NULL,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (hash),
    INDEX (created_at)
)";

if ($conn->query($create_table_sql) === TRUE) {
    echo "Table checked/created successfully<br>";
} else {
    die("Error creating table: " . $conn->error);
}

// Close connection
$conn->close();

echo "Database setup complete. You can now use the decenhash database with the hash_data table.";
?>