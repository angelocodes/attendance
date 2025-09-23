<?php
session_start();
include '../db.php';

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    include 'admin_navbar.php';
}

// Handle AJAX unenroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'unenroll') {
    $enrollment_ids = $_POST['enrollment_ids'] ?? [];
    if (!empty($enrollment_ids)) {
        $ids = implode(',', array_map('intval', $enrollment_ids));
        $conn->query("DELETE FROM student_enrollments WHERE enrollment_id IN ($ids)");
        echo json_encode(['status' => true, 'msg' => 'Students unenrolled successfully.']);
    } else {
        echo json_encode(['status' => false, 'msg' => 'No enrollments selected.']);
    }
    exit();
}

// Handle AJAX enroll single
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'enroll_single') {
    $student_id = intval($_POST['student_id']);
    $unit_ids = $_POST['unit_ids'] ?? [];
    $academic_year = $_POST['academic_year'];
    $semester = intval($_POST['semester']);
    if ($student_id && !empty($unit_ids) && $academic_year && in_array($semester, [1,2])) {
        $stmt = $conn->prepare("INSERT IGNORE INTO student_enrollments (student_id, unit_id, academic_year, semester) VALUES (?, ?, ?, ?)");
        $count = 0;
        foreach($unit_ids as $unit_id){
            $stmt->bind_param("iisi", $student_id, $unit_id, $academic_year, $semester);
            $stmt->execute();
            $count += $stmt->affected_rows;
        }
        echo json_encode(['status' => true, 'msg' => "Student enrolled in $count unit(s)."]);
    } else {
        echo json_encode(['status' => false, 'msg' => 'Invalid data.']);
    }
    exit();
}

// Fetch all enrolled students with school and department
$query = "SELECT se.enrollment_id, s.registration_number, s.first_name, s.last_name, u.unit_name, u.unit_code, c.course_name, sch.school_name, d.department_name, se.academic_year, se.semester
          FROM student_enrollments se
          JOIN students s ON se.student_id = s.student_id
          JOIN course_units u ON se.unit_id = u.unit_id
          JOIN courses c ON u.course_id = c.course_id
          LEFT JOIN departments d ON c.department_id = d.department_id
          LEFT JOIN schools sch ON d.school_id = sch.school_id
          ORDER BY se.academic_year DESC, se.semester, s.registration_number";
$result = $conn->query($query);

// Get unique values for filters
$schools = [];
$departments = [];
$courses = [];
$units = [];
$academic_years = [];
$semesters = [];

$result->data_seek(0);
while($row = $result->fetch_assoc()){
    $schools[$row['school_name']] = 1;
    $departments[$row['department_name']] = 1;
    $courses[$row['course_name']] = 1;
    $units[$row['unit_name']] = 1;
    $academic_years[$row['academic_year']] = 1;
    $semesters[$row['semester']] = 1;
}
$result->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Student Enrollments</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">

<h1 class="text-3xl font-bold text-yellow-400 mb-6">Manage Student Enrollments</h1>

<!-- Filters -->
<div class="grid grid-cols-12 gap-4 mb-6">
    <div class="col-span-2">
        <label>School</label>
        <select id="filter_school" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
            <option value="">All Schools</option>
            <?php foreach(array_keys($schools) as $school): ?>
                <option value="<?= htmlspecialchars($school) ?>"><?= htmlspecialchars($school) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-2">
        <label>Department</label>
        <select id="filter_department" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
            <option value="">All Departments</option>
            <?php foreach(array_keys($departments) as $dept): ?>
                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-2">
        <label>Course</label>
        <select id="filter_course" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
            <option value="">All Courses</option>
            <?php foreach(array_keys($courses) as $course): ?>
                <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-2">
        <label>Unit</label>
        <select id="filter_unit" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
            <option value="">All Units</option>
            <?php foreach(array_keys($units) as $unit): ?>
                <option value="<?= htmlspecialchars($unit) ?>"><?= htmlspecialchars($unit) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-2">
        <label>Academic Year</label>
        <select id="filter_academic_year" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
            <option value="">All Years</option>
            <?php foreach(array_keys($academic_years) as $year): ?>
                <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-2">
        <label>Semester</label>
        <select id="filter_semester" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
            <option value="">All Semesters</option>
            <option value="1">Semester 1</option>
            <option value="2">Semester 2</option>
        </select>
    </div>
    <div class="col-span-4">
        <label>Search</label>
        <input type="text" id="filter_search" placeholder="Search by name or reg no..." class="w-full px-2 py-2 rounded bg-gray-700 text-white" />
    </div>
</div>

<div class="flex justify-between items-center mb-4">
    <button id="unenrollSelected" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-white font-semibold">Unenroll Selected</button>
    <button id="openEnrollModal" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded font-semibold">Enroll New Students</button>
</div>

<div class="overflow-x-auto">
    <table class="min-w-full bg-gray-700 rounded-lg" id="enrolledTable">
        <thead class="bg-yellow-500 text-black">
            <tr>
                <th class="px-4 py-2"><input type="checkbox" id="selectAll"></th>
                <th class="px-4 py-2">Reg No</th>
                <th class="px-4 py-2">Name</th>
                <th class="px-4 py-2">School</th>
                <th class="px-4 py-2">Department</th>
                <th class="px-4 py-2">Course</th>
                <th class="px-4 py-2">Unit</th>
                <th class="px-4 py-2">Academic Year</th>
                <th class="px-4 py-2">Semester</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr class="border-b border-gray-600" data-school="<?= htmlspecialchars($row['school_name']) ?>" data-department="<?= htmlspecialchars($row['department_name']) ?>" data-course="<?= htmlspecialchars($row['course_name']) ?>" data-unit="<?= htmlspecialchars($row['unit_name']) ?>" data-academic-year="<?= htmlspecialchars($row['academic_year']) ?>" data-semester="<?= htmlspecialchars($row['semester']) ?>" data-name="<?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>" data-reg="<?= htmlspecialchars($row['registration_number']) ?>">
                    <td class="px-4 py-2"><input type="checkbox" class="enrollment-checkbox" value="<?= $row['enrollment_id'] ?>"></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['registration_number']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['school_name']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['department_name']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['course_name']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['unit_name'] . ' (' . $row['unit_code'] . ')') ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['academic_year']) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($row['semester']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!--<button id="openEnrollModal" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded font-semibold mb-4">Manage Enrollments</button>

-- Enrollment Modal -->
<div id="enrollModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-800 p-6 rounded-lg max-w-2xl w-full overflow-y-auto max-h-[90vh]">
        <h2 class="text-xl font-bold text-yellow-400 mb-4">Enroll Student</h2>

        <div class="mb-4">
            <label class="block text-white mb-2">Registration Number</label>
            <input type="text" id="student_reg" placeholder="Enter student reg no..." class="w-full px-2 py-2 rounded bg-gray-700 text-white" />
        </div>

        <div id="studentInfo" class="mb-4 p-4 bg-gray-600 rounded hidden">
            <p id="studentName"></p>
            <p id="studentCourse"></p>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-white mb-2">Academic Year</label>
                <select id="enroll_academic_year" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
                    <option value="">Select Year</option>
                    <?php
                    $yearsResult = $conn->query("SELECT academic_year_id, CONCAT(start_year, '/', end_year) AS year_name FROM academic_years ORDER BY start_year DESC");
                    while($year=$yearsResult->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($year['year_name']) ?>"><?= htmlspecialchars($year['year_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-white mb-2">Semester</label>
                <select id="enroll_semester" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
                    <option value="">Select Semester</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>
        </div>

        <div id="unitsList" class="mb-4 hidden">
            <label class="block text-white mb-2">Select Units to Enroll</label>
            <div id="unitsContainer" class="max-h-40 overflow-y-auto bg-gray-700 p-2 rounded"></div>
        </div>

        <div class="flex justify-end space-x-2">
            <button id="enrollStudent" class="bg-yellow-500 px-4 py-2 rounded text-black font-semibold">Enroll Student</button>
            <button id="closeEnrollModal" class="bg-gray-600 px-4 py-2 rounded text-white font-semibold">Close</button>
        </div>
    </div>
</div>

<script>
let schools=[], departments=[], courses=[], units=[];

// Load schools
function loadSchools() {
    $.getJSON('load_schools.php', function(data){
        schools = data;
        let s = $('#modal_school_id'); s.empty().append('<option value="">Select School</option>');
        schools.forEach(x => s.append(`<option value="${x.school_id}">${x.school_name}</option>`));
    });
}

// Load dependent selects
$('#modal_school_id').change(function(){
    let school_id = $(this).val();
    if(!school_id) { $('#modal_department_id').html(''); return; }
    $.getJSON('load_departments.php',{school_id}, function(data){
        departments = data;
        let d = $('#modal_department_id'); d.empty().append('<option value="">Select Department</option>');
        data.forEach(x=>d.append(`<option value="${x.department_id}">${x.department_name}</option>`));
    });
});

$('#modal_department_id').change(function(){
    let dept_id = $(this).val();
    if(!dept_id){ $('#modal_course_id').html(''); return; }
    $.getJSON('fetch_courses.php',{department_id: dept_id}, function(data){
        courses = data;
        let c = $('#modal_course_id'); c.empty().append('<option value="">Select Course</option>');
        data.forEach(x=>c.append(`<option value="${x.course_id}">${x.course_name}</option>`));
    });
});

$('#modal_course_id').change(function(){
    let course_id = $(this).val();
    if(!course_id){ $('#modal_unit_id').html(''); return; }
    $.getJSON('fetch_units.php',{course_id}, function(data){
        units = data;
        let u = $('#modal_unit_id'); u.empty().append('<option value="">Select Unit</option>');
        data.forEach(x=>u.append(`<option value="${x.unit_id}">${x.unit_name}</option>`));
    });
});

// Load students
function loadStudents(){
    let unit_id = $('#modal_unit_id').val();
    let academic_year = $('#modal_academic_year').val();
    let semester = $('#modal_semester').val();
    let search = $('#student_search').val();
    if(!unit_id || !academic_year) { $('#studentsList tbody').empty(); return; }

    $.get('fetch_students.php',{unit_id, academic_year, semester, search}, function(data){
        $('#studentsList tbody').html(data);
    });
}

$('#modal_unit_id, #modal_academic_year, #modal_semester').change(loadStudents);
$('#student_search').on('input', loadStudents);

// Enroll/Unenroll
function processSelected(action){
    let student_ids = [];
    $('#studentsList tbody input[type=checkbox]:checked').each(function(){ student_ids.push($(this).val()); });
    if(student_ids.length===0){ alert('Select students.'); return; }

    $.post('manage_student_enrollments.php',{
        ajax_action: action,
        student_ids,
        unit_id: $('#modal_unit_id').val(),
        academic_year: $('#modal_academic_year').val(),
        semester: $('#modal_semester').val()
    }, function(resp){
        let r = JSON.parse(resp);
        alert(r.msg);
        loadStudents();
    });
}

$('#enrollSelected').click(()=>processSelected('enroll'));
$('#unenrollSelected').click(()=>processSelected('unenroll'));
$('#openEnrollModal').click(()=>$('#enrollModal').removeClass('hidden').addClass('flex'));
$('#closeEnrollModal').click(()=>$('#enrollModal').removeClass('flex').addClass('hidden'));

$('#selectAll').change(function(){
    $('.enrollment-checkbox').prop('checked', $(this).prop('checked'));
});

$('#unenrollSelected').click(function(){
    let enrollment_ids = [];
    $('.enrollment-checkbox:checked').each(function(){ enrollment_ids.push($(this).val()); });
    if(enrollment_ids.length === 0){ alert('Select enrollments to unenroll.'); return; }
    if(confirm('Unenroll selected students?')){
        $.post('manage_student_enrollments.php', {ajax_action: 'unenroll', enrollment_ids}, function(resp){
            let r = JSON.parse(resp);
            alert(r.msg);
            location.reload();
        });
    }
});

function filterTable(){
    let school = $('#filter_school').val().toLowerCase();
    let department = $('#filter_department').val().toLowerCase();
    let course = $('#filter_course').val().toLowerCase();
    let unit = $('#filter_unit').val().toLowerCase();
    let academic_year = $('#filter_academic_year').val().toLowerCase();
    let semester = $('#filter_semester').val().toLowerCase();
    let search = $('#filter_search').val().toLowerCase();

    $('#enrolledTable tbody tr').each(function(){
        let row = $(this);
        let show = true;
        if(school && row.data('school').toLowerCase().indexOf(school) === -1) show = false;
        if(department && row.data('department').toLowerCase().indexOf(department) === -1) show = false;
        if(course && row.data('course').toLowerCase().indexOf(course) === -1) show = false;
        if(unit && row.data('unit').toLowerCase().indexOf(unit) === -1) show = false;
        if(academic_year && row.data('academic-year').toLowerCase().indexOf(academic_year) === -1) show = false;
        if(semester && row.data('semester').toLowerCase() !== semester) show = false;
        if(search && (row.data('name').toLowerCase().indexOf(search) === -1 && row.data('reg').toLowerCase().indexOf(search) === -1)) show = false;
        row.toggle(show);
    });
}

$('#filter_school, #filter_department, #filter_course, #filter_unit, #filter_academic_year, #filter_semester').change(filterTable);
$('#filter_search').on('input', filterTable);

// Enrollment modal
let currentStudentId = null;
let currentCourseId = null;

$('#student_reg').on('input', function(){
    let reg = $(this).val().trim();
    if(reg.length > 0){
        $.get('fetch_student_by_reg.php', {reg_no: reg}, function(data){
            let resp = JSON.parse(data);
            if(resp.error){
                $('#studentInfo').addClass('hidden');
                alert(resp.error);
                currentStudentId = null;
                currentCourseId = null;
            } else {
                $('#studentName').text('Name: ' + resp.first_name + ' ' + resp.last_name);
                $('#studentCourse').text('Course: ' + resp.course_name);
                $('#studentInfo').removeClass('hidden');
                currentStudentId = resp.student_id;
                currentCourseId = resp.course_id;
            }
        });
    } else {
        $('#studentInfo').addClass('hidden');
        currentStudentId = null;
        currentCourseId = null;
    }
});

$('#enroll_academic_year, #enroll_semester').change(function(){
    if(currentCourseId && $('#enroll_academic_year').val() && $('#enroll_semester').val()){
        // Fetch units for the student's course
        $.getJSON('fetch_units.php', {course_id: currentCourseId}, function(units){
            let html = '';
            units.forEach(u => {
                html += `<label class="block"><input type="checkbox" value="${u.unit_id}"> ${u.unit_name}</label>`;
            });
            $('#unitsContainer').html(html);
            $('#unitsList').removeClass('hidden');
        });
    } else {
        $('#unitsList').addClass('hidden');
    }
});

$('#enrollStudent').click(function(){
    let academic_year = $('#enroll_academic_year').val();
    let semester = $('#enroll_semester').val();
    let selectedUnits = [];
    $('#unitsContainer input:checked').each(function(){ selectedUnits.push($(this).val()); });
    if(!currentStudentId || !academic_year || !semester || selectedUnits.length === 0){
        alert('Please fill all fields and select units.');
        return;
    }
    $.post('manage_student_enrollments.php', {
        ajax_action: 'enroll_single',
        student_id: currentStudentId,
        unit_ids: selectedUnits,
        academic_year,
        semester
    }, function(resp){
        let r = JSON.parse(resp);
        alert(r.msg);
        if(r.status) location.reload();
    });
});

// Initial load
loadSchools();
</script>
</body>
</html>
