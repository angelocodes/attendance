<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Ensure only admins can access this navbar
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
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
            <a href="../index.php" class="text-2xl font-bold text-yellow-400">SUNATT Admin</a>

            <!-- Desktop Menu -->
            <div class="hidden md:flex space-x-6 items-center">
                <?php
                $menu_items = [
                    ['href' => 'dashboard.php', 'label' => 'Dashboard'],
                    ['href' => 'manage_users.php', 'label' => 'Manage Users'],
                    ['href' => 'manage_schools.php', 'label' => 'Schools'],
                    ['href' => 'manage_departments.php', 'label' => 'Departments'],
                    ['href' => 'manage_courses.php', 'label' => 'Courses'],
                    ['href' => 'manage_course_units.php', 'label' => 'Course Units'],
                    ['href' => 'manage_student_enrollments.php', 'label' => 'Enrollments'],
                    ['href' => 'manage_lecturer_assignments.php', 'label' => 'Lecturer Assignments'],
                    ['href' => 'manage_academic_years.php', 'label' => 'Academic Years'],
                    ['href' => 'view_attendance_reports.php', 'label' => 'Attendance Reports'],
                    ['href' => 'settings.php', 'label' => 'Settings'],
                   
                    ['href' => '../logout.php', 'label' => 'Logout', 'class' => 'bg-red-600 px-3 py-1 rounded hover:bg-red-500']
                ];
                foreach ($menu_items as $item) {
                    $class = isset($item['class']) ? $item['class'] : 'hover:text-yellow-400';
                    echo "<a href=\"{$item['href']}\" class=\"{$class}\">{$item['label']}</a>";
                }
                ?>
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
            <?php
            foreach ($menu_items as $item) {
                $class = isset($item['class']) ? $item['class'] : 'block px-4 py-2 hover:bg-gray-800 hover:text-yellow-400';
                echo "<a href=\"{$item['href']}\" class=\"{$class}\">{$item['label']}</a>";
            }
            ?>
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