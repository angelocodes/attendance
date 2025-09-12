<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'lecturer_controller.php';
require_once 'config.php';

// Define NOTIFICATIONS_LIMIT if not defined
if (!defined('NOTIFICATIONS_LIMIT')) {
    define('NOTIFICATIONS_LIMIT', 10);
}

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
    header("Location: ../access_denied.php");
    exit();
}
$lecturer_id = (int)$_SESSION['user_id'];

// Initialize variables
$error = '';
$success = '';
$unit_name = 'Select a Unit';
$units = [];
$sessions = [];
$selected_assignment_id = null;

// Check GET or POST for assignment_id
if (isset($_GET['assignment_id']) && is_numeric($_GET['assignment_id'])) {
    $selected_assignment_id = (int)$_GET['assignment_id'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id'])) {
    $selected_assignment_id = (int)$_POST['assignment_id'];
}

// Database connection check
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    $error = "Database connection failed. Please try again later.";
} else {
    $controller = new LecturerController($conn);

    // Fetch theme color
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme_color'");
    if ($stmt && $stmt->execute()) {
        $stmt->bind_result($theme_color);
        $stmt->fetch();
        $stmt->close();
    } else {
        $theme_color = '#6c757d';
        error_log("Failed to fetch theme color: " . ($stmt ? $stmt->error : 'Statement preparation failed'), 3, '../logs/errors.log');
    }

    // Fetch lecturer's assigned units
    $result = $controller->getLecturerAssignments($lecturer_id);
    if ($result['success']) {
        $units = $result['assignments'];
        if (empty($units)) {
            $error = "No units assigned to you. Please contact the administrator.";
            error_log("No units found for lecturer_id: $lecturer_id", 3, '../logs/errors.log');
        }
    } else {
        $error = "Failed to retrieve units: " . $result['error'];
        error_log("Error fetching units: " . $result['error'], 3, '../logs/errors.log');
    }

    // Validate and fetch unit name if selected_assignment_id is set
    if ($selected_assignment_id) {
        $stmt = $conn->prepare("
            SELECT cu.unit_name
            FROM lecturer_assignments la 
            JOIN course_units cu ON la.unit_id = cu.unit_id
            WHERE la.assignment_id = ? AND la.lecturer_id = ?
        ");
        $stmt->bind_param("ii", $selected_assignment_id, $lecturer_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $unit_name = $row['unit_name'];
            } else {
                $error = "Invalid unit selected.";
                error_log("Invalid assignment_id: $selected_assignment_id for lecturer_id: $lecturer_id", 3, '../logs/errors.log');
            }
            $stmt->close();
        } else {
            $error = "Failed to fetch unit details.";
            error_log("Error fetching unit: " . $stmt->error, 3, '../logs/errors.log');
        }

        // Fetch sessions for selected unit
        if (!$error) {
            $result = $controller->getClassSessions($lecturer_id, $selected_assignment_id);
            if ($result['success']) {
                $sessions = $result['sessions'];
            } else {
                $error = $result['error'];
                error_log("Error fetching sessions: " . $result['error'], 3, '../logs/errors.log');
            }
        }
    }
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $session_id = isset($_POST['session_id']) && is_numeric($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    $attendance = isset($_POST['attendance']) && is_array($_POST['attendance']) ? $_POST['attendance'] : [];
    $assignment_id = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;

    if ($session_id <= 0 || $assignment_id <= 0) {
        $error = "Please select a valid session and unit.";
    } else {
        $attendance_data = [];
        foreach ($attendance as $student_id => $status) {
            if (is_numeric($student_id)) {
                $attendance_data[$student_id] = $status === 'on' ? 'Present' : 'Absent';
            }
        }

        if (empty($attendance_data)) {
            $error = "No attendance data provided.";
        } else {
            $result = $controller->saveAttendance($lecturer_id, $session_id, $assignment_id, $attendance_data);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['error'];
                error_log("Error saving attendance: " . $result['error'], 3, '../logs/errors.log');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --theme-color: <?= htmlspecialchars($theme_color ?? '#6c757d') ?>;
        }
        .bg-theme { background-color: var(--theme-color); }
        .text-theme { color: var(--theme-color); }
        .hover\:bg-theme:hover { background-color: var(--theme-color); }
        .focus\:ring-theme:focus { --tw-ring-color: var(--theme-color); }
        .tab-active { border-bottom: 2px solid var(--theme-color); color: var(--theme-color); }
        #report-modal .modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }
        #loading {
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">
    <?php include 'lecturer_navbar.php'; ?>
        
    <main class="container mx-auto p-6 flex-grow">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-semibold text-theme" role="heading" id="unit-header">Attendance for <?= htmlspecialchars($unit_name) ?></h1>
            <button id="generate-report-btn" class="bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme" disabled>
                Generate Report
            </button>
        </div>

        <div id="loading" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center">
            <svg class="animate-spin h-8 w-8 text-theme" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <?php if ($error): ?>
            <div id="error-alert" class="bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span class="text-sm"><?= htmlspecialchars($error) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div id="success-alert" class="bg-green-700 border border-green-600 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span class="text-sm"><?= htmlspecialchars($success) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <section class="bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="mb-4">
                <label for="unit_select" class="block text-sm font-semibold mb-2">Select Unit</label>
                <select id="unit_select" name="assignment_id" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme" aria-describedby="unit-select-help">
                    <option value="">Select a unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= htmlspecialchars($unit['assignment_id']) ?>" data-unit-name="<?= htmlspecialchars($unit['unit_name']) ?>" <?= $selected_assignment_id == $unit['assignment_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($unit['unit_name'] . ' (' . $unit['academic_year'] . ', Sem ' . $unit['semester'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="unit-select-help" class="text-sm text-gray-400 mt-1">Select a unit to manage its attendance.</p>
            </div>

            <div id="sessions-container" class="hidden">
                <div id="tabs-container" class="hidden mb-4 border-b border-gray-700">
                    <ul class="flex -mb-px">
                        <li class="mr-2">
                            <button id="mark-tab" class="tab inline-block p-4 text-white hover:text-theme tab-active" onclick="switchTab('mark')">Mark Attendance</button>
                        </li>
                        <li class="mr-2">
                            <button id="view-tab" class="tab inline-block p-4 text-white hover:text-theme" onclick="switchTab('view')">View Attendance</button>
                        </li>
                    </ul>
                </div>

                <div id="no-sessions-message" class="hidden text-center text-gray-400 py-2"></div>

                <div id="session-content" class="hidden">
                    <div class="mb-4">
                        <label for="session_id" class="block text-sm font-semibold mb-2">Select Session</label>
                        <select id="session_id" class="w-full px-3 py-2 bg-gray-700 border rounded focus:outline-none focus:ring-2 focus:ring-theme" aria-describedby="session-id-help">
                            <option value="">Select a session</option>
                        </select>
                        <p id="session-id-help" class="text-sm text-gray-400 mt-1">Select a scheduled session to manage attendance.</p>
                        <button id="load-students" class="mt-2 bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme" disabled>Load Students</button>
                    </div>

                    <div id="mark-attendance" class="tab-content">
                        <form id="attendance-form" method="POST" action="">
                            <input type="hidden" name="session_id" id="session_id_form" value="">
                            <input type="hidden" name="action" value="save_attendance">
                            <input type="hidden" name="assignment_id" id="attendance_assignment_id" value="<?= htmlspecialchars($selected_assignment_id ?? '') ?>">
                            <div id="mark-students-container" class="overflow-x-auto hidden">
                                <table class="min-w-full divide-y divide-gray-700" aria-label="Students Attendance Table">
                                    <thead class="bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reg No</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Username</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Email</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Present</th>
                                        </tr>
                                    </thead>
                                    <tbody id="mark-students" class="divide-y divide-gray-600"></tbody>
                                </table>
                                <div class="mt-4">
                                    <button type="submit" class="bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme" id="save-btn" disabled>Save Attendance</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div id="view-attendance" class="tab-content hidden">
                        <div id="view-students-container" class="overflow-x-auto hidden">
                            <table class="min-w-full divide-y divide-gray-700" aria-label="Attendance Records">
                                <thead class="bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reg No</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Username</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Attendance</th>
                                    </tr>
                                </thead>
                                <tbody id="view-data-details" class="divide-y divide-gray-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Modal -->
            <div id="report-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50" role="dialog" aria-labelledby="report-modal-title" aria-modal="true">
                <div class="bg-gray-800 rounded-lg shadow-lg max-w-2xl w-full p-6 modal-content">
                    <div class="flex justify-between mb-4">
                        <h2 id="report-modal-title" class="text-xl font-semibold text-theme">Generate Attendance Report</h2>
                        <button id="close-report-modal" class="text-gray-400 hover:text-white" aria-label="Close modal">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <form id="report-form" method="POST" action="generate_attendance_report.php">
                        <input type="hidden" name="assignment_id" id="report_assignment_id" value="<?= htmlspecialchars($selected_assignment_id ?? '') ?>">
                        <input type="hidden" name="format" value="csv">
                        <div class="space-y-4">
                            <!-- Date Range -->
                            <div>
                                <label class="block text-sm font-semibold mb-2">Date Range</label>
                                <div class="flex space-x-2">
                                    <input type="date" name="start_date" class="w-1/2 px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme" required>
                                    <input type="date" name="end_date" class="w-1/2 px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme" required>
                                </div>
                                <select name="date_preset" class="w-full mt-2 px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme">
                                    <option value="">Custom Range</option>
                                    <option value="last_7_days">Last 7 Days</option>
                                    <option value="last_30_days">Last 30 Days</option>
                                    <option value="this_semester">This Semester</option>
                                </select>
                            </div>
                            <!-- Data Fields -->
                            <div>
                                <label class="block text-sm font-semibold mb-2">Include Fields</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fields[]" value="student_reg_no" checked class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="ml-2 text-sm">Student Reg No</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fields[]" value="student_username" checked class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="ml-2 text-sm">Student Username</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fields[]" value="student_email" checked class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="ml-2 text-sm">Student Email</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fields[]" value="session_date" checked class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="ml-2 text-sm">Session Date</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fields[]" value="session_time" class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="ml-2 text-sm">Session Time</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fields[]" value="venue" class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="ml-2 text-sm">Venue</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fields[]" value="attendance_status" checked class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="ml-2 text-sm">Attendance Status</span>
                                    </label>
                                </div>
                            </div>
                            <!-- Summary Options -->
                            <div>
                                <label class="block text-sm font-semibold mb-2">Summary Options</label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="include_summary" checked class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                    <span class="ml-2 text-sm">Include Attendance Summary (e.g., % Present)</span>
                                </label>
                                <label class="flex items-center mt-2">
                                    <input type="checkbox" name="include_session_count" checked class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                    <span class="ml-2 text-sm">Include Total Session Count</span>
                                </label>
                            </div>
                            <!-- Filters -->
                            <div>
                                <label class="block text-sm font-semibold mb-2">Filters</label>
                                <select name="attendance_filter" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme">
                                    <option value="">All Attendance Statuses</option>
                                    <option value="Present">Present Only</option>
                                    <option value="Absent">Absent Only</option>
                                    <option value="Late">Late Only</option>
                                </select>
                            </div>
                            <!-- Report Customization -->
                            <div>
                                <label class="block text-sm font-semibold mb-2">Report Customization</label>
                                <label class="block mt-2">
                                    <span class="text-sm font-semibold">Report Title</span>
                                    <input type="text" name="report_title" id="report_title" class="w-full mt-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme" placeholder="Attendance Report for <?= htmlspecialchars($unit_name) ?>" value="Attendance Report for <?= htmlspecialchars($unit_name) ?>">
                                </label>
                            </div>
                            <!-- Error Display -->
                            <div id="report-error" class="hidden bg-red-700 border border-red-400 text-white px-4 py-3 rounded" role="alert"></div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-2">
                            <button type="button" id="cancel-report-btn" class="px-4 py-2 bg-gray-600 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-theme rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-gray-800 text-center py-4 text-gray-400">
        <p class="text-sm">© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const unitSelect = document.getElementById('unit_select');
        const sessionSelect = document.getElementById('session_id');
        const loadStudentsBtn = document.getElementById('load-students');
        const markTable = document.getElementById('mark-students');
        const viewTable = document.getElementById('view-data-details');
        const markContainer = document.getElementById('mark-students-container');
        const viewContainer = document.getElementById('view-students-container');
        const sessionIdInput = document.getElementById('session_id_form');
        const attendanceAssignmentIdInput = document.getElementById('attendance_assignment_id');
        const saveBtn = document.getElementById('save-btn');
        const loading = document.getElementById('loading');
        const generateReportBtn = document.getElementById('generate-report-btn');
        const reportModal = document.getElementById('report-modal');
        const closeReportModalBtn = document.getElementById('close-report-modal');
        const cancelReportBtn = document.getElementById('cancel-report-btn');
        const reportForm = document.getElementById('report-form');
        const reportError = document.getElementById('report-error');
        const reportAssignmentIdInput = document.getElementById('report_assignment_id');
        const unitHeader = document.getElementById('unit-header');
        const sessionsContainer = document.getElementById('sessions-container');
        const tabsContainer = document.getElementById('tabs-container');
        const sessionContent = document.getElementById('session-content');
        const noSessionsMessage = document.getElementById('no-sessions-message');
        const reportTitleInput = document.getElementById('report_title');
        let activeTab = 'mark';

        // Validate DOM elements
        if (!unitSelect) console.error('Unit select not found');
        if (!sessionsContainer) console.error('Sessions container not found');
        if (!tabsContainer) console.error('Tabs container not found');
        if (!sessionContent) console.error('Session content not found');
        if (!noSessionsMessage) console.error('No sessions message not found');
        if (!reportForm) console.error('Report form not found');
        if (!reportModal) console.error('Report modal not found');

        // Tab switching
        window.switchTab = (tab) => {
            activeTab = tab;
            const markTab = document.getElementById('mark-tab');
            const viewTab = document.getElementById('view-tab');
            const markAttendance = document.getElementById('mark-attendance');
            const viewAttendance = document.getElementById('view-attendance');
            if (markTab && viewTab && markAttendance && viewAttendance) {
                markTab.classList.toggle('tab-active', tab === 'mark');
                viewTab.classList.toggle('tab-active', tab === 'view');
                markAttendance.classList.toggle('hidden', tab !== 'mark');
                viewAttendance.classList.toggle('hidden', tab !== 'view');
            }
            if (!sessionSelect?.value) {
                if (markContainer) markContainer.classList.add('hidden');
                if (viewContainer) viewContainer.classList.add('hidden');
                if (saveBtn) saveBtn.disabled = true;
            } else {
                if (markContainer) markContainer.classList.toggle('hidden', tab !== 'mark');
                if (viewContainer) viewContainer.classList.toggle('hidden', tab !== 'view');
                if (saveBtn) saveBtn.disabled = tab !== 'mark' || markTable.children.length === 0;
            }
        };

        // Unit selection
        unitSelect?.addEventListener('change', () => {
            const assignmentId = unitSelect.value;
            const unitName = unitSelect.selectedOptions?.[0]?.dataset.unitName || 'Select a Unit';
            if (!assignmentId) {
                if (sessionsContainer) sessionsContainer.classList.add('hidden');
                if (tabsContainer) tabsContainer.classList.add('hidden');
                if (sessionContent) sessionContent.classList.add('hidden');
                if (noSessionsMessage) noSessionsMessage.classList.add('hidden');
                if (unitHeader) unitHeader.textContent = 'Attendance for ' + unitName;
                if (generateReportBtn) generateReportBtn.disabled = true;
                if (reportAssignmentIdInput) reportAssignmentIdInput.value = '';
                if (attendanceAssignmentIdInput) attendanceAssignmentIdInput.value = '';
                if (reportTitleInput) reportTitleInput.value = 'Attendance Report for ' + unitName;
                if (sessionSelect) sessionSelect.innerHTML = '<option value="">Select a session</option>';
                if (markContainer) markContainer.classList.add('hidden');
                if (viewContainer) viewContainer.classList.add('hidden');
                if (saveBtn) saveBtn.disabled = true;
                return;
            }

            if (loading) loading.classList.remove('hidden');
            if (generateReportBtn) generateReportBtn.disabled = false;
            if (reportAssignmentIdInput) reportAssignmentIdInput.value = assignmentId;
            if (attendanceAssignmentIdInput) attendanceAssignmentIdInput.value = assignmentId;
            if (unitHeader) unitHeader.textContent = 'Attendance for ' + unitName;
            if (reportTitleInput) reportTitleInput.value = 'Attendance Report for ' + unitName;

            // Fetch sessions for selected unit
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                controller.abort();
                if (loading) loading.classList.add('hidden');
                showError('Request timed out. Please try again.');
            }, 10000);

            const formData = new URLSearchParams();
            formData.append('assignment_id', assignmentId);

            fetch('get_unit_sessions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString(),
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log('Fetch response status:', response.status, response.statusText);
                if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    if (loading) loading.classList.add('hidden');
                    if (sessionSelect) sessionSelect.innerHTML = '<option value="">Select a session</option>';
                    if (data.success && data.sessions && Array.isArray(data.sessions)) {
                        data.sessions.forEach(session => {
                            const option = document.createElement('option');
                            option.value = session.session_id;
                            option.dataset.assignmentId = assignmentId;
                            option.textContent = `${session.session_date || 'Unknown Date'} ${session.start_time || 'N/A'} - ${session.end_time || 'N/A'} (${session.venue || 'N/A'})`;
                            if (sessionSelect) sessionSelect.appendChild(option);
                        });
                        if (sessionsContainer) sessionsContainer.classList.remove('hidden');
                        if (tabsContainer) tabsContainer.classList.remove('hidden');
                        if (sessionContent) sessionContent.classList.remove('hidden');
                        if (noSessionsMessage) noSessionsMessage.classList.add('hidden');
                        if (markContainer) markContainer.classList.add('hidden');
                        if (viewContainer) viewContainer.classList.add('hidden');
                        if (saveBtn) saveBtn.disabled = true;
                        switchTab('mark'); // Ensure tabs are initialized
                    } else {
                        if (noSessionsMessage) {
                            noSessionsMessage.innerHTML = `
                                <p>No sessions scheduled for this unit. 
                                <a href="schedule_session.php?assignment_id=${assignmentId}" class="text-theme hover:underline">Schedule a session</a>.</p>
                            `;
                            noSessionsMessage.classList.remove('hidden');
                        }
                        if (sessionsContainer) sessionsContainer.classList.remove('hidden');
                        if (tabsContainer) tabsContainer.classList.add('hidden');
                        if (sessionContent) sessionContent.classList.add('hidden');
                        if (markContainer) markContainer.classList.add('hidden');
                        if (viewContainer) viewContainer.classList.add('hidden');
                    }
                } catch (e) {
                    throw new Error('Failed to parse response: ' + e.message);
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                if (loading) loading.classList.add('hidden');
                console.error('Fetch error:', error);
                showError(`Failed to load sessions: ${error.message}`);
            });
        });

        // Session selection
        sessionSelect?.addEventListener('change', () => {
            if (loadStudentsBtn) loadStudentsBtn.disabled = !sessionSelect.value;
            if (!sessionSelect.value) {
                if (markContainer) markContainer.classList.add('hidden');
                if (viewContainer) viewContainer.classList.add('hidden');
                if (saveBtn) saveBtn.disabled = true;
            }
        });

        // Load students
        loadStudentsBtn?.addEventListener('click', () => {
            const sessionId = sessionSelect?.value;
            const assignmentId = sessionSelect?.selectedOptions[0]?.dataset.assignmentId || '';

            if (!sessionId || !assignmentId) return;

            if (loading) loading.classList.remove('hidden');
            if (sessionIdInput) sessionIdInput.value = sessionId;

            const formData = new URLSearchParams();
            formData.append('assignment_id', assignmentId);
            formData.append('session_id', sessionId);

            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                controller.abort();
                if (loading) loading.classList.add('hidden');
                showError('Request timed out while fetching students.');
            }, 10000);

            fetch('get_session_students.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString(),
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log('Students fetch response:', response.status, response.statusText);
                if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
                return response.text();
            })
            .then(text => {
                console.log('Students raw response:', text);
                try {
                    const data = JSON.parse(text);
                    if (loading) loading.classList.add('hidden');
                    if (markTable) markTable.innerHTML = '';
                    if (viewTable) viewTable.innerHTML = '';

                    if (data.success) {
                        if (data.students.length === 0) {
                            const message = '<tr><td colspan="4" class="px-4 py-3 text-sm text-center text-gray-400">No students enrolled.</td></tr>';
                            if (markTable) markTable.innerHTML = message;
                            if (viewTable) viewTable.innerHTML = message;
                        } else {
                            data.students.forEach(student => {
                                const markRow = document.createElement('tr');
                                markRow.className = 'hover:bg-gray-700';
                                const isChecked = student.attendance_status === 'Present' ? 'checked' : '';
                                markRow.innerHTML = `
                                    <td class="px-4 py-2 text-sm">${student.registration_number || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm">${student.username || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm">${student.email || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <input type="checkbox" name="attendance[${student.student_id}]" ${isChecked} class="h-4 w-4 text-theme bg-gray-800 border-gray-600 rounded focus:ring-2 focus:ring-theme">
                                    </td>
                                `;
                                if (markTable) markTable.appendChild(markRow);

                                const viewRow = document.createElement('tr');
                                viewRow.className = 'hover:bg-gray-700';
                                viewRow.innerHTML = `
                                    <td class="px-4 py-2 text-sm">${student.registration_number || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm">${student.username || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm">${student.email || 'N/A'}</td>
                                    <td class="px-4 py-2 text-sm">${student.attendance_status || 'Not Marked'}</td>
                                `;
                                if (viewTable) viewTable.appendChild(viewRow);
                            });
                        }
                        if (markContainer) markContainer.classList.toggle('hidden', activeTab !== 'mark');
                        if (viewContainer) viewContainer.classList.toggle('hidden', activeTab !== 'view');
                        if (saveBtn) saveBtn.disabled = activeTab !== 'mark' || data.students.length === 0;
                    } else {
                        showError(data.error || 'Failed to load students.');
                    }
                } catch (e) {
                    throw new Error('Failed to parse response: ' + e.message);
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                if (loading) loading.classList.add('hidden');
                console.error('Students fetch error:', error);
                showError(`Failed to load students: ${error.message}`);
            });
        });

        // Report modal handling
        const openReportModal = () => {
            if (!unitSelect?.value) {
                showError('Please select a unit before generating a report.');
                return;
            }
            if (reportModal) reportModal.classList.remove('hidden');
            if (reportError) reportError.classList.add('hidden');
        };

        const closeReportModal = () => {
            if (reportModal) reportModal.classList.add('hidden');
            if (reportError) reportError.classList.add('hidden');
            if (reportForm) reportForm.reset();
            if (reportAssignmentIdInput && unitSelect) reportAssignmentIdInput.value = unitSelect.value;
            if (reportTitleInput && unitSelect) {
                reportTitleInput.value = 'Attendance Report for ' + (unitSelect.selectedOptions?.[0]?.dataset.unitName || 'Select a Unit');
            }
            // Reset summary checkboxes to checked
            const summaryCheckbox = reportForm?.querySelector('input[name="include_summary"]');
            const sessionCountCheckbox = reportForm?.querySelector('input[name="include_session_count"]');
            if (summaryCheckbox) summaryCheckbox.checked = true;
            if (sessionCountCheckbox) sessionCountCheckbox.checked = true;
        };

        if (generateReportBtn) {
            generateReportBtn.addEventListener('click', openReportModal);
        } else {
            console.error('Generate Report button not found');
        }

        if (closeReportModalBtn) {
            closeReportModalBtn.addEventListener('click', closeReportModal);
        }
        if (cancelReportBtn) {
            cancelReportBtn.addEventListener('click', closeReportModal);
        }

        if (reportModal) {
            reportModal.addEventListener('click', (e) => {
                if (e.target === reportModal) closeReportModal();
            }
);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && reportModal && !reportModal.classList.contains('hidden')) {
                closeReportModal();
            }
        });

        if (reportForm) {
            reportForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const formData = new FormData(reportForm);
                const fields = formData.getAll('fields[]');
                if (fields.length === 0) {
                    if (reportErrorModal) {
                        reportError.textContent = 'Please select at least one field to include in the report.';
                        reportError.classList.remove('hidden');
                        return;
                    }
                }
                if (!formData.get('assignment_id')) {
                    if (reportError) {
                        reportError.textContent = 'Please select a unit before generating a report.';
                        reportError.classList.remove('hidden');
                        return;
                    }
                }
                if (loading) loading.classList.remove('hidden');
                // Submit the form and close modal after a slight delay
                setTimeout(() => {
                    if (reportModal) reportModal.classList.add('hidden');
                    if (loading) loading.classList.add('hidden');
                    reportForm.submit();
                }, 500);
            });
        }

        const showError = (message) => {
            const alert = document.createElement('div');
            alert.id = 'alert-error';
            alert.className = 'bg-red-700 border border-red-400 text-white px-4 py-3 rounded-lg mb-4';
            alert.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="text-sm">${message}</span>
                    <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-400" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            `;
            const existingAlert = document.getElementById('alert-error');
            if (existingAlert) existingAlert.remove();
            const main = document.querySelector('main');
            const section = document.querySelector('section');
            if (main && section) main.insertBefore(alert, section);
        };

        // Trigger unit selection if pre-selected
        if (unitSelect?.value) {
            unitSelect.dispatchEvent(new Event('change'));
        }
    });
    </script>
</body>
</html>