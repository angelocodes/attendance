<?php
include 'admin_navbar.php';
require_once '../db.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

$user_id = $_SESSION['user_id'];

// Initialize filters
$filters = [
    'report_type' => isset($_GET['report_type']) ? $_GET['report_type'] : 'all_students',
    'student_id' => isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0,
    'lecturer_id' => isset($_POST['lecturer_id']) ? (int)$_POST['lecturer_id'] : 0,
    'unit_id' => isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0,
    'academic_year_id' => isset($_POST['academic_year_id']) ? (int)$_POST['academic_year_id'] : 0,
    'semester_id' => isset($_POST['semester_id']) ? (int)$_POST['semester_id'] : 0,
    'start_date' => isset($_POST['start_date']) ? $_POST['start_date'] : '',
    'end_date' => isset($_POST['end_date']) ? $_POST['end_date'] : '',
];

// Initialize data
$report_data = [
    'title' => '',
    'records' => [],
    'summary' => [
        'Present' => 0,
        'Absent' => 0,
        'Late' => 0,
        'chart_data' => []
    ],
    'error' => null
];

// Handle CSV download before any output
if (isset($_POST['download']) && $_POST['download'] === 'csv') {
    // Fetch data for CSV
    try {
        // Fetch students, lecturers, course units, etc. (same as original)
        $students = [];
        $result = $conn->query("SELECT u.user_id, u.username, s.registration_number FROM users u JOIN students s ON u.user_id = s.student_id WHERE u.user_type = 'student' ORDER BY u.username");
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }

        // Fetch lecturers, course units, academic years, semesters (same as original)
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

        // Build query based on report type (same as original)
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

            // Send CSV headers
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="attendance_report.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Username', 'Reg/Staff No.', 'Unit Code', 'Unit Name', 'Status', 'Date']);
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['username'],
                    $row['registration_number'] ?? $row['staff_number'],
                    $row['unit_code'],
                    $row['unit_name'],
                    $row['status'],
                    date('Y-m-d H:i', strtotime($row['marked_at']))
                ]);
            }
            fclose($output);
            $stmt->close();
            exit;
        }
    } catch (Exception $e) {
        $report_data['error'] = "Error fetching data for CSV: " . $e->getMessage();
    }
}

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

// Handle PDF download
if (isset($_POST['download']) && $_POST['download'] === 'pdf') {
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('SUNATT');
    $pdf->SetTitle($report_data['title']);
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, $report_data['title'], 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 10);

    $html = '<table border="1" cellpadding="5"><tr><th>Username</th><th>Reg/Staff No.</th><th>Unit</th><th>Status</th><th>Date</th></tr>';
    foreach ($report_data['records'] as $row) {
        $html .= "<tr>
            <td>" . htmlspecialchars($row['username']) . "</td>
            <td>" . htmlspecialchars($row['registration_number'] ?? $row['staff_number']) . "</td>
            <td>" . htmlspecialchars($row['unit_code'] . ' - ' . $row['unit_name']) . "</td>
            <td>" . htmlspecialchars($row['status']) . "</td>
            <td>" . date('Y-m-d H:i', strtotime($row['marked_at'])) . "</td>
        </tr>";
    }
    $html .= '</table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('attendance_report.pdf', 'D');
    exit;
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-yellow-400 mb-2">Report Type</label>
                    <select name="report_type" onchange="this.form.submit()" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="all_students" <?= $filters['report_type'] === 'all_students' ? 'selected' : '' ?>>All Students</option>
                        <option value="per_student" <?= $filters['report_type'] === 'per_student' ? 'selected' : '' ?>>Per Student</option>
                        <option value="all_lecturers" <?= $filters['report_type'] === 'all_lecturers' ? 'selected' : '' ?>>All Lecturers</option>
                        <option value="per_lecturer" <?= $filters['report_type'] === 'per_lecturer' ? 'selected' : '' ?>>Per Lecturer</option>
                    </select>
                </div>
                <?php if ($filters['report_type'] === 'per_student'): ?>
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
                <?php if ($filters['report_type'] === 'per_lecturer'): ?>
                <div>
                    <label class="block text-yellow-400 mb-2">Lecturer</label>
                    <select name="lecturer_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">Select Lecturer</option>
                        <?php foreach ($lecturers as $lecturer): ?>
                        <option value="<?= $lecturer['user_id'] ?>" <?= $filters['lecturer_id'] === $lecturer['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lecturer['username'] . ' (' . $lecturer['staff_number'] . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
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
                    <select name="academic_year_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Years</option>
                        <?php foreach ($academic_years as $year): ?>
                        <option value="<?= $year['academic_year_id'] ?>" <?= $filters['academic_year_id'] === $year['academic_year_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($year['year_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-yellow-400 mb-2">Semester</label>
                    <select name="semester_id" class="w-full bg-gray-700 text-white p-2 rounded">
                        <option value="0">All Semesters</option>
                        <?php foreach ($semesters as $semester): ?>
                        <option value="<?= $semester['semester_id'] ?>" <?= $filters['semester_id'] === $semester['semester_id'] ? 'selected' : '' ?>>
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
            </div>
            <div class="mt-4 flex space-x-4">
                <button type="submit" class="px-4 py-2 bg-yellow-400 text-gray-900 rounded hover:bg-yellow-300 font-semibold">Generate Report</button>
                <button type="submit" name="download" value="pdf" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-500 font-semibold">Download PDF</button>
                <button type="submit" name="download" value="csv" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-500 font-semibold">Download CSV</button>
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

        <!-- Report Table -->
        <div class="bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold text-yellow-400 mb-4"><?= htmlspecialchars($report_data['title']) ?></h2>
            <?php if (empty($report_data['records'])): ?>
            <p class="text-gray-400">No records found.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-700">
                            <th class="p-3">Username</th>
                            <th class="p-3">Reg/Staff No.</th>
                            <th class="p-3">Unit</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['records'] as $row): ?>
                        <tr class="border-b border-gray-600">
                            <td class="p-3"><?= htmlspecialchars($row['username']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($row['registration_number'] ?? $row['staff_number']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($row['unit_code'] . ' - ' . $row['unit_name']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($row['status']) ?></td>
                            <td class="p-3"><?= date('Y-m-d H:i', strtotime($row['marked_at'])) ?></td>
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

        // Bar Chart
        new Chart(document.getElementById('barChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($report_data['chart_data']['labels']) ?>,
                datasets: [{
                    label: 'Attendance Status',
                    data: <?= json_encode($report_data['chart_data']['values']) ?>,
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
                labels: <?= json_encode($report_data['chart_data']['labels']) ?>,
                datasets: [{
                    data: <?= json_encode($report_data['chart_data']['values']) ?>,
                    backgroundColor: ['#34d399', '#ef4444', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Line Chart (simplified trend)
        new Chart(document.getElementById('lineChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    label: 'Attendance Trend',
                    data: <?= json_encode($report_data['chart_data']['values']) ?>,
                    borderColor: '#34d399',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
</body>
</html>