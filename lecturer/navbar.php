<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';

// Validate lecturer session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header('Location: ../access_denied.php');
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Initial unread count
$notifications_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND user_type = 'lecturer' AND is_read = 0");
$stmt->bind_param('i', $lecturer_id);
$stmt->execute();
$stmt->bind_result($notifications_count);
$stmt->fetch();
$stmt->close();
?>

<nav class="bg-white shadow-md p-4 fixed w-full z-10">
    <div class="container mx-auto flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <span class="text-xl font-bold text-gray-800">SUNATT</span>
            <!-- Hamburger menu for mobile -->
            <button onclick="toggleNav()" class="md:hidden focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                </svg>
            </button>
        </div>

        <!-- Navigation links -->
        <div id="nav-menu" class="hidden md:flex flex-col md:flex-row md:items-center space-y-2 md:space-y-0 md:space-x-6 absolute md:static top-16 left-0 w-full md:w-auto bg-white md:bg-transparent shadow-md md:shadow-none p-4 md:p-0">
            <button onclick="switchTab('dashboard'); toggleNav()" class="flex items-center px-3 py-2 rounded hover:bg-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-1 0a1 1 0 01-1-1v-3a1 1 0 011-1h1a1 1 0 011 1v3z"/></svg>
                Dashboard
            </button>
            <button onclick="switchTab('face-recognition'); toggleNav()" class="flex items-center px-3 py-2 rounded hover:bg-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                Face Recognition
            </button>
            <button onclick="switchTab('schedule-session'); toggleNav()" class="flex items-center px-3 py-2 rounded hover:bg-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Schedule Session
            </button>
            <button onclick="switchTab('attendance-history'); toggleNav()" class="flex items-center px-3 py-2 rounded hover:bg-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                Attendance History
            </button>
            <button onclick="switchTab('my-units'); toggleNav()" class="flex items-center px-3 py-2 rounded hover:bg-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.477 5.253 9.91 4 9 4c-1.414 0-2 1.97-2 3.5 0 1.53.586 3.5 2 3.5.91 0 1.477-1.253 2-2.253zm0 0C13.523 5.253 14.09 4 15 4c1.414 0 2 1.97 2 3.5 0 1.53-.586 3.5-2 3.5-.91 0-1.477-1.253-2-2.253z"/></svg>
                My Units
            </button>
            <button onclick="switchTab('profile'); toggleNav()" class="flex items-center px-3 py-2 rounded hover:bg-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Profile
            </button>
            <button onclick="switchTab('notifications'); toggleNav()" class="relative flex items-center px-3 py-2 rounded hover:bg-gray-100">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1 1m-4 0l-1-1m1-14h-5l1-1m4 0l1 1M9 7h6m-6 4h6m-6 4h6"/></svg>
                Notifications
                <span id="notification-badge" class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full px-2" style="display: none;"></span>
            </button>
        </div>

        <div class="flex items-center space-x-4">
            <span class="text-gray-700">Hello, <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="../logout.php" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600 animate-pulse">Logout</a>
        </div>
    </div>
</nav>

<script>
// Global navigation functions
function toggleNav() {
    const navMenu = document.getElementById('nav-menu');
    navMenu.classList.toggle('hidden');
}

function switchTab(tabName) {
    // This function will be defined in the dashboard component
    // For now, we'll try to call it if it exists
    if (window.switchTab) {
        window.switchTab(tabName);
    } else {
        console.log('switchTab function not found, tab:', tabName);
    }
}

// Update notification badge
async function updateNotifications() {
    try {
        const response = await fetch('api.php?action=get_notifications');
        const data = await response.json();
        const count = data.filter(n => !n.is_read).length;
        const badge = document.getElementById('notification-badge');

        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    } catch (error) {
        console.error('Error updating notifications:', error);
    }
}

// Initialize notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    updateNotifications();
    setInterval(updateNotifications, 30000);
});
</script>
