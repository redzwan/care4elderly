<?php
/**
 * save_edited_image.php
 * Receives a base64-encoded edited image and overwrites the original file.
 */
include '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['doc_id']) || empty($data['image_data'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit();
}

$doc_id = intval($data['doc_id']);
$family_id = $_SESSION['family_id'];

// Verify the document belongs to this family
$stmt = $conn->prepare("
    SELECT pd.id, pd.file_path, pd.parent_id 
    FROM parent_documents pd 
    JOIN parents p ON pd.parent_id = p.id 
    WHERE pd.id = ? AND p.family_id = ?
");
$stmt->bind_param("ii", $doc_id, $family_id);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();

if (!$doc) {
    echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
    exit();
}

// Decode base64 image
$image_data = $data['image_data'];
// Strip data URI prefix if present (e.g., "data:image/jpeg;base64,")
if (strpos($image_data, ',') !== false) {
    $image_data = explode(',', $image_data)[1];
}

$decoded = base64_decode($image_data);
if ($decoded === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid image data']);
    exit();
}

// Determine file path
$original_path = $doc['file_path'];
$full_path = "../../" . $original_path;
$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

// Only allow editing image files
if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
    echo json_encode(['success' => false, 'message' => 'Only image files can be edited']);
    exit();
}

// Create image from decoded data
$src_image = imagecreatefromstring($decoded);
if (!$src_image) {
    echo json_encode(['success' => false, 'message' => 'Failed to process image']);
    exit();
}

// Save with compression
$success = false;
if ($ext === 'png') {
    $success = imagepng($src_image, $full_path, 6); // PNG compression level 6
} else {
    $success = imagejpeg($src_image, $full_path, 75); // JPEG quality 75
}
imagedestroy($src_image);

if ($success) {
    // Append cache buster to force browser refresh
    echo json_encode([
        'success' => true,
        'message' => 'Image saved successfully',
        'new_path' => $original_path . '?v=' . time()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save image']);
}
