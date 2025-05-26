<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id'])) {
    $esId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($esId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment status ID']);
        exit();
    }
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get data before restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ? AND is_disabled = 1");
        $checkStmt->execute([$esId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Equipment status not found or not archived']);
            exit();
        }
        
        // Get the asset tag from the equipment status
        $assetTag = $oldData['asset_tag'];
        
        // Check if there's already an active equipment status record for this asset tag
        $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_status WHERE asset_tag = ? AND is_disabled = 0");
        $activeCheckStmt->execute([$assetTag]);
        $activeCount = $activeCheckStmt->fetchColumn();
        
        if ($activeCount > 0) {
            echo json_encode(['status' => 'warning', 'message' => 'An active equipment status record with asset tag ' . $assetTag . ' already exists. Cannot restore.']);
            exit();
        }
        
        // Perform the restore of equipment status
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id = ? AND is_disabled = 1");
        $stmt->execute([$esId]);
        
        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
        $checkStmt->execute([$esId]);
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Insert into audit_log for equipment status
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_log (
                UserID,
                EntityID,
                Module,
                Action,
                Details,
                OldVal,
                NewVal,
                Status,
                Date_Time
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $auditStmt->execute([
            $_SESSION['user_id'],
            $esId,
            'Equipment Status',
            'Restore',
            'Equipment status has been restored',
            json_encode($oldData),
            json_encode($newData),
            'Successful'
        ]);
        
        // Also restore related equipment details records
        $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $detailsStmt->execute([$assetTag]);
        $detailsRowsAffected = $detailsStmt->rowCount();
        
        // Also restore related equipment location records
        $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $locationStmt->execute([$assetTag]);
        $locationRowsAffected = $locationStmt->rowCount();
        
        // Log the cascaded restorations if any rows were affected
        if ($detailsRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $esId,
                'Equipment Details',
                'Restore',
                'Equipment details entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $detailsRowsAffected]),
                null,
                'Successful'
            ]);
        }
        
        if ($locationRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $esId,
                'Equipment Location',
                'Restore',
                'Equipment location entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $locationRowsAffected]),
                null,
                'Successful'
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Prepare response message based on what was restored
        $message = 'Equipment status restored successfully';
        if ($detailsRowsAffected > 0 || $locationRowsAffected > 0) {
            $message .= ', along with ' . 
                ($detailsRowsAffected > 0 ? $detailsRowsAffected . ' details record(s)' : '') . 
                ($detailsRowsAffected > 0 && $locationRowsAffected > 0 ? ' and ' : '') . 
                ($locationRowsAffected > 0 ? $locationRowsAffected . ' location record(s)' : '');
        }
        
        echo json_encode(['status' => 'success', 'message' => $message]);
    } catch (PDOException $e) {
        // Rollback transaction if error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log failure in audit log
        try {
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID,
                    EntityID,
                    Module,
                    Action,
                    Details,
                    OldVal,
                    NewVal,
                    Status,
                    Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $auditStmt->execute([
                $_SESSION['user_id'],
                $esId,
                'Equipment Status',
                'Restore',
                'Failed to restore equipment status: ' . $e->getMessage(),
                null,
                null,
                'Failed'
            ]);
        } catch (Exception $ex) {
            // Silently handle audit log failure
        }
        
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['es_ids']) && is_array($_POST['es_ids'])) {
    $esIds = array_filter(array_map('intval', $_POST['es_ids']));
    if(empty($esIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment status IDs provided']);
        exit();
    }
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get data before restoration for audit log
        $placeholders = implode(",", array_fill(0, count($esIds), '?'));
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($esIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($oldDataRecords)) {
            echo json_encode(['status' => 'error', 'message' => 'No equipment status found or not archived']);
            exit();
        }
        
        // Store old data by ID for easier access
        $oldDataLookup = [];
        $assetTags = []; // Store asset tags for related records
        $skippedAssetTags = []; // Track skipped asset tags due to existing active records
        $validEsIds = []; // Track valid equipment status IDs after checking for active records
        
        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['equipment_status_id']] = $record;
            $assetTags[$record['equipment_status_id']] = $record['asset_tag'];
            
            // Check if there's already an active equipment status record for this asset tag
            $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_status WHERE asset_tag = ? AND is_disabled = 0");
            $activeCheckStmt->execute([$record['asset_tag']]);
            $activeCount = $activeCheckStmt->fetchColumn();
            
            if ($activeCount > 0) {
                $skippedAssetTags[] = $record['asset_tag'];
            } else {
                $validEsIds[] = $record['equipment_status_id'];
            }
        }
        
        // If no valid IDs after checking for active records, exit
        if (empty($validEsIds)) {
            if (!empty($skippedAssetTags)) {
                echo json_encode(['status' => 'warning', 'message' => 'All selected equipment statuses have active records with the same asset tags: ' . implode(', ', $skippedAssetTags)]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No valid equipment statuses to restore']);
            }
            exit();
        }
        
        // Prepare placeholders for valid IDs
        $validPlaceholders = implode(",", array_fill(0, count($validEsIds), '?'));
        
        // Perform the restore for valid equipment statuses
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id IN ($validPlaceholders) AND is_disabled = 1");
        $stmt->execute($validEsIds);
        
        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id IN ($validPlaceholders)");
        $checkStmt->execute($validEsIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['equipment_status_id']] = $record;
        }
        
        // Track restored related records
        $restoredDetailsCount = 0;
        $restoredLocationCount = 0;
        
        // Log each restoration in audit_log and restore related records
        foreach ($validEsIds as $id) {
            $oldData = $oldDataLookup[$id];
            $assetTag = $assetTags[$id];
            
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID,
                    EntityID,
                    Module,
                    Action,
                    Details,
                    OldVal,
                    NewVal,
                    Status,
                    Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Equipment Status',
                'Restore',
                'Equipment status has been restored',
                json_encode($oldData),
                json_encode($newDataLookup[$id] ?? null),
                'Successful'
            ]);
            
            // Restore related equipment details records
            $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $detailsStmt->execute([$assetTag]);
            $detailsRowsAffected = $detailsStmt->rowCount();
            $restoredDetailsCount += $detailsRowsAffected;
            
            // Restore related equipment location records
            $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $locationStmt->execute([$assetTag]);
            $locationRowsAffected = $locationStmt->rowCount();
            $restoredLocationCount += $locationRowsAffected;
            
            // Log the cascaded restorations if any rows were affected
            if ($detailsRowsAffected > 0) {
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Details',
                    'Restore',
                    'Equipment details entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                    json_encode(['asset_tag' => $assetTag, 'rows_affected' => $detailsRowsAffected]),
                    null,
                    'Successful'
                ]);
            }
            
            if ($locationRowsAffected > 0) {
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Location',
                    'Restore',
                    'Equipment location entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                    json_encode(['asset_tag' => $assetTag, 'rows_affected' => $locationRowsAffected]),
                    null,
                    'Successful'
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Prepare response message
        $message = count($validEsIds) . ' equipment statuses restored successfully';
        
        // Add info about related records
        if ($restoredDetailsCount > 0 || $restoredLocationCount > 0) {
            $message .= ', along with ' . 
                ($restoredDetailsCount > 0 ? $restoredDetailsCount . ' details record(s)' : '') . 
                ($restoredDetailsCount > 0 && $restoredLocationCount > 0 ? ' and ' : '') . 
                ($restoredLocationCount > 0 ? $restoredLocationCount . ' location record(s)' : '');
        }
        
        // Add info about skipped records
        if (!empty($skippedAssetTags)) {
            $message .= '. ' . count($skippedAssetTags) . ' record(s) skipped due to existing active asset tags: ' . implode(', ', $skippedAssetTags);
        }
        
        echo json_encode(['status' => 'success', 'message' => $message]);
    } catch (PDOException $e) {
        // Rollback transaction if error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log failure in audit log
        try {
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID,
                    EntityID,
                    Module,
                    Action,
                    Details,
                    OldVal,
                    NewVal,
                    Status,
                    Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $auditStmt->execute([
                $_SESSION['user_id'],
                null,
                'Equipment Status',
                'Restore',
                'Failed to restore multiple equipment statuses: ' . $e->getMessage(),
                json_encode(['ids' => $esIds]),
                null,
                'Failed'
            ]);
        } catch (Exception $ex) {
            // Silently handle audit log failure
        }
        
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No equipment status selected']);
} 