<?php
define('JUAKAZI_APP', true);
require_once __DIR__ . '/includes/security.php';

// Initialize secure session
init_secure_session();

// Generate and return CSRF token
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'token' => generate_csrf_token()
]);
?>
