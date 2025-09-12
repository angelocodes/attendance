<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'config.php';

// Ensure no output before JSON
ob_start();

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../logout.php");
    exit;
}
$_SESSION['last_activity'] = time();

// Validate user session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    ob_end_flush();
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in mark_notifications_unread.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    ob_end_flush();
    exit;
}

// Validate notification IDs
$notification_ids = isset($_POST['notification_ids']) && is_array($_POST['notification_ids'])
    ? array_filter($_POST['notification_ids'], 'is_numeric')
    : [];

if (empty($notification_ids)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No valid notification IDs provided.']);
    ob_end_flush();
    exit;
}

// Prepare and execute update
$placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 0 
    WHERE notification_id IN ($placeholders) 
    AND (user_id = ? OR user_id IS NULL) 
    AND user_type = 'lecturer'
    AND is_read = 1
");
if (!$stmt) {
    error_log("Statement preparation failed: " . $conn->error, 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to prepare statement.']);
    ob_end_flush();
    exit;
}

$types = str_repeat('i', count($notification_ids)) . 'i';
$params = array_merge($notification_ids, [$lecturer_id]);
$stmt->bind_param($types, ...$params);

header('Content-Type: application/json');
if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    echo json_encode(['success' => true, 'message' => "$affected_rows notification(s) marked as unread."]);
} else {
    error_log("Failed to mark notifications as unread: " . $stmt->error, 3, '../logs/errors.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to mark notifications as unread.']);
}

$stmt->close();
ob_end_flush();
?>