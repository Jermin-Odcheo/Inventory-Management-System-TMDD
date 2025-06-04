<?php
/**
 * @file restore_equipment_location.php
 * @brief Handles the restoration of soft-deleted equipment location records.
 *
 * This script processes POST requests to restore one or more equipment location records
 * (by setting `is_disabled` to 0). It also cascades the restoration to related
 * `equipment_details` and `equipment_status` records with the same asset tag.
 * It includes checks for existing active records to prevent duplicates and logs all
 * restoration actions in the `audit_log` table within a database transaction.
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
 * Handles single equipment location restoration.
 * Checks if 'id' POST parameter is set.
 */
if (isset($_POST['id'])) {
    /**
     * @var int|false $elId The ID of the equipment location to restore, filtered as an integer.
     * False if validation fails.
     */
    $elId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($elId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment location ID']);
        exit();
    }
    try {
        $pdo->beginTransaction(); // Start a database transaction.

        // Get data before restoration for audit log
        /**
         * Fetches the old equipment location record before restoration for audit logging.
         *
         * @var PDOStatement $checkStmt The prepared SQL statement object.
         * @var array|false $oldData The fetched old location data, or false if not found.
         */
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ? AND is_disabled = 1");
        $checkStmt->execute([$elId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Equipment location not found or not archived']);
            exit();
        }

        // Get the asset tag from the old data
        $assetTag = $oldData['asset_tag'];

        // Check if there's already an active equipment location record for this asset tag
        /**
         * Checks for existing active equipment location records with the same asset tag.
         * Prevents restoration if an active record already exists.
         *
         * @var PDOStatement $activeCheckStmt The prepared SQL statement object.
         * @var int $activeCount The count of active records with the same asset tag.
         */
        $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_location WHERE asset_tag = ? AND is_disabled = 0");
        $activeCheckStmt->execute([$assetTag]);
        $activeCount = $activeCheckStmt->fetchColumn();

        if ($activeCount > 0) {
            echo json_encode(['status' => 'warning', 'message' => 'An active equipment location record already exists for asset tag: ' . $assetTag]);
            exit();
        }

        // Perform the restore
        /**
         * Updates the `equipment_location` record to set `is_disabled` to 0 (restore).
         *
         * @var PDOStatement $stmt The prepared SQL statement object.
         */
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id = ? AND is_disabled = 1");
        $stmt->execute([$elId]);

        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $checkStmt->execute([$elId]);
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Insert into audit_log for equipment location
        /**
         * Inserts an audit log entry for the restoration of equipment location.
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
            $elId,
            'Equipment Location',
            'Restore',
            'Equipment location has been restored',
            json_encode($oldData),
            json_encode($newData),
            'Successful'
        ]);

        // Restore related equipment details records
        /**
         * Updates `equipment_details` records with the same asset tag to set `is_disabled` to 0.
         *
         * @var PDOStatement $detailsStmt The prepared SQL statement object.
         * @var int $detailsRowsAffected The number of rows affected in `equipment_details`.
         */
        $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $detailsStmt->execute([$assetTag]);
        $detailsRowsAffected = $detailsStmt->rowCount();

        // Restore related equipment status records
        /**
         * Updates `equipment_status` records with the same asset tag to set `is_disabled` to 0.
         *
         * @var PDOStatement $statusStmt The prepared SQL statement object.
         * @var int $statusRowsAffected The number of rows affected in `equipment_status`.
         */
        $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $statusStmt->execute([$assetTag]);
        $statusRowsAffected = $statusStmt->rowCount();

        // Log the cascaded restorations if any rows were affected
        if ($detailsRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $elId,
                'Equipment Details',
                'Restore',
                'Equipment details entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $detailsRowsAffected]),
                null,
                'Successful'
            ]);
        }

        if ($statusRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $elId,
                'Equipment Status',
                'Restore',
                'Equipment status entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $statusRowsAffected]),
                null,
                'Successful'
            ]);
        }

        $pdo->commit(); // Commit the transaction.

        // Prepare response message
        $message = 'Equipment location restored successfully';
        if ($detailsRowsAffected > 0 || $statusRowsAffected > 0) {
            $message .= ', along with ' .
                ($detailsRowsAffected > 0 ? $detailsRowsAffected . ' details record(s)' : '') .
                ($detailsRowsAffected > 0 && $statusRowsAffected > 0 ? ' and ' : '') .
                ($statusRowsAffected > 0 ? $statusRowsAffected . ' status record(s)' : '');
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
    /**
     * Handles multiple equipment location restoration.
     * Checks if 'el_ids' POST parameter is an array of IDs.
     */
    $elIds = array_filter(array_map('intval', $_POST['el_ids']));
    if(empty($elIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment location IDs provided']);
        exit();
    }
    try {
        $pdo->beginTransaction(); // Start a database transaction.

        // Get data before restoration for audit log
        $placeholders = implode(",", array_fill(0, count($elIds), '?'));
        /**
         * Fetches old equipment location records for multiple IDs before restoration.
         *
         * @var PDOStatement $checkStmt The prepared SQL statement object.
         * @var array $oldDataRecords All fetched old location data.
         */
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($elIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($oldDataRecords)) {
            echo json_encode(['status' => 'error', 'message' => 'No equipment locations found or not archived']);
            exit();
        }

        // Store old data by ID for easier access
        $oldDataLookup = [];
        $assetTags = []; // Store asset tags for related records
        $skippedAssetTags = []; // Track skipped asset tags due to existing active records
        $validElIds = []; // Track valid equipment location IDs after checking for active records

        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['equipment_location_id']] = $record;
            $assetTags[$record['equipment_location_id']] = $record['asset_tag'];

            // Check if there's already an active equipment location record for this asset tag
            $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_location WHERE asset_tag = ? AND is_disabled = 0");
            $activeCheckStmt->execute([$record['asset_tag']]);
            $activeCount = $activeCheckStmt->fetchColumn();

            if ($activeCount > 0) {
                $skippedAssetTags[] = $record['asset_tag'];
            } else {
                $validElIds[] = $record['equipment_location_id'];
            }
        }

        // If no valid IDs after checking for active records, exit
        if (empty($validElIds)) {
            if (!empty($skippedAssetTags)) {
                echo json_encode(['status' => 'warning', 'message' => 'All selected equipment locations have active records with the same asset tags: ' . implode(', ', $skippedAssetTags)]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No valid equipment locations to restore']);
            }
            exit();
        }

        // Prepare placeholders for valid IDs
        $validPlaceholders = implode(",", array_fill(0, count($validElIds), '?'));

        // Perform the restore for valid equipment locations
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id IN ($validPlaceholders) AND is_disabled = 1");
        $stmt->execute($validElIds);

        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id IN ($validPlaceholders)");
        $checkStmt->execute($validElIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['equipment_location_id']] = $record;
        }

        // Track restored related records
        $restoredDetailsCount = 0;
        $restoredStatusCount = 0;

        // Log each restoration in audit_log and restore related records
        foreach ($validElIds as $id) {
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
                'Equipment Location',
                'Restore',
                'Equipment location has been restored',
                json_encode($oldData),
                json_encode($newDataLookup[$id] ?? null),
                'Successful'
            ]);

            // Restore related equipment details records
            $detailsStmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $detailsStmt->execute([$assetTag]);
            $detailsRowsAffected = $detailsStmt->rowCount();
            $restoredDetailsCount += $detailsRowsAffected;

            // Restore related equipment status records
            $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $statusStmt->execute([$assetTag]);
            $statusRowsAffected = $statusStmt->rowCount();
            $restoredStatusCount += $statusRowsAffected;

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

            if ($statusRowsAffected > 0) {
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Status',
                    'Restore',
                    'Equipment status entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                    json_encode(['asset_tag' => $assetTag, 'rows_affected' => $statusRowsAffected]),
                    null,
                    'Successful'
                ]);
            }
        }

        $pdo->commit(); // Commit the transaction.

        // Prepare response message
        $message = count($validElIds) . ' equipment locations restored successfully';

        // Add info about related records
        if ($restoredDetailsCount > 0 || $restoredStatusCount > 0) {
            $message .= ', along with ' .
                ($restoredDetailsCount > 0 ? $restoredDetailsCount . ' details record(s)' : '') .
                ($restoredDetailsCount > 0 && $restoredStatusCount > 0 ? ' and ' : '') .
                ($restoredStatusCount > 0 ? $restoredStatusCount . ' status record(s)' : '');
        }

        // Add info about skipped records
        if (!empty($skippedAssetTags)) {
            $message .= '. ' . count($skippedAssetTags) . ' record(s) skipped due to existing active asset tags: ' . implode(', ', $skippedAssetTags);
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
    /**
     * If no valid IDs are provided for restoration, return an error.
     */
    echo json_encode(['status' => 'error', 'message' => 'No equipment location selected']);
}
