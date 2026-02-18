<?php
// One-time admin creation script.
// DELETE THIS FILE after creating your admin account.
include '../../config/db.php';

$msg = ""; $error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $raw_password = $_POST['password'];
    $role = 'admin';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($raw_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows > 0) {
            $error = "User with this email already exists.";
        } else {
            $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $msg = "Admin account created successfully! You can now login. Please DELETE this file.";
            } else {
                $error = "Error creating admin account.";
            }
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Create Admin - Care4TheLove1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh; background: #f5f7fa;">
<div class="card p-4 shadow" style="max-width: 400px; width: 100%;">
    <h4 class="mb-3">Create Admin Account</h4>
    <div class="alert alert-warning small">Delete this file after creating your admin account.</div>
    <?php if($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Password (min 8 chars)</label><input type="password" name="password" class="form-control" required minlength="8"></div>
        <button type="submit" class="btn btn-primary w-100">Create Admin</button>
    </form>
    <div class="mt-3 text-center"><a href="login.php">Go to Login</a></div>
</div>
</body>
</html>
