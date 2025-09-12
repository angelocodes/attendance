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
    exit;
}
$_SESSION['last_activity'] = time();

// Validate lecturer session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'lecturer' || !isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: ../access_denied.php");
    exit;
}
$lecturer_id = (int)$_SESSION['user_id'];

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in dashboard.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    $error = "Database connection failed.";
    $theme_color = '#6c757d';
    $visible_widgets = [];
    $chart_data = [];
    $labels = [];
    $data = [];
    $notifications = [];
} else {
    // Initialize controller
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

    // Fetch user preferences
    $stmt = $conn->prepare("SELECT widget, setting_value FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param("i", $lecturer_id);
    if ($stmt->execute()) {
        $preferences = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $visible_widgets = [];
        foreach ($preferences as $pref) {
            $visible_widgets[$pref['widget']] = $pref['setting_value'];
        }
    } else {
        $visible_widgets = [];
        error_log("Failed to fetch preferences: " . $stmt->error, 3, '../logs/errors.log');
    }

    // Fetch chart data for average attendance percentage
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(cs.session_date, '%Y-%m') AS month,
               COUNT(DISTINCT cs.session_id) AS total_sessions,
               COUNT(ar.status = 'Present' OR NULL) AS present_count,
               COUNT(DISTINCT ar.student_id) AS student_count
        FROM class_sessions cs
        LEFT JOIN attendance_records ar ON cs.session_id = ar.session_id
        JOIN lecturer_assignments la ON cs.unit_id = la.unit_id
        WHERE la.lecturer_id = ?
        GROUP BY month
        ORDER BY month DESC LIMIT 6
    ");
    $stmt->bind_param("i", $lecturer_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $chart_data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $labels = [];
        $data = [];
        foreach ($chart_data as $row) {
            $labels[] = $row['month'];
            $total_possible = $row['total_sessions'] * ($row['student_count'] > 1 ? $row['student_count'] : 0);
            $data[] = $total_possible > 0 ? round(($row['present_count'] / $total_possible) * 100, 2) : 0.0;
        }
    } else {
        $chart_data = [];
        $labels = [];
        $data = [];
        error_log("Failed to fetch chart data: " . $stmt->error, 3, '../logs/errors.log');
    }

    // Fetch recent notifications
    $notifications_limit = NOTIFICATIONS_LIMIT;
    $stmt = $conn->prepare("
        SELECT n.notification_id, n.message, n.created_at, n.is_read
        FROM notifications n
        LEFT JOIN notification_preferences np ON n.notification_id = np.notification_id AND np.user_id = ?
        WHERE (n.user_id = ? OR n.user_id IS NULL) AND n.user_type = 'lecturer'
        AND (np.is_hidden IS NULL OR np.is_hidden = 0)
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("iii", $lecturer_id, $lecturer_id, $notifications_limit);
    if ($stmt->execute()) {
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $notifications = [];
        error_log("Failed to fetch notifications: " . $stmt->error, 3, '../logs/errors.log');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --theme-color: <?= htmlspecialchars($theme_color ?? '#6c757d') ?>;
        }
        .bg-theme { background-color: var(--theme-color); }
        .text-theme { color: var(--theme-color); }
        .hover\:bg-theme:hover { background-color: var(--theme-color); }
        .focus\:ring-theme:focus { --ring-color: var(--theme-color); }
        #notifications-container::-webkit-scrollbar {
            width: 8px;
        }
        #notifications-container::-webkit-scrollbar-track {
            background: #2d3748;
        }
        #notifications-container::-webkit-scrollbar-thumb {
            background-color: var(--theme-color);
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">
    <?php include 'lecturer_navbar.php'; ?>

    <main class="container mx-auto p-6 flex-grow">
        <h1 class="text-3xl font-bold text-theme mb-6" role="heading" aria-level="1">Lecturer Dashboard</h1>

        <div id="loading" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 flex justify-center items-center z-50">
            <svg class="animate-spin h-8 w-8 text-theme" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <?php if (isset($error)): ?>
            <div id="error-alert" class="bg-red-700 border-2 border-red-400 text-white px-4 py-3 rounded-lg mb-4 flex justify-between items-center" role="alert">
                <span class="text-sm"><?= htmlspecialchars($error) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        <?php endif; ?>

        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Units Widget -->
            <?php if (!isset($visible_widgets['units']) || $visible_widgets['units'] === 'visible'): ?>
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-theme mb-4">Assigned Units</h2>
                    <?php
                    $units_result = isset($conn) && !$conn->connect_error ? $controller->getLecturerAssignments($lecturer_id) : ['success' => false, 'assignments' => []];
                    if ($units_result['success'] && count($units_result['assignments']) > 0):
                    ?>
                        <ul class="space-y-2">
                            <?php foreach (array_slice($units_result['assignments'], 0, 5) as $unit): ?>
                                <li class="flex justify-between items-center">
                                    <span class="text-sm"><?= htmlspecialchars($unit['unit_name']) ?> (<?= htmlspecialchars($unit['academic_year']) ?>)</span>
                                    <a href="attendance.php?assignment_id=<?= $unit['assignment_id'] ?>" class="text-blue-400 hover:underline text-sm">Manage</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="my_units.php" class="mt-4 inline-block text-blue-400 hover:underline text-sm">View All Units</a>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm">No units assigned.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Sessions Widget -->
            <?php if (!isset($visible_widgets['sessions']) || $visible_widgets['sessions'] === 'visible'): ?>
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-theme mb-4">Recent Sessions</h2>
                    <?php
                    $sessions_result = isset($conn) && !$conn->connect_error ? $controller->getClassSessions($lecturer_id, null, 5) : ['success' => false, 'sessions' => []];
                    if ($sessions_result['success'] && count($sessions_result['sessions']) > 0):
                    ?>
                        <ul class="space-y-2">
                            <?php foreach ($sessions_result['sessions'] as $session): ?>
                                <li class="flex justify-between items-center">
                                    <span class="text-sm"><?= htmlspecialchars($session['session_date'] . ' ' . $session['start_time']) ?> (<?= htmlspecialchars($session['venue']) ?>)</span>
                                    <a href="attendance.php?assignment_id=<?= $session['assignment_id'] ?>" class="text-blue-400 hover:underline text-sm">View</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-gray-400 text-sm">No recent sessions.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Notifications Widget -->
            <?php if (!isset($visible_widgets['notifications']) || $visible_widgets['notifications'] === 'visible'): ?>
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-theme mb-4">Notifications</h2>
                    <form id="notification-form" method="POST">
                        <div id="notifications-container" class="space-y-2 max-h-60 overflow-y-auto">
                            <?php if (empty($notifications)): ?>
                                <p class="text-gray-400 text-sm">No notifications.</p>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="flex items-center space-x-2 <?= $notification['is_read'] ? 'opacity-50' : '' ?>" data-notification-id="<?= htmlspecialchars($notification['notification_id']) ?>">
                                        <input type="checkbox" name="notification_ids[]" value="<?= htmlspecialchars($notification['notification_id']) ?>" class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="flex-1 text-sm"><?= htmlspecialchars($notification['message']) ?></span>
                                        <span class="text-sm text-gray-400"><?= date('M d, Y H:i', strtotime($notification['created_at'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 flex space-x-2">
                            <button type="button" id="mark-read-btn" class="bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme text-sm" disabled>
                                Mark Selected as Read
                            </button>
                            <button type="button" id="hide-btn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm" disabled>
                                Hide Selected
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Chart Widget -->
            <?php if (!isset($visible_widgets['chart']) || $visible_widgets['chart'] === 'visible'): ?>
                <div class="bg-gray-800 rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-theme mb-4">Average Attendance (%)</h2>
                    <div class="relative">
                        <canvas id="sessionsChart" class="w-full h-64"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="bg-gray-800 text-center py-4 text-gray-400">
        <p class="text-sm">© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const loadingOverlay = document.getElementById('loading');
            const notificationForm = document.getElementById('notification-form');
            const markReadBtn = document.getElementById('mark-read-btn');
            const hideBtn = document.getElementById('hide-btn');
            const notificationsContainer = document.getElementById('notifications-container');

            // Chart initialization
            const ctx = document.getElementById('sessionsChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_reverse($labels)) ?> || ['2025-01', '2025-02', '2025-03', '2025-04', '2025-05', '2025-06'],
                        datasets: [{
                            label: 'Average Attendance (%)',
                            data: <?= json_encode(array_reverse($data)) ?> || [0, 0, 0, 0, 0, 0],
                            backgroundColor: 'var(--theme-color)',
                            borderColor: 'var(--theme-color)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Percentage (%)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Month'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        }
                    }
                });
            }

            // Notification handling
            if (notificationForm) {
                const checkboxes = notificationForm.querySelectorAll('input[name="notification_ids[]"]');
                const updateButtonStates = () => {
                    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                    markReadBtn.disabled = checkedCount === 0;
                    hideBtn.disabled = checkedCount === 0;
                };
                checkboxes.forEach(cb => cb.addEventListener('change', updateButtonStates));
                updateButtonStates();

                // Perform action (mark read or hide)
                const performAction = (url, successMessage) => {
                    const selectedIds = Array.from(checkboxes)
                        .filter(cb => cb.checked)
                        .map(cb => cb.value);

                    if (selectedIds.length === 0) return;

                    loadingOverlay.classList.remove('hidden');

                    const formData = new URLSearchParams();
                    selectedIds.forEach(id => formData.append('notification_ids[]', id));

                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        loadingOverlay.classList.add('hidden');
                        if (data.success) {
                            fetchNotifications();
                            showSuccess(data.message || successMessage);
                        } else {
                            showError(data.error || 'Operation failed.');
                        }
                    })
                    .catch(error => {
                        loadingOverlay.classList.add('hidden');
                        showError('Error: ' + error.message);
                    });
                };

                // Mark notifications as read
                markReadBtn.addEventListener('click', () => {
                    performAction('mark_notification.php', 'Notifications marked as read.');
                });

                // Hide notifications
                hideBtn.addEventListener('click', () => {
                    if (confirm('Are you sure you want to hide the selected notifications? They will no longer be visible to you.')) {
                        performAction('hide_notifications.php', 'Notifications hidden.');
                    }
                });

                // Fetch notifications
                const fetchNotifications = () => {
                    fetch('fetch_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && notificationsContainer) {
                            notificationsContainer.innerHTML = '';
                            if (data.notifications.length === 0) {
                                notificationsContainer.innerHTML = '<p class="text-gray-400 text-sm">No notifications.</p>';
                            } else {
                                data.notifications.forEach(notification => {
                                    const div = document.createElement('div');
                                    div.className = `flex items-center space-x-2 ${notification.is_read ? 'opacity-50' : ''}`;
                                    div.dataset.notificationId = notification.notification_id;
                                    div.innerHTML = `
                                        <input type="checkbox" name="notification_ids[]" value="${notification.notification_id}" 
                                            class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        <span class="flex-1 text-sm">${notification.message}</span>
                                        <span class="text-sm text-gray-400">${new Date(notification.created_at).toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                                    `;
                                    notificationsContainer.appendChild(div);
                                });
                                // Reattach event listeners to new checkboxes
                                notificationsContainer.querySelectorAll('input[name="notification_ids[]"]').forEach(cb => {
                                    cb.addEventListener('change', updateButtonStates);
                                });
                                updateButtonStates();
                            }
                        } else if (!data.success) {
                            showError(data.error || 'Failed to fetch notifications.');
                        }
                    })
                    .catch(error => {
                        showError('Error fetching notifications: ' + error.message);
                    });
                };

                // Initial fetch and set interval
                fetchNotifications();
                setInterval(fetchNotifications, 60000);
            }

            // Success message
            const showSuccess = (message) => {
                const alert = document.createElement('div');
                alert.className = 'bg-green-600 border border-green-800 text-white px-4 py-3 rounded-lg mb-6 flex justify-between items-center';
                alert.innerHTML = `
                    <span class="text-sm">${message}</span>
                    <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-300" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;
                const main = document.querySelector('main');
                if (main) main.insertBefore(alert, main.firstChild);
            };

            // Error message
            const showError = (message) => {
                const alert = document.createElement('div');
                alert.className = 'bg-red-700 border border-red-400 text-white px-4 py-3 rounded-lg mb-4 flex justify-between items-center';
                alert.innerHTML = `
                    <span class="text-sm">${message}</span>
                    <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                `;
                const existingAlert = document.querySelector('#error-alert');
                if (existingAlert) existingAlert.remove();
                const main = document.querySelector('main');
                if (main) main.insertBefore(alert, main.firstChild);
            };
        });
    </script>
</body>
</html>