<?php
// student_navbar.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: ../login.php');
    exit;
}
$student_id = (int)$_SESSION['user_id'];
?>
<nav class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-4 shadow-md sticky top-0 z-50">
    <div class="container mx-auto flex justify-between items-center">
        <!-- Brand -->
        <div class="flex items-center space-x-3">
            <svg class="w-8 h-8 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 14l9-5-9-5-9 5 9 5zm0 0v7"/>
            </svg>
            <div>
                <a href="#" onclick="switchSection(event,'overview')" class="text-xl font-semibold">SUNATT Student</a>
                <div class="text-xs text-blue-100">Student Portal</div>
            </div>
        </div>

        <!-- Desktop Menu -->
        <ul class="hidden md:flex items-center gap-6 text-sm font-medium">
            <li><a href="#" onclick="switchSection(event,'overview')" data-section="overview" class="nav-link">🏠 Home</a></li>
            <li><a href="#" onclick="switchSection(event,'enrolled_units')" data-section="enrolled_units" class="nav-link">📚 Units</a></li>
            <li><a href="#" onclick="switchSection(event,'attendance')" data-section="attendance" class="nav-link">📝 Attendance</a></li>
            <li><a href="#" onclick="switchSection(event,'statistics')" data-section="statistics" class="nav-link">📊 Analytics</a></li>
            <li><a href="#" onclick="switchSection(event,'timetable')" data-section="timetable" class="nav-link">📅 Timetable</a></li>
            <li><a href="#" onclick="switchSection(event,'notifications')" data-section="notifications" class="nav-link">🔔 Notifications <span id="nav-notif-badge" class="ml-1 inline-block bg-red-500 text-white text-xs px-2 py-0.5 rounded hidden"></span></a></li>
            <li><a href="#" onclick="switchSection(event,'reports')" data-section="reports" class="nav-link">📑 Reports</a></li>
            <li><a href="#" onclick="switchSection(event,'profile')" data-section="profile" class="nav-link">👤 Profile</a></li>
            <li><a href="../logout.php" class="bg-red-600 px-3 py-1 rounded hover:bg-red-500 transition">🚪 Logout</a></li>
        </ul>

        <!-- Mobile Menu Button -->
        <div class="flex items-center gap-3 md:hidden">
            <button id="mobile-notif-btn" class="relative">
                <i class="fa fa-bell text-yellow-200 text-lg"></i>
                <span id="mobile-notif-badge" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded hidden"></span>
            </button>
            <button id="mobile-menu-btn" class="md:hidden focus:outline-none">
                <svg class="w-7 h-7 text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="md:hidden hidden mt-3 px-3 pb-3 space-y-2">
        <a href="#" onclick="switchSection(event,'overview')" class="block px-3 py-2 rounded hover:bg-blue-500">🏠 Home</a>
        <a href="#" onclick="switchSection(event,'enrolled_units')" class="block px-3 py-2 rounded hover:bg-blue-500">📚 Units</a>
        <a href="#" onclick="switchSection(event,'attendance')" class="block px-3 py-2 rounded hover:bg-blue-500">📝 Attendance</a>
        <a href="#" onclick="switchSection(event,'statistics')" class="block px-3 py-2 rounded hover:bg-blue-500">📊 Analytics</a>
        <a href="#" onclick="switchSection(event,'timetable')" class="block px-3 py-2 rounded hover:bg-blue-500">📅 Timetable</a>
        <a href="#" onclick="switchSection(event,'notifications')" class="block px-3 py-2 rounded hover:bg-blue-500">🔔 Notifications</a>
        <a href="#" onclick="switchSection(event,'reports')" class="block px-3 py-2 rounded hover:bg-blue-500">📑 Reports</a>
        <a href="#" onclick="switchSection(event,'profile')" class="block px-3 py-2 rounded hover:bg-blue-500">👤 Profile</a>
        <a href="../logout.php" class="block px-3 py-2 rounded bg-red-600 hover:bg-red-500">🚪 Logout</a>
    </div>

    <style>
        .nav-link { color: rgba(255,255,255,0.95); text-decoration: none; }
        .nav-link.active { color: #fef08a; font-weight: 700; }
    </style>

    <script>
        // mobile menu toggle
        document.getElementById('mobile-menu-btn').addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // mobile notif button opens notifications section
        document.getElementById('mobile-notif-btn').addEventListener('click', (e) => {
            e.preventDefault();
            switchSection(e, 'notifications');
            document.getElementById('mobile-menu').classList.add('hidden');
        });

        // small helper: highlight link by data-section
        function highlightNav(section) {
            document.querySelectorAll('.nav-link').forEach(el => {
                el.classList.toggle('active', el.dataset.section === section);
            });
        }
        // expose highlight function globally for dashboard to call
        window.highlightNav = highlightNav;
    </script>
</nav>
