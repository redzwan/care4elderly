<?php
include '../../config/db.php';

// 1. Basic Auth Check only (DO NOT check for family_id here)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = ""; $err = "";

// 2. If they already have a family, kick them to the dashboard
// (Prevent accessing this page if they are already set up)
if (isset($_SESSION['family_id']) && !empty($_SESSION['family_id'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $familyName = trim($_POST['family_name']);

    if (empty($familyName)) {
        $err = "Please enter a family name.";
    } else {
        // Start Transaction to ensure both actions happen or neither happens
        $conn->begin_transaction();

        try {
            // A. Create the new Family entry
            $stmt = $conn->prepare("INSERT INTO families (name, created_by) VALUES (?, ?)");
            // Assuming your families table has a 'created_by' column. If not, remove it.
            $stmt->bind_param("si", $familyName, $user_id);
            $stmt->execute();
            $newFamilyId = $conn->insert_id;
            $stmt->close();

            // B. Update the User record with the new family_id
            $updateStmt = $conn->prepare("UPDATE users SET family_id = ? WHERE id = ?");
            $updateStmt->bind_param("ii", $newFamilyId, $user_id);
            $updateStmt->execute();
            $updateStmt->close();

            // Commit changes
            $conn->commit();

            // C. Update the current SESSION immediately
            $_SESSION['family_id'] = $newFamilyId;
            $_SESSION['family_name'] = $familyName; // Optional: if you store name in session

            // D. Redirect to Dashboard
            header("Location: ../dashboard/index.php?msg=" . urlencode("Welcome! Your family space is ready. Add your first parent below."));
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $err = "Error creating family: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Setup Family - Care4TheLove1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Reusing your glass-card style, slightly modified for a centered box */
        .setup-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
        }
        .btn-primary-glass {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="setup-card text-center">
                <div class="mb-4 text-primary opacity-75">
                    <i class="fas fa-house-heart fa-4x"></i>
                </div>
                <h2 class="fw-black text-dark mb-3">Welcome to CareApp!</h2>
                <p class="text-muted fw-bold mb-4">Let's get started by creating your private family space to manage your parents' care.</p>

                <?php if($err): ?>
                    <div class="alert alert-danger border-0 shadow-sm fw-bold text-start">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $err ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-floating mb-4">
                        <input type="text" name="family_name" class="form-control border-0 bg-white shadow-sm fw-bold" id="familyName" placeholder="e.g. The Smith Family" required>
                        <label for="familyName" class="text-muted fw-bold">Give your family space a name</label>
                    </div>
                    <button type="submit" class="btn btn-primary-glass w-100 py-3 fw-bold shadow-sm rounded-pill text-uppercase ls-1">
                        <i class="fas fa-rocket me-2"></i>Create My Family Space
                    </button>
                </form>
                <div class="mt-4 text-muted small fw-bold">
                    Have an invite code? <a href="#" class="text-primary">Join existing family (Coming Soon)</a>
                </div>
                <div class="mt-3">
                    <a href="../auth/logout.php" class="text-muted small fw-bold text-decoration-none"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
