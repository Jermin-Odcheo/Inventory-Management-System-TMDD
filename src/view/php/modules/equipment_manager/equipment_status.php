<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Include the header
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

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json'); // Ensure JSON content type is set

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $pdo->beginTransaction();

                    // Insert equipment status
                    $stmt = $pdo->prepare("INSERT INTO equipment_status (
                        asset_tag, 
                        status, 
                        action,
                        remarks, 
                        date_created,
                        is_disabled
                    ) VALUES (?, ?, ?, ?, NOW(), ?)");
                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['status'],
                        $_POST['action'],
                        $_POST['remarks'],
                        isset($_POST['is_disabled']) ? 1 : 0
                    ]);

                    $newStatusId = $pdo->lastInsertId();

                    // Prepare audit log data
                    $newValues = json_encode([
                        'asset_tag' => $_POST['asset_tag'],
                        'status' => $_POST['status'],
                        'action' => $_POST['action'],
                        'remarks' => $_POST['remarks'],
                        'is_disabled' => isset($_POST['is_disabled']) ? 1 : 0
                    ]);

                    // Insert audit log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $newStatusId,
                        'Equipment Status',
                        'Add',
                        'New equipment status added',
                        null,
                        $newValues,
                        'Successful'
                    ]);

                    $pdo->commit();

                    $response['status'] = 'success';
                    $response['message'] = 'Equipment Status has been added successfully.';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response['status'] = 'error';
                    $response['message'] = 'Error adding status: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit;
                break;

            case 'update':
                try {
                    $pdo->beginTransaction();

                    // Get old status details for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $oldStatus = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Update equipment status
                    $stmt = $pdo->prepare("UPDATE equipment_status SET 
                        asset_tag = ?, 
                        status = ?, 
                        action = ?,
                        remarks = ?,
                        is_disabled = ?
                        WHERE equipment_status_id = ?");

                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['status'],
                        $_POST['action'],
                        $_POST['remarks'],
                        isset($_POST['is_disabled']) ? 1 : 0,
                        $_POST['status_id']
                    ]);

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
                        'action' => $_POST['action'],
                        'remarks' => $_POST['remarks'],
                        'date_created' => $oldStatus['date_created'],
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
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $_POST['status_id'],
                        'Equipment Status',
                        'Modified',
                        'Equipment status modified',
                        $oldValues,
                        $newValues,
                        'Successful'
                    ]);

                    $pdo->commit();
                    $response['status'] = 'success';
                    $response['message'] = 'Status updated successfully';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response['status'] = 'error';
                    $response['message'] = 'Error updating status: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit;
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
        }
        .d-flex.gap-2 {
            gap: 0.5rem !important;
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
                    <div class="form-field-group">
                        <div class="form-field-group-title">Status Information</div>
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="asset_tag" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <div class="input-group">
                                <select class="form-control" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="Working">Working</option>
                                    <option value="For Repair">For Repair</option>
                                    <option value="For Disposal">For Disposal</option>
                                    <option value="Disposed">Disposed</option>
                                </select>
                            </div>
                        </div>
                        <!-- Renamed the field from 'accountable_individual' to 'action' -->
                        <div class="mb-3">
                            <label for="action" class="form-label">Action</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="action" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <div class="input-group">
                                <textarea class="form-control" name="remarks" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Equipment Status</button>
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
                <h5 class="modal-title">Edit Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="javascript:void(0);" id="edit_status_form">
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
                            <option value="Needs Repair">Needs Repair</option>
                            <option value="Out of Service">Out of Service</option>
                            <option value="In Maintenance">In Maintenance</option>
                            <option value="Decommissioned">Decommissioned</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_action" class="form-label">Action</label>
                        <input type="text" class="form-control" name="action" id="edit_action" required>
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

            $.ajax({
                url: 'equipment_status.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function (response) {
                    if (response.status === 'success') {
                        $('#addStatusModal').modal('hide');
                        $('#table').load(location.href + ' #table', function() {
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error adding status: ' + error, 'error');
                },
                complete: function () {
                    submitBtn.prop('disabled', false).html('Add Equipment Status');
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
            $.ajax({
                url: 'equipment_status.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    location.reload();
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
    });
</script>
</body>
</html>
