<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'lecturer_controller.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];
$assignment_id = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$session_id = isset($_POST['session_id']) && is_numeric($_POST['session_id']) ? (int)$_POST['session_id'] : 0;

if ($assignment_id <= 0 || $session_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid assignment or session ID']);
    exit;
}

if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$controller = new LecturerController($conn);
$result = $controller->getSessionStudents($lecturer_id, $assignment_id, $session_id);
echo json_encode($result);
exit;
?>