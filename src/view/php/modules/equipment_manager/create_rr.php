<?php
/**
 * Create Receiving Report Module
 *
 * This file provides functionality to create receiving reports for equipment in the system. It handles the generation of reports for newly received equipment, including documentation and verification processes. The module ensures proper validation and maintains data consistency for equipment receiving procedures.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentManager
 * @author     TMDD Interns 25'
 */

session_start(); // Start the PHP session.
date_default_timezone_set('Asia/Manila'); // Set the default timezone for date/time functions.
require_once('../../../../../config/ims-tmdd.php'); // Include the database connection file, providing the $pdo object.

/**
 * @var bool $isAjax Checks if the current request is an AJAX request.
 * This is determined by the 'HTTP_X_REQUESTED_WITH' header.
 */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

/**
 * If the request is not an AJAX request, respond with a 403 Forbidden status
 * and an error message, then exit.
 */
if (!$isAjax) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Direct access not allowed']);
    exit;
}

/**
 * @var int|null $userId Retrieves the user ID from the session.
 * Defaults to null if not set.
 */
$userId = $_SESSION['user_id'] ?? null;

/**
 * Ensures the user is logged in.
 * If no user ID is found in the session, respond with a 401 Unauthorized status
 * and an error message, then exit.
 */
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

/**
 * @var RBACService $rbac Initializes the RBACService with the PDO object and current user ID.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);

/**
 * Checks if the request method is POST and if the 'action' and 'rr_no'
 * parameters are provided and valid for creating an RR.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_rr' && isset($_POST['rr_no'])) {
    /**
     * @var string $rrNo The Receiving Report number provided in the POST request, trimmed.
     * @var string $dateCreated The date of creation for the RR, defaults to current datetime if not provided.
     */
    $rrNo = trim($_POST['rr_no']);
    $dateCreated = $_POST['date_created'] ?? date('Y-m-d H:i:s');

    /**
     * Ensures the RR number has the 'RR' prefix. If not, it prepends it.
     */
    if (strpos($rrNo, 'RR') !== 0) {
        $rrNo = 'RR' . $rrNo;
    }

    /**
     * @var array $response Initializes the response array with a default error status.
     */
    $response = [
        'status' => 'error',
        'message' => 'Failed to create RR record'
    ];

    try {
        /**
         * Checks if the user has 'Create' privilege for 'Equipment Transactions' using RBAC.
         * Throws an exception if the privilege is not granted.
         */
        if (!$rbac->hasPrivilege('Equipment Transactions', 'Create')) {
            throw new Exception('You do not have permission to create receiving reports');
        }

        // Check if RR already exists
        /**
         * Prepares and executes a SQL statement to check if an RR with the given number
         * already exists and is not disabled.
         *
         * @var PDOStatement $stmt The prepared SQL statement object.
         * @var array|false $existing The fetched existing RR row, or false if not found.
         */
        $stmt = $pdo->prepare("SELECT id FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
        $stmt->execute([$rrNo]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        /**
         * If an existing RR is found, return a success response indicating it already exists
         * with its ID and RR number.
         */
        if ($existing) {
            $response = [
                'status' => 'success',
                'message' => 'RR already exists',
                'rr_id' => $existing['id'],
                'rr_no' => $rrNo
            ];
        } else {
            // Insert new RR with minimal info
            /**
             * If no existing RR is found, prepares and executes a SQL statement to insert
             * a new RR record into the `receive_report` table.
             *
             * @var PDOStatement $stmt The prepared SQL statement object.
             * @var bool $result True on success, false on failure.
             */
            $stmt = $pdo->prepare("INSERT INTO receive_report (rr_no, date_created, is_disabled) VALUES (?, ?, 0)");
            $result = $stmt->execute([$rrNo, $dateCreated]);

            /**
             * If the insertion is successful:
             * - Retrieves the ID of the newly inserted record.
             * - Logs the creation action in the `audit_log` table.
             * - Sets a success response with the new RR ID and number.
             */
            if ($result) {
                /** @var int $newId The ID of the newly inserted RR record. */
                $newId = $pdo->lastInsertId();

                // Log the creation in audit_log
                /**
                 * @var string $newValues JSON encoded string of the new RR values for audit logging.
                 */
                $newValues = json_encode([
                    'rr_no' => $rrNo,
                    'date_created' => $dateCreated,
                    'is_disabled' => 0
                ]);

                /**
                 * @var PDOStatement $auditStmt The prepared SQL statement object for inserting audit logs.
                 */
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $newId,
                    'Receiving Report',
                    'Create',
                    'New RR created from Equipment Details page',
                    null,
                    $newValues,
                    'Successful'
                ]);

                $response = [
                    'status' => 'success',
                    'message' => 'RR created successfully',
                    'rr_id' => $newId,
                    'rr_no' => $rrNo
                ];
            }
        }
    } catch (PDOException $e) {
        // Database error
        /**
         * Catches PDO exceptions (database errors), sets an error response,
         * and logs the error to the PHP error log.
         */
        $response = [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ];

        error_log('RR Creation Error: ' . $e->getMessage());
    } catch (Exception $e) {
        // Permission or other error
        /**
         * Catches general exceptions (e.g., RBAC permission denied) and sets an error response.
         */
        $response = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }

    // Return JSON response
    header('Content-Type: application/json'); // Set the content type to JSON.
    echo json_encode($response); // Encode the response array to JSON and output it.
    exit; // Terminate the script.
} else {
    // Invalid request
    /**
     * If the required POST parameters are missing or the action is invalid,
     * respond with a 400 Bad Request status and an error message, then exit.
     */
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request. Required parameters missing.'
    ]);
    exit;
}
