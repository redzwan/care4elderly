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

                <hr class="my-4 text-muted">

                <!-- Test Email Section -->
                <h5 class="fw-bold text-success mb-3"><i class="fas fa-paper-plane me-2"></i>Test Email Configuration</h5>
                <p class="text-muted small mb-3">Send a test email to verify your SMTP settings are working correctly.</p>

                <div class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label class="form-label small fw-bold">Recipient Email</label>
                        <input type="email" id="testEmail" class="form-control" placeholder="you@example.com" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <button type="button" id="btnTestMail" class="btn btn-success px-4 py-2 w-100" onclick="sendTestEmail()">
                            <i class="fas fa-paper-plane me-2"></i>Send Test Email
                        </button>
                    </div>
                </div>

                <!-- Result Area -->
                <div id="testResult" class="mt-3" style="display:none;"></div>

                <!-- SMTP Log (collapsible) -->
                <div id="testLogWrapper" class="mt-3" style="display:none;">
                    <a class="small text-muted" data-bs-toggle="collapse" href="#smtpLog" role="button" aria-expanded="false">
                        <i class="fas fa-terminal me-1"></i>Show SMTP Diagnostic Log
                    </a>
                    <div class="collapse mt-2" id="smtpLog">
                        <pre id="testLogContent" class="bg-dark text-light p-3 rounded small" style="max-height: 300px; overflow-y: auto; font-size: 12px;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function sendTestEmail() {
    const email = document.getElementById('testEmail').value.trim();
    const btn = document.getElementById('btnTestMail');
    const resultDiv = document.getElementById('testResult');
    const logWrapper = document.getElementById('testLogWrapper');
    const logContent = document.getElementById('testLogContent');

    if (!email || !email.includes('@')) {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle me-2"></i>Please enter a valid email address.</div>';
        return;
    }

    // Loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="alert alert-light py-2 text-muted"><i class="fas fa-circle-notch fa-spin me-2"></i>Connecting to SMTP server and sending test email...</div>';
    logWrapper.style.display = 'none';

    const formData = new FormData();
    formData.append('test_email', email);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');

    fetch('test_mail.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>' + data.message + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger py-2"><i class="fas fa-times-circle me-2"></i>' + data.message + '</div>';
        }

        // Show SMTP log
        if (data.log && data.log.length > 0) {
            logWrapper.style.display = 'block';
            logContent.textContent = data.log.join('\n');
        }
    })
    .catch(err => {
        resultDiv.innerHTML = '<div class="alert alert-danger py-2"><i class="fas fa-times-circle me-2"></i>Network error: ' + err.message + '</div>';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Test Email';
    });
}
</script>
</body>
</html>
