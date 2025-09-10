<?php
// edit_user.php - Edit user details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "juakazi";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['id'])) {
    die("User ID not specified.");
}
$id = intval($_GET['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $role = $conn->real_escape_string($_POST['role']);
    $sql = "UPDATE users SET username='$username', email='$email', role='$role' WHERE id=$id";
    if ($conn->query($sql)) {
        header("Location: users.php");
        exit();
    } else {
        $error = "Update failed: " . $conn->error;
    }
}

$sql = "SELECT id, username, email, role FROM users WHERE id=$id";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) {
    die("User not found.");
}
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto py-8">
        <h1 class="text-2xl font-bold mb-6">Edit User</h1>
        <?php if (isset($error)): ?>
            <div class="mb-4 text-red-600"> <?php echo $error; ?> </div>
        <?php endif; ?>
        <form method="post" class="bg-white p-6 rounded shadow max-w-md">
            <div class="mb-4">
                <label class="block mb-1 font-semibold">Username</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full border px-3 py-2 rounded" required>
            </div>
            <div class="mb-4">
                <label class="block mb-1 font-semibold">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full border px-3 py-2 rounded" required>
            </div>
            <div class="mb-4">
                <label class="block mb-1 font-semibold">Role</label>
                <select name="role" class="w-full border px-3 py-2 rounded">
                    <option value="user" <?php if($user['role']==='user') echo 'selected'; ?>>User</option>
                    <option value="admin" <?php if($user['role']==='admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
            <a href="users.php" class="ml-4 text-gray-600 hover:underline">Cancel</a>
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>
