<?php
require_once "../db.php";

if (isset($_POST['school_id'])) {
    $school_id = intval($_POST['school_id']);
    $query = "SELECT course_id, course_name FROM courses WHERE school_id = ? ORDER BY course_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $options = '';
    while ($course = $result->fetch_assoc()) {
        $options .= "<option value='{$course['course_id']}'>" . htmlspecialchars($course['course_name']) . "</option>";
    }
    echo $options;
    $stmt->close();
}
?>