<?php
include '../../config/db.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['family_id'])) {
    header("Location: ../auth/login.php"); exit();
}
// Only Family Admins can perform these actions
if (($_SESSION['family_role'] ?? '') !== 'admin') {
    die("Access Denied. You must be a family admin.");
}

$family_id = $_SESSION['family_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- A. ADD MEMBER ---
    if ($_POST['action'] === 'add_member') {
        $email = trim($_POST['email']);
        $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $userStmt->bind_param("s", $email);
        $userStmt->execute();
        $userResult = $userStmt->get_result();

        if ($userResult->num_rows === 0) {
            header("Location: settings.php?err=user_not_found"); exit();
        }
        $new_user_id = $userResult->fetch_assoc()['id'];

        $checkStmt = $conn->prepare("SELECT id FROM family_members WHERE family_id = ? AND user_id = ?");
        $checkStmt->bind_param("ii", $family_id, $new_user_id);
        $checkStmt->execute();

        if ($checkStmt->get_result()->num_rows > 0) {
            header("Location: settings.php?err=already_member"); exit();
        }

        $insertStmt = $conn->prepare("INSERT INTO family_members (family_id, user_id, role) VALUES (?, ?, 'member')");
        $insertStmt->bind_param("ii", $family_id, $new_user_id);

        if ($insertStmt->execute()) {
            header("Location: settings.php?msg=member_added_success"); exit();
        } else { die("Database error: " . $conn->error); }
    }

    // --- B. REMOVE MEMBER ---
    if ($_POST['action'] === 'remove_member') {
        $user_id_to_remove = intval($_POST['user_id_to_remove']);
        if($user_id_to_remove == $_SESSION['user_id']) {
            header("Location: settings.php?err=cannot_remove_self"); exit();
        }
        $delStmt = $conn->prepare("DELETE FROM family_members WHERE family_id = ? AND user_id = ? AND role != 'admin'");
        $delStmt->bind_param("ii", $family_id, $user_id_to_remove);
        if($delStmt->execute()) { header("Location: settings.php?msg=member_removed_success"); }
        else { header("Location: settings.php?err=remove_failed"); }
        exit();
    }

    // --- C. ADD DOCUMENT TYPE (NEW) ---
    if ($_POST['action'] === 'add_doc_type') {
        $type_name = trim($_POST['type_name']);
        if (empty($type_name)) { header("Location: settings.php?err=name_required"); exit(); }

        // Check for duplicate names in the same family
        $checkStmt = $conn->prepare("SELECT id FROM family_document_types WHERE family_id = ? AND type_name = ?");
        $checkStmt->bind_param("is", $family_id, $type_name);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            header("Location: settings.php?err=duplicate_type_name"); exit();
        }

        $stmt = $conn->prepare("INSERT INTO family_document_types (family_id, type_name) VALUES (?, ?)");
        $stmt->bind_param("is", $family_id, $type_name);
        if($stmt->execute()) {
            header("Location: settings.php?msg=doc_type_added_success");
        } else {
            header("Location: settings.php?err=add_failed");
        }
        exit();
    }

    // --- D. DELETE DOCUMENT TYPE (NEW) ---
    if ($_POST['action'] === 'delete_doc_type') {
        $type_id = intval($_POST['type_id']);
        // Ensure deletion is scoped to the user's family
        $stmt = $conn->prepare("DELETE FROM family_document_types WHERE id = ? AND family_id = ?");
        $stmt->bind_param("ii", $type_id, $family_id);
        if($stmt->execute()) {
            header("Location: settings.php?msg=doc_type_deleted_success");
        } else {
            header("Location: settings.php?err=delete_failed");
        }
        exit();
    }
}
// If accessed directly
header("Location: settings.php"); exit();
?>
