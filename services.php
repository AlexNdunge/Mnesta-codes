<?php
// Start session and include necessary files
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production, log them instead

// Database connection
$conn = new mysqli('localhost', 'root', '', 'juakazi_db');

// Check connection
if ($conn->connect_error) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed', 'message' => $conn->connect_error]);
        exit;
    }
    die("Connection failed: " . $conn->connect_error);
}

// Get unique service categories from users table
$categories = [];
$catResult = $conn->query("SELECT DISTINCT service FROM users WHERE role = 'provider' AND service IS NOT NULL ORDER BY service ASC");
if ($catResult && $catResult->num_rows > 0) {
    while ($catRow = $catResult->fetch_assoc()) {
        if (!empty($catRow['service'])) {
            $categories[] = [
                'slug' => strtolower(str_replace(' ', '-', $catRow['service'])),
                'name' => $catRow['service']
            ];
        }
    }
}

// Check if this is an AJAX request for categories only
if (isset($_GET['get_categories'])) {
    header('Content-Type: application/json');
    echo json_encode(['categories' => $categories]);
    $conn->close();
    exit;
}

// Handle search/filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Build SQL for providers
$sql = "SELECT id, username, email, phone, rating, review_count, profile_image, location, about, service, created_at 
        FROM users 
        WHERE role = 'provider' AND service IS NOT NULL";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " AND (username LIKE ? OR service LIKE ?)";
    $searchParam = "%" . $search . "%";
    $params[] = &$searchParam;
    $params[] = &$searchParam;
    $types .= "ss";
}

if ($category !== '') {
    // Match against the actual service name, not the slug
    $categoryName = str_replace('-', ' ', $category);
    $sql .= " AND LOWER(service) = LOWER(?)";
    $params[] = &$categoryName;
    $types .= "s";
}

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Collect providers data
$providers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $providers[] = $row;
    }
}

// If it's an AJAX request, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'providers' => $providers,
        'total' => count($providers)
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

// Reset result pointer for HTML rendering (only if there are results)
if ($result->num_rows > 0) {
    $result->data_seek(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Services - JuaKazi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl">
                Find Services
            </h1>
            <p class="mt-4 text-xl text-gray-600">
                Browse available services and book with trusted providers.
            </p>
        </div>

        <!-- Search and Filter -->
        <form class="mt-8 flex flex-col sm:flex-row gap-4" method="get" action="services.php">
            <div class="flex-1">
                <input
                    type="text"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Search services..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>
            <div class="sm:w-64">
                <select
                    name="category"
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['slug']); ?>"
                            <?php if ($category == $cat['slug']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Search</button>
            </div>
        </form>

        <!-- Services Grid -->
        <div class="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center space-x-4 mb-4">
                            <?php if (!empty($row['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($row['profile_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($row['username']); ?>" 
                                     class="w-16 h-16 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-2xl">
                                    <?php echo strtoupper(substr($row['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </h2>
                                <p class="text-blue-600 font-medium">
                                    <?php echo htmlspecialchars($row['service']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($row['location'])): ?>
                            <p class="text-gray-600 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                </svg>
                                <?php echo htmlspecialchars($row['location']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($row['about'])): ?>
                            <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                <?php echo htmlspecialchars($row['about']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between mt-4">
                            <div class="flex items-center">
                                <span class="text-yellow-400">★</span>
                                <span class="ml-1 text-gray-700">
                                    <?php echo number_format($row['rating'], 1); ?> (<?php echo $row['review_count']; ?> reviews)
                                </span>
                            </div>
                            <a href="#" class="text-blue-600 hover:text-blue-800 font-medium">View Profile</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-12">
                    <p class="text-gray-500 text-lg">No service providers found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php 
$stmt->close();
$conn->close(); 
?>
