<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include 'db.php';
session_start(); // Start session for storing tokens
require 'vendor/autoload.php'; // Include PHPMailer

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
        // Check if user exists by email in users table
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['user_id'];

            // Generate token and expiry
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Store token in password_resets table (we'll create this temporarily for the session)
            // For now, since no table exists, we'll send the email directly

            // Send password reset email using PHPMailer
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
                $mail->SMTPAuth = true;
                $mail->Username = 'deliveredmilly@gmail.com'; // Your Gmail address
                $mail->Password = 'Sept@2024@'; // Your Gmail app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('deliveredmilly@gmail.com', 'Attendance System'); // Your Gmail address
                $mail->addAddress($email);

                // Content
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/attendance1/reset_password.php?token=" . $token;

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - Attendance System';
                $mail->Body = "
                    <h3>Password Reset Request</h3>
                    <p>You have requested to reset your password for the Attendance System.</p>
                    <p>Please click the link below to reset your password:</p>
                    <p><a href='$reset_link' style='background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p>$reset_link</p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this reset, please ignore this email.</p>
                    <br>
                    <p>Best regards,<br>Attendance System Team</p>
                ";
                $mail->AltBody = "Password Reset Request\n\nYou have requested to reset your password.\n\nPlease visit: $reset_link\n\nThis link will expire in 1 hour.";

                $mail->send();

                // Store token in password_resets table for security
                $resetStmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $resetStmt->bind_param("sss", $email, $token, $expires);
                $resetStmt->execute();
                $resetStmt->close();

                $message = "Password reset email sent! Please check your email for instructions. The reset link will expire in 1 hour.";
                $messageType = 'success';

            } catch (Exception $e) {
                $message = "Failed to send email: " . $mail->ErrorInfo;
                $messageType = 'error';
            }
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
