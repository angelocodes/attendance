<?php
require_once "../db.php";

if (isset($_GET['course_id'])) {
    $course_id = intval($_GET['course_id']);
    $query = "SELECT unit_id, unit_name FROM course_units WHERE course_id = ? ORDER BY unit_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $options = [];
    while ($unit = $result->fetch_assoc()) {
        $options[] = ['unit_id' => $unit['unit_id'], 'unit_name' => $unit['unit_name']];
    }
    echo json_encode($options);
    $stmt->close();
}
?>
