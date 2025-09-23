<?php
require_once "../db.php";

if (isset($_GET['department_id'])) {
    $department_id = intval($_GET['department_id']);
    $query = "SELECT course_id, course_name FROM courses WHERE department_id = ? ORDER BY course_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $options = [];
    while ($course = $result->fetch_assoc()) {
        $options[] = ['course_id' => $course['course_id'], 'course_name' => $course['course_name']];
    }
    echo json_encode($options);
    $stmt->close();
}
?>
