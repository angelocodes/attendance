<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';

// Validate user session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Validate input
$assignment_id = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$session_id = isset($_POST['session_id']) && is_numeric($_POST['session_id']) ? (int)$_POST['session_id'] : 0;

if ($assignment_id <= 0 || $session_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid assignment or session ID.']);
    exit;
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Fetch students enrolled in the unit with their attendance status
$stmt = $conn->prepare("
    SELECT s.student_id, s.registration_number, u.username, u.email, ar.status AS attendance_status
    FROM student_enrollments se
    JOIN students s ON se.student_id = s.student_id
    JOIN users u ON s.student_id = u.user_id
    LEFT JOIN attendance_records ar ON s.student_id = ar.student_id AND ar.session_id = ?
    WHERE se.unit_id = (SELECT unit_id FROM lecturer_assignments WHERE assignment_id = ? AND lecturer_id = ?)
");
$stmt->bind_param("iii", $session_id, $assignment_id, $lecturer_id);
if ($stmt->execute()) {
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'students' => $students]);
} else {
    error_log("Failed to fetch students: " . $stmt->error, 3, '../logs/errors.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch students.']);
}
?>