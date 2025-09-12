<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT cu.unit_name, a.date_marked, a.status 
                         FROM attendance a 
                         JOIN course_units cu ON a.unit_id = cu.unit_id 
                         WHERE a.student_id = ?
                         ORDER BY a.date_marked DESC");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-green-700 mb-6">My Attendance Records</h1>
        <div class="bg-white shadow rounded overflow-x-auto">
            <table class="min-w-full table-auto">
                <thead class="bg-green-600 text-white">
                    <tr>
                        <th class="py-2 px-4 text-left">Course Unit</th>
                        <th class="py-2 px-4 text-left">Date</th>
                        <th class="py-2 px-4 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="border-b">
                                <td class="py-2 px-4"><?= htmlspecialchars($row['unit_name']) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($row['date_marked']) ?></td>
                                <td class="py-2 px-4">
                                    <span class="px-2 py-1 rounded text-white <?= $row['status'] == 'present' ? 'bg-green-500' : 'bg-red-500' ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td class="px-4 py-4 text-gray-500" colspan="3">No attendance records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
