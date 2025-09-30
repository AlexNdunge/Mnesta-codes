<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Adding Sample Service Providers</h2>";

// Sample providers data
$sampleProviders = [
    [
        'username' => 'john_plumber',
        'email' => 'john@example.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'provider',
        'service' => 'Plumbing',
        'phone' => '+254712345678',
        'location' => 'Nairobi, Kenya',
        'about' => 'Professional plumber with 10+ years of experience. Available for all plumbing needs.',
        'rating' => 4.5,
        'review_count' => 23
    ],
    [
        'username' => 'mary_cleaner',
        'email' => 'mary@example.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'provider',
        'service' => 'Home Cleaning',
        'phone' => '+254723456789',
        'location' => 'Mombasa, Kenya',
        'about' => 'Experienced home cleaner providing top-quality cleaning services.',
        'rating' => 4.8,
        'review_count' => 45
    ],
    [
        'username' => 'david_electrician',
        'email' => 'david@example.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'provider',
        'service' => 'Electrical',
        'phone' => '+254734567890',
        'location' => 'Kisumu, Kenya',
        'about' => 'Certified electrician for residential and commercial electrical work.',
        'rating' => 4.7,
        'review_count' => 31
    ],
    [
        'username' => 'sarah_tutor',
        'email' => 'sarah@example.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'provider',
        'service' => 'Tutoring',
        'phone' => '+254745678901',
        'location' => 'Nairobi, Kenya',
        'about' => 'Mathematics and Science tutor for high school and college students.',
        'rating' => 4.9,
        'review_count' => 67
    ],
    [
        'username' => 'james_carpenter',
        'email' => 'james@example.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'provider',
        'service' => 'Carpentry',
        'phone' => '+254756789012',
        'location' => 'Nakuru, Kenya',
        'about' => 'Skilled carpenter specializing in custom furniture and home repairs.',
        'rating' => 4.6,
        'review_count' => 28
    ],
    [
        'username' => 'grace_gardener',
        'email' => 'grace@example.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'provider',
        'service' => 'Gardening',
        'phone' => '+254767890123',
        'location' => 'Eldoret, Kenya',
        'about' => 'Professional gardener offering landscaping and garden maintenance.',
        'rating' => 4.4,
        'review_count' => 19
    ]
];

$added = 0;
$skipped = 0;

foreach ($sampleProviders as $provider) {
    // Check if user already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $provider['email']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo "<p style='color:orange;'>Skipped: {$provider['username']} (already exists)</p>";
        $skipped++;
        $checkStmt->close();
        continue;
    }
    $checkStmt->close();
    
    // Insert provider
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, service, phone, location, about, rating, review_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "ssssssssdi",
        $provider['username'],
        $provider['email'],
        $provider['password'],
        $provider['role'],
        $provider['service'],
        $provider['phone'],
        $provider['location'],
        $provider['about'],
        $provider['rating'],
        $provider['review_count']
    );
    
    if ($stmt->execute()) {
        echo "<p style='color:green;'>Added: {$provider['username']} - {$provider['service']}</p>";
        $added++;
    } else {
        echo "<p style='color:red;'>Error adding {$provider['username']}: " . $stmt->error . "</p>";
    }
    
    $stmt->close();
}

echo "<h3>Summary:</h3>";
echo "<p>Added: $added providers</p>";
echo "<p>Skipped: $skipped providers</p>";

// Show all providers
echo "<h3>All Providers in Database:</h3>";
$result = $conn->query("SELECT username, service, location, rating FROM users WHERE role = 'provider'");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Username</th><th>Service</th><th>Location</th><th>Rating</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td>" . htmlspecialchars($row['service'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['location'] ?? 'N/A') . "</td>";
        echo "<td>" . $row['rating'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No providers found.</p>";
}

$conn->close();

echo "<br><br><a href='services.html'>Go to Services Page</a> | <a href='debug_services.php'>Debug Services</a>";
?>
