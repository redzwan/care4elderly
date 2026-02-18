<?php
include '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if ($_SESSION['user_role'] !== 'admin') { header("Location: ../dashboard/index.php"); exit(); }

$msg = "";

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = "Invalid request.";
    } else {
        $host = trim($_POST['host']);
        $port = intval($_POST['port']);
        $user = trim($_POST['username']);
        $pass = trim($_POST['password']);
        $enc = trim($_POST['encryption']);
        $from_email = trim($_POST['from_email']);
        $from_name = trim($_POST['from_name']);

        if (!in_array($enc, ['tls', 'ssl', 'none'])) {
            $msg = "Invalid encryption type.";
        } else {
            // Check if row exists
            $check = $conn->query("SELECT id FROM smtp_settings WHERE id=1");
            if ($check->num_rows == 0) {
                $stmt = $conn->prepare("INSERT INTO smtp_settings (id, host, port, username, password, encryption, from_email, from_name) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisssss", $host, $port, $user, $pass, $enc, $from_email, $from_name);
            } else {
                $stmt = $conn->prepare("UPDATE smtp_settings SET host=?, port=?, username=?, password=?, encryption=?, from_email=?, from_name=? WHERE id=1");
                $stmt->bind_param("sisssss", $host, $port, $user, $pass, $enc, $from_email, $from_name);
            }

            if ($stmt->execute()) {
                $msg = "Settings updated successfully!";
            } else {
                $msg = "Error updating settings.";
            }
        }
    }
}

// Fetch Current Settings
$s = $conn->query("SELECT * FROM smtp_settings WHERE id=1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Site Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
</head>
<body>

<?php include '../../includes/navbar.php'; ?>

<div class="container" style="margin-top: 100px; margin-bottom: 50px;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h3 class="fw-bold mb-4">Site Configuration</h3>

            <?php if($msg): ?>
                <div class="alert alert-info border-0 shadow-sm mb-4"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div class="glass-card">
                <form method="POST">
                    <?= csrfField() ?>
                    <h5 class="fw-bold text-primary mb-3"><i class="fas fa-envelope me-2"></i>SMTP Email Settings</h5>
                    <p class="text-muted small mb-4">Configure the email server used for sending appointment reminders.</p>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">SMTP Host</label>
                            <input type="text" name="host" class="form-control" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($s['host'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Port</label>
                            <input type="number" name="port" class="form-control" placeholder="587" value="<?= htmlspecialchars($s['port'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Username / Email</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($s['username'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Password / App Key</label>
                            <input type="password" name="password" class="form-control" value="<?= htmlspecialchars($s['password'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Encryption</label>
                            <select name="encryption" class="form-select">
                                <option value="tls" <?= ($s['encryption'] ?? '') == 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($s['encryption'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= ($s['encryption'] ?? '') == 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">From Email</label>
                            <input type="email" name="from_email" class="form-control" value="<?= htmlspecialchars($s['from_email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">From Name</label>
                            <input type="text" name="from_name" class="form-control" value="<?= htmlspecialchars($s['from_name'] ?? '') ?>">
                        </div>
                    </div>

                    <hr class="my-4 text-muted">

                    <div class="text-end">
                        <a href="../dashboard/index.php" class="btn btn-light text-muted me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary-glass px-4 py-2 fw-bold">Save Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
