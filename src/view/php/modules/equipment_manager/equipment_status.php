<?php
require_once '../../../../../config/ims-tmdd.php';
session_start();

// start buffering all output (header/sidebar/footer HTML will be captured)
ob_start();

include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';
// For AJAX requests, we want to handle them separately
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    ob_clean();
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }

    // Initialize RBAC
    $rbac = new RBACService($pdo, $_SESSION['user_id']);

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $response = array('status' => 'error', 'message' => 'Invalid action');

        switch ($_POST['action']) {
            case 'add':
                try {
                    // Check if user has Create privilege
                    if (!$rbac->hasPrivilege('Equipment Management', 'Create')) {
                        throw new Exception('You do not have permission to add equipment status');
                    }

                    // Validate required fields
                    if (empty($_POST['asset_tag'])) {
                        throw new Exception('Asset Tag is required');
                    }

                    $pdo->beginTransaction();

                    // Before inserting into the database
                    error_log('Status to insert: ' . $_POST['status']);

                    // Insert equipment status
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

                    $newStatusId = $pdo->lastInsertId();

                    // Prepare audit log data
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

                    $pdo->commit();

                    $response = [
                        'status' => 'success',
                        'message' => 'Equipment Status has been added successfully.'
                    ];
                } catch (Exception $e) {
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

                    $pdo->beginTransaction();

                    // Get old status details for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $oldStatus = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$oldStatus) {
                        throw new Exception('Status not found');
                    }

                    // Update equipment status
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

                    $pdo->commit();
                    $response = [
                        'status' => 'success',
                        'message' => 'Status updated successfully'
                    ];
                } catch (Exception $e) {
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
                try {
                    // Check if user has Remove privilege
                    if (!$rbac->hasPrivilege('Equipment Management', 'Remove')) {
                        throw new Exception('You do not have permission to delete equipment status');
                    }

                    if (!isset($_POST['status_id'])) {
                        throw new Exception('Status ID is required');
                    }

                    // Get status details before deletion for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $statusData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$statusData) {
                        throw new Exception('Status not found');
                    }

                    // Get the asset tag
                    $assetTag = $statusData['asset_tag'];

                    // Begin transaction
                    $pdo->beginTransaction();

                    // Prepare audit log data
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
                        'Delete',
                        'Equipment status has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);

                    // Perform the delete on equipment_status
                    $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);

                    // No longer check for active status records or cascade deletions
                    // We only update the equipment_status record

                    $_SESSION['success'] = "Equipment Status deleted successfully.";
                    $response = [
                        'status' => 'success',
                        'message' => 'Equipment Status deleted successfully.'
                    ];

                    // Commit transaction
                    $pdo->commit();
                } catch (Exception $e) {
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

// Regular page load continues here...
include('../../general/header.php');

// Initialize RBAC
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;

// Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Management', 'View');

// Button flags
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

// GET deletion (if applicable)
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

            // Begin transaction
            $pdo->beginTransaction();

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

            // Perform the delete on equipment_status
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
        th.sortable.asc::after {
            content: " ▲";
        }

        th.sortable.desc::after {
            content: " ▼";
        }

        /* Styling for read-only fields */
        input[readonly] {
            background-color: #e9ecef;
            opacity: 0.65;
            cursor: not-allowed;
        }

        .filter-btn-custom {
            background: #181818 !important;
            color: #fff !important;
            border-radius: 10px !important;
            margin-left: 8px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            min-width: 180px !important;
            width: 220px !important;
            justify-content: flex-start !important;
            transition: background 0.2s;
        }

        .filter-btn-custom:hover,
        .filter-btn-custom:focus {
            background: #3c3c3c !important;
            color: #fff !important;
        }

        .filter-btn-custom:active {
            background: #222 !important;
            color: #fff !important;
        }

        .clear-btn-custom {
            background: #757d84 !important;
            color: #fff !important;
            border-radius: 10px !important;
            margin-left: 8px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            min-width: 120px !important;
            width: 180px !important;
            justify-content: flex-start !important;
            transition: background 0.2s;
        }

        .clear-btn-custom:hover,
        .clear-btn-custom:focus {
            background: #6c757d !important;
            color: #fff !important;
        }

        .clear-btn-custom:active {
            background: #5a6268 !important;
            color: #fff !important;
        }
    </style>
</head>

<body>

    <div class="main-container">
        <header class="main-header">
            <h1>Equipment Status Management</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Equipment Status</h2>
            </div>

            <div class="card-body">
                <div class="container-fluid px-0">
                    <div class="filter-container">
                        <div class="col-auto">
                            <?php if ($canCreate): ?>
                                <button class="btn btn-dark" id="openAddStatusModalBtn" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                                    <i class="bi bi-plus-lg"></i> Add New Status
                                </button>
                            <?php endif; ?>
                        </div>
                        <!-- <div class="col-md-3">
                            <select class="form-select" id="filterStatus">
                                <option value="">Filter by Status</option>
                                <?php
                                // Get unique status values from the database
                                try {
                                    // First, let's check for any problematic values for debugging
                                    $debugQuery = $pdo->query("SELECT equipment_status_id, asset_tag, status, LENGTH(status) as status_length FROM equipment_status WHERE is_disabled = 0 ORDER BY status");
                                    $debugResults = $debugQuery->fetchAll(PDO::FETCH_ASSOC);

                                    // Log the raw status values with detailed info
                                    foreach ($debugResults as $row) {
                                        $statusValue = $row['status'];
                                        $hexChars = '';
                                        for ($i = 0; $i < strlen($statusValue); $i++) {
                                            $hexChars .= '0x' . dechex(ord($statusValue[$i])) . ' ';
                                        }
                                        error_log("Status ID {$row['equipment_status_id']} (Asset: {$row['asset_tag']}): '{$statusValue}' - Length: {$row['status_length']} - Hex: {$hexChars}");
                                    }

                                    // Now get the distinct values for the dropdown, excluding empty values
                                    $statusOptions = $pdo->query("SELECT DISTINCT status FROM equipment_status WHERE is_disabled = 0 AND status IS NOT NULL AND TRIM(status) != '' ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);

                                    // Debug - print status values
                                    error_log("Equipment Status Values: " . print_r($statusOptions, true));

                                    foreach ($statusOptions as $status) {
                                        // Normalize the status value to remove any extra whitespace
                                        $normalizedStatus = trim(preg_replace('/\s+/', ' ', $status));

                                        // Skip empty values
                                        if (empty($normalizedStatus)) {
                                            continue;
                                        }

                                        echo "<option value=\"" . htmlspecialchars($normalizedStatus) . "\">" . htmlspecialchars($normalizedStatus) . "</option>";
                                    }
                                } catch (PDOException $e) {
                                    error_log("Error retrieving status options: " . $e->getMessage());
                                    // If query fails, fallback to static options
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
                        <div class="col-md-3 d-flex flex-column align-items-start" style="position:relative;">
                            <select class="form-select mb-2" id="dateFilter">
                                <option value="">Filter by Date</option>
                                <option value="desc">Newest to Oldest</option>
                                <option value="asc">Oldest to Newest</option>
                                <option value="month">Specific Month</option>
                                <option value="range">Custom Date Range</option>
                            </select>
                            <button id="filterBtn" type="button" class="btn filter-btn-custom mt-1" style="width:100%;"> 
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" class="bi bi-funnel" viewBox="0 0 16 16">
                                    <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .39.812L10 7.21V13.5a.5.5 0 0 1-.684.474l-2-1A.5.5 0 0 1 7 12.5V7.21L1.61 1.812A.5.5 0 0 1 1.5 1.5zM2.437 2l5.36 5.812a.5.5 0 0 1 .123.329v5.54l1 0.5V8.14a.5.5 0 0 1 .123-.329L13.563 2H2.437z"/>
                                </svg>
                                <span style="color:#fff;">Filter</span>
                            </button>
                        </div>
                        <div class="col-md-3 d-flex flex-column align-items-end" style="position:relative;">
                            <div class="input-group mb-2">
                                <input type="text" id="searchStatus" class="form-control" placeholder="Search status...">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                            </div>
                            <button id="clearBtn" type="button" class="btn clear-btn-custom" style="width:100%;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" class="bi bi-x-circle" viewBox="0 0 16 16">
                                    <path d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16z"/>
                                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                </svg>
                                <span style="color:#fff;">Clear</span>
                            </button>
                        </div>
                    </div>

                    <!--Date Inputs Row -->
                        <!-- <div id="dateInputsContainer" class="date-inputs-container">
                        <div class="month-picker-container" id="monthPickerContainer">
                            <select class="form-select" id="monthSelect">
                                <option value="">Select Month</option>
                                <?php
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                foreach ($months as $index => $month) {
                                    echo "<option value='" . ($index + 1) . "'>" . $month . "</option>";
                                }
                                ?>
                            </select>
                            <select class="form-select" id="yearSelect">
                                <option value="">Select Year</option>
                                <?php
                                $currentYear = date('Y');
                                for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                    echo "<option value='" . $year . "'>" . $year . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="date-range-container" id="dateRangePickers">
                            <input type="date" class="form-control" id="dateFrom" placeholder="From">
                            <input type="date" class="form-control" id="dateTo" placeholder="To">
                        </div>
                    </div> -->

                        <!-- Buttons -->
                        <!-- Buttons-->
                        <div class="col-6 col-md-2 d-grid">
                            <button type="submit" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
                        </div>

                        <div class="col-6 col-md-2 d-grid">
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary shadow-sm"><i class="bi bi-x-circle"></i> Clear</a>
                        </div>

                        <div class="col-12 col-md-3 d-grid">
                            <a href="equipStat_change_log.php" class="btn btn-primary"><i class="bi bi-card-list"></i> View Equipment Status Changes</a>
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
                                    <button id="prevPage"
                                        class="btn btn-outline-primary d-flex align-items-center gap-1">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </button>
                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="30">30</option>
                                        <option value="50">50</option>
                                    </select>
                                    <button id="nextPage"
                                        class="btn btn-outline-primary d-flex align-items-center gap-1">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </button>
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

            // Real-time search & filter
            $('#searchStatus, #filterStatus').on('input change', function() {
                filterStatusTable();
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

                if (totalStatus <= rowsPerPage) {
                    $('#prevPage, #nextPage').css('display', 'none !important').hide();
                }

                // Also check for visible rows (for when filtering is applied)
                const visibleRows = $('#statusTable tbody tr:visible').length;
                if (visibleRows <= rowsPerPage) {
                    $('#prevPage, #nextPage').css('display', 'none !important').hide();
                }
            }

            // Run after rows per page changes
            $('#rowsPerPageSelect').on('change', function() {
                setTimeout(checkAndHidePagination, 100);
            });

            // Date filter handling (only update UI, don't filter yet)
            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();

                // Hide all containers first
                $('#dateInputsContainer').hide();
                $('#monthPickerContainer').hide();
                $('#dateRangePickers').hide();

                // Show appropriate containers based on selection
                if (filterType === 'month') {
                    $('#dateInputsContainer').show();
                    $('#monthPickerContainer').show();
                } else if (filterType === 'range') {
                    $('#dateInputsContainer').show();
                    $('#dateRangePickers').show();
                } else if (filterType === 'desc' || filterType === 'asc') {
                    // Apply sorting without showing date inputs
                    filterStatusTable();
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
                const dateFilterType = $('#dateFilter').val();
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                console.log("Filtering by status:", filterStatus, "Length:", filterStatus ? filterStatus.length : 0);

                // Filter the rows based on search text and status
                window.filteredRows = window.allRows.filter(row => {
                    // Get text content for search
                    const rowText = row.textContent.toLowerCase();

                    // Get status cell text - ensuring we normalize whitespace
                    const statusCell = row.querySelector('td:nth-child(3)');
                    // Normalize whitespace by trimming and removing extra spaces
                    const statusText = statusCell ? statusCell.textContent.replace(/\s+/g, ' ').trim() : '';

                    // Get date info
                    const dateCell = row.querySelector('td:nth-child(5)').textContent;
                    const date = new Date(dateCell);

                    // Text search match
                    const searchMatch = rowText.includes(searchText);

                    // Status filtering - checking for selected value with flexible matching
                    let statusMatch = true;
                    if (filterStatus && filterStatus.trim() !== '') {
                        // Only do status filtering if we have a real value selected
                        statusMatch = statusText.toLowerCase() === filterStatus.toLowerCase();
                    }

                    // Date filtering
                    let dateMatch = true;
                    if (dateFilterType === 'month' && selectedMonth && selectedYear) {
                        dateMatch = (date.getMonth() + 1 === parseInt(selectedMonth)) &&
                            (date.getFullYear() === parseInt(selectedYear));
                    } else if (dateFilterType === 'range' && dateFrom && dateTo) {
                        const from = new Date(dateFrom);
                        const to = new Date(dateTo);
                        to.setHours(23, 59, 59); // Include the entire "to" day
                        dateMatch = date >= from && date <= to;
                    }

                    return searchMatch && statusMatch && dateMatch;
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
            $('#filterBtn').on('click', function() {
                filterStatusTable();
            });
            // Clear button resets filters and triggers filter function
            $('#clearBtn').on('click', function() {
                $('#filterStatus').val('').trigger('change');
                $('#dateFilter').val('').trigger('change');
                $('#monthSelect').val('').trigger('change');
                $('#yearSelect').val('').trigger('change');
                $('#dateFrom').val('');
                $('#dateTo').val('');
                if (typeof filterStatusTable === 'function') {
                    filterStatusTable();
                }
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
                const prevBtn = document.getElementById('prevPage');
                const nextBtn = document.getElementById('nextPage');

                if (totalRows <= rowsPerPage) {
                    if (prevBtn) prevBtn.style.cssText = 'display: none !important';
                    if (nextBtn) nextBtn.style.cssText = 'display: none !important';
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