<?php
include '../db.php';

$school_id = $_GET['school_id'] ?? '';
$course_id = $_GET['course_id'] ?? '';

$query = "SELECT c.*, d.department_name, s.school_name FROM courses c 
          JOIN departments d ON c.department_id = d.department_id 
          JOIN schools s ON c.school_id = s.school_id";

if ($course_id) $query .= " WHERE c.course_id=" . intval($course_id);
else if ($school_id) $query .= " WHERE c.school_id=" . intval($school_id);

$query .= " ORDER BY c.course_id DESC";
$res = $conn->query($query);

$data = [];
while($row = $res->fetch_assoc()) $data[] = $row;
echo json_encode($data);
