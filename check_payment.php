<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/MpesaAPI.php';

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
$stmt = $conn->prepare("SELECT id, payment_status, provider_id, provider_name, customer_phone, service_name, amount, mpesa_receipt_number, created_at FROM bookings WHERE checkout_request_id = ?");
$stmt->bind_param("s", $checkout_request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// If already completed or failed, return that status
if ($booking['payment_status'] === 'completed') {
    echo json_encode([
        'status' => 'completed',
        'message' => 'Payment successful',
        'receipt_number' => $booking['mpesa_receipt_number']
    ]);
    $conn->close();
    exit;
} elseif ($booking['payment_status'] === 'failed') {
    echo json_encode([
        'status' => 'failed',
        'message' => 'Payment failed'
    ]);
    $conn->close();
    exit;
}

// Payment is still pending, query M-Pesa API for status
$mpesa = new MpesaAPI();
$queryResponse = $mpesa->stkQuery($checkout_request_id);

if ($queryResponse['success']) {
    $status = $queryResponse['status'];
    
    if ($status === 'completed') {
        // Payment successful - update database
        $mpesa_receipt = 'SIM' . time(); // In real callback, we get actual receipt number
        
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
        
        $logDir = __DIR__ . '/logs/notifications/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Log notification (in production, send actual SMS)
        file_put_contents($logDir . 'provider_notifications.log', 
            date('Y-m-d H:i:s') . " - Provider: " . $booking['provider_name'] . " (" . $provider['phone'] . ")\n" . $notification . "\n\n", 
            FILE_APPEND
        );
        
        echo json_encode([
            'status' => 'completed',
            'message' => 'Payment successful',
            'receipt_number' => $mpesa_receipt
        ]);
    } elseif ($status === 'failed') {
        // Payment failed - update database
        $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE checkout_request_id = ?");
        $stmt->bind_param("s", $checkout_request_id);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'status' => 'failed',
            'message' => $queryResponse['ResultDesc'] ?? 'Payment failed'
        ]);
    } else {
        // Still pending
        echo json_encode([
            'status' => 'pending',
            'message' => 'Payment pending'
        ]);
    }
} else {
    // Query failed, return pending status
    echo json_encode([
        'status' => 'pending',
        'message' => 'Payment pending'
    ]);
}

$conn->close();
?>
