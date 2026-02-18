<?php
include '../../config/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request.";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            // Prepare SQL statement to fetch user details
            $stmt = $conn->prepare("SELECT id, name, password, role, family_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                // --- LOGIN SUCCESSFUL ---
                session_regenerate_id(true);

                // Set basic user session data immediately
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];

                // --- THE CRITICAL NEW CHECK ---
                // Check if this user is already part of a family
                if (!empty($user['family_id'])) {
                    // Case 1: They belong to a family. Proceed normally.
                    $_SESSION['family_id'] = $user['family_id'];
                    header("Location: ../dashboard/index.php");
                } else {
                    // Case 2: New user with no family yet. Redirect to setup.
                    // NOTE: Do NOT set $_SESSION['family_id'] here.
                    // The setup.php page will do that after they create the family.
                    header("Location: ../family/setup.php");
                }
                exit();
                // -----------------------------

            } else {
                $error = "Invalid email or password.";
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - CareApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
<div class="login-card">
    <h3 class="text-center mb-4">Login</h3>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" action="">
        <?= csrfField() ?>
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <div class="text-center mt-3">
        <a href="register.php">Don't have an account? Register</a>
    </div>
</div>
</body>
</html>
