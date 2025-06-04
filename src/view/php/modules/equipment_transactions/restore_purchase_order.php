<?php
/**
 * @file restore_purchase_order.php
 * @brief Handles the restoration of purchase orders.
 *
 * This script processes requests to restore archived purchase orders in the database,
 * updating their status and logging the actions for audit purposes.
 */
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address for logging.
/**
 * @var string $ipAddress
 * @brief Stores the IP address of the client for logging purposes.
 *
 * This variable holds the remote IP address of the client making the request.
 */
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Function to log audit events
/**
 * @brief Logs audit events for actions performed on purchase orders.
 * @param \PDO $pdo Database connection object.
 * @param string $action The action being performed (e.g., 'restored').
 * @param string $details Detailed description of the action.
 * @param string $status Status of the action (e.g., 'Successful' or 'Failed').
 * @param string $oldData The data before the action was performed.
 * @param string $newData The data after the action was performed.
 * @param int|null $entityId The ID of the entity being acted upon (optional).
 * @return void
 */
function logAudit($pdo, $action, $details, $status, $oldData, $newData, $entityId = null)
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, Status, OldVal, NewVal, Date_Time)
        VALUES (?, ?, 'Purchase Order', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $details, $status, $oldData, $newData]);
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id'])) {
    /**
     * @var int|bool $poId
     * @brief Stores the purchase order ID after validation.
     *
     * This variable holds the validated ID of the purchase order to be restored.
     */
    $poId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($poId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid purchase order ID']);
        exit();
    }
    try {
        // Get the data before restoration for audit
        $checkStmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$poId]);
        /**
         * @var array|bool $oldData
         * @brief Stores the existing data of the purchase order before restoration.
         *
         * This variable holds the data of the purchase order before restoration for audit logging.
         */
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase order not found or not archived']);
            exit();
        }
        
        // Update only if the purchase order is archived (is_disabled = 1)
        $stmt = $pdo->prepare("UPDATE purchase_order SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$poId]);
        
        // Fetch updated data for audit
        $checkStmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ?");
        $checkStmt->execute([$poId]);
        /**
         * @var array|bool $newData
         * @brief Stores the updated data of the purchase order after restoration.
         *
         * This variable holds the data of the purchase order after restoration for audit logging.
         */
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log the restore action
        logAudit(
            $pdo,
            'restored',
            'Purchase Order "' . ($oldData['po_no'] ?? 'ID: '.$poId) . '" has been restored',
            'Successful',
            json_encode($oldData),
            json_encode($newData),
            $poId
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Purchase order restored successfully']);
    } catch (PDOException $e) {
        // Check for duplicate entry errors (integrity constraint violations)
        if ($e->getCode() == '23000' && 
            (strpos($e->getMessage(), 'Duplicate entry') !== false || 
             strpos($e->getMessage(), 'Integrity constraint violation: 1062') !== false)) {
            
            // Log the error with a more specific message
            logAudit(
                $pdo,
                'restored',
                'Purchase Order ID: ' . $poId . ' restoration failed - A document with the same PO number already exists',
                'Failed',
                json_encode(['id' => $poId, 'error' => $e->getMessage()]),
                null,
                $poId
            );
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'A document with the same PO number already exists in the system. Please check existing purchase orders before restoring.'
            ]);
        } else {
            // Log the general error
            logAudit(
                $pdo,
                'restored',
                'Purchase Order ID: ' . $poId . ' restoration failed',
                'Failed',
                json_encode(['id' => $poId, 'error' => $e->getMessage()]),
                null,
                $poId
            );
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} else if (isset($_POST['po_ids']) && is_array($_POST['po_ids'])) {
    /**
     * @var array $poIds
     * @brief Stores the array of purchase order IDs to be restored.
     *
     * This array contains the validated IDs of multiple purchase orders for bulk restoration.
     */
    $poIds = array_filter(array_map('intval', $_POST['po_ids']));
    if(empty($poIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid purchase order IDs provided']);
        exit();
    }
    try {
        // Fetch data for all IDs to be restored
        $placeholders = implode(",", array_fill(0, count($poIds), '?'));
        $checkStmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($poIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($oldDataRecords) != count($poIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Some purchase orders not found or not archived']);
            exit();
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE purchase_order SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($poIds);
        
        // Fetch updated data
        $checkStmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id IN ($placeholders)");
        $checkStmt->execute($poIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Create a lookup array for quick access to new data
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['id']] = $record;
        }
        
        // Log each restoration separately for proper auditing
        foreach ($oldDataRecords as $oldData) {
            $entityId = $oldData['id'];
            $newData = $newDataLookup[$entityId] ?? null;
            
            logAudit(
                $pdo,
                'restored',
                'Purchase Order "' . ($oldData['po_no'] ?? 'ID: '.$entityId) . '" has been restored',
                'Successful',
                json_encode($oldData),
                json_encode($newData),
                $entityId
            );
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Selected purchase orders restored successfully']);
    } catch (PDOException $e) {
        // Check for duplicate entry errors (integrity constraint violations)
        if ($e->getCode() == '23000' && 
            (strpos($e->getMessage(), 'Duplicate entry') !== false || 
             strpos($e->getMessage(), 'Integrity constraint violation: 1062') !== false)) {
            
            // Log the error with a more specific message
            logAudit(
                $pdo,
                'restored',
                'Bulk Purchase Order restoration failed for IDs: ' . implode(', ', $poIds) . ' - Documents with the same PO numbers already exist',
                'Failed',
                json_encode(['ids' => implode(',', $poIds), 'error' => $e->getMessage()]),
                null,
                null
            );
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'One or more purchase orders cannot be restored because documents with the same PO numbers already exist in the system.'
            ]);
        } else {
            // Log the general error
            logAudit(
                $pdo,
                'restored',
                'Bulk Purchase Order restoration failed for IDs: ' . implode(', ', $poIds),
                'Failed',
                json_encode(['ids' => implode(',', $poIds), 'error' => $e->getMessage()]),
                null,
                null
            );
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No purchase order selected']);
}
?> 