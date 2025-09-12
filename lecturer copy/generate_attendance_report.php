<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'config.php';

// Set error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/errors.log');

// Validate session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    http_response_code(401);
    error_log("Unauthorized access attempt at " . date('Y-m-d H:i:s'));
    exit('Unauthorized access.');
}
$lecturer_id = (int)$_SESSION['user_id'];

// Validate inputs
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    error_log("Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    exit('Method not allowed.');
}

$assignment_id = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
$end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
$date_preset = isset($_POST['date_preset']) ? trim($_POST['date_preset']) : '';
$fields = isset($_POST['fields']) && is_array($_POST['fields']) ? array_map('trim', $_POST['fields']) : [];
$report_title = isset($_POST['report_title']) ? trim($_POST['report_title']) : 'Attendance Report';
$attendance_filter = isset($_POST['attendance_filter']) ? trim($_POST['attendance_filter']) : '';
$include_summary = isset($_POST['include_summary']) && $_POST['include_summary'] === 'on';
$include_session_count = isset($_POST['include_session_count']) && $_POST['include_session_count'] === 'on';

// Validate required inputs
if ($assignment_id <= 0 || empty($fields)) {
    http_response_code(400);
    error_log("Invalid input parameters: assignment_id=$assignment_id, fields=" . json_encode($fields));
    exit('Invalid input parameters.');
}

// Handle date presets
if ($date_preset) {
    $today = date('Y-m-d');
    switch ($date_preset) {
        case 'last_7_days':
            $start_date = date('Y-m-d', strtotime('-6 days', strtotime($today)));
            $end_date = $today;
            break;
        case 'last_30_days':
            $start_date = date('Y-m-d', strtotime('-29 days', strtotime($today)));
            $end_date = $today;
            break;
        case 'this_semester':
            $month = date('n');
            $year = date('Y');
            $start_date = $month < 8 ? "$year-01-01" : "$year-08-01";
            $end_date = $month < 8 ? "$year-05-31" : "$year-12-31";
            break;
        default:
            http_response_code(400);
            error_log("Invalid date preset: $date_preset");
            exit('Invalid date preset.');
    }
}

if (!validateDate($start_date) || !validateDate($end_date) || $end_date < $start_date) {
    http_response_code(400);
    error_log("Invalid date range: start_date=$start_date, end_date=$end_date");
    exit('Invalid date range.');
}

// Database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
    http_response_code(500);
    exit('Database connection failed.');
}

// Fetch unit details
$stmt = $conn->prepare("
    SELECT cu.unit_name, cu.unit_code
    FROM lecturer_assignments la 
    JOIN course_units cu ON la.unit_id = cu.unit_id
    WHERE la.assignment_id = ? AND la.lecturer_id = ?
");
$stmt->bind_param("ii", $assignment_id, $lecturer_id);
if (!$stmt->execute()) {
    error_log("Error fetching unit: " . $stmt->error);
    http_response_code(500);
    exit('Failed to fetch unit details.');
}
$result = $stmt->get_result();
if (!$unit = $result->fetch_assoc()) {
    http_response_code(403);
    error_log("Invalid assignment: assignment_id=$assignment_id, lecturer_id=$lecturer_id");
    exit('Invalid assignment.');
}
$unit_name = $unit['unit_name'];
$unit_code = $unit['unit_code'];
$stmt->close();

// Fetch all enrolled students
$stmt = $conn->prepare("
    SELECT DISTINCT s.student_id, s.registration_number, u.username, u.email
    FROM student_enrollments se
    JOIN students s ON se.student_id = s.student_id
    JOIN users u ON s.student_id = u.user_id
    JOIN lecturer_assignments la ON se.unit_id = la.unit_id
    WHERE la.assignment_id = ?
    ORDER BY s.registration_number
");
$stmt->bind_param("i", $assignment_id);
if (!$stmt->execute()) {
    error_log("Error fetching students: " . $stmt->error);
    http_response_code(500);
    exit('Failed to fetch enrolled students.');
}
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch class sessions
$stmt = $conn->prepare("
    SELECT cs.session_id, cs.session_date, cs.start_time, cs.end_time, cs.venue
    FROM class_sessions cs
    JOIN lecturer_assignments la ON cs.unit_id = la.unit_id
    WHERE la.assignment_id = ? AND cs.session_date BETWEEN ? AND ?
    ORDER BY cs.session_date, cs.start_time
");
$stmt->bind_param("iss", $assignment_id, $start_date, $end_date);
if (!$stmt->execute()) {
    error_log("Error fetching sessions: " . $stmt->error);
    http_response_code(500);
    exit('Failed to fetch class sessions.');
}
$result = $stmt->get_result();
$sessions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch attendance records with optional filter
$attendance = [];
if (!empty($sessions)) {
    $session_ids = array_column($sessions, 'session_id');
    $placeholders = implode(',', array_fill(0, count($session_ids), '?'));
    $query = "
        SELECT ar.session_id, ar.student_id, ar.status
        FROM attendance_records ar
        WHERE ar.session_id IN ($placeholders)
    ";
    if ($attendance_filter && in_array($attendance_filter, ['Present', 'Absent', 'Late'])) {
        $query .= " AND ar.status = ?";
    }
    $stmt = $conn->prepare($query);
    $types = str_repeat('i', count($session_ids));
    $params = $session_ids;
    if ($attendance_filter && in_array($attendance_filter, ['Present', 'Absent', 'Late'])) {
        $types .= 's';
        $params[] = $attendance_filter;
    }
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        error_log("Error fetching attendance records: " . $stmt->error);
        http_response_code(500);
        exit('Failed to fetch attendance records.');
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance[$row['session_id']][$row['student_id']] = $row['status'];
    }
    $stmt->close();
}

// Calculate statistics
$total_possible = count($students) * count($sessions);
$total_present = 0;
$student_stats = [];
foreach ($students as $student) {
    $student_stats[$student['student_id']] = [
        'registration_number' => $student['registration_number'] ?? 'N/A',
        'username' => $student['username'] ?? 'N/A',
        'present' => 0,
        'total' => count($sessions), // Total sessions expected
        'percentage' => 0.0
    ];
}

// Valid attendance statuses for statistics
$valid_statuses = ['Present', 'Absent', 'Late'];

// Count attendance for each student
foreach ($sessions as $session) {
    $session_id = $session['session_id'];
    foreach ($students as $student) {
        $student_id = $student['student_id'];
        if (isset($attendance[$session_id][$student_id])) {
            $status = $attendance[$session_id][$student_id];
            if ($status === 'Present') {
                $student_stats[$student_id]['present']++;
                $total_present++;
            }
        }
    }
}

// Calculate percentages
foreach ($student_stats as &$stats) {
    $stats['percentage'] = $stats['total'] > 0 ? round(($stats['present'] / $stats['total']) * 100, 2) : 0.0;
}
unset($stats);

$general_attendance = $total_possible > 0 ? round(($total_present / $total_possible) * 100, 2) : 0.0;

// Generate CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel compatibility

// Unit details
fputcsv($output, ['Unit Code', $unit_code]);
fputcsv($output, ['Unit Name', $unit_name]);
fputcsv($output, ['Report Title', $report_title]);
fputcsv($output, ['Date Range', "$start_date to $end_date"]);
if ($attendance_filter) {
    fputcsv($output, ['Filter', "Attendance Status: $attendance_filter"]);
}
fputcsv($output, []);

// Session-wise attendance
foreach ($sessions as $index => $session) {
    fputcsv($output, []);
    fputcsv($output, ["Session " . ($index + 1)]);
    $session_info = [
        'Date' => $session['session_date'] ?? 'N/A',
        'Time' => ($session['start_time'] && $session['end_time']) ? $session['start_time'] . '-' . $session['end_time'] : 'N/A',
        'Venue' => $session['venue'] ?? 'N/A'
    ];
    fputcsv($output, ['Date', $session_info['Date']]);
    fputcsv($output, ['Time', $session_info['Time']]);
    fputcsv($output, ['Venue', $session_info['Venue']]);
    $headers = array_filter([
        in_array('student_reg_no', $fields) ? 'Registration Number' : '',
        in_array('student_username', $fields) ? 'Username' : '',
        in_array('student_email', $fields) ? 'Email' : '',
        'Attendance Status'
    ]);
    fputcsv($output, $headers);

    foreach ($students as $student) {
        $session_id = $session['session_id'];
        $student_id = $student['student_id'];
        $status = isset($attendance[$session_id][$student_id]) ? $attendance[$session_id][$student_id] : 'Not Marked';
        // Apply filter if set
        if ($attendance_filter && $status !== $attendance_filter && $status !== 'Not Marked') {
            continue; // Skip students not matching the filter
        }
        $row = array_filter([
            in_array('student_reg_no', $fields) ? ($student['registration_number'] ?? 'N/A') : '',
            in_array('student_username', $fields) ? ($student['username'] ?? 'N/A') : '',
            in_array('student_email', $fields) ? ($student['email'] ?? 'N/A') : '',
            $status
        ]);
        fputcsv($output, $row);
    }
}

// Statistics
if ($include_summary) {
    fputcsv($output, []);
    fputcsv($output, ['Attendance Statistics']);
    fputcsv($output, ['General Attendance', sprintf("%.2f%%", $general_attendance)]);
    fputcsv($output, []);
    fputcsv($output, ['Individual Student Attendance']);
    $stat_headers = ['Registration Number', 'Username'];
    if ($include_session_count) {
        $stat_headers[] = 'Present Sessions';
        $stat_headers[] = 'Total Sessions';
    }
    $stat_headers[] = 'Attendance (%)';
    fputcsv($output, $stat_headers);
    foreach ($student_stats as $stats) {
        $stat_row = [
            $stats['registration_number'],
            $stats['username']
        ];
        if ($include_session_count) {
            $stat_row[] = $stats['present'];
            $stat_row[] = $stats['total'];
        }
        $stat_row[] = sprintf("%.2f", $stats['percentage']);
        fputcsv($output, $stat_row);
    }
}

fclose($output);
exit();

// Helper functions
function validateDate($date, $format = 'Y-m-d') {
    if (empty($date)) return false;
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>