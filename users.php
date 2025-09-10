<?php
// users.php - List all users with details from the database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "juakazi";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto py-8">
        <h1 class="text-2xl font-bold mb-6">All Users</h1>
        <table class="min-w-full bg-white rounded shadow">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">ID</th>
                    <th class="py-2 px-4 border-b">Username</th>
                    <th class="py-2 px-4 border-b">Email</th>
                    <th class="py-2 px-4 border-b">Role</th>
                    <th class="py-2 px-4 border-b">Joined</th>
                    <th class="py-2 px-4 border-b text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="py-2 px-4 border-b text-center"><?php echo $row['id']; ?></td>
                            <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td class="py-2 px-4 border-b"><?php echo htmlspecialchars($row['email']); ?></td>
                            <td class="py-2 px-4 border-b text-center"><?php echo htmlspecialchars($row['role']); ?></td>
                            <td class="py-2 px-4 border-b text-center"><?php echo $row['created_at']; ?></td>
                            <td class="py-2 px-4 border-b text-center">
                                <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="bg-yellow-400 text-white px-3 py-1 rounded mr-2 hover:bg-yellow-500">Edit</a>
                                <a href="delete_user.php?id=<?php echo $row['id']; ?>" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="py-4 text-center text-gray-500">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="mt-6">
            <a href="admin-dashboard.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
