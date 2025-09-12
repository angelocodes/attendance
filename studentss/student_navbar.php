<?php
session_start();
// Ensure only students can access this navbar
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../access_denied.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <nav class="bg-gray-900 text-white p-4 shadow-md">
        <div class="container mx-auto flex justify-between items-center">
            <!-- Logo/Brand -->
            <a href="../index.php" class="text-2xl font-bold text-yellow-400">SUN-ATT Student</a>

            <!-- Desktop Menu -->
            <div class="hidden md:flex space-x-6 items-center">
                <a href="dashboard.php" class="hover:text-yellow-400">Dashboard</a>
                <a href="my_units.php" class="hover:text-yellow-400">My Units</a>
                <a href="enrolled_units.php" class="hover:text-yellow-400">Enrolled Units</a>
                <a href="my_attendance.php" class="hover:text-yellow-400">My Attendance</a>
                <a href="download_report.php" class="hover:text-yellow-400">Download Report</a>
                <a href="verify_face.php" class="hover:text-yellow-400">Verify Face</a>
                <a href="profile.php" class="hover:text-yellow-400">Profile</a>
                
                
                <a href="../logout.php" class="bg-red-600 px-3 py-1 rounded hover:bg-red-500">Logout</a>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden focus:outline-none">
                <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="md:hidden hidden mt-4 space-y-2">
            <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400">Dashboard</a>
            <a href="my_units.php" class="block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400">My Units</a>
            <a href="enrolled_units.php" class="block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400">Enrolled Units</a>
            <a href="my_attendance.php" class="block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400">My Attendance</a>
            <a href="download_report.php" class="block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400">Download Report</a>
            <a href="verify_face.php" class="block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400">Verify Face</a>
            <a href="profile.php" class="block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400">Profile</a>
                        <a href="../logout.php" class="block px-4 py-2 bg-red-600 rounded hover:bg-red-500">Logout</a>
        </div>
    </nav>

    <script>
        // Toggle mobile menu
        document.getElementById('mobile-menu-btn').addEventListener('click', () => {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>
</body>
</html>