<?php
include '../db.php';
include 'admin_navbar.php';

// Fetch schools for dropdown
$schoolsResult = $conn->query("SELECT * FROM schools ORDER BY school_name");

// Handle Add
if (isset($_POST['add'])) {
    $department_name = trim($_POST['department_name']);
    $school_id = (int)$_POST['school_id'];
    $head_of_department = trim($_POST['head_of_department']);

    if ($department_name && $school_id) {
        $stmt = $conn->prepare("INSERT INTO departments (department_name, school_id, head_of_department) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $department_name, $school_id, $head_of_department);
        $stmt->execute();
        header("Location: manage_departments.php");
        exit();
    }
}

// Handle Update via POST (from modal)
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $department_name = trim($_POST['department_name']);
    $school_id = (int)$_POST['school_id'];
    $head_of_department = trim($_POST['head_of_department']);

    if ($department_name && $school_id) {
        $stmt = $conn->prepare("UPDATE departments SET department_name = ?, school_id = ?, head_of_department = ? WHERE department_id = ?");
        $stmt->bind_param("sisi", $department_name, $school_id, $head_of_department, $id);
        $stmt->execute();
        header("Location: manage_departments.php");
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM departments WHERE department_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_departments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Departments</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">

<h1 class="text-3xl font-bold text-yellow-400 mb-6">Manage Departments</h1>

<!-- Add New Department -->
<form method="POST" class="mb-6 grid grid-cols-6 gap-4 max-w-full items-center">
    <input type="text" name="department_name" placeholder="Department Name" required
           class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" />

    <select name="school_id" required class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400">
        <option value="">Select School</option>
        <?php while ($school = $schoolsResult->fetch_assoc()): ?>
            <option value="<?= $school['school_id'] ?>"><?= htmlspecialchars($school['school_name']) ?></option>
        <?php endwhile; ?>
    </select>

    <input type="text" name="head_of_department" placeholder="Head of Department (optional)"
           class="px-4 py-2 rounded bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-yellow-400" />

    <button name="add" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded font-semibold text-black col-span-1">Add</button>
</form>

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

<!-- Departments Table -->
<div class="overflow-x-auto max-w-full">
    <table class="min-w-full bg-gray-800 rounded-lg" id="departmentsTable">
        <thead class="bg-yellow-500 text-black">
        <tr>
            <th class="px-4 py-2 text-left">ID</th>
            <th class="px-4 py-2 text-left">Department Name</th>
            <th class="px-4 py-2 text-left">School</th>
            <th class="px-4 py-2 text-left">Head of Department</th>
            <th class="px-4 py-2 text-left">Actions</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-800 p-6 rounded-lg max-w-md w-full">
        <h2 class="text-xl font-bold text-yellow-400 mb-4">Edit Department</h2>
        <form method="POST" id="editForm">
            <input type="hidden" name="id" id="edit_id" />

            <label class="block mb-2">Department Name</label>
            <input type="text" name="department_name" id="edit_department_name" required
                   class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4" />

            <label class="block mb-2">School</label>
            <select name="school_id" id="edit_school_id" required class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4"></select>

            <label class="block mb-2">Head of Department</label>
            <input type="text" name="head_of_department" id="edit_head_of_department"
                   class="w-full px-4 py-2 rounded bg-gray-700 text-white mb-4" />

            <div class="flex justify-end space-x-2">
                <button type="submit" name="update" class="bg-yellow-500 px-4 py-2 rounded text-black font-semibold">Update</button>
                <button type="button" id="closeModal" class="bg-gray-600 px-4 py-2 rounded text-white font-semibold">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let schools = [];
// Load schools for edit modal
function loadSchoolsForModal() {
    $.getJSON('load_schools.php', function(data){
        schools = data;
        $('#edit_school_id').empty();
        schools.forEach(s => {
            $('#edit_school_id').append(`<option value="${s.school_id}">${s.school_name}</option>`);
        });
    });
}

// Load departments
function loadDepartments(school_id = '') {
    $.getJSON('load_department.php', {school_id}, function(data){
        let tbody = '';
        data.forEach(d => {
            tbody += `<tr class="border-b border-gray-700">
                <td class="px-4 py-2">${d.department_id}</td>
                <td class="px-4 py-2">${d.department_name}</td>
                <td class="px-4 py-2">${d.school_name}</td>
                <td class="px-4 py-2">${d.head_of_department || ''}</td>
                <td class="px-4 py-2 space-x-2">
                    <button onclick="openEditModal(${d.department_id})" class="text-blue-400 hover:underline">Edit</button>
                    <a href="?delete=${d.department_id}" onclick="return confirm('Delete this department?')" class="text-red-400 hover:underline">Delete</a>
                </td>
            </tr>`;
        });
        $('#departmentsTable tbody').html(tbody);
    });
}

// Open modal and populate values
function openEditModal(id) {
    $.getJSON('load_department.php', {department_id: id}, function(data){
        const d = data[0];
        $('#edit_id').val(d.department_id);
        $('#edit_department_name').val(d.department_name);
        $('#edit_head_of_department').val(d.head_of_department);
        $('#edit_school_id').val(d.school_id);
        $('#editModal').removeClass('hidden').addClass('flex');
    });
}

$('#closeModal').click(function(){
    $('#editModal').removeClass('flex').addClass('hidden');
});

$('#filterSchool').change(function(){
    const schoolId = $(this).val();
    loadDepartments(schoolId);
});

// Initial load
loadSchoolsForModal();
loadDepartments();
</script>

</body>
</html>
