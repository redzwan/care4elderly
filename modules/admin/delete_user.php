<?php
include '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header("Location: users.php?msg=invalid_request"); exit();
    }
    $id = intval($_POST['id']);
    if ($id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: users.php?msg=deleted");
    exit();
}

header("Location: users.php");
exit();
?>
