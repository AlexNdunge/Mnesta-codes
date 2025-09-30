<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create services table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Services table created successfully or already exists<br>";
    
    // Insert default services if the table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM services");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $services = [
            ['Plumbing', 'plumbing', 'Professional plumbing services', 'fa-faucet'],
            ['Hairdressing', 'hairdressing', 'Professional hairdressing services', 'fa-scissors'],
            ['Gardening', 'gardening', 'Professional gardening services', 'fa-leaf'],
            ['Carpentry', 'carpentry', 'Professional carpentry services', 'fa-hammer'],
            ['Electrician', 'electrician', 'Professional electrical services', 'fa-bolt'],
            ['Painting', 'painting', 'Professional painting services', 'fa-paint-roller'],
            ['Home Cleaning', 'home-cleaning', 'Professional home cleaning services', 'fa-broom'],
            ['Tutoring', 'tutoring', 'Professional tutoring services', 'fa-graduation-cap'],
            ['Pet Care', 'pet-care', 'Professional pet care services', 'fa-paw'],
            ['Beauty', 'beauty', 'Professional beauty services', 'fa-spa'],
            ['Fitness', 'fitness', 'Professional fitness training', 'fa-dumbbell']
        ];
        
        $stmt = $conn->prepare("INSERT INTO services (name, slug, description, icon) VALUES (?, ?, ?, ?)");
        foreach ($services as $service) {
            $stmt->bind_param("ssss", $service[0], $service[1], $service[2], $service[3]);
            $stmt->execute();
        }
        echo "Added default services<br>";
    }
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Update users table to use service slugs instead of names
$conn->query("ALTER TABLE users MODIFY COLUMN service VARCHAR(100)");

echo "<p>Services setup completed. <a href='services.php'>Go to Services Page</a></p>";

$conn->close();
?>
