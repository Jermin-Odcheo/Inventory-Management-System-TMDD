<?php
/**
 * Restore Receiving Report Module
 *
 * This file provides the logic to restore previously deleted receiving reports in the system. It is used to recover receiving report records that were soft-deleted, ensuring that accidental deletions can be reversed. The class interacts with the database to update the status of a receiving report, making it active again and available for use in the system.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentTransactions
 * @author     TMDD Interns 25'
 */

/**
 * RestoreReceivingReport Class
 *
 * Handles the restoration of deleted receiving report records by updating their status in the database. This class provides a method to reinstate receiving reports, supporting data integrity and recovery operations.
 */
class RestoreReceivingReport {
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
     * Restore a deleted receiving report
     *
     * @param int $receivingReportId Receiving Report ID
     * @return bool Success status
     */
    public function restore($receivingReportId) {
        // ... existing code ...
    }
}

session_start();
require_once('../../../../../config/ims-tmdd.php');

if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}
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
 * @brief Logs audit events for actions performed on receiving reports.
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
        VALUES (?, ?, 'Receiving Report', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $details, $status, $oldData, $newData]);
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id'])) {
    /**
     * @var int|bool $rrId
     * @brief Stores the receiving report ID after validation.
     *
     * This variable holds the validated ID of the receiving report to be restored.
     */
    $rrId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($rrId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid receiving report ID']);
        exit();
    }
    try {
        // Get the data before restoration for audit
        $checkStmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$rrId]);
        /**
         * @var array|bool $oldData
         * @brief Stores the existing data of the receiving report before restoration.
         *
         * This variable holds the data of the receiving report before restoration for audit logging.
         */
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Receiving report not found or not archived']);
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE receive_report SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$rrId]);
        
        // Fetch updated data for audit
        $checkStmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ?");
        $checkStmt->execute([$rrId]);
        /**
         * @var array|bool $newData
         * @brief Stores the updated data of the receiving report after restoration.
         *
         * This variable holds the data of the receiving report after restoration for audit logging.
         */
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log the restore action
        logAudit(
            $pdo,
            'restored',
            'Receiving Report "' . ($oldData['rr_no'] ?? 'ID: '.$rrId) . '" has been restored',
            'Successful',
            json_encode($oldData),
            json_encode($newData),
            $rrId
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Receiving report restored successfully']);
    } catch (PDOException $e) {
        // Check for duplicate entry errors (integrity constraint violations)
        if ($e->getCode() == '23000' && 
            (strpos($e->getMessage(), 'Duplicate entry') !== false || 
             strpos($e->getMessage(), 'Integrity constraint violation: 1062') !== false)) {
            
            // Log the error with a more specific message
            logAudit(
                $pdo,
                'restored',
                'Receiving Report ID: ' . $rrId . ' restoration failed - A document with the same RR number already exists',
                'Failed',
                json_encode(['id' => $rrId, 'error' => $e->getMessage()]),
                null,
                $rrId
            );
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'A document with the same RR number already exists in the system. Please check existing receiving reports before restoring.'
            ]);
        } else {
            // Log the general error
            logAudit(
                $pdo,
                'restored',
                'Receiving Report ID: ' . $rrId . ' restoration failed',
                'Failed',
                json_encode(['id' => $rrId, 'error' => $e->getMessage()]),
                null,
                $rrId
            );
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} else if (isset($_POST['rr_ids']) && is_array($_POST['rr_ids'])) {
    /**
     * @var array $rrIds
     * @brief Stores the array of receiving report IDs to be restored.
     *
     * This array contains the validated IDs of multiple receiving reports for bulk restoration.
     */
    $rrIds = array_filter(array_map('intval', $_POST['rr_ids']));
    if(empty($rrIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid receiving report IDs provided']);
        exit();
    }
    try {
        // Fetch data for all IDs to be restored
        $placeholders = implode(",", array_fill(0, count($rrIds), '?'));
        $checkStmt = $pdo->prepare("SELECT * FROM receive_report WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($rrIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($oldDataRecords) != count($rrIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Some receiving reports not found or not archived']);
            exit();
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE receive_report SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($rrIds);
        
        // Fetch updated data
        $checkStmt = $pdo->prepare("SELECT * FROM receive_report WHERE id IN ($placeholders)");
        $checkStmt->execute($rrIds);
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
                'Receiving Report "' . ($oldData['rr_no'] ?? 'ID: '.$entityId) . '" has been restored',
                'Successful',
                json_encode($oldData),
                json_encode($newData),
                $entityId
            );
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Selected receiving reports restored successfully']);
    } catch (PDOException $e) {
        // Check for duplicate entry errors (integrity constraint violations)
        if ($e->getCode() == '23000' && 
            (strpos($e->getMessage(), 'Duplicate entry') !== false || 
             strpos($e->getMessage(), 'Integrity constraint violation: 1062') !== false)) {
            
            // Log the error with a more specific message
            logAudit(
                $pdo,
                'restored',
                'Bulk Receiving Report restoration failed for IDs: ' . implode(', ', $rrIds) . ' - Documents with the same RR numbers already exist',
                'Failed',
                json_encode(['ids' => implode(',', $rrIds), 'error' => $e->getMessage()]),
                null,
                null
            );
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'One or more receiving reports cannot be restored because documents with the same RR numbers already exist in the system.'
            ]);
        } else {
            // Log the general error
            logAudit(
                $pdo,
                'restored',
                'Bulk Receiving Report restoration failed for IDs: ' . implode(', ', $rrIds),
                'Failed',
                json_encode(['ids' => implode(',', $rrIds), 'error' => $e->getMessage()]),
                null,
                null
            );
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No receiving report selected']);
} 