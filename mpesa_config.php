<?php
/**
 * M-Pesa Configuration File
 * 
 * IMPORTANT: Never commit this file with real credentials to version control
 * Add this file to .gitignore
 */

// Environment: 'sandbox' or 'production'
define('MPESA_ENV', 'sandbox');

// Sandbox Configuration
define('MPESA_SANDBOX_CONSUMER_KEY', 'YOUR_SANDBOX_CONSUMER_KEY');
define('MPESA_SANDBOX_CONSUMER_SECRET', 'YOUR_SANDBOX_CONSUMER_SECRET');
define('MPESA_SANDBOX_SHORTCODE', '174379');
define('MPESA_SANDBOX_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
define('MPESA_SANDBOX_INITIATOR_NAME', 'testapi');
define('MPESA_SANDBOX_INITIATOR_PASSWORD', 'Safaricom999!*!');

// Production Configuration
define('MPESA_PRODUCTION_CONSUMER_KEY', 'YOUR_PRODUCTION_CONSUMER_KEY');
define('MPESA_PRODUCTION_CONSUMER_SECRET', 'YOUR_PRODUCTION_CONSUMER_SECRET');
define('MPESA_PRODUCTION_SHORTCODE', 'YOUR_PRODUCTION_SHORTCODE');
define('MPESA_PRODUCTION_PASSKEY', 'YOUR_PRODUCTION_PASSKEY');
define('MPESA_PRODUCTION_INITIATOR_NAME', 'YOUR_INITIATOR_NAME');
define('MPESA_PRODUCTION_INITIATOR_PASSWORD', 'YOUR_INITIATOR_PASSWORD');

// Active Configuration (based on environment)
define('MPESA_CONSUMER_KEY', MPESA_ENV === 'production' ? MPESA_PRODUCTION_CONSUMER_KEY : MPESA_SANDBOX_CONSUMER_KEY);
define('MPESA_CONSUMER_SECRET', MPESA_ENV === 'production' ? MPESA_PRODUCTION_CONSUMER_SECRET : MPESA_SANDBOX_CONSUMER_SECRET);
define('MPESA_SHORTCODE', MPESA_ENV === 'production' ? MPESA_PRODUCTION_SHORTCODE : MPESA_SANDBOX_SHORTCODE);
define('MPESA_PASSKEY', MPESA_ENV === 'production' ? MPESA_PRODUCTION_PASSKEY : MPESA_SANDBOX_PASSKEY);
define('MPESA_INITIATOR_NAME', MPESA_ENV === 'production' ? MPESA_PRODUCTION_INITIATOR_NAME : MPESA_SANDBOX_INITIATOR_NAME);
define('MPESA_INITIATOR_PASSWORD', MPESA_ENV === 'production' ? MPESA_PRODUCTION_INITIATOR_PASSWORD : MPESA_SANDBOX_INITIATOR_PASSWORD);

// API URLs
define('MPESA_BASE_URL', MPESA_ENV === 'production' 
    ? 'https://api.safaricom.co.ke' 
    : 'https://sandbox.safaricom.co.ke');

define('MPESA_OAUTH_URL', MPESA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials');
define('MPESA_STK_PUSH_URL', MPESA_BASE_URL . '/mpesa/stkpush/v1/processrequest');
define('MPESA_STK_QUERY_URL', MPESA_BASE_URL . '/mpesa/stkpushquery/v1/query');

// Callback URLs (Update with your actual domain)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('MPESA_CALLBACK_URL', $protocol . '://' . $host . '/Juakazi/mpesa_callback.php');
define('MPESA_TIMEOUT_URL', $protocol . '://' . $host . '/Juakazi/mpesa_timeout.php');

// Transaction Settings
define('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline');
define('MPESA_ACCOUNT_REFERENCE', 'JuaKazi');
define('MPESA_TRANSACTION_DESC', 'Service Booking Fee');

// Logging
define('MPESA_LOG_DIR', __DIR__ . '/logs/mpesa/');
define('MPESA_ENABLE_LOGGING', true);

// Create log directory if it doesn't exist
if (MPESA_ENABLE_LOGGING && !file_exists(MPESA_LOG_DIR)) {
    mkdir(MPESA_LOG_DIR, 0755, true);
}
?>
