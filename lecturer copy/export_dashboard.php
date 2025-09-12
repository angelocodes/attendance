<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'lecturer_controller.php';
require_once 'config.php';

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
    header("Location: ../access_denied.php");
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in export_dashboard.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

$controller = new LecturerController($conn);
$export_type = $_POST['export_type'] ?? '';

if ($export_type === 'sessions') {
    $stmt = $conn->prepare("
        SELECT cs.session_date, cs.start_time, cs.end_time, cs.venue, cu.unit_name
        FROM class_sessions cs
        JOIN lecturer_assignments la ON cs.unit_id = la.unit_id AND cs.lecturer_id = la.lecturer_id
        JOIN course_units cu ON la.unit_id = cu.unit_id
        WHERE cs.lecturer_id = ?
        ORDER BY cs.session_date DESC
    ");
    $stmt->bind_param("i", $lecturer_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $sessions = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sessions_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Start Time', 'End Time', 'Venue', 'Unit']);
        foreach ($sessions as $session) {
            fputcsv($output, [
                $session['session_date'],
                $session['start_time'],
                $session['end_time'],
                $session['venue'],
                $session['unit_name']
            ]);
        }
        fclose($output);
        exit;
    } else {
        error_log("Failed to fetch sessions: " . $stmt->error, 3, '../logs/errors.log');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch sessions.']);
        exit;
    }
} elseif ($export_type === 'attendance') {
    $stmt = $conn->prepare("
        SELECT cs.session_date, cu.unit_name, s.registration_number, s.first_name, s.last_name, a.attendance_status
        FROM attendance a
        JOIN class_sessions cs ON a.session_id = cs.session_id
        JOIN lecturer_assignments la ON cs.unit_id = la.unit_id AND cs.lecturer_id = la.lecturer_id
        JOIN course_units cu ON la.unit_id = cu.unit_id
        JOIN students s ON a.student_id = s.student_id
        WHERE cs.lecturer_id = ?
        ORDER BY cs.session_date DESC
    ");
    $stmt->bind_param("i", $lecturer_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $attendance = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Session Date', 'Unit', 'Reg Number', 'Name', 'Attendance']);
        foreach ($attendance as $record) {
            fputcsv($output, [
                $record['session_date'],
                $record['unit_name'],
                $record['registration_number'],
                ($record['last_name'] ? $record['first_name'] . ' ' . $record['last_name'] : $record['first_name']),
                $record['attendance_status']
            ]);
        }
        fclose($output);
        exit;
    } else {
        error_log("Failed to fetch attendance: " . $stmt->error, 3, '../logs/errors.log');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch attendance.']);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid export type.']);
    exit;
}
?>