<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../db.php';
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
    error_log("Database connection failed in notifications.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    $error = "Database connection failed.";
    $theme_color = '#6c757d';
    $notifications = [];
} else {
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

    // Initial notifications fetch (server-side for first load)
    $notifications_limit = NOTIFICATIONS_LIMIT;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $notifications_limit;

    $stmt = $conn->prepare("
        SELECT n.notification_id, n.message, n.created_at, n.is_read
        FROM notifications n
        LEFT JOIN notification_preferences np ON n.notification_id = np.notification_id AND np.user_id = ?
        WHERE (n.user_id = ? OR n.user_id IS NULL) AND n.user_type = 'lecturer'
        AND (np.is_hidden IS NULL OR np.is_hidden = 0)
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iiii", $lecturer_id, $lecturer_id, $notifications_limit, $offset);
    if ($stmt->execute()) {
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $notifications = [];
        error_log("Failed to fetch notifications: " . $stmt->error, 3, '../logs/errors.log');
    }

    // Count total notifications for pagination
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM notifications n
        LEFT JOIN notification_preferences np ON n.notification_id = np.notification_id AND np.user_id = ?
        WHERE (n.user_id = ? OR n.user_id IS NULL) AND n.user_type = 'lecturer'
        AND (np.is_hidden IS NULL OR np.is_hidden = 0)
    ");
    $stmt->bind_param("ii", $lecturer_id, $lecturer_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result()->fetch_assoc();
        $total_notifications = $result['total'];
        $stmt->close();
    } else {
        $total_notifications = 0;
        error_log("Failed to count notifications: " . $stmt->error, 3, '../logs/errors.log');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --theme-color: <?= htmlspecialchars($theme_color ?? '#6c757d') ?>;
        }
        .bg-theme { background-color: var(--theme-color); }
        .text-theme { color: var(--theme-color); }
        .hover\:bg-theme:hover { background-color: var(--theme-color); }
        .focus\:ring-theme:focus { --tw-ring-color: var(--theme-color); }
        .border-theme { border-color: var(--theme-color); }
        #notifications-table-container::-webkit-scrollbar {
            height: 8px;
        }
        #notifications-table-container::-webkit-scrollbar-track {
            background: #2d3748;
        }
        #notifications-table-container::-webkit-scrollbar-thumb {
            background: var(--theme-color);
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">
    <?php include 'lecturer_navbar.php'; ?>

    <main class="container mx-auto p-6 flex-grow">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-theme" role="heading" aria-level="1">Notifications</h1>
            <div class="flex space-x-4">
                <button id="mark-read-btn" class="bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme text-sm" disabled>
                    Mark Selected as Read
                </button>
                <button id="mark-unread-btn" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme text-sm" disabled>
                    Mark Selected as Unread
                </button>
                <button id="hide-btn" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 text-sm" disabled>
                    Hide Selected
                </button>
            </div>
        </div>

        <div id="loading" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50">
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

        <!-- Filters -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <form id="filter-form" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <label for="status-filter" class="block text-sm font-medium text-gray-300">Status</label>
                    <select id="status-filter" name="status" class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md text-white text-sm focus:ring-theme focus:border-theme">
                        <option value="all">All</option>
                        <option value="read">Read</option>
                        <option value="unread">Unread</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label for="date-from" class="block text-sm font-medium text-gray-300">From Date</label>
                    <input type="date" id="date-from" name="date_from" class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md text-white text-sm focus:ring-theme focus:border-theme">
                </div>
                <div class="flex-1">
                    <label for="date-to" class="block text-sm font-medium text-gray-300">To Date</label>
                    <input type="date" id="date-to" name="date_to" class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md text-white text-sm focus:ring-theme focus:border-theme">
                </div>
                <div class="flex-1">
                    <label for="search" class="block text-sm font-medium text-gray-300">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search notifications..." class="mt-1 block w-full bg-gray-700 border-gray-600 rounded-md text-white text-sm focus:ring-theme focus:border-theme">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme text-sm">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Notifications Table -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6">
            <form id="notifications-form">
                <div id="notifications-table-container" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700" aria-label="Notifications table">
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all" class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                </th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Message</th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody id="notifications-tbody" class="divide-y divide-gray-700">
                            <?php if (empty($notifications)): ?>
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-sm text-gray-400 text-center">No notifications found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr class="<?= $notification['is_read'] ? 'opacity-50' : '' ?>" data-notification-id="<?= $notification['notification_id'] ?>">
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <input type="checkbox" name="notification_ids[]" value="<?= $notification['notification_id'] ?>" class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm"><?= htmlspecialchars($notification['message']) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-400"><?= date('M d, Y H:i', strtotime($notification['created_at'])) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm"><?= $notification['is_read'] ? 'Read' : 'Unread' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Pagination -->
            <?php if ($total_notifications > $notifications_limit): ?>
                <div class="mt-4 flex justify-between items-center">
                    <p class="text-sm text-gray-400">
                        Showing <?= ($offset + 1) ?> to <?= min($offset + $notifications_limit, $total_notifications) ?> of <?= $total_notifications ?> notifications
                    </p>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-700 rounded text-sm hover:bg-gray-600">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= ceil($total_notifications / $notifications_limit); $i++): ?>
                            <a href="?page=<?= $i ?>" class="px-3 py-1 rounded text-sm <?= $i === $page ? 'bg-theme text-white' : 'bg-gray-700 hover:bg-gray-600' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($page < ceil($total_notifications / $notifications_limit)): ?>
                            <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-700 rounded text-sm hover:bg-gray-600">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-gray-800 text-center py-4 text-gray-400">
        <p class="text-sm">© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const loadingOverlay = document.getElementById('loading');
            const notificationsForm = document.getElementById('notifications-form');
            const notificationsTbody = document.getElementById('notifications-tbody');
            const selectAllCheckbox = document.getElementById('select-all');
            const markReadBtn = document.getElementById('mark-read-btn');
            const markUnreadBtn = document.getElementById('mark-unread-btn');
            const hideBtn = document.getElementById('hide-btn');
            const filterForm = document.getElementById('filter-form');

            // Update button states
            const updateButtonStates = () => {
                const checkboxes = notificationsForm.querySelectorAll('input[name="notification_ids[]"]');
                const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                markReadBtn.disabled = checkedCount === 0;
                markUnreadBtn.disabled = checkedCount === 0;
                hideBtn.disabled = checkedCount === 0;
                selectAllCheckbox.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
            };

            // Attach checkbox listeners
            const attachCheckboxListeners = () => {
                notificationsForm.querySelectorAll('input[name="notification_ids[]"]').forEach(cb => {
                    cb.addEventListener('change', updateButtonStates);
                });
            };
            attachCheckboxListeners();

            // Select all checkbox
            selectAllCheckbox.addEventListener('change', () => {
                notificationsForm.querySelectorAll('input[name="notification_ids[]"]').forEach(cb => {
                    cb.checked = selectAllCheckbox.checked;
                });
                updateButtonStates();
            });

            // Perform action (mark read, mark unread, hide)
            const performAction = (url, successMessage) => {
                const selectedIds = Array.from(notificationsForm.querySelectorAll('input[name="notification_ids[]"]:checked')).map(cb => cb.value);
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
                        fetchNotifications(); // Refresh notifications
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

            // Mark as read
            markReadBtn.addEventListener('click', () => {
                performAction('mark_notification.php', 'Notifications marked as read.');
            });

            // Mark as unread
            markUnreadBtn.addEventListener('click', () => {
                performAction('mark_notifications_unread.php', 'Notifications marked as unread.');
            });

            // Hide notifications
            hideBtn.addEventListener('click', () => {
                if (confirm('Are you sure you want to hide the selected notifications? They will no longer be visible to you.')) {
                    performAction('hide_notifications.php', 'Notifications hidden.');
                }
            });

            // Fetch notifications with filters
            const fetchNotifications = () => {
                const formData = new FormData(filterForm);
                formData.append('page', '<?= $page ?>');
                const params = new URLSearchParams(formData).toString();

                fetch(`fetch_notifications.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificationsTbody.innerHTML = '';
                        if (data.notifications.length === 0) {
                            notificationsTbody.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-sm text-gray-400 text-center">No notifications found.</td></tr>';
                        } else {
                            data.notifications.forEach(notification => {
                                const tr = document.createElement('tr');
                                tr.className = notification.is_read ? 'opacity-50' : '';
                                tr.dataset.notificationId = notification.notification_id;
                                tr.innerHTML = `
                                    <td class="px-3 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="notification_ids[]" value="${notification.notification_id}" class="h-4 w-4 text-theme bg-gray-700 border-gray-600 rounded focus:ring-theme">
                                    </td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm">${notification.message}</td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-400">${new Date(notification.created_at).toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                                    <td class="px-3 py-4 whitespace-nowrap text-sm">${notification.is_read ? 'Read' : 'Unread'}</td>
                                `;
                                notificationsTbody.appendChild(tr);
                            });
                            attachCheckboxListeners();
                            updateButtonStates();
                        }
                        // Update pagination
                        const paginationContainer = notificationsTbody.parentElement.parentElement.nextElementSibling;
                        if (paginationContainer && data.total_notifications) {
                            const total = data.total_notifications;
                            const pages = Math.ceil(total / <?= $notifications_limit ?>);
                            let paginationHtml = `<p class="text-sm text-gray-400">Showing ${Math.min(<?= $offset + 1 ?>, total)} to ${Math.min(<?= $offset + $notifications_limit ?>, total)} of ${total} notifications</p>`;
                            paginationHtml += '<div class="flex space-x-2">';
                            if (<?= $page ?> > 1) {
                                paginationHtml += `<a href="?page=<?= $page - 1 ?>&${params}" class="px-3 py-1 bg-gray-700 rounded text-sm hover:bg-gray-600">Previous</a>`;
                            }
                            for (let i = 1; i <= pages; i++) {
                                paginationHtml += `<a href="?page=${i}&${params}" class="px-3 py-1 rounded text-sm ${i === <?= $page ?> ? 'bg-theme text-white' : 'bg-gray-700 hover:bg-gray-600'}">${i}</a>`;
                            }
                            if (<?= $page ?> < pages) {
                                paginationHtml += `<a href="?page=<?= $page + 1 ?>&${params}" class="px-3 py-1 bg-gray-700 rounded text-sm hover:bg-gray-600">Next</a>`;
                            }
                            paginationHtml += '</div>';
                            paginationContainer.innerHTML = paginationHtml;
                        }
                    } else {
                        showError(data.error || 'Failed to fetch notifications.');
                    }
                })
                .catch(error => {
                    showError('Error fetching notifications: ' + error.message);
                });
            };

            // Filter form submission
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                fetchNotifications();
            });

            // Periodically fetch notifications (every 30 seconds)
            setInterval(fetchNotifications, 60000);

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
                alert.className = 'bg-red-600 border border-red-800 text-white px-4 py-3 rounded-lg mb-6 flex justify-between items-center';
                alert.innerHTML = `
                    <span class="text-sm">${message}</span>
                    <button onclick="this.parentElement.remove()" class="text-white hover:bg-gray-800 p-1 rounded" aria-label="Close">
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