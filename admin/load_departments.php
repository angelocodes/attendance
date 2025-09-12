<?php
include '../db.php';

$school_id = intval($_GET['school_id'] ?? 0);
$departments = [];

if ($school_id) {
    $stmt = $conn->prepare("
        SELECT d.department_id, d.department_name, d.head_of_department, s.school_name
        FROM departments d
        JOIN schools s ON d.school_id = s.school_id
        WHERE d.school_id = ?
        ORDER BY d.department_name
    ");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

echo json_encode($departments);
