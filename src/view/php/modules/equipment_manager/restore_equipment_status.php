<?php
/**
 * Restore Equipment Status Module
 *
 * This file provides functionality to restore previously modified equipment status in the system. It handles the recovery of status information, including historical records and change logs. The module ensures proper validation, user authorization, and maintains data consistency during the restoration process.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentManager
 * @author     TMDD Interns 25'
 */

session_start(); // Start the PHP session.
require_once('../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.

/**
 * Sets database session variables for the current user ID and IP address,
 * primarily for database-level auditing if triggers are configured.
 */
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

header('Content-Type: application/json'); // Set the content type to JSON for all responses.
header('X-Content-Type-Options: nosniff'); // Prevent browsers from MIME-sniffing a response away from the declared content type.

/**
 * Handles single equipment status restoration.
 * Checks if 'id' POST parameter is set.
 */
if (isset($_POST['id'])) {
    /**
     * @var int|false $esId The ID of the equipment status to restore, filtered as an integer.
     * False if validation fails.
     */
    $esId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($esId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment status ID']);
        exit();
    }
    try {
        $pdo->beginTransaction(); // Start a database transaction.

        // Get data before restoration for audit log
        /**
         * Fetches the old equipment status record before restoration for audit logging.
         *
         * @var PDOStatement $checkStmt The prepared SQL statement object.
         * @var array|false $oldData The fetched old status data, or false if not found.
         */
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ? AND is_disabled = 1");
        $checkStmt->execute([$esId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Equipment status not found or not archived']);
            exit();
        }

        // Get the asset tag from the equipment status
        $assetTag = $oldData['asset_tag'];

        // Check if there's already an active equipment status record for this asset tag
        /**
         * Checks for existing active equipment status records with the same asset tag.
         * Prevents restoration if an active record already exists.
         *
         * @var PDOStatement $activeCheckStmt The prepared SQL statement object.
         * @var int $activeCount The count of active records with the same asset tag.
         */
        $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_status WHERE asset_tag = ? AND is_disabled = 0");
        $activeCheckStmt->execute([$assetTag]);
        $activeCount = $activeCheckStmt->fetchColumn();

        if ($activeCount > 0) {
            echo json_encode(['status' => 'warning', 'message' => 'An active equipment status record with asset tag ' . $assetTag . ' already exists. Cannot restore.']);
            exit();
        }

        // Perform the restore of equipment status
        /**
         * Updates the `equipment_status` record to set `is_disabled` to 0 (restore).
         *
         * @var PDOStatement $stmt The prepared SQL statement object.
         */
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id = ? AND is_disabled = 1");
        $stmt->execute([$esId]);

        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
        $checkStmt->execute([$esId]);
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Insert into audit_log for equipment status
        /**
         * Inserts an audit log entry for the restoration of equipment status.
         *
         * @var PDOStatement $auditStmt The prepared SQL statement object for audit logs.
         */
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

        // Also restore related equipment details records
        /**
         * Updates `equipment_details` records with the same asset tag to set `is_disabled` to 0.
         *
         * @var PDOStatement $detailsStmt The prepared SQL statement object.
         * @var int $detailsRowsAffected The number of rows affected in `equipment_details`.
         */
        $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $detailsStmt->execute([$assetTag]);
        $detailsRowsAffected = $detailsStmt->rowCount();

        // Also restore related equipment location records
        /**
         * Updates `equipment_location` records with the same asset tag to set `is_disabled` to 0.
         *
         * @var PDOStatement $locationStmt The prepared SQL statement object.
         * @var int $locationRowsAffected The number of rows affected in `equipment_location`.
         */
        $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $locationStmt->execute([$assetTag]);
        $locationRowsAffected = $locationStmt->rowCount();

        // Log the cascaded restorations if any rows were affected
        if ($detailsRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $esId,
                'Equipment Details',
                'Restore',
                'Equipment details entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $detailsRowsAffected]),
                null,
                'Successful'
            ]);
        }

        if ($locationRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $esId,
                'Equipment Location',
                'Restore',
                'Equipment location entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $locationRowsAffected]),
                null,
                'Successful'
            ]);
        }

        $pdo->commit(); // Commit the transaction.

        // Prepare response message based on what was restored
        $message = 'Equipment status restored successfully';
        if ($detailsRowsAffected > 0 || $locationRowsAffected > 0) {
            $message .= ', along with ' .
                ($detailsRowsAffected > 0 ? $detailsRowsAffected . ' details record(s)' : '') .
                ($detailsRowsAffected > 0 && $locationRowsAffected > 0 ? ' and ' : '') .
                ($locationRowsAffected > 0 ? $locationRowsAffected . ' location record(s)' : '');
        }

        echo json_encode(['status' => 'success', 'message' => $message]);
    } catch (PDOException $e) {
        // Rollback transaction if error
        /**
         * Catches PDO exceptions (database errors). If a transaction is active, it rolls back
         * the transaction to prevent partial data changes. Then, it returns an error message.
         * It also attempts to log the failure in the audit log.
         */
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
    /**
     * Handles multiple equipment status restoration.
     * Checks if 'es_ids' POST parameter is an array of IDs.
     */
    $esIds = array_filter(array_map('intval', $_POST['es_ids']));
    if(empty($esIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment status IDs provided']);
        exit();
    }
    try {
        $pdo->beginTransaction(); // Start a database transaction.

        // Get data before restoration for audit log
        $placeholders = implode(",", array_fill(0, count($esIds), '?'));
        /**
         * Fetches old equipment status records for multiple IDs before restoration.
         *
         * @var PDOStatement $checkStmt The prepared SQL statement object.
         * @var array $oldDataRecords All fetched old status data.
         */
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($esIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($oldDataRecords)) {
            echo json_encode(['status' => 'error', 'message' => 'No equipment status found or not archived']);
            exit();
        }

        // Store old data by ID for easier access
        $oldDataLookup = [];
        $assetTags = []; // Store asset tags for related records
        $skippedAssetTags = []; // Track skipped asset tags due to existing active records
        $validEsIds = []; // Track valid equipment status IDs after checking for active records

        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['equipment_status_id']] = $record;
            $assetTags[$record['equipment_status_id']] = $record['asset_tag'];

            // Check if there's already an active equipment status record for this asset tag
            $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_status WHERE asset_tag = ? AND is_disabled = 0");
            $activeCheckStmt->execute([$record['asset_tag']]);
            $activeCount = $activeCheckStmt->fetchColumn();

            if ($activeCount > 0) {
                $skippedAssetTags[] = $record['asset_tag'];
            } else {
                $validEsIds[] = $record['equipment_status_id'];
            }
        }

        // If no valid IDs after checking for active records, exit
        if (empty($validEsIds)) {
            if (!empty($skippedAssetTags)) {
                echo json_encode(['status' => 'warning', 'message' => 'All selected equipment statuses have active records with the same asset tags: ' . implode(', ', $skippedAssetTags)]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No valid equipment statuses to restore']);
            }
            exit();
        }

        // Prepare placeholders for valid IDs
        $validPlaceholders = implode(",", array_fill(0, count($validEsIds), '?'));

        // Perform the restore for valid equipment statuses
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id IN ($validPlaceholders) AND is_disabled = 1");
        $stmt->execute($validEsIds);

        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id IN ($validPlaceholders)");
        $checkStmt->execute($validEsIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['equipment_status_id']] = $record;
        }

        // Track restored related records
        $restoredDetailsCount = 0;
        $restoredLocationCount = 0;

        // Log each restoration in audit_log and restore related records
        foreach ($validEsIds as $id) {
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
                'Equipment Status',
                'Restore',
                'Equipment status has been restored',
                json_encode($oldData),
                json_encode($newDataLookup[$id] ?? null),
                'Successful'
            ]);

            // Restore related equipment details records
            $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $detailsStmt->execute([$assetTag]);
            $detailsRowsAffected = $detailsStmt->rowCount();
            $restoredDetailsCount += $detailsRowsAffected;

            // Restore related equipment location records
            $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $locationStmt->execute([$assetTag]);
            $locationRowsAffected = $locationStmt->rowCount();
            $restoredLocationCount += $locationRowsAffected;

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

            if ($locationRowsAffected > 0) {
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Location',
                    'Restore',
                    'Equipment location entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                    json_encode(['asset_tag' => $assetTag, 'rows_affected' => $locationRowsAffected]),
                    null,
                    'Successful'
                ]);
            }
        }

        $pdo->commit(); // Commit the transaction.

        // Prepare response message for multiple restorations
        $message = count($validEsIds) . ' equipment status record(s) restored successfully';
        if (!empty($skippedAssetTags)) {
            $message .= '. Skipped asset tags with active records: ' . implode(', ', array_unique($skippedAssetTags));
        }
        if ($restoredDetailsCount > 0 || $restoredLocationCount > 0) {
            $message .= ', along with ' .
                ($restoredDetailsCount > 0 ? $restoredDetailsCount . ' details record(s)' : '') .
                ($restoredDetailsCount > 0 && $restoredLocationCount > 0 ? ' and ' : '') .
                ($restoredLocationCount > 0 ? $restoredLocationCount . ' location record(s)' : '');
        }

        echo json_encode(['status' => 'success', 'message' => $message]);
    } catch (PDOException $e) {
        // Rollback transaction if error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log failure in audit log for multiple restorations
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
                null, // No single EntityID for batch operation
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
    // Invalid request
    /**
     * If the required POST parameters are missing or invalid,
     * respond with a 400 Bad Request status and an error message, then exit.
     */
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request. Required parameters missing.']);
}
