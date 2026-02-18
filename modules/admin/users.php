<?php
include '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['user_role'] !== 'admin') { header("Location: ../dashboard/index.php"); exit(); }

// Fetch Users
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Users - Care4TheLove1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
</head>
<body>

<?php include '../../includes/navbar.php'; ?>

<div class="container" style="margin-top: 100px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold">User Management</h3>
        <span class="badge bg-info text-dark"><?= $result->num_rows ?> Users</span>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4">User deleted successfully.</div>
    <?php endif; ?>

    <div class="glass-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                <tr>
                    <th class="ps-4">Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php while($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($user['name']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if($user['role'] === 'admin'): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Family</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                        <td class="text-end pe-4">
                            <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></a>

                            <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="delete_user.php" class="d-inline" onsubmit="return confirm('Are you sure? This will delete all parents and data associated with this user.');"><input type="hidden" name="id" value="<?= $user['id'] ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
