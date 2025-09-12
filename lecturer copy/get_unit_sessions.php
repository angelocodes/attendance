<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'lecturer_controller.php';

header('Content-Type: application/json');
ob_start(); // Start output buffering to catch unexpected output

try {
    // Log request start
    error_log("get_unit_sessions.php accessed at " . date('Y-m-d H:i:s'), 3, '../logs/requests.log');

    // Validate session
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
        error_log("Unauthorized access attempt: session data=" . json_encode($_SESSION), 3, '../logs/errors.log');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
        exit;
    }
    $lecturer_id = (int)$_SESSION['user_id'];
    error_log("Lecturer ID: $lecturer_id", 3, '../logs/requests.log');

    // Validate assignment_id
    $assignment_id = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
    if ($assignment_id <= 0) {
        error_log("Invalid assignment_id: " . ($_POST['assignment_id'] ?? 'missing'), 3, '../logs/errors.log');
        http_response_code(400);
        echo json_encode(['success' => 'false', 'error' => 'Invalid assignment ID']);
        exit();
    }
    error_log("Assignment ID: $assignment_id", 3, '../logs/requests.log');

    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        error_log("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }

    // Fetch sessions
    $controller = new LecturerController($conn);
    $result = $controller->getClassSessions($lecturer_id, $assignment_id);
    error_log("getClassSessions result: " . json_encode($result), 3, '../logs/requests.log');

    // Ensure result is valid
    if (!isset($result['success'])) {
        error_log("Invalid controller response: " . json_encode($result), 3, '../logs/errors.log');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Invalid server response']);
        exit();
    }

    ob_end_clean(); // Discard output buffer
    echo json_encode($result);
} catch (Exception $e) {
    ob_end_clean();
    error_log("Exception in get_unit_sessions.php: " . $e->getMessage(), 3, '../logs/errors.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
exit;
?>