<?php
session_start();
include '../db.php';
include 'admin_navbar.php';

// Fetch academic years
$yearsResult = $conn->query("SELECT academic_year_id, CONCAT(start_year, '/', end_year) AS year_name FROM academic_years ORDER BY start_year DESC");

// Handle AJAX enroll/unenroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    $action = $_POST['ajax_action'];
    $student_ids = $_POST['student_ids'] ?? [];
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $academic_year = $_POST['academic_year'] ?? '';
    $semester = intval($_POST['semester'] ?? 0);
    $result = ['status'=>false,'msg'=>'Invalid request'];

    if($unit_id && $academic_year && in_array($semester,[1,2]) && !empty($student_ids)){
        if($action==='enroll'){
            $stmt = $conn->prepare("INSERT IGNORE INTO student_enrollments (student_id, unit_id, academic_year, semester) VALUES (?, ?, ?, ?)");
            foreach($student_ids as $sid){
                $stmt->bind_param("iisi",$sid,$unit_id,$academic_year,$semester);
                $stmt->execute();
            }
            $result = ['status'=>true,'msg'=>'Students enrolled successfully.'];
        } elseif($action==='unenroll'){
            $stmt = $conn->prepare("DELETE FROM student_enrollments WHERE student_id=? AND unit_id=? AND academic_year=? AND semester=?");
            foreach($student_ids as $sid){
                $stmt->bind_param("iisi",$sid,$unit_id,$academic_year,$semester);
                $stmt->execute();
            }
            $result = ['status'=>true,'msg'=>'Students unenrolled successfully.'];
        }
    }
    echo json_encode($result);
    exit();
}
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

<button id="openEnrollModal" class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded font-semibold mb-4">Manage Enrollments</button>

<!-- Enrollment Modal -->
<div id="enrollModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-800 p-6 rounded-lg max-w-5xl w-full overflow-y-auto max-h-[90vh]">
        <h2 class="text-xl font-bold text-yellow-400 mb-4">Manage Enrollments</h2>
        <div class="grid grid-cols-12 gap-4 mb-4">
            <div class="col-span-3">
                <label>Academic Year</label>
                <select id="modal_academic_year" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
                    <option value="">Select Year</option>
                    <?php while($year=$yearsResult->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($year['year_name']) ?>"><?= htmlspecialchars($year['year_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-span-3">
                <label>School</label>
                <select id="modal_school_id" class="w-full px-2 py-2 rounded bg-gray-700 text-white"></select>
            </div>
            <div class="col-span-3">
                <label>Department</label>
                <select id="modal_department_id" class="w-full px-2 py-2 rounded bg-gray-700 text-white"></select>
            </div>
            <div class="col-span-3">
                <label>Course</label>
                <select id="modal_course_id" class="w-full px-2 py-2 rounded bg-gray-700 text-white"></select>
            </div>
            <div class="col-span-3">
                <label>Unit</label>
                <select id="modal_unit_id" class="w-full px-2 py-2 rounded bg-gray-700 text-white"></select>
            </div>
            <div class="col-span-3">
                <label>Semester</label>
                <select id="modal_semester" class="w-full px-2 py-2 rounded bg-gray-700 text-white">
                    <option value="">Select Semester</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>
            <div class="col-span-6">
                <label>Search Students</label>
                <input type="text" id="student_search" placeholder="Search by name..." class="w-full px-2 py-2 rounded bg-gray-700 text-white" />
            </div>
        </div>

        <!-- Student list -->
        <div class="overflow-x-auto max-h-80">
            <table class="min-w-full bg-gray-700 rounded-lg" id="studentsList">
                <thead class="bg-yellow-500 text-black sticky top-0">
                    <tr>
                        <th class="px-4 py-2">Enroll</th>
                        <th class="px-4 py-2">Reg No</th>
                        <th class="px-4 py-2">Name</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="flex justify-end space-x-2 mt-4">
            <button id="enrollSelected" class="bg-yellow-500 px-4 py-2 rounded text-black font-semibold">Enroll Selected</button>
            <button id="unenrollSelected" class="bg-red-500 px-4 py-2 rounded text-black font-semibold">Unenroll Selected</button>
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
    if(!unit_id || !academic_year || !semester) { $('#studentsList tbody').empty(); return; }

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

// Initial load
loadSchools();
</script>
</body>
</html>
