<?php
include '../../config/db.php';
include '../../includes/mailer.php'; // Include your mailer system

$msg = "";
$err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // 1. Check if email exists
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        // 2. Generate Token & Expiry (1 Hour)
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));
        $user_id = $row['id'];

        // 3. Save to DB
        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
        $update->bind_param("ssi", $token, $expiry, $user_id);

        if ($update->execute()) {
            // 4. Send Email
            $resetLink = "https://" . $_SERVER['SERVER_NAME'] . "/modules/auth/reset_password.php?token=" . $token;

            // Fetch SMTP Settings directly
            $smtp = $conn->query("SELECT * FROM smtp_settings WHERE id=1")->fetch_assoc();

            $subject = "Password Reset Request - Melb2KL";
            $body = "Hi " . $row['name'] . ",\n\nWe received a request to reset your password.\n\nClick the link below to reset it (valid for 1 hour):\n" . $resetLink . "\n\nIf you did not request this, please ignore this email.\n\nRegards,\nMelb2KL Team";

            // Use the low-level socket mailer function from includes/mailer.php
            if (send_socket_mail($smtp, $email, $subject, $body)) {
                $msg = "Reset link has been sent to your email.";
            } else {
                $err = "Failed to send email. Check SMTP settings.";
            }
        } else {
            $err = "Database error. Please try again.";
        }
    } else {
        // Security: Don't reveal if email exists or not
        $msg = "If an account exists, a reset link has been sent.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password - Melb2KL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
    <style>
        /* Fix Input Visibility */
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white !important;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--primary-red);
            box-shadow: none;
        }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.5); }
        .form-floating label { color: rgba(255, 255, 255, 0.6); }
    </style>
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center vh-100">

<div style="position: absolute; width: 100%; height: 100%; overflow: hidden; z-index: -1;">
    <div style="position: absolute; top: 20%; right: 20%; width: 300px; height: 300px; background: var(--primary-purple); filter: blur(150px); opacity: 0.4;"></div>
    <div style="position: absolute; bottom: 20%; left: 20%; width: 300px; height: 300px; background: var(--primary-red); filter: blur(150px); opacity: 0.3;"></div>
</div>

<div class="card glass-card p-4 p-md-5 border-0" style="width: 100%; max-width: 420px;">
    <div class="text-center mb-4">
        <h3 class="fw-black text-uppercase letter-spacing-2 text-white">Recover Password</h3>
        <p class="text-white-50 small">Enter your email and we'll send you a reset link.</p>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-success border-0 bg-success bg-opacity-25 text-white text-center mb-4">
            <i class="fas fa-check-circle me-2"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <?php if($err): ?>
        <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-white text-center mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i> <?= $err ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-floating mb-4">
            <input type="email" name="email" class="form-control" id="resetEmail" placeholder="name@example.com" required>
            <label for="resetEmail">Email Address</label>
        </div>
        <button type="submit" class="btn btn-danger w-100 fw-bold py-3 mb-3">SEND RESET LINK</button>
    </form>

    <div class="text-center">
        <a href="login.php" class="text-white-50 text-decoration-none small hover-underline">
            <i class="fas fa-arrow-left me-1"></i> Back to Login
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
