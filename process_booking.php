<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get form data
$provider_id = intval($_POST['provider_id'] ?? 0);
$customer_phone = trim($_POST['customer_phone'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$amount = 149; // Fixed booking fee

// Validate inputs
if (empty($provider_id) || empty($customer_phone)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate phone number format
if (!preg_match('/^254[0-9]{9}$/', $customer_phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number format. Use 254XXXXXXXXX']);
    exit;
}

// Get provider details
$stmt = $conn->prepare("SELECT username, service, phone FROM users WHERE id = ? AND role = 'provider'");
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Provider not found']);
    exit;
}

$provider = $result->fetch_assoc();
$stmt->close();

// Get customer ID if logged in
$customer_id = $_SESSION['user_id'] ?? null;

// Create booking record
$stmt = $conn->prepare("INSERT INTO bookings (provider_id, customer_phone, customer_id, service_name, provider_name, amount, notes, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("isisids", $provider_id, $customer_phone, $customer_id, $provider['service'], $provider['username'], $amount, $notes);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create booking']);
    exit;
}

$booking_id = $conn->insert_id;
$stmt->close();

// M-Pesa STK Push Integration
// NOTE: For production, you need to register with Safaricom Daraja API
// This is a simplified version for development

// M-Pesa API Credentials (SANDBOX - Replace with production credentials)
$consumerKey = 'YOUR_CONSUMER_KEY'; // Get from Daraja
$consumerSecret = 'YOUR_CONSUMER_SECRET'; // Get from Daraja
$businessShortCode = '174379'; // Sandbox shortcode
$passkey = 'YOUR_PASSKEY'; // Get from Daraja
$callbackUrl = 'https://yourdomain.com/mpesa_callback.php';

// For development/testing, simulate M-Pesa response
$checkoutRequestId = 'ws_CO_' . time() . rand(1000, 9999);
$merchantRequestId = 'mr_' . time() . rand(1000, 9999);

// Update booking with M-Pesa details
$stmt = $conn->prepare("UPDATE bookings SET checkout_request_id = ?, merchant_request_id = ? WHERE id = ?");
$stmt->bind_param("ssi", $checkoutRequestId, $merchantRequestId, $booking_id);
$stmt->execute();
$stmt->close();

// In production, you would make actual M-Pesa API call here
// For now, we'll simulate the response

// Simulate sending SMS to provider
$providerMessage = "New Booking Alert!\n\n";
$providerMessage .= "Service: " . $provider['service'] . "\n";
$providerMessage .= "Customer Phone: " . $customer_phone . "\n";
$providerMessage .= "Amount: KSh " . $amount . "\n";
if (!empty($notes)) {
    $providerMessage .= "Notes: " . $notes . "\n";
}
$providerMessage .= "\nBooking ID: #" . $booking_id;

// Log the notification (in production, send actual SMS)
file_put_contents(__DIR__ . '/booking_notifications.log', 
    date('Y-m-d H:i:s') . " - Provider: " . $provider['username'] . " (" . $provider['phone'] . ")\n" . $providerMessage . "\n\n", 
    FILE_APPEND
);

$conn->close();

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'M-Pesa prompt sent! Please enter your PIN on your phone.',
    'checkout_request_id' => $checkoutRequestId,
    'booking_id' => $booking_id
]);
?>
