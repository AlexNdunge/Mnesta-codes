<?php
/**
 * M-Pesa API Helper Class
 * Handles all M-Pesa Daraja API interactions
 */

require_once __DIR__ . '/mpesa_config.php';

class MpesaAPI {
    private $accessToken = null;
    private $accessTokenExpiry = null;
    
    /**
     * Log messages to file
     */
    private function log($message, $data = null) {
        if (!MPESA_ENABLE_LOGGING) return;
        
        $logFile = MPESA_LOG_DIR . 'mpesa_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message";
        
        if ($data !== null) {
            $logMessage .= "\n" . print_r($data, true);
        }
        
        $logMessage .= "\n" . str_repeat('-', 80) . "\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Generate M-Pesa access token
     */
    public function generateAccessToken() {
        // Check if we have a valid cached token
        if ($this->accessToken && $this->accessTokenExpiry && time() < $this->accessTokenExpiry) {
            return $this->accessToken;
        }
        
        $this->log('Generating new access token');
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => MPESA_OAUTH_URL,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            $this->log('Access token generation failed - CURL Error', $error);
            return false;
        }
        
        $result = json_decode($response);
        
        if ($httpCode === 200 && isset($result->access_token)) {
            $this->accessToken = $result->access_token;
            // Token expires in 3599 seconds, cache for 3500 seconds to be safe
            $this->accessTokenExpiry = time() + 3500;
            $this->log('Access token generated successfully');
            return $this->accessToken;
        }
        
        $this->log('Access token generation failed', [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        
        return false;
    }
    
    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     */
    public function stkPush($phoneNumber, $amount, $accountReference, $transactionDesc) {
        $accessToken = $this->generateAccessToken();
        
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Failed to generate access token'
            ];
        }
        
        // Format phone number (remove + if present, ensure starts with 254)
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '254' . substr($phoneNumber, 1);
        }
        
        // Validate phone number
        if (!preg_match('/^254[0-9]{9}$/', $phoneNumber)) {
            return [
                'success' => false,
                'message' => 'Invalid phone number format'
            ];
        }
        
        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
        
        $postData = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => MPESA_TRANSACTION_TYPE,
            'Amount' => (int)$amount,
            'PartyA' => $phoneNumber,
            'PartyB' => MPESA_SHORTCODE,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => MPESA_CALLBACK_URL,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc
        ];
        
        $this->log('Initiating STK Push', $postData);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => MPESA_STK_PUSH_URL,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            $this->log('STK Push failed - CURL Error', $error);
            return [
                'success' => false,
                'message' => 'Connection error. Please try again.'
            ];
        }
        
        $result = json_decode($response);
        $this->log('STK Push Response', [
            'http_code' => $httpCode,
            'response' => $result
        ]);
        
        if ($httpCode === 200 && isset($result->ResponseCode) && $result->ResponseCode == '0') {
            return [
                'success' => true,
                'message' => 'STK Push sent successfully',
                'MerchantRequestID' => $result->MerchantRequestID,
                'CheckoutRequestID' => $result->CheckoutRequestID,
                'ResponseDescription' => $result->ResponseDescription
            ];
        }
        
        $errorMessage = isset($result->errorMessage) ? $result->errorMessage : 
                       (isset($result->ResponseDescription) ? $result->ResponseDescription : 'STK Push failed');
        
        return [
            'success' => false,
            'message' => $errorMessage,
            'response' => $result
        ];
    }
    
    /**
     * Query STK Push transaction status
     */
    public function stkQuery($checkoutRequestID) {
        $accessToken = $this->generateAccessToken();
        
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Failed to generate access token'
            ];
        }
        
        $timestamp = date('YmdHis');
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
        
        $postData = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestID
        ];
        
        $this->log('Querying STK Push status', $postData);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => MPESA_STK_QUERY_URL,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            $this->log('STK Query failed - CURL Error', $error);
            return [
                'success' => false,
                'message' => 'Connection error'
            ];
        }
        
        $result = json_decode($response);
        $this->log('STK Query Response', [
            'http_code' => $httpCode,
            'response' => $result
        ]);
        
        if ($httpCode === 200 && isset($result->ResponseCode)) {
            $status = 'pending';
            
            // ResultCode 0 = Success
            if ($result->ResultCode == '0') {
                $status = 'completed';
            }
            // ResultCode 1032 = Cancelled by user
            // ResultCode 1037 = Timeout (user didn't enter PIN)
            // ResultCode 1 = Insufficient funds
            elseif (in_array($result->ResultCode, ['1', '1032', '1037', '2001'])) {
                $status = 'failed';
            }
            
            return [
                'success' => true,
                'status' => $status,
                'ResultCode' => $result->ResultCode,
                'ResultDesc' => $result->ResultDesc ?? '',
                'response' => $result
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Query failed',
            'response' => $result
        ];
    }
    
    /**
     * Validate callback data from M-Pesa
     */
    public function validateCallback($callbackData) {
        $this->log('Callback received', $callbackData);
        
        if (!isset($callbackData['Body']['stkCallback'])) {
            return [
                'valid' => false,
                'message' => 'Invalid callback structure'
            ];
        }
        
        $callback = $callbackData['Body']['stkCallback'];
        
        $result = [
            'valid' => true,
            'MerchantRequestID' => $callback['MerchantRequestID'] ?? '',
            'CheckoutRequestID' => $callback['CheckoutRequestID'] ?? '',
            'ResultCode' => $callback['ResultCode'] ?? null,
            'ResultDesc' => $callback['ResultDesc'] ?? ''
        ];
        
        // If payment was successful, extract metadata
        if ($result['ResultCode'] == 0 && isset($callback['CallbackMetadata']['Item'])) {
            foreach ($callback['CallbackMetadata']['Item'] as $item) {
                $name = $item['Name'];
                $value = $item['Value'] ?? null;
                
                if ($name === 'Amount') {
                    $result['Amount'] = $value;
                } elseif ($name === 'MpesaReceiptNumber') {
                    $result['MpesaReceiptNumber'] = $value;
                } elseif ($name === 'TransactionDate') {
                    $result['TransactionDate'] = $value;
                } elseif ($name === 'PhoneNumber') {
                    $result['PhoneNumber'] = $value;
                }
            }
        }
        
        return $result;
    }
}
?>
