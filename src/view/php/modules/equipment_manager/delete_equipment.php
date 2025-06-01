<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (
    isset($_POST['id']) && isset($_POST['permanent']) && $_POST['permanent'] == 1 && isset($_POST['module'])
) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $module = $_POST['module'];
    if ($id === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit();
    }
    try {
        if ($module === 'Equipment Location') {
            // First fetch the record for the audit log
            $fetchStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
            $fetchStmt->execute([$id]);
            $locationData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            if ($locationData) {
                // Begin transaction for atomicity
                $pdo->beginTransaction();

                // Log the deletion in audit_log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Location',
                    'Delete',
                    'Equipment location has been permanently deleted',
                    json_encode($locationData),
                    null,
                    'Successful'
                ]);

                // Now perform the actual deletion
                $stmt = $pdo->prepare("DELETE FROM equipment_location WHERE equipment_location_id = ?");
                $stmt->execute([$id]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Equipment location permanently deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment location not found.']);
            }
        } elseif ($module === 'Equipment Status') {
            // First fetch the record for the audit log
            $fetchStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
            $fetchStmt->execute([$id]);
            $statusData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            if ($statusData) {
                // Begin transaction for atomicity
                $pdo->beginTransaction();

                // Log the deletion in audit_log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Status',
                    'Delete',
                    'Equipment status has been permanently deleted',
                    json_encode($statusData),
                    null,
                    'Successful'
                ]);

                // Now perform the actual deletion
                $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE equipment_status_id = ?");
                $stmt->execute([$id]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Equipment status permanently deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment status not found.']);
            }
        } elseif ($module === 'Equipment Details') {
            // First fetch the record for the audit log
            $fetchStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
            $fetchStmt->execute([$id]);
            $detailsData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            if ($detailsData) {
                // Begin transaction for atomicity
                $pdo->beginTransaction();

                // Get the asset tag for cascading deletion logging
                $assetTag = $detailsData['asset_tag'];

                // Log the main equipment details permanent deletion in audit_log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Details',
                    'Delete',
                    'Equipment details have been permanently deleted',
                    json_encode($detailsData),
                    null,
                    'Successful'
                ]);

                // Check for related status records to log their deletion as well
                $statusStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE asset_tag = ? AND is_disabled = 1");
                $statusStmt->execute([$assetTag]);
                $statusRecords = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($statusRecords) > 0) {
                    // Log the cascaded status deletions
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $id,
                        'Equipment Status',
                        'Delete',
                        'Equipment status entries for asset tag ' . $assetTag . ' have been permanently deleted (cascaded delete)',
                        json_encode(['asset_tag' => $assetTag, 'records' => $statusRecords]),
                        null,
                        'Successful'
                    ]);

                    // Delete the related status records
                    $delStatusStmt = $pdo->prepare("DELETE FROM equipment_status WHERE asset_tag = ? AND is_disabled = 1");
                    $delStatusStmt->execute([$assetTag]);
                }

                // Check for related location records to log their deletion as well
                $locationStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE asset_tag = ? AND is_disabled = 1");
                $locationStmt->execute([$assetTag]);
                $locationRecords = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($locationRecords) > 0) {
                    // Log the cascaded location deletions
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $id,
                        'Equipment Location',
                        'Delete',
                        'Equipment location entries for asset tag ' . $assetTag . ' have been permanently deleted (cascaded delete)',
                        json_encode(['asset_tag' => $assetTag, 'records' => $locationRecords]),
                        null,
                        'Successful'
                    ]);

                    // Delete the related location records
                    $delLocationStmt = $pdo->prepare("DELETE FROM equipment_location WHERE asset_tag = ? AND is_disabled = 1");
                    $delLocationStmt->execute([$assetTag]);
                }

                // Now perform the actual deletion of equipment details
                $stmt = $pdo->prepare("DELETE FROM equipment_details WHERE id = ?");
                $stmt->execute([$id]);

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Equipment details permanently deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment details not found.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Unknown module for permanent delete.']);
        }
    } catch (PDOException $e) {
        // Rollback transaction if there's an error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);

