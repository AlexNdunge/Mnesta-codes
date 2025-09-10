<?php
// delete_user.php - Delete user by ID
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "juakazi";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['id'])) {
    die("User ID not specified.");
}
$id = intval($_GET['id']);

$sql = "DELETE FROM users WHERE id=$id";
if ($conn->query($sql)) {
    header("Location: users.php");
    exit();
} else {
    echo "Delete failed: " . $conn->error;
}
$conn->close();
?>
