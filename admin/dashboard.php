<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../access_denied.php");
    exit;
}

require_once '../db.php';
$admin_id = $_SESSION['user_id'];

// Initialize stats
$stats = [
    'username' => 'Admin',
    'total_students' => 0,
    'total_lecturers' => 0,
    'total_sessions' => 0,
    'recent_activities' => [],
    'notifications' => [],
    'error' => null
];

try {
    // Admin username
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ? AND user_type='admin'");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) $stats['username'] = $user['username'];
    $stmt->close();

    // Counts
    $stats['total_students'] = $conn->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetch_row()[0];
    $stats['total_lecturers'] = $conn->query("SELECT COUNT(*) FROM lecturers")->fetch_row()[0];
    $stats['total_sessions'] = $conn->query("SELECT COUNT(*) FROM class_sessions")->fetch_row()[0];

    // Recent Activities
    $act_sql = "SELECT ar.marked_at, u.username, cu.unit_code, ar.status
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.user_id
                JOIN class_sessions cs ON ar.session_id = cs.session_id
                JOIN course_units cu ON cs.unit_id = cu.unit_id
                ORDER BY ar.marked_at DESC LIMIT 5";
    $res = $conn->query($act_sql);
    while ($row = $res->fetch_assoc()) $stats['recent_activities'][] = $row;

    // Notifications
    $note_stmt = $conn->prepare("SELECT message, created_at, is_read FROM notifications
                                 WHERE user_id = ? OR user_id IS NULL
                                 ORDER BY created_at DESC LIMIT 5");
    $note_stmt->bind_param("i", $admin_id);
    $note_stmt->execute();
    $res = $note_stmt->get_result();
    while ($row = $res->fetch_assoc()) $stats['notifications'][] = $row;

} catch (Exception $e) {
    $stats['error'] = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<?php include 'admin_navbar.php'; ?>

<main class="container mx-auto p-6">
    <h1 class="text-3xl font-bold text-yellow-400 mb-6 text-center">Welcome, <?= htmlspecialchars($stats['username']) ?>!</h1>

    <?php if ($stats['error']): ?>
        <div class="bg-red-700 text-white p-4 rounded mb-6"><?= htmlspecialchars($stats['error']) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Overview Cards -->
        <div class="bg-gray-800 rounded-lg shadow p-6 space-y-4">
            <h2 class="text-xl font-semibold text-yellow-400">System Overview</h2>
            <div>
                <p class="text-2xl font-bold text-yellow-400"><?= $stats['total_students'] ?></p>
                <p class="text-gray-400">Active Students</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-green-400"><?= $stats['total_lecturers'] ?></p>
                <p class="text-gray-400">Lecturers</p>
            </div>
            <div>
                <p class="text-2xl font-bold text-blue-400"><?= $stats['total_sessions'] ?></p>
                <p class="text-gray-400">Class Sessions</p>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="bg-gray-800 rounded-lg shadow p-6 lg:col-span-2">
            <h2 class="text-xl font-semibold text-yellow-400 mb-4">Recent Activities</h2>
            <?php if (empty($stats['recent_activities'])): ?>
                <p class="text-gray-400">No recent activities.</p>
            <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($stats['recent_activities'] as $act): ?>
                        <li class="bg-gray-700 p-3 rounded-lg hover:bg-gray-600 transition">
                            <p><span class="font-semibold"><?= htmlspecialchars($act['username']) ?></span> marked as <span class="font-semibold"><?= htmlspecialchars($act['status']) ?></span> for <span class="font-semibold"><?= htmlspecialchars($act['unit_code']) ?></span></p>
                            <time class="text-xs text-gray-400"><?= date("M j, Y H:i", strtotime($act['marked_at'])) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications -->
    <section class="bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold text-yellow-400 mb-4">Notifications</h2>
        <?php if (empty($stats['notifications'])): ?>
            <p class="text-gray-400">No new notifications.</p>
        <?php else: ?>
            <ul class="space-y-3">
                <?php foreach ($stats['notifications'] as $note): ?>
                    <li class="bg-gray-700 p-3 rounded-lg hover:bg-gray-600 transition <?= !$note['is_read'] ? 'border-l-4 border-yellow-400' : '' ?>">
                        <p><?= htmlspecialchars($note['message']) ?></p>
                        <time class="text-xs text-gray-400"><?= date("M j, Y H:i", strtotime($note['created_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

<footer class="bg-gray-800 text-center py-4 mt-8">
    <p>© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
</footer>

</body>
</html>
