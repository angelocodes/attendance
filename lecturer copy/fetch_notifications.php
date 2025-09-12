<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'config.php';

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../logout.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Validate user session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access.']);
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in fetch_notifications.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Get parameters
$notifications_limit = defined('NOTIFICATIONS_LIMIT') ? NOTIFICATIONS_LIMIT : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $notifications_limit;
$status = isset($_GET['status']) && in_array($_GET['status'], ['all', 'read', 'unread']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) && validateDate($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) && validateDate($_GET['date_to']) ? $_GET['date_to'] : null;
$search = isset($_GET['search']) && !empty(trim($_GET['search'])) ? trim($_GET['search']) : null;

// Build query
$query = "
    SELECT n.notification_id, n.message, n.created_at, n.is_read
    FROM notifications n
    LEFT JOIN notification_preferences np ON n.notification_id = np.notification_id AND np.user_id = ?
    WHERE (n.user_id = ? OR n.user_id IS NULL) 
    AND n.user_type = 'lecturer'
    AND (np.is_hidden IS NULL OR np.is_hidden = 0)
";
$count_query = "
    SELECT COUNT(*) as total
    FROM notifications n
    LEFT JOIN notification_preferences np ON n.notification_id = np.notification_id AND np.user_id = ?
    WHERE (n.user_id = ? OR n.user_id IS NULL) 
    AND n.user_type = 'lecturer'
    AND (np.is_hidden IS NULL OR np.is_hidden = 0)
";
$types = "ii";
$params = [$lecturer_id, $lecturer_id];

if ($status === 'read') {
    $query .= " AND n.is_read = 1";
    $count_query .= " AND n.is_read = 1";
} elseif ($status === 'unread') {
    $query .= " AND n.is_read = 0";
    $count_query .= " AND n.is_read = 0";
}

if ($date_from) {
    $query .= " AND n.created_at >= ?";
    $count_query .= " AND n.created_at >= ?";
    $types .= "s";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $query .= " AND n.created_at <= ?";
    $count_query .= " AND n.created_at <= ?";
    $types .= "s";
    $params[] = $date_to . ' 23:59:59';
}

if ($search) {
    $query .= " AND n.message LIKE ?";
    $count_query .= " AND n.message LIKE ?";
    $types .= "s";
    $params[] = "%$search%";
}

$query .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $notifications_limit;
$params[] = $offset;

// Fetch notifications
$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("Statement preparation failed: " . $conn->error, 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to prepare statement.']);
    exit;
}
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("Failed to fetch notifications: " . $stmt->error, 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to fetch notifications.']);
    exit;
}

// Count total notifications
$stmt = $conn->prepare($count_query);
if (!$stmt) {
    error_log("Count statement preparation failed: " . $conn->error, 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to prepare count statement.']);
    exit;
}
$stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
if ($stmt->execute()) {
    $result = $stmt->get_result()->fetch_assoc();
    $total_notifications = $result['total'];
    $stmt->close();
} else {
    error_log("Failed to count notifications: " . $stmt->error, 3, '../logs/errors.log');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to count notifications.']);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'total_notifications' => $total_notifications
]);

// Helper function
function validateDate($date, $format = 'Y-m-d') {
    if (empty($date)) return false;
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>