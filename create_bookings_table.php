<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Creating Bookings Table</h2>";

// Create bookings table
$sql = "CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_id INT NULL,
    service_name VARCHAR(255) NOT NULL,
    provider_name VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) DEFAULT 149.00,
    notes TEXT NULL,
    checkout_request_id VARCHAR(255) NULL,
    merchant_request_id VARCHAR(255) NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    mpesa_receipt_number VARCHAR(255) NULL,
    transaction_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "<p style='color:green;'>✓ Bookings table created successfully!</p>";
} else {
    echo "<p style='color:red;'>✗ Error creating table: " . $conn->error . "</p>";
}

// Show table structure
echo "<h3>Bookings Table Structure:</h3>";
$result = $conn->query("DESCRIBE bookings");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();

echo "<br><br><a href='services.html'>Go to Services Page</a>";
?>
