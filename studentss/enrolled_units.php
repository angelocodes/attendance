<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT cu.unit_name, se.academic_year, se.semester 
                         FROM student_enrollments se 
                         JOIN course_units cu ON se.unit_id = cu.unit_id 
                         WHERE se.student_id = ?
                         ORDER BY se.academic_year DESC, se.semester DESC");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Enrolled Units</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-green-700 mb-6">My Enrolled Units</h1>
        <div class="bg-white shadow rounded overflow-x-auto">
            <table class="min-w-full table-auto">
                <thead class="bg-green-600 text-white">
                    <tr>
                        <th class="py-2 px-4 text-left">Course Unit</th>
                        <th class="py-2 px-4 text-left">Academic Year</th>
                        <th class="py-2 px-4 text-left">Semester</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="border-b">
                                <td class="py-2 px-4"><?= htmlspecialchars($row['unit_name']) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($row['academic_year']) ?></td>
                                <td class="py-2 px-4"><?= "Semester " . htmlspecialchars($row['semester']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td class="px-4 py-4 text-gray-500" colspan="3">You are not enrolled in any units.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
