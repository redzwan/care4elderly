<?php
include '../../config/db.php';
include '../../includes/mailer.php';

if (isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

    // 1. Check if user exists and is NOT verified
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND is_verified = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $userId = $row['id'];

        // 2. Generate a fresh token
        $newToken = bin2hex(random_bytes(32));

        // 3. Update DB
        $update = $conn->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
        $update->bind_param("si", $newToken, $userId);

        if ($update->execute()) {
            // 4. Send Email using the function from previous step
            if (sendVerificationEmail($userId, $newToken)) {
                header("Location: login.php?msg=verification_resent");
                exit();
            } else {
                $error = "Failed to send email. Check mail settings.";
            }
        } else {
            $error = "System error updating token.";
        }
    } else {
        // User not found OR already verified
        // Redirect to login to prevent information leakage, or show standard message
        header("Location: login.php?msg=verified");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Resend Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style.css">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center vh-100">
<div class="card glass-card p-4 text-center">
    <h4 class="text-danger mb-3">Error</h4>
    <p><?= $error ?? 'Unknown Error' ?></p>
    <a href="login.php" class="btn btn-outline-light btn-sm mt-3">Back to Login</a>
</div>
</body>
</html>
