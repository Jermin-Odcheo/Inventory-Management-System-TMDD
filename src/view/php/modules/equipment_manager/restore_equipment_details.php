<?php
/**
 * @file restore_equipment_details.php
 * @brief Handles the restoration of soft-deleted equipment details records.
 *
 * This script processes POST requests to restore one or more equipment details records
 * (by setting `is_disabled` to 0). It also cascades the restoration to related
 * `equipment_status` and `equipment_location` records with the same asset tag.
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
 * Handles single equipment details restoration.
 * Checks if 'id' POST parameter is set.
 */
if (isset($_POST['id'])) {
    /**
     * @var int|false $edId The ID of the equipment detail to restore, filtered as an integer.
     * False if validation fails.
     */
    $edId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($edId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment details ID']);
        exit();
    }
    try {
        $pdo->beginTransaction(); // Start a database transaction.

        // Get data before restoration for audit log
        /**
         * Fetches the old equipment details record before restoration for audit logging.
         *
         * @var PDOStatement $checkStmt The prepared SQL statement object.
         * @var array|false $oldData The fetched old equipment data, or false if not found.
         */
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ? AND is_disabled = 1");
        $checkStmt->execute([$edId]);
        $oldData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            echo json_encode(['status' => 'error', 'message' => 'Equipment details not found or not archived']);
            exit();
        }

        // Get the asset tag from the equipment details
        $assetTag = $oldData['asset_tag'];

        // Check if there's already an active equipment details record for this asset tag
        /**
         * Checks for existing active equipment details records with the same asset tag.
         * Prevents restoration if an active record already exists.
         *
         * @var PDOStatement $activeCheckStmt The prepared SQL statement object.
         * @var int $activeCount The count of active records with the same asset tag.
         */
        $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_details WHERE asset_tag = ? AND is_disabled = 0");
        $activeCheckStmt->execute([$assetTag]);
        $activeCount = $activeCheckStmt->fetchColumn();

        if ($activeCount > 0) {
            echo json_encode(['status' => 'warning', 'message' => 'An active equipment details record with asset tag ' . $assetTag . ' already exists. Cannot restore.']);
            exit();
        }

        // Perform the restore of equipment details
        /**
         * Updates the `equipment_details` record to set `is_disabled` to 0 (restore).
         *
         * @var PDOStatement $stmt The prepared SQL statement object.
         */
        $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$edId]);

        // Get data after restoration for audit log
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
        $checkStmt->execute([$edId]);
        $newData = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Insert into audit_log for equipment details
        /**
         * Inserts an audit log entry for the restoration of equipment details.
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
            $edId,
            'Equipment Details',
            'Restored',
            'Equipment details have been restored',
            json_encode($oldData),
            json_encode($newData),
            'Successful'
        ]);

        // Also restore related equipment status records
        /**
         * Updates `equipment_status` records with the same asset tag to set `is_disabled` to 0.
         *
         * @var PDOStatement $statusStmt The prepared SQL statement object.
         * @var int $statusRowsAffected The number of rows affected in `equipment_status`.
         */
        $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
        $statusStmt->execute([$assetTag]);
        $statusRowsAffected = $statusStmt->rowCount();

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
        if ($statusRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $edId,
                'Equipment Status',
                'Restored',
                'Equipment status entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $statusRowsAffected]),
                null,
                'Successful'
            ]);
        }

        if ($locationRowsAffected > 0) {
            $auditStmt->execute([
                $_SESSION['user_id'],
                $edId,
                'Equipment Location',
                'Restored',
                'Equipment location entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                json_encode(['asset_tag' => $assetTag, 'rows_affected' => $locationRowsAffected]),
                null,
                'Successful'
            ]);
        }

        $pdo->commit(); // Commit the transaction.

        // Prepare response message based on what was restored
        $message = 'Equipment details restored successfully';
        if ($statusRowsAffected > 0 || $locationRowsAffected > 0) {
            $message .= ', along with ' .
                ($statusRowsAffected > 0 ? $statusRowsAffected . ' status record(s)' : '') .
                ($statusRowsAffected > 0 && $locationRowsAffected > 0 ? ' and ' : '') .
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
    /**
     * Handles multiple equipment details restoration.
     * Checks if 'ed_ids' POST parameter is an array of IDs.
     */
    $edIds = array_filter(array_map('intval', $_POST['ed_ids']));
    if(empty($edIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment details IDs provided']);
        exit();
    }
    try {
        $pdo->beginTransaction(); // Start a database transaction.

        // Get data before restoration for audit log
        $placeholders = implode(",", array_fill(0, count($edIds), '?'));
        /**
         * Fetches old equipment details records for multiple IDs before restoration.
         *
         * @var PDOStatement $checkStmt The prepared SQL statement object.
         * @var array $oldDataRecords All fetched old equipment data.
         */
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id IN ($placeholders) AND is_disabled = 1");
        $checkStmt->execute($edIds);
        $oldDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($oldDataRecords)) {
            echo json_encode(['status' => 'error', 'message' => 'No equipment details found or not archived']);
            exit();
        }

        // Store old data by ID for easier access
        $oldDataLookup = [];
        $assetTags = []; // Store asset tags for related records
        $skippedAssetTags = []; // Track skipped asset tags due to existing active records
        $validEdIds = []; // Track valid equipment detail IDs after checking for active records

        foreach ($oldDataRecords as $record) {
            $oldDataLookup[$record['id']] = $record;
            $assetTags[$record['id']] = $record['asset_tag'];

            // Check if there's already an active equipment details record for this asset tag
            $activeCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_details WHERE asset_tag = ? AND is_disabled = 0");
            $activeCheckStmt->execute([$record['asset_tag']]);
            $activeCount = $activeCheckStmt->fetchColumn();

            if ($activeCount > 0) {
                $skippedAssetTags[] = $record['asset_tag'];
            } else {
                $validEdIds[] = $record['id'];
            }
        }

        // If no valid IDs after checking for active records, exit
        if (empty($validEdIds)) {
            if (!empty($skippedAssetTags)) {
                echo json_encode(['status' => 'warning', 'message' => 'All selected equipment details have active records with the same asset tags: ' . implode(', ', $skippedAssetTags)]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No valid equipment details to restore']);
            }
            exit();
        }

        // Prepare placeholders for valid IDs
        $validPlaceholders = implode(",", array_fill(0, count($validEdIds), '?'));

        // Perform the restore for valid equipment details
        $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE id IN ($validPlaceholders) AND is_disabled = 1");
        $stmt->execute($validEdIds);

        // Get data after restoration
        $checkStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id IN ($validPlaceholders)");
        $checkStmt->execute($validEdIds);
        $newDataRecords = $checkStmt->fetchAll(PDO::FETCH_ASSOC);

        // Store new data by ID for easier access
        $newDataLookup = [];
        foreach ($newDataRecords as $record) {
            $newDataLookup[$record['id']] = $record;
        }

        // Track restored related records
        $restoredStatusCount = 0;
        $restoredLocationCount = 0;

        // Log each restoration in audit_log and restore related records
        foreach ($validEdIds as $id) {
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
                'Equipment Details',
                'Restored',
                'Equipment details have been restored',
                json_encode($oldData),
                json_encode($newDataLookup[$id] ?? null),
                'Successful'
            ]);

            // Restore related equipment status records
            $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $statusStmt->execute([$assetTag]);
            $statusRowsAffected = $statusStmt->rowCount();
            $restoredStatusCount += $statusRowsAffected;

            // Restore related equipment location records
            $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE asset_tag = ? AND is_disabled = 1");
            $locationStmt->execute([$assetTag]);
            $locationRowsAffected = $locationStmt->rowCount();
            $restoredLocationCount += $locationRowsAffected;

            // Log the cascaded restorations if any rows were affected
            if ($statusRowsAffected > 0) {
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Status',
                    'Restored',
                    'Equipment status entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                    json_encode(['asset_tag' => $assetTag, 'rows_affected' => $statusRowsAffected]),
                    null,
                    'Successful'
                ]);
            }

            if ($locationRowsAffected > 0) {
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Location',
                    'Restored',
                    'Equipment location entries for asset tag ' . $assetTag . ' have been restored (cascaded restore)',
                    json_encode(['asset_tag' => $assetTag, 'rows_affected' => $locationRowsAffected]),
                    null,
                    'Successful'
                ]);
            }
        }

        $pdo->commit(); // Commit the transaction.

        // Prepare response message
        $message = count($validEdIds) . ' equipment details restored successfully';

        // Add info about related records
        if ($restoredStatusCount > 0 || $restoredLocationCount > 0) {
            $message .= ', along with ' .
                ($restoredStatusCount > 0 ? $restoredStatusCount . ' status record(s)' : '') .
                ($restoredStatusCount > 0 && $restoredLocationCount > 0 ? ' and ' : '') .
                ($restoredLocationCount > 0 ? $restoredLocationCount . ' location record(s)' : '');
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
    /**
     * If no valid IDs are provided for restoration, return an error.
     */
    echo json_encode(['status' => 'error', 'message' => 'No equipment details selected']);
}
