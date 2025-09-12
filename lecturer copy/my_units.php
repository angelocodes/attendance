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
    error_log("Database connection failed in my_units.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    $error = "Database connection failed. Please try again later.";
    $units = [];
    $units_error = $error;
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
        $theme_color = '#6c8976';
        error_log("Failed to fetch theme color: " . ($stmt ? $stmt->error : 'Statement preparation failed'), 3, '../logs/errors.log');
    }

    // Pagination
    $units_per_page = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $units_per_page;

    // Fetch units with session counts
    $stmt = $conn->prepare("
        SELECT la.assignment_id, cu.unit_code, cu.unit_name, c.course_name, la.academic_year, la.semester,
               (SELECT COUNT(*) FROM class_sessions cs WHERE cs.unit_id = la.unit_id AND cs.lecturer_id = la.lecturer_id) AS session_count
        FROM lecturer_assignments la
        JOIN course_units cu ON la.unit_id = cu.unit_id
        LEFT JOIN courses c ON cu.course_id = c.course_id
        WHERE la.lecturer_id = ?
        ORDER BY la.academic_year DESC, la.semester DESC, cu.unit_name ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("iii", $lecturer_id, $units_per_page, $offset);
    $stmt->execute();
    $units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get total units for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) FROM lecturer_assignments WHERE lecturer_id = ?");
    $stmt->bind_param("i", $lecturer_id);
    $stmt->execute();
    $stmt->bind_result($total_units);
    $stmt->fetch();
    $stmt->close();
    $total_pages = ceil($total_units / $units_per_page);

    $units_error = '';
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="my_units_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Unit Code', 'Unit Name', 'Course', 'Academic Year', 'Semester', 'Sessions']);
    foreach ($units as $unit) {
        fputcsv($output, [
            $unit['unit_code'],
            $unit['unit_name'],
            $unit['course_name'] ?? 'N/A',
            $unit['academic_year'],
            $unit['semester'],
            $unit['session_count']
        ]);
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Units - SUNATT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --theme-color: <?= htmlspecialchars($theme_color ?? '#6c8976') ?>;
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
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-theme" role="heading" aria-level="1">My Assigned Units</h1>
            <div class="flex space-x-2">
                <button id="refresh-btn" class="bg-theme text-white px-4 py-2 rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme">
                    Refresh Units
                </button>
                <a href="?export=csv" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-theme focus:outline-none focus:ring-2 focus:ring-theme">
                    Export to CSV
                </a>
            </div>
        </div>

        <div id="loading" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50">
            <svg class="animate-spin h-8 w-8 text-theme" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <?php if ($units_error): ?>
            <div id="error-alert" class="bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span><?= htmlspecialchars($units_error) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        <?php endif; ?>

        <section class="bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="mb-4">
                <label for="search" class="block text-sm font-semibold mb-2">Search Units</label>
                <input type="text" id="search" placeholder="Search by unit code, name, or academic year"
                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-theme"
                       aria-describedby="search-help">
                <p id="search-help" class="text-sm text-gray-400 mt-1">Enter keywords to filter units.</p>
            </div>

            <?php if (count($units) === 0): ?>
                <p class="text-center text-gray-400">You have no assigned units currently.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700" aria-label="Assigned Units Table">
                        <thead class="bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Unit Code</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Unit Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Course</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Academic Year</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Semester</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Sessions</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="units-table" class="divide-y divide-gray-600">
                            <?php foreach ($units as $unit): ?>
                                <tr class="hover:bg-gray-600" data-unit-code="<?= htmlspecialchars($unit['unit_code']) ?>" 
                                    data-unit-name="<?= htmlspecialchars($unit['unit_name']) ?>" 
                                    data-academic-year="<?= htmlspecialchars($unit['academic_year']) ?>">
                                    <td class="px-4 py-2 text-sm"><?= htmlspecialchars($unit['unit_code']) ?></td>
                                    <td class="px-4 py-2 text-sm"><?= htmlspecialchars($unit['unit_name']) ?></td>
                                    <td class="px-4 py-2 text-sm"><?= htmlspecialchars($unit['course_name'] ?? 'N/A') ?></td>
                                    <td class="px-4 py-2 text-sm"><?= htmlspecialchars($unit['academic_year']) ?></td>
                                    <td class="px-4 py-2 text-sm"><?= htmlspecialchars($unit['semester']) ?></td>
                                    <td class="px-4 py-2 text-sm"><?= $unit['session_count'] ?></td>
                                    <td class="px-4 py-2 text-sm flex space-x-2">
                                        <a href="schedule_session.php?assignment_id=<?= $unit['assignment_id'] ?>" 
                                           class="text-theme hover:underline text-sm" 
                                           aria-label="Schedule session for <?= htmlspecialchars($unit['unit_name']) ?>">Schedule</a>
                                        <a href="attendance.php?assignment_id=<?= $unit['assignment_id'] ?>" 
                                           class="text-theme hover:underline text-sm" 
                                           aria-label="Manage attendance for <?= htmlspecialchars($unit['unit_name']) ?>">View Sessions</a>
                                        <button class="view-students-btn text-theme hover:underline text-sm" 
                                                data-assignment-id="<?= $unit['assignment_id'] ?>" 
                                                data-unit-name="<?= htmlspecialchars($unit['unit_name']) ?>" 
                                                aria-label="View students for <?= htmlspecialchars($unit['unit_name']) ?>">View Students</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4 flex justify-between items-center" aria-label="Pagination">
                        <div>
                            <span class="text-sm text-gray-400">
                                Showing <?= count($units) ?> of <?= $total_units ?> units
                            </span>
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-700 rounded hover:bg-theme text-sm" aria-label="Previous page">Previous</a>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?= $i ?>" class="px-3 py-1 rounded text-sm <?= $i === $page ? 'bg-theme text-white' : 'bg-gray-700 hover:bg-theme' ?>" aria-label="Page <?= $i ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-700 rounded hover:bg-theme text-sm" aria-label="Next page">Next</a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <div id="students-modal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50" role="dialog" aria-labelledby="modal-title" aria-modal="true">
            <div class="bg-gray-800 rounded-lg shadow-lg max-w-2xl w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 id="modal-title" class="text-xl font-semibold text-theme">Students for <span id="modal-unit-name"></span></h2>
                    <button id="close-modal-btn" class="text-gray-400 hover:text-white" aria-label="Close modal">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div id="modal-loading" class="hidden flex justify-center items-center h-32">
                    <svg class="animate-spin h-8 w-8 text-theme" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
                <div id="modal-content">
                    <div id="modal-error" class="hidden bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4" role="alert"></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-700" aria-label="Enrolled Students Table">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Reg No.</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Username</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-300">Email</th>
                                </tr>
                            </thead>
                            <tbody id="students-table" class="divide-y divide-gray-600"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-center py-4 text-gray-400">
        <p class="text-sm">© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('search');
            const rows = document.querySelectorAll('#units-table tr');
            const refreshBtn = document.getElementById('refresh-btn');
            const loadingOverlay = document.getElementById('loading');
            const modal = document.getElementById('students-modal');
            const modalUnitName = document.getElementById('modal-unit-name');
            const modalContent = document.getElementById('modal-content');
            const modalLoading = document.getElementById('modal-loading');
            const modalError = document.getElementById('modal-error');
            const studentsTable = document.getElementById('students-table');
            const closeModalBtn = document.getElementById('close-modal-btn');
            const viewStudentsBtns = document.querySelectorAll('.view-students-btn');

            // Search functionality
            searchInput.addEventListener('input', () => {
                const query = searchInput.value.toLowerCase();
                rows.forEach(row => {
                    const unitCode = row.dataset.unitCode.toLowerCase();
                    const unitName = row.dataset.unitName.toLowerCase();
                    const academicYear = row.dataset.academicYear.toLowerCase();
                    row.style.display = unitCode.includes(query) || unitName.includes(query) || academicYear.includes(query) ? '' : 'none';
                });
            });

            // Refresh button
            refreshBtn.addEventListener('click', () => {
                loadingOverlay.classList.remove('hidden');
                window.location.href = window.location.pathname;
            });

            // Modal handling
            const openModal = (assignmentId, unitName) => {
                modalUnitName.textContent = unitName;
                modal.classList.remove('hidden');
                modalLoading.classList.remove('hidden');
                modalContent.classList.add('hidden');
                modalError.classList.add('hidden');
                studentsTable.innerHTML = '';

                const formData = new URLSearchParams();
                formData.append('assignment_id', assignmentId);

                fetch('get_enrolled_students.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    modalLoading.classList.add('hidden');
                    modalContent.classList.remove('hidden');
                    if (data.success) {
                        if (data.students.length === 0) {
                            studentsTable.innerHTML = '<tr><td colspan="3" class="px-4 py-3 text-center text-gray-400">No students enrolled.</td></tr>';
                        } else {
                            data.students.forEach(student => {
                                const row = document.createElement('tr');
                                row.classList.add('hover:bg-gray-600');
                                row.innerHTML = `
                                    <td class="px-4 py-3 text-sm">${student.registration_number}</td>
                                    <td class="px-4 py-3 text-sm">${student.username}</td>
                                    <td class="px-4 py-3 text-sm">${student.email}</td>
                                `;
                                studentsTable.appendChild(row);
                            });
                        }
                    } else {
                        modalError.textContent = data.error || 'Failed to load students.';
                        modalError.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    modalLoading.classList.add('hidden');
                    modalContent.classList.remove('hidden');
                    modalError.textContent = `Failed to load students: ${error.message}`;
                    modalError.classList.remove('hidden');
                    console.error('Fetch error:', error);
                });
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                studentsTable.innerHTML = '';
                modalError.classList.add('hidden');
            };

            viewStudentsBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const assignmentId = btn.dataset.assignmentId;
                    const unitName = btn.dataset.unitName;
                    openModal(assignmentId, unitName);
                });
            });

            closeModalBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
            });
        });
    </script>
</body>
</html>