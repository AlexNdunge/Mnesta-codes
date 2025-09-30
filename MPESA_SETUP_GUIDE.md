# M-Pesa Booking System Setup Guide

## Overview
The booking system allows customers to book service providers and pay KSh 149 via M-Pesa. Upon successful payment, the provider receives a notification.

## Setup Steps

### 1. Create Bookings Table
Run this script to create the bookings database table:
```
http://localhost/Juakazi/create_bookings_table.php
```

### 2. Test the Booking System (Development Mode)
The system is currently in **development/testing mode** which simulates M-Pesa payments:
- Payments auto-complete after 5 seconds
- No actual money is charged
- Notifications are logged to files instead of SMS

To test:
1. Go to `http://localhost/Juakazi/services.html`
2. Click "Book Now" on any provider
3. Enter phone number in format: `254712345678`
4. Click "Pay with M-Pesa"
5. Wait 5-10 seconds for simulated payment confirmation

### 3. Production M-Pesa Integration

For production, you need to:

#### A. Register with Safaricom Daraja API
1. Go to https://developer.safaricom.co.ke/
2. Create an account
3. Create a new app
4. Get your credentials:
   - Consumer Key
   - Consumer Secret
   - Passkey
   - Business Short Code

#### B. Update `process_booking.php`
Replace these placeholders with your actual credentials:
```php
$consumerKey = 'YOUR_CONSUMER_KEY';
$consumerSecret = 'YOUR_CONSUMER_SECRET';
$businessShortCode = 'YOUR_SHORTCODE';
$passkey = 'YOUR_PASSKEY';
$callbackUrl = 'https://yourdomain.com/mpesa_callback.php';
```

#### C. Implement Actual M-Pesa STK Push
Add this code in `process_booking.php` (replace the simulation):

```php
// Generate access token
$url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
$result = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);
$result = json_decode($result);
$access_token = $result->access_token;

// STK Push
$url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
$timestamp = date('YmdHis');
$password = base64_encode($businessShortCode . $passkey . $timestamp);

$curl_post_data = array(
    'BusinessShortCode' => $businessShortCode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $customer_phone,
    'PartyB' => $businessShortCode,
    'PhoneNumber' => $customer_phone,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => 'Booking#' . $booking_id,
    'TransactionDesc' => 'Service Booking Fee'
);

$data_string = json_encode($curl_post_data);
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $access_token));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
$curl_response = curl_exec($curl);
curl_close($curl);

$response = json_decode($curl_response);
```

#### D. SMS Notifications (Production)
To send actual SMS to providers, integrate an SMS gateway like:
- Africa's Talking
- Twilio
- Safaricom SMS API

Example with Africa's Talking:
```php
require_once 'path/to/AfricasTalkingGateway.php';
$username = "YOUR_USERNAME";
$apiKey = "YOUR_API_KEY";
$gateway = new AfricasTalkingGateway($username, $apiKey);

$message = "New Booking Alert! Customer: " . $customer_phone;
$gateway->sendMessage($provider['phone'], $message);
```

## Database Schema

### Bookings Table
```sql
- id: Unique booking ID
- provider_id: ID of the service provider
- customer_phone: Customer's M-Pesa phone number
- customer_id: Customer's user ID (if logged in)
- service_name: Name of the service booked
- provider_name: Name of the provider
- amount: Booking fee (149.00)
- notes: Customer's additional notes
- checkout_request_id: M-Pesa checkout request ID
- merchant_request_id: M-Pesa merchant request ID
- payment_status: pending/completed/failed
- mpesa_receipt_number: M-Pesa transaction receipt
- transaction_date: Date of successful payment
- created_at: Booking creation timestamp
- updated_at: Last update timestamp
```

## Workflow

### 1. Customer Books Service
1. Customer clicks "Book Now" on provider card
2. Modal opens with booking form
3. Customer enters phone number and notes
4. Clicks "Pay with M-Pesa"

### 2. Payment Processing
1. System creates booking record (status: pending)
2. M-Pesa STK push sent to customer's phone
3. Customer enters M-Pesa PIN
4. Payment processed

### 3. Payment Verification
1. System polls payment status every 2 seconds
2. On success:
   - Booking status updated to 'completed'
   - Provider receives SMS notification
   - Customer sees success message
3. On failure:
   - Booking status updated to 'failed'
   - Customer sees error message

### 4. Provider Notification
Provider receives SMS with:
- Service name
- Customer phone number
- Amount paid
- M-Pesa receipt number
- Instructions to contact customer

## Files Created

1. **create_bookings_table.php** - Creates database table
2. **process_booking.php** - Handles booking and M-Pesa payment
3. **check_payment.php** - Checks payment status
4. **mpesa_callback.php** - Receives M-Pesa callbacks
5. **services.html** - Updated with booking button and modal

## Testing Checklist

- [ ] Bookings table created
- [ ] Can open booking modal
- [ ] Phone number validation works
- [ ] Payment simulation completes after 5 seconds
- [ ] Provider notification logged
- [ ] Success message displayed
- [ ] Modal closes after successful payment

## Production Checklist

- [ ] Safaricom Daraja API credentials obtained
- [ ] SSL certificate installed (required for callbacks)
- [ ] Callback URL configured in Daraja portal
- [ ] Actual M-Pesa STK push implemented
- [ ] SMS gateway integrated
- [ ] Error logging implemented
- [ ] Payment reconciliation system in place

## Security Notes

1. **Never commit API keys** to version control
2. **Use environment variables** for sensitive data
3. **Validate all inputs** before processing
4. **Log all transactions** for audit trail
5. **Implement rate limiting** to prevent abuse
6. **Use HTTPS** in production

## Support

For M-Pesa integration issues:
- Safaricom Daraja Support: developer@safaricom.co.ke
- Documentation: https://developer.safaricom.co.ke/Documentation

For SMS integration:
- Africa's Talking: https://africastalking.com/
- Twilio: https://www.twilio.com/
