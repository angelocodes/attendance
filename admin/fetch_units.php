<?php
require_once "../db.php";

if (isset($_POST['course_id'])) {
    $course_id = intval($_POST['course_id']);
    $query = "SELECT unit_id, unit_name FROM course_units WHERE course_id = ? ORDER BY unit_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $options = '';
    while ($unit = $result->fetch_assoc()) {
        $options .= "<option value='{$unit['unit_id']}'>" . htmlspecialchars($unit['unit_name']) . "</option>";
    }
    echo $options;
    $stmt->close();
}
?>