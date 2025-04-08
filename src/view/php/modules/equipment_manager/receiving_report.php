<?php
session_start();
ob_start(); // Start buffering to ensure clean JSON responses
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Include the header (loads common assets)
include('../../general/header.php');

// Set audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'Receiving Report'");
} else {
    $pdo->exec("SET @current_user_id = NULL");
    $pdo->exec("SET @current_module = NULL");
}

// Set client IP address (adjust if using a proxy)
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
    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        $response = ['status' => 'success', 'message' => $_SESSION['success'] ?? 'Operation completed successfully'];
        if (!empty($_SESSION['errors'])) {
            $response = ['status' => 'error', 'message' => $_SESSION['errors'][0]];
        }
        echo json_encode($response);
        exit;
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

    // Always set is_disabled to 0 (active)
    $is_disabled = 0;

    // Retrieve the date_created value (from a datetime-local input)
    $date_created = trim($_POST['date_created'] ?? '');
    if (empty($date_created)) {
        $date_created = date('Y-m-d H:i:s');
    } else {
        $date_created = date('Y-m-d H:i:s', strtotime($date_created));
    }

    // Validate required fields
    if (empty($rr_no) || empty($accountable_individual) || empty($po_no) || empty($ai_loc) || empty($date_created)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
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
            $stmt = $pdo->prepare("INSERT INTO receive_report (rr_no, accountable_individual, po_no, ai_loc, date_created, is_disabled)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$rr_no, $accountable_individual, $po_no, $ai_loc, $date_created, $is_disabled]);
            $_SESSION['success'] = "Receiving Report has been added successfully.";
            $response['status'] = 'success';
            $response['message'] = $_SESSION['success'];
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = "Error adding Receiving Report: " . $e->getMessage();
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        $response = ['status' => '', 'message' => ''];
        try {
            $stmt = $pdo->prepare("UPDATE receive_report 
                                   SET rr_no = ?, accountable_individual = ?, po_no = ?, ai_loc = ?, date_created = ?, is_disabled = ?
                                   WHERE id = ?");
            $stmt->execute([$rr_no, $accountable_individual, $po_no, $ai_loc, $date_created, $is_disabled, $id]);
            $_SESSION['success'] = "Receiving Report has been updated successfully.";
            $response['status'] = 'success';
            $response['message'] = $_SESSION['success'];
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = "Error updating Receiving Report: " . $e->getMessage();
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
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
    <title>Receiving Report Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 300px; /* Adjust if you have a sidebar */
            padding: 20px;
            margin-bottom: 20px;
            width: auto;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        /* Ensure table responsiveness */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        
        /* Fix for Save Changes button hover state */
        .btn-primary:hover {
            color: #fff !important; /* Ensure text stays white on hover */
            background-color: #0b5ed7; /* Darker blue on hover */
            border-color: #0a58ca;
        }
        
        /* Specific styling for the edit form button */
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
                                    <td><?php echo htmlspecialchars($rr['id']); ?></td>
                                    <td><?php echo htmlspecialchars($rr['rr_no']); ?></td>
                                    <td><?php echo htmlspecialchars($rr['accountable_individual']); ?></td>
                                    <td><?php echo htmlspecialchars($rr['po_no']); ?></td>
                                    <td><?php echo htmlspecialchars($rr['ai_loc']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($rr['date_created'])); ?></td>
                                    <td>
                                        <?php echo $rr['is_disabled'] == 1
                                            ? '<span class="badge bg-danger">Disabled</span>'
                                            : '<span class="badge bg-success">Active</span>';
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-outline-primary edit-report"
                                               data-id="<?php echo htmlspecialchars($rr['id']); ?>"
                                               data-rr="<?php echo htmlspecialchars($rr['rr_no']); ?>"
                                               data-individual="<?php echo htmlspecialchars($rr['accountable_individual']); ?>"
                                               data-po="<?php echo htmlspecialchars($rr['po_no']); ?>"
                                               data-location="<?php echo htmlspecialchars($rr['ai_loc']); ?>"
                                               data-date_created="<?php echo htmlspecialchars($rr['date_created']); ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger delete-report"
                                               data-id="<?php echo htmlspecialchars($rr['id']); ?>" href="#">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
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
                <!-- Pagination Controls (if needed) -->
                <div class="container-fluid mt-3">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">0</span> of <span
                                        id="totalRows">0</span> entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
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
        </div>
    </div>
</div>

<!-- Add Report Modal (without disabled field) -->
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
                        <label for="accountable_individual" class="form-label">Accountable Individual <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="accountable_individual" required>
                    </div>
                    <div class="mb-3">
                        <label for="po_no" class="form-label">Purchase Order Number <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="po_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="ai_loc" class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ai_loc" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_created" class="form-label">Date Created <span
                                    class="text-danger">*</span></label>
                        <!-- Pre-fill with current date/time in the correct format for datetime-local -->
                        <input type="datetime-local" class="form-control" name="date_created" id="date_created" required
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Add Receiving Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Report Modal (without disabled field) -->
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
                        <label for="edit_rr_no" class="form-label">Receiving Report Number <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="rr_no" id="edit_rr_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_accountable_individual" class="form-label">Accountable Individual <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="accountable_individual"
                               id="edit_accountable_individual" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_po_no" class="form-label">Purchase Order Number <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="po_no" id="edit_po_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_ai_loc" class="form-label">Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ai_loc" id="edit_ai_loc" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_date_created" class="form-label">Date Created <span
                                    class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="date_created" id="edit_date_created"
                               required>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Delete Recieving Report Order Modal -->
<div class="modal fade" id="deleteRRModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this Receiving Report order?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for functionality -->
<script>
    $(document).ready(function () {
        // Filter table by search text and location
        $('#searchReport, #filterLocation').on('input change', function () {
            const searchText = $('#searchReport').val().toLowerCase();
            const filterLocation = $('#filterLocation').val().toLowerCase();
            $("#table tbody tr").each(function () {
                const row = $(this);
                const rowText = row.text().toLowerCase();
                // Assuming the Location is in the 5th column
                const locationCell = row.find('td:nth-child(5)').text().toLowerCase();
                const searchMatch = rowText.indexOf(searchText) > -1;
                const locationMatch = !filterLocation || locationCell === filterLocation;
                row.toggle(searchMatch && locationMatch);
            });
        });

        // Open Edit Report Modal and populate fields
        $(document).on('click', '.edit-report', function () {
            var id = $(this).data('id');
            var rr = $(this).data('rr');
            var individual = $(this).data('individual');
            var po = $(this).data('po');
            var location = $(this).data('location');
            var dateCreated = $(this).data('date_created');

            $('#edit_report_id').val(id);
            $('#edit_rr_no').val(rr);
            $('#edit_accountable_individual').val(individual);
            $('#edit_po_no').val(po);
            $('#edit_ai_loc').val(location);
            // Convert dateCreated from "YYYY-MM-DD HH:MM:SS" to "YYYY-MM-DDTHH:MM" for datetime-local input
            var formattedDate = dateCreated.replace(' ', 'T').substring(0, 16);
            $('#edit_date_created').val(formattedDate);

            $('#editReportModal').modal('show');
        });



        // Global variable to store the ID for deletion
        var deleteId = null;

    // When a delete-report link is clicked, show the delete modal
        $(document).on('click', '.delete-report', function (e) {
            e.preventDefault();
            deleteId = $(this).data('id');
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteRRModal'));
            deleteModal.show();
        });

    // When the confirm delete button is clicked, process deletion via AJAX
        $('#confirmDeleteBtn').on('click', function () {
            if (deleteId) {
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'GET',
                    data: { action: 'delete', id: deleteId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            $('#rrTable').load(location.href + ' #rrTable', function () {
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                        // Hide the modal after processing
                        var deleteModalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteRRModal'));
                        deleteModalInstance.hide();
                    },
                    error: function () {
                        showToast('Error processing request.', 'error');
                    }
                });
            }
        });


        // AJAX submission for Add Report form using toast notifications
        $('#addReportForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'receiving_report.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json', // ensures jQuery parses the JSON response automatically
                success: function (response) {
                    if (response.status === 'success') {
                        $('#addReportModal').modal('hide');
                        $('#rrTable').load(location.href + ' #rrTable', function () {
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message || 'An error occurred', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error submitting form: ' + error, 'error');
                }
            });
        });


        // AJAX submission for Edit Report form using toast notifications
        $('#editReportForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'receiving_report.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        $('#editReportModal').modal('hide');
                        $('#rrTable').load(location.href + ' #rrTable', function () {
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function () {
                    showToast('Error processing request.', 'error');
                }
            });
        });
    });

    $('#addReportModal').on('hidden.bs.modal', function () {
        $('#addReportForm')[0].reset();
    });

</script>

<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js"
        defer></script>

<?php include '../../general/footer.php'; ?>
</body>
</html>
