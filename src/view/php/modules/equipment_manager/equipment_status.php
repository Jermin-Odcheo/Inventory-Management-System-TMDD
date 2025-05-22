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

                    // Begin transaction
                    $pdo->beginTransaction();

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
                        'Equipment Status',
                        'Delete',
                        'Equipment status has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);

                    // Now perform the delete
                    $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);

                    // Commit transaction
                    $pdo->commit();

                    $_SESSION['success'] = "Equipment Status deleted successfully.";
                    $response = [
                        'status' => 'success',
                        'message' => 'Equipment Status deleted successfully.'
                    ];
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

            // Now perform the delete
            $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE equipment_status_id = ?");
            $stmt->execute([$id]);

            // Commit transaction
            $pdo->commit();

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
                        <div class="col-md-3">
                            <select class="form-select" id="filterStatus">
                                <option value="">Filter by Status</option>
<option value="Maintenance">Maintenance</option>
<option value="Working">Working</option>
<option value="For Repair">For Repair</option>
<option value="For Disposal">For Disposal</option>
<option value="Disposed">Disposed</option>
<option value="Condemned">Condemned</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="dateFilter">
                                <option value="">Filter by Date</option>
                                <option value="desc">Newest to Oldest</option>
                                <option value="asc">Oldest to Newest</option>
                                <option value="month">Specific Month</option>
                                <option value="range">Custom Date Range</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" id="searchStatus" class="form-control" placeholder="Search status...">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- Date Inputs Row -->
                    <div id="dateInputsContainer" class="date-inputs-container">
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
                    </div>
                </div>

                <div class="table-responsive" id="table">
                    <table class="table" id="statusTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Asset Tag</th>
                                <th>Status</th>
                                <th>Process Action Taken</th>
                                <th>Created Date</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                $totalStatus = 0;
                                try {
                                    $countStmt = $pdo->query("SELECT COUNT(*) FROM equipment_status WHERE is_disabled = 0");
                                    $totalStatus = $countStmt->fetchColumn();
                                } catch (PDOException $e) {
                                    // Fallback to 0 if query fails
                                }
                                ?>
                                <input type="hidden" id="total-users" value="<?= $totalStatus ?>">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage"><?= min($totalStatus, 10) ?></span> of <span
                                    id="totalRows"><?= $totalStatus ?></span>
                                entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1" <?= $totalStatus <= 10 ? 'style="display:none !important;"' : '' ?>>
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>
                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1" <?= $totalStatus <= 10 ? 'style="display:none !important;"' : '' ?>>
                                    Next <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <ul class="pagination justify-content-center" id="pagination"></ul>
                            </div>
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
    <option value="">Select or type Asset Tag</option>
    <?php
    // Fetch unique asset tags from equipment_details and equipment_location
    $assetTags = [];
    $stmt1 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_details WHERE is_disabled = 0");
    $assetTags = array_merge($assetTags, $stmt1->fetchAll(PDO::FETCH_COLUMN));
    $stmt2 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_location WHERE is_disabled = 0");
    $assetTags = array_merge($assetTags, $stmt2->fetchAll(PDO::FETCH_COLUMN));
    $assetTags = array_unique(array_filter($assetTags));
    sort($assetTags);
    foreach ($assetTags as $tag) {
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
                            <input type="text" class="form-control" id="edit_asset_tag" name="asset_tag">
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
        $(document).ready(function() {
            // Real-time search & filter
            $('#searchStatus, #filterStatus').on('input change', function() {
                filterTable();
            });

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
            
            // Run on page load with a longer delay to ensure DOM is fully processed
            setTimeout(checkAndHidePagination, 300);
            
            // Run after any filter changes
            $('#searchStatus, #filterStatus, #dateFilter, #monthSelect, #yearSelect, #dateFrom, #dateTo').on('change input', function() {
                setTimeout(checkAndHidePagination, 100);
            });
            
            // Run after rows per page changes
            $('#rowsPerPageSelect').on('change', function() {
                setTimeout(checkAndHidePagination, 100);
            });

            // Date filter handling
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
                    filterTable();
                }
            });
            
            // Handle month/year selection changes
            $('#monthSelect, #yearSelect').on('change', function() {
                const month = $('#monthSelect').val();
                const year = $('#yearSelect').val();
                
                if (month && year) {
                    filterTable();
                }
            });
            
            // Handle date range changes
            $('#dateFrom, #dateTo').on('change', function() {
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();
                
                if (dateFrom && dateTo) {
                    filterTable();
                }
            });

            function filterTable() {
                const searchText = $('#searchStatus').val().toLowerCase();
                const filterStatus = $('#filterStatus').val().toLowerCase();
                const dateFilterType = $('#dateFilter').val();
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                $(".table tbody tr").each(function() {
                    const row = $(this);
                    const rowText = row.text().toLowerCase();
                    const statusCell = row.find('td:eq(2)').text().toLowerCase();
                    const dateCell = row.find('td:eq(4)').text(); // Created date column
                    const date = new Date(dateCell);
                    
                    const searchMatch = rowText.indexOf(searchText) > -1;
                    const statusMatch = !filterStatus || statusCell === filterStatus;
                    
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

                    row.toggle(searchMatch && statusMatch && dateMatch);
                });
                
                // Handle sorting if needed
                if (dateFilterType === 'asc' || dateFilterType === 'desc') {
                    const tbody = $('.table tbody');
                    const rows = tbody.find('tr').toArray();
                    
                    rows.sort(function(a, b) {
                        const dateA = new Date($(a).find('td:eq(4)').text());
                        const dateB = new Date($(b).find('td:eq(4)').text());
                        return dateFilterType === 'asc' ? dateA - dateB : dateB - dateA;
                    });
                    
                    tbody.append(rows);
                }
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
                                $('#statusTable').load(location.href + ' #statusTable', function() {
                                    showToast(response.message, 'success');
                                });
                            } else {
                                showToast(response.message, 'error');
                            }
                            var deleteModalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                            deleteModalInstance.hide();
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
                            $('#addStatusModal').modal('hide');
// Remove any lingering modal backdrops and reset body class if needed
setTimeout(function() {
    if ($('.modal-backdrop').length) {
        $('.modal-backdrop').remove();
    }
    if ($('body').hasClass('modal-open') && $('.modal.show').length === 0) {
        $('body').removeClass('modal-open');
        $('body').css('padding-right', '');
    }
}, 500);
$('#statusTable').load(location.href + ' #statusTable', function() {
    showToast(result.message, 'success');
});
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

            $('#addStatusModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
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
                            $('#editStatusModal').modal('hide');
                            $('#statusTable').load(location.href + ' #statusTable', function() {
                                showToast(result.message, 'success');
                            });
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

            $('#editStatusModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
            });
        });
    </script>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#openAddStatusModalBtn').on('click', function() {
            setTimeout(function() {
                if ($('.modal-backdrop').length) {
                    $('.modal-backdrop').remove();
                }
                if ($('body').hasClass('modal-open') && $('.modal.show').length === 0) {
                    $('body').removeClass('modal-open');
                    $('body').css('padding-right', '');
                }
            }, 10);
        });
        // Initialize Select2 for Asset Tag with tags (creatable)
        $('#add_status_asset_tag').select2({
            tags: true,
            placeholder: 'Select or type Asset Tag',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#addStatusModal')
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
</body>

</html>