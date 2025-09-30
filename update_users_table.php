<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Updating Users Table Structure</h2>";

// Check current table structure
echo "<h3>Current Table Structure:</h3>";
$result = $conn->query("DESCRIBE users");
$existingColumns = [];
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Adding Missing Columns:</h3>";

// Columns to add
$columnsToAdd = [
    'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL",
    'location' => "ALTER TABLE users ADD COLUMN location VARCHAR(255) NULL",
    'about' => "ALTER TABLE users ADD COLUMN about TEXT NULL",
    'rating' => "ALTER TABLE users ADD COLUMN rating DECIMAL(3,2) DEFAULT 0.0",
    'review_count' => "ALTER TABLE users ADD COLUMN review_count INT DEFAULT 0",
    'profile_image' => "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL"
];

foreach ($columnsToAdd as $columnName => $sql) {
    if (in_array($columnName, $existingColumns)) {
        echo "<p style='color:orange;'>Column '$columnName' already exists - skipped</p>";
    } else {
        if ($conn->query($sql)) {
            echo "<p style='color:green;'>✓ Added column: $columnName</p>";
        } else {
            echo "<p style='color:red;'>✗ Error adding column '$columnName': " . $conn->error . "</p>";
        }
    }
}

echo "<h3>Updated Table Structure:</h3>";
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();

echo "<br><br>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><a href='add_sample_providers.php'>Add Sample Providers</a></li>";
echo "<li><a href='test_ajax.php'>Test AJAX Connection</a></li>";
echo "<li><a href='services.html'>View Services Page</a></li>";
echo "</ol>";
?>
