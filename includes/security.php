<?php
/**
 * Security Functions for JuaKazi
 * CSRF protection, XSS prevention, session management
 */

define('JUAKAZI_APP', true);
require_once __DIR__ . '/../config.php';

/**
 * Initialize secure session
 */
function init_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Set secure flag in production
        if (ENVIRONMENT === 'production') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            session_unset();
            session_destroy();
            header('Location: signin.html?timeout=1');
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF Token
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !isset($_SESSION[CSRF_TOKEN_NAME . '_time'])) {
        return false;
    }
    
    // Check token expiry
    if (time() - $_SESSION[CSRF_TOKEN_NAME . '_time'] > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION[CSRF_TOKEN_NAME . '_time']);
        return false;
    }
    
    // Validate token
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Sanitize output to prevent XSS
 */
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

/**
 * Check login attempts and implement rate limiting
 */
function check_login_attempts($email) {
    $attempt_key = 'login_attempts_' . md5($email);
    $lockout_key = 'login_lockout_' . md5($email);
    
    // Check if account is locked
    if (isset($_SESSION[$lockout_key]) && time() < $_SESSION[$lockout_key]) {
        $remaining = $_SESSION[$lockout_key] - time();
        return [
            'allowed' => false,
            'message' => "Account temporarily locked. Try again in " . ceil($remaining / 60) . " minutes."
        ];
    }
    
    // Check attempts
    if (!isset($_SESSION[$attempt_key])) {
        $_SESSION[$attempt_key] = 0;
    }
    
    if ($_SESSION[$attempt_key] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION[$lockout_key] = time() + LOGIN_LOCKOUT_TIME;
        return [
            'allowed' => false,
            'message' => "Too many failed attempts. Account locked for " . (LOGIN_LOCKOUT_TIME / 60) . " minutes."
        ];
    }
    
    return ['allowed' => true];
}

/**
 * Record failed login attempt
 */
function record_failed_login($email) {
    $attempt_key = 'login_attempts_' . md5($email);
    if (!isset($_SESSION[$attempt_key])) {
        $_SESSION[$attempt_key] = 0;
    }
    $_SESSION[$attempt_key]++;
}

/**
 * Reset login attempts on successful login
 */
function reset_login_attempts($email) {
    $attempt_key = 'login_attempts_' . md5($email);
    $lockout_key = 'login_lockout_' . md5($email);
    unset($_SESSION[$attempt_key]);
    unset($_SESSION[$lockout_key]);
}

/**
 * Validate password strength
 */
function validate_password_strength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

/**
 * Secure database connection
 */
function get_db_connection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            throw new Exception("Database connection failed");
        }
        
        // Set charset to prevent SQL injection
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        if (ENVIRONMENT === 'development') {
            die("Connection error: " . $e->getMessage());
        } else {
            die("Service temporarily unavailable. Please try again later.");
        }
    }
}

/**
 * Log security events
 */
function log_security_event($event_type, $details) {
    $log_file = __DIR__ . '/../logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = sprintf(
        "[%s] %s | IP: %s | User-Agent: %s | Details: %s\n",
        $timestamp,
        $event_type,
        $ip,
        $user_agent,
        json_encode($details)
    );
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Check if user is authenticated
 */
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: signin.html');
        exit();
    }
}

/**
 * Check if user has specific role
 */
function require_role($required_role) {
    require_auth();
    if ($_SESSION['role'] !== $required_role) {
        http_response_code(403);
        die('Access denied');
    }
}
?>
