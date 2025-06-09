<?php
/**
 * Delete Purchase Order Module
 *
 * This file manages the deletion of purchase orders from the Inventory Management System. It provides the backend logic to safely remove purchase order records, typically by marking them as deleted (soft delete) rather than permanently erasing them. This approach allows for potential recovery and auditing. The class ensures that all related data integrity checks are performed before deletion, and that only users with the appropriate permissions can execute this operation.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentTransactions
 * @author     TMDD Interns 25'
 */

/**
 * DeletePurchaseOrder Class
 *
 * Handles the logic for deleting purchase order records, including permission checks and data integrity validation. Supports soft deletion to allow for future restoration if needed.
 */
class DeletePurchaseOrder {
    /**
     * Database connection instance
     *
     * @var PDO
     */
    private $db;

    /**
     * Constructor
     *
     * @param PDO $db Database connection
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Delete a purchase order
     *
     * @param int $purchaseOrderId Purchase Order ID
     * @return bool Success status
     */
    public function delete($purchaseOrderId) {
        // ... existing code ...
    }
}

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
 * @param string $action The action being performed (e.g., 'Remove').
 * @param string $details Detailed description of the action.
 * @param string $status Status of the action (e.g., 'Successful' or 'Failed').
 * @param string $oldData The data before the action was performed.
 * @param int|null $entityId The ID of the entity being acted upon (optional).
 * @return void
 */
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
    /**
     * @var int|bool $poId
     * @brief Stores the purchase order ID after validation.
     *
     * This variable holds the validated ID of the purchase order to be deleted.
     */
    $poId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($poId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid purchase order ID']);
        exit();
    }
    try {
        // First check if the record exists and is already archived
        $checkStmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$poId]);
        /**
         * @var array|bool $oldData
         * @brief Stores the existing data of the purchase order.
         *
         * This variable holds the data of the purchase order before deletion for audit logging.
         */
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Purchase order not found or not archived']);
            exit();
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        try {
            // Clear po_no in charge_invoice for this PO
            if (!empty($oldData['po_no'])) {
                $poNo = $oldData['po_no'];
                // Find affected invoices
                $affectedInvoices = $pdo->prepare("SELECT * FROM charge_invoice WHERE po_no = ?");
                $affectedInvoices->execute([$poNo]);
                $affected = $affectedInvoices->fetchAll(PDO::FETCH_ASSOC);
                // Update po_no to NULL
                $upd = $pdo->prepare("UPDATE charge_invoice SET po_no = NULL WHERE po_no = ?");
                $upd->execute([$poNo]);
                // Audit each affected invoice
                foreach ($affected as $ci) {
                    logAudit(
                        $pdo,
                        'Modified',
                        json_encode($ci),
                        json_encode(array_merge($ci, ['po_no' => null])),
                        $ci['id'],
                        'PO reference cleared due to PO deletion',
                        'Successful'
                    );
                }
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
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Purchase order permanently deleted']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
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
    /**
     * @var array $poIds
     * @brief Stores the array of purchase order IDs to be deleted.
     *
     * This array contains the validated IDs of multiple purchase orders for bulk deletion.
     */
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
        
        // Begin transaction
        $pdo->beginTransaction();
        try {
            // Clear po_no in charge_invoice for each PO
            foreach ($oldDataRecords as $oldData) {
                if (!empty($oldData['po_no'])) {
                    $poNo = $oldData['po_no'];
                    // Find affected invoices
                    $affectedInvoices = $pdo->prepare("SELECT * FROM charge_invoice WHERE po_no = ?");
                    $affectedInvoices->execute([$poNo]);
                    $affected = $affectedInvoices->fetchAll(PDO::FETCH_ASSOC);
                    // Update po_no to NULL
                    $upd = $pdo->prepare("UPDATE charge_invoice SET po_no = NULL WHERE po_no = ?");
                    $upd->execute([$poNo]);
                    // Audit each affected invoice
                    foreach ($affected as $ci) {
                        logAudit(
                            $pdo,
                            'Modified',
                            json_encode($ci),
                            json_encode(array_merge($ci, ['po_no' => null])),
                            $ci['id'],
                            'PO reference cleared due to PO deletion',
                            'Successful'
                        );
                    }
                }
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
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Selected purchase orders permanently deleted']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }
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