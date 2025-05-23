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
    $edId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($edId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment details ID']);
        exit();
    }
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get data before restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$edId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Equipment details not found or not archived']);
            exit();
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$edId]);
        
        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
        $checkStmt->execute([$edId]);
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
            $edId,
            'Equipment Details',
            'Restored',
            'Equipment details have been restored',
            json_encode($oldData),
            json_encode($newData),
            'Successful'
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Equipment details restored successfully']);
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
                $edId,
                'Equipment Details',
                'Restored',
                'Failed to restore equipment details: ' . $e->getMessage(),
                null,
                null,
                'Failed'
            ]);
        } catch (Exception $ex) {
            // Silently handle audit log failure
        }
        
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['ed_ids']) && is_array($_POST['ed_ids'])) {
    $edIds = array_filter(array_map('intval', $_POST['ed_ids']));
    if(empty($edIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment details IDs provided']);
        exit();
    }
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get data before restoration for audit log
        $placeholders = implode(",", array_fill(0, count($edIds), '?'));
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($edIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($oldDataRecords)) {
            echo json_encode(['status' => 'error', 'message' => 'No equipment details found or not archived']);
            exit();
        }
        
        // Store old data by ID for easier access
        $oldDataLookup = [];
        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['id']] = $record;
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($edIds);
        
        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id IN ($placeholders)");
        $checkStmt->execute($edIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['id']] = $record;
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
                'Equipment Details',
                'Restored',
                'Equipment details have been restored',
                json_encode($oldData),
                json_encode($newDataLookup[$id] ?? null),
                'Successful'
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Selected equipment details restored successfully']);
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
                'Equipment Details',
                'Restored',
                'Failed to restore multiple equipment details: ' . $e->getMessage(),
                json_encode(['ids' => $edIds]),
                null,
                'Failed'
            ]);
        } catch (Exception $ex) {
            // Silently handle audit log failure
        }
        
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No equipment details selected']);
} 