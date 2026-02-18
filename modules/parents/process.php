<?php
include '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 1. ADD PARENT ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_parent') {
        $user_id = $_SESSION['user_id'];
        $name = trim($_POST['full_name']);
        $ic = trim($_POST['ic_number']);
        $pension = trim($_POST['pension_card_no']);
        $dob = $_POST['dob'];
        $notes = trim($_POST['medical_notes']);

        $family_id = $_SESSION['family_id']; // Get from session

        $sql = "INSERT INTO parents (family_id, full_name, ic_number, pension_card_no, dob, medical_notes) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $family_id, $name, $ic, $pension, $dob, $notes);

        if ($stmt->execute()) {
            $parent_id = $conn->insert_id;
            // Handle Document Uploads immediately if present
            uploadDocument($conn, $parent_id, 'ic_file', 'IC');
            uploadDocument($conn, $parent_id, 'pension_file', 'Pension Card');
            uploadDocument($conn, $parent_id, 'birth_file', 'Birth Certificate');

            header("Location: ../dashboard/index.php?msg=parent_added");
        } else {
            die("Error: " . $conn->error);
        }
    }

    // --- 2. ADD APPOINTMENT ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_appointment') {
        $parent_id = intval($_POST['parent_id']);
        $title = trim($_POST['title']);
        $date = $_POST['date'] . ' ' . $_POST['time']; // Combine date & time
        $location = trim($_POST['location']);

        $stmt = $conn->prepare("INSERT INTO appointments (parent_id, title, appointment_date, location) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $parent_id, $title, $date, $location);
        $stmt->execute();
        header("Location: ../parents/view.php?id=$parent_id&msg=appt_added");
    }
}

function uploadDocument($conn, $parent_id, $input_name, $doc_type) {
    if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == 0) {
        // File size limit: 10MB
        if ($_FILES[$input_name]['size'] > 10 * 1024 * 1024) {
            return;
        }

        $ext = strtolower(pathinfo($_FILES[$input_name]["name"], PATHINFO_EXTENSION));

        // File extension whitelist
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            return;
        }

        $target_dir = "../../assets/documents/" . $parent_id . "/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

        $filename = time() . "_" . str_replace(' ', '_', $doc_type) . "." . $ext;
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES[$input_name]["tmp_name"], $target_file)) {
            $path = "assets/documents/" . $parent_id . "/" . $filename;
            $stmt = $conn->prepare("INSERT INTO parent_documents (parent_id, doc_type, file_path) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $parent_id, $doc_type, $path);
            $stmt->execute();
        }
    }
}
?>
