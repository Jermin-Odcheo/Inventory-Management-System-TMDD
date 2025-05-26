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
    $elId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($elId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment location ID']);
        exit();
    }
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get data before restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ? AND is_disabled = 1");
        $checkStmt->execute([$elId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Equipment location not found or not archived']);
            exit();
        }
        
        // Get the asset tag from the old data
        $assetTag = $oldData['asset_tag'];
        
        // Check if there's already an active equipment location record for this asset tag
        $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_location WHERE asset_tag = ? AND is_disabled = 0");
        $activeCheckStmt->execute([$assetTag]);
        $activeCount = $activeCheckStmt->fetchColumn();
        
        if ($activeCount > 0) {
            echo json_encode(['status' => 'warning', 'message' => 'An active equipment location record already exists for asset tag: ' . $assetTag]);
            exit();
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id = ? AND is_disabled = 1");
        $stmt->execute([$elId]);
        
        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $checkStmt->execute([$elId]);
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Insert into audit_log for equipment location
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
            $elId,
            'Equipment Location',
            'Restore',
            'Equipment location has been restored',
            json_encode($oldData),
            json_encode($newData),
            'Successful'
        ]);
        
        // Restore related equipment details records
        $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $detailsStmt->execute([$assetTag]);
        $detailsRowsAffected = $detailsStmt->rowCount();
        
        // Restore related equipment status records
        $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $statusStmt->execute([$assetTag]);
        $statusRowsAffected = $statusStmt->rowCount();
        
        // Log the cascaded restorations if any rows were affected
        if ($detailsRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $elId,
                'Equipment Details',
                'Restore',
                'Equipment details entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $detailsRowsAffected]),
                null,
                'Successful'
            ]);
        }
        
        if ($statusRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $elId,
                'Equipment Status',
                'Restore',
                'Equipment status entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $statusRowsAffected]),
                null,
                'Successful'
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Prepare response message
        $message = 'Equipment location restored successfully';
        if ($detailsRowsAffected > 0 || $statusRowsAffected > 0) {
            $message .= ', along with ' . 
                ($detailsRowsAffected > 0 ? $detailsRowsAffected . ' details record(s)' : '') . 
                ($detailsRowsAffected > 0 && $statusRowsAffected > 0 ? ' and ' : '') . 
                ($statusRowsAffected > 0 ? $statusRowsAffected . ' status record(s)' : '');
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
                $elId,
                'Equipment Location',
                'Restore',
                'Failed to restore equipment location: ' . $e->getMessage(),
                null,
                null,
                'Failed'
            ]);
        } catch (Exception $ex) {
            // Silently handle audit log failure
        }
        
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['el_ids']) && is_array($_POST['el_ids'])) {
    $elIds = array_filter(array_map('intval', $_POST['el_ids']));
    if(empty($elIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment location IDs provided']);
        exit();
    }
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get data before restoration for audit log
        $placeholders = implode(",", array_fill(0, count($elIds), '?'));
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($elIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($oldDataRecords)) {
            echo json_encode(['status' => 'error', 'message' => 'No equipment locations found or not archived']);
            exit();
        }
        
        // Store old data by ID for easier access
        $oldDataLookup = [];
        $assetTags = []; // Store asset tags for related records
        $skippedAssetTags = []; // Track skipped asset tags due to existing active records
        $validElIds = []; // Track valid equipment location IDs after checking for active records
        
        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['equipment_location_id']] = $record;
            $assetTags[$record['equipment_location_id']] = $record['asset_tag'];
            
            // Check if there's already an active equipment location record for this asset tag
            $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_location WHERE asset_tag = ? AND is_disabled = 0");
            $activeCheckStmt->execute([$record['asset_tag']]);
            $activeCount = $activeCheckStmt->fetchColumn();
            
            if ($activeCount > 0) {
                $skippedAssetTags[] = $record['asset_tag'];
            } else {
                $validElIds[] = $record['equipment_location_id'];
            }
        }
        
        // If no valid IDs after checking for active records, exit
        if (empty($validElIds)) {
            if (!empty($skippedAssetTags)) {
                echo json_encode(['status' => 'warning', 'message' => 'All selected equipment locations have active records with the same asset tags: ' . implode(', ', $skippedAssetTags)]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No valid equipment locations to restore']);
            }
            exit();
        }
        
        // Prepare placeholders for valid IDs
        $validPlaceholders = implode(",", array_fill(0, count($validElIds), '?'));
        
        // Perform the restore for valid equipment locations
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id IN ($validPlaceholders) AND is_disabled = 1");
        $stmt->execute($validElIds);
        
        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id IN ($validPlaceholders)");
        $checkStmt->execute($validElIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['equipment_location_id']] = $record;
        }
        
        // Track restored related records
        $restoredDetailsCount = 0;
        $restoredStatusCount = 0;
        
        // Log each restoration in audit_log and restore related records
        foreach ($validElIds as $id) {
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
                'Equipment Location',
                'Restore',
                'Equipment location has been restored',
                json_encode($oldData),
                json_encode($newDataLookup[$id] ?? null),
                'Successful'
            ]);
            
            // Restore related equipment details records
            $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $detailsStmt->execute([$assetTag]);
            $detailsRowsAffected = $detailsStmt->rowCount();
            $restoredDetailsCount += $detailsRowsAffected;
            
            // Restore related equipment status records
            $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $statusStmt->execute([$assetTag]);
            $statusRowsAffected = $statusStmt->rowCount();
            $restoredStatusCount += $statusRowsAffected;
            
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
            
            if ($statusRowsAffected > 0) {
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Status',
                    'Restore',
                    'Equipment status entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                    json_encode(['asset_tag' => $assetTag, 'rows_affected' => $statusRowsAffected]),
                    null,
                    'Successful'
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Prepare response message
        $message = count($validElIds) . ' equipment locations restored successfully';
        
        // Add info about related records
        if ($restoredDetailsCount > 0 || $restoredStatusCount > 0) {
            $message .= ', along with ' . 
                ($restoredDetailsCount > 0 ? $restoredDetailsCount . ' details record(s)' : '') . 
                ($restoredDetailsCount > 0 && $restoredStatusCount > 0 ? ' and ' : '') . 
                ($restoredStatusCount > 0 ? $restoredStatusCount . ' status record(s)' : '');
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
                'Equipment Location',
                'Restore',
                'Failed to restore multiple equipment locations: ' . $e->getMessage(),
                json_encode(['ids' => $elIds]),
                null,
                'Failed'
            ]);
        } catch (Exception $ex) {
            // Silently handle audit log failure
        }
        
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No equipment location selected']);
} 