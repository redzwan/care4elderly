<?php
include '../../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $raw_pass = $_POST['password'];

    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($raw_pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Hash Password
        $pass = password_hash($raw_pass, PASSWORD_DEFAULT);

        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            // Insert User (Matching Care4TheLove1 Database Structure)
            // Removed 'phone' and 'verification_token' as they weren't in the initial SQL setup
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param("sss", $name, $email, $pass);

            if ($stmt->execute()) {
                // Success - Redirect to Login
                header("Location: login.php?msg=registered");
                exit();
            } else {
                $error = "System error. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register - Care4TheLove1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">

<div class="glass-card p-4 p-md-5" style="width: 100%; max-width: 450px;">
    <div class="text-center mb-4">
        <h3 class="text-gradient display-6">Join Family</h3>
        <p class="text-muted small">Create an account to manage care</p>
    </div>

    <?php if(isset($error)): ?>
        <div class='alert alert-danger border-0 bg-danger bg-opacity-10 text-danger shadow-sm'>
            <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-floating mb-3">
            <input type="text" name="name" class="form-control" id="nameInput" placeholder="Full Name" required>
            <label for="nameInput">Full Name</label>
        </div>
        <div class="form-floating mb-3">
            <input type="email" name="email" class="form-control" id="emailInput" placeholder="Email" required>
            <label for="emailInput">Email Address</label>
        </div>
        <div class="form-floating mb-4">
            <input type="password" name="password" class="form-control" id="passInput" placeholder="Password" required>
            <label for="passInput">Password (Min 6 chars)</label>
        </div>
        <button type="submit" class="btn btn-primary-glass w-100 py-3 fw-bold">CREATE ACCOUNT</button>
    </form>

    <div class="mt-4 text-center">
        <a href="login.php" class="text-decoration-none small text-muted">Already have an account? <span class="text-primary fw-bold">Login</span></a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
