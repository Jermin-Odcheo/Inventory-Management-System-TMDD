<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address for logging.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Function to log audit events
function logAudit($pdo, $action, $details, $status, $oldData, $entityId = null)
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, Status, OldVal, NewVal, Date_Time)
        VALUES (?, ?, 'Purchase Order', ?, ?, ?, ?, NULL, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $details, $status, $oldData]);
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id']) && isset($_POST['permanent']) && $_POST['permanent'] == 1) {
    $poId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($poId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid purchase order ID']);
        exit();
    }
    try {
        // First check if the record exists and is already archived
        $checkStmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$poId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase order not found or not archived']);
            exit();
        }
        
        // Permanently delete the purchase order
        $stmt = $pdo->prepare("DELETE FROM purchase_order WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$poId]);
        
        // Log the permanent delete action
        logAudit(
            $pdo,
            'Remove',
            'Purchase Order "' . ($oldData['po_no'] ?? 'ID: '.$poId) . '" has been permanently deleted',
            'Successful',
            json_encode($oldData),
            $poId
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Purchase order permanently deleted']);
    } catch (PDOException $e) {
        // Log the error
        logAudit(
            $pdo,
            'Remove',
            'Purchase Order ID: ' . $poId . ' permanent deletion failed',
            'Failed',
            json_encode(['id' => $poId, 'error' => $e->getMessage()]),
            $poId
        );
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (((isset($_POST['ids']) && is_array($_POST['ids'])) || (isset($_POST['po_ids']) && is_array($_POST['po_ids']))) && isset($_POST['permanent']) && $_POST['permanent'] == 1) {
    // Get IDs from either ids or po_ids parameter
    $poIds = isset($_POST['po_ids']) && is_array($_POST['po_ids']) ? 
        array_filter(array_map('intval', $_POST['po_ids'])) : 
        array_filter(array_map('intval', $_POST['ids'] ?? []));
    
    if(empty($poIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid purchase order IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($poIds), '?'));
        
        // First check if the records exist and are already archived
        $checkStmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($poIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($oldDataRecords) != count($poIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Some purchase orders not found or not archived']);
            exit();
        }
        
        // Permanently delete the purchase orders
        $stmt = $pdo->prepare("DELETE FROM purchase_order WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($poIds);
        
        // Log each deletion separately for proper auditing
        foreach ($oldDataRecords as $oldData) {
            logAudit(
                $pdo,
                'Remove',
                'Purchase Order "' . ($oldData['po_no'] ?? 'ID: '.$oldData['id']) . '" has been permanently deleted',
                'Successful',
                json_encode($oldData),
                $oldData['id']
            );
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Selected purchase orders permanently deleted']);
    } catch (PDOException $e) {
        // Log the error
        logAudit(
            $pdo,
            'Remove',
            'Bulk Purchase Order deletion failed for IDs: ' . implode(', ', $poIds),
            'Failed',
            json_encode(['ids' => implode(',', $poIds), 'error' => $e->getMessage()]),
            null
        );
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing permanent flag']);
}
?> 