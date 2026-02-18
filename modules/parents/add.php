<?php
include '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}
// IMPORTANT: Ensure Family ID is set in session
if (!isset($_SESSION['family_id'])) {
    header("Location: ../auth/logout.php");
    exit();
}

/**
 * Helper function to compress images
 * * @param string $source_path The path to the uploaded file
 * @param string $destination_path The path to save the compressed file
 * @param int $quality Compression quality (0-100)
 * @return boolean True on success, false on failure
 */
function compressImage($source_path, $destination_path, $quality = 75) {
    $info = getimagesize($source_path);
    if ($info === false) {
        return false;
    }

    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source_path);
            // For PNG, quality is 0 (no compression) to 9. We map 0-100 to 9-0.
            $png_quality = 9 - round(($quality / 100) * 9);
            imagepng($image, $destination_path, $png_quality);
            imagedestroy($image);
            return true;
        case 'image/gif':
            // GIFs are usually not compressed in the same way, so we just move them
            return move_uploaded_file($source_path, $destination_path);
        default:
            return false;
    }

    // For JPEG, we use the specified quality
    imagejpeg($image, $destination_path, $quality);
    imagedestroy($image);
    return true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Parent - Care4TheLove1</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style.css">
    <style>
        /* Loading Overlay Styles */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            backdrop-filter: blur(5px);
        }
    </style>
</head>
<body>

<div id="loadingOverlay">
    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
    <h5 class="fw-bold">Processing & Compressing Images...</h5>
    <p class="text-white-50 small">This may take a moment, please do not close the page.</p>
</div>

<?php include '../../includes/navbar.php'; ?>

<div class="container" style="margin-top: 100px; margin-bottom: 50px;">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex align-items-center mb-4">
                <a href="../dashboard/index.php" class="btn btn-light text-primary rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h3 class="fw-bold m-0 text-dark mb-1">Add Family Member</h3>
                    <p class="text-muted small mb-0">Create a new profile for a parent or patient.</p>
                </div>
            </div>

            <div class="glass-card overflow-hidden shadow-lg">
                <form action="process.php" method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
                    <input type="hidden" name="action" value="add_parent">

                    <h5 class="fw-bold text-primary mb-4 pb-3 border-bottom border-light"><i class="fas fa-user-circle me-2"></i>Personal Details</h5>

                    <div class="row g-4 mb-4">
                        <div class="col-md-12">
                            <div class="form-floating">
                                <input type="text" name="full_name" class="form-control border-0 bg-white shadow-sm" id="fullName" placeholder="Full Name" required>
                                <label for="fullName">Full Name (e.g. Ali Bin Abu)</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" name="ic_number" class="form-control border-0 bg-white shadow-sm" id="icNumber" placeholder="IC Number" required>
                                <label for="icNumber">IC Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="date" name="dob" class="form-control border-0 bg-white shadow-sm" id="dob" required>
                                <label for="dob">Date of Birth</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" name="pension_card_no" class="form-control border-0 bg-white shadow-sm" id="pensionCard" placeholder="Pension Card">
                                <label for="pensionCard">Pension Card No. (Optional)</label>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-primary mb-4 pb-3 border-bottom border-light"><i class="fas fa-notes-medical me-2"></i>Medical History</h5>
                    <div class="mb-4">
                        <div class="form-floating">
                            <textarea name="medical_notes" class="form-control border-0 bg-white shadow-sm" placeholder="Notes" id="medicalNotes" style="height: 120px"></textarea>
                            <label for="medicalNotes">Important Notes / Conditions / Allergies</label>
                        </div>
                    </div>

                    <div class="bg-primary bg-opacity-10 p-4 rounded-4 mb-4">
                        <h5 class="fw-bold text-primary mb-3"><i class="fas fa-folder-open me-2"></i>Initial Documents</h5>
                        <div class="alert alert-info border-0 shadow-sm small mb-4 d-flex align-items-center bg-white">
                            <i class="fas fa-info-circle me-3 fs-4 text-info"></i>
                            <div>Upload clear photos or PDFs. <br><strong>Images will be automatically compressed.</strong></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">IC Copy</label>
                                <input type="file" name="ic_file" class="form-control form-control-sm border-0 bg-white shadow-sm" accept=".jpg,.jpeg,.png,.pdf">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Pension Card</label>
                                <input type="file" name="pension_file" class="form-control form-control-sm border-0 bg-white shadow-sm" accept=".jpg,.jpeg,.png,.pdf">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Birth Certificate</label>
                                <input type="file" name="birth_file" class="form-control form-control-sm border-0 bg-white shadow-sm" accept=".jpg,.jpeg,.png,.pdf">
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary-glass px-5 py-3 fw-bold shadow-lg">
                            <i class="fas fa-save me-2"></i> Create Profile
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to show the loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
</script>
</body>
</html>
