<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";     
$password = "";         
$dbname = "juakazi_db";    

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Check if user exists and get their information
    $stmt = $conn->prepare("SELECT id, username, email, role, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        // User not found - redirect to signup
        echo "<script>
            alert('❌ Account not found. Please create an account first.');
            window.location.href='signup.html';
        </script>";
    } else {
        $stmt->bind_result($id, $username, $userEmail, $role, $hashedPassword);
        $stmt->fetch();

        // Verify password
        if (password_verify($password, $hashedPassword)) {
            // Password correct - set session and redirect to services page
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $userEmail;
            $_SESSION['role'] = $role;
            
            echo "<script>
                alert('✅ Login successful! Welcome back, " . htmlspecialchars($username) . "!');
                window.location.href='services.html';
            </script>";
            exit();
        } else {
            // Wrong password
            echo "<script>
                alert('❌ Invalid password. Please try again.');
                window.location.href='signin.html';
            </script>";
        }
    }

    $stmt->close();
} else {
    http_response_code(405);
    echo "<script>
        alert('⚠️ Method not allowed. Please use the login form.');
        window.location.href='signin.html';
    </script>";
}

$conn->close();
?>
