<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../access_denied.php");
    exit;
}

require_once '../db.php';

$user_id = $_SESSION['user_id'];

// Initialize stats
$stats = [
    'username' => 'Student',
    'email' => '',
    'phone' => 'Not Provided',
    'registration_number' => 'N/A',
    'created_at' => date('Y-m-d'),
    'total_lectures' => 0,
    'attended_lectures' => 0,
    'attendance_rate' => 0,
    'enrolled_units' => [],
    'deadlines' => [],
    'notifications' => [],
    'error' => null
];

try {
    // Fetch user and student info
    $stmt = $conn->prepare("SELECT u.username, u.email, u.phone_number, u.created_at, s.registration_number
                            FROM users u
                            JOIN students s ON u.user_id = s.student_id
                            WHERE u.user_id = ? AND u.user_type = 'student'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $stats['username'] = $user['username'];
        $stats['email'] = $user['email'];
        $stats['phone'] = $user['phone_number'] ?: 'Not Provided';
        $stats['created_at'] = $user['created_at'];
        $stats['registration_number'] = $user['registration_number'] ?: 'N/A';
    } else {
        throw new Exception("Student record not found.");
    }
    $stmt->close();

    // Fetch enrolled units
    $stmt = $conn->prepare("SELECT cu.unit_code, cu.unit_name
                            FROM course_units cu
                            JOIN student_enrollments se ON cu.unit_id = se.unit_id
                            WHERE se.student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['enrolled_units'][] = $row;
    }
    $stmt->close();

    // Fetch attendance stats
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT cs.session_id)
                            FROM class_sessions cs
                            JOIN student_enrollments se ON cs.unit_id = se.unit_id
                            WHERE se.student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($stats['total_lectures']);
    $stmt->fetch();
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*)
                            FROM attendance_records ar
                            WHERE ar.student_id = ? AND ar.status = 'Present'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($stats['attended_lectures']);
    $stmt->fetch();
    $stmt->close();

    $stats['attendance_rate'] = $stats['total_lectures'] ? round(($stats['attended_lectures'] / $stats['total_lectures']) * 100, 1) : 0;

    // Fetch deadlines
    $stmt = $conn->prepare("SELECT a.title, a.due_date, cu.unit_code
                            FROM assignments a
                            JOIN course_units cu ON a.unit_id = cu.unit_id
                            JOIN student_enrollments se ON cu.unit_id = se.unit_id
                            WHERE se.student_id = ? AND a.due_date >= NOW()
                            ORDER BY a.due_date ASC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['deadlines'][] = $row;
    }
    $stmt->close();

    // Fetch notifications
    $stmt = $conn->prepare("SELECT n.message, n.created_at, n.is_read
                            FROM notifications n
                            WHERE n.user_id = ? OR n.user_id IS NULL
                            ORDER BY n.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['notifications'][] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $stats['error'] = "Error fetching data: " . $e->getMessage();
}

$missed_lectures = $stats['total_lectures'] - $stats['attended_lectures'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <?php include 'student_navbar.php'; ?>

    <main class="container mx-auto p-8">
        <h1 class="text-3xl font-bold text-yellow-400 mb-6">Student Dashboard</h1>
        <p class="mb-6 text-lg">Welcome, <?= htmlspecialchars($stats['username']) ?>!</p>

        <?php if ($stats['error']): ?>
            <div class="mb-6 bg-red-700 p-4 rounded">
                <p class="text-lg"><?= htmlspecialchars($stats['error']) ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Profile -->
            <section class="bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">My Profile</h2>
                <ul class="space-y-2">
                    <li><strong>Username:</strong> <?= htmlspecialchars($stats['username']) ?></li>
                    <li><strong>Email:</strong> <?= htmlspecialchars($stats['email']) ?></li>
                    <li><strong>Phone:</strong> <?= htmlspecialchars($stats['phone']) ?></li>
                    <li><strong>Reg. Number:</strong> <?= htmlspecialchars($stats['registration_number']) ?></li>
                    <li><strong>Member Since:</strong> <?= date("F j, Y", strtotime($stats['created_at'])) ?></li>
                </ul>
                <a href="profile.php" class="inline-block mt-4 px-4 py-2 bg-yellow-400 text-gray-900 rounded hover:bg-yellow-300 font-semibold">Edit Profile</a>
            </section>

            <!-- Attendance -->
            <section class="bg-gray-800 rounded-lg shadow p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Attendance Overview</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <p class="text-2xl font-bold text-yellow-400"><?= $stats['total_lectures'] ?></p>
                        <p class="text-sm text-gray-400">Total Lectures</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-green-400"><?= $stats['attended_lectures'] ?></p>
                        <p class="text-sm text-gray-400">Attended</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-red-400"><?= $missed_lectures ?></p>
                        <p class="text-sm text-gray-400">Missed</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-yellow-400"><?= $stats['attendance_rate'] ?>%</p>
                        <p class="text-sm text-gray-400">Attendance Rate</p>
                    </div>
                </div>
                <div class="mt-6">
                    <canvas id="attendanceChart" class="w-full max-w-md mx-auto"></canvas>
                </div>
            </section>

            <!-- Enrolled Units -->
            <section class="bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Enrolled Units</h2>
                <?php if (empty($stats['enrolled_units'])): ?>
                    <p class="text-gray-400">No enrolled units.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($stats['enrolled_units'] as $unit): ?>
                            <li class="bg-gray-700 p-3 rounded-lg">
                                <strong><?= htmlspecialchars($unit['unit_code']) ?></strong> - <?= htmlspecialchars($unit['unit_name']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a href="enrolled_units.php" class="inline-block mt-4 px-4 py-2 bg-yellow-400 text-gray-900 rounded hover:bg-yellow-300 font-semibold">View All Units</a>
            </section>

            <!-- Deadlines -->
            <section class="bg-gray-800 rounded-lg shadow p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Upcoming Deadlines</h2>
                <?php if (empty($stats['deadlines'])): ?>
                    <p class="text-gray-400">No upcoming deadlines.</p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($stats['deadlines'] as $deadline): ?>
                            <li class="flex justify-between border-b border-gray-600 pb-2">
                                <span><?= htmlspecialchars($deadline['title']) ?> (<?= htmlspecialchars($deadline['unit_code']) ?>)</span>
                                <time class="text-sm text-gray-400"><?= date("M j, Y H:i", strtotime($deadline['due_date'])) ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Notifications -->
            <section class="bg-gray-800 rounded-lg shadow p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Notifications</h2>
                <?php if (empty($stats['notifications'])): ?>
                    <p class="text-gray-400">No new notifications.</p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($stats['notifications'] as $note): ?>
                            <li class="bg-gray-700 p-3 rounded-lg <?= $note['is_read'] ? '' : 'border-l-4 border-yellow-400' ?>">
                                <p><?= htmlspecialchars($note['message']) ?></p>
                                <time class="text-xs text-gray-400 block mt-1"><?= date("M j, Y H:i", strtotime($note['created_at'])) ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="bg-gray-800 text-center py-4 mt-8">
        <p>© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" integrity="sha512-CQ9B8vNr8rr+xeUS4/5Fpbvj4wcNjo2oZ4ig3SFuRWgpxq2rL/ywk+VaF0q4iE7S2B01v9l1K0FXlZq3caus3QA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Attended', 'Missed'],
                datasets: [{
                    data: [<?= $stats['attended_lectures'] ?>, <?= $missed_lectures ?>],
                    backgroundColor: ['#34d399', '#ef4444'],
                    hoverOffset: 20
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>