<?php
include 'db.php';

$message = '';
$messageType = 'error';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = "Invalid or missing reset token.";
    $messageType = 'error';
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (empty($newPassword) || empty($confirmPassword)) {
        $message = "Both password fields are required.";
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = "Password must be at least 8 characters long.";
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = 'error';
    } else {
        // Since we don't have a password_resets table yet, we'll simulate token validation
        // In production, you'd check if the token exists and hasn't expired

        // For now, we'll just accept any valid token format and allow password reset
        // In a real implementation, you'd store tokens in a password_resets table

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Validate token and get email from session (for demonstration)
        // In production, you'd validate from a password_resets table
        if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_email']) ||
            $_SESSION['reset_token'] !== $token ||
            strtotime($_SESSION['reset_expires']) < time()) {
            $message = "Invalid or expired reset token. Please request a new password reset.";
            $messageType = 'error';
        } else {
            $resetEmail = $_SESSION['reset_email'];
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashedPassword, $resetEmail);

        if ($stmt->execute()) {
            $message = "Password reset successful! You can now login with your new password.";
            $message .= "<br><br><a href='login.php' class='underline font-bold'>Go to Login Page</a>";
            $messageType = 'success';
        } else {
            $message = "Failed to update password. Please try again.";
            $messageType = 'error';
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Reset Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">

<div class="min-h-screen flex items-center justify-center">
  <div class="bg-gray-800 p-8 rounded shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold text-center text-yellow-400 mb-6">Reset Your Password</h2>

    <?php if (!empty($message)) : ?>
      <div class="<?php echo $messageType === 'success' ? 'bg-green-500' : 'bg-red-500'; ?> text-white p-3 rounded mb-4">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <?php if (empty($token)) : ?>
      <div class="bg-red-500 text-white p-3 rounded mb-4">
        Invalid reset link. Please request a new password reset.
      </div>
    <?php elseif ($messageType !== 'success') : ?>
      <form method="POST" novalidate>
        <div class="mb-4">
          <label class="block text-white mb-2">New Password</label>
          <input type="password" name="new_password" required class="w-full p-2 bg-gray-700 border border-gray-600 text-white rounded" placeholder="Enter new password (min. 8 characters)" minlength="8">
        </div>
        <div class="mb-4">
          <label class="block text-white mb-2">Confirm New Password</label>
          <input type="password" name="confirm_password" required class="w-full p-2 bg-gray-700 border border-gray-600 text-white rounded" placeholder="Confirm new password">
        </div>
        <button type="submit" class="w-full bg-yellow-400 text-black py-2 rounded hover:bg-yellow-300 font-semibold">Reset Password</button>
      </form>
    <?php endif; ?>

    <p class="mt-4 text-center text-sm"><a href="login.php" class="text-yellow-400 underline">Back to Login</a></p>
  </div>
</div>

</body>
</html>
