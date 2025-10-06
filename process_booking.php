<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/MpesaAPI.php';

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

// Initialize M-Pesa API
$mpesa = new MpesaAPI();

// Initiate STK Push
$accountReference = 'Booking#' . $booking_id;
$transactionDesc = 'JuaKazi Service Booking Fee';

$stkResponse = $mpesa->stkPush($customer_phone, $amount, $accountReference, $transactionDesc);

if ($stkResponse['success']) {
    // Update booking with M-Pesa details
    $checkoutRequestId = $stkResponse['CheckoutRequestID'];
    $merchantRequestId = $stkResponse['MerchantRequestID'];
    
    $stmt = $conn->prepare("UPDATE bookings SET checkout_request_id = ?, merchant_request_id = ? WHERE id = ?");
    $stmt->bind_param("ssi", $checkoutRequestId, $merchantRequestId, $booking_id);
    $stmt->execute();
    $stmt->close();
    
    // Log notification for provider (in production, send actual SMS)
    $providerMessage = "New Booking Alert!\n\n";
    $providerMessage .= "Service: " . $provider['service'] . "\n";
    $providerMessage .= "Customer Phone: " . $customer_phone . "\n";
    $providerMessage .= "Amount: KSh " . $amount . "\n";
    if (!empty($notes)) {
        $providerMessage .= "Notes: " . $notes . "\n";
    }
    $providerMessage .= "\nBooking ID: #" . $booking_id;
    
    $logDir = __DIR__ . '/logs/notifications/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logDir . 'booking_notifications.log', 
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
} else {
    // STK Push failed, update booking status
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => false,
        'message' => $stkResponse['message'] ?? 'Failed to initiate M-Pesa payment'
    ]);
}
?>
