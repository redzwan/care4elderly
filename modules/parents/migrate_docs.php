<?php
// Adjust path depending on where you place this script
include '../../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    die("Access Denied. Admins only.");
}

echo "Starting migration...<br><br>";

// 1. Fetch all existing documents that haven't been migrated yet
$docsSql = "SELECT pd.id, pd.doc_type, p.family_id 
            FROM parent_documents pd
            JOIN parents p ON pd.parent_id = p.id
            WHERE pd.family_document_type_id IS NULL";
$docsRes = $conn->query($docsSql);

$count = 0;
$skipped = 0;

if ($docsRes->num_rows > 0) {
    while ($doc = $docsRes->fetch_assoc()) {
        $docId = $doc['id'];
        $oldTypeStr = $doc['doc_type'];
        $familyId = $doc['family_id'];

        $side = 'other';
        $baseTypeName = $oldTypeStr; // Default if no match found later

        // Determine Side and Base Name based on string suffix
        if (str_ends_with($oldTypeStr, ' Front')) {
            $side = 'front';
            $baseTypeName = substr($oldTypeStr, 0, -6); // Remove ' Front'
        } elseif (str_ends_with($oldTypeStr, ' Back')) {
            $side = 'back';
            $baseTypeName = substr($oldTypeStr, 0, -5); // Remove ' Back'
        } elseif (str_ends_with($oldTypeStr, ' (Single)')) {
            $side = 'single';
            $baseTypeName = substr($oldTypeStr, 0, -9); // Remove ' (Single)'
        } elseif ($oldTypeStr === 'Other') {
            // Handle legacy 'Other' items that don't have a specific type
            $otherStmt = $conn->prepare("UPDATE parent_documents SET document_side = 'other' WHERE id = ?"); $otherStmt->bind_param("i", $docId); $otherStmt->execute();
            $skipped++;
            continue;
        }

        // Find corresponding ID in family_document_types for this family
        $typeStmt = $conn->prepare("SELECT id FROM family_document_types WHERE family_id = ? AND type_name = ?");
        $typeStmt->bind_param("is", $familyId, $baseTypeName);
        $typeStmt->execute();
        $typeResult = $typeStmt->get_result();

        if ($typeRow = $typeResult->fetch_assoc()) {
            $newTypeId = $typeRow['id'];
            // Update the document record with new ID and Side
            $updateStmt = $conn->prepare("UPDATE parent_documents SET family_document_type_id = ?, document_side = ? WHERE id = ?");
            $updateStmt->bind_param("isi", $newTypeId, $side, $docId);
            if ($updateStmt->execute()) {
                $count++;
                echo "Migrated Doc ID $docId: '$oldTypeStr' -> Type ID: $newTypeId, Side: $side<br>";
            }
        } else {
            echo "Warning: Could not find matching type for Doc ID $docId ('$baseTypeName' in Family $familyId). Marked as 'other'.<br>";
            $otherStmt2 = $conn->prepare("UPDATE parent_documents SET document_side = 'other' WHERE id = ?"); $otherStmt2->bind_param("i", $docId); $otherStmt2->execute();
            $skipped++;
        }
    }
}

echo "<br>---------------------------<br>";
echo "Migration completed.<br>";
echo "Successfully updated: $count documents.<br>";
echo "Skipped/Marked as 'Other': $skipped documents.<br>";
echo "You can now safely delete this script and run Step 3 (Cleanup) in SQL.";
?>
