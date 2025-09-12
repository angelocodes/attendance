<?php
include '../db.php';

$school_id = intval($_GET['school_id']);

// Get lecturers in school
$lecturers = $conn->query("SELECT * FROM lecturers WHERE school_id=$school_id");
$lecturerList = [];
while($l = $lecturers->fetch_assoc()) {
    $lecturerList[] = $l;
}

// Get units (via courses in the same school)
$units = $conn->query("
    SELECT u.* FROM course_units u 
    JOIN courses c ON u.course_id=c.course_id
    WHERE c.school_id=$school_id
");
$unitList = [];
while($u = $units->fetch_assoc()) {
    $unitList[] = $u;
}

echo json_encode([
    "lecturers" => $lecturerList,
    "units" => $unitList
]);
