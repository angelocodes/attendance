<?php
session_start();
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $message = "Please fill in both fields.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, password, user_type FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $db_username, $db_password, $user_type);
            $stmt->fetch();

            if (password_verify($password, $db_password)) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $db_username;
                $_SESSION['user_type'] = $user_type;

                // Redirect
                switch ($user_type) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'lecturer':
                        header("Location: lecturer/dashboard.php");
                        break;
                    case 'student':
                        header("Location: student/dashboard.php");
                        break;
                    default:
                        $message = "Unknown user type.";
                        session_destroy();
                }
                exit;
            } else {
                $message = "Invalid username or password.";
            }
        } else {
            $message = "Invalid username or password.";
        }

        $stmt->close();
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">

<div class="min-h-screen flex items-center justify-center">
  <div class="bg-gray-800 p-8 rounded shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold text-center text-yellow-400 mb-6">Login</h2>

    <?php if (!empty($message)) : ?>
      <div class="bg-red-500 text-white p-3 rounded mb-4">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-4">
        <label class="block mb-1" for="username">Username</label>
        <input id="username" type="text" name="username" required class="w-full p-2 bg-gray-700 border border-gray-600 rounded">
      </div>
      <div class="mb-4">
        <label class="block mb-1" for="password">Password</label>
        <input id="password" type="password" name="password" required class="w-full p-2 bg-gray-700 border border-gray-600 rounded">
      </div>
      <button type="submit" class="w-full bg-yellow-400 text-black py-2 rounded hover:bg-yellow-300 font-semibold">Login</button>
    </form>


    
    <p class="mt-2 text-center text-sm">
      <a href="forgot_password.php" class="text-yellow-400 underline">Forgot Password?</a>
    </p>
  </div>
</div>

</body>
</html>
