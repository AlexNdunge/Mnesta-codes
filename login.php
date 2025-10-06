<?php
define('JUAKAZI_APP', true);
require_once __DIR__ . '/includes/security.php';

// Initialize secure session
init_secure_session();

// Get database connection
$conn = get_db_connection();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        log_security_event('CSRF_FAILED', ['email' => $email, 'action' => 'login']);
        echo "<script>
            alert('⚠️ Security validation failed. Please try again.');
            window.location.href='signin.html';
        </script>";
        exit();
    }
    
    // Check login attempts
    $attempt_check = check_login_attempts($email);
    if (!$attempt_check['allowed']) {
        log_security_event('LOGIN_LOCKED', ['email' => $email]);
        echo "<script>
            alert('" . addslashes($attempt_check['message']) . "');
            window.location.href='signin.html';
        </script>";
        exit();
    }

    // Check if user exists and get their information
    $stmt = $conn->prepare("SELECT id, username, email, role, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        // User not found - record failed attempt
        record_failed_login($email);
        log_security_event('LOGIN_FAILED', ['email' => $email, 'reason' => 'user_not_found']);
        echo "<script>
            alert('❌ Invalid email or password.');
            window.location.href='signin.html';
        </script>";
    } else {
        $stmt->bind_result($id, $username, $userEmail, $role, $hashedPassword);
        $stmt->fetch();

        // Verify password
        if (password_verify($password, $hashedPassword)) {
            // Password correct - reset login attempts
            reset_login_attempts($email);
            
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $userEmail;
            $_SESSION['role'] = $role;
            $_SESSION['login_time'] = time();
            
            // Log successful login
            log_security_event('LOGIN_SUCCESS', ['user_id' => $id, 'email' => $email]);
            
            echo "<script>
                alert('✅ Login successful! Welcome back, " . sanitize_output($username) . "!');
                window.location.href='services.html';
            </script>";
            exit();
        } else {
            // Wrong password - record failed attempt
            record_failed_login($email);
            log_security_event('LOGIN_FAILED', ['email' => $email, 'reason' => 'invalid_password']);
            echo "<script>
                alert('❌ Invalid email or password.');
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
