<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows > 0) {
    echo "Users table exists. Structure:<br>";
    $columns = $conn->query("DESCRIBE users");
    echo "<pre>";
    while($col = $columns->fetch_assoc()) {
        print_r($col);
    }
    echo "</pre>";
} else {
    echo "Users table does not exist.";
}

$conn->close();
?>
