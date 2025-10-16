<?php
include 'admin_navbar.php';
require_once '../db.php';


// PDF generation - optional, will be disabled if DomPDF is not available
$tcpdf_available = false;
require_once '../vendor/autoload.php';
if (class_exists('Dompdf\Dompdf')) {
    $tcpdf_available = true;
}

$user_id = $_SESSION['user_id'];

// Initialize filters
$filters = [
    'report_type' => isset($_GET['report_type']) ? $_GET['report_type'] : 'summary',
    'student_id' => isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0,
    'lecturer_id' => isset($_POST['lecturer_id']) ? (int)$_POST['lecturer_id'] : 0,
    'unit_id' => isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0,
    'school_id' => isset($_POST['school_id']) ? (int)$_POST['school_id'] : 0,
    'department_id' => isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0,
    'course_id' => isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0,
    'academic_year' => isset($_POST['academic_year']) ? $_POST['academic_year'] : '',
    'semester' => isset($_POST['semester']) ? (int)$_POST['semester'] : 0,
    'start_date' => isset($_POST['start_date']) ? $_POST['start_date'] : '',
    'end_date' => isset($_POST['end_date']) ? $_POST['end_date'] : '',
    'min_percentage' => isset($_POST['min_percentage']) ? (float)$_POST['min_percentage'] : 0,
];

// Initialize report data
$report_data = [
    'title' => 'Attendance Summary Report',
    'type' => $filters['report_type'],
    'records' => [],
    'summary' => [
        'total_sessions' => 0,
        'total_present' => 0,
        'total_absent' => 0,
        'total_late' => 0,
        'overall_percentage' => 0
    ],
    'charts' => [],
    'error' => null
];

// Functions for report generation
function generateReportData($conn, &$filters, &$report_data) {
    try {
        switch ($filters['report_type']) {
            case 'summary':
                generateSummaryReport($conn, $filters, $report_data);
                break;
            case 'student_detail':
                generateStudentDetailReport($conn, $filters, $report_data);
                break;
            case 'unit_report':
                generateUnitReport($conn, $filters, $report_data);
                break;
            case 'lecturer_report':
                generateLecturerReport($conn, $filters, $report_data);
                break;
            default:
                generateSummaryReport($conn, $filters, $report_data);
        }
    } catch (Exception $e) {
        $report_data['error'] = "Error generating report: " . $e->getMessage();
    }
}

function generateSummaryReport($conn, $filters, &$report_data) {
    $report_data['title'] = 'Student Attendance Summary Report';

    $query = "
        SELECT
            CONCAT(st.first_name, ' ', st.last_name) as student_name,
            st.registration_number,
            COUNT(ar.attendance_id) as total_sessions,
            SUM(CASE WHEN ar.status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN ar.status = 'Late' THEN 1 ELSE 0 END) as late_count,
            COALESCE(ROUND(
                CASE WHEN COUNT(ar.attendance_id) > 0 THEN
                    (SUM(CASE WHEN ar.status = 'Present' THEN 1 ELSE 0 END) +
                     SUM(CASE WHEN ar.status = 'Late' THEN 0.5 ELSE 0 END)) /
                    COUNT(ar.attendance_id) * 100
                ELSE 0 END, 1
            ), 0) as attendance_percentage
        FROM students st
        LEFT JOIN attendance_records ar ON st.student_id = ar.student_id
        LEFT JOIN class_sessions cs ON ar.session_id = cs.session_id
        WHERE 1=1
    ";

    $conditions = [];
    $params = [];
    $param_types = "";

    if ($filters['school_id']) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM departments d
            JOIN courses c ON d.department_id = c.department_id
            WHERE c.course_id = st.course_id AND d.school_id = ?
        )";
        $params[] = $filters['school_id'];
        $param_types .= "i";
    }

    if ($filters['department_id']) {
        $conditions[] = "EXISTS (
            SELECT 1 FROM departments d
            JOIN courses c ON d.department_id = c.department_id
            WHERE c.course_id = st.course_id AND d.department_id = ?
        )";
        $params[] = $filters['department_id'];
        $param_types .= "i";
    }

    if ($filters['course_id']) {
        $conditions[] = "st.course_id = ?";
        $params[] = $filters['course_id'];
        $param_types .= "i";
    }

    if ($filters['academic_year']) {
        $conditions[] = "st.academic_year_id = (
            SELECT academic_year_id FROM academic_years
            WHERE CONCAT(start_year, '/', end_year) = ?
        )";
        $params[] = $filters['academic_year'];
        $param_types .= "s";
    }

    if ($filters['start_date'] && $filters['end_date']) {
        $conditions[] = "ar.marked_at BETWEEN ? AND ?";
        $params[] = [$filters['start_date'] . ' 00:00:00', $filters['end_date'] . ' 23:59:59'];
        $param_types .= "ss";
    }

    if ($conditions) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " GROUP BY st.student_id, st.registration_number, st.first_name, st.last_name";

    if ($filters['min_percentage'] > 0) {
        $query .= " HAVING attendance_percentage >= ?";
        $params[] = $filters['min_percentage'];
        $param_types .= "d";
    }

    $query .= " ORDER BY attendance_percentage DESC, student_name";

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $report_data['records'][] = $row;
        $report_data['summary']['total_sessions'] += $row['total_sessions'];
        $report_data['summary']['total_present'] += $row['present_count'];
        $report_data['summary']['total_absent'] += $row['absent_count'];
        $report_data['summary']['total_late'] += $row['late_count'];
    }

    // Calculate overall percentage
    $total_attendance = $report_data['summary']['total_present'] + $report_data['summary']['total_late'] * 0.5;
    $total_possible = $report_data['summary']['total_sessions'];
    $report_data['summary']['overall_percentage'] = $total_possible > 0 ?
        round(($total_attendance / $total_possible) * 100, 1) : 0;

    // Prepare chart data
    $report_data['charts'] = [
        'attendance_distribution' => [
            'labels' => ['Present', 'Absent', 'Late'],
            'values' => [
                $report_data['summary']['total_present'],
                $report_data['summary']['total_absent'],
                $report_data['summary']['total_late']
            ]
        ],
        'percentage_ranges' => calculatePercentageRanges($report_data['records'])
    ];
}

function calculatePercentageRanges($records) {
    $ranges = [
        '90-100%' => 0,
        '80-89%' => 0,
        '70-79%' => 0,
        '60-69%' => 0,
        'Below 60%' => 0
    ];

    foreach ($records as $record) {
        $percentage = $record['attendance_percentage'];
        if ($percentage >= 90) $ranges['90-100%']++;
        elseif ($percentage >= 80) $ranges['80-89%']++;
        elseif ($percentage >= 70) $ranges['70-79%']++;
        elseif ($percentage >= 60) $ranges['60-69%']++;
        else $ranges['Below 60%']++;
    }

    return [
        'labels' => array_keys($ranges),
        'values' => array_values($ranges)
    ];
}

function generateStudentDetailReport($conn, $filters, &$report_data) {
    if (!$filters['student_id']) {
        $report_data['error'] = "Please select a student for detailed report";
        return;
    }

    $student_query = $conn->prepare("
        SELECT CONCAT(s.first_name, ' ', s.last_name) as student_name, st.registration_number
        FROM students st
        JOIN users s ON st.student_id = s.user_id
        WHERE st.student_id = ?
    ");
    $student_query->bind_param("i", $filters['student_id']);
    $student_query->execute();
    $student = $student_query->get_result()->fetch_assoc();

    $report_data['title'] = "Detailed Attendance Report - " . $student['student_name'] . " (" . $student['registration_number'] . ")";

    $query = "
        SELECT
            cu.unit_code,
            cu.unit_name,
            CONCAT(l.first_name, ' ', l.last_name) as lecturer_name,
            cs.session_date,
            cs.start_time,
            cs.end_time,
            ar.status,
            ar.marked_at
        FROM attendance_records ar
        JOIN class_sessions cs ON ar.session_id = cs.session_id
        JOIN course_units cu ON cs.unit_id = cu.unit_id
        LEFT JOIN users l ON cs.lecturer_id = l.user_id
        WHERE ar.student_id = ?
    ";

    $conditions = [];
    $params = [$filters['student_id']];
    $param_types = "i";

    if ($filters['unit_id']) {
        $conditions[] = "cu.unit_id = ?";
        $params[] = $filters['unit_id'];
        $param_types .= "i";
    }

    if ($filters['start_date'] && $filters['end_date']) {
        $conditions[] = "ar.marked_at BETWEEN ? AND ?";
        $params[] = [$filters['start_date'] . ' 00:00:00', $filters['end_date'] . ' 23:59:59'];
        $param_types .= "ss";
    }

    if ($conditions) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY ar.marked_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $report_data['records'][] = $row;
        if ($row['status'] == 'Present') $report_data['summary']['total_present']++;
        elseif ($row['status'] == 'Absent') $report_data['summary']['total_absent']++;
        elseif ($row['status'] == 'Late') $report_data['summary']['total_late']++;
    }

    $report_data['summary']['total_sessions'] = count($report_data['records']);
}

function generateUnitReport($conn, $filters, &$report_data) {
    $report_data['title'] = 'Unit-wise Attendance Report';

    $query = "
        SELECT
            cu.unit_code,
            cu.unit_name,
            COUNT(DISTINCT cs.session_id) as total_sessions,
            COUNT(ar.attendance_id) as total_records,
            SUM(CASE WHEN ar.status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN ar.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN ar.status = 'Late' THEN 1 ELSE 0 END) as late_count,
            ROUND(AVG(
                CASE
                    WHEN ar.status = 'Present' THEN 100
                    WHEN ar.status = 'Late' THEN 75
                    ELSE 0
                END
            ), 1) as avg_attendance
        FROM course_units cu
        LEFT JOIN class_sessions cs ON cu.unit_id = cs.unit_id
        LEFT JOIN attendance_records ar ON cs.session_id = ar.session_id
        WHERE 1=1
    ";

    $conditions = [];
    $params = [];
    $param_types = "";

    if ($filters['unit_id']) {
        $conditions[] = "cu.unit_id = ?";
        $params[] = $filters['unit_id'];
        $param_types .= "i";
    }

    if ($filters['start_date'] && $filters['end_date']) {
        $conditions[] = "cs.session_date BETWEEN ? AND ?";
        $params[] = [$filters['start_date'], $filters['end_date']];
        $param_types .= "ss";
    }

    if ($conditions) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " GROUP BY cu.unit_id, cu.unit_code, cu.unit_name ORDER BY avg_attendance DESC";

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $report_data['records'][] = $row;
    }
}

function generateLecturerReport($conn, $filters, &$report_data) {
    $report_data['title'] = 'Lecturer Performance Report';

    $query = "
        SELECT
            CONCAT(l.first_name, ' ', l.last_name) as lecturer_name,
            lec.staff_number,
            COUNT(DISTINCT cs.session_id) as sessions_conducted,
            COUNT(ar.attendance_id) as total_attendance_records,
            ROUND(AVG(
                CASE
                    WHEN ar.status = 'Present' THEN 100
                    WHEN ar.status = 'Late' THEN 75
                    ELSE 0
                END
            ), 1) as avg_class_attendance,
            COUNT(DISTINCT cu.unit_id) as units_taught
        FROM lecturers lec
        JOIN users l ON lec.lecturer_id = l.user_id
        LEFT JOIN class_sessions cs ON lec.lecturer_id = cs.lecturer_id
        LEFT JOIN attendance_records ar ON cs.session_id = ar.session_id
        LEFT JOIN course_units cu ON cs.unit_id = cu.unit_id
        WHERE 1=1
    ";

    $conditions = [];
    $params = [];
    $param_types = "";

    if ($filters['lecturer_id']) {
        $conditions[] = "lec.lecturer_id = ?";
        $params[] = $filters['lecturer_id'];
        $param_types .= "i";
    }

    if ($filters['start_date'] && $filters['end_date']) {
        $conditions[] = "cs.session_date BETWEEN ? AND ?";
        $params[] = [$filters['start_date'], $filters['end_date']];
        $param_types .= "ss";
    }

    if ($conditions) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " GROUP BY lec.lecturer_id, lec.staff_number, l.first_name, l.last_name ORDER BY avg_class_attendance DESC";

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $report_data['records'][] = $row;
    }
}

// Handle CSV download before any output
if (isset($_POST['download']) && $_POST['download'] === 'csv') {
    // Send CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');

    // Generate report data first
    generateReportData($conn, $filters, $report_data);

    $output = fopen('php://output', 'w');

    // CSV headers based on report type
    if ($filters['report_type'] === 'summary') {
        fputcsv($output, ['Student Name', 'Registration No.', 'Total Sessions', 'Present', 'Absent', 'Late', 'Attendance %']);
        foreach ($report_data['records'] as $row) {
            fputcsv($output, [
                $row['student_name'],
                $row['registration_number'],
                $row['total_sessions'],
                $row['present_count'],
                $row['absent_count'],
                $row['late_count'],
                number_format($row['attendance_percentage'], 1) . '%'
            ]);
        }
    } else {
        fputcsv($output, ['Student Name', 'Registration No.', 'Unit Code', 'Unit Name', 'Status', 'Date', 'Lecturer']);
        foreach ($report_data['records'] as $row) {
            fputcsv($output, [
                $row['student_name'] ?? $row['username'],
                $row['registration_number'] ?? $row['staff_number'],
                $row['unit_code'],
                $row['unit_name'],
                $row['status'],
                date('Y-m-d H:i', strtotime($row['marked_at'])),
                $row['lecturer_name'] ?? ''
            ]);
        }
    }
    fclose($output);
    exit;
}

// Generate report data
generateReportData($conn, $filters, $report_data);

// Rest of the original code for fetching data and rendering HTML
try {
    // Fetch students, lecturers, course units, academic years, semesters (same as original)
    $students = [];
    $result = $conn->query("SELECT u.user_id, u.username, s.registration_number FROM users u JOIN students s ON u.user_id = s.student_id WHERE u.user_type = 'student' ORDER BY u.username");
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $lecturers = [];
    $result = $conn->query("SELECT u.user_id, l.staff_number FROM users u JOIN lecturers l ON u.user_id = l.lecturer_id WHERE u.user_type = 'lecturer' ORDER BY u.username");
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = $row;
    }

    $course_units = [];
    $result = $conn->query("SELECT unit_id, unit_code, unit_name FROM course_units ORDER BY unit_name");
    while ($row = $result->fetch_assoc()) {
        $course_units[] = $row;
    }

    $academic_years = [];
    $result = $conn->query("SELECT academic_year_id, CONCAT(start_year, '/', end_year) AS year_name FROM academic_years ORDER BY start_year DESC");
    while ($row = $result->fetch_assoc()) {
        $academic_years[] = $row;
    }

    $semesters = [];
    $result = $conn->query("SELECT semester_id, semester_name FROM semesters ORDER BY semester_name");
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
    }

    // Build and execute query (same as original)
    $query = "";
    $params = [];
    $param_types = "";

    switch ($filters['report_type']) {
        case 'per_student':
            if ($filters['student_id']) {
                $report_data['title'] = "Attendance Report for Student ID: {$filters['student_id']}";
                $query = "SELECT u.username, s.registration_number, cu.unit_code, cu.unit_name, ar.status, ar.marked_at
                          FROM attendance_records ar
                          JOIN users u ON ar.student_id = u.user_id
                          JOIN students s ON ar.student_id = s.student_id
                          JOIN class_sessions cs ON ar.session_id = cs.session_id
                          JOIN course_units cu ON cs.unit_id = cu.unit_id
                          WHERE ar.student_id = ?";
                $params[] = $filters['student_id'];
                $param_types .= "i";
            }
            break;

        case 'all_students':
            $report_data['title'] = "Attendance Report for All Students";
            $query = "SELECT u.username, s.registration_number, cu.unit_code, cu.unit_name, ar.status, ar.marked_at
                      FROM attendance_records ar
                      JOIN users u ON ar.student_id = u.user_id
                      JOIN students s ON ar.student_id = s.student_id
                      JOIN class_sessions cs ON ar.session_id = cs.session_id
                      JOIN course_units cu ON cs.unit_id = cu.unit_id";
            break;

        case 'per_lecturer':
            if ($filters['lecturer_id']) {
                $report_data['title'] = "Attendance Report for Lecturer ID: {$filters['lecturer_id']}";
                $query = "SELECT u.username, l.staff_number, cu.unit_code, cu.unit_name, ar.status, ar.marked_at
                          FROM attendance_records ar
                          JOIN class_sessions cs ON ar.session_id = cs.session_id
                          JOIN users u ON cs.lecturer_id = u.user_id
                          JOIN lecturers l ON u.user_id = l.lecturer_id
                          JOIN course_units cu ON cs.unit_id = cu.unit_id
                          WHERE cs.lecturer_id = ?";
                $params[] = $filters['lecturer_id'];
                $param_types .= "i";
            }
            break;

        case 'all_lecturers':
            $report_data['title'] = "Attendance Report for All Lecturers";
            $query = "SELECT u.username, l.staff_number, cu.unit_code, cu.unit_name, ar.status, ar.marked_at
                      FROM attendance_records ar
                      JOIN class_sessions cs ON ar.session_id = cs.session_id
                      JOIN users u ON cs.lecturer_id = u.user_id
                      JOIN lecturers l ON u.user_id = l.lecturer_id
                      JOIN course_units cu ON cs.unit_id = cu.unit_id";
            break;
    }

    // Apply filters
    if ($query) {
        $conditions = [];
        if ($filters['unit_id']) {
            $conditions[] = "cu.unit_id = ?";
            $params[] = $filters['unit_id'];
            $param_types .= "i";
        }
        if ($filters['academic_year_id']) {
            $conditions[] = "se.academic_year = (SELECT CONCAT(start_year, '/', end_year) FROM academic_years WHERE academic_year_id = ?)";
            $params[] = $filters['academic_year_id'];
            $param_types .= "i";
        }
        if ($filters['semester_id']) {
            $conditions[] = "se.semester_id = ?";
            $params[] = $filters['semester_id'];
            $param_types .= "i";
        }
        if ($filters['start_date'] && $filters['end_date']) {
            $conditions[] = "ar.marked_at BETWEEN ? AND ?";
            $params[] = [$filters['start_date'], $filters['end_date'] . ' 23:59:59'];
            $param_types .= "[]";
        }

        if ($conditions) {
            $query .= " AND " . implode(" AND ", $conditions);
        }
        $query .= " ORDER BY ar.marked_at DESC";

        // Execute query
        $stmt = $conn->prepare($query);
        if ($params) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $report_data['records'][] = $row;
            $report_data['summary'][$row['status']]++;
        }
        $stmt->close();

        // Prepare chart data
        $report_data['chart_data'] = [
            'labels' => ['Present', 'Absent', 'Late'],
            'values' => [
                $report_data['summary']['Present'],
                $report_data['summary']['Absent'],
                $report_data['summary']['Late']
            ]
        ];
    }
} catch (Exception $e) {
    $report_data['error'] = "Error fetching data: " . $e->getMessage();
}


?>

<!-- Rest of the HTML and JavaScript remains unchanged -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <main class="container mx-auto p-8">
        <h1 class="text-3xl font-bold text-yellow-400 mb-6">Attendance Reports</h1>

        <?php if ($report_data['error']): ?>
        <div class="mb-6 bg-red-700 p-4 rounded">
            <p class="text-lg"><?= htmlspecialchars($report_data['error']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="POST" class="bg-gray-800 p-6 rounded-lg shadow mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-yellow-400 mb-2">Report Type</label>
                    <select name="report_type" onchange="this.form.submit()" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="summary" <?= $filters['report_type'] === 'summary' ? 'selected' : '' ?>>Student Summary</option>
                        <option value="student_detail" <?= $filters['report_type'] === 'student_detail' ? 'selected' : '' ?>>Student Details</option>
                        <option value="unit_report" <?= $filters['report_type'] === 'unit_report' ? 'selected' : '' ?>>Unit Report</option>
                        <option value="lecturer_report" <?= $filters['report_type'] === 'lecturer_report' ? 'selected' : '' ?>>Lecturer Report</option>
                    </select>
                </div>

                <?php if ($filters['report_type'] === 'student_detail'): ?>
                <div>
                    <label class="block text-yellow-400 mb-2">Student</label>
                    <select name="student_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">Select Student</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?= $student['user_id'] ?>" <?= $filters['student_id'] === $student['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['username'] . ' (' . $student['registration_number'] . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($filters['report_type'] === 'lecturer_report'): ?>
                <div>
                    <label class="block text-yellow-400 mb-2">Lecturer</label>
                    <select name="lecturer_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Lecturers</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                        <option value="<?= $lecturer['user_id'] ?>" <?= $filters['lecturer_id'] === $lecturer['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lecturer['username'] . ' (' . $lecturer['staff_number'] . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-yellow-400 mb-2">School</label>
                    <select name="school_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Schools</option>
                        <?php
                        $schools_result = $conn->query("SELECT * FROM schools ORDER BY school_name");
                        while ($school = $schools_result->fetch_assoc()): ?>
                        <option value="<?= $school['school_id'] ?>" <?= $filters['school_id'] === $school['school_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($school['school_name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-yellow-400 mb-2">Department</label>
                    <select name="department_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Departments</option>
                        <?php
                        $depts_result = $conn->query("SELECT * FROM departments ORDER BY department_name");
                        while ($dept = $depts_result->fetch_assoc()): ?>
                        <option value="<?= $dept['department_id'] ?>" <?= $filters['department_id'] === $dept['department_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['department_name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-yellow-400 mb-2">Course</label>
                    <select name="course_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Courses</option>
                        <?php
                        $courses_result = $conn->query("SELECT * FROM courses ORDER BY course_name");
                        while ($course = $courses_result->fetch_assoc()): ?>
                        <option value="<?= $course['course_id'] ?>" <?= $filters['course_id'] === $course['course_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-yellow-400 mb-2">Course Unit</label>
                    <select name="unit_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Units</option>
                        <?php foreach ($course_units as $unit): ?>
                        <option value="<?= $unit['unit_id'] ?>" <?= $filters['unit_id'] === $unit['unit_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($unit['unit_code'] . ' - ' . $unit['unit_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-yellow-400 mb-2">Academic Year</label>
                    <select name="academic_year" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="">All Years</option>
                        <?php foreach ($academic_years as $year): ?>
                        <option value="<?= $year['year_name'] ?>" <?= $filters['academic_year'] === $year['year_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['year_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-yellow-400 mb-2">Semester</label>
                    <select name="semester" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Semesters</option>
                        <?php foreach ($semesters as $semester): ?>
                        <option value="<?= $semester['semester_id'] ?>" <?= $filters['semester'] === $semester['semester_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($semester['semester_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-yellow-400 mb-2">Date Range</label>
                    <input type="text" name="date_range" id="date_range" class="w-full bg-gray-700 text-white p-2 rounded" value="<?= htmlspecialchars($filters['start_date'] . ' - ' . $filters['end_date']) ?>">
                    <input type="hidden" name="start_date" id="start_date" value="<?= htmlspecialchars($filters['start_date']) ?>">
                    <input type="hidden" name="end_date" id="end_date" value="<?= htmlspecialchars($filters['end_date']) ?>">
                </div>

                <?php if ($filters['report_type'] === 'summary'): ?>
                <div>
                    <label class="block text-yellow-400 mb-2">Min Attendance %</label>
                    <input type="number" name="min_percentage" min="0" max="100" step="5" value="<?= $filters['min_percentage'] ?>" class="w-full bg-gray-700 text-white p-2 rounded" placeholder="e.g., 75">
                </div>
                <?php endif; ?>
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="px-6 py-2 bg-yellow-400 text-gray-900 rounded hover:bg-yellow-300 font-semibold">Generate Report</button>
                <button type="button" onclick="downloadPDF()" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-500 font-semibold">Download PDF</button>
                <button type="submit" name="download" value="csv" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-500 font-semibold">Download CSV</button>
            </div>
        </form>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Attendance Status (Bar)</h2>
                <canvas id="barChart"></canvas>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Attendance Distribution (Pie)</h2>
                <canvas id="pieChart"></canvas>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-6 lg:col-span-2">
                <h2 class="text-xl font-semibold text-yellow-400 mb-4">Attendance Trend (Line)</h2>
                <canvas id="lineChart"></canvas>
            </div>
        </div>

        <!-- Summary Statistics (for summary report) -->
        <?php if ($filters['report_type'] === 'summary' && !empty($report_data['records'])): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-gray-800 rounded-lg shadow p-6 text-center">
                <h3 class="text-lg font-semibold text-yellow-400 mb-2">Total Sessions</h3>
                <p class="text-3xl font-bold text-white"><?= number_format($report_data['summary']['total_sessions']) ?></p>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-6 text-center">
                <h3 class="text-lg font-semibold text-green-400 mb-2">Total Present</h3>
                <p class="text-3xl font-bold text-white"><?= number_format($report_data['summary']['total_present']) ?></p>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-6 text-center">
                <h3 class="text-lg font-semibold text-red-400 mb-2">Total Absent</h3>
                <p class="text-3xl font-bold text-white"><?= number_format($report_data['summary']['total_absent']) ?></p>
            </div>
            <div class="bg-gray-800 rounded-lg shadow p-6 text-center">
                <h3 class="text-lg font-semibold text-yellow-400 mb-2">Overall Attendance</h3>
                <p class="text-3xl font-bold text-white"><?= number_format($report_data['summary']['overall_percentage'], 1) ?>%</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Report Table -->
        <div class="bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-yellow-400 mb-4"><?= htmlspecialchars($report_data['title']) ?></h2>
            <?php if (empty($report_data['records'])): ?>
            <p class="text-gray-400">No records found. Please adjust your filters and try again.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-700">
                            <?php if ($filters['report_type'] === 'summary'): ?>
                                <th class="p-3">Student Name</th>
                                <th class="p-3">Registration No.</th>
                                <th class="p-3">Total Sessions</th>
                                <th class="p-3">Present</th>
                                <th class="p-3">Absent</th>
                                <th class="p-3">Late</th>
                                <th class="p-3">Attendance %</th>
                            <?php elseif ($filters['report_type'] === 'student_detail'): ?>
                                <th class="p-3">Unit Code</th>
                                <th class="p-3">Unit Name</th>
                                <th class="p-3">Lecturer</th>
                                <th class="p-3">Session Date</th>
                                <th class="p-3">Time</th>
                                <th class="p-3">Status</th>
                            <?php elseif ($filters['report_type'] === 'unit_report'): ?>
                                <th class="p-3">Unit Code</th>
                                <th class="p-3">Unit Name</th>
                                <th class="p-3">Sessions</th>
                                <th class="p-3">Present</th>
                                <th class="p-3">Absent</th>
                                <th class="p-3">Late</th>
                                <th class="p-3">Avg Attendance %</th>
                            <?php elseif ($filters['report_type'] === 'lecturer_report'): ?>
                                <th class="p-3">Lecturer Name</th>
                                <th class="p-3">Staff Number</th>
                                <th class="p-3">Sessions Conducted</th>
                                <th class="p-3">Total Records</th>
                                <th class="p-3">Avg Class Attendance %</th>
                                <th class="p-3">Units Taught</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['records'] as $row): ?>
                        <tr class="border-b border-gray-600 hover:bg-gray-700">
                            <?php if ($filters['report_type'] === 'summary'): ?>
                                <td class="p-3"><?= htmlspecialchars($row['student_name'] ?? 'N/A') ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['registration_number'] ?? 'N/A') ?></td>
                                <td class="p-3"><?= $row['total_sessions'] ?? 0 ?></td>
                                <td class="p-3 text-green-400"><?= $row['present_count'] ?? 0 ?></td>
                                <td class="p-3 text-red-400"><?= $row['absent_count'] ?? 0 ?></td>
                                <td class="p-3 text-yellow-400"><?= $row['late_count'] ?? 0 ?></td>
                                <td class="p-3 font-semibold <?= ($row['attendance_percentage'] ?? 0) >= 75 ? 'text-green-400' : (($row['attendance_percentage'] ?? 0) >= 60 ? 'text-yellow-400' : 'text-red-400') ?>">
                                    <?= number_format($row['attendance_percentage'] ?? 0, 1) ?>%
                                </td>
                            <?php elseif ($filters['report_type'] === 'student_detail'): ?>
                                <td class="p-3 font-mono"><?= htmlspecialchars($row['unit_code']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['unit_name']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['lecturer_name'] ?? 'N/A') ?></td>
                                <td class="p-3"><?= date('M d, Y', strtotime($row['session_date'])) ?></td>
                                <td class="p-3"><?= date('H:i', strtotime($row['start_time'])) ?> - <?= date('H:i', strtotime($row['end_time'])) ?></td>
                                <td class="p-3">
                                    <span class="px-2 py-1 rounded text-xs font-semibold
                                        <?= $row['status'] === 'Present' ? 'bg-green-600 text-white' :
                                           ($row['status'] === 'Late' ? 'bg-yellow-600 text-white' : 'bg-red-600 text-white') ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                            <?php elseif ($filters['report_type'] === 'unit_report'): ?>
                                <td class="p-3 font-mono"><?= htmlspecialchars($row['unit_code']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($row['unit_name']) ?></td>
                                <td class="p-3"><?= $row['total_sessions'] ?></td>
                                <td class="p-3 text-green-400"><?= $row['present_count'] ?></td>
                                <td class="p-3 text-red-400"><?= $row['absent_count'] ?></td>
                                <td class="p-3 text-yellow-400"><?= $row['late_count'] ?></td>
                                <td class="p-3 font-semibold <?= $row['avg_attendance'] >= 75 ? 'text-green-400' : ($row['avg_attendance'] >= 60 ? 'text-yellow-400' : 'text-red-400') ?>">
                                    <?= number_format($row['avg_attendance'], 1) ?>%
                                </td>
                            <?php elseif ($filters['report_type'] === 'lecturer_report'): ?>
                                <td class="p-3"><?= htmlspecialchars($row['lecturer_name']) ?></td>
                                <td class="p-3 font-mono"><?= htmlspecialchars($row['staff_number']) ?></td>
                                <td class="p-3"><?= $row['sessions_conducted'] ?></td>
                                <td class="p-3"><?= $row['total_attendance_records'] ?></td>
                                <td class="p-3 font-semibold <?= $row['avg_class_attendance'] >= 75 ? 'text-green-400' : ($row['avg_class_attendance'] >= 60 ? 'text-yellow-400' : 'text-red-400') ?>">
                                    <?= number_format($row['avg_class_attendance'], 1) ?>%
                                </td>
                                <td class="p-3"><?= $row['units_taught'] ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-800 text-center py-4 mt-8">
        <p>© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
        // Initialize Flatpickr
        flatpickr("#date_range", {
            mode: "range",
            dateFormat: "Y-m-d",
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    document.getElementById('start_date').value = selectedDates[0].toISOString().split('T')[0];
                    document.getElementById('end_date').value = selectedDates[1].toISOString().split('T')[0];
                }
            }
        });

        // Initialize charts with default data
        const defaultChartData = {
            labels: ['Present', 'Absent', 'Late'],
            values: [0, 0, 0]
        };

        const chartData = <?= json_encode($report_data['charts']['attendance_distribution'] ?? $report_data['chart_data'] ?? defaultChartData) ?>;
        const percentageData = <?= json_encode($report_data['charts']['percentage_ranges'] ?? ['labels' => [], 'values' => []]) ?>;

        // Bar Chart
        new Chart(document.getElementById('barChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.labels || ['Present', 'Absent', 'Late'],
                datasets: [{
                    label: 'Attendance Count',
                    data: chartData.values || [0, 0, 0],
                    backgroundColor: ['#34d399', '#ef4444', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // Pie Chart
        new Chart(document.getElementById('pieChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: chartData.labels || ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: chartData.values || [0, 0, 0],
                    backgroundColor: ['#34d399', '#ef4444', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Line Chart - Percentage Ranges (for summary) or Trend
        const lineLabels = percentageData.labels && percentageData.labels.length > 0 ?
            percentageData.labels : ['Present', 'Absent', 'Late'];
        const lineValues = percentageData.values && percentageData.values.length > 0 ?
            percentageData.values : (chartData.values || [0, 0, 0]);

        new Chart(document.getElementById('lineChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: lineLabels,
                datasets: [{
                    label: percentageData.labels && percentageData.labels.length > 0 ? 'Students by Percentage Range' : 'Attendance Distribution',
                    data: lineValues,
                    borderColor: '#34d399',
                    backgroundColor: 'rgba(52, 211, 153, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });

        // PDF Download function
        function downloadPDF() {
            const element = document.querySelector('body'); // Or more specific: '.bg-gray-800.rounded-lg.shadow.p-6' for the table container
            const opt = {
                margin: 0.5,
                filename: 'attendance_report.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
