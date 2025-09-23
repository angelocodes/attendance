<?php
include '../db.php';
include 'admin_navbar.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('USERS_PER_PAGE', 20);

function redirect($url) {
    header("Location: $url");
    exit;
}

function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone($phone) {
    return preg_match('/^\+?\d{7,15}$/', $phone);
}

function valid_password($password) {
    return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/\d/', $password);
}

// Fetch courses and schools for dropdowns
$courses = $conn->query("SELECT course_id, course_name FROM courses WHERE course_id IN (SELECT course_id FROM courses WHERE school_id IN (SELECT school_id FROM schools)) ORDER BY course_name");
$schools = $conn->query("SELECT school_id, school_name FROM schools ORDER BY school_name");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add']) && !empty($_POST['first_name'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone_number']);
    $user_type = $_POST['user_type'];
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
    $face_encoding = isset($_POST['face_encoding']) ? trim($_POST['face_encoding']) : '';

    if (!$first_name) $errors[] = "First name is required.";
    if (!$username) $errors[] = "Username is required.";
    if (!$password || !valid_password($password)) $errors[] = "Password must be at least 8 characters with letters and numbers.";
    if ($email && !valid_email($email)) $errors[] = "Invalid email format.";
    if ($phone && !valid_phone($phone)) $errors[] = "Invalid phone number format.";
    if (!$user_type) $errors[] = "User type is required.";
    if ($user_type === 'student' && !$course_id) $errors[] = "Course selection is required for students.";
    if ($user_type === 'lecturer' && !$school_id) $errors[] = "School selection is required for lecturers.";

    if ($user_type === 'student') {
        if (empty($face_encoding)) {
            $errors[] = "Face encoding is required for students.";
        } else {
            $decoded_encodings = json_decode($face_encoding, true);
            if (!is_array($decoded_encodings) || empty($decoded_encodings)) {
                $errors[] = "Invalid face encoding data.";
            } else {
                foreach ($decoded_encodings as $index => $encoding) {
                    if (!is_array($encoding) || count($encoding) !== 128 || !array_reduce($encoding, function($carry, $val) { return $carry && is_numeric($val); }, true)) {
                        $errors[] = "Invalid face encoding at position ";
                    } else {
                        $is_zero = array_reduce($encoding, function($carry, $val) { return $carry && ($val == 0); }, true);
                        if ($is_zero) {
                            $errors[] = "Face encoding at position " . ($index + 1) . " is all zeros, indicating a detection failure.";
                        }
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, phone_number, user_type, status, created_at, face_encoding) VALUES (?, ?, ?, ?, ?, 'active', NOW(), ?)");
        $face_encoding = $user_type === 'student' ? $face_encoding : null;
        $stmt->bind_param("ssssss", $username, $hashed_password, $email, $phone, $user_type, $face_encoding);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            if ($user_type === 'student') {
                $reg_no = 'REG' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                $stmt2 = $conn->prepare("INSERT INTO students (student_id, registration_number, first_name, last_name, course_id, year_of_study, intake_month, intake_year, status, school_id) VALUES (?, ?, ?, ?, ?, 1, 'January', YEAR(NOW()), 'active', (SELECT school_id FROM courses WHERE course_id = ?))");
                $stmt2->bind_param("isssii", $user_id, $reg_no, $first_name, $last_name, $course_id, $course_id);
                $stmt2->execute();
            } elseif ($user_type === 'lecturer') {
                $staff_no = 'STAFF' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                $stmt2 = $conn->prepare("INSERT INTO lecturers (lecturer_id, staff_number, first_name, last_name, rank, department_id, school_id) VALUES (?, ?, ?, ?, 'Lecturer', 1, ?)");
                $stmt2->bind_param("isssi", $user_id, $staff_no, $first_name, $last_name, $school_id);
                $stmt2->execute();
            } elseif ($user_type === 'admin') {
                $stmt2 = $conn->prepare("INSERT INTO admins (admin_id, first_name, last_name, role) VALUES (?, ?, ?, 'System Administrator')");
                $stmt2->bind_param("iss", $user_id, $first_name, $last_name);
                $stmt2->execute();
            }
            redirect("manage_users.php");
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
    }
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("UPDATE users SET status = 'deleted' WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    redirect("manage_users.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $user_id = intval($_POST['user_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone_number']);
    $user_type = $_POST['user_type'];
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $school_id = isset($_POST['school_id']) ? intval($_POST['school_id']) : 0;
    $face_encoding = isset($_POST['face_encoding']) ? trim($_POST['face_encoding']) : '';

    if (!$first_name) $errors[] = "First name is required.";
    if (!$username) $errors[] = "Username is required.";
    if ($email && !valid_email($email)) $errors[] = "Invalid email format.";
    if ($phone && !valid_phone($phone)) $errors[] = "Invalid phone number format.";
    if (!$user_type) $errors[] = "User type is required.";
    if ($user_type === 'student' && !$course_id) $errors[] = "Course selection is required for students.";
    if ($user_type === 'lecturer' && !$school_id) $errors[] = "School selection is required for lecturers.";

    if ($user_type === 'student' && !empty($face_encoding)) {
        $decoded_encodings = json_decode($face_encoding, true);
        if (!is_array($decoded_encodings) || empty($decoded_encodings)) {
            $errors[] = "Invalid face encoding data.";
        } else {
            foreach ($decoded_encodings as $index => $encoding) {
                if (!is_array($encoding) || count($encoding) !== 128 || !array_reduce($encoding, function($carry, $val) { return $carry && is_numeric($val); }, true)) {
                    $errors[] = "Invalid face encoding at position " . ($index + 1) . ".";
                } else {
                    $is_zero = array_reduce($encoding, function($carry, $val) { return $carry && ($val == 0); }, true);
                    if ($is_zero) {
                        $errors[] = "Face encoding at position " . ($index + 1) . " is all zeros, indicating a detection failure.";
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE users SET username=?, email=?, phone_number=?, user_type=?";
        $params = [$username, $email, $phone, $user_type];
        $types = "ssss";
        if ($user_type === 'student' && !empty($face_encoding)) {
            $sql .= ", face_encoding=?";
            $params[] = $face_encoding;
            $types .= "s";
        } elseif ($user_type !== 'student') {
            $sql .= ", face_encoding=NULL";
        }
        $sql .= " WHERE user_id=?";
        $params[] = $user_id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            if ($user_type === 'student') {
                $check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $check->store_result();
                if ($check->num_rows === 0) {
                    $reg_no = 'REG' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                    $stmt2 = $conn->prepare("INSERT INTO students (student_id, registration_number, first_name, last_name, course_id, year_of_study, intake_month, intake_year, status, school_id) VALUES (?, ?, ?, ?, ?, 1, 'January', YEAR(NOW()), 'active', (SELECT school_id FROM courses WHERE course_id = ?))");
                    $stmt2->bind_param("isssii", $user_id, $reg_no, $first_name, $last_name, $course_id, $course_id);
                    $stmt2->execute();
                } else {
                    $stmt2 = $conn->prepare("UPDATE students SET first_name=?, last_name=?, course_id=?, school_id=(SELECT school_id FROM courses WHERE course_id = ?) WHERE student_id=?");
                    $stmt2->bind_param("ssiii", $first_name, $last_name, $course_id, $course_id, $user_id);
                    $stmt2->execute();
                }
            } elseif ($user_type === 'lecturer') {
                $check = $conn->prepare("SELECT lecturer_id FROM lecturers WHERE lecturer_id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $check->store_result();
                if ($check->num_rows === 0) {
                    $staff_no = 'STAFF' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                    $stmt2 = $conn->prepare("INSERT INTO lecturers (lecturer_id, staff_number, first_name, last_name, rank, department_id, school_id) VALUES (?, ?, ?, ?, 'Lecturer', 1, ?)");
                    $stmt2->bind_param("isssi", $user_id, $staff_no, $first_name, $last_name, $school_id);
                    $stmt2->execute();
                } else {
                    $stmt2 = $conn->prepare("UPDATE lecturers SET first_name=?, last_name=?, school_id=? WHERE lecturer_id=?");
                    $stmt2->bind_param("ssii", $first_name, $last_name, $school_id, $user_id);
                    $stmt2->execute();
                }
            } elseif ($user_type === 'admin') {
                $check = $conn->prepare("SELECT admin_id FROM admins WHERE admin_id = ?");
                $check->bind_param("i", $user_id);
                $check->execute();
                $check->store_result();
                if ($check->num_rows === 0) {
                    $stmt2 = $conn->prepare("INSERT INTO admins (admin_id, first_name, last_name, role) VALUES (?, ?, ?, 'System Administrator')");
                    $stmt2->bind_param("iss", $user_id, $first_name, $last_name);
                    $stmt2->execute();
                } else {
                    $stmt2 = $conn->prepare("UPDATE admins SET first_name=?, last_name=? WHERE admin_id=?");
                    $stmt2->bind_param("ssi", $first_name, $last_name, $user_id);
                    $stmt2->execute();
                }
            }
            redirect("manage_users.php");
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
    }
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$offset = ($page - 1) * USERS_PER_PAGE;

$sql_where = "WHERE u.status = 'active'";
$params = [];
$param_types = "";

if ($search !== "") {
    $sql_where .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ? OR u.user_type LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR l.first_name LIKE ? OR l.last_name LIKE ? OR a.first_name LIKE ? OR a.last_name LIKE ?)";
    $like_search = "%$search%";
    $params = array_fill(0, 10, $like_search);
    $param_types = str_repeat("s", 10);
}

$count_sql = "
    SELECT COUNT(*) 
    FROM users u
    LEFT JOIN students s ON u.user_id = s.student_id AND u.user_type = 'student'
    LEFT JOIN lecturers l ON u.user_id = l.lecturer_id AND u.user_type = 'lecturer'
    LEFT JOIN admins a ON u.user_id = a.admin_id AND u.user_type = 'admin'
    $sql_where";
$stmt = $conn->prepare($count_sql);
if ($search !== "") {
    $bind_params = [];
    $bind_params[] = &$param_types;
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
}
$stmt->execute();
$stmt->bind_result($total_users);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_users / USERS_PER_PAGE);

$sql = "
    SELECT 
        u.user_id, 
        u.username, 
        u.email, 
        u.phone_number, 
        u.user_type, 
        COALESCE(s.first_name, l.first_name, a.first_name, '') AS first_name,
        COALESCE(s.last_name, l.last_name, a.last_name, '') AS last_name,
        u.face_encoding,
        s.course_id,
        l.school_id
    FROM users u
    LEFT JOIN students s ON u.user_id = s.student_id AND u.user_type = 'student'
    LEFT JOIN lecturers l ON u.user_id = l.lecturer_id AND u.user_type = 'lecturer'
    LEFT JOIN admins a ON u.user_id = a.admin_id AND u.user_type = 'admin'
    $sql_where 
    ORDER BY u.created_at ASC
    LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$offset_ref = $offset;
$limit_ref = USERS_PER_PAGE;
if ($search !== "") {
    $param_types .= "ii";
    $params[] = $offset;
    $params[] = USERS_PER_PAGE;
    $bind_params = [];
    $bind_params[] = &$param_types;
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
} else {
    $stmt->bind_param("ii", $offset_ref, $limit_ref);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="../face-api.min.js"></script>
    <style>
        .modal-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            color: #333;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .capture-video {
            max-width: 100%;
            border: 2px solid #f44336;
            border-radius: 6px;
            position: relative;
        }
        .capture-canvas {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #f44336;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 p-8 min-h-screen">
    <h1 class="text-3xl font-bold mb-6 text-red-600">Manage Users</h1>

    <form method="GET" class="mb-6 flex gap-2 max-w-md">
        <input type="text" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>"
            class="flex-grow p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500" />
        <button type="submit" class="bg-red-600 text-white font-bold px-4 rounded hover:bg-red-500">Search</button>
    </form>

    <div class="mb-10 bg-white p-6 rounded shadow max-w-lg">
        <h2 class="text-xl font-semibold mb-4 text-red-600">Add New User</h2>

        <?php if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])): ?>
            <div class="mb-4 bg-red-100 border border-red-500 text-red-700 p-3 rounded">
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateAddForm();" class="space-y-4" novalidate>
            <div>
                <label for="first_name" class="block mb-1 font-medium">First Name <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" id="first_name" required
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label for="last_name" class="block mb-1 font-medium">Last Name</label>
                <input type="text" name="last_name" id="last_name"
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label for="username" class="block mb-1 font-medium">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" id="username" required
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label for="password" class="block mb-1 font-medium">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" id="password" required
                    placeholder="min 8 chars, letters & numbers"
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label for="email" class="block mb-1 font-medium">Email</label>
                <input type="email" name="email" id="email"
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label for="phone_number" class="block mb-1 font-medium">Phone Number</label>
                <input type="text" name="phone_number" id="phone_number" placeholder="+1234567890"
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <div>
                <label for="user_type" class="block mb-1 font-medium">User Type <span class="text-red-500">*</span></label>
                <select name="user_type" id="user_type" required
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">-- Select user type --</option>
                    <option value="admin">Admin</option>
                    <option value="lecturer">Lecturer</option>
                    <option value="student">Student</option>
                </select>
            </div>
            <div id="course-container" style="display:none;">
                <label for="course_id" class="block mb-1 font-medium">Course <span class="text-red-500">*</span></label>
                <select name="course_id" id="course_id"
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">-- Select course --</option>
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <option value="<?= $course['course_id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                    <?php endwhile; $courses->data_seek(0); ?>
                </select>
            </div>
            <div id="school-container" style="display:none;">
                <label for="school_id" class="block mb-1 font-medium">School <span class="text-red-500">*</span></label>
                <select name="school_id" id="school_id"
                    class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <option value="">-- Select school --</option>
                    <?php while ($school = $schools->fetch_assoc()): ?>
                        <option value="<?= $school['school_id'] ?>"><?= htmlspecialchars($school['school_name']) ?></option>
                    <?php endwhile; $schools->data_seek(0); ?>
                </select>
            </div>
            <div id="face-capture-container" style="display:none;">
                <label class="block mb-2 font-medium">Capture Face (Required for Students)</label>
                <p class="text-sm text-gray-600 mb-2">Ensure bright lighting, face centered, and occupies 50-70% of frame.</p>
                <div style="position: relative;">
                    <video id="capture-video" autoplay muted class="mb-2 capture-video"></video>
                    <canvas id="capture-canvas" class="capture-canvas"></canvas>
                </div>
                <button type="button" id="capture-face-btn" class="bg-blue-600 text-white font-semibold px-4 py-2 rounded hover:bg-blue-500">Capture Face</button>
                <div id="capture-status" class="mt-2 text-sm"></div>
                <input type="hidden" name="face_encoding" id="face-encoding" />
            </div>
            <button type="submit" name="add" class="bg-blue-600 text-white font-semibold px-6 py-2 rounded-lg hover:bg-blue-500">Add User</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white rounded-lg shadow divide-y">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-blue-600 font-semibold">Name</th>
                    <th class="px-4 py-3 text-left text-blue-600 font-semibold">Username</th>
                    <th class="px-4 py-3 text-left text-blue-600 font-semibold">Email</th>
                    <th class="px-4 py-3 text-left text-blue-600 font-semibold">Phone</th>
                    <th class="px-4 py-3 text-left text-blue-600 font-semibold">User Type</th>
                    <th class="px-4 py-3 text-left text-blue-600 font-semibold">Face Encoding</th>
                    <th class="px-4 py-3 text-left text-blue-600 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php while ($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="px-4 py-3"><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?: 'N/A' ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($user['username']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($user['email']) ?: 'N/A' ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($user['phone_number']) ?: 'N/A' ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($user['user_type']) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($user['user_type'] === 'student' && $user['face_encoding']): ?>
                                <span class="text-green-600">Captured</span>
                            <?php else: ?>
                                <span class="text-gray-500">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center space-x-2">
                            <button onclick="showEditModal(<?= $user['user_id'] ?>)"
                                class="bg-blue-600 text-white font-semibold px-3 py-1 rounded hover:bg-blue-500">Edit</button>
                            <a href="?delete=<?= $user['user_id'] ?>" 
                               onclick="return confirm('Confirm delete user <?= htmlspecialchars($user['username']) ?>?');"
                               class="bg-red-600 text-white font-semibold px-3 py-1 rounded hover:bg-red-500">Delete</a>
                        </td>
                    </tr>
                    <div id="edit-modal-<?= $user['user_id'] ?>" class="modal-bg">
                        <div class="modal-content">
                            <button onclick="hideEditModal(<?= $user['user_id'] ?>)"
                                class="absolute top-2 right-3 text-red-600 font-bold text-xl hover:text-red-500">×</button>
                            <h3 class="text-xl font-semibold mb-4 text-blue-600">Edit User: <?= htmlspecialchars($user['username']) ?></h3>
                            <form method="POST" onsubmit="return validateEditForm(this);" class="space-y-4" novalidate>
                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>" />
                                <div>
                                    <label for="first_name-<?= $user['user_id'] ?>" class="block mb-1 font-medium">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="first_name" id="first_name-<?= $user['user_id'] ?>" value="<?= htmlspecialchars($user['first_name']) ?>" required
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label for="last_name-<?= $user['user_id'] ?>" class="block mb-1 font-medium">Last Name</label>
                                    <input type="text" name="last_name" id="last_name-<?= $user['user_id'] ?>" value="<?= htmlspecialchars($user['last_name']) ?>"
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label for="username-<?= $user['user_id'] ?>" class="block mb-1 font-medium">Username <span class="text-red-500">*</span></label>
                                    <input type="text" name="username" id="username-<?= $user['user_id'] ?>" value="<?= htmlspecialchars($user['username']) ?>" required
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label for="email-<?= $user['user_id'] ?>" class="block mb-1 font-medium">Email</label>
                                    <input type="email" name="email" id="email-<?= $user['user_id'] ?>" value="<?= htmlspecialchars($user['email']) ?>"
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label for="phone_number-<?= $user['user_id'] ?>" class="block mb-1 font-medium">Phone Number</label>
                                    <input type="text" name="phone_number" id="phone_number-<?= $user['user_id'] ?>" value="<?= htmlspecialchars($user['phone_number']) ?>"
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label for="user_type-<?= $user['user_id'] ?>" class="block mb-1 font-medium">User Type <span class="text-red-500">*</span></label>
                                    <select name="user_type" id="user_type-<?= $user['user_id'] ?>" required
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="admin" <?= $user['user_type'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="lecturer" <?= $user['user_type'] === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                                        <option value="student" <?= $user['user_type'] === 'student' ? 'selected' : '' ?>>Student</option>
                                    </select>
                                </div>
                                <div class="course-container" id="course-container-<?= $user['user_id'] ?>" style="display: <?= $user['user_type'] === 'student' ? 'block' : 'none' ?>;">
                                    <label for="course_id-<?= $user['user_id'] ?>" class="block mb-1 font-medium">Course <span class="text-red-500">*</span></label>
                                    <select name="course_id" id="course_id-<?= $user['user_id'] ?>"
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">-- Select course --</option>
                                        <?php while ($course = $courses->fetch_assoc()): ?>
                                            <option value="<?= $course['course_id'] ?>" <?= $user['course_id'] == $course['course_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($course['course_name']) ?>
                                            </option>
                                        <?php endwhile; $courses->data_seek(0); ?>
                                    </select>
                                </div>
                                <div class="school-container" id="school-container-<?= $user['user_id'] ?>" style="display: <?= $user['user_type'] === 'lecturer' ? 'block' : 'none' ?>;">
                                    <label for="school_id-<?= $user['user_id'] ?>" class="block mb-1 font-medium">School <span class="text-red-500">*</span></label>
                                    <select name="school_id" id="school_id-<?= $user['user_id'] ?>"
                                        class="w-full p-2 rounded border border-gray-300 text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">-- Select school --</option>
                                        <?php while ($school = $schools->fetch_assoc()): ?>
                                            <option value="<?= $school['school_id'] ?>" <?= $user['school_id'] == $school['school_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($school['school_name']) ?>
                                            </option>
                                        <?php endwhile; $schools->data_seek(0); ?>
                                    </select>
                                </div>
                                <div class="face-capture-container" id="face-capture-container-<?= $user['user_id'] ?>" style="display: <?= $user['user_type'] === 'student' ? 'block' : 'none' ?>;">
                                    <label class="block mb-2 font-medium">Capture Face (Optional for Update)</label>
                                    <p class="text-sm text-gray-600 mb-2">Ensure bright lighting, face centered, and occupies 50-70% of frame.</p>
                                    <?php if ($user['face_encoding']): ?>
                                        <p class="text-green-600 mb-2">Face encoding exists. Re-capture to update.</p>
                                    <?php endif; ?>
                                    <div style="position: relative;">
                                        <video id="capture-video-<?= $user['user_id'] ?>" autoplay muted class="mb-2 capture-video"></video>
                                        <canvas id="capture-canvas-<?= $user['user_id'] ?>" class="capture-canvas"></canvas>
                                    </div>
                                    <button type="button" id="capture-face-btn-<?= $user['user_id'] ?>" class="bg-blue-600 text-white font-semibold px-4 py-2 rounded hover:bg-blue-500">Capture Face</button>
                                    <div id="capture-status-<?= $user['user_id'] ?>" class="mt-2 text-sm"></div>
                                    <input type="hidden" name="face_encoding" id="face-encoding-<?= $user['user_id'] ?>" />
                                </div>
                                <button type="submit" name="update" class="bg-blue-600 text-white font-semibold px-6 py-2 rounded hover:bg-blue-500">Save Changes</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex justify-between items-center">
        <span class="text-sm text-gray-600">Page <?= $page ?> of <?= $total_pages ?></span>
        <div class="space-x-2">
            <a href="?page=<?= $page - 1 ?>&search=<?= htmlspecialchars($search) ?>"
               class="px-3 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300 <?= $page <= 1 ? 'hidden' : '' ?>">Previous</a>
            <a href="?page=<?= $page + 1 ?>&search=<?= htmlspecialchars($search) ?>"
               class="px-3 py-2 rounded bg-gray-200 text-gray-700 hover:bg-gray-300 <?= $page >= $total_pages ? 'hidden' : '' ?>">Next</a>
        </div>
    </div>

    <script>
        let faceApiLoaded = false;
        const faceCaptureInitialized = new Map();
        const MODEL_URL = '../models';

        async function loadFaceApiModels() {
            if (faceApiLoaded) return;
            
            console.log('Loading face-api.js models from local directory...');
            try {
                await Promise.all([
                    faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                console.log('Face-api.js models loaded successfully from local directory');
                faceApiLoaded = true;
            } catch (error) {
                console.error('Error loading face-api.js models:', error);
                throw error;
            }
        }

        async function initializeFaceCapture(videoId, statusId, inputId, buttonId, canvasId) {
            console.log(`Initializing face capture for videoId: ${videoId}`);
            if (faceCaptureInitialized.get(videoId)) {
                console.log(`Face capture already initialized for ${videoId}`);
                return;
            }

            const video = document.getElementById(videoId);
            const status = document.getElementById(statusId);
            const input = document.getElementById(inputId);
            const button = document.getElementById(buttonId);
            let canvas = document.getElementById(canvasId);

            if (!video || !status || !input || !button) {
                const errorMsg = `Missing DOM elements: video=${!!video}, status=${!!status}, input=${!!input}, button=${!!button}`;
                status.innerHTML = '<span class="text-red-600">Setup error. Contact support.</span>';
                console.error(errorMsg);
                return;
            }

            if (!canvas) {
                canvas = document.createElement('canvas');
                canvas.id = canvasId;
                canvas.className = 'capture-canvas';
                video.parentNode.insertBefore(canvas, video.nextSibling);
            }

            if (!window.isSecureContext) {
                status.innerHTML = '<span class="text-red-600">Error: Webcam access requires HTTPS or localhost.</span>';
                console.error('Insecure context detected');
                return;
            }

            try {
                await loadFaceApiModels();
            } catch (err) {
                status.innerHTML = '<span class="text-red-600">Failed to initialize face detection: ' + err.message + '</span>';
                console.error('Face-api.js initialization error:', err);
                return;
            }

            let stream;
            try {
                console.log('Attempting to access webcam...');
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { width: { ideal: 640 }, height: { ideal: 480 } }
                });
                video.srcObject = stream;
                await new Promise(resolve => video.onloadedmetadata = resolve);
                console.log('Video stream initialized:', video.videoWidth, 'x', video.videoHeight);
                status.innerHTML = '<span class="text-green-600">Camera ready. Click "Capture Face" to take snapshot.</span>';
            } catch (err) {
                status.innerHTML = `<span class="text-red-600">Camera error: ${err.message}. Please allow camera access.</span>`;
                console.error('Camera error:', err);
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            console.log('Canvas dimensions set:', canvas.width, 'x', canvas.height);
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                status.innerHTML = '<span class="text-red-600">Canvas context unavailable.</span>';
                console.error('Failed to get canvas context');
                return;
            }

            let snapshotTaken = false;
            faceCaptureInitialized.set(videoId, true);

            const captureHandler = async () => {
                console.log(`Capture Face button clicked for ${videoId}`);
                if (snapshotTaken) {
                    console.log('Snapshot already taken, ignoring click');
                    return;
                }
                button.disabled = true;
                status.innerHTML = '<span class="loading-spinner"></span> <span class="text-gray-600">Capturing face, please hold still...</span>';

                try {
                    const detection = await faceapi.detectSingleFace(video)
                        .withFaceLandmarks()
                        .withFaceDescriptor();

                    ctx.clearRect(0, 0, canvas.width, canvas.height);

                    if (!detection) {
                        status.innerHTML = '<span class="text-yellow-600">No face detected. Ensure face is centered and well-lit.</span>';
                        console.log('No face detected in video');
                        button.disabled = false;
                        return;
                    }

                    console.log('Face detected in video');
                    snapshotTaken = true;
                    
                    const snapshotCanvas = document.createElement('canvas');
                    snapshotCanvas.width = video.videoWidth;
                    snapshotCanvas.height = video.videoHeight;
                    snapshotCanvas.getContext('2d').drawImage(video, 0, 0);

                    video.srcObject.getTracks().forEach(track => track.stop());
                    video.srcObject = null;

                    const descriptor = JSON.stringify(Array.from(detection.descriptor));
                    
                    ctx.strokeStyle = 'green';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(detection.detection.box.x, detection.detection.box.y, detection.detection.box.width, detection.detection.box.height);
                    ctx.fillStyle = 'green';
                    ctx.font = '16px Arial';
                    ctx.fillText(`Confidence: ${(detection.detection.score * 100).toFixed(2)}%`, detection.detection.box.x, detection.detection.box.y - 10);

                    input.value = JSON.stringify([Array.from(detection.descriptor)]);
                    status.innerHTML = '<span class="text-green-600">Face captured successfully!</span>';
                    console.log('Face encoding captured:', Array.from(detection.descriptor).slice(0, 10));
                } catch (err) {
                    status.innerHTML = `<span class="text-red-600">Error capturing face: ${err.message}</span>`;
                    console.error('Capture error:', err);
                    snapshotTaken = false;
                }

                button.disabled = false;
                if (!snapshotTaken && video.srcObject) {
                    setTimeout(() => {
                        status.innerHTML = '<span class="text-green-600">Camera ready. Click "Capture Face" to try again.</span>';
                    }, 2000);
                }
            };

            button.addEventListener('click', captureHandler);

            video.onpause = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                snapshotTaken = false;
            };
        }

        function toggleFaceCapture() {
            console.log('Toggling face capture for add user form');
            const userType = document.getElementById('user_type').value;
            const faceContainer = document.getElementById('face-capture-container');
            const courseContainer = document.getElementById('course-container');
            const schoolContainer = document.getElementById('school-container');

            faceContainer.style.display = userType === 'student' ? 'block' : 'none';
            courseContainer.style.display = userType === 'student' ? 'block' : 'none';
            schoolContainer.style.display = userType === 'lecturer' ? 'block' : 'none';

            if (userType === 'student') {
                setTimeout(() => {
                    initializeFaceCapture('capture-video', 'capture-status', 'face-encoding', 'capture-face-btn', 'capture-canvas');
                }, 100);
            } else {
                const input = document.getElementById('face-encoding');
                input.value = '';
                const status = document.getElementById('capture-status');
                status.innerHTML = '';
                const video = document.getElementById('capture-video');
                if (video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                }
                const canvas = document.getElementById('capture-canvas');
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    canvas.remove();
                }
                faceCaptureInitialized.delete('capture-video');
            }

            document.getElementById('course_id').required = userType === 'student';
            document.getElementById('school_id').required = userType === 'lecturer';
        }

        function toggleModalFaceCapture(userType, modal) {
            console.log(`Toggling modal face capture for userType: ${userType}`);
            const userId = modal.querySelector('input[name="user_id"]').value;
            const faceContainer = modal.querySelector(`#face-capture-container-${userId}`);
            const courseContainer = modal.querySelector(`#course-container-${userId}`);
            const schoolContainer = modal.querySelector(`#school-container-${userId}`);

            faceContainer.style.display = userType === 'student' ? 'block' : 'none';
            courseContainer.style.display = userType === 'student' ? 'block' : 'none';
            schoolContainer.style.display = userType === 'lecturer' ? 'block' : 'none';

            if (userType === 'student') {
                setTimeout(() => {
                    initializeFaceCapture(
                        `capture-video-${userId}`,
                        `capture-status-${userId}`,
                        `face-encoding-${userId}`,
                        `capture-face-btn-${userId}`,
                        `capture-canvas-${userId}`
                    );
                }, 100);
            } else {
                const input = modal.querySelector(`#face-encoding-${userId}`);
                input.value = '';
                const status = modal.querySelector(`#capture-status-${userId}`);
                status.innerHTML = '';
                const video = modal.querySelector(`#capture-video-${userId}`);
                if (video.srcObject) {
                    video.srcObject.getTracks().forEach(track => track.stop());
                }
                const canvas = document.getElementById(`capture-canvas-${userId}`);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    canvas.remove();
                }
                faceCaptureInitialized.delete(`capture-video-${userId}`);
            }

            const courseSelect = modal.querySelector(`#course_id-${userId}`);
            const schoolSelect = modal.querySelector(`#school_id-${userId}`);
            courseSelect.required = userType === 'student';
            schoolSelect.required = userType === 'lecturer';
        }

        function showEditModal(userId) {
            console.log(`Showing edit modal for userId: ${userId}`);
            const modal = document.getElementById(`edit-modal-${userId}`);
            modal.style.display = 'flex';
            const userTypeSelect = modal.querySelector('select[name="user_type"]');
            toggleModalFaceCapture(userTypeSelect.value, modal);
        }

        function hideEditModal(userId) {
            console.log(`Hiding edit modal for userId: ${userId}`);
            const modal = document.getElementById(`edit-modal-${userId}`);
            modal.style.display = 'none';
            const video = document.getElementById(`capture-video-${userId}`);
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(track => track.stop());
            }
            const canvas = document.getElementById(`capture-canvas-${userId}`);
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                canvas.remove();
            }
            faceCaptureInitialized.delete(`capture-video-${userId}`);
        }

        function validateAddForm() {
            console.log('Validating add form');
            const firstName = document.getElementById('first_name').value.trim();
            const userType = document.getElementById('user_type').value;
            const courseId = document.getElementById('course_id').value;
            const schoolId = document.getElementById('school_id').value;

            if (!firstName) {
                alert('Please enter a first name.');
                return false;
            }
            if (userType === 'student' && !courseId) {
                alert('Please select a course for the student.');
                return false;
            }
            if (userType === 'lecturer' && !schoolId) {
                alert('Please select a school for the lecturer.');
                return false;
            }
            if (userType === 'student') {
                const encoding = document.getElementById('face-encoding').value;
                if (!encoding) {
                    alert('Please capture a valid face encoding for the student.');
                    return false;
                }
            }
            return true;
        }

        function validateEditForm(form) {
            console.log('Validating edit form');
            const firstName = form.querySelector('input[name="first_name"]').value.trim();
            const userType = form.querySelector('select[name="user_type"]').value;
            const courseId = form.querySelector('select[name="course_id"]')?.value;
            const schoolId = form.querySelector('select[name="school_id"]')?.value;

            if (!firstName) {
                alert('Please enter a first name.');
                return false;
            }
            if (userType === 'student' && !courseId) {
                alert('Please select a course for the student.');
                return false;
            }
            if (userType === 'lecturer' && !schoolId) {
                alert('Please select a school for the lecturer.');
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM loaded, attaching event listeners');
            const userTypeSelect = document.getElementById('user_type');
            if (userTypeSelect) {
                userTypeSelect.addEventListener('change', toggleFaceCapture);
            }
            toggleFaceCapture();

            document.querySelectorAll('select[name="user_type"]').forEach(select => {
                if (select.id !== 'user_type') {
                    select.addEventListener('change', () => {
                        toggleModalFaceCapture(select.value, select.closest('.modal-content'));
                    });
                }
            });
        });
    </script>
</body>
</html>
