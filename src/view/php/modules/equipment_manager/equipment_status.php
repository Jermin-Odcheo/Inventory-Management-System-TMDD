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

// Retrieve any session messages from previous requests
$errors = [];
$success = "";
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
    header('Content-Type: application/json'); // Set JSON content type

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
                    $response = ['status' => 'success', 'message' => 'Equipment Status has been added successfully.'];
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response = ['status' => 'error', 'message' => 'Error adding status: ' . $e->getMessage()];
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
                    $response = ['status' => 'success', 'message' => 'Status updated successfully'];
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response = ['status' => 'error', 'message' => 'Error updating status: ' . $e->getMessage()];
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
                    $pdo->beginTransaction();
                    // Prepare audit log data
                    $oldValue = json_encode([
                        'equipment_status_id' => $statusData['equipment_status_id'],
                        'asset_tag' => $statusData['asset_tag'],
                        'status' => $statusData['status'],
                        'remarks' => $statusData['remarks']
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
                        $statusData['equipment_status_id'],
                        'Equipment Status',
                        'Delete',
                        'Equipment status has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);
                    // Delete record
                    $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE equipment_status_id = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $pdo->commit();
                    $_SESSION['success'] = "Equipment Status deleted successfully.";
                    $response = ['status' => 'success', 'message' => 'Equipment Status deleted successfully.'];
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $_SESSION['errors'] = ["Error deleting status: " . $e->getMessage()];
                    $response = ['status' => 'error', 'message' => 'Error deleting status: ' . $e->getMessage()];
                }
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
        $stmt = $pdo->prepare("SELECT * FROM equipment_status WHERE equipment_status_id = ?");
        $stmt->execute([$id]);
        $statusData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($statusData) {
            $pdo->beginTransaction();
            $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
            $oldValue = json_encode([
                'equipment_status_id' => $statusData['equipment_status_id'],
                'asset_tag' => $statusData['asset_tag'],
                'status' => $statusData['status'],
                'remarks' => $statusData['remarks']
            ]);
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
            $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE equipment_status_id = ?");
            $stmt->execute([$id]);
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
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
<body>
<?php include('../../general/header.php'); ?>
<?php include('../../general/sidebar.php'); ?>
<?php include('../../general/footer.php'); ?>
<div class="main-container">
    <header class="main-header">
        <h1><i class="bi bi-gear-fill"></i> Equipment Status Management</h1>
    </header>
    <section class="card">
        <div class="card-header">
            <h2><i class="bi bi-list-task"></i> List of Equipment Status</h2>
        </div>
        <div class="card-body">
            <div class="container-fluid px-0 mb-3">
                <div class="row align-items-center g-2">
                    <div class="col-auto">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStatusModal">
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

            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM equipment_status ORDER BY date_created DESC");
                $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $statuses = [];
                echo '<div class="alert alert-danger">Error loading equipment status: ' . $e->getMessage() . '</div>';
            }
            ?>

            <div class="table-responsive" id="table">
                <table class="table" id="esTable">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset Tag</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Created Date</th>
                        <th>Remarks</th>
                        <th>Status Flag</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($statuses)): ?>
                        <?php foreach ($statuses as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['equipment_status_id']) ?></td>
                                <td><?= htmlspecialchars($row['asset_tag']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['action']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($row['date_created'])) ?></td>
                                <td><?= htmlspecialchars($row['remarks']) ?></td>
                                <td>
                                    <?php if ($row['is_disabled']): ?>
                                        <span class="badge bg-danger">Disabled</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info edit-status"
                                            data-id="<?= htmlspecialchars($row['equipment_status_id']) ?>"
                                            data-asset="<?= htmlspecialchars($row['asset_tag']) ?>"
                                            data-status="<?= htmlspecialchars($row['status']) ?>"
                                            data-action="<?= htmlspecialchars($row['action']) ?>"
                                            data-remarks="<?= htmlspecialchars($row['remarks']) ?>"
                                            data-disabled="<?= htmlspecialchars($row['is_disabled']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-status"
                                            data-id="<?= htmlspecialchars($row['equipment_status_id']) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No Equipment Status Found.</td>
                        </tr>
                    <?php endif; ?>
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
                        <label for="action" class="form-label">Action</label>
                        <input type="text" class="form-control" name="action" required>
                    </div>
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3"></textarea>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_disabled" name="is_disabled">
                        <label class="form-check-label" for="is_disabled">Disabled</label>
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
                        <input type="text" class="form-control" id="edit_action" name="action" required>
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

<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
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
                success: function (response) {
                    if (response.status === 'success') {
                        $('#addStatusModal').modal('hide');
                        location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function (xhr, status, error) {
                    alert('Error adding status: ' + error);
                },
                complete: function () {
                    submitBtn.prop('disabled', false).html('<i class="bi bi-plus-circle"></i> Add Equipment Status');
                }
            });
        });

        // Delete Status
        $('.delete-status').click(function () {
            var id = $(this).data('id');
            if (confirm('Are you sure you want to delete this status?')) {
                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: {action: 'delete', status_id: id},
                    success: function (response) {
                        try {
                            var result = JSON.parse(response);
                            if (result.status === 'success') {
                                location.reload();
                            } else {
                                alert(result.message);
                            }
                        } catch (e) {
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

        // Filter Table
        $('#searchStatus, #filterStatus').on('input change', function () {
            filterTable();
        });

        function filterTable() {
            const searchText = $('#searchStatus').val().toLowerCase();
            const filterStatus = $('#filterStatus').val().toLowerCase();
            $("#statusTable tbody tr").each(function () {
                const rowText = $(this).text().toLowerCase();
                const statusCell = $(this).find("td:eq(2)").text().toLowerCase();
                const searchMatch = rowText.indexOf(searchText) > -1;
                const statusMatch = !filterStatus || statusCell === filterStatus;
                $(this).toggle(searchMatch && statusMatch);
            });
        }
    });
</script>
</body>
</html>
