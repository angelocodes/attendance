<?php
include '../db.php';
include 'admin_navbar.php';

// Fetch schools
$schoolsResult = $conn->query("SELECT * FROM schools ORDER BY school_name");

// Handle Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $course_code = strtoupper(trim($_POST['course_code']));
    $course_name = trim($_POST['course_name']);
    $department_id = intval($_POST['department_id']);
    $school_id = intval($_POST['school_id']);
    $duration_years = intval($_POST['duration_years']);

    if ($course_code && $course_name && $department_id && $school_id) {
        if ($id) {
            $stmt = $conn->prepare("UPDATE courses SET course_code=?, course_name=?, department_id=?, duration_years=?, school_id=? WHERE course_id=?");
            $stmt->bind_param("ssiiii", $course_code, $course_name, $department_id, $duration_years, $school_id, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, department_id, duration_years, school_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiii", $course_code, $course_name, $department_id, $duration_years, $school_id);
        }
        $stmt->execute();
        header("Location: manage_courses.php");
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_courses.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Courses</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">

<h1 class="text-3xl font-bold text-yellow-400 mb-6">Manage Courses</h1>

<!-- Add Course Button -->
<button id="openAddModal" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded font-semibold mb-4">Add Course</button>

<!-- Filter by School -->
<div class="mb-4">
    <label class="mr-2 font-semibold text-yellow-400">Filter by School:</label>
    <select id="filterSchool" class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
        <option value="">All Schools</option>
        <?php
        $schoolsResult->data_seek(0);
        while ($s = $schoolsResult->fetch_assoc()):
        ?>
        <option value="<?= $s['school_id'] ?>"><?= htmlspecialchars($s['school_name']) ?></option>
        <?php endwhile; ?>
    </select>
</div>

<!-- Courses Table -->
<div class="overflow-x-auto max-w-full">
    <table class="min-w-full bg-gray-800 rounded-lg" id="coursesTable">
        <thead class="bg-yellow-500 text-black">
        <tr>
            <th class="px-4 py-2">ID</th>
            <th class="px-4 py-2">Code</th>
            <th class="px-4 py-2">Name</th>
            <th class="px-4 py-2">Department</th>
            <th class="px-4 py-2">School</th>
            <th class="px-4 py-2">Duration</th>
            <th class="px-4 py-2">Actions</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div id="courseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-800 p-6 rounded-lg max-w-md w-full">
        <h2 class="text-xl font-bold text-yellow-400 mb-4" id="modalTitle">Add Course</h2>
        <form method="POST" id="courseForm">
            <input type="hidden" name="id" id="course_id">

            <label class="block mb-2">School</label>
            <select name="school_id" id="modal_school_id" class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4" required></select>

            <label class="block mb-2">Department</label>
            <select name="department_id" id="modal_department_id" class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4" required></select>

            <label class="block mb-2">Course Code</label>
            <input type="text" name="course_code" id="modal_course_code" required class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4">

            <label class="block mb-2">Course Name</label>
            <input type="text" name="course_name" id="modal_course_name" required class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4">

            <label class="block mb-2">Duration (Years)</label>
            <input type="number" name="duration_years" id="modal_duration_years" min="1" max="10"
                   class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4" required>

            <div class="flex justify-end space-x-2">
                <button type="submit" class="bg-yellow-500 px-4 py-2 rounded text-black font-semibold">Save</button>
                <button type="button" id="closeModal" class="bg-gray-600 px-4 py-2 rounded text-white font-semibold">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let schools = [], departments = [];

// Load schools and departments
function loadSchools() {
    $.getJSON('load_schools.php', function(data){
        schools = data;
        let select = $('#modal_school_id');
        select.empty();
        select.append('<option value="">Select School</option>');
        schools.forEach(s => select.append(`<option value="${s.school_id}">${s.school_name}</option>`));
    });
}

function loadDepartments(school_id, selectId='#modal_department_id') {
    if (!school_id) return;
    $.getJSON('load_departments.php', {school_id}, function(data){
        let select = $(selectId);
        select.empty();
        select.append('<option value="">Select Department</option>');
        data.forEach(d => select.append(`<option value="${d.department_id}">${d.department_name}</option>`));
    });
}

// Load courses
function loadCourses(school_id='') {
    $.getJSON('load_courses.php', {school_id}, function(data){
        let tbody = '';
        data.forEach(c => {
            tbody += `<tr class="border-b border-gray-700">
                <td class="px-4 py-2">${c.course_id}</td>
                <td class="px-4 py-2">${c.course_code}</td>
                <td class="px-4 py-2">${c.course_name}</td>
                <td class="px-4 py-2">${c.department_name}</td>
                <td class="px-4 py-2">${c.school_name}</td>
                <td class="px-4 py-2 text-center">${c.duration_years}</td>
                <td class="px-4 py-2 space-x-2">
                    <button onclick="openModal(${c.course_id})" class="text-blue-400 hover:underline">Edit</button>
                    <a href="?delete=${c.course_id}" onclick="return confirm('Delete this course?')" class="text-red-400 hover:underline">Delete</a>
                </td>
            </tr>`;
        });
        $('#coursesTable tbody').html(tbody);
    });
}

// Open modal
function openModal(id=0){
    $('#courseForm')[0].reset();
    $('#course_id').val(id);
    if(id) {
        $('#modalTitle').text('Edit Course');
        $.getJSON('load_courses.php', {course_id:id}, function(data){
            const c = data[0];
            $('#modal_course_code').val(c.course_code);
            $('#modal_course_name').val(c.course_name);
            $('#modal_duration_years').val(c.duration_years);
            $('#modal_school_id').val(c.school_id);
            loadDepartments(c.school_id);
            setTimeout(()=>$('#modal_department_id').val(c.department_id),100);
        });
    } else {
        $('#modalTitle').text('Add Course');
        loadDepartments($('#modal_school_id').val());
    }
    $('#courseModal').removeClass('hidden').addClass('flex');
}

$('#closeModal').click(()=>$('#courseModal').removeClass('flex').addClass('hidden'));
$('#filterSchool').change(function(){ loadCourses($(this).val()); });
$('#modal_school_id').change(function(){ loadDepartments($(this).val()); });
$('#openAddModal').click(()=>openModal());

// Initial load
loadSchools();
loadCourses();
</script>

</body>
</html>
