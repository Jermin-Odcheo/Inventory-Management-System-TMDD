<?php
// Start output buffering at the very beginning
ob_start();

ini_set('display_errors', 0); // Disable error display for production
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Add this at the very top of your file
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Is AJAX: ' . (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'));
error_log('POST Data: ' . print_r($_POST, true));

session_start();
require_once('../../../../../config/ims-tmdd.php');

// For AJAX requests, we want to handle them separately
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Clear any previous output and set JSON header
    ob_clean();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $response = array('status' => 'error', 'message' => 'Invalid action');
        
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Validate required fields
                    if (empty($_POST['asset_tag'])) {
                        throw new Exception('Asset Tag is required');
                    }
                    if (empty($_POST['status'])) {
                        throw new Exception('Status is required');
                    }
                    if (empty($_POST['action_description'])) {
                        throw new Exception('Action is required');
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
                            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditResult = $auditStmt->execute([
                        $_SESSION['user_id'],
                        $newStatusId,
                        'Equipment Status',
                        'Add',
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
                    // Validate required fields
                    if (empty($_POST['status_id'])) {
                        throw new Exception('Status ID is required');
                    }
                    if (empty($_POST['asset_tag'])) {
                        throw new Exception('Asset Tag is required');
                    }
                    if (empty($_POST['status'])) {
                        throw new Exception('Status is required');
                    }
                    if (empty($_POST['action_description'])) {
                        throw new Exception('Action is required');
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
                        isset($_POST['is_disabled']) && $_POST['is_disabled'] === '1' ? 1 : 0,
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
                        'is_disabled' => isset($_POST['is_disabled']) && $_POST['is_disabled'] === '1' ? 1 : 0
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
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
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
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
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
                    $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);

                    // Commit transaction
                    $pdo->commit();

                    $_SESSION['success'] = "Equipment Status deleted successfully.";
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment Status deleted successfully.';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['errors'] = ["Error deleting status: " . $e->getMessage()];
                    $response['status'] = 'error';
                    $response['message'] = 'Error deleting status: ' . $e->getMessage();
                }

                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                break;
        }
    }

    // Ensure a JSON response is always sent
    echo json_encode($response);
    exit;
}

// Regular page load continues here...
include('../../general/header.php');

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
                    Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
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
            $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE equipment_status_id = ?");
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Status Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Add sidebar CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/sidebar.css">
    <!-- Add equipment manager CSS -->
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            padding-top: 70px;
        }
        .container-fluid {
            margin-left: 300px;
            padding: 20px;
            width: calc(100% - 300px);
        }
        h2.mb-4 {
            margin-top: 5px;
            margin-bottom: 15px !important;
        }
        .card.shadow {
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .container-fluid {
                margin-left: 0;
                width: 100%;
            }
        }
        .search-container {
            width: 250px;
        }
        .search-container input {
            padding-right: 30px;
        }
        .search-container i {
            color: #6c757d;
            pointer-events: none;
        }
        .form-select-sm {
            min-width: 150px;
            padding-right: 30px;
        }
        .d-flex.gap-2 {
            gap: 0.5rem !important;
        }
        
        /* Toast Container */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999; /* Increased z-index */
            pointer-events: none;
        }

        .custom-toast {
            pointer-events: auto;
            margin-bottom: 10px;
            min-width: 300px;
            max-width: 400px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease-in-out;
        }

        .custom-toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, #4CAF50, #8BC34A);
            transform-origin: left;
        }

        /* Toast Types */
        .toast-success { border-left: 4px solid #28a745; }
        .toast-error { border-left: 4px solid #dc3545; }
        .toast-warning { border-left: 4px solid #ffc107; }
        .toast-info { border-left: 4px solid #17a2b8; }

        /* Toast Header */
        .toast-header {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid #dee2e6;
        }

        .toast-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 500;
            flex-grow: 1;
        }

        /* Toast Body */
        .toast-body {
            padding: 12px 15px;
            color: #666;
        }
    </style>
</head>
<body>
<!-- Include Sidebar -->
<?php include('../../general/sidebar.php'); ?>
<!-- Main Content -->
<div class="container-fluid">
    <h2 class="mb-4">Equipment Status Management</h2>


    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul"></i> List of Equipment Status</span>
            <!-- Move search to header -->
            <div class="input-group w-auto">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="searchStatus" class="form-control" placeholder="Search status...">
            </div>
        </div>
        <div class="card-body p-3">
            <!-- Add Location Button and Filter -->
            <div class="d-flex justify-content-between mb-3">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1"
                            data-bs-toggle="modal" data-bs-target="#addStatusModal">
                        <i class="bi bi-plus-circle"></i>
                        <span>Add New Status</span>
                    </button>
                    <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                        <option value="">Filter By Status</option>
                        <option value="Working">Working</option>
                        <option value="For Repair">For Repair</option>
                        <option value="For Disposal">For Disposal</option>
                        <option value="Disposed">Disposed</option>
                    </select>
                    <!-- Add date filter controls -->
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" id="dateFilter" style="width: auto;">
                            <option value="">Filter by Date</option>
                            <option value="desc">Newest to Oldest</option>
                            <option value="asc">Oldest to Newest</option>
                            <option value="month">Specific Month</option>
                            <option value="range">Custom Date Range</option>
                        </select>
                        <!-- Date inputs container -->
                        <div id="dateInputsContainer" style="display: none;">
                            <!-- Month Picker -->
                            <div class="d-flex gap-2" id="monthPickerContainer" style="display: none;">
                                <select class="form-select form-select-sm" id="monthSelect" style="min-width: 130px;">
                                    <option value="">Select Month</option>
                                    <?php
                                    $months = [
                                        'January', 'February', 'March', 'April', 'May', 'June',
                                        'July', 'August', 'September', 'October', 'November', 'December'
                                    ];
                                    foreach ($months as $index => $month) {
                                        echo "<option value='" . ($index + 1) . "'>" . $month . "</option>";
                                    }
                                    ?>
                                </select>
                                <select class="form-select form-select-sm" id="yearSelect" style="min-width: 110px;">
                                    <option value="">Select Year</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                        echo "<option value='" . $year . "'>" . $year . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <!-- Date Range Pickers -->
                            <div class="d-flex gap-2" id="dateRangePickers" style="display: none;">
                                <input type="date" class="form-control form-control-sm" id="dateFrom" placeholder="From">
                                <input type="date" class="form-control form-control-sm" id="dateTo" placeholder="To">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status List Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm mb-0" id="table">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 7%">#</th>
                        <th style="width: 13%">Asset Tag</th>
                        <th style="width: 15%">Status</th>
                        <th style="width: 15%">Action</th>
                        <th style="width: 10%">Created Date</th>
                        <th style="width: 20%">Remarks</th>
                        <th style="width: 5%">Status</th>
                        <th style="width: 15%">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT * FROM equipment_status ORDER BY date_created DESC");
                        while ($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['equipment_status_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['asset_tag']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['action']) . "</td>";
                            // Output Created Date then Remarks to match the header order
                            echo "<td>" . date('Y-m-d H:i', strtotime($row['date_created'])) . "</td>";
                            echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
                            echo "<td>" . ($row['is_disabled'] ? '<span class=\"badge bg-danger\">Disabled</span>' : '<span class=\"badge bg-success\">Active</span>') . "</td>";
                            echo "<td>
                              <div class='d-flex justify-content-center gap-2'>
                                <button class='btn btn-sm btn-outline-primary edit-status' 
                                        data-id='" . htmlspecialchars($row['equipment_status_id']) . "'
                                        data-asset='" . htmlspecialchars($row['asset_tag']) . "'
                                        data-status='" . htmlspecialchars($row['status']) . "'
                                        data-action='" . htmlspecialchars($row['action']) . "'
                                        data-remarks='" . htmlspecialchars($row['remarks']) . "'
                                        data-disabled='" . htmlspecialchars($row['is_disabled']) . "'>
                                  <i class='far fa-edit'></i> Edit
                                </button>
                                <button class='btn btn-sm btn-outline-danger delete-status' 
                                        data-id='" . htmlspecialchars($row['equipment_status_id']) . "'>
                                  <i class='far fa-trash-alt'></i> Delete
                                </button>
                              </div>
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
        </div>
        <!-- Pagination Controls -->
        <div class="container-fluid">
            <div class="row align-items-center g-3">
                <!-- Pagination Info -->
                <div class="col-12 col-sm-auto">
                    <div class="text-muted">
                        Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows">100</span> entries
                    </div>
                </div>
                <div class="col-12 col-sm-auto ms-sm-auto">
                    <div class="d-flex align-items-center gap-2">
                        <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                            <i class="bi bi-chevron-left"></i>
                            Previous
                        </button>
                        <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="30">30</option>
                            <option value="50">50</option>
                        </select>
                        <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                            Next
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <!-- New Pagination Page Numbers -->
            <div class="row mt-3">
                <div class="col-12">
                    <ul class="pagination justify-content-center" id="pagination"></ul>
                </div>
            </div>
        </div> <!-- /.End of Pagination -->
    </div>
</div>

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
                        <label for="asset_tag" class="form-label">Asset Tag</label>
                        <input type="text" class="form-control" name="asset_tag" required>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="">Select Status</option>
                            <option value="Working">Working</option>
                            <option value="For Repair">For Repair</option>
                            <option value="For Disposal">For Disposal</option>
                            <option value="Disposed">Disposed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="action_description" class="form-label">Action</label>
                        <input type="text" class="form-control" name="action_description" required>
                    </div>
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3"></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Equipment Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                        <label for="edit_asset_tag" class="form-label">Asset Tag</label>
                        <input type="text" class="form-control" id="edit_asset_tag" name="asset_tag" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="Working">Working</option>
                            <option value="For Repair">For Repair</option>
                            <option value="For Disposal">For Disposal</option>
                            <option value="Disposed">Disposed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_action" class="form-label">Action</label>
                        <input type="text" class="form-control" id="edit_action" name="action_description" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_disabled" name="is_disabled">
                        <label class="form-check-label" for="edit_is_disabled">Disabled</label>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript and jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
<script src="<?php echo BASE_URL; ?>src/control/js/toast.js"></script>
<!-- Main Script -->
<script>
    $(document).ready(function () {
        // Add Status
        $('#addStatusForm').on('submit', function (e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');

            // Validate form
            const assetTag = $('input[name="asset_tag"]').val().trim();
            const status = $('select[name="status"]').val();
            const action = $('input[name="action_description"]').val().trim();

            if (!assetTag || !status || !action) {
                showToast('Please fill in all required fields', 'error');
                submitBtn.prop('disabled', false).html('<i class="bi bi-plus-circle"></i> Add Equipment Status');
                return;
            }

            const formData = $(this).serialize();
            console.log('Sending data:', formData); // Debug log

            $.ajax({
                url: 'equipment_status.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function (response) {
                    console.log('Success response:', response); // Debug log
                    if (response && response.status === 'success') {
                        showToast(response.message, 'success');
                        $('#addStatusModal').modal('hide');
                        $('#addStatusForm')[0].reset();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast(response.message || 'An error occurred while adding the status', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Ajax Error:', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    let errorMessage = 'Server error occurred';
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.message || errorMessage;
                        } catch (e) {
                            console.error('Parse error:', e);
                            errorMessage = 'Invalid server response';
                        }
                    }
                    
                    showToast(errorMessage, 'error');
                },
                complete: function () {
                    submitBtn.prop('disabled', false).html('<i class="bi bi-plus-circle"></i> Add Equipment Status');
                }
            });
        });

        // Delete Status (changed parameter from 'crud_action' to 'action')
        $('.delete-status').click(function (e) {
            e.preventDefault();
            var id = $(this).data('id');

            if (confirm('Are you sure you want to delete this status?')) {
                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: {
                        action: 'delete',
                        status_id: id
                    },
                    success: function (response) {
                        try {
                            var result = JSON.parse(response);
                            if (result.status === 'success') {
                                location.reload();
                            } else {
                                alert(result.message);
                            }
                        } catch (e) {
                            console.error('Parse error:', e);
                            location.reload();
                        }
                    }
                });
            }
        });

        // Edit Status
        $('.edit-status').click(function () {
            var id = $(this).data('id');
            var asset = $(this).data('asset');
            var status = $(this).data('status');
            var action = $(this).data('action');
            var remarks = $(this).data('remarks');
            var disabled = $(this).data('disabled');

            // Log the values for debugging
            console.log('Edit Status Data:', {
                id: id,
                asset: asset,
                status: status,
                action: action,
                remarks: remarks,
                disabled: disabled
            });

            $('#edit_status_id').val(id);
            $('#edit_asset_tag').val(asset);
            $('#edit_status').val(status);
            $('#edit_action').val(action);
            $('#edit_remarks').val(remarks);
            $('#edit_is_disabled').prop('checked', disabled == 1);

            $('#editStatusModal').modal('show');
        });

        // Update Status
        $('#edit_status_form').on('submit', function (e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');

            // Validate form
            const statusId = $('#edit_status_id').val();
            const assetTag = $('#edit_asset_tag').val().trim();
            const status = $('#edit_status').val();
            const actionDescription = $('#edit_action').val().trim();

            if (!statusId || !assetTag || !status || !actionDescription) {
                showToast('Please fill in all required fields', 'error');
                submitBtn.prop('disabled', false).html('Update Status');
                return;
            }

            // Serialize form data with correct parameter names
            const formData = {
                action: 'update',
                status_id: statusId,
                asset_tag: assetTag,
                status: status,
                action_description: actionDescription,
                remarks: $('#edit_remarks').val().trim(),
                is_disabled: $('#edit_is_disabled').is(':checked') ? '1' : '0'
            };
            console.log('Form Data:', formData); // Debug log to verify the checkbox state

            $.ajax({
                url: 'equipment_status.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function (response) {
                    console.log('Update response:', response); // Debug log
                    if (response.status === 'success') {
                        showToast(response.message, 'success');
                        $('#editStatusModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast(response.message || 'An error occurred while updating the status', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Ajax Error:', {
                        xhr: xhr,
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    let errorMessage = 'Server error occurred';
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.message || errorMessage;
                        } catch (e) {
                            console.error('Parse error:', e);
                            errorMessage = 'Invalid server response';
                        }
                    }
                    
                    showToast(errorMessage, 'error');
                },
                complete: function () {
                    submitBtn.prop('disabled', false).html('Update Status');
                }
            });
        });

        // Search and Filter functionality
        $('#searchStatus, #filterStatus, #dateFilter, #monthSelect, #yearSelect, #dateFrom, #dateTo').on('input change', function () {
            filterTable();
        });

        $('#dateFilter').on('change', function () {
            const value = $(this).val();

            $('#dateInputsContainer').hide();
            $('#monthPickerContainer, #dateRangePickers').hide();
            $('#dateFrom, #dateTo').hide();

            switch (value) {
                case 'month':
                    $('#dateInputsContainer').show();
                    $('#monthPickerContainer').show();
                    break;
                case 'range':
                    $('#dateInputsContainer').show();
                    $('#dateRangePickers').show();
                    $('#dateFrom, #dateTo').show();
                    break;
                default:
                    filterTable();
                    break;
            }
        });

        function filterTable() {
            const searchText = $('#searchStatus').val().toLowerCase();
            const filterStatus = $('#filterStatus').val().toLowerCase();
            const filterType = $('#dateFilter').val();
            const selectedMonth = $('#monthSelect').val();
            const selectedYear = $('#yearSelect').val();
            const dateFrom = $('#dateFrom').val();
            const dateTo = $('#dateTo').val();

            $(".table tbody tr").each(function () {
                const row = $(this);
                const rowText = row.text().toLowerCase();
                const statusCell = row.find('td:eq(2)').text().toLowerCase();
                const dateCell = row.find('td:eq(4)').text(); // Created Date column
                const date = new Date(dateCell);

                const searchMatch = rowText.indexOf(searchText) > -1;
                const statusMatch = !filterStatus || statusCell === filterStatus;
                let dateMatch = true;

                switch (filterType) {
                    case 'asc':
                        const tbody = $('.table tbody');
                        const rows = tbody.find('tr').toArray();
                        rows.sort((a, b) => {
                            const dateA = new Date($(a).find('td:eq(4)').text());
                            const dateB = new Date($(b).find('td:eq(4)').text());
                            return dateA - dateB;
                        });
                        tbody.append(rows);
                        return;
                    case 'desc':
                        const tbody2 = $('.table tbody');
                        const rows2 = tbody2.find('tr').toArray();
                        rows2.sort((a, b) => {
                            const dateA = new Date($(a).find('td:eq(4)').text());
                            const dateB = new Date($(b).find('td:eq(4)').text());
                            return dateB - dateA;
                        });
                        tbody2.append(rows2);
                        return;
                    case 'month':
                        if (selectedMonth && selectedYear) {
                            dateMatch = date.getMonth() + 1 === parseInt(selectedMonth) &&
                                date.getFullYear() === parseInt(selectedYear);
                        }
                        break;
                    case 'range':
                        if (dateFrom && dateTo) {
                            const from = new Date(dateFrom);
                            const to = new Date(dateTo);
                            to.setHours(23, 59, 59);
                            dateMatch = date >= from && date <= to;
                        }
                        break;
                }

                row.toggle(searchMatch && statusMatch && dateMatch);
            });
        }

        // Test toast functionality
        window.testToast = function() {
            showToast('Test message', 'success');
        }

        // Add this after your document.ready function
        function testToastSystem() {
            showToast('Testing toast system', 'success');
            setTimeout(() => showToast('Test error message', 'error'), 1000);
            setTimeout(() => showToast('Test warning message', 'warning'), 2000);
            setTimeout(() => showToast('Test info message', 'info'), 3000);
        }

        // You can test it in the console by calling:
        // testToastSystem()

        // Reset the form when the modal is completely closed
        $('#addStatusModal').on('hidden.bs.modal', function () {
            $(this).find('form').trigger('reset');
        });

        $('#editStatusModal').on('hidden.bs.modal', function () {
            $(this).find('form').trigger('reset');
        });
    });
</script>
</body>
</html>
