<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$checkout_request_id = $_GET['checkout_request_id'] ?? '';

if (empty($checkout_request_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing checkout request ID']);
    exit;
}

// Get booking details
$stmt = $conn->prepare("SELECT id, payment_status, provider_id, provider_name, customer_phone, service_name, amount FROM bookings WHERE checkout_request_id = ?");
$stmt->bind_param("s", $checkout_request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// For development: Auto-complete payment after 5 seconds
// In production, this would check actual M-Pesa API status
$booking_time = strtotime($booking['created_at'] ?? 'now');
$current_time = time();
$elapsed_time = $current_time - $booking_time;

if ($booking['payment_status'] === 'pending' && $elapsed_time > 5) {
    // Simulate successful payment
    $mpesa_receipt = 'MPE' . strtoupper(substr(md5($checkout_request_id), 0, 10));
    
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'completed', mpesa_receipt_number = ?, transaction_date = NOW() WHERE checkout_request_id = ?");
    $stmt->bind_param("ss", $mpesa_receipt, $checkout_request_id);
    $stmt->execute();
    $stmt->close();
    
    // Get provider phone number
    $stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $booking['provider_id']);
    $stmt->execute();
    $provider_result = $stmt->get_result();
    $provider = $provider_result->fetch_assoc();
    $stmt->close();
    
    // Send notification to provider
    $notification = "✅ BOOKING CONFIRMED!\n\n";
    $notification .= "Service: " . $booking['service_name'] . "\n";
    $notification .= "Customer: " . $booking['customer_phone'] . "\n";
    $notification .= "Amount Paid: KSh " . $booking['amount'] . "\n";
    $notification .= "M-Pesa Receipt: " . $mpesa_receipt . "\n";
    $notification .= "\nPlease contact the customer to arrange service delivery.";
    
    // Log notification (in production, send actual SMS)
    file_put_contents(__DIR__ . '/provider_notifications.log', 
        date('Y-m-d H:i:s') . " - Provider: " . $booking['provider_name'] . " (" . $provider['phone'] . ")\n" . $notification . "\n\n", 
        FILE_APPEND
    );
    
    echo json_encode([
        'status' => 'completed',
        'message' => 'Payment successful',
        'receipt_number' => $mpesa_receipt
    ]);
} elseif ($booking['payment_status'] === 'completed') {
    echo json_encode([
        'status' => 'completed',
        'message' => 'Payment already completed',
        'receipt_number' => $booking['mpesa_receipt_number']
    ]);
} elseif ($booking['payment_status'] === 'failed') {
    echo json_encode([
        'status' => 'failed',
        'message' => 'Payment failed'
    ]);
} else {
    echo json_encode([
        'status' => 'pending',
        'message' => 'Payment pending'
    ]);
}

$conn->close();
?>
