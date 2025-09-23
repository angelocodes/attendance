<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Validate lecturer session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$lecturer_id = (int)$_SESSION['user_id'];

// Handle JSON input from axios
$input = file_get_contents('php://input');
$json_data = json_decode($input, true);

if ($json_data) {
    // JSON request
    $action = $json_data['action'] ?? '';
    $_POST = array_merge($_POST, $json_data);
} else {
    // Traditional form request
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
}

// Debug: Log the received action and data
error_log("Lecturer API - Received action: '" . $action . "'");
error_log("Lecturer API - Raw input: " . $input);
error_log("Lecturer API - JSON data: " . json_encode($json_data));
error_log("Lecturer API - POST data: " . json_encode($_POST));
error_log("Lecturer API - GET data: " . json_encode($_GET));

switch ($action) {
    // Get students (optimized: only join student_enrollments when unit_id is provided)
    // Suggested index: CREATE INDEX idx_user_type ON users(user_type);
    case 'get_students':
        $unit_id = intval($_GET['unit_id'] ?? 0);
        $query = "SELECT s.first_name, s.last_name, u.face_encoding, u.user_id AS student_id FROM users u JOIN students s ON u.user_id = s.student_id";
        $params = [];
        $types = '';
        if ($unit_id) {
            $query .= " JOIN student_enrollments se ON u.user_id = se.student_id WHERE se.unit_id = ? AND u.user_type = 'student'";
            $params[] = $unit_id;
            $types = 'i';
        } else {
            $query .= " WHERE u.user_type = 'student'";
        }
        $stmt = $conn->prepare($query);
        if ($unit_id) $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $res = $stmt->get_result();
        $students = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode($students);
        break;

    // Mark attendance (requires session_id)
    // Suggested index: CREATE INDEX idx_attendance ON attendance_records(student_id, session_id);
    case 'mark_attendance':
        $student_name = filter_var($_POST['student_name'] ?? '', FILTER_SANITIZE_STRING);
        $session_id = intval($_POST['session_id'] ?? 0);
        if (!$student_name || !$session_id) {
            echo json_encode(['success' => false, 'message' => 'Missing parameters']);
            exit;
        }
        $stmt = $conn->prepare("SELECT u.user_id FROM users u JOIN students s ON u.user_id = s.student_id WHERE CONCAT(s.first_name, ' ', s.last_name) = ? AND u.user_type = 'student'");
        $stmt->bind_param("s", $student_name);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $student_id = $row['user_id'];
            $check_stmt = $conn->prepare("SELECT attendance_id FROM attendance_records WHERE student_id = ? AND session_id = ?");
            $check_stmt->bind_param("ii", $student_id, $session_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows == 0) {
                $ins_stmt = $conn->prepare("INSERT INTO attendance_records (student_id, session_id, status) VALUES (?, ?, 'Present')");
                $ins_stmt->bind_param("ii", $student_id, $session_id);
                if ($ins_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Attendance marked']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Attendance already marked']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid student']);
        }
        break;

    // Mark manual attendance (single student toggle)
    // Suggested index: same as above
    case 'mark_manual_attendance':
        $session_id = intval($_POST['session_id'] ?? 0);
        $student_id = intval($_POST['student_id'] ?? 0);
        $status = filter_var($_POST['status'] ?? '', FILTER_SANITIZE_STRING);
        if (!$session_id || !$student_id || !in_array($status, ['Present', 'Absent'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        $check_stmt = $conn->prepare("SELECT attendance_id FROM attendance_records WHERE student_id = ? AND session_id = ?");
        $check_stmt->bind_param("ii", $student_id, $session_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            $update_stmt = $conn->prepare("UPDATE attendance_records SET status = ? WHERE student_id = ? AND session_id = ?");
            $update_stmt->bind_param("sii", $status, $student_id, $session_id);
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
            }
        } else {
            $ins_stmt = $conn->prepare("INSERT INTO attendance_records (student_id, session_id, status) VALUES (?, ?, ?)");
            $ins_stmt->bind_param("iis", $student_id, $session_id, $status);
            if ($ins_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance marked']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
            }
        }
        break;

    // Schedule session
    // Suggested index: CREATE INDEX idx_lecturer_assignments ON lecturer_assignments(lecturer_id, assignment_id);
    case 'schedule_session':
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $session_date = $_POST['session_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $venue = filter_var($_POST['venue'] ?? '', FILTER_SANITIZE_STRING);

        // Debug: Log received parameters and validation
        error_log("Schedule session - assignment_id: $assignment_id, session_date: $session_date, start_time: $start_time, end_time: $end_time, venue: $venue");
        error_log("Schedule session - strtotime(session_date): " . strtotime($session_date) . ", time(): " . time());
        error_log("Schedule session - strtotime(start_time): " . strtotime($start_time) . ", strtotime(end_time): " . strtotime($end_time));

        if (!$assignment_id || !$session_date || !$start_time || !$end_time || !$venue) {
            error_log("Schedule session - Missing required fields");
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }

        // Allow sessions for today and future dates
        $session_date_timestamp = strtotime($session_date . ' 00:00:00');
        $today_timestamp = strtotime(date('Y-m-d') . ' 00:00:00');

        if ($session_date_timestamp < $today_timestamp) {
            error_log("Schedule session - Date is in the past: $session_date");
            echo json_encode(['success' => false, 'message' => 'Session date cannot be in the past']);
            exit;
        }

        if (strtotime($start_time) >= strtotime($end_time)) {
            error_log("Schedule session - Start time is after end time: $start_time >= $end_time");
            echo json_encode(['success' => false, 'message' => 'Start time must be before end time']);
            exit;
        }
        $stmt = $conn->prepare("SELECT unit_id FROM lecturer_assignments WHERE assignment_id = ? AND lecturer_id = ?");
        $stmt->bind_param("ii", $assignment_id, $lecturer_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $unit_id = $row['unit_id'];
            $ins_stmt = $conn->prepare("INSERT INTO class_sessions (unit_id, lecturer_id, session_date, start_time, end_time, venue) VALUES (?, ?, ?, ?, ?, ?)");
            $ins_stmt->bind_param("iissss", $unit_id, $lecturer_id, $session_date, $start_time, $end_time, $venue);
            if ($ins_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Session scheduled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to schedule']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid assignment']);
        }
        break;

    // Export attendance
    // Suggested index: CREATE INDEX idx_session_lecturer ON class_sessions(lecturer_id, session_date);
    case 'export_attendance':
        $from_date = $_GET['from_date'] ?? null;
        $to_date = $_GET['to_date'] ?? null;
        $where_clause = '';
        $params = [$lecturer_id];
        $types = 'i';
        if ($from_date && $to_date) {
            $where_clause = ' AND cs.session_date BETWEEN ? AND ?';
            $params[] = $from_date;
            $params[] = $to_date;
            $types .= 'ss';
        }
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Student Name', 'Unit', 'Status', 'Date']);
        $query = "SELECT CONCAT(s.first_name, ' ', s.last_name) as student_name, cu.unit_name, ar.status, cs.session_date
                  FROM attendance_records ar
                  JOIN users u ON ar.student_id = u.user_id
                  JOIN students s ON u.user_id = s.student_id
                  JOIN class_sessions cs ON ar.session_id = cs.session_id
                  JOIN course_units cu ON cs.unit_id = cu.unit_id
                  WHERE cs.lecturer_id = ? $where_clause";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            fclose($output);
            http_response_code(500);
            exit('Database error');
        }
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [$row['student_name'], $row['unit_name'], $row['status'], $row['session_date']]);
        }
        fclose($output);
        exit;

    // Get attendance history (includes student_id for checkbox mapping)
    case 'get_attendance_history':
        $session_id = intval($_GET['session_id'] ?? 0);
        $from_date = $_GET['from_date'] ?? null;
        $to_date = $_GET['to_date'] ?? null;
        $params = [$lecturer_id];
        $types = 'i';
        $where_clause = '';
        if ($session_id) {
            $where_clause = ' AND cs.session_id = ?';
            $params[] = $session_id;
            $types .= 'i';
        } elseif ($from_date && $to_date) {
            $where_clause = ' AND cs.session_date BETWEEN ? AND ?';
            $params[] = $from_date;
            $params[] = $to_date;
            $types .= 'ss';
        }
        $query = "SELECT u.user_id AS student_id, CONCAT(s.first_name, ' ', s.last_name) as student_name, cu.unit_name, ar.status, cs.session_date, cs.session_id
                  FROM attendance_records ar
                  JOIN users u ON ar.student_id = u.user_id
                  JOIN students s ON u.user_id = s.student_id
                  JOIN class_sessions cs ON ar.session_id = cs.session_id
                  JOIN course_units cu ON cs.unit_id = cu.unit_id
                  WHERE cs.lecturer_id = ? $where_clause ORDER BY cs.session_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $res = $stmt->get_result();
        $history = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode($history);
        break;

    // Get notifications
    // Suggested index: CREATE INDEX idx_notifications ON notifications(user_id, user_type, is_read);
    case 'get_notifications':
        $stmt = $conn->prepare("SELECT notification_id, message, created_at, is_read FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND user_type = 'lecturer' ORDER BY created_at DESC LIMIT 20");
        $stmt->bind_param("i", $lecturer_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $res = $stmt->get_result();
        $notifications = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode($notifications);
        break;

    // Mark notification as read
    case 'mark_notification_read':
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if (!$notification_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND (user_id = ? OR user_id IS NULL)");
        $stmt->bind_param("ii", $notification_id, $lecturer_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
        }
        break;

    // Get scheduled sessions (grouped by unit)
    case 'get_scheduled_sessions':
        // Debug: Log query parameters
        error_log("Lecturer API - Querying sessions for lecturer_id: $lecturer_id");

        $stmt = $conn->prepare("SELECT cs.session_id, cs.session_date, cs.start_time, cs.end_time, cs.venue, cu.unit_name, cu.unit_id
                                FROM class_sessions cs JOIN course_units cu ON cs.unit_id = cu.unit_id
                                WHERE cs.lecturer_id = ? AND cs.session_date >= CURDATE() ORDER BY cu.unit_name, cs.session_date");
        $stmt->bind_param("i", $lecturer_id);

        if (!$stmt->execute()) {
            error_log("Lecturer API - Query execution failed: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        $res = $stmt->get_result();
        $sessions = [];
        $rowCount = 0;

        while ($row = $res->fetch_assoc()) {
            $unit_name = $row['unit_name'];
            if (!isset($sessions[$unit_name])) $sessions[$unit_name] = [];
            $sessions[$unit_name][] = $row;
            $rowCount++;
        }

        // Debug: Log detailed results
        error_log("Lecturer API - Query returned $rowCount rows");
        error_log("Lecturer API - Sessions found: " . json_encode($sessions));

        echo json_encode($sessions);
        break;

    // Get my units and enrolled students
    // Suggested index: CREATE INDEX idx_lecturer_units ON lecturer_assignments(lecturer_id);
    case 'get_my_units':
        $stmt = $conn->prepare("SELECT cu.unit_id, cu.unit_name, GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') AS students
                                FROM lecturer_assignments la
                                JOIN course_units cu ON la.unit_id = cu.unit_id
                                LEFT JOIN student_enrollments se ON cu.unit_id = se.unit_id
                                LEFT JOIN users u ON se.student_id = u.user_id
                                LEFT JOIN students s ON u.user_id = s.student_id
                                WHERE la.lecturer_id = ? GROUP BY cu.unit_id");
        $stmt->bind_param("i", $lecturer_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $res = $stmt->get_result();
        $units = $res->fetch_all(MYSQLI_ASSOC);
        echo json_encode($units);
        break;

    // Get profile
    case 'get_profile':
        $stmt = $conn->prepare("SELECT u.username, u.email, u.phone_number, l.staff_number, l.rank
                                FROM users u JOIN lecturers l ON u.user_id = l.lecturer_id WHERE u.user_id = ?");
        $stmt->bind_param("i", $lecturer_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $res = $stmt->get_result();
        $profile = $res->fetch_assoc();
        echo json_encode($profile ?? []);
        break;

    // Update profile (contact and password)
    case 'update_profile':
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone_number = filter_var($_POST['phone_number'] ?? '', FILTER_SANITIZE_STRING);
        $password = $_POST['password'] ?? '';
        if (!$email || !$phone_number) {
            echo json_encode(['success' => false, 'message' => 'Email and phone number are required']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET email = ?, phone_number = ?" . ($password ? ", password = ?" : "") . " WHERE user_id = ?");
        $params = [$email, $phone_number];
        $types = 'ss';
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $params[] = $hashed_password;
            $types .= 's';
        }
        $params[] = $lecturer_id;
        $types .= 'i';
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
