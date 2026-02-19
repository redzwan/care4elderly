<?php
include '../../config/db.php';

// 1. Auth & Family Check
if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
if (!isset($_SESSION['family_id'])) { header("Location: ../auth/logout.php"); exit(); }

$user_id = $_SESSION['user_id'];
$family_id = $_SESSION['family_id'];
$parent_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// --- IMAGE COMPRESSION HELPER FUNCTION ---
function compressImage($source_path, $destination_path, $quality = 60) {
    $info = getimagesize($source_path);
    if ($info === false) return false;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($source_path); break;
        case 'image/png':  $image = imagecreatefrompng($source_path); $png_quality = 9 - round(($quality / 100) * 9); imagepng($image, $destination_path, $png_quality); imagedestroy($image); return true;
        default: return move_uploaded_file($source_path, $destination_path);
    }
    imagejpeg($image, $destination_path, $quality);
    imagedestroy($image);
    return true;
}

// 2. Fetch Parent Details
$sql = "SELECT * FROM parents WHERE id = ? AND family_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $parent_id, $family_id);
$stmt->execute();
$parent = $stmt->get_result()->fetch_assoc();

if (!$parent) {
    include '../../includes/navbar.php';
    echo '<div class="container" style="margin-top: 100px;"><div class="glass-card text-center p-5"><h3>Access Denied</h3><a href="../dashboard/index.php" class="btn btn-primary-glass mt-3">Back to Dashboard</a></div></div>';
    exit();
}

// 3. Handle POST Actions
// Get message from URL if redirect happened
$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. Add Appointment
    if (isset($_POST['action']) && $_POST['action'] === 'add_appointment') {
        $stmt = $conn->prepare("INSERT INTO appointments (parent_id, title, appointment_date, location) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $parent_id, $_POST['title'], $_POST['date'], $_POST['location']);
        if($stmt->execute()) $msg = "Appointment added!";
    }

    // B. Add/Edit Medication
    if (isset($_POST['action']) && str_contains($_POST['action'], '_medication')) {
        $is_edit = $_POST['action'] === 'edit_medication';
        $ongoing = isset($_POST['is_ongoing']) ? 1 : 0;
        $end = (!empty($_POST['end_date']) && $ongoing == 0) ? $_POST['end_date'] : NULL;

        if ($is_edit) {
            $stmt = $conn->prepare("UPDATE parent_medications SET medication_name=?, instruction=?, start_date=?, end_date=?, is_ongoing=? WHERE id=? AND parent_id=?");
            $stmt->bind_param("ssssiii", $_POST['medication_name'], $_POST['instruction'], $_POST['start_date'], $end, $ongoing, $_POST['med_id'], $parent_id);
            $msg = ($stmt->execute()) ? "Medication updated!" : "Update failed.";
        } else {
            $stmt = $conn->prepare("INSERT INTO parent_medications (parent_id, medication_name, instruction, start_date, end_date, is_ongoing) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssi", $parent_id, $_POST['medication_name'], $_POST['instruction'], $_POST['start_date'], $end, $ongoing);
            $msg = ($stmt->execute()) ? "Medication added!" : "Add failed.";
        }
    }

    // C. Upload Document
    if (isset($_POST['action']) && $_POST['action'] === 'upload_document') {
        if (isset($_FILES['doc_file']) && is_array($_FILES['doc_file']['name'])) {

            $target_dir = "../../assets/documents/" . $parent_id . "/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $successCount = 0; $fileCount = count($_FILES['doc_file']['name']);

            $stmt = $conn->prepare("INSERT INTO parent_documents (parent_id, family_document_type_id, document_side, file_path) VALUES (?, ?, ?, ?)");

            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['doc_file']['error'][$i] == 0 && !empty($_FILES['doc_file']['name'][$i])) {
                    // File size limit: 10MB
                    if ($_FILES['doc_file']['size'][$i] > 10 * 1024 * 1024) {
                        $msg = "File too large. Maximum size is 10MB.";
                        continue;
                    }
                    $selectedValue = $_POST['doc_type_combined'][$i] ?? 'other|other';
                    list($typeIdStr, $side) = explode('|', $selectedValue);
                    $typeId = ($typeIdStr === 'other') ? NULL : intval($typeIdStr);

                    $ext = strtolower(pathinfo($_FILES['doc_file']['name'][$i], PATHINFO_EXTENSION));
                    // File extension whitelist
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                        $msg = "Invalid file type. Only JPG, JPEG, PNG, and PDF are allowed.";
                        continue;
                    }
                    $filenameStr = time() . "_" . uniqid() . "_doc." . $ext;
                    $target_file = $target_dir . $filenameStr;

                    if (in_array($ext, ['jpg', 'jpeg', 'png'])) { $uploadSuccess = compressImage($_FILES['doc_file']['tmp_name'][$i], $target_file, 70); }
                    else { $uploadSuccess = move_uploaded_file($_FILES['doc_file']['tmp_name'][$i], $target_file); }

                    if ($uploadSuccess) {
                        $db_path = "assets/documents/" . $parent_id . "/" . $filenameStr;
                        $stmt->bind_param("iiss", $parent_id, $typeId, $side, $db_path);
                        if($stmt->execute()) $successCount++;
                    }
                }
            }

            if ($successCount > 0) {
                $msgStr = urlencode("$successCount document(s) uploaded successfully!");
                header("Location: view.php?id=$parent_id&msg=$msgStr");
                exit();
            } elseif ($fileCount > 0) {
                $msg = "Error uploading documents. Please check file types.";
            }
        }
    }

    // C2. Delete Document Group
    if (isset($_POST['action']) && $_POST['action'] === 'delete_doc_group') {
        $typeIdToDelete = ($_POST['type_id'] === 'other') ? NULL : intval($_POST['type_id']);
        if ($typeIdToDelete === NULL) {
            $chkStmt = $conn->prepare("SELECT file_path FROM parent_documents WHERE parent_id = ? AND family_document_type_id IS NULL");
            $chkStmt->bind_param("i", $parent_id);
        } else {
            $chkStmt = $conn->prepare("SELECT file_path FROM parent_documents WHERE parent_id = ? AND family_document_type_id = ?");
            $chkStmt->bind_param("ii", $parent_id, $typeIdToDelete);
        }
        $chkStmt->execute(); $res = $chkStmt->get_result();
        $filesUnlinked = 0;
        while($f = $res->fetch_assoc()) { if(file_exists("../../" . $f['file_path']) && unlink("../../" . $f['file_path'])) $filesUnlinked++; }

        if ($typeIdToDelete === NULL) {
            $delStmt = $conn->prepare("DELETE FROM parent_documents WHERE parent_id = ? AND family_document_type_id IS NULL");
            $delStmt->bind_param("i", $parent_id);
        } else {
            $delStmt = $conn->prepare("DELETE FROM parent_documents WHERE parent_id = ? AND family_document_type_id = ?");
            $delStmt->bind_param("ii", $parent_id, $typeIdToDelete);
        }

        $msgStr = "";
        if($delStmt->execute()) { $msgStr = urlencode("Document group deleted ($filesUnlinked files removed)."); }
        else { $msgStr = urlencode("Error deleting records."); }
        header("Location: view.php?id=$parent_id&msg=$msgStr");
        exit();
    }

    // D. Edit Profile
    if (isset($_POST['action']) && $_POST['action'] === 'edit_profile') {
        $stmt = $conn->prepare("UPDATE parents SET full_name=?, ic_number=?, pension_card_no=?, dob=?, medical_notes=? WHERE id=?");
        $stmt->bind_param("sssssi", $_POST['full_name'], $_POST['ic_number'], $_POST['pension_card_no'], $_POST['dob'], $_POST['medical_notes'], $parent_id);
        if ($stmt->execute()) {
            $msg = "Profile updated!";
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
                $target_dir = "../../assets/uploads/profiles/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                $allowed_photo_ext = ['jpg', 'jpeg', 'png'];
                if (!in_array($ext, $allowed_photo_ext) || $_FILES['profile_photo']['size'] > 10 * 1024 * 1024) {
                    $msg = "Invalid photo. Only JPG/PNG under 10MB allowed.";
                } else {
                    $filename = "parent_" . $parent_id . "_" . time() . "." . $ext;
                    if (compressImage($_FILES['profile_photo']['tmp_name'], $target_dir . $filename, 60)) {
                        $db_path = "assets/uploads/profiles/" . $filename;
                        $photoStmt = $conn->prepare("UPDATE parents SET profile_photo=? WHERE id=?");
                        $photoStmt->bind_param("si", $db_path, $parent_id);
                        $photoStmt->execute();
                    }
                }
            }
            $stmt = $conn->prepare("SELECT * FROM parents WHERE id = ?"); $stmt->bind_param("i", $parent_id); $stmt->execute();
            $parent = $stmt->get_result()->fetch_assoc();
        }
    }

    // E. Delete Items (Existing functionality)
    if (isset($_POST['delete_appt'])) { $delApptStmt = $conn->prepare("DELETE FROM appointments WHERE id=? AND parent_id=?"); $delApptId = intval($_POST['delete_appt']); $delApptStmt->bind_param("ii", $delApptId, $parent_id); $delApptStmt->execute(); }
    if (isset($_POST['delete_med'])) { $delMedStmt = $conn->prepare("DELETE FROM parent_medications WHERE id=? AND parent_id=?"); $delMedId = intval($_POST['delete_med']); $delMedStmt->bind_param("ii", $delMedId, $parent_id); $delMedStmt->execute(); }

    // F. Toggle Appointment Participation (NEW)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_participation') {
        $appt_id = intval($_POST['appt_id']);

        // Check current status
        $statusStmt = $conn->prepare("SELECT appointment_id FROM appointment_participants WHERE appointment_id = ? AND user_id = ?");
        $statusStmt->bind_param("ii", $appt_id, $user_id);
        $statusStmt->execute();
        $isParticipating = $statusStmt->get_result()->num_rows > 0;

        if ($isParticipating) {
            // Leave
            $toggleStmt = $conn->prepare("DELETE FROM appointment_participants WHERE appointment_id = ? AND user_id = ?");
            $msg = "You left the appointment.";
        } else {
            // Join
            $toggleStmt = $conn->prepare("INSERT INTO appointment_participants (appointment_id, user_id) VALUES (?, ?)");
            $msg = "You joined the appointment.";
        }
        $toggleStmt->bind_param("ii", $appt_id, $user_id);
        $toggleStmt->execute();
        // No header redirect needed here as we are just refreshing the current view state
    }
}

// 4. Fetch and Process Data

// A. Process Documents into Groups
$docsSql = "SELECT pd.*, fdt.type_name FROM parent_documents pd LEFT JOIN family_document_types fdt ON pd.family_document_type_id = fdt.id WHERE pd.parent_id = ? ORDER BY (fdt.type_name IS NULL), fdt.type_name ASC, pd.document_side ASC";
$docsStmt = $conn->prepare($docsSql); $docsStmt->bind_param("i", $parent_id); $docsStmt->execute();
$docsRes = $docsStmt->get_result();
$groupedDocs = [];
while($doc = $docsRes->fetch_assoc()) {
    $typeKey = $doc['family_document_type_id'] ?? 'other';
    $typeName = $doc['type_name'] ?? 'Other Documents';
    if (!isset($groupedDocs[$typeKey])) { $groupedDocs[$typeKey] = [ 'title' => $typeName, 'type_id' => $typeKey, 'files' => [] ]; }
    $doc['display_label'] = ($typeKey === 'other') ? 'Document' : $typeName . ' (' . ucfirst($doc['document_side']) . ')';
    $groupedDocs[$typeKey]['files'][] = $doc;
}

// B. Fetch Appointments (UPDATED QUERY to include participants)
$apptsSql = "SELECT 
                a.id, a.title, a.appointment_date, a.location,
                GROUP_CONCAT(u.name SEPARATOR ', ') as participant_names,
                SUM(CASE WHEN ap.user_id = ? THEN 1 ELSE 0 END) as is_participating
            FROM appointments a
            LEFT JOIN appointment_participants ap ON a.id = ap.appointment_id
            LEFT JOIN users u ON ap.user_id = u.id
            WHERE a.parent_id = ? AND a.appointment_date >= NOW()
            GROUP BY a.id
            ORDER BY a.appointment_date ASC";
$apptsStmt = $conn->prepare($apptsSql);
$apptsStmt->bind_param("ii", $user_id, $parent_id);
$apptsStmt->execute();
$appts = $apptsStmt->get_result();


// C. Fetch Medications
$medsStmt = $conn->prepare("SELECT * FROM parent_medications WHERE parent_id = ? ORDER BY start_date DESC"); $medsStmt->bind_param("i", $parent_id); $medsStmt->execute(); $medsRes = $medsStmt->get_result();
$currentMeds = []; $pastMeds = []; $today = date('Y-m-d');
while($m = $medsRes->fetch_assoc()) { if(($m['is_ongoing'] == 1) || (!empty($m['end_date']) && $m['end_date'] >= $today)) $currentMeds[] = $m; else $pastMeds[] = $m; }

// D. Build Document Type Dropdown Options
$docTypesSql = "SELECT id, type_name FROM family_document_types WHERE family_id = ? ORDER BY type_name ASC";
$docTypesStmt = $conn->prepare($docTypesSql); $docTypesStmt->bind_param("i", $family_id); $docTypesStmt->execute(); $docTypesResult = $docTypesStmt->get_result();
$docTypeOptionsHTML = '';
if ($docTypesResult->num_rows > 0) {
    while($dt = $docTypesResult->fetch_assoc()) {
        $safeName = htmlspecialchars($dt['type_name']); $id = $dt['id'];
        $docTypeOptionsHTML .= "<option value='{$id}|front'>{$safeName} Front</option><option value='{$id}|back'>{$safeName} Back</option><option value='{$id}|single'>{$safeName} (Single)</option><option disabled>──────────</option>";
    }
} else { $docTypeOptionsHTML .= "<option disabled>No custom types configured.</option>"; }
$docTypeOptionsHTML .= "<option value='other|other'>Other</option>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars($parent['full_name']) ?> - Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <style>
        #loadingOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); z-index: 10000; display: none; flex-direction: column; justify-content: center; align-items: center; color: white; backdrop-filter: blur(5px); }
        .carousel-item object { min-height: 60vh; }
        .carousel-control-prev, .carousel-control-next { filter: invert(1) grayscale(100); }
        .carousel-item img { max-height: 75vh; width: auto; margin: auto; }
        /* Image Editor Styles */
        #imageEditorView { display: none; flex-direction: column; }
        #imageEditorView .cropper-container { max-height: 60vh; }
        .edit-toolbar { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 10px 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .edit-toolbar .btn { border-radius: 12px; min-width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem; }
        .edit-toolbar .btn i { font-size: 1rem; }
        #editorSaving { position: absolute; inset: 0; background: rgba(255,255,255,0.85); z-index: 100; backdrop-filter: blur(4px); visibility: hidden; opacity: 0; transition: opacity 0.2s; }
        #editorSaving.active { visibility: visible; opacity: 1; }
    </style>
</head>
<body>

<div id="loadingOverlay"><div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div><h5 class="fw-bold">Processing...</h5><p class="text-white-50 small">Please wait.</p></div>
<?php include '../../includes/navbar.php'; ?>

<div class="container" style="margin-top: 100px; margin-bottom: 80px;">
    <div class="d-flex align-items-center mb-4"><a href="../dashboard/index.php" class="btn btn-light text-primary rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;"><i class="fas fa-arrow-left"></i></a><div><h3 class="fw-bold m-0 text-dark mb-1">Profile Details</h3><nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small fw-bold"><li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($parent['full_name']) ?></li></ol></nav></div><?php if($msg): ?><div class="ms-auto alert alert-success border-0 shadow-sm py-2 px-4 mb-0 fw-bold"><i class="fas fa-check-circle me-2"></i> <?= $msg ?></div><?php endif; ?></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="glass-card mb-4 position-relative overflow-hidden">
                <button class="btn btn-light btn-sm rounded-circle position-absolute top-0 end-0 m-4 shadow-sm text-primary z-2" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fas fa-pencil-alt"></i></button>
                <div class="d-flex align-items-center mb-4 position-relative z-1">
                    <div class="me-4"><?php if(!empty($parent['profile_photo'])): ?><img src="../../<?= htmlspecialchars($parent['profile_photo']) ?>" class="rounded-circle shadow-lg border border-3 border-white object-fit-cover" style="width: 100px; height: 100px;"><?php else: ?><div class="bg-white shadow-lg border border-3 border-white rounded-circle d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;"><i class="fas fa-user fa-3x text-info opacity-50"></i></div><?php endif; ?></div>
                    <div><h2 class="fw-black mb-1 text-dark"><?= htmlspecialchars($parent['full_name']) ?></h2><div class="d-flex align-items-center text-muted small fw-bold"><span class="me-3"><i class="fas fa-id-card me-2 text-primary"></i>IC: <?= htmlspecialchars($parent['ic_number']) ?></span><span><i class="fas fa-birthday-cake me-2 text-danger"></i>Born: <?= date('d M Y', strtotime($parent['dob'])) ?></span></div></div>
                </div>
                <div class="bg-white bg-opacity-50 p-4 rounded-4 border-0"><h6 class="fw-bold text-primary mb-3"><i class="fas fa-notes-medical me-2"></i>Important Medical Notes</h6><p class="mb-0 text-dark" style="white-space: pre-line;"><?= !empty($parent['medical_notes']) ? htmlspecialchars($parent['medical_notes']) : '<span class="text-muted fst-italic">No medical notes added yet.</span>' ?></p></div>
            </div>

            <div class="glass-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-light pb-3"><h5 class="fw-bold m-0 text-dark"><i class="fas fa-pills me-2 text-danger"></i>Medications</h5><button class="btn btn-sm btn-primary-glass px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#medicationModal"><i class="fas fa-plus me-2"></i>Add New</button></div>
                <?php if(count($currentMeds) > 0): ?><div class="row g-3"><?php foreach($currentMeds as $med): ?><div class="col-md-6"><div class="p-3 bg-white border-0 rounded-4 shadow-sm position-relative h-100 card-hover"><div class="position-absolute top-0 end-0 m-2 z-2 d-flex gap-1 opacity-50 hover-opacity-100 transition-all"><button class="btn btn-link text-primary p-0 small edit-med-btn" data-bs-toggle="modal" data-bs-target="#editMedicationModal" data-id="<?= $med['id'] ?>" data-name="<?= htmlspecialchars($med['medication_name']) ?>" data-instruction="<?= htmlspecialchars($med['instruction']) ?>" data-start="<?= $med['start_date'] ?>" data-end="<?= $med['end_date'] ?>" data-ongoing="<?= $med['is_ongoing'] ?>"><i class="fas fa-edit"></i></button><form method="POST" class="d-inline"><input type="hidden" name="delete_med" value="<?= $med['id'] ?>"><button class="btn btn-link text-danger p-0 small" onclick="return confirm('Delete this medication?')"><i class="fas fa-trash-alt"></i></button></form></div><div class="d-flex mb-2"><i class="fas fa-capsules fa-2x text-success me-3 opacity-75"></i><div><h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($med['medication_name']) ?></h6><span class="badge bg-success bg-opacity-10 text-success rounded-pill small px-2 py-1">Active</span></div></div><div class="bg-light p-2 rounded-3 small text-muted fst-italic mb-2"><i class="fas fa-info-circle me-2"></i><?= htmlspecialchars($med['instruction']) ?></div><div class="text-end small text-muted fw-bold">Started: <?= date('d M Y', strtotime($med['start_date'])) ?></div></div></div><?php endforeach; ?></div><?php else: ?><div class="alert alert-light border-0 shadow-sm small text-muted fw-bold text-center py-3">No active medications listed.</div><?php endif; ?>
                <?php if(count($pastMeds) > 0): ?><div class="mt-4 pt-3 border-top border-light"><button class="btn btn-light w-100 text-muted text-decoration-none small p-2 fw-bold shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#medHistory"><i class="fas fa-history me-2"></i> View Medication History (<?= count($pastMeds) ?> Archived)</button><div class="collapse mt-3" id="medHistory"><div class="row g-3"><?php foreach($pastMeds as $med): ?><div class="col-md-6"><div class="p-3 bg-light border-0 rounded-4 position-relative h-100 opacity-75 grayscale"><div class="position-absolute top-0 end-0 m-2 z-2 d-flex gap-1"><button class="btn btn-link text-primary p-0 small edit-med-btn" data-bs-toggle="modal" data-bs-target="#editMedicationModal" data-id="<?= $med['id'] ?>" data-name="<?= htmlspecialchars($med['medication_name']) ?>" data-instruction="<?= htmlspecialchars($med['instruction']) ?>" data-start="<?= $med['start_date'] ?>" data-end="<?= $med['end_date'] ?>" data-ongoing="<?= $med['is_ongoing'] ?>"><i class="fas fa-edit"></i></button><form method="POST" class="d-inline"><input type="hidden" name="delete_med" value="<?= $med['id'] ?>"><button class="btn btn-link text-danger p-0 small" onclick="return confirm('Delete record permanently?')"><i class="fas fa-trash-alt"></i></button></form></div><h6 class="fw-bold text-muted mb-1"><?= htmlspecialchars($med['medication_name']) ?></h6><div class="small text-muted mb-1 fst-italic"><?= htmlspecialchars($med['instruction']) ?></div><small class="text-muted fw-bold d-block">Ended: <?= $med['end_date'] ? date('d M Y', strtotime($med['end_date'])) : 'Stopped' ?></small></div></div><?php endforeach; ?></div></div></div><?php endif; ?>
            </div>

            <div class="glass-card">
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-light pb-3"><h5 class="fw-bold m-0 text-dark"><i class="fas fa-folder-open me-2 text-warning"></i>Documents</h5><button class="btn btn-sm btn-primary-glass px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fas fa-cloud-upload-alt me-2"></i>Upload</button></div>
                <div class="row g-3">
                    <?php if(!empty($groupedDocs)): ?>
                        <?php foreach($groupedDocs as $typeKey => $group): ?>
                            <?php
                            $files = $group['files']; $title = $group['title']; $typeId = $group['type_id'];
                            $firstExt = strtolower(pathinfo($files[0]['file_path'], PATHINFO_EXTENSION));
                            $icon = (in_array($firstExt, ['jpg','jpeg','png','gif'])) ? 'fa-file-image text-info' : 'fa-file-alt text-secondary';
                            $fileCount = count($files);
                            $filesJson = htmlspecialchars(json_encode($files), ENT_QUOTES, 'UTF-8');
                            ?>
                            <div class="col-md-4 col-6">
                                <div class="bg-white p-3 rounded-4 border-0 shadow-sm h-100 position-relative text-center card-hover view-doc-group-btn cursor-pointer" data-bs-toggle="modal" data-bs-target="#documentPreviewModal" data-grouptitle="<?= htmlspecialchars($title) ?>" data-files="<?= $filesJson ?>">
                                    <div class="position-absolute top-0 start-0 m-2 z-3" onclick="event.stopPropagation();"><form method="POST" onsubmit="return confirm('Delete all documents in the \'<?= htmlspecialchars($title) ?>\' group? This cannot be undone.');"><input type="hidden" name="action" value="delete_doc_group"><input type="hidden" name="type_id" value="<?= htmlspecialchars($typeId) ?>"><button class="btn btn-link text-danger p-0 small opacity-50 hover-opacity-100"><i class="fas fa-trash-alt"></i></button></form></div>
                                    <div class="position-absolute top-0 end-0 m-2"><span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill small"><?= $fileCount ?> File<?= $fileCount > 1 ? 's' : '' ?></span></div>
                                    <i class="fas <?= $icon ?> fa-3x mb-3 opacity-75 mt-3"></i><div class="fw-bold text-dark small text-truncate mb-2"><?= htmlspecialchars($title) ?></div><span class="btn btn-sm btn-light w-100 fw-bold shadow-sm text-primary">View Documents</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?><div class="col-12 text-center py-4 text-muted small fw-bold opacity-75"><i class="fas fa-folder-open fa-3x mb-2 opacity-50"></i><br>No documents uploaded yet.</div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="glass-card h-100 position-sticky top-100">
                <h5 class="fw-bold mb-4 text-dark border-bottom border-light pb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Upcoming Events</h5>
                <form method="POST" class="mb-4 bg-primary bg-opacity-10 p-4 rounded-4 border-0 shadow-sm"><input type="hidden" name="action" value="add_appointment"><h6 class="small fw-bold text-primary mb-3 text-uppercase ls-1">Set New Reminder</h6><div class="form-floating mb-2"><input type="text" name="title" class="form-control form-control-sm border-0 shadow-sm" id="aptTitle" placeholder="Title" required><label for="aptTitle">Title (e.g. Checkup)</label></div><div class="form-floating mb-2"><input type="datetime-local" name="date" class="form-control form-control-sm border-0 shadow-sm" id="aptDate" required><label for="aptDate">Date & Time</label></div><div class="form-floating mb-3"><input type="text" name="location" class="form-control form-control-sm border-0 shadow-sm" id="aptLoc" placeholder="Location" required><label for="aptLoc">Location</label></div><button class="btn btn-primary-glass w-100 fw-bold shadow-sm py-2"><i class="fas fa-bell me-2"></i>Set Reminder</button></form>

                <div class="d-flex flex-column gap-3" style="max-height: 500px; overflow-y: auto;">
                    <?php if($appts->num_rows > 0): ?>
                        <?php while($appt = $appts->fetch_assoc()):
                            $participants = $appt['participant_names'] ?: 'No participants yet';
                            $amIParticipating = $appt['is_participating'] > 0;
                            // Styling based on participation status
                            $cardBg = $amIParticipating ? 'bg-success bg-opacity-10 border-success border-opacity-25' : 'bg-white border-light';
                            $btnClass = $amIParticipating ? 'btn-outline-danger' : 'btn-success text-white';
                            $btnText = $amIParticipating ? 'Leave' : 'Join';
                            $dateObj = strtotime($appt['appointment_date']);
                            ?>
                            <div class="p-3 rounded-4 shadow-sm position-relative border <?= $cardBg ?>">
                                <form method="POST" class="position-absolute top-0 end-0 m-2" onsubmit="return confirm('Remove appointment?');">
                                    <input type="hidden" name="delete_appt" value="<?= $appt['id'] ?>">
                                    <button class="btn btn-link text-danger p-0 small opacity-50 hover-opacity-100"><i class="fas fa-trash-alt"></i></button>
                                </form>

                                <div class="d-flex align-items-start mb-2">
                                    <div class="bg-white rounded-3 text-center me-3 p-2 d-flex flex-column align-items-center justify-content-center shadow-sm border" style="min-width: 55px; height: 55px;">
                                        <small class="d-block fw-bold text-muted" style="font-size: 0.7rem; line-height:1"><?= date('M', $dateObj) ?></small>
                                        <span class="d-block h4 mb-0 fw-black text-dark" style="line-height:1;"><?= date('d', $dateObj) ?></span>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="fw-bold text-dark text-truncate mb-1"><?= htmlspecialchars($appt['title']) ?></div>
                                        <div class="text-muted small fw-bold mb-1">
                                            <i class="far fa-clock me-1 text-primary"></i> <?= date('g:i A', $dateObj) ?>
                                            <span class="mx-1">|</span>
                                            <i class="fas fa-map-marker-alt me-1 text-danger"></i><?= htmlspecialchars($appt['location']) ?>
                                        </div>
                                        <small class="text-muted fst-italic text-truncate d-block small mb-2">
                                            <i class="fas fa-users me-1 opacity-50"></i> <?= htmlspecialchars($participants) ?>
                                        </small>
                                    </div>
                                </div>
                                <form method="POST" class="d-grid">
                                    <input type="hidden" name="action" value="toggle_participation">
                                    <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
                                    <button type="submit" class="btn <?= $btnClass ?> btn-sm fw-bold shadow-sm py-1"><?= $btnText ?></button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center text-muted small py-4 fw-bold opacity-75"><i class="far fa-calendar-times fa-3x mb-2 opacity-50"></i><br>No upcoming appointments.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content glass-card border-0 p-0 shadow-lg">
    <div class="modal-header border-bottom border-light p-3">
        <h5 class="modal-title fw-bold text-dark" id="previewModalTitle">Document Viewer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body p-0 bg-light position-relative" style="min-height: 400px; height: 75vh;">
        <!-- Normal Carousel View -->
        <div id="carouselView">
            <div id="documentCarousel" class="carousel slide h-100" data-bs-interval="false">
                <div class="carousel-inner h-100" id="carouselContent"></div>
                <button class="carousel-control-prev" type="button" data-bs-target="#documentCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span></button>
                <button class="carousel-control-next" type="button" data-bs-target="#documentCarousel" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span></button>
            </div>
        </div>
        <!-- Image Editor View -->
        <div id="imageEditorView" class="h-100 position-relative">
            <div id="editorSaving" class="d-flex flex-column align-items-center justify-content-center">
                <div class="spinner-border text-primary mb-2" role="status"></div>
                <span class="fw-bold text-muted">Saving...</span>
            </div>
            <div class="h-100 d-flex flex-column">
                <div class="flex-grow-1 overflow-hidden p-2" style="min-height:0;">
                    <img id="editorImage" src="" style="max-width:100%; display:block;">
                </div>
                <div class="p-3">
                    <div class="edit-toolbar d-flex align-items-center justify-content-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-light" onclick="editorRotate(-90)" title="Rotate Left 90°">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button type="button" class="btn btn-light" onclick="editorRotate(90)" title="Rotate Right 90°">
                            <i class="fas fa-redo"></i>
                        </button>
                        <div class="vr mx-1 opacity-25"></div>
                        <button type="button" class="btn btn-light" onclick="editorFlipH()" title="Flip Horizontal">
                            <i class="fas fa-arrows-alt-h"></i>
                        </button>
                        <button type="button" class="btn btn-light" onclick="editorFlipV()" title="Flip Vertical">
                            <i class="fas fa-arrows-alt-v"></i>
                        </button>
                        <div class="vr mx-1 opacity-25"></div>
                        <button type="button" class="btn btn-light" id="btnStartCrop" onclick="startCropMode()" title="Crop">
                            <i class="fas fa-crop-alt"></i>
                        </button>
                        <button type="button" class="btn btn-warning text-white" id="btnCancelCrop" onclick="cancelCropMode()" title="Cancel Crop" style="display:none;">
                            <i class="fas fa-crop-alt"></i> <i class="fas fa-times ms-1"></i>
                        </button>
                        <div class="vr mx-1 opacity-25"></div>
                        <button type="button" class="btn btn-outline-secondary px-3" onclick="editorCancel()">
                            <i class="fas fa-times me-1"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-success px-3 text-white" onclick="editorSave()">
                            <i class="fas fa-check me-1"></i> Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer border-top border-light p-3 justify-content-between bg-white align-items-center" id="previewModalFooter">
        <small class="text-muted fst-italic"><i class="fas fa-info-circle me-1"></i> Swipe to view</small>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary fw-bold shadow-sm" id="modalEditBtn" style="display:none;" onclick="startImageEditor()">
                <i class="fas fa-crop-alt me-2"></i>Edit Image
            </button>
            <a id="modalDownloadBtn" href="#" class="btn btn-primary-glass fw-bold shadow-sm" download>
                <i class="fas fa-download me-2"></i>Download File
            </a>
        </div>
    </div>
</div></div></div>
<?php // Include other modals here as they were in the original file ?>
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass-card border-0 p-0 shadow-lg"><div class="modal-header border-bottom border-light p-4"><h5 class="modal-title fw-bold text-dark"><i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body p-4"><form method="POST" enctype="multipart/form-data" onsubmit="showLoading()"><input type="hidden" name="action" value="edit_profile"><div class="text-center mb-4"><label for="profilePhotoInput" class="cursor-pointer position-relative d-inline-block group-hover"><?php if(!empty($parent['profile_photo'])): ?><img src="../../<?= htmlspecialchars($parent['profile_photo']) ?>" class="rounded-circle shadow-lg border border-3 border-white object-fit-cover" style="width: 110px; height: 110px;"><?php else: ?><div class="bg-white shadow-lg border border-3 border-white rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 110px; height: 110px;"><i class="fas fa-camera fa-2x text-muted opacity-50"></i></div><?php endif; ?><div class="position-absolute top-50 start-50 translate-middle opacity-0 group-hover-opacity-100 transition-all bg-dark bg-opacity-50 rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 110px; height: 110px;"><i class="fas fa-camera"></i></div></label><input type="file" name="profile_photo" id="profilePhotoInput" class="d-none" accept=".jpg,.jpeg,.png"><div class="small fw-bold text-primary mt-2">Tap image to change</div></div><div class="form-floating mb-3"><input type="text" name="full_name" class="form-control border-0 bg-white shadow-sm" id="editName" value="<?= htmlspecialchars($parent['full_name']) ?>" required><label for="editName">Full Name</label></div><div class="row g-2 mb-3"><div class="col-6"><div class="form-floating"><input type="text" name="ic_number" class="form-control border-0 bg-white shadow-sm" id="editIC" value="<?= htmlspecialchars($parent['ic_number']) ?>" required><label for="editIC">IC Number</label></div></div><div class="col-6"><div class="form-floating"><input type="date" name="dob" class="form-control border-0 bg-white shadow-sm" id="editDOB" value="<?= $parent['dob'] ?>" required><label for="editDOB">Date of Birth</label></div></div></div><div class="form-floating mb-3"><input type="text" name="pension_card_no" class="form-control border-0 bg-white shadow-sm" id="editPension" value="<?= htmlspecialchars($parent['pension_card_no']) ?>"><label for="editPension">Pension Card No.</label></div><div class="form-floating mb-4"><textarea name="medical_notes" class="form-control border-0 bg-white shadow-sm" placeholder="Notes" id="editNotes" style="height: 120px"><?= htmlspecialchars($parent['medical_notes']) ?></textarea><label for="editNotes">Medical Notes</label></div><button type="submit" class="btn btn-primary-glass w-100 fw-bold py-3 shadow-sm"><i class="fas fa-save me-2"></i>Save Changes</button></form></div></div></div></div>
<div class="modal fade" id="medicationModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass-card border-0 p-0 shadow-lg"><div class="modal-header border-bottom border-light p-4"><h5 class="modal-title fw-bold text-dark"><i class="fas fa-pills me-2 text-danger"></i>Add Medication</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body p-4"><form method="POST"><input type="hidden" name="action" value="add_medication"><div class="form-floating mb-2"><input type="text" name="medication_name" class="form-control border-0 bg-white shadow-sm" id="medName" placeholder="Medication Name" required><label for="medName">Medication Name (e.g. Paracetamol)</label></div><div class="form-floating mb-3"><textarea name="instruction" class="form-control border-0 bg-white shadow-sm" placeholder="Instruction" id="medInstr" style="height: 80px" required></textarea><label for="medInstr">Dosage Instructions</label></div><div class="row g-2 mb-3"><div class="col-6"><div class="form-floating"><input type="date" name="start_date" class="form-control border-0 bg-white shadow-sm" id="medStart" value="<?= date('Y-m-d') ?>" required><label for="medStart">Start Date</label></div></div><div class="col-6" id="endDateDiv"><div class="form-floating"><input type="date" name="end_date" class="form-control border-0 bg-white shadow-sm" id="medEnd"><label for="medEnd">End Date</label></div></div></div><div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" role="switch" name="is_ongoing" id="addIsOngoing" value="1" onchange="toggleEndDate('addIsOngoing', 'endDateDiv')"><label class="form-check-label fw-bold small" for="addIsOngoing">Ongoing Treatment (No End Date)</label></div><button type="submit" class="btn btn-primary-glass w-100 fw-bold py-3 shadow-sm"><i class="fas fa-save me-2"></i>Save Medication</button></form></div></div></div></div>
<div class="modal fade" id="editMedicationModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass-card border-0 p-0 shadow-lg"><div class="modal-header border-bottom border-light p-4"><h5 class="modal-title fw-bold text-dark"><i class="fas fa-edit me-2 text-primary"></i>Edit Medication</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body p-4"><form method="POST"><input type="hidden" name="action" value="edit_medication"><input type="hidden" name="med_id" id="editMedId"><div class="form-floating mb-2"><input type="text" name="medication_name" class="form-control border-0 bg-white shadow-sm" id="editMedName" required><label for="editMedName">Medication Name</label></div><div class="form-floating mb-3"><textarea name="instruction" class="form-control border-0 bg-white shadow-sm" id="editMedInstr" style="height: 80px" required></textarea><label for="editMedInstr">Dosage Instructions</label></div><div class="row g-2 mb-3"><div class="col-6"><div class="form-floating"><input type="date" name="start_date" class="form-control border-0 bg-white shadow-sm" id="editMedStart" required><label for="editMedStart">Start Date</label></div></div><div class="col-6" id="editEndDateDiv"><div class="form-floating"><input type="date" name="end_date" class="form-control border-0 bg-white shadow-sm" id="editMedEnd"><label for="editMedEnd">End Date</label></div></div></div><div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" role="switch" name="is_ongoing" id="editIsOngoing" value="1" onchange="toggleEndDate('editIsOngoing', 'editEndDateDiv')"><label class="form-check-label fw-bold small" for="editIsOngoing">Ongoing Treatment (No End Date)</label></div><button type="submit" class="btn btn-primary-glass w-100 fw-bold py-3 shadow-sm"><i class="fas fa-save me-2"></i>Update Medication</button></form></div></div></div></div>
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content glass-card border-0 p-0 shadow-lg"><div class="modal-header border-bottom border-light p-4"><h5 class="modal-title fw-bold text-dark"><i class="fas fa-cloud-upload-alt me-2 text-warning"></i>Upload Documents</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body p-4"><form method="POST" enctype="multipart/form-data" onsubmit="showLoading()"><input type="hidden" name="action" value="upload_document"><div id="document-repeater"><div class="doc-row mb-3 pb-3 border-bottom border-light"><div class="form-floating mb-2"><select name="doc_type_combined[]" class="form-select border-0 bg-white shadow-sm"><?= $docTypeOptionsHTML ?></select><label>Document Type</label></div><div class="input-group shadow-sm"><input class="form-control border-0 bg-white" type="file" name="doc_file[]" required accept=".pdf,.jpg,.jpeg,.png"><button type="button" class="btn btn-light text-danger border-0 bg-white d-none" onclick="removeDocumentRow(this)"><i class="fas fa-times"></i></button></div></div></div><div class="text-center mb-4"><button type="button" class="btn btn-light text-primary fw-bold shadow-sm btn-sm" onclick="addDocumentRow()"><i class="fas fa-plus-circle me-2"></i>Add Another Document</button></div><button type="submit" class="btn btn-primary-glass w-100 fw-bold py-3 shadow-sm"><i class="fas fa-upload me-2"></i>Upload All Now</button></form></div></div></div></div>
<template id="document-row-template"><div class="doc-row mb-3 pb-3 border-bottom border-light"><div class="form-floating mb-2"><select name="doc_type_combined[]" class="form-select border-0 bg-white shadow-sm"><?= $docTypeOptionsHTML ?></select><label>Document Type</label></div><div class="input-group shadow-sm"><input class="form-control border-0 bg-white" type="file" name="doc_file[]" required accept=".pdf,.jpg,.jpeg,.png"><button type="button" class="btn btn-light text-danger border-0 bg-white" onclick="removeDocumentRow(this)"><i class="fas fa-times"></i></button></div></div></template>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
    function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
    function toggleEndDate(checkboxId, divId) { const checkbox = document.getElementById(checkboxId); const endDiv = document.getElementById(divId); const endInput = endDiv.querySelector('input'); if (checkbox.checked) { endInput.value = ''; endInput.disabled = true; endDiv.style.opacity = '0.5'; } else { endInput.disabled = false; endDiv.style.opacity = '1'; } }
    function addDocumentRow() { const container = document.getElementById('document-repeater'); const template = document.getElementById('document-row-template'); const clone = template.content.cloneNode(true); container.appendChild(clone); }
    function removeDocumentRow(button) { button.closest('.doc-row').remove(); }

    // ========== IMAGE EDITOR STATE ==========
    let cropper = null;
    let currentEditDocId = null;
    let currentEditFilePath = null;
    let currentFilesData = [];
    let editorScaleX = 1;
    let editorScaleY = 1;
    let editorRotation = 0;
    let editorMode = 'view'; // 'view' = rotate/flip only, 'crop' = cropping active
    let originalImageSrc = null;

    // ========== DOCUMENT PREVIEW MODAL ==========
    const previewModal = document.getElementById('documentPreviewModal');
    const carouselEl = document.getElementById('documentCarousel');
    const downloadBtn = document.getElementById('modalDownloadBtn');
    const editBtn = document.getElementById('modalEditBtn');

    if (previewModal) {
        previewModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const groupTitle = button.getAttribute('data-grouptitle');
            const files = JSON.parse(button.getAttribute('data-files'));
            currentFilesData = files;
            const modalTitle = previewModal.querySelector('#previewModalTitle');
            const carouselContent = previewModal.querySelector('#carouselContent');
            const controls = previewModal.querySelectorAll('.carousel-control-prev, .carousel-control-next');
            modalTitle.textContent = groupTitle;
            carouselContent.innerHTML = '';
            controls.forEach(el => el.style.display = files.length > 1 ? 'flex' : 'none');

            files.forEach((file, index) => {
                const filePath = "../../" + file.file_path;
                const fileExt = filePath.split('.').pop().toLowerCase().split('?')[0];
                const isActive = index === 0 ? 'active' : '';
                const safeFilename = file.display_label.replace(/[^a-zA-Z0-9-_]/g, '_') + '.' + fileExt;
                const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
                let contentHTML = '';
                if (isImage) {
                    contentHTML = `<img src="${filePath}?v=${Date.now()}" class="d-block img-fluid" style="max-height: 75vh; width: auto; margin: auto;" alt="${file.display_label}">`;
                } else if (fileExt === 'pdf') {
                    contentHTML = `<object data="${filePath}" type="application/pdf" width="100%" height="100%" style="min-height:60vh;"><div class="text-center p-4"><p class="fw-bold">PDF preview unavailable.</p></div></object>`;
                }
                carouselContent.insertAdjacentHTML('beforeend', `<div class="carousel-item ${isActive} h-100" data-filepath="${filePath}" data-filename="${safeFilename}" data-docid="${file.id}" data-isimage="${isImage}"><div class="d-flex justify-content-center align-items-center bg-light h-100">${contentHTML}</div><div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2 mb-4"><h6 class="text-white m-0">${file.display_label}</h6></div></div>`);
            });

            if (files.length > 0 && downloadBtn) {
                const first = files[0];
                downloadBtn.setAttribute('href', "../../" + first.file_path);
                downloadBtn.setAttribute('download', first.display_label.replace(/[^a-zA-Z0-9-_]/g, '_') + '.' + first.file_path.split('.').pop().toLowerCase());
            }
            updateEditButton();
        });

        if (carouselEl) {
            carouselEl.addEventListener('slid.bs.carousel', function () {
                const active = carouselEl.querySelector('.carousel-item.active');
                if (active && downloadBtn) {
                    downloadBtn.setAttribute('href', active.getAttribute('data-filepath'));
                    downloadBtn.setAttribute('download', active.getAttribute('data-filename'));
                }
                updateEditButton();
            });
        }

        previewModal.addEventListener('hidden.bs.modal', function () {
            previewModal.querySelector('#carouselContent').innerHTML = '';
            editorCancel();
        });
    }

    function updateEditButton() {
        const active = carouselEl.querySelector('.carousel-item.active');
        if (active && active.getAttribute('data-isimage') === 'true') {
            editBtn.style.display = 'inline-flex';
        } else {
            editBtn.style.display = 'none';
        }
    }

    // ========== IMAGE EDITOR FUNCTIONS ==========
    // ========== HELPER: Rotate a canvas image by degrees ==========
    function rotateCanvas(img, degrees) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const rad = (degrees * Math.PI) / 180;
        // For 90/270 rotation, swap width/height
        if (degrees === 90 || degrees === -270 || degrees === 270 || degrees === -90) {
            canvas.width = img.naturalHeight || img.height;
            canvas.height = img.naturalWidth || img.width;
        } else {
            canvas.width = img.naturalWidth || img.width;
            canvas.height = img.naturalHeight || img.height;
        }
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate(rad);
        ctx.drawImage(img, -(img.naturalWidth || img.width) / 2, -(img.naturalHeight || img.height) / 2);
        return canvas;
    }

    // ========== HELPER: Flip a canvas image ==========
    function flipCanvas(img, horizontal) {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const w = img.naturalWidth || img.width;
        const h = img.naturalHeight || img.height;
        canvas.width = w;
        canvas.height = h;
        if (horizontal) {
            ctx.translate(w, 0);
            ctx.scale(-1, 1);
        } else {
            ctx.translate(0, h);
            ctx.scale(1, -1);
        }
        ctx.drawImage(img, 0, 0);
        return canvas;
    }

    function startImageEditor() {
        const active = carouselEl.querySelector('.carousel-item.active');
        if (!active) return;

        currentEditDocId = active.getAttribute('data-docid');
        currentEditFilePath = active.getAttribute('data-filepath');
        editorScaleX = 1;
        editorScaleY = 1;
        editorRotation = 0;
        editorMode = 'view';

        // Destroy any previous cropper
        if (cropper) { cropper.destroy(); cropper = null; }

        // Switch views
        document.getElementById('carouselView').style.display = 'none';
        document.getElementById('imageEditorView').style.display = 'flex';
        document.getElementById('previewModalFooter').style.display = 'none';
        document.getElementById('btnStartCrop').style.display = '';
        document.getElementById('btnCancelCrop').style.display = 'none';

        const editorImg = document.getElementById('editorImage');
        editorImg.src = '';
        editorImg.style.maxHeight = '60vh';
        editorImg.style.width = 'auto';
        editorImg.style.maxWidth = '100%';
        editorImg.style.margin = 'auto';
        editorImg.style.objectFit = 'contain';

        setTimeout(function() {
            originalImageSrc = currentEditFilePath + '?v=' + Date.now();
            editorImg.src = originalImageSrc;
        }, 100);
    }

    // Rotate: apply immediately to image via canvas (keeps full resolution)
    function editorRotate(deg) {
        if (editorMode === 'crop' && cropper) {
            // If in crop mode, cancel crop first
            cancelCropMode();
        }
        const editorImg = document.getElementById('editorImage');
        const canvas = rotateCanvas(editorImg, deg);
        editorImg.src = canvas.toDataURL('image/png');
        editorRotation = (editorRotation + deg) % 360;
    }

    function editorFlipH() {
        if (editorMode === 'crop' && cropper) { cancelCropMode(); }
        const editorImg = document.getElementById('editorImage');
        const canvas = flipCanvas(editorImg, true);
        editorImg.src = canvas.toDataURL('image/png');
        editorScaleX = -editorScaleX;
    }

    function editorFlipV() {
        if (editorMode === 'crop' && cropper) { cancelCropMode(); }
        const editorImg = document.getElementById('editorImage');
        const canvas = flipCanvas(editorImg, false);
        editorImg.src = canvas.toDataURL('image/png');
        editorScaleY = -editorScaleY;
    }

    // Crop mode: initialize Cropper.js on the current (possibly rotated) image
    function startCropMode() {
        editorMode = 'crop';
        document.getElementById('btnStartCrop').style.display = 'none';
        document.getElementById('btnCancelCrop').style.display = '';

        const editorImg = document.getElementById('editorImage');
        if (cropper) cropper.destroy();

        cropper = new Cropper(editorImg, {
            viewMode: 1,
            dragMode: 'crop',
            autoCropArea: 0.9,
            responsive: true,
            restore: false,
            guides: true,
            center: true,
            highlight: true,
            cropBoxMovable: true,
            cropBoxResizable: true,
            background: true,
            rotatable: false,  // We handle rotation ourselves
            scalable: false,
        });
    }

    function cancelCropMode() {
        if (cropper) { cropper.destroy(); cropper = null; }
        editorMode = 'view';
        document.getElementById('btnStartCrop').style.display = '';
        document.getElementById('btnCancelCrop').style.display = 'none';
    }

    function editorCancel() {
        if (cropper) { cropper.destroy(); cropper = null; }
        editorMode = 'view';
        editorRotation = 0;
        editorScaleX = 1;
        editorScaleY = 1;
        originalImageSrc = null;
        document.getElementById('imageEditorView').style.display = 'none';
        document.getElementById('carouselView').style.display = 'block';
        document.getElementById('previewModalFooter').style.display = 'flex';
        currentEditDocId = null;
        currentEditFilePath = null;
    }

    function editorSave() {
        if (!currentEditDocId) return;

        document.getElementById('editorSaving').classList.add('active');

        let base64;
        const ext = currentEditFilePath.split('.').pop().toLowerCase().split('?')[0];
        const mimeType = (ext === 'png') ? 'image/png' : 'image/jpeg';
        const quality = (ext === 'png') ? undefined : 0.92;

        if (editorMode === 'crop' && cropper) {
            // Get cropped canvas at full resolution
            const canvas = cropper.getCroppedCanvas({
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            if (!canvas) {
                alert('Failed to process image.');
                document.getElementById('editorSaving').classList.remove('active');
                return;
            }
            base64 = canvas.toDataURL(mimeType, quality);
        } else {
            // No crop — save the current editor image (which includes rotation/flip)
            const editorImg = document.getElementById('editorImage');
            const canvas = document.createElement('canvas');
            const w = editorImg.naturalWidth || editorImg.width;
            const h = editorImg.naturalHeight || editorImg.height;
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(editorImg, 0, 0, w, h);
            base64 = canvas.toDataURL(mimeType, quality);
        }

        // Send to backend
        fetch('save_edited_image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                doc_id: currentEditDocId,
                image_data: base64
            })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('editorSaving').classList.remove('active');
            if (data.success) {
                // Update the carousel image with cache buster
                const active = carouselEl.querySelector('.carousel-item.active');
                if (active) {
                    const img = active.querySelector('img');
                    if (img) img.src = currentEditFilePath + '?v=' + Date.now();
                }
                editorCancel();
                // Brief success feedback
                const footer = document.getElementById('previewModalFooter');
                const badge = document.createElement('span');
                badge.className = 'badge bg-success ms-2 py-2 px-3';
                badge.innerHTML = '<i class="fas fa-check me-1"></i> Saved!';
                footer.querySelector('.d-flex').prepend(badge);
                setTimeout(() => badge.remove(), 3000);
            } else {
                alert('Error: ' + (data.message || 'Failed to save'));
            }
        })
        .catch(err => {
            document.getElementById('editorSaving').classList.remove('active');
            alert('Network error. Please try again.');
            console.error(err);
        });
    }

    // ========== EDIT MEDICATION MODAL ==========
    const editMedModal = document.getElementById('editMedicationModal');
    if(editMedModal) {
        editMedModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            document.getElementById('editMedId').value = btn.getAttribute('data-id');
            document.getElementById('editMedName').value = btn.getAttribute('data-name');
            document.getElementById('editMedInstr').value = btn.getAttribute('data-instruction');
            document.getElementById('editMedStart').value = btn.getAttribute('data-start');
            document.getElementById('editMedEnd').value = btn.getAttribute('data-end');
            const isOngoing = btn.getAttribute('data-ongoing') == '1';
            const ongoingCheckbox = document.getElementById('editIsOngoing');
            ongoingCheckbox.checked = isOngoing;
            toggleEndDate('editIsOngoing', 'editEndDateDiv');
        });
    }
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]'); const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
</script>
</body>
</html>
