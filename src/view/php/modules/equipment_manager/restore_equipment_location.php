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
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id = ? AND is_disabled = 1");
        $stmt->execute([$elId]);
        
        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $checkStmt->execute([$elId]);
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
            $elId,
            'Equipment Location',
            'Restore',
            'Equipment location has been restored',
            json_encode($oldData),
            json_encode($newData),
            'Successful'
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Equipment location restored successfully']);
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
        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['equipment_location_id']] = $record;
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($elIds);
        
        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id IN ($placeholders)");
        $checkStmt->execute($elIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['equipment_location_id']] = $record;
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
                'Equipment Location',
                'Restore',
                'Equipment location has been restored',
                json_encode($oldData),
                json_encode($newDataLookup[$id] ?? null),
                'Successful'
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Selected equipment locations restored successfully']);
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