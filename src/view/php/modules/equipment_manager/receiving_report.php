<?php
// receiving_report.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Include the header
include('../../general/header.php');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'Receiving Report'");
} else {
    $pdo->exec("SET @current_user_id = NULL");
    $pdo->exec("SET @current_module = NULL");
}

// Set IP address; adjust as needed if you use a proxy, etc.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

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

// ------------------------
// DELETE RECEIVING REPORT
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM receive_report WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Receiving Report deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Receiving Report: " . $e->getMessage()];
    }
    header("Location: receiving_report.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form input
    $rr_no = trim($_POST['rr_no'] ?? '');
    $accountable_individual = trim($_POST['accountable_individual'] ?? '');
    $po_no = trim($_POST['po_no'] ?? '');
    $ai_loc = trim($_POST['ai_loc'] ?? '');

    // Validate required fields
    if (empty($rr_no) || empty($accountable_individual) || empty($po_no) || empty($ai_loc)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
        header("Location: receiving_report.php");
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $response = array('status' => '', 'message' => '');
        try {
            $stmt = $pdo->prepare("INSERT INTO receive_report (
                rr_no, 
                accountable_individual, 
                po_no, 
                ai_loc,
                is_disabled
            ) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['rr_no'],
                $_POST['accountable_individual'],
                $_POST['po_no'],
                $_POST['ai_loc'],
                0 // Default to not disabled
            ]);
            $response['status'] = 'success';
            $response['message'] = 'Receiving Report has been added successfully.';
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = 'Error adding Receiving Report: ' . $e->getMessage();
        }
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE receive_report 
                                   SET rr_no = ?, accountable_individual = ?, po_no = ?, ai_loc = ?, is_disabled = ?
                                   WHERE id = ?");
            $stmt->execute([
                $rr_no,
                $accountable_individual,
                $po_no,
                $ai_loc,
                isset($_POST['is_disabled']) ? 1 : 0,
                $id
            ]);
            $_SESSION['success'] = "Receiving Report has been updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error updating Receiving Report: " . $e->getMessage()];
        }
        header("Location: receiving_report.php");
        exit;
    }
}

// ------------------------
// LOAD RECEIVING REPORT DATA FOR EDITING (if applicable)
// ------------------------
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

// ------------------------
// RETRIEVE ALL RECEIVING REPORTS
// ------------------------
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Receiving Report Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            padding-top: 80px;
        }
        h2.mb-4 {
            margin-top: 20px;
        }
        .main-content {
            margin-left: 300px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
<?php include '../../general/sidebar.php'; ?>

<div class="container-fluid" style="margin-left: 320px; padding: 20px; width: calc(100vw - 340px);">
    <!-- Success Message -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php foreach ($errors as $err): ?>
                <p><i class="bi bi-exclamation-triangle"></i> <?php echo $err; ?></p>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Title -->
    <h2 class="mb-4">Receiving Report Management</h2>

    <div class="card shadow">
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
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#addReportModal">
                        <i class="bi bi-plus-circle"></i> Add Receiving Report
                    </button>
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

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm mb-0" id="table">
                    <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>RR Number</th>
                        <th>Accountable Individual</th>
                        <th>PO Number</th>
                        <th>Location</th>
                        <th>Created Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($receivingReports as $rr): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($rr['id']); ?></td>
                            <td><?php echo htmlspecialchars($rr['rr_no']); ?></td>
                            <td><?php echo htmlspecialchars($rr['accountable_individual']); ?></td>
                            <td><?php echo htmlspecialchars($rr['po_no']); ?></td>
                            <td><?php echo htmlspecialchars($rr['ai_loc']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($rr['date_created'])); ?></td>
                            <td><?php echo $rr['is_disabled'] == 1 ? '<span class="badge bg-danger">Disabled</span>' : '<span class="badge bg-success">Active</span>'; ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a class="btn btn-sm btn-outline-primary edit-report"
                                       data-id="<?php echo htmlspecialchars($rr['id']); ?>"
                                       data-rr="<?php echo htmlspecialchars($rr['rr_no']); ?>"
                                       data-individual="<?php echo htmlspecialchars($rr['accountable_individual']); ?>"
                                       data-po="<?php echo htmlspecialchars($rr['po_no']); ?>"
                                       data-location="<?php echo htmlspecialchars($rr['ai_loc']); ?>"
                                       data-disabled="<?php echo htmlspecialchars($rr['is_disabled']); ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <a class="btn btn-sm btn-outline-danger delete-report"
                                       data-id="<?php echo htmlspecialchars($rr['id']); ?>"
                                       href="#">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="container-fluid">
                <div class="row align-items-center g-3">
                    <!-- Pagination Info -->
                    <div class="col-12 col-sm-auto">
                        <div class="text-muted">
                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span id="totalRows">0</span> entries
                        </div>
                    </div>
                    <!-- Pagination Buttons -->
                    <div class="col-12 col-sm-auto ms-sm-auto">
                        <div class="d-flex align-items-center gap-2">
                            <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                <i class="bi bi-chevron-left"></i>
                                Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                Next
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Added missing pagination element -->
            <!-- New Pagination Page Numbers -->
            <div class="row mt-3">
                <div class="col-12">
                    <ul class="pagination justify-content-center" id="pagination"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

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
                        <label for="rr_no" class="form-label">Receiving Report Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="rr_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="accountable_individual" class="form-label">Accountable Individual <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="accountable_individual" required>
                    </div>
                    <div class="mb-3">
                        <label for="po_no" class="form-label">Purchase Order Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="po_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="ai_loc" class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ai_loc" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_disabled" id="is_disabled">
                        <label class="form-check-label" for="is_disabled">Disabled</label>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Add Receiving Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                        <label for="edit_rr_no" class="form-label">Receiving Report Number</label>
                        <input type="text" class="form-control" name="rr_no" id="edit_rr_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_accountable_individual" class="form-label">Accountable Individual</label>
                        <input type="text" class="form-control" name="accountable_individual" id="edit_accountable_individual" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_po_no" class="form-label">Purchase Order Number</label>
                        <input type="text" class="form-control" name="po_no" id="edit_po_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_ai_loc" class="form-label">Location</label>
                        <input type="text" class="form-control" name="ai_loc" id="edit_ai_loc" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_disabled" id="edit_is_disabled">
                        <label class="form-check-label" for="edit_is_disabled">Disabled</label>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for functionality -->
<script>
    $(document).ready(function () {
        // Search functionality
        $('#searchReport').on('input', function () {
            filterTable();
        });

        // Location filter
        $('#filterLocation').on('change', function () {
            filterTable();
        });

        function filterTable() {
            const searchText = $('#searchReport').val().toLowerCase();
            const filterLocation = $('#filterLocation').val().toLowerCase();
            $("#table tbody tr").each(function () {
                const row = $(this);
                const rowText = row.text().toLowerCase();
                const locationCell = row.find('td:nth-child(5)').text().toLowerCase();
                const searchMatch = rowText.indexOf(searchText) > -1;
                const locationMatch = !filterLocation || locationCell === filterLocation;
                row.toggle(searchMatch && locationMatch);
            });
        }

        // Edit Report
        $('.edit-report').click(function () {
            var id = $(this).data('id');
            var rr = $(this).data('rr');
            var individual = $(this).data('individual');
            var po = $(this).data('po');
            var location = $(this).data('location');
            var isDisabled = $(this).data('disabled');

            $('#edit_report_id').val(id);
            $('#edit_rr_no').val(rr);
            $('#edit_accountable_individual').val(individual);
            $('#edit_po_no').val(po);
            $('#edit_ai_loc').val(location);
            $('#edit_is_disabled').prop('checked', isDisabled == 1);

            $('#editReportModal').modal('show');
        });

        // Delete Report
        $('.delete-report').click(function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this report?')) {
                window.location.href = '?action=delete&id=' + $(this).data('id');
            }
        });

        // Add Report form submission
        $('#addReportForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'receiving_report.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            $('#addReportModal').modal('hide');
                            location.reload();
                        } else {
                            alert(result.message || 'An error occurred');
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        location.reload();
                    }
                },
                error: function (xhr, status, error) {
                    alert('Error submitting form: ' + error);
                }
            });
        });
    });
</script>
<!-- Pagination script -->
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
