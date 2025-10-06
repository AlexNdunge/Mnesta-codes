# M-Pesa Integration Guide for JuaKazi

## 🎯 Overview

This guide will help you integrate M-Pesa Lipa Na M-Pesa Online (STK Push) into your JuaKazi booking system. The integration allows customers to pay the KSh 149 booking fee directly from their M-Pesa accounts.

## 📁 Files Created

1. **mpesa_config.php** - Configuration file for M-Pesa credentials
2. **MpesaAPI.php** - Helper class for M-Pesa API operations
3. **process_booking.php** - Updated with real M-Pesa STK Push
4. **check_payment.php** - Updated to query M-Pesa transaction status
5. **mpesa_callback.php** - Updated to handle M-Pesa callbacks

## 🚀 Quick Start

### Step 1: Get Daraja API Credentials

#### For Testing (Sandbox)
1. Visit [Safaricom Daraja Portal](https://developer.safaricom.co.ke/)
2. Create an account and log in
3. Create a new app (select "Lipa Na M-Pesa Online")
4. You'll receive:
   - **Consumer Key**
   - **Consumer Secret**
   - **Passkey** (for sandbox: `bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919`)
   - **Business Short Code** (for sandbox: `174379`)

#### For Production
1. Complete Safaricom's Go-Live process
2. Submit your production app for approval
3. Receive production credentials

### Step 2: Configure Credentials

Open `mpesa_config.php` and update the credentials:

```php
// For Sandbox Testing
define('MPESA_ENV', 'sandbox');
define('MPESA_SANDBOX_CONSUMER_KEY', 'YOUR_SANDBOX_CONSUMER_KEY');
define('MPESA_SANDBOX_CONSUMER_SECRET', 'YOUR_SANDBOX_CONSUMER_SECRET');

// For Production (when ready)
define('MPESA_ENV', 'production');
define('MPESA_PRODUCTION_CONSUMER_KEY', 'YOUR_PRODUCTION_CONSUMER_KEY');
define('MPESA_PRODUCTION_CONSUMER_SECRET', 'YOUR_PRODUCTION_CONSUMER_SECRET');
define('MPESA_PRODUCTION_SHORTCODE', 'YOUR_PRODUCTION_SHORTCODE');
define('MPESA_PRODUCTION_PASSKEY', 'YOUR_PRODUCTION_PASSKEY');
```

### Step 3: Test the Integration

1. Ensure your XAMPP server is running
2. Navigate to `http://localhost/Juakazi/services.html`
3. Click "Book Now" on any service provider
4. Enter a test phone number: `254708374149` (Sandbox test number)
5. Click "Pay with M-Pesa"
6. You should see a simulated STK Push prompt

**Note:** In sandbox mode, you won't receive an actual STK push on your phone. The API will simulate the response.

### Step 4: Monitor Logs

Check the following log files for debugging:

- `logs/mpesa/mpesa_YYYY-MM-DD.log` - API requests and responses
- `logs/mpesa/callbacks_YYYY-MM-DD.log` - Callback data from Safaricom
- `logs/mpesa/successful_payments_YYYY-MM-DD.log` - Successful payments
- `logs/mpesa/failed_payments_YYYY-MM-DD.log` - Failed payments
- `logs/notifications/provider_notifications.log` - Provider notifications

## 🔧 Configuration Details

### Environment Switching

To switch between sandbox and production:

```php
// In mpesa_config.php
define('MPESA_ENV', 'sandbox');  // For testing
define('MPESA_ENV', 'production');  // For live transactions
```

### Callback URL Configuration

The callback URL is automatically generated based on your domain:

```php
// Default: http://localhost/Juakazi/mpesa_callback.php
// Production: https://yourdomain.com/Juakazi/mpesa_callback.php
```

**IMPORTANT:** For production, you MUST:
1. Use HTTPS (SSL certificate required)
2. Make the callback URL publicly accessible
3. Configure the callback URL in your Daraja app settings

## 📊 How It Works

### Payment Flow

```
1. Customer clicks "Book Now"
   ↓
2. Customer enters phone number
   ↓
3. System creates booking record (status: pending)
   ↓
4. System initiates STK Push via M-Pesa API
   ↓
5. Customer receives prompt on phone
   ↓
6. Customer enters M-Pesa PIN
   ↓
7. M-Pesa processes payment
   ↓
8. M-Pesa sends callback to your server
   ↓
9. System updates booking status (completed/failed)
   ↓
10. Provider receives notification
```

### API Endpoints

#### 1. STK Push (Initiate Payment)
- **File:** `process_booking.php`
- **Method:** POST
- **Parameters:** `provider_id`, `customer_phone`, `notes`
- **Response:** `checkout_request_id`, `booking_id`

#### 2. Payment Status Check
- **File:** `check_payment.php`
- **Method:** GET
- **Parameters:** `checkout_request_id`
- **Response:** `status` (pending/completed/failed)

#### 3. M-Pesa Callback
- **File:** `mpesa_callback.php`
- **Method:** POST (from Safaricom)
- **Purpose:** Receives payment confirmation

## 🔐 Security Best Practices

### 1. Protect Configuration File

Add to `.gitignore`:
```
mpesa_config.php
logs/
```

### 2. Use Environment Variables (Recommended)

Instead of hardcoding credentials, use environment variables:

```php
// In mpesa_config.php
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY'));
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET'));
```

### 3. Enable HTTPS in Production

```apache
# In .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 4. Validate Callback Source

Add IP whitelisting for Safaricom's callback servers:

```php
// In mpesa_callback.php
$allowed_ips = ['196.201.214.200', '196.201.214.206', '196.201.213.114'];
$client_ip = $_SERVER['REMOTE_ADDR'];

if (!in_array($client_ip, $allowed_ips)) {
    http_response_code(403);
    exit('Forbidden');
}
```

## 🧪 Testing Guide

### Sandbox Test Numbers

Use these phone numbers for testing in sandbox:

- `254708374149` - Success scenario
- `254708374150` - Insufficient funds
- `254708374151` - User cancelled

### Test Scenarios

#### 1. Successful Payment
```
Phone: 254708374149
Expected: Payment completes, booking status = completed
```

#### 2. Failed Payment
```
Phone: 254708374150
Expected: Payment fails, booking status = failed
```

#### 3. Cancelled Payment
```
Phone: 254708374151
Expected: User cancels, booking status = failed
```

### Manual Testing Checklist

- [ ] STK Push initiates successfully
- [ ] Booking record created with pending status
- [ ] Payment status polling works
- [ ] Callback updates booking status
- [ ] Provider notification logged
- [ ] Failed payments handled correctly
- [ ] Logs are being written

## 🚨 Troubleshooting

### Issue: "Failed to generate access token"

**Cause:** Invalid consumer key/secret

**Solution:**
1. Verify credentials in `mpesa_config.php`
2. Check if you're using correct environment (sandbox/production)
3. Ensure no extra spaces in credentials

### Issue: "Invalid phone number format"

**Cause:** Phone number not in correct format

**Solution:**
- Use format: `254XXXXXXXXX` (12 digits)
- Remove spaces, dashes, or special characters
- Ensure starts with 254 (Kenya country code)

### Issue: "Callback not received"

**Cause:** Callback URL not accessible

**Solution:**
1. Ensure URL is publicly accessible (use ngrok for local testing)
2. Verify HTTPS is enabled
3. Check firewall settings
4. Verify callback URL in Daraja portal matches your actual URL

### Issue: "STK Push timeout"

**Cause:** Customer didn't enter PIN within time limit

**Solution:**
- This is normal user behavior
- System will mark as failed after timeout
- Customer can retry booking

### Using ngrok for Local Testing

```bash
# Install ngrok
# Download from https://ngrok.com/

# Start ngrok tunnel
ngrok http 80

# Update callback URL in mpesa_config.php
# Example: https://abc123.ngrok.io/Juakazi/mpesa_callback.php
```

## 📱 SMS Notifications (Optional)

To send actual SMS to providers, integrate an SMS gateway:

### Option 1: Africa's Talking

```php
// Install SDK
composer require africastalking/africastalking

// In mpesa_callback.php
use AfricasTalking\SDK\AfricasTalking;

$username = 'YOUR_USERNAME';
$apiKey = 'YOUR_API_KEY';
$AT = new AfricasTalking($username, $apiKey);
$sms = $AT->sms();

$result = $sms->send([
    'to' => $provider['phone'],
    'message' => $notification
]);
```

### Option 2: Twilio

```php
// Install SDK
composer require twilio/sdk

// In mpesa_callback.php
use Twilio\Rest\Client;

$sid = 'YOUR_ACCOUNT_SID';
$token = 'YOUR_AUTH_TOKEN';
$client = new Client($sid, $token);

$message = $client->messages->create(
    $provider['phone'],
    [
        'from' => 'YOUR_TWILIO_NUMBER',
        'body' => $notification
    ]
);
```

## 📈 Production Deployment Checklist

### Pre-Launch

- [ ] Obtain production M-Pesa credentials
- [ ] Update `mpesa_config.php` with production credentials
- [ ] Set `MPESA_ENV` to `'production'`
- [ ] Install SSL certificate (HTTPS required)
- [ ] Configure public callback URL
- [ ] Test with small amounts first
- [ ] Set up error monitoring
- [ ] Configure automatic log rotation
- [ ] Implement SMS notifications
- [ ] Set up payment reconciliation process

### Post-Launch

- [ ] Monitor logs daily for first week
- [ ] Track payment success rate
- [ ] Set up alerts for failed payments
- [ ] Review callback response times
- [ ] Implement payment retry mechanism
- [ ] Create admin dashboard for transactions
- [ ] Set up automated backups

## 🔄 Payment Reconciliation

Create a daily reconciliation script:

```php
// reconcile_payments.php
require_once 'MpesaAPI.php';

$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

// Get all pending payments older than 5 minutes
$stmt = $conn->prepare("
    SELECT checkout_request_id 
    FROM bookings 
    WHERE payment_status = 'pending' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$stmt->execute();
$result = $stmt->get_result();

$mpesa = new MpesaAPI();

while ($row = $result->fetch_assoc()) {
    $queryResponse = $mpesa->stkQuery($row['checkout_request_id']);
    
    if ($queryResponse['success']) {
        // Update status based on query result
        // ... (implement status update logic)
    }
}
```

## 📞 Support & Resources

### Safaricom Daraja
- **Portal:** https://developer.safaricom.co.ke/
- **Documentation:** https://developer.safaricom.co.ke/Documentation
- **Support Email:** developer@safaricom.co.ke
- **Support Phone:** +254 711 082 300

### API Reference
- **OAuth:** Generate access token
- **STK Push:** Initiate payment
- **STK Query:** Check payment status
- **Callback:** Receive payment confirmation

### Useful Links
- [Daraja API Postman Collection](https://developer.safaricom.co.ke/test_credentials)
- [M-Pesa API Errors](https://developer.safaricom.co.ke/docs#errors)
- [Go-Live Process](https://developer.safaricom.co.ke/go-live)

## 💡 Tips & Best Practices

1. **Always log everything** - Logs are crucial for debugging
2. **Handle timeouts gracefully** - Users may not complete payment immediately
3. **Implement retry logic** - Network issues can cause failures
4. **Monitor success rates** - Track and optimize conversion
5. **Test thoroughly** - Use sandbox extensively before going live
6. **Keep credentials secure** - Never commit to version control
7. **Use HTTPS in production** - Required for callbacks
8. **Implement rate limiting** - Prevent abuse
9. **Set up alerts** - Get notified of issues immediately
10. **Document everything** - Make maintenance easier

## 🎓 Next Steps

1. Test the integration in sandbox mode
2. Implement SMS notifications
3. Create admin dashboard for transactions
4. Set up payment reconciliation
5. Apply for production credentials
6. Complete Safaricom's Go-Live process
7. Deploy to production
8. Monitor and optimize

---

**Need Help?** Check the logs in `logs/mpesa/` for detailed error messages and API responses.

**Ready for Production?** Follow the Production Deployment Checklist above.
