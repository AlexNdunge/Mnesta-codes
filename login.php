<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";     
$password = "";         
$dbname = "juakazi";    

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        echo "<script>alert('❌ Account not found. Please create an account.'); window.location.href='signin.html';</script>";
    } else {
        $stmt->bind_result($id, $hashedPassword);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['email'] = $email;
            header("Location: dashboard.php");
            exit();
        } else {
            echo "<script>alert('❌ Invalid password. Please try again.'); window.location.href='signin.html';</script>";
        }
    }

    $stmt->close();
} else {
    http_response_code(405);
    echo "<script>alert('⚠️ Method not allowed. Please use the login form.'); window.location.href='signin.html';</script>";
}

$conn->close();
?>
