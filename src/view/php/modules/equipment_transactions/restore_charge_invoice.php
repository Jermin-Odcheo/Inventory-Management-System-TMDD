<?php
/**
 * @file restore_charge_invoice.php
 * @brief Handles the restoration of charge invoices.
 *
 * This script processes requests to restore archived charge invoices in the database,
 * updating their status and logging the actions for audit purposes.
 */
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
 * @brief Logs audit events for actions performed on charge invoices.
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
        VALUES (?, ?, 'Charge Invoice', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $details, $status, $oldData, $newData]);
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id'])) {
    /**
     * @var int|bool $ciId
     * @brief Stores the charge invoice ID after validation.
     *
     * This variable holds the validated ID of the charge invoice to be restored.
     */
    $ciId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($ciId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid charge invoice ID']);
        exit();
    }
    try {
        // Get the data before restoration for audit
        $checkStmt = $pdo->prepare("SELECT * FROM charge_invoice WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$ciId]);
        /**
         * @var array|bool $oldData
         * @brief Stores the existing data of the charge invoice before restoration.
         *
         * This variable holds the data of the charge invoice before restoration for audit logging.
         */
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Charge invoice not found or not archived']);
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE charge_invoice SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$ciId]);
        
        // Fetch updated data for audit
        $checkStmt = $pdo->prepare("SELECT * FROM charge_invoice WHERE id = ?");
        $checkStmt->execute([$ciId]);
        /**
         * @var array|bool $newData
         * @brief Stores the updated data of the charge invoice after restoration.
         *
         * This variable holds the data of the charge invoice after restoration for audit logging.
         */
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log the restore action
        logAudit(
            $pdo,
            'restored',
            'Charge Invoice "' . ($oldData['ci_no'] ?? 'ID: '.$ciId) . '" has been restored',
            'Successful',
            json_encode($oldData),
            json_encode($newData),
            $ciId
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Charge invoice restored successfully']);
    } catch (PDOException $e) {
        // Check for duplicate entry errors (integrity constraint violations)
        if ($e->getCode() == '23000' && 
            (strpos($e->getMessage(), 'Duplicate entry') !== false || 
             strpos($e->getMessage(), 'Integrity constraint violation: 1062') !== false)) {
            
            // Log the error with a more specific message
            logAudit(
                $pdo,
                'restored',
                'Charge Invoice ID: ' . $ciId . ' restoration failed - A document with the same CI number already exists',
                'Failed',
                json_encode(['id' => $ciId, 'error' => $e->getMessage()]),
                null,
                $ciId
            );
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'A document with the same CI number already exists in the system. Please check existing charge invoices before restoring.'
            ]);
        } else {
            // Log the general error
            logAudit(
                $pdo,
                'restored',
                'Charge Invoice ID: ' . $ciId . ' restoration failed',
                'Failed',
                json_encode(['id' => $ciId, 'error' => $e->getMessage()]),
                null,
                $ciId
            );
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} else if (isset($_POST['ci_ids']) && is_array($_POST['ci_ids'])) {
    /**
     * @var array $ciIds
     * @brief Stores the array of charge invoice IDs to be restored.
     *
     * This array contains the validated IDs of multiple charge invoices for bulk restoration.
     */
    $ciIds = array_filter(array_map('intval', $_POST['ci_ids']));
    if(empty($ciIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid charge invoice IDs provided']);
        exit();
    }
    try {
        // Fetch data for all IDs to be restored
        $placeholders = implode(",", array_fill(0, count($ciIds), '?'));
        $checkStmt = $pdo->prepare("SELECT * FROM charge_invoice WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($ciIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($oldDataRecords) != count($ciIds)) {
            echo json_encode(['status' => 'error', 'message' => 'Some charge invoices not found or not archived']);
            exit();
        }
        
        // Perform the restore
        $stmt = $pdo->prepare("UPDATE charge_invoice SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($ciIds);
        
        // Fetch updated data
        $checkStmt = $pdo->prepare("SELECT * FROM charge_invoice WHERE id IN ($placeholders)");
        $checkStmt->execute($ciIds);
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
                'Charge Invoice "' . ($oldData['ci_no'] ?? 'ID: '.$entityId) . '" has been restored',
                'Successful',
                json_encode($oldData),
                json_encode($newData),
                $entityId
            );
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Selected charge invoices restored successfully']);
    } catch (PDOException $e) {
        // Check for duplicate entry errors (integrity constraint violations)
        if ($e->getCode() == '23000' && 
            (strpos($e->getMessage(), 'Duplicate entry') !== false || 
             strpos($e->getMessage(), 'Integrity constraint violation: 1062') !== false)) {
            
            // Log the error with a more specific message
            logAudit(
                $pdo,
                'restored',
                'Bulk Charge Invoice restoration failed for IDs: ' . implode(', ', $ciIds) . ' - Documents with the same CI numbers already exist',
                'Failed',
                json_encode(['ids' => implode(',', $ciIds), 'error' => $e->getMessage()]),
                null,
                null
            );
            
            echo json_encode([
                'status' => 'error', 
                'message' => 'One or more charge invoices cannot be restored because documents with the same CI numbers already exist in the system.'
            ]);
        } else {
            // Log the general error
            logAudit(
                $pdo,
                'restored',
                'Bulk Charge Invoice restoration failed for IDs: ' . implode(', ', $ciIds),
                'Failed',
                json_encode(['ids' => implode(',', $ciIds), 'error' => $e->getMessage()]),
                null,
                null
            );
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No charge invoice selected']);
} 