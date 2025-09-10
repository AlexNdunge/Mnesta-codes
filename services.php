<?php
session_start();
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "Juakazi";

$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Handle search and filter
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';

$sql = "SELECT username, role, service FROM users WHERE role = 'provider'";
if ($search !== '') {
    $sql .= " AND (username LIKE '%$search%' OR service LIKE '%$search%')";
}
if ($category !== '') {
    $sql .= " AND service = '$category'";
}
$result = $conn->query($sql);
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
                    <option value="home-cleaning" <?php if($category=="home-cleaning") echo 'selected'; ?>>Home Cleaning</option>
                    <option value="plumbing" <?php if($category=="plumbing") echo 'selected'; ?>>Plumbing</option>
                    <option value="electrical" <?php if($category=="electrical") echo 'selected'; ?>>Electrical</option>
                    <option value="carpentry" <?php if($category=="carpentry") echo 'selected'; ?>>Carpentry</option>
                    <option value="gardening" <?php if($category=="gardening") echo 'selected'; ?>>Gardening</option>
                    <option value="tutoring" <?php if($category=="tutoring") echo 'selected'; ?>>Tutoring</option>
                    <option value="pet-care" <?php if($category=="pet-care") echo 'selected'; ?>>Pet Care</option>
                    <option value="beauty" <?php if($category=="beauty") echo 'selected'; ?>>Beauty</option>
                    <option value="fitness" <?php if($category=="fitness") echo 'selected'; ?>>Fitness</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Search</button>
            </div>
        </form>

        <!-- Services Grid -->
        <div class="mt-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="bg-white p-6 rounded shadow border border-gray-200">
                        <h2 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($row['service']); ?></h2>
                        <p class="text-gray-600 mb-1">Provider: <span class="font-medium text-gray-900"><?php echo htmlspecialchars($row['username']); ?></span></p>
                        <p class="text-gray-500 text-sm">Category: <?php echo htmlspecialchars($row['role']); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-12">
                    <p class="text-gray-500 text-lg">No services found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
