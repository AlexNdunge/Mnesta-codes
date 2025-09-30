<?php
// M-Pesa Callback Handler
// This file receives payment notifications from Safaricom Daraja API

header('Content-Type: application/json');

// Get the callback data
$callbackData = file_get_contents('php://input');

// Log the callback for debugging
file_put_contents(__DIR__ . '/mpesa_callbacks.log', 
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
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Database error']);
    exit;
}

// Extract callback data
$resultCode = $data['Body']['stkCallback']['ResultCode'] ?? null;
$resultDesc = $data['Body']['stkCallback']['ResultDesc'] ?? '';
$merchantRequestID = $data['Body']['stkCallback']['MerchantRequestID'] ?? '';
$checkoutRequestID = $data['Body']['stkCallback']['CheckoutRequestID'] ?? '';

if ($resultCode === 0) {
    // Payment successful
    $callbackMetadata = $data['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];
    
    $amount = 0;
    $mpesaReceiptNumber = '';
    $transactionDate = '';
    $phoneNumber = '';
    
    foreach ($callbackMetadata as $item) {
        if ($item['Name'] === 'Amount') {
            $amount = $item['Value'];
        } elseif ($item['Name'] === 'MpesaReceiptNumber') {
            $mpesaReceiptNumber = $item['Value'];
        } elseif ($item['Name'] === 'TransactionDate') {
            $transactionDate = $item['Value'];
        } elseif ($item['Name'] === 'PhoneNumber') {
            $phoneNumber = $item['Value'];
        }
    }
    
    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'completed', mpesa_receipt_number = ?, transaction_date = NOW() WHERE checkout_request_id = ?");
    $stmt->bind_param("ss", $mpesaReceiptNumber, $checkoutRequestID);
    $stmt->execute();
    $stmt->close();
    
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
        
        // In production, send actual SMS here
        // For now, log it
        file_put_contents(__DIR__ . '/provider_notifications.log', 
            date('Y-m-d H:i:s') . " - Provider: " . $booking['provider_name'] . " (" . $booking['provider_phone'] . ")\n" . $notification . "\n\n", 
            FILE_APPEND
        );
    }
    $stmt->close();
    
} else {
    // Payment failed
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE checkout_request_id = ?");
    $stmt->bind_param("s", $checkoutRequestID);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

// Respond to Safaricom
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
?>
