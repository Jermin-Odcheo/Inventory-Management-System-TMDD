<?php
require_once '../../../../../config/ims-tmdd.php';
session_start();

// start buffering all output (header/sidebar/footer HTML will be captured)
ob_start();

include '../../general/header.php';

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: index.php');
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Transactions', 'View');

// 3) Button flags
$canCreate = $rbac->hasPrivilege('Equipment Transactions', 'Create');
$canModify = $rbac->hasPrivilege('Equipment Transactions', 'Modify');
$canDelete = $rbac->hasPrivilege('Equipment Transactions', 'Remove');
 
// Set audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'Receiving Report'");
} else {
    $pdo->exec("SET @current_user_id = NULL");
    $pdo->exec("SET @current_module = NULL");
}

// Set client IP address
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Initialize messages
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

function is_ajax_request()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Audit helper
function logAudit($pdo, $action, $oldVal, $newVal, $entityId = null)
{
    $stmt = $pdo->prepare("INSERT INTO audit_log (UserID, EntityID, Module, Action, OldVal, NewVal, Date_Time) VALUES (?, ?, 'Receiving Report', ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $oldVal, $newVal]);
}

// DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        // Check if user has Remove privilege
        if (!$rbac->hasPrivilege('Equipment Transactions', 'Remove')) {
            throw new Exception('You do not have permission to delete receiving reports');
        }
        
        $stmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ?");
        $stmt->execute([$id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($oldData) {
            $stmt = $pdo->prepare("UPDATE receive_report SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Receiving Report deleted successfully.";
            logAudit($pdo, 'delete', json_encode($oldData), null, $id);
        } else {
            $_SESSION['errors'] = ["Receiving Report not found for deletion."];
        }
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Receiving Report: " . $e->getMessage()];
    } catch (Exception $e) {
        $_SESSION['errors'] = [$e->getMessage()];
    }

    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        if (!empty($_SESSION['errors'])) {
            echo json_encode(['status' => 'error', 'message' => $_SESSION['errors'][0]]);
        } else {
            echo json_encode(['status' => 'success', 'message' => $_SESSION['success']]);
        }
        exit;
    }

    header("Location: receiving_report.php");
    exit;
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rr_no = trim($_POST['rr_no'] ?? '');
    $accountable_individual = trim($_POST['accountable_individual'] ?? '');
    $po_no = trim($_POST['po_no'] ?? '');
    $ai_loc = trim($_POST['ai_loc'] ?? '');

    // Enforce RR and PO prefixes BEFORE validation
    if ($rr_no !== '' && strpos($rr_no, 'RR') !== 0) {
        $rr_no = 'RR' . $rr_no;
    }
    if ($po_no !== '' && strpos($po_no, 'PO') !== 0) {
        $po_no = 'PO' . $po_no;
    }
    $is_disabled = 0;
    $date_created = trim($_POST['date_created'] ?? '');
    if (empty($date_created)) {
        $date_created = date('Y-m-d H:i:s');
    } else {
        $date_created = date('Y-m-d H:i:s', strtotime($date_created));
    }

    // Validate required fields and proper RR/PO format
    $fieldError = false;
    if (empty($rr_no) || empty($accountable_individual) || empty($po_no) || empty($ai_loc) || empty($date_created)) {
        $fieldError = 'Please fill in all required fields.';
    } elseif (!preg_match('/^RR\d+$/', $rr_no)) {
        $fieldError = 'RR Number must be in the format RR followed by numbers (e.g., RR123).';
    } elseif (!preg_match('/^PO\d+$/', $po_no)) {
        $fieldError = 'PO Number must be in the format PO followed by numbers (e.g., PO123).';
    }
    if ($fieldError) {
        $_SESSION['errors'] = [$fieldError];
        if (is_ajax_request()) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $_SESSION['errors'][0]]);
            exit;
        }
        header("Location: receiving_report.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $response = ['status' => '', 'message' => ''];
        try {
            // Check if user has Create privilege
            if (!$rbac->hasPrivilege('Equipment Transactions', 'Create')) {
                throw new Exception('You do not have permission to add receiving reports');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO receive_report
                    (rr_no, accountable_individual, po_no, ai_loc, date_created, is_disabled)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$rr_no, $accountable_individual, $po_no, $ai_loc, $date_created, $is_disabled]);
            $_SESSION['success'] = "Receiving Report has been added successfully.";
            $response['status'] = 'success';
            $response['message'] = $_SESSION['success'];
            logAudit($pdo, 'add', null, json_encode([
                'rr_no' => $rr_no,
                'accountable_individual' => $accountable_individual,
                'po_no' => $po_no,
                'ai_loc' => $ai_loc,
                'date_created' => $date_created
            ]));
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = "Error adding Receiving Report: " . $e->getMessage();
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        $response = ['status' => '', 'message' => ''];
        try {
            // Check if user has Modify privilege
            if (!$rbac->hasPrivilege('Equipment Transactions', 'Modify')) {
                throw new Exception('You do not have permission to modify receiving reports');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ?");
            $stmt->execute([$id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($oldData) {
                $stmt = $pdo->prepare("
                    UPDATE receive_report
                    SET rr_no = ?, accountable_individual = ?, po_no = ?, ai_loc = ?, date_created = ?, is_disabled = ?
                    WHERE id = ?
                ");
                $stmt->execute([$rr_no, $accountable_individual, $po_no, $ai_loc, $date_created, $is_disabled, $id]);
                $_SESSION['success'] = "Receiving Report has been updated successfully.";
                $response['status'] = 'success';
                $response['message'] = $_SESSION['success'];
                logAudit(
                    $pdo,
                    'modified',
                    json_encode($oldData),
                    json_encode([
                        'rr_no' => $rr_no,
                        'accountable_individual' => $accountable_individual,
                        'po_no' => $po_no,
                        'ai_loc' => $ai_loc,
                        'date_created' => $date_created
                    ])
                );
            } else {
                $response['status'] = 'error';
                $response['message'] = "Receiving Report not found.";
            }
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = "Error updating Receiving Report: " . $e->getMessage();
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// LOAD for edit
$editReceivingReport = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ?");
        $stmt->execute([$id]);
        $editReceivingReport = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editReceivingReport) {
            $_SESSION['errors'] = ["Receiving Report not found for editing."];
            header("Location: receiving_report.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Receiving Report for editing: " . $e->getMessage();
    }
}

// FETCH ALL
try {
    $stmt = $pdo->query("SELECT * FROM receive_report ORDER BY id DESC");
    $receivingReports = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Receiving Reports: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receiving Report Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 300px;
            /* Adjust if you have a sidebar */
            padding: 20px;
            margin-bottom: 20px;
            margin-top: 70px; /* Ensure content is visible below navbar */
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px; /* Also apply for mobile */
            }
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .btn-primary:hover {
            color: #fff !important;
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        #editReportForm .btn-primary {
            transition: all 0.2s ease-in-out;
        }

        #editReportForm .btn-primary:hover {
            color: #fff !important;
            background-color: #0d6efd;
            border-color: #0d6efd;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <?php include('../../general/sidebar.php'); ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Management Title -->
            <h2 class="mb-4">Receiving Report Management</h2>
            <!-- End Management Title -->
            <div class="card shadow" style="margin-top: 20px;">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul"></i> List of Receiving Reports</span>
                    <div class="input-group w-auto">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchReport" class="form-control" placeholder="Search report...">
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex gap-2">
                            <?php if ($canCreate): ?>
                            <button type="button" class="btn btn-success btn-sm" id="openAddBtn">
                                <i class="bi bi-plus-circle"></i> Add Receiving Report
                            </button>
                            <?php endif; ?>
                            <select class="form-select form-select-sm" id="filterLocation" style="width: auto;">
                                <option value="">Filter Location</option>
                                <?php
                                $locations = array_unique(array_column($receivingReports, 'ai_loc'));
                                foreach ($locations as $location) {
                                    if (!empty($location)) {
                                        echo "<option value='" . htmlspecialchars($location) . "'>" . htmlspecialchars($location) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive" id="table">
                        <table id="rrTable" class="table table-striped table-bordered table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>RR Number</th>
                                    <th>Accountable Individual</th>
                                    <th>PO Number</th>
                                    <th>Location</th>
                                    <th>Created Date</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($receivingReports)): ?>
                                    <?php foreach ($receivingReports as $rr): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rr['id']) ?></td>
                                            <td><?= htmlspecialchars($rr['rr_no']) ?></td>
                                            <td><?= htmlspecialchars($rr['accountable_individual']) ?></td>
                                            <td><?= htmlspecialchars($rr['po_no']) ?></td>
                                            <td><?= htmlspecialchars($rr['ai_loc']) ?></td>
                                            <td><?= date('Y-m-d H:i', strtotime($rr['date_created'])) ?></td>
                                            <td>
                                                <?= $rr['is_disabled'] == 1
                                                    ? '<span class="badge bg-danger">Disabled</span>'
                                                    : '<span class="badge bg-success">Active</span>'
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <?php if ($canModify): ?>
                                                    <button
                                                        class="btn btn-sm btn-outline-primary edit-report"
                                                        data-id="<?= htmlspecialchars($rr['id']) ?>"
                                                        data-rr="<?= htmlspecialchars($rr['rr_no']) ?>"
                                                        data-individual="<?= htmlspecialchars($rr['accountable_individual']) ?>"
                                                        data-po="<?= htmlspecialchars($rr['po_no']) ?>"
                                                        data-location="<?= htmlspecialchars($rr['ai_loc']) ?>"
                                                        data-date_created="<?= htmlspecialchars($rr['date_created']) ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if ($canDelete): ?>
                                                    <button
                                                        class="btn btn-sm btn-outline-danger delete-report"
                                                        data-id="<?= htmlspecialchars($rr['id']) ?>">
                                                        <i class="bi bi-trash"></i> Remove
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">No Receiving Reports found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="container-fluid mt-3">
                        <div class="row align-items-center g-3">
                            <div class="col-auto text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">0</span> of <span id="totalRows">0</span> entries
                            </div>
                            <div class="col-auto ms-auto d-flex gap-2 align-items-center">
                                <button id="prevPage" class="btn btn-outline-primary"><i class="bi bi-chevron-left"></i> Previous</button>
                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button id="nextPage" class="btn btn-outline-primary">Next <i class="bi bi-chevron-right"></i></button>
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
        </div>
    </div>

    <?php if ($canCreate): ?>
    <!-- Add Report Modal -->
    <div class="modal fade" id="addReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Receiving Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addReportForm" method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">RR Number <span class="text-danger">*</span></label>
                            <input type="number" name="rr_no" class="form-control" required min="0" step="1" title="Numbers only" id="add_rr_no">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Accountable Individual <span class="text-danger">*</span></label>
                            <input type="text" name="accountable_individual" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PO Number <span class="text-danger">*</span></label>
                            <input type="number" name="po_no" class="form-control" required min="0" step="1" title="Numbers only" id="add_po_no">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" name="ai_loc" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Created <span class="text-danger">*</span></label>
                            <input
                                type="datetime-local"
                                name="date_created"
                                class="form-control"
                                id="date_created"
                                required
                                value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canModify): ?>
    <!-- Edit Report Modal -->
    <div class="modal fade" id="editReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Receiving Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editReportForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_report_id">
                        <div class="mb-3">
                            <label class="form-label">RR Number <span class="text-danger">*</span></label>
                            <input type="number" name="rr_no" id="edit_rr_no" class="form-control" required min="0" step="1" title="Numbers only">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Accountable Individual <span class="text-danger">*</span></label>
                            <input type="text" name="accountable_individual" id="edit_accountable_individual" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">PO Number <span class="text-danger">*</span></label>
                            <input type="number" name="po_no" id="edit_po_no" class="form-control" required min="0" step="1" title="Numbers only">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <input type="text" name="ai_loc" id="edit_ai_loc" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Created <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="date_created" id="edit_date_created" class="form-control" required>
                        </div>
                        <div class="mb-3 text-end">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteRRModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this Receiving Report?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <script>
        // Instantiate modals
        const addModal = new bootstrap.Modal(document.getElementById('addReportModal'));
        const editModal = new bootstrap.Modal(document.getElementById('editReportModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteRRModal'));

        $(function() {
            // Filter table
            $('#searchReport, #filterLocation').on('input change', function() {
                const searchText = $('#searchReport').val().toLowerCase();
                const filterLoc = $('#filterLocation').val().toLowerCase();
                $('#table tbody tr').each(function() {
                    const row = $(this);
                    const textMatched = row.text().toLowerCase().includes(searchText);
                    const locMatched = !filterLoc || row.find('td:nth-child(5)').text().toLowerCase() === filterLoc;
                    row.toggle(textMatched && locMatched);
                });
            });

            // Open Add modal
            $('#openAddBtn').on('click', () => addModal.show());
            $('#addReportModal').on('hidden.bs.modal', () => $('#addReportForm')[0].reset());

            // Edit button
            $(document).on('click', '.edit-report', function() {
                const btnData = $(this).data();
                $('#edit_report_id').val(btnData.id);
                // Remove RR/PO prefix when showing in modal (for editing)
                let rrVal = btnData.rr ? btnData.rr.replace(/^RR/, '') : '';
                let poVal = btnData.po ? btnData.po.replace(/^PO/, '') : '';
                $('#edit_rr_no').val(rrVal);
                $('#edit_accountable_individual').val(btnData.individual);
                $('#edit_po_no').val(poVal);
                $('#edit_ai_loc').val(btnData.location);
                $('#edit_date_created').val(btnData.date_created.replace(' ', 'T').substring(0, 16));
                editModal.show();
            });

            // Delete button
            let deleteId = null;
            $(document).on('click', '.delete-report', function() {
                deleteId = $(this).data('id');
                deleteModal.show();
            });

            // Confirm delete
            $('#confirmDeleteBtn').on('click', function() {
                if (!deleteId) return;
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'GET',
                    data: {
                        action: 'delete',
                        id: deleteId
                    },
                    dataType: 'json',
                    success(response) {
                        deleteModal.hide();
                        $('#rrTable').load(location.href + ' #rrTable', function() {
                            showToast(response.message, response.status);
                        });
                    },
                    error() {
                        showToast('Error processing request.', 'error');
                    }
                });
            });

            // Add form
            // Client-side validation and prefix for Add form
            $('#addReportForm').on('submit', function(e) {
                let rrNo = $('#add_rr_no').val();
                let poNo = $('#add_po_no').val();
                let valid = true;
                if (!/^\d+$/.test(rrNo)) {
                    showToast('RR Number must contain numbers only.', 'error');
                    valid = false;
                }
                if (!/^\d+$/.test(poNo)) {
                    showToast('PO Number must contain numbers only.', 'error');
                    valid = false;
                }
                if (!valid) {
                    e.preventDefault();
                    return false;
                }
                // Build data with prefixed RR/PO numbers
                const formData = $(this).serializeArray();
                let dataObj = {};
                formData.forEach(function(item) {
                    if (item.name === 'rr_no') {
                        dataObj['rr_no'] = 'RR' + rrNo;
                    } else if (item.name === 'po_no') {
                        dataObj['po_no'] = 'PO' + poNo;
                    } else {
                        dataObj[item.name] = item.value;
                    }
                });

                e.preventDefault();
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'POST',
                    data: dataObj,
                    dataType: 'json',
                    success(response) {
                        if (response.status === 'success') {
                            addModal.hide();
                            $('#rrTable').load(location.href + ' #rrTable', () => {
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error(xhr, status, err) {
                        showToast('Error submitting form: ' + err, 'error');
                    }
                });
            });

            // Edit form
            // Client-side validation and prefix for Edit form
            $('#editReportForm').on('submit', function(e) {
                let rrNo = $('#edit_rr_no').val();
                let poNo = $('#edit_po_no').val();
                let valid = true;
                if (!/^\d+$/.test(rrNo)) {
                    showToast('RR Number must contain numbers only.', 'error');
                    valid = false;
                }
                if (!/^\d+$/.test(poNo)) {
                    showToast('PO Number must contain numbers only.', 'error');
                    valid = false;
                }
                if (!valid) {
                    e.preventDefault();
                    return false;
                }
                // Build data with prefixed RR/PO numbers
                const formData = $(this).serializeArray();
                let dataObj = {};
                formData.forEach(function(item) {
                    if (item.name === 'rr_no') {
                        dataObj['rr_no'] = 'RR' + rrNo;
                    } else if (item.name === 'po_no') {
                        dataObj['po_no'] = 'PO' + poNo;
                    } else {
                        dataObj[item.name] = item.value;
                    }
                });

                e.preventDefault();
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'POST',
                    data: dataObj,
                    dataType: 'json',
                    success(response) {
                        if (response.status === 'success') {
                            editModal.hide();
                            $('#rrTable').load(location.href + ' #rrTable', () => {
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error() {
                        showToast('Error processing request.', 'error');
                    }
                });
            });
        });
        // Block non-numeric input for RR and PO fields
        function blockNonNumericInput(selector) {
            $(document).on('keydown', selector, function(e) {
                // Allow: backspace, delete, tab, escape, enter, arrows, home, end
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
                    // Allow: Ctrl/cmd+A
                    (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: Ctrl/cmd+C
                    (e.keyCode === 67 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: Ctrl/cmd+X
                    (e.keyCode === 88 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: home, end, left, right
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                        return;
                }
                // Ensure that it is a number and stop the keypress
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            });
        }
        blockNonNumericInput('#add_rr_no');
        blockNonNumericInput('#add_po_no');
        blockNonNumericInput('#edit_rr_no');
        blockNonNumericInput('#edit_po_no');
    </script>

    <script src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js" defer></script>
    <?php include('../../general/footer.php'); ?>
</body>

</html>