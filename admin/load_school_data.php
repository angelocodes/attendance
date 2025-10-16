<?php
include '../db.php';

$school_id = intval($_GET['school_id']);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

if ($school_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid school ID"]);
    exit;
}

// Get lecturers in school
$lecturersQuery = $conn->query("SELECT lecturer_id, first_name, last_name FROM lecturers WHERE school_id=$school_id");
if (!$lecturersQuery) {
    http_response_code(500);
    echo json_encode(["error" => "Lecturers query failed: " . $conn->error]);
    exit;
}

$lecturerList = [];
while($l = $lecturersQuery->fetch_assoc()) {
    $lecturerList[] = $l;
}

// Get units (via courses in the same school)
$unitsQuery = $conn->query("
    SELECT u.unit_id, u.unit_name, u.unit_code FROM course_units u
    JOIN courses c ON u.course_id=c.course_id
    WHERE c.school_id=$school_id
");
if (!$unitsQuery) {
    http_response_code(500);
    echo json_encode(["error" => "Units query failed: " . $conn->error]);
    exit;
}

$unitList = [];
while($u = $unitsQuery->fetch_assoc()) {
    $unitList[] = $u;
}

header('Content-Type: application/json');
echo json_encode([
    "lecturers" => $lecturerList,
    "units" => $unitList
]);
