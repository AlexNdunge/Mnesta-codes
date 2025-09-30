<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

// Check connection
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed', 'message' => $conn->connect_error]);
    exit;
}

// Try to get providers
$sql = "SELECT id, username, email, phone, rating, review_count, profile_image, location, about, service, created_at 
        FROM users 
        WHERE role = 'provider' AND service IS NOT NULL";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['error' => 'Query failed', 'message' => $conn->error]);
    exit;
}

$providers = [];
while ($row = $result->fetch_assoc()) {
    $providers[] = $row;
}

echo json_encode([
    'success' => true,
    'providers' => $providers,
    'total' => count($providers),
    'message' => 'Data fetched successfully'
]);

$conn->close();
?>
