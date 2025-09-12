<?php
include '../db.php';
include 'admin_navbar.php';

// Fetch schools and courses hierarchy
$school_courses = [];
$school_res = $conn->query("SELECT s.school_id, s.school_name, c.course_id, c.course_name 
                            FROM schools s 
                            LEFT JOIN courses c ON s.school_id = c.school_id 
                            ORDER BY s.school_name, c.course_name");
while ($row = $school_res->fetch_assoc()) {
    $school_courses[$row['school_id']]['school_name'] = $row['school_name'];
    if ($row['course_id']) {
        $school_courses[$row['school_id']]['courses'][$row['course_id']] = $row['course_name'];
    }
}

// Handle Add
if (isset($_POST['add'])) {
    $unit_name = trim($_POST['unit_name']);
    $unit_code = trim($_POST['unit_code']);
    $course_id = (int)$_POST['course_id'];
    $semester = (int)$_POST['semester'];
    $year = (int)$_POST['year'];
    $credit_units = (int)$_POST['credit_units'];

    if ($unit_name && $unit_code && $course_id && $semester && $year && $credit_units) {
        $stmt = $conn->prepare("INSERT INTO course_units (unit_name, unit_code, course_id, semester, year, credit_units) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiii", $unit_name, $unit_code, $course_id, $semester, $year, $credit_units);
        $stmt->execute();
        header("Location: manage_course_units.php");
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM course_units WHERE unit_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_course_units.php");
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $unit_name = trim($_POST['unit_name']);
    $unit_code = trim($_POST['unit_code']);
    $course_id = (int)$_POST['course_id'];
    $semester = (int)$_POST['semester'];
    $year = (int)$_POST['year'];
    $credit_units = (int)$_POST['credit_units'];

    if ($unit_name && $unit_code && $course_id && $semester && $year && $credit_units) {
        $stmt = $conn->prepare("UPDATE course_units SET unit_name=?, unit_code=?, course_id=?, semester=?, year=?, credit_units=? WHERE unit_id=?");
        $stmt->bind_param("ssiiiii", $unit_name, $unit_code, $course_id, $semester, $year, $credit_units, $id);
        $stmt->execute();
        header("Location: manage_course_units.php");
        exit();
    }
}

// Fetch all units with course and school info
$sql = "SELECT cu.*, c.course_name, s.school_name 
        FROM course_units cu 
        LEFT JOIN courses c ON cu.course_id = c.course_id 
        LEFT JOIN schools s ON c.school_id = s.school_id 
        ORDER BY s.school_name, c.course_name, cu.unit_name";
$results = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Course Units</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">

<h1 class="text-3xl font-bold text-yellow-400 mb-6 text-center">Manage Course Units</h1>

<!-- Add New Unit -->
<form method="POST" class="mb-6 grid grid-cols-7 gap-4 max-w-full items-center">
    <input type="text" name="unit_code" placeholder="Unit Code" required
           class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
    <input type="text" name="unit_name" placeholder="Unit Name" required
           class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
    <select name="course_id" required
            class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
        <option value="" disabled selected>Select Course</option>
        <?php foreach ($school_courses as $school): ?>
            <?php if (isset($school['courses'])): ?>
                <optgroup label="<?= htmlspecialchars($school['school_name']) ?>">
                    <?php foreach ($school['courses'] as $id => $name): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <input type="number" name="semester" min="1" max="3" placeholder="Semester" required
           class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
    <input type="number" name="year" min="1" max="6" placeholder="Year" required
           class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
    <input type="number" name="credit_units" min="1" max="10" placeholder="Credit Units" required
           class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
    <button name="add" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded text-black font-semibold">Add</button>
</form>

<!-- Units Table -->
<div class="overflow-x-auto max-w-full">
    <table class="min-w-full bg-gray-800 rounded-lg">
        <thead class="bg-yellow-500 text-black">
        <tr>
            <th class="px-4 py-2 text-left">ID</th>
            <th class="px-4 py-2 text-left">Unit Code</th>
            <th class="px-4 py-2 text-left">Unit Name</th>
            <th class="px-4 py-2 text-left">Course</th>
            <th class="px-4 py-2 text-left">School</th>
            <th class="px-4 py-2 text-left">Semester</th>
            <th class="px-4 py-2 text-left">Year</th>
            <th class="px-4 py-2 text-left">Credit Units</th>
            <th class="px-4 py-2 text-left">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $results->fetch_assoc()): ?>
            <tr class="border-b border-gray-700">
                <td class="px-4 py-2"><?= $row['unit_id'] ?></td>

                <?php if (isset($_GET['edit']) && $_GET['edit'] == $row['unit_id']): ?>
                    <form method="POST" class="grid grid-cols-9 gap-4 items-center px-0 py-2 w-full">
                        <input type="hidden" name="id" value="<?= $row['unit_id'] ?>" />
                        <td><input type="text" name="unit_code" value="<?= htmlspecialchars($row['unit_code']) ?>" required class="px-2 py-1 rounded bg-gray-700 text-white"></td>
                        <td><input type="text" name="unit_name" value="<?= htmlspecialchars($row['unit_name']) ?>" required class="px-2 py-1 rounded bg-gray-700 text-white"></td>
                        <td>
                            <select name="course_id" required class="px-2 py-1 rounded bg-gray-700 text-white w-full">
                                <?php foreach ($school_courses as $school): ?>
                                    <?php if (isset($school['courses'])): ?>
                                        <optgroup label="<?= htmlspecialchars($school['school_name']) ?>">
                                            <?php foreach ($school['courses'] as $id => $name): ?>
                                                <option value="<?= $id ?>" <?= $id == $row['course_id'] ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-2 py-1"><?= htmlspecialchars($row['school_name'] ?? 'N/A') ?></td>
                        <td><input type="number" name="semester" min="1" max="3" value="<?= $row['semester'] ?>" required class="px-2 py-1 rounded bg-gray-700 text-white"></td>
                        <td><input type="number" name="year" min="1" max="6" value="<?= $row['year'] ?>" required class="px-2 py-1 rounded bg-gray-700 text-white"></td>
                        <td><input type="number" name="credit_units" min="1" max="10" value="<?= $row['credit_units'] ?>" required class="px-2 py-1 rounded bg-gray-700 text-white"></td>
                        <td class="space-x-2">
                            <button name="update" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">Save</button>
                            <a href="manage_course_units.php" class="text-yellow-400 hover:underline px-2 py-1">Cancel</a>
                        </td>
                    </form>
                <?php else: ?>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['unit_code']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['unit_name']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['course_name'] ?? 'N/A') ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['school_name'] ?? 'N/A') ?></td>
                    <td class="px-4 py-2"><?= $row['semester'] ?></td>
                    <td class="px-4 py-2"><?= $row['year'] ?></td>
                    <td class="px-4 py-2"><?= $row['credit_units'] ?></td>
                    <td class="px-4 py-2 space-x-2">
                        <a href="?edit=<?= $row['unit_id'] ?>" class="text-blue-400 hover:underline">Edit</a>
                        <a href="?delete=<?= $row['unit_id'] ?>" onclick="return confirm('Delete this unit?');" class="text-red-500 hover:underline">Delete</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>
