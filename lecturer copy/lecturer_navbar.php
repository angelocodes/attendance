<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'config.php';

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../logout.php");
    exit;
}
$_SESSION['last_activity'] = time();

// Validate lecturer session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: ../access_denied.php");
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Fetch theme color
$theme_color = '#6c8976'; // Default
if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme_color'");
    if ($stmt && $stmt->execute()) {
        $stmt->bind_result($theme_color);
        $stmt->fetch();
        $stmt->close();
    } else {
        error_log("Failed to fetch theme color: " . ($stmt ? $stmt->error : 'Statement preparation failed'), 3, '../logs/errors.log');
    }
} else {
    error_log("Database connection failed in lecturer_navbar.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
}

// Fetch unread notification count
$unread_count = 0;
if (isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 AND user_type = 'lecturer'");
    $stmt->bind_param("i", $lecturer_id);
    if ($stmt->execute()) {
        $stmt->bind_result($unread_count);
        $stmt->fetch();
        $stmt->close();
    } else {
        error_log("Failed to fetch notification count: " . $stmt->error, 3, '../logs/errors.log');
    }
}
?>

<nav class="bg-gray-900 text-white p-4 shadow-md" role="navigation" aria-label="Main navigation">
    <div class="container mx-auto flex justify-between items-center">
        <!-- Logo/Brand -->
        <a href="../index.php" class="text-2xl font-bold text-theme hover:text-theme-light transition-colors" aria-label="SUNATT Lecturer Home">SUNATT Lecturer</a>

        <!-- Desktop Menu -->
        <div class="hidden md:flex space-x-6 items-center">
            <a href="dashboard.php" class="hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded px-2 py-1 transition-colors" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'page' : 'false'; ?>">Dashboard</a>
            <a href="my_units.php" class="hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded px-2 py-1 transition-colors" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'my_units.php' ? 'page' : 'false'; ?>">My Units</a>
            <a href="schedule_session.php" class="hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded px-2 py-1 transition-colors" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'schedule_session.php' ? 'page' : 'false'; ?>">Schedule Session</a>
            <a href="attendance.php" class="hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded px-2 py-1 transition-colors" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'page' : 'false'; ?>">Attendance</a>
            <a href="start_face_session.php" class="hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded px-2 py-1 transition-colors" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'start_face_session.php' ? 'page' : 'false'; ?>">Start Face Session</a>
            <div class="relative">
                <a href="notifications.php" class="hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded px-2 py-1 transition-colors" aria-current="false" aria-label="Notifications">
                    Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="absolute -top-1 -right-2 bg-blue-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center" aria-label="<?php echo $unread_count; ?> unread notifications"><?php echo htmlspecialchars($unread_count); ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <a href="profile.php" class="hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded px-2 py-1 transition-colors" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'page' : 'false'; ?>">Profile</a>
            <a href="../logout.php" class="bg-red-600 px-4 py-1 rounded-md hover:bg-red-700 text-white focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors" aria-label="Logout">Logout</a>
        </div>

        <!-- Mobile Menu Button -->
        <button id="mobile-menu-btn" class="md:hidden focus:outline-none focus:ring-2 focus:ring-theme rounded p-2" aria-label="Toggle mobile menu" aria-expanded="false">
            <svg class="w-6 h-6 text-theme" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="md:hidden hidden mt-4 space-y-2 bg-gray-800 rounded-lg transition-all duration-300 ease-in-out" role="menu" aria-hidden="true">
        <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-700 hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded transition-colors" role="menuitem" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'page' : 'false'; ?>">Dashboard</a>
        <a href="my_units.php" class="block px-4 py-2 hover:bg-gray-700 hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded transition-colors" role="menuitem" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'my_units.php' ? 'page' : 'false'; ?>">My Units</a>
        <a href="schedule_session.php" class="block px-4 py-2 hover:bg-gray-700 hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded transition-colors" role="menuitem" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'schedule_session.php' ? 'page' : 'false'; ?>">Schedule Session</a>
        <a href="attendance.php" class="block px-4 py-2 hover:bg-gray-700 hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded transition-colors" role="menuitem" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'page' : 'false'; ?>">Attendance</a>
        <a href="start_face_session.php" class="block px-4 py-2 hover:bg-gray-700 hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded transition-colors" role="menuitem" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'start_face_session.php' ? 'page' : 'false'; ?>">Start Face Session</a>
        <a href="notifications.php" class="block px-4 py-2 hover:bg-gray-700 hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded transition-colors" role="menuitem">
            Notifications
            <?php if ($unread_count > 0): ?>
                <span class="ml-2 bg-blue-600 text-white text-xs rounded-full h-5 w-5 inline-flex items-center justify-center" aria-label="<?php echo $unread_count; ?> unread notifications"><?php echo htmlspecialchars($unread_count); ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="block px-4 py-2 hover:bg-gray-700 hover:text-theme focus:outline-none focus:ring-2 focus:ring-theme rounded transition-colors" role="menuitem" aria-current="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'page' : 'false'; ?>">Profile</a>
        <a href="../logout.php" class="block px-4 py-2 bg-red-600 rounded hover:bg-red-700 text-white focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors" role="menuitem" aria-label="Logout">Logout</a>
    </div>
</nav>

<style>
    :root {
        --theme-color: <?php echo htmlspecialchars($theme_color); ?>;
        --theme-color-light: <?php echo htmlspecialchars(adjustBrightness($theme_color, 1.2)); ?>;
    }
    .text-theme { color: var(--theme-color); }
    .hover\:text-theme-light:hover { color: var(--theme-color-light); }
    .focus\:ring-theme:focus { --tw-ring-color: var(--theme-color); }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuBtn.addEventListener('click', () => {
            const isExpanded = mobileMenuBtn.getAttribute('aria-expanded') === 'true';
            mobileMenu.classList.toggle('hidden');
            mobileMenu.classList.toggle('opacity-0');
            mobileMenu.classList.toggle('opacity-100');
            mobileMenu.setAttribute('aria-hidden', isExpanded ? 'true' : 'false');
            mobileMenuBtn.setAttribute('aria-expanded', !isExpanded ? 'true' : 'false');
        });
    });
</script>