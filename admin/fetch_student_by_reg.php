<?php
require_once "../db.php";

if (isset($_GET['reg_no'])) {
    $reg_no = trim($_GET['reg_no']);
    $stmt = $conn->prepare("SELECT s.student_id, s.first_name, s.last_name, c.course_name, s.course_id FROM students s JOIN courses c ON s.course_id = c.course_id WHERE s.registration_number = ? AND s.status = 'active'");
    $stmt->bind_param("s", $reg_no);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Student not found']);
    }
    $stmt->close();
}
?>
