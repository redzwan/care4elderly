<?php
include '../../config/db.php';

$msg = "";
$err = "";
$validToken = false;

// 1. VERIFY TOKEN FROM URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if token exists and is not expired
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $validToken = true;
    } else {
        $err = "Invalid or expired link. Please request a new one.";
    }
} else {
    header("Location: login.php");
    exit();
}

// 2. HANDLE PASSWORD UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && $validToken) {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $token = $_POST['token'];

    if (strlen($pass) < 6) {
        $err = "Password must be at least 6 characters.";
    } elseif ($pass !== $confirm) {
        $err = "Passwords do not match.";
    } else {
        // Hash & Update
        $hashed = password_hash($pass, PASSWORD_DEFAULT);

        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?");
        $update->bind_param("ss", $hashed, $token);

        if ($update->execute()) {
            $msg = "Password updated! Redirecting to login...";
            echo "<script>setTimeout(() => window.location='login.php', 3000);</script>";
            $validToken = false; // Prevent resubmission
        } else {
            $err = "Error updating password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Set New Password - Melb2KL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
    <style>
        /* Glass Input Styles */
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
    <div style="position: absolute; top: 10%; left: 30%; width: 400px; height: 400px; background: var(--primary-purple); filter: blur(200px); opacity: 0.3;"></div>
    <div style="position: absolute; bottom: 10%; right: 30%; width: 400px; height: 400px; background: var(--primary-red); filter: blur(200px); opacity: 0.25;"></div>
</div>

<div class="card glass-card p-4 p-md-5 border-0" style="width: 100%; max-width: 450px;">

    <div class="text-center mb-4">
        <h3 class="fw-black text-uppercase letter-spacing-2">Reset Password</h3>
        <p class="text-white-50 small">Create a new secure password.</p>
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

    <?php if ($validToken): ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">

            <div class="form-floating mb-3">
                <input type="password" name="password" class="form-control" id="newPass" placeholder="New Password" required>
                <label for="newPass">New Password</label>
            </div>

            <div class="form-floating mb-4">
                <input type="password" name="confirm_password" class="form-control" id="confPass" placeholder="Confirm Password" required>
                <label for="confPass">Confirm Password</label>
            </div>

            <button type="submit" class="btn btn-danger w-100 fw-bold py-3 mb-3 shadow">UPDATE PASSWORD</button>
        </form>
    <?php elseif (!$msg): ?>
        <div class="text-center">
            <p class="text-white-50">This link is invalid or has expired.</p>
            <a href="forgot_password.php" class="btn btn-outline-light rounded-pill px-4">Request New Link</a>
        </div>
    <?php endif; ?>

    <div class="text-center mt-3">
        <a href="login.php" class="text-white-50 text-decoration-none small hover-underline">
            <i class="fas fa-arrow-left me-1"></i> Back to Login
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
