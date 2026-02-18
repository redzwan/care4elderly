<?php
include '../../config/db.php';

$msg = "";
$error = "";

if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];

    // Validate Token
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND verification_token = ? AND is_verified = 0");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Activate Account
        $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE email = ?");
        $update->bind_param("s", $email);

        if ($update->execute()) {
            // Success
            header("Location: login.php?msg=verified");
            exit();
        } else {
            $error = "System error during activation.";
        }
    } else {
        $error = "Invalid or expired verification link.";
    }
} else {
    $error = "Missing verification data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Verify Account - Melb2KL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style.css">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center vh-100">
<div class="card glass-card p-5 text-center" style="max-width: 400px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
    <?php if($error): ?>
        <h3 class="text-danger fw-bold mb-3"><i class="fas fa-times-circle"></i> Error</h3>
        <p class="text-white-50"><?= $error ?></p>
    <?php else: ?>
        <h3 class="text-success fw-bold mb-3"><i class="fas fa-check-circle"></i> Verifying...</h3>
    <?php endif; ?>
    <a href="login.php" class="btn btn-outline-light mt-3">Back to Login</a>
</div>
</body>
</html>
