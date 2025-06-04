<?php
/**
 * @file delete_equipment.php
 * @brief Handles the permanent deletion of equipment-related records from the database.
 *
 * This script processes POST requests to permanently delete records from `equipment_location`,
 * `equipment_status`, or `equipment_details` tables based on the 'module' and 'id' provided.
 * It performs audit logging for deletions and handles cascading deletions for `equipment_details`.
 * All operations are wrapped in a database transaction for atomicity.
 */

session_start(); // Start the PHP session.
require_once('../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.

header('Content-Type: application/json'); // Set the content type to JSON for all responses.
header('X-Content-Type-Options: nosniff'); // Prevent browsers from MIME-sniffing a response away from the declared content type.

/**
 * Checks if required POST parameters are set for a permanent delete operation.
 * - 'id': The ID of the record to be deleted.
 * - 'permanent': Must be 1 to confirm permanent deletion.
 * - 'module': Specifies which module (table) the deletion applies to.
 */
if (
    isset($_POST['id']) && isset($_POST['permanent']) && $_POST['permanent'] == 1 && isset($_POST['module'])
) {
    /**
     * @var int|false $id The ID of the record to delete, filtered as an integer.
     * False if validation fails.
     * @var string $module The module (table name) from which to delete the record.
     */
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $module = $_POST['module'];

    /**
     * If the ID is not a valid integer, return an error and exit.
     */
    if ($id === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit();
    }

    try {
        /**
         * Handles deletion based on the specified module.
         */
        if ($module === 'Equipment Location') {
            // First fetch the record for the audit log
            /**
             * Fetches the `equipment_location` record before deletion for audit logging.
             *
             * @var PDOStatement $fetchStmt The prepared SQL statement object.
             * @var array|false $locationData The fetched location data, or false if not found.
             */
            $fetchStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
            $fetchStmt->execute([$id]);
            $locationData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            /**
             * If the location record is found, proceed with deletion and audit logging.
             */
            if ($locationData) {
                // Begin transaction for atomicity
                $pdo->beginTransaction(); // Start a database transaction.

                // Log the deletion in audit_log
                /**
                 * Prepares and executes a SQL statement to insert an audit log entry for the deletion.
                 *
                 * @var PDOStatement $auditStmt The prepared SQL statement object for audit logs.
                 */
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Location',
                    'Delete',
                    'Equipment location has been permanently deleted',
                    json_encode($locationData), // Old value is the full record data.
                    null, // New value is null as it's a deletion.
                    'Successful'
                ]);

                // Now perform the actual deletion
                /**
                 * Deletes the `equipment_location` record.
                 *
                 * @var PDOStatement $stmt The prepared SQL statement object for deletion.
                 */
                $stmt = $pdo->prepare("DELETE FROM equipment_location WHERE equipment_location_id = ?");
                $stmt->execute([$id]);

                $pdo->commit(); // Commit the transaction.
                echo json_encode(['status' => 'success', 'message' => 'Equipment location permanently deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment location not found.']);
            }
        } elseif ($module === 'Equipment Status') {
            // First fetch the record for the audit log
            /**
             * Fetches the `equipment_status` record before deletion for audit logging.
             *
             * @var PDOStatement $fetchStmt The prepared SQL statement object.
             * @var array|false $statusData The fetched status data, or false if not found.
             */
            $fetchStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
            $fetchStmt->execute([$id]);
            $statusData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            /**
             * If the status record is found, proceed with deletion and audit logging.
             */
            if ($statusData) {
                // Begin transaction for atomicity
                $pdo->beginTransaction(); // Start a database transaction.

                // Log the deletion in audit_log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Status',
                    'Delete',
                    'Equipment status has been permanently deleted',
                    json_encode($statusData), // Old value is the full record data.
                    null, // New value is null as it's a deletion.
                    'Successful'
                ]);

                // Now perform the actual deletion
                /**
                 * Deletes the `equipment_status` record.
                 *
                 * @var PDOStatement $stmt The prepared SQL statement object for deletion.
                 */
                $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE equipment_status_id = ?");
                $stmt->execute([$id]);

                $pdo->commit(); // Commit the transaction.
                echo json_encode(['status' => 'success', 'message' => 'Equipment status permanently deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment status not found.']);
            }
        } elseif ($module === 'Equipment Details') {
            // First fetch the record for the audit log
            /**
             * Fetches the `equipment_details` record before deletion for audit logging.
             *
             * @var PDOStatement $fetchStmt The prepared SQL statement object.
             * @var array|false $detailsData The fetched details data, or false if not found.
             */
            $fetchStmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
            $fetchStmt->execute([$id]);
            $detailsData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            /**
             * If the equipment details record is found, proceed with deletion, including cascaded deletions.
             */
            if ($detailsData) {
                // Begin transaction for atomicity
                $pdo->beginTransaction(); // Start a database transaction.

                // Get the asset tag for cascading deletion logging
                /** @var string $assetTag The asset tag associated with the equipment details. */
                $assetTag = $detailsData['asset_tag'];

                // Log the main equipment details permanent deletion in audit_log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $id,
                    'Equipment Details',
                    'Delete',
                    'Equipment details have been permanently deleted',
                    json_encode($detailsData), // Old value is the full record data.
                    null, // New value is null as it's a deletion.
                    'Successful'
                ]);

                // Check for related status records to log their deletion as well
                /**
                 * Fetches related `equipment_status` records that are marked as disabled (soft-deleted)
                 * to be permanently deleted.
                 *
                 * @var PDOStatement $statusStmt The prepared SQL statement object.
                 * @var array $statusRecords All fetched status records.
                 */
                $statusStmt = $pdo->prepare("SELECT * FROM equipment_status WHERE asset_tag = ? AND is_disabled = 1");
                $statusStmt->execute([$assetTag]);
                $statusRecords = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

                /**
                 * If related status records exist, log their cascaded deletion and then delete them.
                 */
                if (count($statusRecords) > 0) {
                    // Log the cascaded status deletions
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $id,
                        'Equipment Status',
                        'Delete',
                        'Equipment status entries for asset tag ' . $assetTag . ' have been permanently deleted (cascaded delete)',
                        json_encode(['asset_tag' => $assetTag, 'records' => $statusRecords]), // Old value includes asset tag and records.
                        null, // New value is null.
                        'Successful'
                    ]);

                    // Delete the related status records
                    /**
                     * Deletes the related `equipment_status` records.
                     *
                     * @var PDOStatement $delStatusStmt The prepared SQL statement object for deletion.
                     */
                    $delStatusStmt = $pdo->prepare("DELETE FROM equipment_status WHERE asset_tag = ? AND is_disabled = 1");
                    $delStatusStmt->execute([$assetTag]);
                }

                // Check for related location records to log their deletion as well
                /**
                 * Fetches related `equipment_location` records that are marked as disabled (soft-deleted)
                 * to be permanently deleted.
                 *
                 * @var PDOStatement $locationStmt The prepared SQL statement object.
                 * @var array $locationRecords All fetched location records.
                 */
                $locationStmt = $pdo->prepare("SELECT * FROM equipment_location WHERE asset_tag = ? AND is_disabled = 1");
                $locationStmt->execute([$assetTag]);
                $locationRecords = $locationStmt->fetchAll(PDO::FETCH_ASSOC);

                /**
                 * If related location records exist, log their cascaded deletion and then delete them.
                 */
                if (count($locationRecords) > 0) {
                    // Log the cascaded location deletions
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $id,
                        'Equipment Location',
                        'Delete',
                        'Equipment location entries for asset tag ' . $assetTag . ' have been permanently deleted (cascaded delete)',
                        json_encode(['asset_tag' => $assetTag, 'records' => $locationRecords]), // Old value includes asset tag and records.
                        null, // New value is null.
                        'Successful'
                    ]);

                    // Delete the related location records
                    /**
                     * Deletes the related `equipment_location` records.
                     *
                     * @var PDOStatement $delLocationStmt The prepared SQL statement object for deletion.
                     */
                    $delLocationStmt = $pdo->prepare("DELETE FROM equipment_location WHERE asset_tag = ? AND is_disabled = 1");
                    $delLocationStmt->execute([$assetTag]);
                }

                // Now perform the actual deletion of equipment details
                /**
                 * Deletes the main `equipment_details` record.
                 *
                 * @var PDOStatement $stmt The prepared SQL statement object for deletion.
                 */
                $stmt = $pdo->prepare("DELETE FROM equipment_details WHERE id = ?");
                $stmt->execute([$id]);

                $pdo->commit(); // Commit the transaction.
                echo json_encode(['status' => 'success', 'message' => 'Equipment details permanently deleted.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Equipment details not found.']);
            }
        } else {
            /**
             * If an unknown module is specified, return an error.
             */
            echo json_encode(['status' => 'error', 'message' => 'Unknown module for permanent delete.']);
        }
    } catch (PDOException $e) {
        // Rollback transaction if there's an error
        /**
         * Catches PDO exceptions (database errors). If a transaction is active, it rolls back
         * the transaction to prevent partial data changes. Then, it returns an error message.
         */
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit(); // Terminate the script.
}
/**
 * If the initial POST request does not meet the required parameters for permanent deletion,
 * return a generic invalid request error.
 */
echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
