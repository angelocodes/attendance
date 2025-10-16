<?php
include 'db.php';

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "Please enter your email address.";
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = 'error';
    } else {
        // Check if user exists by email
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];

            // Now get user details from appropriate table (student or lecturer)
            $userStmt = $conn->prepare("SELECT user_id, first_name, last_name FROM students WHERE user_id = ? UNION SELECT user_id, first_name, last_name FROM lecturers WHERE user_id = ?");
            $userStmt->bind_param("ii", $user_id, $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();

            if ($userResult->num_rows > 0) {
                $userData = $userResult->fetch_assoc();

                // Generate token and expiry - for now we'll show it directly
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

                // Since there's no password_resets table, we'll implement a simple reset via reset_password.php
                // You may want to add a password_resets table later for proper email-based reset

                $message = "Password reset token generated. Use token: <strong>$token</strong> on the reset page. This token expires in 1 hour.";
                $message .= "<br><br><a href='reset_password.php?token=$token' class='underline font-bold'>Go to Reset Password Page</a>";
                $messageType = 'success';
            } else {
                $message = "User account not found.";
                $messageType = 'error';
            }
            $userStmt->close();
        } else {
            $message = "No user found with that email address.";
            $messageType = 'error';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Forgot Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">

<div class="min-h-screen flex items-center justify-center">
  <div class="bg-gray-800 p-8 rounded shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold text-center text-yellow-400 mb-6">Forgot Password</h2>

    <?php if (!empty($message)) : ?>
      <div class="<?php echo $messageType === 'success' ? 'bg-green-500' : 'bg-red-500'; ?> text-white p-3 rounded mb-4">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <label class="block text-white mb-2">Email Address</label>
        <input type="email" name="email" required class="w-full p-2 bg-gray-700 border border-gray-600 text-white rounded" placeholder="Enter your email address">
      </div>
      <button type="submit" class="w-full bg-yellow-400 text-black py-2 rounded hover:bg-yellow-300">Send Reset Link</button>
    </form>

    <p class="mt-4 text-center text-sm"><a href="login.php" class="text-yellow-400 underline">Back to Login</a></p>
  </div>
</div>

</body>
</html>
