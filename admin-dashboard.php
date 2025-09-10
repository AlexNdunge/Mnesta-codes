<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "Juakazi";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Handle delete user
if (isset($_GET['delete_user'])) {
  $delete_id = intval($_GET['delete_user']);
  $conn->query("DELETE FROM users WHERE id=$delete_id");
  header('Location: admin-dashboard.php');
  exit();
}
// Handle search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$user_query = "SELECT id, username, email, role, created_at FROM users";
if ($search !== '') {
  $user_query .= " WHERE username LIKE '%$search%' OR email LIKE '%$search%'";
}
$user_query .= " ORDER BY created_at DESC LIMIT 10";
$users = $conn->query($user_query);
// Fetch messages
$messages = $conn->query("SELECT sender_id, content, sent_at FROM messages ORDER BY sent_at DESC LIMIT 10");
// Fetch stats
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$total_services = $conn->query("SELECT COUNT(*) FROM services")->fetch_row()[0];
$total_messages = $conn->query("SELECT COUNT(*) FROM messages")->fetch_row()[0];
$total_admins = $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetch_row()[0];
// User registrations per month (last 6 months)
$reg_data = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users GROUP BY month ORDER BY month DESC LIMIT 6");
$reg_months = [];
$reg_counts = [];
while($row = $reg_data->fetch_assoc()) {
  $reg_months[] = $row['month'];
  $reg_counts[] = $row['count'];
}
$reg_months = array_reverse($reg_months);
$reg_counts = array_reverse($reg_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - JuaKazi</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .sidebar {
      min-height: 100vh;
      background: linear-gradient(180deg, #1e3a8a 0%, #2563eb 100%);
    }
    .sidebar a.active, .sidebar a:hover {
      background: rgba(255,255,255,0.1);
      color: #fff;
    }
    .card {
      box-shadow: 0 2px 8px rgba(0,0,0,0.07);
    }
  </style>
</head>
<body class="bg-gray-50">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="sidebar w-64 text-white flex flex-col py-8 px-4">
      <div class="mb-10 flex items-center space-x-2">
        <i class="fas fa-user-shield text-2xl"></i>
        <span class="text-2xl font-bold">JuaKazi Admin</span>
      </div>
      <nav class="flex-1 space-y-2">
        <a href="#" class="block py-2.5 px-4 rounded transition active"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
        <a href="#" class="block py-2.5 px-4 rounded transition"><i class="fas fa-users mr-2"></i>Users</a>
        <a href="#" class="block py-2.5 px-4 rounded transition"><i class="fas fa-briefcase mr-2"></i>Services</a>
        <a href="#" class="block py-2.5 px-4 rounded transition"><i class="fas fa-comments mr-2"></i>Messages</a>
        <a href="#" class="block py-2.5 px-4 rounded transition"><i class="fas fa-cogs mr-2"></i>Settings</a>
      </nav>
      <div class="mt-auto">
        <a href="index.html" class="block py-2.5 px-4 rounded transition bg-blue-900 text-center hover:bg-blue-700"><i class="fas fa-home mr-2"></i>Back to Site</a>
      </div>
    </aside>
    <!-- Main Content -->
    <main class="flex-1 p-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
          <p class="text-gray-500 mt-2">Welcome, Admin! Here’s an overview of your platform.</p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-2">
          <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>Add Service</button>
          <button class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300"><i class="fas fa-user-plus mr-2"></i>Add User</button>
        </div>
      </div>
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
        <div class="card bg-white rounded-lg p-6 flex flex-col items-center">
          <i class="fas fa-users text-3xl text-blue-600 mb-2"></i>
          <div class="text-2xl font-bold"><?php echo $total_users; ?></div>
          <div class="text-gray-500">Total Users</div>
        </div>
        <div class="card bg-white rounded-lg p-6 flex flex-col items-center">
          <i class="fas fa-briefcase text-3xl text-green-600 mb-2"></i>
          <div class="text-2xl font-bold"><?php echo $total_services; ?></div>
          <div class="text-gray-500">Services</div>
        </div>
        <div class="card bg-white rounded-lg p-6 flex flex-col items-center">
          <i class="fas fa-comments text-3xl text-purple-600 mb-2"></i>
          <div class="text-2xl font-bold"><?php echo $total_messages; ?></div>
          <div class="text-gray-500">Messages</div>
        </div>
        <div class="card bg-white rounded-lg p-6 flex flex-col items-center">
          <i class="fas fa-user-shield text-3xl text-yellow-500 mb-2"></i>
          <div class="text-2xl font-bold"><?php echo $total_admins; ?></div>
          <div class="text-gray-500">Admins</div>
        </div>
      </div>
      <!-- Graphs -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-bold mb-4">User Registrations (Last 6 Months)</h2>
          <canvas id="regChart" height="120"></canvas>
        </div>
        <!-- Placeholder for traffic graph -->
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-bold mb-4">Site Traffic (Demo)</h2>
          <canvas id="trafficChart" height="120"></canvas>
        </div>
      </div>
      <!-- Search User -->
      <div class="mb-8">
        <form method="get" class="flex max-w-md">
          <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search user by name or email..." class="flex-1 px-4 py-2 border border-gray-300 rounded-l focus:outline-none">
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-r hover:bg-blue-700">Search</button>
        </form>
      </div>
      <!-- Recent Users Table -->
      <div class="bg-white rounded-lg shadow p-6 mb-10">
        <h2 class="text-xl font-bold mb-4">Recent Users</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($users && $users->num_rows > 0): ?>
                <?php while($u = $users->fetch_assoc()): ?>
                  <tr>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($u['username']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($u['role']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($u['created_at']); ?></td>
                    <td class="px-4 py-2 text-center">
                      <a href="edit-user.php?id=<?php echo $u['id']; ?>" class="action-btn edit-btn mr-2">Edit</a>
                      <a href="admin-dashboard.php?delete_user=<?php echo $u['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-4">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // User Registrations Graph
  const regCtx = document.getElementById('regChart').getContext('2d');
  new Chart(regCtx, {
    type: 'line',
    data: {
      labels: <?php echo json_encode($reg_months); ?>,
      datasets: [{
        label: 'Registrations',
        data: <?php echo json_encode($reg_counts); ?>,
        backgroundColor: 'rgba(37,99,235,0.2)',
        borderColor: '#2563eb',
        borderWidth: 2,
        fill: true,
        tension: 0.4
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
  // Demo Traffic Graph
  const trafficCtx = document.getElementById('trafficChart').getContext('2d');
  new Chart(trafficCtx, {
    type: 'bar',
    data: {
      labels: ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
      datasets: [{
        label: 'Visits',
        data: [120, 150, 180, 140, 200, 170],
        backgroundColor: 'rgba(16,185,129,0.7)',
        borderColor: '#10b981',
        borderWidth: 1
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
</script>
      <!-- Recent Messages Table -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Recent Messages</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Message</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($messages && $messages->num_rows > 0): ?>
                <?php while($m = $messages->fetch_assoc()): ?>
                  <tr>
                    <td class="px-4 py-2">User #<?php echo htmlspecialchars($m['sender_id']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($m['content']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($m['sent_at']); ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="3" class="text-center text-gray-400 py-4">No messages found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
<?php $conn->close(); ?>
