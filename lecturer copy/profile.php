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
$user_id = (int)$_SESSION['user_id'];

$theme_color = '#6c757d';
$lecturer = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'phone_number' => '', 'staff_number' => ''];
$message = '';
$error = '';

if (!isset($conn) || $conn->connect_error) {
    error_log("Database connection failed in profile.php: " . ($conn->connect_error ?? 'Unknown error'), 3, '../logs/errors.log');
    $error = "Database connection failed.";
} else {
    $controller = new LecturerController($conn);

    // Fetch theme color
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'theme_color'");
    if ($stmt && $stmt->execute()) {
        $stmt->bind_result($theme_color);
        $stmt->fetch();
        $stmt->close();
    } else {
        error_log("Failed to fetch theme color: " . ($stmt ? $stmt->error : 'Statement preparation failed'), 3, '../logs/errors.log');
    }

    // Fetch lecturer details
    $stmt = $conn->prepare("
        SELECT l.lecturer_name, l.staff_number, u.username, u.email, u.phone_number
        FROM lecturers l
        JOIN users u ON l.lecturer_id = u.user_id
        WHERE l.lecturer_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $lecturer = $stmt->get_result()->fetch_assoc() ?: $lecturer;
        if ($lecturer && isset($lecturer['lecturer_name'])) {
            $name_parts = explode(' ', $lecturer['lecturer_name'], 2);
            $lecturer['first_name'] = $name_parts[0] ?? '';
            $lecturer['last_name'] = $name_parts[1] ?? '';
        }
        $stmt->close();
    } else {
        error_log("Failed to fetch lecturer details: " . $stmt->error, 3, '../logs/errors.log');
        $error = "Failed to load profile details.";
    }
}

$first_name = $lecturer['first_name'];
$last_name = $lecturer['last_name'];
$username = $lecturer['username'];
$email = $lecturer['email'];
$phone = $lecturer['phone_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Combine first and last name
    $lecturer_name = trim($first_name . ' ' . $last_name);

    // Sanitize inputs
    $lecturer_name = htmlspecialchars($lecturer_name, ENT_QUOTES, 'UTF-8');
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');

    // Validate inputs
    if (empty($first_name) || empty($last_name)) {
        $error = "First name and last name are required.";
    } elseif (empty($username) || !preg_match('/^[a-zA-Z0-9]{4,50}$/', $username)) {
        $error = "Username is required and must be 4-50 alphanumeric characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($phone && !preg_match('/^\+?\d{10,15}$/', $phone)) {
        $error = "Invalid phone number format.";
    } elseif ($current_password || $new_password || $confirm_password) {
        if (!$current_password || !$new_password || !$confirm_password) {
            $error = "All password fields are required to change the password.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long.";
        }
    }

    // Check username uniqueness
    if (!$error && $username !== $lecturer['username']) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $user_id);
        if ($stmt->execute()) {
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username is already in use.";
            }
            $stmt->close();
        } else {
            $error = "Failed to verify username.";
            error_log("Username check failed: " . $stmt->error, 3, '../logs/errors.log');
        }
    }

    // Check email uniqueness
    if (!$error && $email !== $lecturer['email']) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        if ($stmt->execute()) {
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Email is already in use.";
            }
            $stmt->close();
        } else {
            $error = "Failed to verify email.";
            error_log("Email check failed: " . $stmt->error, 3, '../logs/errors.log');
        }
    }

    // Verify current password if changing password
    if (!$error && $new_password) {
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->bind_result($hashed_password);
            if ($stmt->fetch() && !password_verify($current_password, $hashed_password)) {
                $error = "Current password is incorrect.";
            }
            $stmt->close();
        } else {
            $error = "Failed to verify current password.";
            error_log("Password verification failed: " . $stmt->error, 3, '../logs/errors.log');
        }
    }

    if (!$error && $conn && !$conn->connect_error) {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Update lecturer and user details
            $stmt = $conn->prepare("
                UPDATE lecturers l
                JOIN users u ON l.lecturer_id = u.user_id
                SET l.lecturer_name = ?, u.username = ?, u.email = ?, u.phone_number = ?
                WHERE l.lecturer_id = ?
            ");
            $phone = $phone ?: null; // Handle empty phone as NULL
            $stmt->bind_param("ssssi", $lecturer_name, $username, $email, $phone, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update profile: " . $stmt->error);
            }
            $stmt->close();

            // Update password if provided
            if ($new_password) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_new_password, $user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update password: " . $stmt->error);
                }
                $stmt->close();
            }

            $conn->commit();
            $message = "Profile " . ($new_password ? "and password " : "") . "updated successfully.";
            // Update session variables
            $lecturer['lecturer_name'] = $lecturer_name;
            $lecturer['username'] = $username;
            $lecturer['email'] = $email;
            $lecturer['phone_number'] = $phone;
            $lecturer['first_name'] = $first_name;
            $lecturer['last_name'] = $last_name;
            $_SESSION['username'] = $username; // Update session username
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to update profile.";
            error_log($e->getMessage(), 3, '../logs/errors.log');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SUNATT</title>
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
        <h1 class="text-3xl font-bold text-theme mb-6" role="heading" aria-level="1">My Profile</h1>

        <div id="loading" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 flex justify-center items-center z-50">
            <svg class="animate-spin h-8 w-8 text-theme" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <?php if ($message): ?>
            <div id="success-alert" class="bg-green-700 border border-green-600 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span><?= htmlspecialchars($message) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div id="error-alert" class="bg-red-700 border border-red-400 text-white px-4 py-3 rounded mb-4 flex justify-between items-center" role="alert">
                <span><?= htmlspecialchars($error) ?></span>
                <button onclick="this.parentElement.classList.add('hidden')" class="text-white hover:text-gray-300" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <section class="bg-gray-800 rounded-lg shadow-lg max-w-lg mx-auto p-6">
            <form id="profile-form" method="POST" action="profile.php" aria-label="Update Profile Form">
                <div class="mb-4">
                    <label for="staff_number" class="block text-sm font-semibold mb-2">Staff Number</label>
                    <input type="text" id="staff_number" value="<?= htmlspecialchars($lecturer['staff_number'] ?? '') ?>" disabled
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-400 cursor-not-allowed" aria-describedby="staff-number-help">
                    <p id="staff-number-help" class="text-sm text-gray-500 mt-1">Your staff number cannot be changed.</p>
                </div>

                <div class="mb-4">
                    <label for="username" class="block text-sm font-semibold mb-2">Username</label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           value="<?= htmlspecialchars($username) ?>" maxlength="50" aria-describedby="username-help">
                    <p id="username-help" class="text-sm text-gray-500 mt-1">Enter your username (4-50 alphanumeric characters).</p>
                </div>

                <div class="mb-4">
                    <label for="first_name" class="block text-sm font-semibold mb-2">First Name</label>
                    <input type="text" id="first_name" name="first_name" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           value="<?= htmlspecialchars($first_name) ?>" maxlength="50" aria-describedby="first-name-help">
                    <p id="first-name-help" class="text-sm text-gray-500 mt-1">Enter your first name.</p>
                </div>

                <div class="mb-4">
                    <label for="last_name" class="block text-sm font-semibold mb-2">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           value="<?= htmlspecialchars($last_name) ?>" maxlength="50" aria-describedby="last-name-help">
                    <p id="last-name-help" class="text-sm text-gray-500 mt-1">Enter your last name.</p>
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-sm font-semibold mb-2">Email Address</label>
                    <input type="email" id="email" name="email" required
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           value="<?= htmlspecialchars($email) ?>" maxlength="100" aria-describedby="email-help">
                    <p id="email-help" class="text-sm text-gray-500 mt-1">Enter a valid email address.</p>
                </div>

                <div class="mb-4">
                    <label for="phone_number" class="block text-sm font-semibold mb-2">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           value="<?= htmlspecialchars($phone) ?>" maxlength="15" aria-describedby="phone-help">
                    <p id="phone-help" class="text-sm text-gray-500 mt-1">Enter your phone number (optional, e.g., +1234567890).</p>
                </div>

                <div class="mb-4">
                    <label for="current_password" class="block text-sm font-semibold mb-2">Current Password</label>
                    <input type="password" id="current_password" name="current_password"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           aria-describedby="current-password-help">
                    <p id="current-password-help" class="text-sm text-gray-500 mt-1">Enter your current password to change it.</p>
                </div>

                <div class="mb-4">
                    <label for="new_password" class="block text-sm font-semibold mb-2">New Password</label>
                    <input type="password" id="new_password" name="new_password"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           aria-describedby="new-password-help">
                    <p id="new-password-help" class="text-sm text-gray-500 mt-1">Enter a new password (minimum 8 characters, optional).</p>
                </div>

                <div class="mb-6">
                    <label for="confirm_password" class="block text-sm font-semibold mb-2">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-theme"
                           aria-describedby="confirm-password-help">
                    <p id="confirm-password-help" class="text-sm text-gray-500 mt-1">Confirm your new password.</p>
                </div>

                <button type="submit"
                        class="w-full bg-theme text-white font-semibold py-2 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-theme relative"
                        aria-label="Update Profile">
                    <span id="submit-btn-text">Update Profile</span>
                    <svg id="submit-spinner" class="hidden absolute left-2 top-0 my-auto h-5 w-5 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>
            </form>
        </section>
    </main>

    <footer class="bg-gray-800 text-center py-4 text-gray-400">
        <p>© <?= date('Y') ?> SUNATT | Soroti University Attendance System</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('profile-form');
            const submitBtn = form.querySelector('button[type="submit"]');
            const submitText = document.getElementById('submit-btn-text');
            const submitSpinner = document.getElementById('submit-spinner');
            const loadingSpinner = document.getElementById('loading');

            form.addEventListener('submit', () => {
                submitBtn.disabled = true;
                submitText.classList.add('hidden');
                submitSpinner.classList.remove('hidden');
                loadingSpinner.classList.remove('hidden');
            });
        });
    </script>
</body>
</html>