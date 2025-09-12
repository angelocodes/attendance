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
    error_log("Database connection failed in hide_notifications.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
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

// Begin transaction
$conn->begin_transaction();

try {
    // Prepare to insert or update notification preferences
    $placeholders = implode(',', array_fill(0, count($notification_ids), '(?, ?, 1)'));
    $types = str_repeat('ii', count($notification_ids));
    $params = [];
    foreach ($notification_ids as $id) {
        $params[] = $lecturer_id;
        $params[] = $id;
    }

    $stmt = $conn->prepare("
        INSERT INTO notification_preferences (user_id, notification_id, is_hidden)
        VALUES $placeholders
        ON DUPLICATE KEY UPDATE is_hidden = 1
    ");
    if (!$stmt) {
        throw new Exception("Statement preparation failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception("Failed to hide notifications: " . $stmt->error);
    }
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => "$affected_rows notification(s) hidden."]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in hide_notifications.php: " . $e->getMessage(), 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to hide notifications.']);
}

ob_end_flush();
?>