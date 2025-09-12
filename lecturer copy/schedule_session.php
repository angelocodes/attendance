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
    error_log("Database connection failed in schedule_session.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    $error = "Database connection failed. Please try again later.";
    $assignments = [];
    $assignments_error = $error;
} else {
    // Initialize controller
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

    // Fetch assignments
    $assignments_result = $controller->getLecturerAssignments($lecturer_id);
    $assignments = $assignments_result['success'] ? $assignments_result['assignments'] : [];
    $assignments_error = !$assignments_result['success'] ? $assignments_result['error'] : '';

    // Fetch all sessions
    $sessions_result = $controller->getClassSessions($lecturer_id);
    $sessions = $sessions_result['success'] ? $sessions_result['sessions'] : [];
    $sessions_error = !$sessions_result['success'] ? $sessions_result['error'] : '';
}

$message = '';
$error = '';
$session_date = '';
$venue = '';
$start_time = '';
$end_time = '';
$assignment_id = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_session'])) {
        // Handle session deletion
        $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        if ($session_id > 0) {
            // Check if session is in the past
            $stmt = $conn->prepare("SELECT session_date FROM class_sessions WHERE session_id = ? AND lecturer_id = ?");
            $stmt->bind_param("ii", $session_id, $lecturer_id);
            $stmt->execute();
            $stmt->bind_result($session_date);
            if ($stmt->fetch() && strtotime($session_date) < strtotime(date('Y-m-d'))) {
                $error = "Cannot delete past sessions.";
                $stmt->close();
            } else {
                $stmt->close();
                // Check for associated attendance records
                $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance_records WHERE session_id = ?");
                $stmt->bind_param("i", $session_id);
                $stmt->execute();
                $stmt->bind_result($attendance_count);
                $stmt->fetch();
                $stmt->close();
                
                if ($attendance_count > 0) {
                    $error = "Cannot delete session with existing attendance records.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM class_sessions WHERE session_id = ? AND lecturer_id = ?");
                    $stmt->bind_param("ii", $session_id, $lecturer_id);
                    if ($stmt->execute()) {
                        $message = "Session deleted successfully.";
                    } else {
                        $error = "Failed to delete session: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            header("Location: schedule_session.php?message=" . urlencode($message) . "&error=" . urlencode($error));
            exit;
        }
    } elseif (isset($_POST['update_session'])) {
        // Handle session update
        $session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        $session_date = trim($_POST['session_date'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');

        // Validate inputs
        if ($session_id <= 0) {
            $error = "Invalid session ID.";
        } elseif (empty($session_date)) {
            $error = "Please select the session date.";
        } elseif (strtotime($session_date) < strtotime(date('Y-m-d'))) {
            $error = "Session date must be today or in the future.";
        } elseif (empty($venue)) {
            $error = "Please enter the session venue.";
        } elseif (empty($start_time)) {
            $error = "Please select the start time.";
        } elseif (empty($end_time)) {
            $error = "Please select the end time.";
        } elseif (strtotime($start_time) >= strtotime($end_time)) {
            $error = "Start time must be before end time.";
        } else {
            // Sanitize inputs
            $venue = htmlspecialchars($venue, ENT_QUOTES, 'UTF-8');

            $stmt = $conn->prepare("UPDATE class_sessions SET session_date = ?, venue = ?, start_time = ?, end_time = ? WHERE session_id = ? AND lecturer_id = ?");
            $stmt->bind_param("ssssii", $session_date, $venue, $start_time, $end_time, $session_id, $lecturer_id);
            if ($stmt->execute()) {
                $message = "Session updated successfully.";
            } else {
                $error = "Failed to update session: " . $stmt->error;
            }
            $stmt->close();
            header("Location: schedule_session.php?message=" . urlencode($message) . "&error=" . urlencode($error));
            exit;
        }
    } else {
        // Handle new session creation
        $assignment_id = isset($_POST['assignment_id']) && is_numeric($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
        $session_date = trim($_POST['session_date'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');

        // Validate inputs
        if ($assignment_id <= 0) {
            $error = "Please select a valid assignment.";
        } elseif (empty($session_date)) {
            $error = "Please select the session date.";
        } elseif (strtotime($session_date) < strtotime(date('Y-m-d'))) {
            $error = "Session date must be today or in the future.";
        } elseif (empty($venue)) {
            $error = "Please enter the session venue.";
        } elseif (empty($start_time)) {
            $error = "Please select the start time.";
        } elseif (empty($end_time)) {
            $error = "Please select the end time.";
        } elseif (strtotime($start_time) >= strtotime($end_time)) {
            $error = "Start time must be before end time.";
        } else {
            // Sanitize inputs
            $venue = htmlspecialchars($venue, ENT_QUOTES, 'UTF-8');

            $result = $controller->scheduleSession($lecturer_id, $assignment_id, $session_date, $venue, $start_time, $end_time);
            if ($result['success']) {
                $message = $result['message'];
                $assignment_id = $session_date = $venue = $start_time = $end_time = '';
                // Refetch sessions to ensure table is updated
                $sessions_result = $controller->getClassSessions($lecturer_id);
                $sessions = $sessions_result['success'] ? $sessions_result['sessions'] : [];
                $sessions_error = !$sessions_result['success'] ? $sessions_result['error'] : '';
            } else {
                $error = $result['error'];
            }
            header("Location: schedule_session.php?message=" . urlencode($message) . "&error=" . urlencode($error));
            exit;
        }
    }
}

// Handle messages from redirects
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Attendance Session - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --theme-color: <?= htmlspecialchars($theme_color) ?>;
        }
        .bg-theme { background-color: var(--theme-color); }
        .text-theme { color: var(--theme-color); }
        .hover\:bg-theme:hover { background-color: var(--theme-color); }
        .focus\:ring-theme:focus { --tw-ring-color: var(--theme-color); }
        .modal-content { max-height: 80vh; overflow-y: auto; }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">
    <?php include 'lecturer_navbar.php'; ?>

    <main class="container mx-auto p-6 flex-grow">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-theme" role="heading" aria-level="1">Schedule Attendance Session</h1>
            <button onclick="openAddModal()" 
                    class="px-4 py-2 bg-theme text-white rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme"
                    aria-label="Add New Session">
                <i class="fas fa-plus mr-2"></i>Add New Session
            </button>
        </div>

        <div id="loading" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50">
            <svg class="animate-spin h-8 w-8 text-theme" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <?php if ($message): ?>
            <div id="success-alert" class="bg-green-700 border border-green-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span><?= htmlspecialchars($message) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error || $assignments_error || $sessions_error): ?>
            <div id="error-alert" class="bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span><?= htmlspecialchars($error ?: $assignments_error ?: $sessions_error) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Add Session Modal -->
        <div id="addModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6 modal-content">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-theme">Add New Session</h3>
                    <button onclick="closeAddModal()" class="text-gray-400 hover:text-white" aria-label="Close modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="schedule-form" method="POST" action="schedule_session.php" aria-label="Schedule Attendance Session">
                    <div class="mb-4">
                        <label for="assignment_id" class="block text-white font-semibold mb-2">Select Assignment</label>
                        <select id="assignment_id" name="assignment_id" required
                                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme"
                                aria-describedby="assignment_id-help">
                            <option value="">-- Select Unit and Academic Year --</option>
                            <?php foreach ($assignments as $item): ?>
                                <option value="<?= $item['assignment_id'] ?>" <?= $assignment_id == $item['assignment_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['unit_name']) ?> — Academic Year <?= htmlspecialchars($item['academic_year']) ?> — Semester <?= htmlspecialchars($item['semester']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="assignment_id-help" class="text-sm text-gray-400 mt-1">Choose the course unit and academic year for the session.</p>
                    </div>

                    <div class="mb-4">
                        <label for="session_date" class="block text-sm font-semibold mb-2">Session Date</label>
                        <input type="date" id="session_date" name="session_date" required
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme"
                               value="<?= htmlspecialchars($session_date) ?>"
                               aria-describedby="session_date-help">
                        <p id="session_date-help" class="text-sm text-gray-400 mt-1">Select a date for the session (today or future).</p>
                    </div>

                    <div class="mb-4">
                        <label for="start_time" class="block text-sm font-semibold mb-2">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme"
                               value="<?= htmlspecialchars($start_time) ?>"
                               aria-describedby="start_time-help">
                        <p id="start_time-help" class="text-sm text-gray-400 mt-1">Select the start time for the session.</p>
                    </div>

                    <div class="mb-4">
                        <label for="end_time" class="block text-sm font-semibold mb-2">End Time</label>
                        <input type="time" id="end_time" name="end_time" required
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme"
                               value="<?= htmlspecialchars($end_time) ?>"
                               aria-describedby="end_time-help">
                        <p id="end_time-help" class="text-sm text-gray-400 mt-1">Select the end time for the session.</p>
                    </div>

                    <div class="mb-6">
                        <label for="venue" class="block text-sm font-semibold mb-2">Venue</label>
                        <input type="text" id="venue" name="venue" required
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme"
                               placeholder="E.g., Lecture Hall A"
                               maxlength="100"
                               value="<?= htmlspecialchars($venue) ?>"
                               aria-describedby="venue-help">
                        <p id="venue-help" class="text-sm text-gray-400 mt-1">Specify the location of the session.</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddModal()" 
                                class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-theme text-white rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme relative"
                                aria-label="Schedule Session">
                            <span id="submit-text">Schedule Session</span>
                            <svg id="submit-spinner" class="hidden absolute inset-y-0 left-2 my-auto h-5 w-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Scheduled Sessions Table -->
        <section class="bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-theme mb-4" role="heading" aria-level="2">Scheduled Sessions</h2>
            
            <?php if (empty($sessions)): ?>
                <p class="text-gray-400">No sessions have been scheduled yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-gray-700 rounded-lg overflow-hidden">
                        <thead class="bg-gray-600">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Venue</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600">
                            <?php foreach ($sessions as $session): 
                                $unit_name = '';
                                foreach ($assignments as $assignment) {
                                    if ($assignment['assignment_id'] == $session['assignment_id']) {
                                        $unit_name = $assignment['unit_name'];
                                        break;
                                    }
                                }
                                // Check if session is in the past or has attendance records
                                $is_past_session = strtotime($session['session_date']) < strtotime(date('Y-m-d'));
                                $has_attendance = false;
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance_records WHERE session_id = ?");
                                $stmt->bind_param("i", $session['session_id']);
                                $stmt->execute();
                                $stmt->bind_result($attendance_count);
                                $stmt->fetch();
                                $has_attendance = $attendance_count > 0;
                                $stmt->close();
                            ?>
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($unit_name) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars(date('M j, Y', strtotime($session['session_date']))) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?= htmlspecialchars(date('g:i A', strtotime($session['start_time']))) ?> - 
                                        <?= htmlspecialchars(date('g:i A', strtotime($session['end_time']))) ?>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($session['venue']) ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <button onclick="openEditModal(<?= htmlspecialchars(json_encode($session)) ?>, '<?= htmlspecialchars($unit_name) ?>')"
                                                class="text-blue-400 hover:text-blue-300 mr-3"
                                                aria-label="Edit session">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!$is_past_session && !$has_attendance): ?>
                                            <button onclick="openDeleteModal(<?= $session['session_id'] ?>, '<?= htmlspecialchars($unit_name) ?>', '<?= htmlspecialchars(date('M j, Y', strtotime($session['session_date']))) ?>')"
                                                    class="text-red-400 hover:text-red-300"
                                                    aria-label="Delete session">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-500 cursor-not-allowed" 
                                                  aria-label="<?= $is_past_session ? 'Cannot delete past session' : 'Cannot delete session with attendance records' ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Edit Session Modal -->
    <div id="editModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6 modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-theme">Edit Session</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-white" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="edit-form" method="POST" action="schedule_session.php">
                <input type="hidden" name="session_id" id="edit_session_id">
                <input type="hidden" name="update_session" value="1">
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Unit</label>
                    <input type="text" id="edit_unit_name" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded cursor-not-allowed" readonly>
                </div>
                
                <div class="mb-4">
                    <label for="edit_session_date" class="block text-sm font-semibold mb-2">Session Date</label>
                    <input type="date" id="edit_session_date" name="session_date" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme">
                </div>
                
                <div class="mb-4">
                    <label for="edit_start_time" class="block text-sm font-semibold mb-2">Start Time</label>
                    <input type="time" id="edit_start_time" name="start_time" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme">
                </div>
                
                <div class="mb-4">
                    <label for="edit_end_time" class="block text-sm font-semibold mb-2">End Time</label>
                    <input type="time" id="edit_end_time" name="end_time" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme">
                </div>
                
                <div class="mb-6">
                    <label for="edit_venue" class="block text-sm font-semibold mb-2">Venue</label>
                    <input type="text" id="edit_venue" name="venue" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme"
                           maxlength="100">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" 
                            class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-theme text-white rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme">
                        Update Session
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-red-500">Delete Session</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-white" aria-label="Close modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <p class="mb-6" id="delete-confirmation-text">Are you sure you want to delete this session?</p>
            
            <form id="delete-form" method="POST" action="schedule_session.php">
                <input type="hidden" name="session_id" id="delete_session_id">
                <input type="hidden" name="delete_session" value="1">
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500">
                        Delete Session
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="bg-gray-800 text-center py-4 text-gray-400">
        <p>© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sessionDateInput = document.getElementById('session_date');
            const editSessionDateInput = document.getElementById('edit_session_date');
            const today = new Date().toISOString().split('T')[0];
            sessionDateInput.setAttribute('min', today);
            editSessionDateInput.setAttribute('min', today);

            const form = document.getElementById('schedule-form');
            const submitBtn = form.querySelector('button[type="submit"]');
            const submitText = document.getElementById('submit-text');
            const submitSpinner = document.getElementById('submit-spinner');
            const loadingOverlay = document.getElementById('loading');

            form.addEventListener('submit', () => {
                submitBtn.disabled = true;
                submitText.classList.add('hidden');
                submitSpinner.classList.remove('hidden');
                loadingOverlay.classList.remove('hidden');
            });

            <?php if ($message): ?>
                form.reset();
                closeAddModal();
                // Ensure success alert is visible
                setTimeout(() => {
                    const successAlert = document.getElementById('success-alert');
                    if (successAlert) {
                        successAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 100);
            <?php endif; ?>
        });

        // Add Modal Functions
        function openAddModal() {
            const modal = document.getElementById('addModal');
            modal.classList.remove('hidden');
        }

        function closeAddModal() {
            const modal = document.getElementById('addModal');
            modal.classList.add('hidden');
            document.getElementById('schedule-form').reset();
        }

        // Edit Modal Functions
        function openEditModal(session, unitName) {
            const modal = document.getElementById('editModal');
            document.getElementById('edit_session_id').value = session.session_id;
            document.getElementById('edit_unit_name').value = unitName;
            document.getElementById('edit_session_date').value = session.session_date;
            document.getElementById('edit_start_time').value = session.start_time;
            document.getElementById('edit_end_time').value = session.end_time;
            document.getElementById('edit_venue').value = session.venue;
            
            modal.classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        // Delete Modal Functions
        function openDeleteModal(sessionId, unitName, sessionDate) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('delete_session_id').value = sessionId;
            document.getElementById('delete-confirmation-text').textContent = 
                `Are you sure you want to delete the session for ${unitName} on ${sessionDate}?`;
            modal.classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
    </script>
</body>
</html>