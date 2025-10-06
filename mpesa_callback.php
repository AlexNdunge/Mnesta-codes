<?php
/**
 * M-Pesa Callback Handler
 * This file receives payment notifications from Safaricom Daraja API
 * 
 * IMPORTANT: This URL must be publicly accessible via HTTPS
 * Configure this URL in your Daraja API app settings
 */

header('Content-Type: application/json');

require_once __DIR__ . '/MpesaAPI.php';

// Get the callback data
$callbackData = file_get_contents('php://input');

// Create log directory if it doesn't exist
$logDir = __DIR__ . '/logs/mpesa/';
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log the callback for debugging
file_put_contents($logDir . 'callbacks_' . date('Y-m-d') . '.log', 
    date('Y-m-d H:i:s') . " - Callback received:\n" . $callbackData . "\n\n", 
    FILE_APPEND
);

// Parse the JSON data
$data = json_decode($callbackData, true);

if (!$data) {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid data']);
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

if ($conn->connect_error) {
    file_put_contents($logDir . 'errors_' . date('Y-m-d') . '.log', 
        date('Y-m-d H:i:s') . " - Database connection failed\n", 
        FILE_APPEND
    );
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Database error']);
    exit;
}

// Validate and extract callback data using MpesaAPI helper
$mpesa = new MpesaAPI();
$callbackResult = $mpesa->validateCallback($data);

if (!$callbackResult['valid']) {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => $callbackResult['message']]);
    exit;
}

$resultCode = $callbackResult['ResultCode'];
$checkoutRequestID = $callbackResult['CheckoutRequestID'];
$merchantRequestID = $callbackResult['MerchantRequestID'];

if ($resultCode == 0) {
    // Payment successful
    $mpesaReceiptNumber = $callbackResult['MpesaReceiptNumber'] ?? '';
    $amount = $callbackResult['Amount'] ?? 0;
    $phoneNumber = $callbackResult['PhoneNumber'] ?? '';
    
    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'completed', mpesa_receipt_number = ?, transaction_date = NOW() WHERE checkout_request_id = ?");
    $stmt->bind_param("ss", $mpesaReceiptNumber, $checkoutRequestID);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected > 0) {
        // Get booking details to notify provider
        $stmt = $conn->prepare("SELECT b.*, u.phone as provider_phone FROM bookings b JOIN users u ON b.provider_id = u.id WHERE b.checkout_request_id = ?");
        $stmt->bind_param("s", $checkoutRequestID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $booking = $result->fetch_assoc();
            
            // Send notification to provider
            $notification = "✅ NEW BOOKING CONFIRMED!\n\n";
            $notification .= "Service: " . $booking['service_name'] . "\n";
            $notification .= "Customer: " . $booking['customer_phone'] . "\n";
            $notification .= "Amount: KSh " . $amount . "\n";
            $notification .= "M-Pesa Receipt: " . $mpesaReceiptNumber . "\n";
            $notification .= "\nPlease contact the customer to arrange service delivery.";
            
            $notifDir = __DIR__ . '/logs/notifications/';
            if (!file_exists($notifDir)) {
                mkdir($notifDir, 0755, true);
            }
            
            // In production, integrate SMS gateway here
            // Example: sendSMS($booking['provider_phone'], $notification);
            
            // For now, log it
            file_put_contents($notifDir . 'provider_notifications.log', 
                date('Y-m-d H:i:s') . " - Provider: " . $booking['provider_name'] . " (" . $booking['provider_phone'] . ")\n" . $notification . "\n\n", 
                FILE_APPEND
            );
        }
        $stmt->close();
        
        // Log successful payment
        file_put_contents($logDir . 'successful_payments_' . date('Y-m-d') . '.log', 
            date('Y-m-d H:i:s') . " - Payment completed: Receipt=$mpesaReceiptNumber, Amount=$amount, Phone=$phoneNumber\n", 
            FILE_APPEND
        );
    }
    
} else {
    // Payment failed or cancelled
    $resultDesc = $callbackResult['ResultDesc'] ?? 'Payment failed';
    
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE checkout_request_id = ?");
    $stmt->bind_param("s", $checkoutRequestID);
    $stmt->execute();
    $stmt->close();
    
    // Log failed payment
    file_put_contents($logDir . 'failed_payments_' . date('Y-m-d') . '.log', 
        date('Y-m-d H:i:s') . " - Payment failed: Code=$resultCode, Desc=$resultDesc, CheckoutID=$checkoutRequestID\n", 
        FILE_APPEND
    );
}

$conn->close();

// Respond to Safaricom
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
?>
