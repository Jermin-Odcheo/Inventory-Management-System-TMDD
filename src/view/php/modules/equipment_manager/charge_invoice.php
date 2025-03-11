<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Include the header (this should load common assets)
include('../../general/header.php');

// Set audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'Charge Invoice'");
} else {
    $pdo->exec("SET @current_user_id = NULL");
    $pdo->exec("SET @current_module = NULL");
}

// Set IP address (adjust if using a proxy)
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
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no = trim($_POST['invoice_no'] ?? '');
    $date_of_purchase = trim($_POST['date_of_purchase'] ?? '');
    $po_no = trim($_POST['po_no'] ?? '');

    if (empty($invoice_no) || empty($date_of_purchase) || empty($po_no)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
        if (is_ajax_request()) {
            echo json_encode(['status' => 'error', 'message' => $_SESSION['errors'][0]]);
            exit;
        }
        header("Location: charge_invoice.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        // Validate that the provided PO number exists in the purchase_order table
        $stmt = $pdo->prepare("SELECT po_no FROM purchase_order WHERE po_no = ?");
        $stmt->execute([$po_no]);
        $existingPO = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingPO) {
            $_SESSION['errors'] = ["Invalid Purchase Order Number. Please ensure the Purchase Order exists."];
            if (is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => $_SESSION['errors'][0]]);
                exit;
            }
            header("Location: charge_invoice.php");
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO charge_invoice (invoice_no, date_of_purchase, po_no, date_created, is_disabled)
                               VALUES (?, ?, ?, NOW(), 0)");
            $stmt->execute([$invoice_no, $date_of_purchase, $po_no]);
            $_SESSION['success'] = "Charge Invoice has been added successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error adding Charge Invoice: " . $e->getMessage()];
        }
    }

    // If this is an AJAX request, return a JSON response.
    if (is_ajax_request()) {
        $response = ['status' => 'success', 'message' => $_SESSION['success'] ?? 'Operation completed successfully'];
        if (!empty($_SESSION['errors'])) {
            $response = ['status' => 'error', 'message' => $_SESSION['errors'][0]];
        }
        echo json_encode($response);
        exit;
    }
    header("Location: charge_invoice.php");
    exit;
}

// ------------------------
// DELETE CHARGE INVOICE (soft delete)
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("UPDATE charge_invoice SET is_disabled = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Charge Invoice deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Charge Invoice: " . $e->getMessage()];
    }
    header("Location: charge_invoice.php");
    exit;
}

// ------------------------
// LOAD CHARGE INVOICE DATA FOR EDITING (if applicable)
// ------------------------
$editChargeInvoice = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM charge_invoice WHERE id = ? AND is_disabled = 0");
        $stmt->execute([$id]);
        $editChargeInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editChargeInvoice) {
            $_SESSION['errors'] = ["Charge Invoice not found for editing."];
            header("Location: charge_invoice.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Charge Invoice for editing: " . $e->getMessage();
    }
}

// ------------------------
// RETRIEVE ALL CHARGE INVOICES (active only)
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM charge_invoice WHERE is_disabled = 0 ORDER BY id DESC");
    $chargeInvoices = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Charge Invoices: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Charge Invoice Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Styles (if any) -->
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
<?php include('../../general/sidebar.php'); ?>

<div class="container-fluid" style="margin-left: 320px; padding: 20px; width: calc(100vw - 340px);">
    <!-- Display Success Message -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Display Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php foreach ($errors as $err): ?>
                <p><i class="bi bi-exclamation-triangle"></i> <?php echo $err; ?></p>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <h2 class="mb-4">Charge Invoice Management</h2>

    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">

            <span><i class="bi bi-list-ul"></i> List of Charge Invoices</span>
            <div class="input-group w-auto">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="searchInvoice" class="form-control" placeholder="Search invoice...">
            </div>
        </div>
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addInvoiceModal">
                    <i class="bi bi-plus-circle"></i> Add Charge Invoice
                </button>
                <!-- Optionally add date filters -->
                <select class="form-select form-select-sm" id="dateFilter" style="width: auto;">
                    <option value="">Filter by Date</option>
                    <option value="desc">Newest to Oldest</option>
                    <option value="asc">Oldest to Newest</option>
                    <option value="month">Specific Month</option>
                    <option value="range">Custom Date Range</option>
                </select>
                <div id="dateInputsContainer" style="display: none;">
                    <div class="d-flex gap-2" id="monthPickerContainer" style="display: none;">
                        <select class="form-select form-select-sm" id="monthSelect" style="min-width: 130px;">
                            <option value="">Select Month</option>
                            <?php
                            $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
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
                    <div class="d-flex gap-2" id="dateRangePickers" style="display: none;">
                        <input type="date" class="form-control form-control-sm" id="dateFrom" placeholder="From">
                        <input type="date" class="form-control form-control-sm" id="dateTo" placeholder="To">
                    </div>
                </div>
            </div>

            <div class="table-responsive" id="table">
                <table class="table table-hover">
                    <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Invoice Number</th>
                        <th>Purchase Date</th>
                        <th>PO Number</th>
                        <th>Created Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($chargeInvoices)): ?>
                        <?php foreach ($chargeInvoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['id']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['date_of_purchase']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['po_no']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($invoice['date_created'])); ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a class="btn btn-sm btn-outline-primary edit-invoice"
                                           data-id="<?php echo htmlspecialchars($invoice['id']); ?>"
                                           data-invoice="<?php echo htmlspecialchars($invoice['invoice_no']); ?>"
                                           data-date="<?php echo htmlspecialchars($invoice['date_of_purchase']); ?>"
                                           data-po="<?php echo htmlspecialchars($invoice['po_no']); ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a class="btn btn-sm btn-outline-danger delete-invoice"
                                           data-id="<?php echo htmlspecialchars($invoice['id']); ?>"
                                           href="#">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No Charge Invoices found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <!-- Pagination Controls (optional) -->
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                        id="totalRows">100</span> entries
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
                    </div>
                    <!-- Pagination numbers (if using pagination.js) -->
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

<!-- Add Invoice Modal -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Charge Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addInvoiceForm" method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="invoice_no" class="form-label">Invoice Number <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="invoice_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_of_purchase" class="form-label">Date of Purchase <span
                                    class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_of_purchase" required>
                    </div>
                    <div class="mb-3">
                        <label for="po_no" class="form-label">Purchase Order Number <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="po_no" required>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Add Charge Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Edit Invoice Modal -->

<div class="modal fade" id="editInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Charge Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editInvoiceForm" method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_invoice_id">
                    <div class="mb-3">
                        <label for="edit_invoice_no" class="form-label">Invoice Number <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="invoice_no" id="edit_invoice_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_date_of_purchase" class="form-label">Date of Purchase <span
                                    class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_of_purchase" id="edit_date_of_purchase"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_po_no" class="form-label">Purchase Order Number <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="po_no" id="edit_po_no" required>
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
        // Search filter
        $('#searchInvoice').on('input', function () {
            var searchText = $(this).val().toLowerCase();
            $("#table tbody tr").filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
            });
        });

        // Trigger Edit Invoice Modal
        $('.edit-invoice').click(function () {
            var id = $(this).data('id');
            var invoice = $(this).data('invoice');
            var date = $(this).data('date');
            var po = $(this).data('po');
            $('#edit_invoice_id').val(id);
            $('#edit_invoice_no').val(invoice);
            $('#edit_date_of_purchase').val(date);
            $('#edit_po_no').val(po);
            $('#editInvoiceModal').modal('show');
        });

        // Delete Invoice confirmation
        $('.delete-invoice').click(function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (confirm('Are you sure you want to delete this invoice?')) {
                window.location.href = '?action=delete&id=' + id;
            }
        });

        // Add Invoice AJAX submission
        $('#addInvoiceForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'charge_invoice.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.status === 'success') {
                            $('#addInvoiceModal').modal('hide');
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

        // Edit Invoice AJAX submission
        $('#editInvoiceForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'charge_invoice.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.status === 'success') {
                            $('#editInvoiceModal').modal('hide');
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
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Optional: Include your pagination.js if required -->
<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js"
        defer></script>
</body>
</html>
