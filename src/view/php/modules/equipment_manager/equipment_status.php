<?php
/**
 * Equipment Status Module
 *
 * This file provides comprehensive functionality for managing equipment status in the system. It handles the creation, modification, and tracking of equipment status, including status changes and historical records. The module ensures proper validation, user authorization, and maintains data consistency across the system.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentManager
 * @author     TMDD Interns 25'
 */

require_once '../../../../../config/ims-tmdd.php'; // Include the database connection file, providing the $pdo object.
session_start(); // Start the PHP session.

// start buffering all output (header/sidebar/footer HTML will be captured)
ob_start();

include '../../general/header.php'; // Include the general header HTML.
include '../../general/sidebar.php'; // Include the general sidebar HTML.
include '../../general/footer.php'; // Include the general footer HTML.

// For AJAX requests, we want to handle them separately
/**
 * Handles AJAX POST requests for 'add', 'update', and 'delete' actions.
 * Discards any buffered HTML output before sending JSON responses.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    ob_clean(); // Clean any existing output buffers.
    header('Content-Type: application/json'); // Set the content type to JSON for AJAX responses.

    /**
     * Checks if the user is logged in. If not, returns an error message and exits.
     */
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }

    /**
     * @var RBACService $rbac Initializes the RBACService with the PDO object and current user ID.
     */
    $rbac = new RBACService($pdo, $_SESSION['user_id']);

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $response = array('status' => 'error', 'message' => 'Invalid action');

        switch ($_POST['action']) {
            case 'add':
                /**
                 * Handles the 'add' action for equipment status.
                 * Checks for 'Create' privilege, validates inputs, performs a transaction,
                 * inserts a new record into `equipment_status`, and logs creation in `audit_log`.
                 */
                try {
                    // Check if user has Create privilege
                    if (!$rbac->hasPrivilege('Equipment Management', 'Create')) {
                        throw new Exception('You do not have permission to add equipment status');
                    }

                    // Validate required fields
                    if (empty($_POST['asset_tag'])) {
                        throw new Exception('Asset Tag is required');
                    }

                    $pdo->beginTransaction(); // Start a database transaction.

                    // Before inserting into the database
                    error_log('Status to insert: ' . $_POST['status']);

                    // Insert equipment status
                    /**
                     * Inserts a new record into the `equipment_status` table.
                     *
                     * @var PDOStatement $stmt The prepared SQL statement object.
                     * @var bool $result True on success, false on failure.
                     */
                    $stmt = $pdo->prepare("INSERT INTO equipment_status (
                        asset_tag,
                        status,
                        action,
                        remarks,
                        date_created,
                        is_disabled
                    ) VALUES (?, ?, ?, ?, NOW(), ?)");

                    $result = $stmt->execute([
                        trim($_POST['asset_tag']),
                        trim($_POST['status']),
                        trim($_POST['action_description']),
                        trim($_POST['remarks'] ?? ''),
                        isset($_POST['is_disabled']) ? 1 : 0
                    ]);

                    if (!$result) {
                        throw new Exception('Failed to insert equipment status');
                    }

                    /** @var int $newStatusId The ID of the newly inserted status record. */
                    $newStatusId = $pdo->lastInsertId();

                    // Prepare audit log data
                    /**
                     * @var string $newValues JSON encoded string of the new status data for audit logging.
                     * @var PDOStatement $auditStmt The prepared SQL statement object for inserting audit logs.
                     */
                    $newValues = json_encode([
                        'asset_tag' => $_POST['asset_tag'],
                        'status' => $_POST['status'],
                        'action' => $_POST['action_description'],
                        'remarks' => $_POST['remarks'],
                        'is_disabled' => isset($_POST['is_disabled']) ? 1 : 0
                    ]);

                    // Insert audit log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $auditResult = $auditStmt->execute([
                        $_SESSION['user_id'],
                        $newStatusId,
                        'Equipment Status',
                        'Create',
                        'New equipment status added',
                        null,
                        $newValues,
                        'Successful'
                    ]);

                    if (!$auditResult) {
                        throw new Exception('Failed to create audit log');
                    }

                    $pdo->commit(); // Commit the transaction.

                    $response = [
                        'status' => 'success',
                        'message' => 'Equipment Status has been added successfully.'
                    ];
                } catch (Exception $e) {
                    /**
                     * Catches exceptions during 'add' action, rolls back if active,
                     * and sets an error response.
                     */
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $response = [
                        'status' => 'error',
                        'message' => 'Error adding status: ' . $e->getMessage()
                    ];
                }
                break;

            case 'update':
                /**
                 * Handles the 'update' action for equipment status.
                 * Checks for 'Modify' privilege, validates inputs, performs a transaction,
                 * updates an existing record in `equipment_status`, and logs modification in `audit_log`.
                 */
                try {
                    // Check if user has Modify privilege
                    if (!$rbac->hasPrivilege('Equipment Management', 'Modify')) {
                        throw new Exception('You do not have permission to modify equipment status');
                    }

                    // Validate required fields
                    if (empty($_POST['status_id'])) {
                        throw new Exception('Status ID is required');
                    }
                    if (empty($_POST['asset_tag'])) {
                        throw new Exception('Asset Tag is required');
                    }

                    $pdo->beginTransaction(); // Start a database transaction.

                    // Get old status details for audit log
                    /**
                     * Fetches the old equipment status data before updating for audit logging.
                     *
                     * @var PDOStatement $stmt The prepared SQL statement object.
                     * @var array|false $oldStatus The fetched old status data, or false if not found.
                     */
                    $stmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $oldStatus = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$oldStatus) {
                        throw new Exception('Status not found');
                    }

                    // Update equipment status
                    /**
                     * Updates the `equipment_status` record with new values.
                     *
                     * @var PDOStatement $stmt The prepared SQL statement object.
                     * @var bool $result True on success, false on failure.
                     */
                    $stmt = $pdo->prepare("UPDATE equipment_status SET
                        asset_tag = ?,
                        status = ?,
                        action = ?,
                        remarks = ?,
                        is_disabled = ?
                        WHERE equipment_status_id = ?");

                    $result = $stmt->execute([
                        trim($_POST['asset_tag']),
                        trim($_POST['status']),
                        trim($_POST['action_description']),
                        trim($_POST['remarks'] ?? ''),
                        isset($_POST['is_disabled']) ? 1 : 0,
                        $_POST['status_id']
                    ]);

                    if (!$result) {
                        throw new Exception('Failed to update equipment status');
                    }

                    // Prepare audit log data
                    /**
                     * @var string $oldValues JSON encoded string of the old status data for audit logging.
                     * @var string $newValues JSON encoded string of the new status data for audit logging.
                     * @var PDOStatement $auditStmt The prepared SQL statement object for inserting audit logs.
                     */
                    $oldValues = json_encode([
                        'asset_tag' => $oldStatus['asset_tag'],
                        'status' => $oldStatus['status'],
                        'action' => $oldStatus['action'],
                        'remarks' => $oldStatus['remarks'],
                        'date_created' => $oldStatus['date_created'],
                        'is_disabled' => $oldStatus['is_disabled']
                    ]);

                    $newValues = json_encode([
                        'asset_tag' => $_POST['asset_tag'],
                        'status' => $_POST['status'],
                        'action' => $_POST['action_description'],
                        'remarks' => $_POST['remarks'],
                        'is_disabled' => isset($_POST['is_disabled']) ? 1 : 0
                    ]);

                    // Insert audit log
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

                    $auditResult = $auditStmt->execute([
                        $_SESSION['user_id'],
                        $_POST['status_id'],
                        'Equipment Status',
                        'Modified',
                        'Equipment status modified',
                        $oldValues,
                        $newValues,
                        'Successful'
                    ]);

                    if (!$auditResult) {
                        throw new Exception('Failed to create audit log');
                    }

                    $pdo->commit(); // Commit the transaction.
                    $response = [
                        'status' => 'success',
                        'message' => 'Status updated successfully'
                    ];
                } catch (Exception $e) {
                    /**
                     * Catches exceptions during 'update' action, rolls back if active,
                     * and sets an error response.
                     */
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response = [
                        'status' => 'error',
                        'message' => 'Error updating status: ' . $e->getMessage()
                    ];
                }
                break;

            case 'delete':
                /**
                 * Handles the 'delete' action for equipment status.
                 * Checks for 'Remove' privilege, validates inputs, performs a transaction,
                 * soft deletes a record in `equipment_status` (sets `is_disabled` to 1),
                 * and logs deletion in `audit_log`.
                 */
                try {
                    // Check if user has Remove privilege
                    if (!$rbac->hasPrivilege('Equipment Management', 'Remove')) {
                        throw new Exception('You do not have permission to delete equipment status');
                    }

                    if (!isset($_POST['status_id'])) {
                        throw new Exception('Status ID is required');
                    }

                    // Get status details before deletion for audit log
                    /**
                     * Fetches the equipment status data before soft deletion for audit logging.
                     *
                     * @var PDOStatement $stmt The prepared SQL statement object.
                     * @var array|false $statusData The fetched status data, or false if not found.
                     */
                    $stmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $statusData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$statusData) {
                        throw new Exception('Status not found');
                    }

                    // Get the asset tag
                    $assetTag = $statusData['asset_tag'];

                    $pdo->beginTransaction(); // Start a database transaction.

                    // Prepare audit log data
                    /**
                     * @var string $oldValue JSON encoded string of the old status data before soft deletion for audit logging.
                     * @var PDOStatement $auditStmt The prepared SQL statement object for inserting audit logs.
                     */
                    $oldValue = json_encode([
                        'equipment_status_id' => $statusData['equipment_status_id'],
                        'asset_tag' => $statusData['asset_tag'],
                        'status' => $statusData['status'],
                        'remarks' => $statusData['remarks']
                    ]);

                    // Insert into audit_log for status deletion
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
                        $statusData['equipment_status_id'],
                        'Equipment Status',
                        'Remove',
                        'Equipment status has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);

                    // Perform the delete on equipment_status (soft delete)
                    $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);

                    // No longer check for active status records or cascade deletions
                    // We only update the equipment_status record

                    $_SESSION['success'] = "Equipment Status deleted successfully.";
                    $response = [
                        'status' => 'success',
                        'message' => 'Equipment Status deleted successfully.'
                    ];

                    $pdo->commit(); // Commit the transaction.
                } catch (Exception $e) {
                    /**
                     * Catches exceptions during 'delete' action, rolls back if active,
                     * and sets an error response.
                     */
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['errors'] = ["Error deleting status: " . $e->getMessage()];
                    $response = [
                        'status' => 'error',
                        'message' => 'Error deleting status: ' . $e->getMessage()
                    ];
                }
                break;
        }
    }

    // Ensure a JSON response is always sent
    echo json_encode($response);
    exit;
}


// Initialize RBAC (re-initialize for regular page load if not already done)
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;

// Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Management', 'View');

// Button flags (re-set for clarity, though already determined above)
$canCreate = $rbac->hasPrivilege('Equipment Management', 'Create');
$canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
$canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');

// Initialize response array
$response = array('status' => '', 'message' => '');

// Initialize messages
$errors = [];
$success = "";

// Retrieve any session messages from previous requests
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// GET deletion (if applicable) - This block is now redundant due to AJAX handling above.
// It's kept for completeness but the AJAX POST 'delete' case is preferred.
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    // Check if user has Remove privilege
    if (!$rbac->hasPrivilege('Equipment Management', 'Remove')) {
        $_SESSION['errors'] = ["You do not have permission to delete equipment status"];
        header("Location: equipment_status.php");
        exit;
    }

    $id = $_GET['id'];
    try {
        // Get status details before deletion for audit log
        $stmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
        $stmt->execute([$id]);
        $statusData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($statusData) {
            // Get the asset tag
            $assetTag = $statusData['asset_tag'];

            $pdo->beginTransaction(); // Start a database transaction.

            // Set current user for audit logging
            $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);

            // Prepare audit log data
            $oldValue = json_encode([
                'equipment_status_id' => $statusData['equipment_status_id'],
                'asset_tag' => $statusData['asset_tag'],
                'status' => $statusData['status'],
                'remarks' => $statusData['remarks']
            ]);

            // Insert into audit_log
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
                $statusData['equipment_status_id'],
                'Equipment Management',
                'Delete',
                'Equipment status has been deleted',
                $oldValue,
                null,
                'Successful'
            ]);

            // Perform the delete on equipment_status (soft delete)
            $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE equipment_status_id = ?");
            $stmt->execute([$id]);

            // No longer check for active status records or cascade deletions
            // We only update the equipment_status record

            $_SESSION['success'] = "Equipment Status deleted successfully.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error deleting Equipment Status: " . $e->getMessage();
    }
    header("Location: equipment_status.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Status Management</title>
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <style>
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px !important; /* Space for sort icon */
        }
        th.sortable::after {
            content: "\f0dc"; /* Font Awesome sort icon */
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            right: 8px;
            color: #999;
        }
        th.sortable.asc::after {
            content: "\f0de"; /* Font Awesome sort-up icon */
            color: #0d6efd; /* Bootstrap primary color */
        }
        th.sortable.desc::after {
            content: "\f0dd"; /* Font Awesome sort-down icon */
            color: #0d6efd; /* Bootstrap primary color */
        }

        /* Styling for read-only fields */
        input[readonly] {
            background-color: #e9ecef;
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* Filter container styles */
        .filter-container {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .filter-container .form-label {
            margin-bottom: 0.5rem;
            color: #495057;
            font-size: 0.875rem;
        }

        .filter-container .form-select,
        .filter-container .form-control {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            height: 38px;
        }

        .filter-container .input-group {
            height: 38px;
        }

        .filter-container .input-group-text {
            height: 38px;
            padding: 0.375rem 0.75rem;
        }

        .filter-container .btn {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            height: 38px;
            padding: 0.375rem 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Ensure consistent height for all form elements */
        .filter-container .form-select,
        .filter-container .form-control,
        .filter-container .input-group,
        .filter-container .btn {
            min-height: 38px;
        }

        /* Ensure buttons maintain consistent height */
        .filter-container .btn.h-100 {
            height: 38px !important;
        }

        /* Specific styles for the View Equipment Changes button */
        .filter-container .btn-primary {
            height: 38px !important;
            margin-top: 0;
        }

        /* Date inputs container styles */
        .date-inputs-container {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
        }

        .month-picker-container,
        .date-range-container {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .month-picker-container select,
        .date-range-container input {
            flex: 1;
            height: 38px;
        }

        /* Table styles */
        .table-responsive {
            margin-top: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            vertical-align: middle;
        }

        /* Button styles */
        .btn-dark {
            background-color: #212529;
            border-color: #212529;
        }

        .btn-dark:hover {
            background-color: #1a1e21;
            border-color: #1a1e21;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5c636a;
            border-color: #5c636a;
        }

        /* Select2 customization */
        .select2-container--default .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
    </style>
</head>

<body>

    <div class="main-container">
        <header class="main-header">
            <h1>Asset Status Management</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Asset Status</h2>
            </div>

            <div class="card-body">
                <div class="container-fluid px-0">
                    <div class="filter-container" id="filterContainer">
                        <!-- Single row for all controls -->
                        <div class="row mb-2 g-2 align-items-end">
                            <div class="col-md-2">
                                <?php if ($canCreate): ?>
                                    <button class="btn btn-dark w-100 h-100" id="openAddStatusModalBtn" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                                        <i class="bi bi-plus-lg"></i> Add New Status
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <label for="filterStatus" class="form-label fw-semibold">Status</label>
                                <select class="form-select" id="filterStatus">
                                    <option value="">All Statuses</option>
                                    <?php
                                    // Get unique status values from the database
                                    try {
                                        $statusOptions = $pdo->query("SELECT DISTINCT status FROM equipment_status WHERE is_disabled = 0 AND status IS NOT NULL AND TRIM(status) != '' ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
                                        foreach ($statusOptions as $status) {
                                            $normalizedStatus = trim(preg_replace('/\s+/', ' ', $status));
                                            if (!empty($normalizedStatus)) {
                                                echo "<option value=\"" . htmlspecialchars($normalizedStatus) . "\">" . htmlspecialchars($normalizedStatus) . "</option>";
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error retrieving status options: " . $e->getMessage());
                                        echo "<option value=\"Maintenance\">Maintenance</option>";
                                        echo "<option value=\"Working\">Working</option>";
                                        echo "<option value=\"For Repair\">For Repair</option>";
                                        echo "<option value=\"For Disposal\">For Disposal</option>";
                                        echo "<option value=\"Disposed\">Disposed</option>";
                                        echo "<option value=\"Condemned\">Condemned</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filterAction" class="form-label fw-semibold">Action</label>
                                <select class="form-select" id="filterAction">
                                    <option value="">All Actions</option>
                                    <?php
                                    try {
                                        $actionOptions = $pdo->query("SELECT DISTINCT action FROM equipment_status WHERE is_disabled = 0 AND action IS NOT NULL AND TRIM(action) != '' ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
                                        foreach ($actionOptions as $action) {
                                            $normalizedAction = trim(preg_replace('/\s+/', ' ', $action));
                                            if (!empty($normalizedAction)) {
                                                echo "<option value=\"" . htmlspecialchars($normalizedAction) . "\">" . htmlspecialchars($normalizedAction) . "</option>";
                                            }
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error retrieving action options: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="date-filters-wrapper">
                                    <div>
                                        <label for="dateFilter" class="form-label fw-semibold">Date Filter</label>
                                        <select class="form-select" id="dateFilter">
                                            <option value="">-- Select Type --</option>
                                            <option value="mdy">Month-Day-Year Range</option>
                                            <option value="month">Month Range</option>
                                            <option value="year">Year Range</option>
                                            <option value="month_year">Month-Year Range</option>
                                        </select>
                                    </div>
                                    <!-- Date inputs will show here inline -->
                                    <div id="mdy-group-inline" class="date-group d-none">
                                        <div class="date-input-container">
                                            <label for="dateFrom" class="form-label">From</label>
                                            <input type="date" id="dateFrom" class="form-control">
                                        </div>
                                        <div class="date-input-container">
                                            <label for="dateTo" class="form-label">To</label>
                                            <input type="date" id="dateTo" class="form-control">
                                        </div>
                                    </div>
                                    
                                    <div id="month-group-inline" class="date-group d-none">
                                        <div class="date-input-container">
                                            <label for="monthFrom" class="form-label">From</label>
                                            <input type="month" id="monthFrom" class="form-control">
                                        </div>
                                        <div class="date-input-container">
                                            <label for="monthTo" class="form-label">To</label>
                                            <input type="month" id="monthTo" class="form-control">
                                        </div>
                                    </div>
                                    
                                    <div id="year-group-inline" class="date-group d-none">
                                        <div class="date-input-container">
                                            <label for="yearFrom" class="form-label">From</label>
                                            <input type="number" id="yearFrom" class="form-control" min="1900" max="2100" placeholder="YYYY">
                                        </div>
                                        <div class="date-input-container">
                                            <label for="yearTo" class="form-label">To</label>
                                            <input type="number" id="yearTo" class="form-control" min="1900" max="2100" placeholder="YYYY">
                                        </div>
                                    </div>
                                    
                                    <div id="monthyear-group-inline" class="date-group d-none">
                                        <div class="date-input-container">
                                            <label for="monthYearFrom" class="form-label">From</label>
                                            <input type="month" id="monthYearFrom" class="form-control">
                                        </div>
                                        <div class="date-input-container">
                                            <label for="monthYearTo" class="form-label">To</label>
                                            <input type="month" id="monthYearTo" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div id="date-filter-error" class="position-relative"></div>
                            </div>
                            <div class="col-md-2">
                                <label for="searchStatus" class="form-label fw-semibold">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="searchStatus" class="form-control" placeholder="Search status...">
                                </div>
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="button" id="filterBtn" class="btn btn-dark flex-grow-1 h-100"><i class="bi bi-funnel"></i> Filter</button>
                                <button type="button" id="clearBtn" class="btn btn-secondary flex-grow-1 h-100"><i class="bi bi-x-circle"></i> Clear</button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <a href="equipStat_change_log.php" class="btn btn-primary w-100 h-100 d-inline-flex align-items-center justify-content-center"><i class="bi bi-card-list"></i> View Equipment Changes</a>
                            </div>
                        </div>

                        <!-- Date filter inputs will now be shown directly in the layout -->
                        <style>
                            /* Custom styles for inline date filters */
                            .date-filters-wrapper {
                                display: flex;
                                align-items: flex-end;
                                gap: 10px;
                                flex-wrap: nowrap;
                            }
                            .date-group {
                                display: flex;
                                gap: 8px;
                                align-items: flex-end;
                                margin-left: 10px;
                            }
                            .date-input-container {
                                min-width: 140px;
                            }
                            @media (max-width: 1200px) {
                                .date-filters-wrapper {
                                    flex-wrap: wrap;
                                }
                            }
                            /* Tooltip styles */
                            .validation-tooltip {
                                position: absolute;
                                z-index: 1000;
                                background-color: #d9534f;
                                color: white;
                                padding: 6px 10px;
                                border-radius: 4px;
                                font-size: 0.85em;
                                margin-top: 5px;
                                white-space: nowrap;
                                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                            }
                            .validation-tooltip::before {
                                content: '';
                                position: absolute;
                                top: -5px;
                                left: 50%;
                                transform: translateX(-50%);
                                width: 0;
                                height: 0;
                                border-left: 5px solid transparent;
                                border-right: 5px solid transparent;
                                border-bottom: 5px solid #d9534f;
                            }
                        </style>
                        
                        <div id="dateInputsContainer" style="display: none;">
                            <!-- All date groups are hidden initially -->
                            <!-- MDY Range -->
                            <div class="date-group d-none" id="mdy-group">
                                <div class="date-input-container">
                                    <label for="dateFrom" class="form-label">From</label>
                                    <input type="date" id="dateFrom" class="form-control">
                                </div>
                                <div class="date-input-container">
                                    <label for="dateTo" class="form-label">To</label>
                                    <input type="date" id="dateTo" class="form-control">
                                </div>
                            </div>

                            <!-- Month Range -->
                            <div class="date-group d-none" id="month-group">
                                <div class="date-input-container">
                                    <label for="monthFrom" class="form-label">From</label>
                                    <input type="month" id="monthFrom" class="form-control">
                                </div>
                                <div class="date-input-container">
                                    <label for="monthTo" class="form-label">To</label>
                                    <input type="month" id="monthTo" class="form-control">
                                </div>
                            </div>

                            <!-- Year Range -->
                            <div class="date-group d-none" id="year-group">
                                <div class="date-input-container">
                                    <label for="yearFrom" class="form-label">From</label>
                                    <input type="number" id="yearFrom" class="form-control" min="1900" max="2100">
                                </div>
                                <div class="date-input-container">
                                    <label for="yearTo" class="form-label">To</label>
                                    <input type="number" id="yearTo" class="form-control" min="1900" max="2100">
                                </div>
                            </div>

                            <!-- Month-Year Range -->
                            <div class="date-group d-none" id="monthyear-group">
                                <div class="date-input-container">
                                    <label for="monthYearFrom" class="form-label">From</label>
                                    <input type="month" id="monthYearFrom" class="form-control">
                                </div>
                                <div class="date-input-container">
                                    <label for="monthYearTo" class="form-label">To</label>
                                    <input type="month" id="monthYearTo" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" id="table">
                        <table class="table" id="statusTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="number" data-column="1">#</th>
                                    <th class="sortable" data-sort="string" data-column="2">Asset Tag</th>
                                    <th class="sortable" data-sort="string" data-column="3">Status</th>
                                    <th class="sortable" data-sort="string" data-column="4">Process Action Taken</th>
                                    <th class="sortable" data-sort="date" data-column="5">Created Date</th>
                                    <th class="sortable" data-sort="string" data-column="6">Remarks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="statusTbody">
                                <?php
                                try {
                                    /**
                                     * Fetches all active equipment status records for initial page display.
                                     *
                                     * @var PDOStatement $stmt The prepared SQL statement object.
                                     */
                                    $stmt = $pdo->query("SELECT * FROM equipment_status WHERE is_disabled = 0 ORDER BY date_created DESC");
                                    while ($row = $stmt->fetch()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['equipment_status_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['asset_tag']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['action']) . "</td>";
                                        echo "<td>" . date('Y-m-d H:i', strtotime($row['date_created'])) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
                                        echo "<td>
                        <div class='d-flex justify-content-center gap-2'>";

                                        if ($canModify) {
                                            echo "<button class='btn btn-sm btn-outline-info edit-status'
                                data-id='" . htmlspecialchars($row['equipment_status_id']) . "'
                                data-asset='" . htmlspecialchars($row['asset_tag']) . "'
                                data-status='" . htmlspecialchars($row['status']) . "'
                                data-action='" . htmlspecialchars($row['action']) . "'
                                data-remarks='" . htmlspecialchars($row['remarks']) . "'
                                data-disabled='" . htmlspecialchars($row['is_disabled']) . "'>
                              <i class='bi bi-pencil'></i>
                            </button>";
                                        }

                                        if ($canDelete) {
                                            echo "<button class='btn btn-sm btn-outline-danger delete-status'
                                data-id='" . htmlspecialchars($row['equipment_status_id']) . "'>
                              <i class='bi bi-trash'></i>
                            </button>";
                                        }

                                        echo "</div>
                    </td>";
                                        echo "</tr>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='8' class='text-danger text-center'>Error loading equipment status: " . $e->getMessage() . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>


                    <!-- Pagination Controls -->
                    <div class="container-fluid">
                        <div class="row align-items-center g-3">
                            <div class="col-12 col-sm-auto">
                                <div class="text-muted">
                                    <?php
                                    // Count total equipment status entries
                                    $statusCountStmt = $pdo->query("SELECT COUNT(*) FROM equipment_status WHERE is_disabled = 0");
                                    $totalLogs = $statusCountStmt->fetchColumn();
                                    ?>
                                    <input type="hidden" id="total-users" value="<?= $totalLogs ?>">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto ms-sm-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="30">30</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <ul class="pagination justify-content-center" id="pagination"></ul>
                            </div>
                        </div>
                    </div>
                </div>
        </section>
    </div>

    <?php if ($canCreate): ?>
        <!-- Add Status Modal -->
        <div class="modal fade" id="addStatusModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Equipment Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addStatusForm">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label for="asset_tag" class="form-label">Asset Tag <span class="text-danger">*</span></label>
                                <select class="form-select asset-tag-select2" name="asset_tag" id="add_status_asset_tag" required style="width: 100%;">
                                    <option value="">Select Asset Tag</option>
                                    <?php
                                    // Fetch unique asset tags from equipment_details and equipment_location
                                    // but exclude those that already have active status records
                                    $assetTags = [];

                                    // Get all asset tags from equipment_details and equipment_location
                                    $stmt1 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_details WHERE is_disabled = 0");
                                    $assetTags = array_merge($assetTags, $stmt1->fetchAll(PDO::FETCH_COLUMN));
                                    $stmt2 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_location WHERE is_disabled = 0");
                                    $assetTags = array_merge($assetTags, $stmt2->fetchAll(PDO::FETCH_COLUMN));
                                    $assetTags = array_unique(array_filter($assetTags));

                                    // Get asset tags that already have active status records
                                    $stmt3 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_status WHERE is_disabled = 0");
                                    $activeStatusTags = $stmt3->fetchAll(PDO::FETCH_COLUMN);

                                    // Filter out asset tags that already have active status
                                    $availableAssetTags = array_diff($assetTags, $activeStatusTags);

                                    // Sort the available asset tags
                                    sort($availableAssetTags);

                                    foreach ($availableAssetTags as $tag) {
                                        echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                                    }
                                    ?>
                                </select>

                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">Select Status</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Working">Working</option>
                                    <option value="For Repair">For Repair</option>
                                    <option value="For Disposal">For Disposal</option>
                                    <option value="Disposed">Disposed</option>
                                    <option value="Condemned">Condemned</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="action_description" class="form-label">Action</label>
                                <input type="text" class="form-control" name="action_description">
                            </div>
                            <div class="mb-3">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" rows="3"></textarea>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="margin-right: 4px;">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Equipment Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canModify): ?>
        <!-- Edit Status Modal -->
        <div class="modal fade" id="editStatusModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Equipment Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="edit_status_form">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="status_id" id="edit_status_id">
                            <div class="mb-3">
                                <label for="edit_asset_tag" class="form-label"><i class="bi bi-tag"></i> Asset Tag <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_asset_tag" name="asset_tag" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="edit_status" class="form-label"><i class="bi bi-info-circle"></i> Status</label>
                                <select class="form-select" id="edit_status" name="status">
                                    <option value="">Select Status</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Working">Working</option>
                                    <option value="For Repair">For Repair</option>
                                    <option value="For Disposal">For Disposal</option>
                                    <option value="Disposed">Disposed</option>
                                    <option value="Condemned">Condemned</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit_action" class="form-label"><i class="bi bi-gear"></i> Action</label>
                                <input type="text" class="form-control" id="edit_action" name="action_description">
                            </div>
                            <div class="mb-3">
                                <label for="edit_remarks" class="form-label"><i class="bi bi-chat-left-text"></i> Remarks</label>
                                <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
        <!--Delete Confirmation Modal-->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Delete Confirmation</h5>
                        <!-- Using Bootstrap 5 close button -->
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this status?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script>
        // Initialize pagination for equipment status
        document.addEventListener('DOMContentLoaded', function() {
            // Store all table rows for pagination
            const statusRows = Array.from(document.querySelectorAll('#statusTbody tr'));
            window.allRows = statusRows;
            window.filteredRows = statusRows;
            window.paginationInitialized = true;

            // Initialize pagination with the status table ID
            initPagination({
                tableId: 'statusTbody',
                currentPage: 1
            });

            // Diagnostic function to analyze all status values
            function analyzeStatusValues() {
                console.log("--- ANALYZING ALL STATUS VALUES ---");
                const statusValues = {};
                const statusCells = document.querySelectorAll('#statusTbody tr td:nth-child(3)');
                statusCells.forEach((cell, index) => {
                    const statusText = cell.textContent.trim();
                    const normalizedStatus = statusText.replace(/\s+/g, ' ').trim();

                    if (!statusValues[normalizedStatus]) {
                        statusValues[normalizedStatus] = [];
                    }
                    statusValues[normalizedStatus].push(index + 1);

                    // Log each status with detailed info
                    console.log(`Row ${index+1} Status: "${statusText}"`);
                    console.log(`  - Length: ${statusText.length}`);
                    console.log(`  - Character codes: [${Array.from(statusText).map(c => c.charCodeAt(0))}]`);
                });

                console.log("--- STATUS VALUE SUMMARY ---");
                for (const [status, rows] of Object.entries(statusValues)) {
                    console.log(`Status "${status}" appears in ${rows.length} rows: ${rows.join(', ')}`);
                }
            }

            // Run the analysis once the page is loaded
            setTimeout(analyzeStatusValues, 1000);
        });
    </script>

    <script>
        $(document).ready(function() {
            // Ensure we have proper Bootstrap modal instances
            const addStatusModal = new bootstrap.Modal(document.getElementById('addStatusModal'), {
                backdrop: true,
                keyboard: true,
                focus: true
            });

            // Table sorting
            $('.sortable').on('click', function(event) {
                const sortField = $(this).data('sort');
                const currentClass = $(this).hasClass('asc') ? 'asc' : ($(this).hasClass('desc') ? 'desc' : '');
                const columnIndex = $(this).data('column');

                // Remove sort indicators from all headers
                $('.sortable').removeClass('asc desc');

                // Set new sort direction
                let newDirection = 'asc';
                if (currentClass === 'asc') {
                    newDirection = 'desc';
                    $(this).addClass('desc');
                } else {
                    $(this).addClass('asc');
                }

                // Sort the rows
                sortTable(sortField, newDirection, columnIndex);
            });

            // Function to sort the table
            function sortTable(field, direction, columnIndex) {
                // Use the allRows array as the source
                const sortedRows = [...window.allRows];

                sortedRows.sort((a, b) => {
                    let valueA, valueB;

                    switch (field) {
                        case 'number':
                            valueA = parseInt(a.querySelector(`td:nth-child(${columnIndex})`).textContent.trim()) || 0;
                            valueB = parseInt(b.querySelector(`td:nth-child(${columnIndex})`).textContent.trim()) || 0;
                            break;
                        case 'string':
                            valueA = a.querySelector(`td:nth-child(${columnIndex})`).textContent.toLowerCase().trim();
                            valueB = b.querySelector(`td:nth-child(${columnIndex})`).textContent.toLowerCase().trim();
                            break;
                        case 'date':
                            valueA = new Date(a.querySelector(`td:nth-child(${columnIndex})`).textContent.trim());
                            valueB = new Date(b.querySelector(`td:nth-child(${columnIndex})`).textContent.trim());
                            break;
                        default:
                            return 0;
                    }

                    // Compare values based on direction
                    if (direction === 'asc') {
                        return valueA > valueB ? 1 : (valueA < valueB ? -1 : 0);
                    } else {
                        return valueA < valueB ? 1 : (valueA > valueB ? -1 : 0);
                    }
                });

                // Update the filteredRows with the sorted rows
                window.filteredRows = sortedRows;

                // Update the pagination
                updatePagination();
            }

            // Force hide pagination buttons if no rows or all fit on one page
            function checkAndHidePagination() {
                const totalStatus = parseInt($('#total-users').val()) || 0;
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val()) || 10;

                // Only hide pagination if total rows is less than or equal to rows per page
                if (totalStatus <= rowsPerPage) {
                    $('#pagination').hide();
                } else {
                    $('#pagination').show();
                }
            }

            // Run after rows per page changes
            $('#rowsPerPageSelect').on('change', function() {
                const rowsPerPage = parseInt($(this).val()) || 10;
                const totalRows = parseInt($('#total-users').val()) || 0;
                
                // Update the rows per page display
                $('#rowsPerPage').text(rowsPerPage);
                
                // Show pagination if total rows is greater than rows per page
                if (totalRows > rowsPerPage) {
                    $('#pagination').show();
                }
                
                // Update pagination
                updatePagination();
            });

            // Date filter handling (only update UI, don't filter yet)
            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();

                // Hide all date groups first
                $('.date-group').addClass('d-none');
                $('#date-filter-error').empty();

                // Show appropriate date inputs based on selection
                if (filterType) {
                    // Show the specific date input group inline
                    if (filterType === 'mdy') {
                        $('#mdy-group-inline').removeClass('d-none');
                    } else if (filterType === 'month') {
                        $('#month-group-inline').removeClass('d-none');
                    } else if (filterType === 'year') {
                        $('#year-group-inline').removeClass('d-none');
                    } else if (filterType === 'month_year') {
                        $('#monthyear-group-inline').removeClass('d-none');
                    }
                }
            });

            // Handle month/year selection changes
            $('#monthSelect, #yearSelect').on('change', function() {
                const month = $('#monthSelect').val();
                const year = $('#yearSelect').val();

                if (month && year) {
                    filterStatusTable();
                }
            });

            // Handle date range changes
            $('#dateFrom, #dateTo').on('change', function() {
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                if (dateFrom && dateTo) {
                    filterStatusTable();
                }
            });

            // Custom filter function for this page that works with the pagination system
            function filterStatusTable() {
                const searchText = $('#searchStatus').val().toLowerCase();
                const filterStatus = $('#filterStatus').val();
                const filterAction = $('#filterAction').val();
                const dateFilterType = $('#dateFilter').val();
                
                // Get date values based on filter type
                let fromDate, toDate;
                if (dateFilterType === 'mdy') {
                    fromDate = $('#dateFrom').val();
                    toDate = $('#dateTo').val();
                } else if (dateFilterType === 'month') {
                    fromDate = $('#monthFrom').val();
                    toDate = $('#monthTo').val();
                } else if (dateFilterType === 'year') {
                    fromDate = $('#yearFrom').val();
                    toDate = $('#yearTo').val();
                } else if (dateFilterType === 'month_year') {
                    fromDate = $('#monthYearFrom').val();
                    toDate = $('#monthYearTo').val();
                }
                
                // Legacy variables - will be removed in future refactoring
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                console.log("Filtering by status:", filterStatus, "Length:", filterStatus ? filterStatus.length : 0);
                console.log("Filtering by action:", filterAction, "Length:", filterAction ? filterAction.length : 0);

                // Filter the rows based on search text and status
                window.filteredRows = window.allRows.filter(row => {
                    // Get text content for search
                    const rowText = row.textContent.toLowerCase();

                    // Get status cell text - ensuring we normalize whitespace
                    const statusCell = row.querySelector('td:nth-child(3)');
                    const statusText = statusCell ? statusCell.textContent.replace(/\s+/g, ' ').trim() : '';

                    // Get action cell text
                    const actionCell = row.querySelector('td:nth-child(4)');
                    const actionText = actionCell ? actionCell.textContent.replace(/\s+/g, ' ').trim() : '';

                    // Get date info
                    const dateCell = row.querySelector('td:nth-child(5)').textContent;
                    const date = new Date(dateCell);

                    // Text search match
                    const searchMatch = rowText.includes(searchText);

                    // Status filtering
                    let statusMatch = true;
                    if (filterStatus && filterStatus !== 'all') {
                        statusMatch = statusText.toLowerCase() === filterStatus.toLowerCase();
                    }

                    // Action filtering
                    let actionMatch = true;
                    if (filterAction && filterAction !== 'all') {
                        actionMatch = actionText.toLowerCase() === filterAction.toLowerCase();
                    }

                    // Date filtering
                    let dateMatch = true;
                    if (dateFilterType === 'mdy' && fromDate && toDate) {
                        const from = new Date(fromDate);
                        const to = new Date(toDate);
                        to.setHours(23, 59, 59); // Include the entire "to" day
                        dateMatch = date >= from && date <= to;
                    } else if (dateFilterType === 'month' && fromDate && toDate) {
                        // Parse month and year from the month input (YYYY-MM format)
                        const fromParts = fromDate.split('-');
                        const toParts = toDate.split('-');
                        
                        if (fromParts.length === 2 && toParts.length === 2) {
                            const fromYear = parseInt(fromParts[0]);
                            const fromMonth = parseInt(fromParts[1]);
                            const toYear = parseInt(toParts[0]);
                            const toMonth = parseInt(toParts[1]);
                            
                            const dateYear = date.getFullYear();
                            const dateMonth = date.getMonth() + 1; // JavaScript months are 0-based
                            
                            // Check if date falls within the month range
                            if (dateYear < fromYear || (dateYear === fromYear && dateMonth < fromMonth)) {
                                dateMatch = false;
                            }
                            
                            if (dateYear > toYear || (dateYear === toYear && dateMonth > toMonth)) {
                                dateMatch = false;
                            }
                        }
                    } else if (dateFilterType === 'year' && fromDate && toDate) {
                        const fromYear = parseInt(fromDate);
                        const toYear = parseInt(toDate);
                        const dateYear = date.getFullYear();
                        
                        dateMatch = dateYear >= fromYear && dateYear <= toYear;
                    } else if (dateFilterType === 'month_year' && fromDate && toDate) {
                        // This is the same as 'month' filter since month inputs also include year
                        const fromParts = fromDate.split('-');
                        const toParts = toDate.split('-');
                        
                        if (fromParts.length === 2 && toParts.length === 2) {
                            const fromYear = parseInt(fromParts[0]);
                            const fromMonth = parseInt(fromParts[1]);
                            const toYear = parseInt(toParts[0]);
                            const toMonth = parseInt(toParts[1]);
                            
                            const dateYear = date.getFullYear();
                            const dateMonth = date.getMonth() + 1;
                            
                            // Check if date falls within the month-year range
                            if (dateYear < fromYear || (dateYear === fromYear && dateMonth < fromMonth)) {
                                dateMatch = false;
                            }
                            
                            if (dateYear > toYear || (dateYear === toYear && dateMonth > toMonth)) {
                                dateMatch = false;
                            }
                        }
                    }

                    return searchMatch && statusMatch && actionMatch && dateMatch;
                });

                // Handle sorting if needed
                if (dateFilterType === 'asc' || dateFilterType === 'desc') {
                    window.filteredRows.sort(function(a, b) {
                        const dateA = new Date(a.querySelector('td:nth-child(5)').textContent);
                        const dateB = new Date(b.querySelector('td:nth-child(5)').textContent);
                        return dateFilterType === 'asc' ? dateA - dateB : dateB - dateA;
                    });
                }

                // Update total count in the UI
                $('#totalRows').text(window.filteredRows.length);

                // Update pagination with filtered rows
                updatePagination();

                // After pagination updates, check if we need to hide pagination controls
                setTimeout(checkAndHidePagination, 100);
            }

            // Delegate event for editing status
            $(document).on('click', '.edit-status', function() {
                var id = $(this).data('id');
                var asset = $(this).data('asset');
                var status = $(this).data('status');
                var action = $(this).data('action');
                var remarks = $(this).data('remarks');
                var disabled = $(this).data('disabled');

                $('#edit_status_id').val(id);
                $('#edit_asset_tag').val(asset);
                $('#edit_status').val(status);
                $('#edit_action').val(action);
                $('#edit_remarks').val(remarks);
                $('#edit_is_disabled').prop('checked', disabled == 1);

                $('#editStatusModal').modal('show');
            });

            // Global variable for deletion
            var deleteStatusId = null;

            // Delegate event for delete button to show modal
            $(document).on('click', '.delete-status', function(e) {
                e.preventDefault();
                deleteStatusId = $(this).data('id');
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });

            // When confirm delete button is clicked, perform AJAX delete
            $('#confirmDelete').on('click', function() {
                if (deleteStatusId) {
                    $.ajax({
                        url: 'equipment_status.php',
                        method: 'POST',
                        data: {
                            action: 'delete',
                            status_id: deleteStatusId
                        },
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                // Properly hide the modal
                                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                                if (deleteModal) {
                                    deleteModal.hide();
                                }

                                // Ensure scrolling is restored
                                $('body').removeClass('modal-open');
                                $('.modal-backdrop').remove();
                                $('body').css('overflow', '');
                                $('body').css('padding-right', '');

                                // Show success message
                                showToast(response.message || 'Status deleted successfully', 'success');

                                // Refresh table without page reload
                                refreshTable();
                            } else {
                                showToast(response.message, 'error');
                                var deleteModalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                                deleteModalInstance.hide();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Error Response:", xhr.responseText);
                            try {
                                // Try to parse the response as JSON
                                const errorResponse = JSON.parse(xhr.responseText);
                                showToast(errorResponse.message || 'Unknown error occurred', 'error');
                            } catch (e) {
                                // If it's not valid JSON, show the error
                                showToast('Error deleting status: ' + error, 'error');
                            }
                            var deleteModalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                            deleteModalInstance.hide();
                        }
                    });
                }
            });

            // AJAX submission for Add Status form using toast notifications
            $('#addStatusForm').on('submit', function(e) {
                e.preventDefault();
                const submitBtn = $(this).find('button[type="submit"]');
                const originalBtnText = submitBtn.text();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');

                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(result) {
                        if (result.status === 'success') {
                            // Properly hide the modal
                            const addModal = bootstrap.Modal.getInstance(document.getElementById('addStatusModal'));
                            if (addModal) {
                                addModal.hide();
                            }

                            // Reset form
                            $('#addStatusForm')[0].reset();

                            // Ensure scrolling is restored
                            $('body').removeClass('modal-open');
                            $('.modal-backdrop').remove();
                            $('body').css('overflow', '');
                            $('body').css('padding-right', '');

                            // Show success message
                            showToast(result.message || 'Status added successfully', 'success');

                            // Refresh table without page reload
                            refreshTable();
                        } else {
                            showToast(result.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error adding status: ' + error, 'error');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).text(originalBtnText);
                    }
                });
            });

            // Ensure proper cleanup when modal is hidden
            $('#addStatusModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();

                // Ensure scrolling is restored
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
                $('body').css('overflow', '');
                $('body').css('padding-right', '');
            });

            // AJAX submission for Edit Status form using toast notifications
            $('#edit_status_form').on('submit', function(e) {
                e.preventDefault();
                const submitBtn = $(this).find('button[type="submit"]');
                const originalBtnText = submitBtn.text();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');

                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(result) {
                        // Always re-enable the button
                        submitBtn.prop('disabled', false).text(originalBtnText);

                        // Regardless of changes, show a success toast.
                        if (result.status === 'success') {
                            // Properly hide the modal
                            const editModal = bootstrap.Modal.getInstance(document.getElementById('editStatusModal'));
                            if (editModal) {
                                editModal.hide();
                            }

                            // Ensure scrolling is restored
                            $('body').removeClass('modal-open');
                            $('.modal-backdrop').remove();
                            $('body').css('overflow', '');
                            $('body').css('padding-right', '');

                            // Show success message
                            showToast(result.message || 'Status updated successfully', 'success');

                            // Refresh table without page reload
                            refreshTable();
                        } else {
                            showToast(result.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        submitBtn.prop('disabled', false).text(originalBtnText);
                        showToast('Error updating status: ' + error, 'error');
                    }
                });
            });

            // Ensure proper cleanup when edit modal is hidden
            $('#editStatusModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();

                // Ensure scrolling is restored
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
                $('body').css('overflow', '');
                $('body').css('padding-right', '');
            });

            // Run on page load with a longer delay to ensure DOM is fully processed
            setTimeout(checkAndHidePagination, 300);

            // Filter button now triggers filtering using current filter values
            $('#filterBtn').on('click', function(e) {
                // Validate date filters before applying
                let isValid = true;
                let errorMessage = '';
                let dateFilterType = $('#dateFilter').val();
                
                if (dateFilterType) {
                    let fromDate, toDate;
                    
                    if (dateFilterType === 'mdy') {
                        fromDate = $('#dateFrom').val();
                        toDate = $('#dateTo').val();
                        
                        // Check if dates are empty
                        if (fromDate && !toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To dates.';
                        } else if (!fromDate && toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To dates.';
                        } else if (fromDate && toDate) {
                            // Check if from date is greater than to date
                            if (new Date(fromDate) > new Date(toDate)) {
                                isValid = false;
                                errorMessage = 'From date cannot be greater than To date.';
                            }
                        }
                    } else if (dateFilterType === 'month') {
                        fromDate = $('#monthFrom').val();
                        toDate = $('#monthTo').val();
                        
                        // Check if dates are empty
                        if (fromDate && !toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To months.';
                        } else if (!fromDate && toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To months.';
                        } else if (fromDate && toDate) {
                            // Check if from date is greater than to date
                            if (new Date(fromDate) > new Date(toDate)) {
                                isValid = false;
                                errorMessage = 'From month cannot be greater than To month.';
                            }
                        }
                    } else if (dateFilterType === 'year') {
                        fromDate = $('#yearFrom').val();
                        toDate = $('#yearTo').val();
                        
                        // Check if years are empty
                        if (fromDate && !toDate) {
                            isValid = false;
                            errorMessage = 'Please enter both From and To years.';
                        } else if (!fromDate && toDate) {
                            isValid = false;
                            errorMessage = 'Please enter both From and To years.';
                        } else if (fromDate && toDate) {
                            // Check if from year is greater than to year
                            if (parseInt(fromDate) > parseInt(toDate)) {
                                isValid = false;
                                errorMessage = 'From year cannot be greater than To year.';
                            }
                        }
                    } else if (dateFilterType === 'month_year') {
                        fromDate = $('#monthYearFrom').val();
                        toDate = $('#monthYearTo').val();
                        
                        // Check if dates are empty
                        if (fromDate && !toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To month-years.';
                        } else if (!fromDate && toDate) {
                            isValid = false;
                            errorMessage = 'Please select both From and To month-years.';
                        } else if (fromDate && toDate) {
                            // Check if from date is greater than to date
                            if (new Date(fromDate) > new Date(toDate)) {
                                isValid = false;
                                errorMessage = 'From month-year cannot be greater than To month-year.';
                            }
                        }
                    }
                }
                
                if (!isValid) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    $('#date-filter-error').empty();
                    $('#date-filter-error').html('<div class="validation-tooltip">' + errorMessage + '</div>');
                    setTimeout(function() {
                        $('#date-filter-error .validation-tooltip').fadeOut('slow', function() {
                            $('#date-filter-error').empty();
                        });
                    }, 3000);
                    return false;
                }
                
                $('#date-filter-error').empty();
                filterStatusTable();
            });
            // Clear button resets filters and triggers filter function
            $('#clearBtn').on('click', function() {
                $('#filterStatus').val('').trigger('change');
                $('#filterAction').val('').trigger('change');
                $('#dateFilter').val('').trigger('change');
                $('#dateFrom').val('');
                $('#dateTo').val('');
                $('#monthFrom').val('');
                $('#monthTo').val('');
                $('#yearFrom').val('');
                $('#yearTo').val('');
                $('#monthYearFrom').val('');
                $('#monthYearTo').val('');
                $('#searchStatus').val('');
                $('.date-group').addClass('d-none');
                $('#date-filter-error').empty();
                filterStatusTable();
            });
        });
    </script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2 for Asset Tag with tags (creatable)
            $('#add_status_asset_tag').select2({
                tags: false,
                placeholder: 'Select Asset Tag',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#addStatusModal')
            });

            // Proper management of modal backdrops when closing a modal
            $('#addStatusModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
                // Only remove backdrop if no other modals are open
                if (!$('.modal.show').length) {
                    $('body').removeClass('modal-open');
                    $('body').css('padding-right', '');
                    $('.modal-backdrop').remove();
                }
            });
        });
    </script>

    <!-- Force hide pagination buttons if no data -->
    <script>
        (function() {
            // Function to check and hide pagination
            function forcePaginationCheck() {
                const totalRows = parseInt(document.getElementById('total-users')?.value || '0');
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect')?.value || '10');
                const pagination = document.getElementById('pagination');

                // Only hide pagination if total rows is less than or equal to rows per page
                if (totalRows <= rowsPerPage) {
                    if (pagination) pagination.style.cssText = 'display: none !important';
                } else {
                    if (pagination) pagination.style.cssText = 'display: flex !important';
                }
            }

            // Run immediately
            forcePaginationCheck();

            // Also run after a delay to ensure DOM is fully loaded
            setTimeout(forcePaginationCheck, 500);
            setTimeout(forcePaginationCheck, 1000);

            // Add event listener for DOMContentLoaded
            document.addEventListener('DOMContentLoaded', forcePaginationCheck);
        })();
    </script>
    <script>
        // Function to refresh table without reloading the page
        function refreshTable() {
            // Create a temporary container to hold the loaded content
            const tempContainer = $('<div>');

            // Load the page content into the temporary container
            tempContainer.load(location.href + ' #statusTable', function() {
                // Extract only the tbody content
                const newTbody = tempContainer.find('#statusTbody').html();

                // Replace the current tbody content with the new content
                $('#statusTbody').html(newTbody);

                // Update pagination after table refresh
                window.allRows = Array.from(document.querySelectorAll('#statusTbody tr'));
                window.filteredRows = window.allRows;

                // Update total count in the UI
                $('#totalRows').text(window.allRows.length);

                // Reinitialize pagination
                if (typeof updatePagination === 'function') {
                    updatePagination();
                }

                // Check if pagination buttons should be hidden
                if (typeof checkAndHidePagination === 'function') {
                    setTimeout(checkAndHidePagination, 100);
                }
            });
        }
    </script>

</body>

</html>
