<?php
// modules/family/settings.php
include '../../config/db.php';

// 1. Auth Check & Role Verification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['family_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$family_id = $_SESSION['family_id'];
$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$err = isset($_GET['err']) ? htmlspecialchars($_GET['err']) : '';

// Verify current user is actually an Admin
$userStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$currentUser = $userStmt->get_result()->fetch_assoc();

// THE SECURITY CHECK CAUSING THE "DENIED" MESSAGE
// If their role in DB is not 'admin', kick them out.
if ($currentUser['role'] !== 'admin') {
    header("Location: ../dashboard/index.php?msg=denied");
    exit();
}

// 2. Fetch Family Details
$famStmt = $conn->prepare("SELECT name, invite_code FROM families WHERE id = ?");
$famStmt->bind_param("i", $family_id);
$famStmt->execute();
$family = $famStmt->get_result()->fetch_assoc();

// 3. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. Update Family Name
    if (isset($_POST['action']) && $_POST['action'] === 'update_family_name') {
        $newName = trim($_POST['family_name']);
        if (empty($newName)) {
            $err = "Family name cannot be empty.";
        } else {
            $updateStmt = $conn->prepare("UPDATE families SET name = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newName, $family_id);
            if ($updateStmt->execute()) {
                $msg = "Family name updated successfully.";
                $family['name'] = $newName; // Update local variable
            } else {
                $err = "Error updating name.";
            }
        }
    }

    // B. Regenerate Invite Code
    elseif (isset($_POST['action']) && $_POST['action'] === 'regenerate_code') {
        // Generate a random 8-character uppercase code
        $newCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $updateStmt = $conn->prepare("UPDATE families SET invite_code = ? WHERE id = ?");
        $updateStmt->bind_param("si", $newCode, $family_id);
        if ($updateStmt->execute()) {
            $msg = "New invite code generated.";
            $family['invite_code'] = $newCode;
        } else {
            $err = "Error generating code: " . $conn->error;
        }
    }

    // C. Change Member Role
    elseif (isset($_POST['action']) && $_POST['action'] === 'change_role') {
        $targetUserId = intval($_POST['target_user_id']);
        $newRole = $_POST['new_role'];

        // Safety checks
        if ($targetUserId === $user_id) {
            $err = "You cannot change your own role here.";
        } elseif (!in_array($newRole, ['admin', 'member'])) {
            $err = "Invalid role specified.";
        } else {
            // Verify target user belongs to this family before changing
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND family_id = ?");
            $checkStmt->bind_param("ii", $targetUserId, $family_id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                $err = "User not found in your family.";
            } else {
                // Perform update
                $roleStmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $roleStmt->bind_param("si", $newRole, $targetUserId);
                if ($roleStmt->execute()) {
                    $msg = "User role updated successfully.";
                } else {
                    $err = "Error updating role: " . $conn->error;
                }
            }
        }
    }
}

// 4. Fetch Family Members List (Done after POST to reflect changes immediately)
$memStmt = $conn->prepare("SELECT id, name, email, role, joined_at FROM users WHERE family_id = ? ORDER BY role ASC, name ASC");
$memStmt->bind_param("i", $family_id);
$memStmt->execute();
$membersRes = $memStmt->get_result();
$members = [];
while ($m = $membersRes->fetch_assoc()) {
    $members[] = $m;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Family Settings - Care4TheLove1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
</head>
<body>

<?php include '../../includes/navbar.php'; ?>

<div class="container" style="margin-top: 100px; margin-bottom: 80px;">
    <div class="d-flex align-items-center mb-4">
        <a href="../dashboard/index.php" class="btn btn-light text-primary rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h3 class="fw-bold m-0 text-dark mb-1">Family Settings</h3>
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small fw-bold"><li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li><li class="breadcrumb-item active">Settings</li></ol></nav>
        </div>
    </div>

    <?php if($msg): ?><div class="alert alert-success border-0 shadow-sm fw-bold mb-4"><i class="fas fa-check-circle me-2"></i><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="alert alert-danger border-0 shadow-sm fw-bold mb-4"><i class="fas fa-exclamation-circle me-2"></i><?= $err ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="glass-card mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-signature me-2 text-primary"></i>General Info</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="update_family_name">
                    <div class="form-floating mb-3">
                        <input type="text" name="family_name" class="form-control border-0 bg-white shadow-sm fw-bold" id="famName" value="<?= htmlspecialchars($family['name'] ?? '') ?>" required>
                        <label for="famName">Family Name</label>
                    </div>
                    <button type="submit" class="btn btn-primary-glass w-100 fw-bold shadow-sm">Update Name</button>
                </form>
            </div>

            <div class="glass-card bg-primary bg-opacity-10 border-primary border-opacity-25">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-ticket-alt me-2 text-primary"></i>Invite New Members</h5>
                <p class="text-muted small fw-bold mb-3">Share this code with family members so they can join your space.</p>

                <div class="bg-white p-3 rounded-3 text-center shadow-sm mb-3 d-flex align-items-center justify-content-center" style="min-height: 60px;">
                    <?php if (!empty($family['invite_code'])): ?>
                        <h2 class="fw-black text-primary mb-0 ls-2"><?= htmlspecialchars($family['invite_code']) ?></h2>
                    <?php else: ?>
                        <span class="text-muted small fw-bold fst-italic">No code generated yet.</span>
                    <?php endif; ?>
                </div>

                <form method="POST" onsubmit="return confirm('Are you sure? The old code will stop working.');">
                    <input type="hidden" name="action" value="regenerate_code">
                    <button type="submit" class="btn btn-light text-primary w-100 fw-bold shadow-sm">
                        <i class="fas fa-sync-alt me-2"></i><?= empty($family['invite_code']) ? 'Generate Code' : 'Regenerate Code' ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="glass-card">
                <h5 class="fw-bold text-dark mb-4"><i class="fas fa-users-cog me-2 text-success"></i>Family Members (<?= count($members) ?>)</h5>
                <div class="table-responsive rounded-4 shadow-sm" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-borderless align-middle mb-0 bg-white">
                        <thead class="bg-light border-bottom">
                        <tr class="small fw-bold text-muted text-uppercase ls-1">
                            <th class="ps-4 py-3">Name</th>
                            <th class="py-3">Role</th>
                            <th class="py-3">Joined</th>
                            <th class="pe-4 py-3 text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($members as $m):
                            $isMe = ($m['id'] === $user_id);
                            $badgeBg = ($m['role'] === 'admin') ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary';
                            ?>
                            <tr class="border-bottom">
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($m['name']) ?></div>
                                            <small class="text-muted fw-bold"><?= htmlspecialchars($m['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <span class="badge <?= $badgeBg ?> rounded-pill text-uppercase px-3 py-2 small fw-bold"><?= ucfirst($m['role']) ?></span>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted fw-bold"><?= !empty($m['joined_at']) ? date('M j, Y', strtotime($m['joined_at'])) : 'N/A' ?></small>
                                </td>
                                <td class="pe-4 py-3 text-end">
                                    <?php if ($isMe): ?>
                                        <span class="text-muted fst-italic small fw-bold">It's you</span>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="target_user_id" value="<?= $m['id'] ?>">

                                            <?php if ($m['role'] === 'member'): ?>
                                                <input type="hidden" name="new_role" value="admin">
                                                <button type="submit" class="btn btn-sm btn-success-glass fw-bold shadow-sm" onclick="return confirm('Make this user an Admin?');"><i class="fas fa-shield-alt me-1"></i>Make Admin</button>
                                            <?php elseif ($m['role'] === 'admin'): ?>
                                                <input type="hidden" name="new_role" value="member">
                                                <button type="submit" class="btn btn-sm btn-warning-glass fw-bold shadow-sm text-danger" onclick="return confirm('Remove admin rights?');"><i class="fas fa-user-shield me-1"></i>Remove Admin</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
