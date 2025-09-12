<?php
include '../db.php'; 
include 'admin_navbar.php'; 

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// Delete assignment
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM lecturer_assignments WHERE assignment_id=$id");
    header("Location: manage_lecturer_assignments.php");
    exit();
}

// Handle Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = $_POST['assignment_id'] ?? '';
    $school_id = intval($_POST['school_id']);
    $lecturer_id = intval($_POST['lecturer_id']);
    $unit_id = intval($_POST['unit_id']);
    $semester = intval($_POST['semester']);
    $academic_year = intval($_POST['academic_year']);
    $allow_multiple = isset($_POST['allow_multiple']) ? true : false;

    // Duplicate check
    $dupCheck = $conn->prepare("SELECT * FROM lecturer_assignments 
                                WHERE unit_id=? AND semester=? AND academic_year=?");
    $dupCheck->bind_param("iii", $unit_id, $semester, $academic_year);
    $dupCheck->execute();
    $dupResult = $dupCheck->get_result();

    if ($dupResult->num_rows > 0 && !$allow_multiple) {
        $message = "⚠️ This course is already assigned. Tick 'Allow Multiple Lecturers' if needed.";
    }

    if ($message === "") {
        if ($assignment_id) {
            $stmt = $conn->prepare("UPDATE lecturer_assignments 
                                    SET school_id=?, lecturer_id=?, unit_id=?, semester=?, academic_year=? 
                                    WHERE assignment_id=?");
            $stmt->bind_param("iiiisi", $school_id, $lecturer_id, $unit_id, $semester, $academic_year, $assignment_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO lecturer_assignments 
                                    (school_id, lecturer_id, unit_id, semester, academic_year) 
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiis", $school_id, $lecturer_id, $unit_id, $semester, $academic_year);
        }
        $stmt->execute();
        header("Location: manage_lecturer_assignments.php");
        exit();
    }
}

// Load dropdown data
$schools = $conn->query("SELECT * FROM schools");
$years = $conn->query("SELECT * FROM academic_years");

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $edit = $conn->query("SELECT * FROM lecturer_assignments WHERE assignment_id=$id")->fetch_assoc();
}

// Filters for assignment display
$filter_school = $_GET['school'] ?? '';
$filter_department = $_GET['department'] ?? '';
$filter_course = $_GET['course'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Lecturer Assignments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    // Dynamic population of lecturers & courses based on school
    async function loadLecturersAndCourses() {
        const schoolId = document.getElementById("school_id").value;
        if (!schoolId) return;

        const lecturerSelect = document.getElementById("lecturer_id");
        const unitSelect = document.getElementById("unit_id");

        lecturerSelect.innerHTML = "<option>Loading...</option>";
        unitSelect.innerHTML = "<option>Loading...</option>";

        const response = await fetch("load_school_data.php?school_id=" + schoolId);
        const data = await response.json();

        // Populate lecturers
        lecturerSelect.innerHTML = "";
        data.lecturers.forEach(l => {
            let opt = document.createElement("option");
            opt.value = l.lecturer_id;
            opt.textContent = l.first_name + " " + l.last_name;
            lecturerSelect.appendChild(opt);
        });

        // Populate units
        unitSelect.innerHTML = "";
        data.units.forEach(u => {
            let opt = document.createElement("option");
            opt.value = u.unit_id;
            opt.textContent = u.unit_name;
            unitSelect.appendChild(opt);
        });
    }
    </script>
</head>
<body class="bg-[#0f172a] text-white min-h-screen p-6 font-sans">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-yellow-400">Lecturer Assignments</h1>

        <?php if ($message): ?>
            <div class="bg-red-600 text-white p-3 rounded mb-4"><?= $message ?></div>
        <?php endif; ?>

        <!-- Form -->
        <form method="post" class="bg-[#1e293b] p-6 rounded-lg shadow-md mb-8">
            <input type="hidden" name="assignment_id" value="<?= $edit['assignment_id'] ?? '' ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

                <!-- School -->
                <div>
                    <label class="block mb-1 text-yellow-300 font-semibold">School</label>
                    <select id="school_id" name="school_id" onchange="loadLecturersAndCourses()" 
                            class="w-full bg-[#334155] border border-[#475569] p-2 rounded text-white" required>
                        <option value="">-- Select School --</option>
                        <?php while($row = $schools->fetch_assoc()): ?>
                            <option value="<?= $row['school_id'] ?>" 
                                <?= isset($edit) && $edit['school_id'] == $row['school_id'] ? 'selected' : '' ?>>
                                <?= $row['school_name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Lecturer -->
                <div>
                    <label class="block mb-1 text-yellow-300 font-semibold">Lecturer</label>
                    <select id="lecturer_id" name="lecturer_id" 
                            class="w-full bg-[#334155] border border-[#475569] p-2 rounded text-white" required>
                        <option value="">-- Select Lecturer --</option>
                    </select>
                </div>

                <!-- Academic Year -->
                <div>
                    <label class="block mb-1 text-yellow-300 font-semibold">Academic Year</label>
                    <select name="academic_year" 
                            class="w-full bg-[#334155] border border-[#475569] p-2 rounded text-white" required>
                        <?php while($row = $years->fetch_assoc()): ?>
                            <option value="<?= $row['academic_year_id'] ?>" 
                                <?= isset($edit) && $edit['academic_year'] == $row['academic_year_id'] ? 'selected' : '' ?>>
                                <?= $row['description'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Semester -->
                <div>
                    <label class="block mb-1 text-yellow-300 font-semibold">Semester</label>
                    <input type="number" min="1" max="2" name="semester" 
                           class="w-full bg-[#334155] border border-[#475569] p-2 rounded text-white" 
                           value="<?= $edit['semester'] ?? '' ?>" required>
                </div>

                <!-- Course Unit -->
                <div>
                    <label class="block mb-1 text-yellow-300 font-semibold">Course Unit</label>
                    <select id="unit_id" name="unit_id" 
                            class="w-full bg-[#334155] border border-[#475569] p-2 rounded text-white" required>
                        <option value="">-- Select Course Unit --</option>
                    </select>
                </div>
            </div>

            <label class="inline-flex items-center mt-2">
                <input type="checkbox" name="allow_multiple" class="mr-2">
                Allow Multiple Lecturers for Same Course
            </label>

            <button type="submit" class="ml-4 bg-yellow-500 text-black font-semibold px-4 py-2 rounded hover:bg-yellow-400 transition">
                <?= $edit ? 'Update Assignment' : 'Add Assignment' ?>
            </button>
        </form>

        <!-- Filter Controls -->
        <form method="get" class="bg-[#1e293b] p-4 rounded-lg mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- School -->
            <select name="school" class="bg-[#334155] border border-[#475569] p-2 rounded text-white">
                <option value="">All Schools</option>
                <?php 
                $schoolsRes = $conn->query("SELECT * FROM schools");
                while($s = $schoolsRes->fetch_assoc()): ?>
                    <option value="<?= $s['school_id'] ?>" <?= $filter_school == $s['school_id'] ? 'selected' : '' ?>>
                        <?= $s['school_name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Department -->
            <select name="department" class="bg-[#334155] border border-[#475569] p-2 rounded text-white">
                <option value="">All Departments</option>
                <?php 
                $deptQuery = "SELECT * FROM departments";
                if ($filter_school) {
                    $deptQuery .= " WHERE school_id=" . intval($filter_school);
                }
                $departmentsRes = $conn->query($deptQuery);
                while($d = $departmentsRes->fetch_assoc()): ?>
                    <option value="<?= $d['department_id'] ?>" <?= $filter_department == $d['department_id'] ? 'selected' : '' ?>>
                        <?= $d['department_name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- Course -->
            <select name="course" class="bg-[#334155] border border-[#475569] p-2 rounded text-white">
                <option value="">All Courses</option>
                <?php 
                $courseQuery = "SELECT * FROM courses";
                if ($filter_department) {
                    $courseQuery .= " WHERE department_id=" . intval($filter_department);
                }
                $coursesRes = $conn->query($courseQuery);
                while($c = $coursesRes->fetch_assoc()): ?>
                    <option value="<?= $c['course_id'] ?>" <?= $filter_course == $c['course_id'] ? 'selected' : '' ?>>
                        <?= $c['course_name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" class="bg-yellow-500 text-black font-semibold px-4 py-2 rounded hover:bg-yellow-400 transition">
                Filter
            </button>
        </form>

        <!-- Assignments Table -->
        <div class="bg-[#1e293b] shadow-md rounded-lg p-4 overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-yellow-500 text-black text-left">
                        <th class="p-2">ID</th>
                        <th class="p-2">School</th>
                        <th class="p-2">Lecturer</th>
                        <th class="p-2">Course Unit</th>
                        <th class="p-2">Semester</th>
                        <th class="p-2">Year</th>
                        <th class="p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "
                        SELECT la.assignment_id, 
                               s.school_name,
                               CONCAT(l.first_name, ' ', l.last_name) AS lecturer_name,
                               u.unit_name, 
                               la.semester, 
                               ay.description AS year_name
                        FROM lecturer_assignments la
                        JOIN lecturers l ON la.lecturer_id = l.lecturer_id
                        JOIN course_units u ON la.unit_id = u.unit_id
                        JOIN academic_years ay ON la.academic_year = ay.academic_year_id
                        JOIN schools s ON la.school_id = s.school_id
                        JOIN courses c ON u.course_id = c.course_id
                        JOIN departments d ON c.department_id = d.department_id
                        WHERE 1=1
                    ";
                    if ($filter_school) {
                        $query .= " AND s.school_id=" . intval($filter_school);
                    }
                    if ($filter_department) {
                        $query .= " AND d.department_id=" . intval($filter_department);
                    }
                    if ($filter_course) {
                        $query .= " AND c.course_id=" . intval($filter_course);
                    }

                    $assignments = $conn->query($query);
                    while ($row = $assignments->fetch_assoc()):
                    ?>
                    <tr class="border-b border-gray-700 hover:bg-[#334155] transition">
                        <td class="p-2"><?= $row['assignment_id'] ?></td>
                        <td class="p-2"><?= $row['school_name'] ?></td>
                        <td class="p-2"><?= $row['lecturer_name'] ?></td>
                        <td class="p-2"><?= $row['unit_name'] ?></td>
                        <td class="p-2"><?= $row['semester'] ?></td>
                        <td class="p-2"><?= $row['year_name'] ?></td>
                        <td class="p-2 space-x-2">
                            <a href="?edit=<?= $row['assignment_id'] ?>" class="text-blue-400 hover:underline">Edit</a>
                            <a href="?delete=<?= $row['assignment_id'] ?>" 
                               onclick="return confirm('Delete this assignment?')" 
                               class="text-red-400 hover:underline">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
