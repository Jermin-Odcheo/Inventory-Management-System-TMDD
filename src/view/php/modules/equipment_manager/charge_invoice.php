<?php
require_once '../../../../../config/ims-tmdd.php';
session_start();

// start buffering all output (header/sidebar/footer HTML will be captured)
ob_start();

include '../../general/header.php';

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ../../../../../public/index.php');
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

// Set audit-log session vars for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'Charge Invoice'");
} else {
    $pdo->exec("SET @current_user_id = NULL");
    $pdo->exec("SET @current_module = NULL");
}
// Set IP (adjust if behind proxy)
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Flash messages
$errors = $_SESSION['errors']  ?? [];
$success = $_SESSION['success'] ?? '';
unset($_SESSION['errors'], $_SESSION['success']);

// Fetch active POs for dropdown
$stmtPO = $pdo->prepare("
  SELECT po_no
    FROM purchase_order
   WHERE is_disabled = 0
   ORDER BY po_no
");
$stmtPO->execute();
$poList = $stmtPO->fetchAll(PDO::FETCH_COLUMN);

function is_ajax_request()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}
/**
 * Logs an audit entry including Details and Status.
 *
 * @param PDO    $pdo
 * @param string $action    e.g. 'Create', 'Modified', 'Delete'
 * @param mixed  $oldVal    JSON or null
 * @param mixed  $newVal    JSON or null
 * @param int    $entityId  optional
 * @param string $details   human summary (e.g. "Charge Invoice CI123 created")
 * @param string $status    e.g. 'Successful' or 'Failed'
 */
function logAudit($pdo, $action, $oldVal, $newVal, $entityId = null, $details = '', $status = 'Successful')
{
    $stmt = $pdo->prepare("
      INSERT INTO audit_log
        (UserID, EntityID, Module, Action, OldVal, NewVal, Details, Status, Date_Time)
      VALUES (?, ?, 'Charge Invoice', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $entityId,
        $action,
        $oldVal,
        $newVal,
        $details,
        $status
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_no       = trim($_POST['invoice_no']       ?? '');
    $date_of_purchase = trim($_POST['date_of_purchase'] ?? '');
    $po_no            = trim($_POST['po_no']            ?? '');

    // enforce CI prefix
    if ($invoice_no !== '' && strpos($invoice_no, 'CI') !== 0) {
        $invoice_no = 'CI' . $invoice_no;
    }
    // po_no comes straight from the <select> (either "" or "POxxx")

    // 1) Validation
    $fieldError = null;
    if ($invoice_no === '') {
        $fieldError = 'Invoice Number is required.';
    } elseif (!preg_match('/^CI\d+$/', $invoice_no)) {
        $fieldError = 'Invoice Number must be like CI123.';
    } elseif ($po_no !== '' && !in_array($po_no, $poList, true)) {
        $fieldError = 'Invalid PO Number selected.';
    }

    if ($fieldError) {
        $_SESSION['errors'] = [$fieldError];
        if (is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $fieldError]);
            exit;
        }
        header('Location: charge_invoice.php');
        exit;
    }

    // normalize optional fields
    if ($po_no === '') {
        $po_no = null;
    }
    if ($date_of_purchase === '') {
        $date_of_purchase = null;
    }

    // ADD
    if (($_POST['action'] ?? '') === 'add') {
        try {
            if (!$canCreate) {
                throw new Exception('No permission to add invoices.');
            }
            $ins = $pdo->prepare("
          INSERT INTO charge_invoice
            (invoice_no, date_of_purchase, po_no, date_created, is_disabled)
          VALUES (?, ?, ?, NOW(), 0)
        ");
            $ins->execute([$invoice_no, $date_of_purchase, $po_no]);

            $newId = $pdo->lastInsertId();
            logAudit(
                $pdo,
                'Create',
                null,
                json_encode([
                    'invoice_no'       => $invoice_no,
                    'date_of_purchase' => $date_of_purchase,
                    'po_no'            => $po_no
                ]),
                $newId,
                "Charge Invoice {$invoice_no} created",
                'Successful'
            );

            $_SESSION['success'] = "Charge Invoice added.";
        } catch (Exception $e) {
            $_SESSION['errors'] = ["Error adding Charge Invoice: " . $e->getMessage()];
            // optionally: log a Failed audit here as well
        }
    }


    // 4) UPDATE
    if (($_POST['action'] ?? '') === 'update') {
        ob_clean();
        try {
            if (!$canModify) {
                throw new Exception('No permission to modify invoices.');
            }
            $id = (int)$_POST['id'];
            // fetch old
            $sel = $pdo->prepare("SELECT * FROM charge_invoice WHERE id = ?");
            $sel->execute([$id]);
            $old = $sel->fetch(PDO::FETCH_ASSOC);
            if (!$old) throw new Exception('Charge Invoice not found.');

            // run update...
            $upd = $pdo->prepare("
          UPDATE charge_invoice
             SET invoice_no       = ?,
                 date_of_purchase = ?,
                 po_no            = ?
           WHERE id = ? AND is_disabled = 0
        ");
            $upd->execute([$invoice_no, $date_of_purchase, $po_no, $id]);

            if ($upd->rowCount() > 0) {
                logAudit(
                    $pdo,
                    'Modified',
                    json_encode($old),
                    json_encode([
                        'invoice_no'       => $invoice_no,
                        'date_of_purchase' => $date_of_purchase,
                        'po_no'            => $po_no
                    ]),
                    $id,
                    "Charge Invoice {$invoice_no} updated",
                    'Successful'
                );

                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Charge Invoice updated successfully.']);
                exit;
            }
            throw new Exception('No changes made or record not found.');
        } catch (Exception $e) {
            // you could also logAudit(..., 'Failed') here if desired
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }


    // 5) Final AJAX / redirect response
    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        $resp = ['status' => 'success', 'message' => $_SESSION['success'] ?? 'Operation completed successfully'];
        if (!empty($_SESSION['errors'])) {
            $resp = ['status' => 'error', 'message' => $_SESSION['errors'][0]];
        }
        echo json_encode($resp);
        exit;
    }
    header("Location: charge_invoice.php");
    exit;
}

// ------------------------
// SOFT DELETE
// ------------------------
if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        if (!$canDelete) {
            throw new Exception('No permission to delete invoices.');
        }
        // fetch old
        $sel = $pdo->prepare("SELECT * FROM charge_invoice WHERE id = ?");
        $sel->execute([$id]);
        $old = $sel->fetch(PDO::FETCH_ASSOC);

        if ($old) {
            $pdo->prepare("UPDATE charge_invoice SET is_disabled = 1 WHERE id = ?")
                ->execute([$id]);

            logAudit(
                $pdo,
                'Delete',
                json_encode($old),
                null,
                $id,
                "Charge Invoice {$old['invoice_no']} deleted",
                'Successful'
            );

            $_SESSION['success'] = "Charge Invoice deleted successfully.";
        } else {
            $_SESSION['errors'] = ["Charge Invoice not found for deletion."];
        }
    } catch (Exception $e) {
        $_SESSION['errors'] = [$e->getMessage()];
    }
    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        $resp = ['status' => 'success', 'message' => $_SESSION['success'] ?? 'Done'];
        if (!empty($_SESSION['errors'])) {
            $resp = ['status' => 'error', 'message' => $_SESSION['errors'][0]];
        }
        echo json_encode($resp);
        exit;
    }
    header("Location: charge_invoice.php");
    exit;
}

// ------------------------
// LOAD FOR EDIT
// ------------------------
$editChargeInvoice = null;
if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
    try {
        $sel = $pdo->prepare("SELECT * FROM charge_invoice WHERE id = ? AND is_disabled = 0");
        $sel->execute([$_GET['id']]);
        $editChargeInvoice = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$editChargeInvoice) {
            $_SESSION['errors'] = ["Charge Invoice not found for editing."];
            header("Location: charge_invoice.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading for edit: " . $e->getMessage();
    }
}

// ------------------------
// LIST ALL
// ------------------------
try {
    $all = $pdo->query("SELECT * FROM charge_invoice WHERE is_disabled = 0 ORDER BY id DESC");
    $chargeInvoices = $all->fetchAll();
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
        }

        /* Fix for Save Changes button hover state */
        .btn-primary:hover {
            color: #fff !important;
            /* Ensure text stays white on hover */
            background-color: #0b5ed7;
            /* Darker blue on hover */
            border-color: #0a58ca;
        }

        /* Specific styling for the edit form button */
        #editInvoiceForm .btn-primary {
            transition: all 0.2s ease-in-out;
        }

        #editInvoiceForm .btn-primary:hover {
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
    <div class="container-fluid" style="margin-left: 320px; padding: 20px; width: calc(100vw - 340px);">
        <!-- The page now displays notifications only via toast messages -->

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
                    <?php if ($canCreate): ?>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#addInvoiceModal">
                            <i class="bi bi-plus-circle"></i> Add Charge Invoice
                        </button>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
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
                    <table id="invoiceTable" class="table table-hover">
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
                                        <td><?php echo htmlspecialchars($invoice['invoice_no'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['date_of_purchase'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['po_no'] ?? ''); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($invoice['date_created'] ?? '')); ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <?php if ($canModify): ?>
                                                    <a class="btn btn-sm btn-outline-primary edit-invoice"
                                                        data-id="<?php echo htmlspecialchars($invoice['id']); ?>"
                                                        data-invoice="<?php echo htmlspecialchars($invoice['invoice_no'] ?? ''); ?>"
                                                        data-date="<?php echo htmlspecialchars($invoice['date_of_purchase'] ?? ''); ?>"
                                                        data-po="<?php echo htmlspecialchars($invoice['po_no'] ?? ''); ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($canDelete): ?>
                                                    <a class="btn btn-sm btn-outline-danger delete-invoice"
                                                        data-id="<?php echo htmlspecialchars($invoice['id']); ?>"
                                                        href="#">
                                                        <i class="bi bi-trash"></i> Remove
                                                    </a>
                                                <?php endif; ?>
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

    <?php if ($canDelete): ?>
        <!-- Delete Invoice Modal -->
        <div class="modal fade" id="deleteInvoiceModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Invoice Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this charge invoice?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmDeleteInvoiceBtn"
                            class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;"></div>

    <?php if ($canCreate): ?>
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
                                <input type="number" class="form-control" name="invoice_no" min="0" step="1" required pattern="\d*" inputmode="numeric">
                            </div>
                            <div class="mb-3">
                                <label for="date_of_purchase" class="form-label">Date of Purchase <span
                                        class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_of_purchase" required>
                            </div>
                            <div class="mb-3">
                                <label for="po_no" class="form-label">Purchase Order Number</label>
                                <select class="form-select" name="po_no" id="po_no">
                                    <option value="">— None / Select PO —</option>
                                    <?php foreach ($poList as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>">
                                            <?= htmlspecialchars($opt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" style="margin-right: 4px;"
                                    data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Confirm</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canModify): ?>
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
                                <input type="number" class="form-control" name="invoice_no" id="edit_invoice_no" min="0" step="1" required pattern="\d*" inputmode="numeric">
                            </div>
                            <div class="mb-3">
                                <label for="edit_date_of_purchase" class="form-label">Date of Purchase <span
                                        class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_of_purchase"
                                    id="edit_date_of_purchase" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_po_no" class="form-label">Purchase Order Number</label>
                                <select class="form-select" name="po_no" id="edit_po_no">
                                    <option value="">— None / Select PO —</option>
                                    <?php foreach ($poList as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>">
                                            <?= htmlspecialchars($opt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- JavaScript for functionality -->
    <script>
        var deleteInvoiceId = null;

        $(document).ready(function() {
            // Always clean up modal backdrop and body class after modal is hidden
            $('#addInvoiceModal').on('hidden.bs.modal', function() {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
                $('body').css('padding-right', '');
            });
            // Restrict Invoice Number and PO Number fields to numbers only (block e, +, -, . and paste)
            $(document).on('keydown', 'input[name="invoice_no"], #edit_invoice_no, input[name="po_no"], #edit_po_no', function(e) {
                // Allow: backspace, delete, tab, escape, enter, arrows
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
                    // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                    ((e.keyCode == 65 || e.keyCode == 67 || e.keyCode == 86 || e.keyCode == 88) && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: home, end, left, right, down, up
                    (e.keyCode >= 35 && e.keyCode <= 40)) {
                    return;
                }
                // Block: e, +, -, .
                if ([69, 187, 189, 190].includes(e.keyCode)) {
                    e.preventDefault();
                }
                // Ensure only numbers
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            });
            // Block paste of non-numeric
            $(document).on('paste', 'input[name="invoice_no"], #edit_invoice_no, input[name="po_no"], #edit_po_no', function(e) {
                var pasted = (e.originalEvent || e).clipboardData.getData('text');
                if (!/^\d+$/.test(pasted)) {
                    e.preventDefault();
                }
            });
            // Search filter for invoices
            $('#searchInvoice').on('input', function() {
                var searchText = $(this).val().toLowerCase();
                $("#table tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
                });
            });

            // Trigger Edit Invoice Modal using Bootstrap 5 Modal API
            $(document).on('click', '.edit-invoice', function() {
                const id = $(this).data('id');
                const invoice = $(this).data('invoice') || '';
                const date = $(this).data('date') || '';
                const po = $(this).data('po') || '';

                // Fill hidden and inputs
                $('#edit_invoice_id').val(id);
                $('#edit_invoice_no').val(invoice.replace(/^CI/, '')); // strip CI so input=number stays numeric
                $('#edit_date_of_purchase').val(date);
                $('#edit_po_no').val(po); // set the dropdown

                // Show the edit modal
                const modalEl = document.getElementById('editInvoiceModal');
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });


            // Trigger Delete Invoice Modal
            $(document).on('click', '.delete-invoice', function(e) {
                e.preventDefault();
                deleteInvoiceId = $(this).data('id');
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteInvoiceModal'));
                deleteModal.show();
            });

            // Confirm Delete Invoice via AJAX
            $('#confirmDeleteInvoiceBtn').on('click', function() {
                if (deleteInvoiceId) {
                    $.ajax({
                        url: 'charge_invoice.php',
                        method: 'GET',
                        data: {
                            action: 'delete',
                            id: deleteInvoiceId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#invoiceTable').load(location.href + ' #invoiceTable', function() {
                                    showToast(response.message, 'success');
                                });
                            } else {
                                showToast(response.message, 'error');
                            }
                            var deleteModalEl = document.getElementById('deleteInvoiceModal');
                            var deleteModalInstance = bootstrap.Modal.getInstance(deleteModalEl);
                            deleteModalInstance.hide();
                        },
                        error: function() {
                            showToast('Error processing request.', 'error');
                        }
                    });
                }
            });

            // Add Invoice AJAX submission
            $('#addInvoiceForm').on('submit', function(e) {
                let invoiceNo = $(this).find('input[name="invoice_no"]').val();
                let valid = true;
                if (!/^\d+$/.test(invoiceNo)) {
                    showToast('Invoice Number must contain numbers only.', 'error');
                    valid = false;
                }
                if (!valid) {
                    e.preventDefault();
                    return false;
                }
                // Build data with prefixed invoice number
                const formData = $(this).serializeArray();
                let dataObj = {};
                formData.forEach(function(item) {
                    if (item.name === 'invoice_no') {
                        dataObj['invoice_no'] = 'CI' + invoiceNo;
                    } else {
                        dataObj[item.name] = item.value;
                    }
                });
                e.preventDefault();
                $.ajax({
                    url: 'charge_invoice.php',
                    method: 'POST',
                    data: dataObj,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#invoiceTable').load(location.href + ' #invoiceTable', function() {
                                showToast(response.message, 'success');
                            });
                            // Close the modal after successful submission
                            var addModalEl = document.getElementById('addInvoiceModal');
                            var addModal = bootstrap.Modal.getInstance(addModalEl);
                            if (addModal) {
                                addModal.hide();
                            }
                            // Reset form fields to be blank when reopening the modal
                            $('#addInvoiceForm')[0].reset();
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error: function() {
                        showToast('Error processing request.', 'error');
                        // Also remove modal backdrop in case of error
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('overflow', '');
                        $('body').css('padding-right', '');
                    }
                });
            });

            // Edit Invoice AJAX submission
            $('#editInvoiceForm').on('submit', function(e) {
                let invoiceNo = $(this).find('input[name="invoice_no"]').val();
                let valid = true;
                if (!/^\d+$/.test(invoiceNo)) {
                    showToast('Invoice Number must contain numbers only.', 'error');
                    valid = false;
                }
                if (!valid) {
                    e.preventDefault();
                    return false;
                }
                // Build data with prefixed invoice number
                const formData = $(this).serializeArray();
                let dataObj = {};
                formData.forEach(function(item) {
                    if (item.name === 'invoice_no') {
                        dataObj['invoice_no'] = 'CI' + invoiceNo;
                    } else {
                        dataObj[item.name] = item.value;
                    }
                });
                e.preventDefault();
                $.ajax({
                    url: 'charge_invoice.php',
                    method: 'POST',
                    data: dataObj,
                    dataType: 'json',
                    beforeSend: function() {
                        // Optionally add loading state
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#invoiceTable').load(location.href + ' #invoiceTable', function() {
                                showToast(response.message, 'success');
                            });

                            var editModalEl = document.getElementById('editInvoiceModal');
                            var editModal = bootstrap.Modal.getInstance(editModalEl);
                            if (editModal) {
                                editModal.hide();
                            }

                            // Ensure modal backdrop is removed and body class is reset after closing the modal
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css('overflow', '');
                            $('body').css('padding-right', '');
                        } else {
                            showToast(response.message || 'Error updating invoice', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ajax error:', error);
                        console.log(xhr.responseText); // This will help debug the actual server response
                        showToast('Error processing request. Please try again.', 'error');

                        // Remove modal backdrop in case of error
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('overflow', '');
                        $('body').css('padding-right', '');
                    }
                });
            });
        });

        // Date filter functionality
        $('#dateFilter').on('change', function() {
            const filterValue = $(this).val();
            const monthPickerContainer = $('#monthPickerContainer');
            const dateRangePickers = $('#dateRangePickers');
            const dateInputsContainer = $('#dateInputsContainer');

            // Reset and hide all date input containers first
            dateInputsContainer.hide();
            monthPickerContainer.hide();
            dateRangePickers.hide();

            switch (filterValue) {
                case 'desc':
                case 'asc':
                    filterByOrder(filterValue);
                    break;
                case 'month':
                    dateInputsContainer.show();
                    monthPickerContainer.show();
                    break;
                case 'range':
                    dateInputsContainer.show();
                    dateRangePickers.show();
                    break;
                default:
                    // Show all rows when no filter is selected
                    $('#invoiceTable tbody tr').show();
            }
        });

        // Handle month and year selection
        $('#monthSelect, #yearSelect').on('change', function() {
            const month = $('#monthSelect').val();
            const year = $('#yearSelect').val();

            if (month && year) {
                filterByMonth(month, year);
            }
        });

        // Handle date range selection
        $('#dateFrom, #dateTo').on('change', function() {
            const dateFrom = $('#dateFrom').val();
            const dateTo = $('#dateTo').val();

            if (dateFrom && dateTo) {
                filterByDateRange(dateFrom, dateTo);
            }
        });

        function filterByOrder(order) {
            const tbody = $('#invoiceTable tbody');
            const rows = tbody.find('tr').toArray();

            rows.sort((a, b) => {
                const dateA = new Date($(a).find('td:eq(2)').text()); // Index 2 is the date_of_purchase column
                const dateB = new Date($(b).find('td:eq(2)').text());

                return order === 'asc' ? dateA - dateB : dateB - dateA;
            });

            tbody.empty().append(rows);
        }

        function filterByMonth(month, year) {
            $('#invoiceTable tbody tr').each(function() {
                const purchaseDate = new Date($(this).find('td:eq(2)').text());
                const rowMonth = purchaseDate.getMonth() + 1; // getMonth() returns 0-11
                const rowYear = purchaseDate.getFullYear();

                if (rowMonth == month && rowYear == year) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }

        function filterByDateRange(dateFrom, dateTo) {
            const fromDate = new Date(dateFrom);
            const toDate = new Date(dateTo);

            $('#invoiceTable tbody tr').each(function() {
                const purchaseDate = new Date($(this).find('td:eq(2)').text());

                if (purchaseDate >= fromDate && purchaseDate <= toDate) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    </script>

    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js" defer></script>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php include '../../general/footer.php'; ?>
</body>

</html>