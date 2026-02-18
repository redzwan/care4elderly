<?php
include '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request.";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];

        if (!in_array($role, ['user', 'admin'])) {
            $error = "Invalid role selected.";
        } else {
            // Check email uniqueness if changed
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->bind_param("si", $email, $id);
            $checkStmt->execute();
            $check = $checkStmt->get_result();
            if ($check->num_rows > 0) {
                $error = "Email already in use.";
            } else {
                $updateStmt = $conn->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                $updateStmt->bind_param("sssi", $name, $email, $role, $id);
                $updateStmt->execute();
                header("Location: users.php?msg=updated");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style.css">
</head>
<body>
<?php include '../../includes/navbar.php'; ?>

<div class="container" style="margin-top: 100px;">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="glass-card">
                <h4 class="fw-bold mb-4">Edit User</h4>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted">Role</label>
                        <select name="role" class="form-select">
                            <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>Family User</option>
                            <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Administrator</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="users.php" class="btn btn-light text-muted">Cancel</a>
                        <button type="submit" class="btn btn-primary-glass px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
