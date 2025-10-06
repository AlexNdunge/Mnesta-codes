<?php
/**
 * JuaKazi Configuration File
 * Centralized configuration for security and database settings
 */

// Prevent direct access
if (!defined('JUAKAZI_APP')) {
    die('Direct access not permitted');
}

// Environment Configuration
define('ENVIRONMENT', 'development'); // Change to 'production' when live

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'juakazi_db');

// Security Settings
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
define('PASSWORD_MIN_LENGTH', 8);

// CSRF Token Settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRY', 3600);

// File Upload Settings
define('MAX_FILE_SIZE', 2097152); // 2MB in bytes
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Email Configuration (for future use)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('FROM_EMAIL', 'noreply@juakazi.co.ke');
define('FROM_NAME', 'JuaKazi');

// Application URLs
$protocol = (ENVIRONMENT === 'production' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . '/Juakazi/');

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Timezone
date_default_timezone_set('Africa/Nairobi');
?>
