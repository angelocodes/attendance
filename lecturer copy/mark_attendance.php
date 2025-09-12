<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
require_once 'lecturer_controller.php';
require_once 'config.php';

// Define NOTIFICATIONS_LIMIT if not in config.php
if (!defined('NOTIFICATIONS_LIMIT')) {
    define('NOTIFICATIONS_LIMIT', 10);
}

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

// Validate assignment_id
$assignment_id = isset($_GET['assignment_id']) && is_numeric($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if (!$assignment_id) {
    header("Location: my_units.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$unit_name = 'Unknown Unit';
$sessions = [];

// Initialize CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    $error = "Database connection failed.";
} else {
    $controller = new LecturerController($conn);

    // Fetch theme color
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme_color'");
    if ($stmt->execute()) {
        $stmt->bind_result($theme_color);
        $stmt->fetch();
        $stmt->close();
    } else {
        $theme_color = '#6c8976';
        error_log("Failed to fetch theme color: " . $stmt->error, 3, '../logs/errors.log');
    }

    // Fetch unit name
    $stmt = $conn->prepare("
        SELECT cu.unit_name
        FROM lecturer_assignments la 
        JOIN course_units cu ON la.unit_id = cu.unit_id
        WHERE la.assignment_id = ? AND la.lecturer_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $lecturer_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $unit_name = $row['unit_name'];
        } else {
            $error = "Invalid assignment selected.";
        }
        $stmt->close();
    } else {
        $error = "Failed to fetch unit details.";
        error_log("Error fetching unit: " . $stmt->error, 3, '../logs/errors.log');
    }

    // Fetch sessions
    if (!$error) {
        $result = $controller->getClassSessions($lecturer_id, $assignment_id);
        if ($result['success']) {
            $sessions = $result['sessions'];
        } else {
            $error = $result['error'];
        }
    }

    // Handle attendance submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        // Debug CSRF token
        error_log("POST CSRF: " . ($_POST['csrf_token'] ?? 'missing') . ", Session CSRF: " . ($_SESSION['csrf_token'] ?? 'missing'), 3, '../logs/errors.log');

        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = "Invalid CSRF token.";
            error_log("CSRF validation failed in mark_attendance.php", 3, '../logs/errors.log');
        } else {
            $session_id = isset($_POST['session_id']) && is_numeric($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
            $attendance = isset($_POST['attendance']) && is_array($_POST['attendance']) ? $_POST['attendance'] : [];

            if ($session_id <= 0) {
                $error = "Please select a session.";
            } else {
                // Prepare attendance data
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
                        // Regenerate CSRF token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $csrf_token = $_SESSION['csrf_token'];
                    } else {
                        $error = $result['error'];
                    }
                }
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
    <title>Mark Attendance - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --theme-color: <?= htmlspecialchars($theme_color) ?>;
        }
        .bg-theme { background-color: var(--theme-color); }
        .text-theme { color: var(--theme-color); }
        .hover\:bg-theme:hover { background-color: var(--theme-color); }
        .focus\:ring-theme:focus { --tw-ring-color: var(--theme-color); }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">
    <?php include 'lecturer_navbar.php'; ?>

    <main class="container mx-auto p-6 flex-grow">
        <h1 class="text-3xl font-semibold text-theme mb-6" role="heading" aria-level="1">Mark Attendance for <?= htmlspecialchars($unit_name) ?></h1>

        <div id="loading" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50">
            <svg class="animate-spin h-8 w-8 text-theme" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <?php if ($error): ?>
            <div id="error-alert" class="bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span><?= htmlspecialchars($error) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div id="success-alert" class="bg-green-700 border border-green-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span><?= htmlspecialchars($success) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <section class="bg-gray-800 rounded-lg shadow-lg p-6">
            <?php if (count($sessions) === 0): ?>
                <p class="text-center text-gray-400">No sessions scheduled for this unit. <a href="schedule_session.php?assignment_id=<?= $assignment_id ?>" class="text-theme hover:underline">Schedule a session</a>.</p>
            <?php else: ?>
                <form id="attendance-form" method="POST" action="">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="session_id" id="session_id" value="">

                    <div class="mb-4">
                        <label for="session-select" class="block text-sm font-semibold mb-2">Select Session</label>
                        <select id="session-select" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme" aria-describedby="session-help">
                            <option value="">Select a session</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= $session['session_id'] ?>" data-assignment-id="<?= $assignment_id ?>">
                                    <?= htmlspecialchars($session['session_date'] . ' ' . $session['start_time'] . ' - ' . $session['end_time'] . ' (' . $session['venue'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="session-help" class="text-sm text-gray-400 mt-1">Select a scheduled session to mark attendance.</p>
                    </div>

                    <div id="students-table-container" class="overflow-x-auto hidden">
                        <table class="min-w-full divide-y divide-gray-700" aria-label="Students Attendance Table">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Reg No</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">Present</th>
                                </tr>
                            </thead>
                            <tbody id="students-table" class="divide-y divide-gray-600"></tbody>
                        </table>
                        <div class="mt-4">
                            <button type="submit" class="bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme" id="save-btn" disabled>
                                Save Attendance
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <footer class="bg-gray-800 text-center py-4 text-gray-400">
        <p>© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sessionSelect = document.getElementById('session-select');
            const studentsTable = document.getElementById('students-table');
            const studentsTableContainer = document.getElementById('students-table-container');
            const sessionIdInput = document.getElementById('session_id');
            const saveBtn = document.getElementById('save-btn');
            const loadingOverlay = document.getElementById('loading');
            const errorAlert = document.getElementById('error-alert');
            let csrfToken = document.getElementById('csrf_token').value;

            // Fetch new CSRF token if page is reloaded
            const refreshCsrfToken = async () => {
                try {
                    const response = await fetch('refresh_csrf.php', { method: 'GET' });
                    const data = await response.json();
                    if (data.csrf_token) {
                        csrfToken = data.csrf_token;
                        document.getElementById('csrf_token').value = csrfToken;
                    }
                } catch (error) {
                    console.error('Failed to refresh CSRF token:', error);
                }
            };

            sessionSelect.addEventListener('change', () => {
                const sessionId = sessionSelect.value;
                const assignmentId = sessionSelect.selectedOptions[0].dataset.assignmentId;

                if (!sessionId) {
                    studentsTableContainer.classList.add('hidden');
                    saveBtn.disabled = true;
                    return;
                }

                loadingOverlay.classList.remove('hidden');
                sessionIdInput.value = sessionId;

                fetch('get_session_students.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `assignment_id=${encodeURIComponent(assignmentId)}&session_id=${encodeURIComponent(sessionId)}&csrf_token=${encodeURIComponent(csrfToken)}`
                })
                .then(response => response.json())
                .then(data => {
                    loadingOverlay.classList.add('hidden');
                    studentsTable.innerHTML = '';

                    if (data.success) {
                        if (data.students.length === 0) {
                            studentsTable.innerHTML = '<tr><td colspan="4" class="px-4 py-3 text-center text-gray-400">No students enrolled.</td></tr>';
                        } else {
                            data.students.forEach(student => {
                                const row = document.createElement('tr');
                                row.className = 'hover:bg-gray-600';
                                const isChecked = student.attendance_status === 'Present' ? 'checked' : '';
                                const name = student.last_name ? `${student.first_name} ${student.last_name}` : student.first_name;
                                row.innerHTML = `
                                    <td class="px-4 py-3 text-sm">${student.registration_number}</td>
                                    <td class="px-4 py-3 text-sm">${name}</td>
                                    <td class="px-4 py-3 text-sm">${student.email}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <input type="checkbox" name="attendance[${student.student_id}]" ${isChecked} class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                    </td>
                                `;
                                studentsTable.appendChild(row);
                            });
                            studentsTableContainer.classList.remove('hidden');
                            saveBtn.disabled = false;
                        }
                    } else {
                        if (errorAlert) errorAlert.remove();
                        const alert = document.createElement('div');
                        alert.id = 'error-alert';
                        alert.className = 'bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center';
                        alert.innerHTML = `
                            <span>${data.error}</span>
                            <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        `;
                        document.querySelector('main').insertBefore(alert, document.querySelector('section'));
                    }
                })
                .catch(error => {
                    loadingOverlay.classList.add('hidden');
                    const alert = document.createElement('div');
                    alert.id = 'error-alert';
                    alert.className = 'bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center';
                    alert.innerHTML = `
                        <span>Failed to load students: ${error.message}</span>
                        <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    `;
                    document.querySelector('main').insertBefore(alert, document.querySelector('section'));
                });
            });

            // Refresh CSRF token on page load
            refreshCsrfToken();
        });
    </script>
</body>
</html>