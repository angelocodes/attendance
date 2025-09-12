<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4 py-8 text-center">
        <h1 class="text-4xl font-bold text-red-600 mb-4">Access Denied</h1>
        <p class="text-lg mb-6">You do not have permission to access this page.</p>
        
        <?php if (isset($_SESSION['user_type'])): ?>
            <p class="mb-6">Please return to your dashboard or choose an action below.</p>
            <div class="flex justify-center gap-4">
                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                    <a href="admin/dashboard.php" class="bg-yellow-400 text-gray-900 font-bold px-4 py-2 rounded hover:bg-yellow-300">Go to Admin Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'staff'): ?>
                    <a href="lecturer/dashboard.php" class="bg-yellow-400 text-gray-900 font-bold px-4 py-2 rounded hover:bg-yellow-300">Go to Lecturer Dashboard</a>
                <?php elseif ($_SESSION['user_type'] === 'student'): ?>
                    <a href="student/dashboard.php" class="bg-yellow-400 text-gray-900 font-bold px-4 py-2 rounded hover:bg-yellow-300">Go to Student Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="bg-red-600 text-white font-bold px-4 py-2 rounded hover:bg-red-500">Logout</a>
            </div>
        <?php else: ?>
            <p class="mb-6">Please log in to access the system.</p>
            <a href="login.php" class="bg-yellow-400 text-gray-900 font-bold px-4 py-2 rounded hover:bg-yellow-300">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>