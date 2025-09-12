<?php
include '../db.php';
include 'student_navbar.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$student_id = $_SESSION['user_id'] ?? null;
if (!$student_id) {
    header('Location: ../login.php'); exit;
}

// Fetch student's name
$stmt = $conn->prepare("SELECT first_name, last_name FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$student_name = htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  function loadSection(section) {
    const container = document.getElementById('content');
    container.innerHTML = `
      <div class="w-full py-12 flex items-center justify-center">
        <div class="animate-spin h-8 w-8 rounded-full border-4 border-gray-200 border-t-blue-600"></div>
      </div>`;
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      container.innerHTML = (xhr.status === 200) ? xhr.responseText :
        '<div class="p-4 rounded-lg bg-red-50 border border-red-200 text-red-700">Error loading content.</div>';
    };
    xhr.send('section=' + encodeURIComponent(section));
    // persist last section for back nav
    localStorage.setItem('student_last_section', section);
  }

  function pollNotifications() {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'api.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      if (xhr.status === 200) {
        const count = parseInt(xhr.responseText || '0', 10);
        const badgeEls = document.querySelectorAll('[data-notif-badge]');
        badgeEls.forEach(el => el.textContent = count);
        badgeEls.forEach(el => el.classList.toggle('hidden', count <= 0));
      }
    };
    xhr.send('action=count_notifications');
    setTimeout(pollNotifications, 30000); // 30s
  }

  window.addEventListener('DOMContentLoaded', () => {
    const last = localStorage.getItem('student_last_section') || 'overview';
    loadSection(last);
    pollNotifications();
  });
</script>
</head>
<body class="bg-gray-50 text-gray-900">
  <header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-blue-700">Welcome, <?= $student_name ?: 'Student'; ?> 👋</h1>
          <p class="text-gray-600 mt-1">Your classes, attendance, and notifications in one place.</p>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="loadSection('overview')" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 text-white px-4 py-2.5 hover:bg-blue-500 shadow-sm">
            <!-- home icon -->
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2h-3m-8 0H5a2 2 0 0 1-2-2V10m12 12v-6a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v6"/>
            </svg>
            Overview
          </button>
          <button onclick="loadSection('notifications')" class="relative inline-flex items-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2.5 hover:bg-gray-50">
            <!-- bell icon -->
            <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.24 17.24a2 2 0 0 1-4.48 0M4 8a8 8 0 1 1 16 0c0 4 2 6 2 6H2s2-2 2-6"/>
            </svg>
            Notifications
            <span data-notif-badge class="hidden ml-2 inline-flex items-center justify-center text-xs font-semibold rounded-full bg-red-600 text-white min-w-[1.5rem] h-6 px-1">0</span>
          </button>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div id="content" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 min-h-[280px]"></div>
  </main>
</body>
</html>
