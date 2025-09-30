<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli('localhost', 'root', '');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "MySQL server version: " . $conn->server_version;

// Check if database exists
$result = $conn->query("SHOW DATABASES LIKE 'juakazi_db'");
if ($result->num_rows > 0) {
    echo "<br>Database 'juakazi_db' exists!";
} else {
    echo "<br>Database 'juakazi_db' does not exist.";
}

$conn->close();
?>
