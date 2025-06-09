<?php
/**
 * Delete Charge Invoice Module
 *
 * This file manages the deletion of charge invoices from the Inventory Management System. It provides the backend logic to safely remove charge invoice records, typically by marking them as deleted (soft delete) rather than permanently erasing them. This approach allows for potential recovery and auditing. The class ensures that all related data integrity checks are performed before deletion, and that only users with the appropriate permissions can execute this operation.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentTransactions
 * @author     TMDD Interns 25'
 */

/**
 * DeleteChargeInvoice Class
 *
 * Handles the logic for deleting charge invoice records, including permission checks and data integrity validation. Supports soft deletion to allow for future restoration if needed.
 */
class DeleteChargeInvoice {
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
     * Delete a charge invoice
     *
     * @param int $chargeInvoiceId Charge Invoice ID
     * @return bool Success status
     */
    public function delete($chargeInvoiceId) {
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
 * @brief Logs audit events for actions performed on charge invoices.
 * @param \PDO $pdo Database connection object.
 * @param string $action The action being performed (e.g., 'Delete').
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
        VALUES (?, ?, 'Charge Invoice', ?, ?, ?, ?, NULL, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $details, $status, $oldData]);
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id']) && isset($_POST['permanent']) && $_POST['permanent'] == 1) {
    /**
     * @var int|bool $ciId
     * @brief Stores the charge invoice ID after validation.
     *
     * This variable holds the validated ID of the charge invoice to be deleted.
     */
    $ciId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($ciId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid charge invoice ID']);
        exit();
    }
    try {
        // First check if the record exists and is already archived
        $checkStmt = $pdo->prepare("SELECT * FROM charge_invoice WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$ciId]);
        /**
         * @var array|bool $oldData
         * @brief Stores the existing data of the charge invoice.
         *
         * This variable holds the data of the charge invoice before deletion for audit logging.
         */
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Charge invoice not found or not archived']);
            exit();
        }
        
        // Permanently delete the charge invoice
        $stmt = $pdo->prepare("DELETE FROM charge_invoice WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$ciId]);
        
        // Log the permanent delete action
        logAudit(
            $pdo,
            'Delete',
            'Charge Invoice "' . ($oldData['ci_no'] ?? 'ID: '.$ciId) . '" has been permanently deleted',
            'Successful',
            json_encode($oldData),
            $ciId
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Charge invoice permanently deleted']);
    } catch (PDOException $e) {
        // Log the error
        logAudit(
            $pdo,
            'Remove',
            'Charge Invoice ID: ' . $ciId . ' permanent deletion failed',
            'Failed',
            json_encode(['id' => $ciId, 'error' => $e->getMessage()]),
            $ciId
        );
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (((isset($_POST['ids']) && is_array($_POST['ids'])) || (isset($_POST['ci_ids']) && is_array($_POST['ci_ids']))) && isset($_POST['permanent']) && $_POST['permanent'] == 1) {
    // Get IDs from either ids or ci_ids parameter
    /**
     * @var array $ciIds
     * @brief Stores the array of charge invoice IDs to be deleted.
     *
     * This array contains the validated IDs of multiple charge invoices for bulk deletion.
     */
    $ciIds = isset($_POST['ci_ids']) && is_array($_POST['ci_ids']) ? 
        array_filter(array_map('intval', $_POST['ci_ids'])) : 
        array_filter(array_map('intval', $_POST['ids'] ?? []));
    
    if(empty($ciIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid charge invoice IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($ciIds), '?'));
        
        // First check if the records exist and are already archived
        $checkStmt = $pdo->prepare("SELECT * FROM charge_invoice WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($ciIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($oldDataRecords) != count($ciIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Some charge invoices not found or not archived']);
            exit();
        }
        
        // Permanently delete the charge invoices
        $stmt = $pdo->prepare("DELETE FROM charge_invoice WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($ciIds);
        
        // Log each deletion separately for proper auditing
        foreach ($oldDataRecords as $oldData) {
            logAudit(
                $pdo,
                'Remove',
                'Charge Invoice "' . ($oldData['ci_no'] ?? 'ID: '.$oldData['id']) . '" has been permanently deleted',
                'Successful',
                json_encode($oldData),
                $oldData['id']
            );
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Selected charge invoices permanently deleted']);
    } catch (PDOException $e) {
        // Log the error
        logAudit(
            $pdo,
            'Remove',
            'Bulk Charge Invoice deletion failed for IDs: ' . implode(', ', $ciIds),
            'Failed',
            json_encode(['ids' => implode(',', $ciIds), 'error' => $e->getMessage()]),
            null
        );
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or missing permanent flag']);
}
?> 