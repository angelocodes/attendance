<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'student') {
    header('Location: ../login.php'); exit;
}
?>
<nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-14">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold">AT</div>
        <span class="font-semibold text-gray-800">Student Portal</span>
      </div>
      <ul class="hidden md:flex items-center gap-3 text-sm">
        <li><a href="dashboard.php" class="px-3 py-2 rounded-lg hover:bg-gray-100">Home</a></li>
        <li><button onclick="loadSection('enrolled_units')" class="px-3 py-2 rounded-lg hover:bg-gray-100">Enrolled Units</button></li>
        <li><button onclick="loadSection('attendance')" class="px-3 py-2 rounded-lg hover:bg-gray-100">Attendance</button></li>
        <li><button onclick="loadSection('statistics')" class="px-3 py-2 rounded-lg hover:bg-gray-100">Statistics</button></li>
        <li>
          <button onclick="loadSection('notifications')" class="relative px-3 py-2 rounded-lg hover:bg-gray-100 inline-flex items-center gap-2">
            Notifications
            <span data-notif-badge class="hidden ml-1 inline-flex items-center justify-center text-[11px] font-semibold rounded-full bg-red-600 text-white min-w-[1.25rem] h-5 px-1">0</span>
          </button>
        </li>
        <li><button onclick="loadSection('profile')" class="px-3 py-2 rounded-lg hover:bg-gray-100">Profile</button></li>
        <li><a href="../logout.php" class="px-3 py-2 rounded-lg text-red-600 hover:bg-red-50">Logout</a></li>
      </ul>
      <div class="md:hidden">
        <button id="mobileMenuBtn" class="p-2 rounded-lg hover:bg-gray-100" aria-label="Menu">
          <svg class="w-6 h-6 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
  <!-- Mobile drawer -->
  <div id="mobileMenu" class="md:hidden hidden border-t border-gray-200 bg-white">
    <div class="px-4 py-2 grid gap-1">
      <a href="dashboard.php" class="px-3 py-2 rounded-lg hover:bg-gray-100">Home</a>
      <button onclick="loadSection('enrolled_units')" class="text-left px-3 py-2 rounded-lg hover:bg-gray-100">Enrolled Units</button>
      <button onclick="loadSection('attendance')" class="text-left px-3 py-2 rounded-lg hover:bg-gray-100">Attendance</button>
      <button onclick="loadSection('statistics')" class="text-left px-3 py-2 rounded-lg hover:bg-gray-100">Statistics</button>
      <button onclick="loadSection('notifications')" class="relative text-left px-3 py-2 rounded-lg hover:bg-gray-100">
        Notifications
        <span data-notif-badge class="hidden ml-2 inline-flex items-center justify-center text-xs font-semibold rounded-full bg-red-600 text-white min-w-[1.25rem] h-5 px-1">0</span>
      </button>
      <button onclick="loadSection('profile')" class="text-left px-3 py-2 rounded-lg hover:bg-gray-100">Profile</button>
      <a href="../logout.php" class="px-3 py-2 rounded-lg text-red-600 hover:bg-red-50">Logout</a>
    </div>
  </div>
</nav>
<script>
  document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
    const m = document.getElementById('mobileMenu');
    m.classList.toggle('hidden');
  });
</script>
