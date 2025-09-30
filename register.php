```php
<?php
// Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/register_errors.log');

session_start();

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Log request data for debugging
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'post_data' => $_POST,
    'files' => $_FILES,
    'session' => session_id(),
    'server' => [
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'http_accept' => $_SERVER['HTTP_ACCEPT'] ?? 'not set'
    ]
];

file_put_contents(__DIR__ . '/register_debug.log', 
    print_r($logData, true) . "\n\n", 
    FILE_APPEND
);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Database connection
$config = [
    'servername' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => 'juakazi_db'
];

try {
    $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['dbname']);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Collect and sanitize form data
    $username = trim($_POST['username'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'] ?? '';
    $service = $_POST['service'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $about = trim($_POST['about'] ?? '');

    // Validate required fields
    $errors = [];
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (empty($role)) $errors[] = 'Please select an account type';
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    // Validate passwords match
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    // Validate role
    $validRoles = ['customer', 'provider'];
    if (!in_array($role, $validRoles)) {
        $errors[] = 'Invalid account type';
    }
    
    // If provider, validate service is selected and additional fields
    if ($role === 'provider') {
        if (empty($service)) {
            $errors[] = 'Please select a service type';
        }
        if (empty($phone)) {
            $errors[] = 'Phone number is required for providers';
        }
        if (empty($location)) {
            $errors[] = 'Location is required for providers';
        }
        if (empty($about)) {
            $errors[] = 'About section is required for providers';
        }
    }
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit();
    }
    
    // Check if username or email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $errorMsg = $row['username'] === $username ? 'Username already exists' : 'Email already registered';
        throw new Exception($errorMsg);
    }
    $checkStmt->close();
    
    // Handle profile image upload
    $profileImagePath = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = uniqid('profile_') . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                $profileImagePath = 'uploads/profiles/' . $fileName;
            }
        }
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Prepare and execute insert
        $stmt = $conn->prepare("INSERT INTO users (username, email, role, service, password, phone, location, about, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $username, $email, $role, $service, $hashedPassword, $phone, $location, $about, $profileImagePath);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create user: " . $stmt->error);
        }
        
        $userId = $conn->insert_id;
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        
        // Commit transaction
        $conn->commit();
        
        // Prepare success response
        $response = [
            'success' => true, 
            'message' => 'Account created successfully! Redirecting to services page...',
            'redirect' => 'services.html',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'role' => $role
            ]
        ];
        
        // Log successful registration
        file_put_contents(__DIR__ . '/register_success.log', 
            date('Y-m-d H:i:s') . " - User registered: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n", 
            FILE_APPEND
        );
        
        // Send JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $errorData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ],
        'post_data' => $_POST
    ];
    
    file_put_contents(__DIR__ . '/register_errors.log', 
        print_r($errorData, true) . "\n\n", 
        FILE_APPEND
    );
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.',
        'debug' => (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) ? $e->getMessage() : null
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
```
