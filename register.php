<?php
// --- Database connection ---
$servername = "localhost";  // usually "localhost"
$dbusername = "root";       // XAMPP/WAMP default
$dbpassword = "";           // XAMPP/WAMP default (leave empty)
$dbname = "juakazi_db";     // database we created

session_start();
$servername = "localhost";  // usually "localhost"
$dbusername = "root";       // XAMPP/WAMP default
$dbpassword = "";           // XAMPP/WAMP default (leave empty)
$dbname = "Juakazi";        // use the correct database name
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Collect form data ---
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$role = $_POST['role'];
$service = isset($_POST['service']) ? $_POST['service'] : NULL;
$password = $_POST['password'];
$confirmPassword = $_POST['confirmPassword'];

// --- Validate passwords ---
if ($password !== $confirmPassword) {
    die("Error: Passwords do not match.");
}

// --- Hash password ---
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// --- Insert user ---
$stmt = $conn->prepare("INSERT INTO users (username, email, role, service, password) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $username, $email, $role, $service, $hashedPassword);

if ($stmt->execute()) {
    // Log the user in automatically after registration
    $_SESSION['user_id'] = $conn->insert_id;
    $_SESSION['email'] = $email;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    header("Location: dashboard.php"); // redirect to dashboard after signup
    exit();
} else {
    echo "❌ Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
