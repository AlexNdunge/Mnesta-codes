<?php
// Debug script to check services.php issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Connection: SUCCESS</h2>";

// Check if users table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableCheck->num_rows > 0) {
    echo "<h3>Users table: EXISTS</h3>";
} else {
    echo "<h3 style='color:red;'>Users table: DOES NOT EXIST</h3>";
}

// Check for providers
$providerCheck = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'provider'");
if ($providerCheck) {
    $row = $providerCheck->fetch_assoc();
    echo "<h3>Total providers: " . $row['count'] . "</h3>";
} else {
    echo "<h3 style='color:red;'>Error checking providers: " . $conn->error . "</h3>";
}

// Check for providers with services
$serviceCheck = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'provider' AND service IS NOT NULL");
if ($serviceCheck) {
    $row = $serviceCheck->fetch_assoc();
    echo "<h3>Providers with services: " . $row['count'] . "</h3>";
} else {
    echo "<h3 style='color:red;'>Error checking services: " . $conn->error . "</h3>";
}

// Check table structure
echo "<h3>Users table structure:</h3>";
$structure = $conn->query("DESCRIBE users");
if ($structure) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($field = $structure->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $field['Null'] . "</td>";
        echo "<td>" . $field['Key'] . "</td>";
        echo "<td>" . $field['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Try to fetch providers
echo "<h3>Sample provider data:</h3>";
$providers = $conn->query("SELECT id, username, email, role, service FROM users WHERE role = 'provider' LIMIT 5");
if ($providers && $providers->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Service</th></tr>";
    while ($provider = $providers->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $provider['id'] . "</td>";
        echo "<td>" . $provider['username'] . "</td>";
        echo "<td>" . $provider['email'] . "</td>";
        echo "<td>" . $provider['role'] . "</td>";
        echo "<td>" . ($provider['service'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No providers found or error: " . $conn->error . "</p>";
}

// Test AJAX response
echo "<h3>Test AJAX Response:</h3>";
$sql = "SELECT id, username, email, phone, rating, review_count, profile_image, location, about, service, created_at 
        FROM users 
        WHERE role = 'provider' AND service IS NOT NULL";
$result = $conn->query($sql);

$testProviders = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $testProviders[] = $row;
    }
}

echo "<pre>";
echo json_encode([
    'providers' => $testProviders,
    'total' => count($testProviders)
], JSON_PRETTY_PRINT);
echo "</pre>";

$conn->close();
?>
