<?php
include 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    // Check if user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate token and expiry
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // Store token in resets table
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['user_id'], $token, $expires]);

        // You can email this link or just show it for testing
        $reset_link = "http://yourdomain.com/reset_password.php?token=" . $token;

        $message = "Password reset link: <a href='$reset_link' class='underline'>$reset_link</a>";
    } else {
        $message = "No user found with that email.";
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
      <div class="bg-blue-500 text-white p-3 rounded mb-4">
        <?= $message ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <label>Email Address</label>
        <input type="email" name="email" required class="w-full p-2 bg-gray-700 border border-gray-600 rounded">
      </div>
      <button type="submit" class="w-full bg-yellow-400 text-black py-2 rounded hover:bg-yellow-300">Send Reset Link</button>
    </form>

    <p class="mt-4 text-center text-sm"><a href="login.php" class="text-yellow-400 underline">Back to Login</a></p>
  </div>
</div>

</body>
</html>
