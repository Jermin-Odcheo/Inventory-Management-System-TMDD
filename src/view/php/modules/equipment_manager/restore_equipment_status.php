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
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id = ? AND is_disabled = 1");
        $stmt->execute([$esId]);
        
        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
        $checkStmt->execute([$esId]);
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Insert into audit_log
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
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Equipment status restored successfully']);
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
        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['equipment_status_id']] = $record;
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($esIds);
        
        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id IN ($placeholders)");
        $checkStmt->execute($esIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['equipment_status_id']] = $record;
        }
        
        // Log each restoration in audit_log
        foreach ($oldDataLookup as $id => $oldData) {
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
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Selected equipment statuses restored successfully']);
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