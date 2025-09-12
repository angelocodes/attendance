<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

include '../db.php';
$student_id = $_SESSION['user_id'];

// Fetch student's name
$stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$student_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function loadSection(section) {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function () {
        if (xhr.status === 200) {
          document.getElementById('content').innerHTML = xhr.responseText;
        } else {
          document.getElementById('content').innerHTML =
            '<p class="text-red-600">Error loading content.</p>';
        }
      };
      xhr.send(`section=${encodeURIComponent(section)}`);
    }

    function pollNotifications() {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function () {
        if (xhr.status === 200) {
          document.getElementById('notification-count').innerText = xhr.responseText;
        }
      };
      xhr.send('action=count_notifications');
      setTimeout(pollNotifications, 30000);
    }

    window.onload = function () {
      loadSection('overview');
      pollNotifications();
    };

    function openPasswordModal() {
      document.getElementById('passwordModal').classList.remove('hidden');
    }
    function closePasswordModal() {
      document.getElementById('passwordModal').classList.add('hidden');
    }
    function changePassword() {
      const current = document.getElementById('current_password').value;
      const newPass = document.getElementById('new_password').value;
      const confirm = document.getElementById('confirm_password').value;
      if (newPass !== confirm) {
        alert("New password and confirmation don't match!");
        return;
      }
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function () {
        alert(xhr.responseText);
        if (xhr.responseText.includes('success')) {
          closePasswordModal();
          document.getElementById('current_password').value = '';
          document.getElementById('new_password').value = '';
          document.getElementById('confirm_password').value = '';
        }
      };
      xhr.send(`action=change_password&current=${encodeURIComponent(current)}&new=${encodeURIComponent(newPass)}`);
    }
  </script>
</head>
<body class="bg-gray-100 text-gray-900">

<!-- NAVBAR -->
<nav class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-4 shadow-md sticky top-0 z-50">
  <div class="container mx-auto flex justify-between items-center">
    <div class="flex items-center space-x-2">
      <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 14l9-5-9-5-9 5 9 5zm0 0v7"/>
      </svg>
      <h1 class="text-xl font-bold tracking-wide">Student Portal</h1>
    </div>
    <ul class="hidden md:flex space-x-6 items-center font-medium">
      <li><a href="#" onclick="loadSection('overview')" class="hover:text-yellow-300 transition">🏠 Home</a></li>
      <li><a href="#" onclick="loadSection('enrolled_units')" class="hover:text-yellow-300 transition">📚 Units</a></li>
      <li><a href="#" onclick="loadSection('attendance')" class="hover:text-yellow-300 transition">📝 Attendance</a></li>
      <li><a href="#" onclick="loadSection('statistics')" class="hover:text-yellow-300 transition">📊 Analytics</a></li>
      <li><a href="#" onclick="loadSection('timetable')" class="hover:text-yellow-300 transition">📅 Timetable</a></li>
      <li><a href="#" onclick="loadSection('notifications')" class="hover:text-yellow-300 transition">🔔 Notifications (<span id="notification-count" class="bg-red-500 text-white px-2 rounded">0</span>)</a></li>
      <li><a href="#" onclick="loadSection('reports')" class="hover:text-yellow-300 transition">📑 Reports</a></li>
      <li><a href="#" onclick="loadSection('profile')" class="hover:text-yellow-300 transition">👤 Profile</a></li>
      <li><button onclick="openPasswordModal()" class="hover:text-yellow-300">🔑 Change Password</button></li>
      <li><a href="../logout.php" class="bg-red-600 px-3 py-1 rounded hover:bg-red-500 transition">🚪 Logout</a></li>
    </ul>
    <button id="mobile-menu-btn" class="md:hidden focus:outline-none">
      <svg class="w-7 h-7 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
  </div>
  <div id="mobile-menu" class="md:hidden hidden mt-4 space-y-2 px-2">
    <a href="#" onclick="loadSection('overview')" class="block px-3 py-2 rounded hover:bg-blue-500">🏠 Home</a>
    <a href="#" onclick="loadSection('enrolled_units')" class="block px-3 py-2 rounded hover:bg-blue-500">📚 Units</a>
    <a href="#" onclick="loadSection('attendance')" class="block px-3 py-2 rounded hover:bg-blue-500">📝 Attendance</a>
    <a href="#" onclick="loadSection('statistics')" class="block px-3 py-2 rounded hover:bg-blue-500">📊 Analytics</a>
    <a href="#" onclick="loadSection('timetable')" class="block px-3 py-2 rounded hover:bg-blue-500">📅 Timetable</a>
    <a href="#" onclick="loadSection('notifications')" class="block px-3 py-2 rounded hover:bg-blue-500">🔔 Notifications (<span id="notification-count">0</span>)</a>
    <a href="#" onclick="loadSection('reports')" class="block px-3 py-2 rounded hover:bg-blue-500">📑 Reports</a>
    <a href="#" onclick="loadSection('profile')" class="block px-3 py-2 rounded hover:bg-blue-500">👤 Profile</a>
    <button onclick="openPasswordModal()" class="block w-full text-left px-3 py-2 rounded hover:bg-blue-500">🔑 Change Password</button>
    <a href="../logout.php" class="block px-3 py-2 rounded bg-red-600 hover:bg-red-500">🚪 Logout</a>
  </div>
</nav>

<div class="container mx-auto p-6">
  <h1 class="text-3xl font-bold mb-6 text-blue-600">Welcome, <?php echo $student_name; ?>!</h1>
  <div id="content" class="bg-white p-6 rounded shadow"></div>
</div>

<!-- Password Change Modal -->
<div id="passwordModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white rounded-lg shadow-lg w-96 p-6 relative">
    <h2 class="text-xl font-semibold mb-4">Change Password</h2>
    <input type="password" id="current_password" placeholder="Current Password" class="w-full p-2 border rounded mb-3">
    <input type="password" id="new_password" placeholder="New Password" class="w-full p-2 border rounded mb-3">
    <input type="password" id="confirm_password" placeholder="Confirm New Password" class="w-full p-2 border rounded mb-4">
    <div class="flex justify-end space-x-3">
      <button onclick="closePasswordModal()" class="px-4 py-2 bg-gray-400 text-white rounded">Cancel</button>
      <button onclick="changePassword()" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
    </div>
  </div>
</div>

<script>
  document.getElementById('mobile-menu-btn').addEventListener('click', () => {
    document.getElementById('mobile-menu').classList.toggle('hidden');
  });
</script>
</body>
</html>
