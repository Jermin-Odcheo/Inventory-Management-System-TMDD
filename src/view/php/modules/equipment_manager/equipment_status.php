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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Status Management</title>

    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
</head>

<body>
    <?php
    include '../../general/header.php';
    include '../../general/sidebar.php';
    include '../../general/footer.php';
    ?>

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
                    <div class="row align-items-center g-2">
                        <div class="col-auto">
                            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                                <i class="bi bi-plus-lg"></i> Add New Status
                            </button>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="filterStatus">
                                <option value="">Filter by Status</option>
                                <option value="Working">Working</option>
                                <option value="For Repair">For Repair</option>
                                <option value="For Disposal">For Disposal</option>
                                <option value="Disposed">Disposed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" id="searchStatus" class="form-control" placeholder="Search status...">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                            </div>
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
                                <th>Action</th>
                                <th>Created Date</th>
                                <th>Remarks</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                    echo "<td>" . date('Y-m-d H:i', strtotime($row['date_created'])) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['remarks']) . "</td>";
                                    echo "<td>" . ($row['is_disabled'] ? '<span class=\"badge bg-danger\">Disabled</span>' : '<span class=\"badge bg-success\">Active</span>') . "</td>";
                                    echo "<td>
                      <div class='d-flex justify-content-center gap-2'>
                        <button class='btn btn-sm btn-outline-info edit-status' 
                                data-id='" . htmlspecialchars($row['equipment_status_id']) . "'
                                data-asset='" . htmlspecialchars($row['asset_tag']) . "'
                                data-status='" . htmlspecialchars($row['status']) . "'
                                data-action='" . htmlspecialchars($row['action']) . "'
                                data-remarks='" . htmlspecialchars($row['remarks']) . "'
                                data-disabled='" . htmlspecialchars($row['is_disabled']) . "'>
                          <i class='bi bi-pencil'></i>
                        </button>
                        <button class='btn btn-sm btn-outline-danger delete-status' 
                                data-id='" . htmlspecialchars($row['equipment_status_id']) . "'>
                          <i class='bi bi-trash'></i>
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
                <!-- Pagination Controls -->
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                    id="totalRows">100</span>
                                entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>
                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
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
                            <input type="text" class="form-control" name="asset_tag">
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">Select Status</option>
                                <option value="Working">Working</option>
                                <option value="For Repair">For Repair</option>
                                <option value="For Disposal">For Disposal</option>
                                <option value="Disposed">Disposed</option>
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
                                <option value="Working">Working</option>
                                <option value="For Repair">For Repair</option>
                                <option value="For Disposal">For Disposal</option>
                                <option value="Disposed">Disposed</option>
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
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_disabled" name="is_disabled">
                            <label class="form-check-label" for="edit_is_disabled">Disabled</label>
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

    <script src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script>
        $(document).ready(function() {
            // Real-time search & filter
            $('#searchStatus, #filterStatus').on('input change', function() {
                filterTable();
            });

            function filterTable() {
                const searchText = $('#searchStatus').val().toLowerCase();
                const filterStatus = $('#filterStatus').val().toLowerCase();

                $(".table tbody tr").each(function() {
                    const row = $(this);
                    const rowText = row.text().toLowerCase();
                    const statusCell = row.find('td:eq(2)').text().toLowerCase();

                    const searchMatch = rowText.indexOf(searchText) > -1;
                    const statusMatch = !filterStatus || statusCell === filterStatus;

                    row.toggle(searchMatch && statusMatch);
                });
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
                            $('.modal-backdrop').remove();
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
</body>

</html>