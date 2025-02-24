<?php
// charge_invoice.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// -----------------------------------------------------------------
// Optionally check for admin privileges (uncomment if needed)
// if (!isset($_SESSION['user_id'])) {
//     header("Location: add_user.php");
//     exit();
// }    
// -----------------------------------------------------------------

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address; adjust as needed if you use a proxy.
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
// DELETE CHARGE INVOICE
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM chargeinvoice WHERE ChargeInvoiceID = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Charge Invoice deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Charge Invoice: " . $e->getMessage()];
    }
    header("Location: charge_invoice.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form input
    $ChargeInvoiceNo    = trim($_POST['ChargeInvoiceNo'] ?? '');
    $DateOfChargeInvoice = trim($_POST['DateOfChargeInvoice'] ?? '');
    $PurchaseOrderNumber = trim($_POST['PurchaseOrderNumber'] ?? '');

    // Validate required fields
    if (empty($ChargeInvoiceNo) || empty($DateOfChargeInvoice) || empty($PurchaseOrderNumber)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
        header("Location: charge_invoice.php");
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO chargeinvoice (ChargeInvoiceNo, DateOfChargeInvoice, PurchaseOrderNumber)
                                   VALUES (?, ?, ?)");
            $stmt->execute([$ChargeInvoiceNo, $DateOfChargeInvoice, $PurchaseOrderNumber]);
            $_SESSION['success'] = "Charge Invoice has been added successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error adding Charge Invoice: " . $e->getMessage()];
        }
        header("Location: charge_invoice.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE chargeinvoice 
                                   SET ChargeInvoiceNo = ?, DateOfChargeInvoice = ?, PurchaseOrderNumber = ?
                                   WHERE ChargeInvoiceID = ?");
            $stmt->execute([$ChargeInvoiceNo, $DateOfChargeInvoice, $PurchaseOrderNumber, $id]);
            $_SESSION['success'] = "Charge Invoice has been updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error updating Charge Invoice: " . $e->getMessage()];
        }
        header("Location: charge_invoice.php");
        exit;
    }
}

// ------------------------
// LOAD CHARGE INVOICE DATA FOR EDITING (if applicable)
// ------------------------
$editChargeInvoice = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM chargeinvoice WHERE ChargeInvoiceID = ?");
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
// RETRIEVE ALL CHARGE INVOICES
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM chargeinvoice ORDER BY ChargeInvoiceID DESC");
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
    <!-- Custom CSS -->
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .main-content {
            width: 100%;
            min-height: 100vh;
        }

        .card {
            margin-bottom: 1rem;
        }

        .form-control {
            font-size: 0.9rem;
        }

        .table {
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            main {
                margin-left: 0 !important;
                max-width: 100% !important;
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

        <!-- Title moved outside the card -->
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
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                        <i class="bi bi-plus-circle"></i> Add Charge Invoice
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Invoice Number</th>
                                <th>Date of Charge Invoice</th>
                                <th>Purchase Order Number</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chargeInvoices as $invoice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($invoice['ChargeInvoiceID']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['ChargeInvoiceNo']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['DateOfChargeInvoice']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['PurchaseOrderNumber']); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-outline-primary edit-invoice" 
                                               data-id="<?php echo htmlspecialchars($invoice['ChargeInvoiceID']); ?>"
                                               data-invoice="<?php echo htmlspecialchars($invoice['ChargeInvoiceNo']); ?>"
                                               data-date="<?php echo htmlspecialchars($invoice['DateOfChargeInvoice']); ?>"
                                               data-po="<?php echo htmlspecialchars($invoice['PurchaseOrderNumber']); ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger delete-invoice" 
                                               data-id="<?php echo htmlspecialchars($invoice['ChargeInvoiceID']); ?>"
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
            </div>
        </div>
    </div>

    <!-- Add Invoice Modal -->
    <div class="modal fade" id="addInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Add New Charge Invoice
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addInvoiceForm" method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="form-field-group">
                            <div class="form-field-group-title">Invoice Information</div>
                            <div class="mb-3">
                                <label for="ChargeInvoiceNo" class="form-label">
                                    <i class="bi bi-tag"></i> Charge Invoice Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="ChargeInvoiceNo" required>
                            </div>
                            <div class="mb-3">
                                <label for="DateOfChargeInvoice" class="form-label">
                                    <i class="bi bi-calendar"></i> Date of Charge Invoice <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" name="DateOfChargeInvoice" required>
                            </div>
                            <div class="mb-3">
                                <label for="PurchaseOrderNumber" class="form-label">
                                    <i class="bi bi-file-text"></i> Purchase Order Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="PurchaseOrderNumber" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle me-2"></i>Add Invoice
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Invoice Modal -->
    <div class="modal fade" id="editInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Edit Charge Invoice
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editInvoiceForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_invoice_id">
                        <div class="form-field-group">
                            <div class="form-field-group-title">Invoice Information</div>
                            <div class="mb-3">
                                <label for="edit_ChargeInvoiceNo" class="form-label">
                                    <i class="bi bi-tag"></i> Charge Invoice Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="ChargeInvoiceNo" id="edit_ChargeInvoiceNo" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_DateOfChargeInvoice" class="form-label">
                                    <i class="bi bi-calendar"></i> Date of Charge Invoice <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" name="DateOfChargeInvoice" id="edit_DateOfChargeInvoice" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_PurchaseOrderNumber" class="form-label">
                                    <i class="bi bi-file-text"></i> Purchase Order Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="PurchaseOrderNumber" id="edit_PurchaseOrderNumber" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-circle me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for functionality -->
    <script>
        $(document).ready(function() {
            // Search functionality
            $('#searchInvoice').on('input', function() {
                var searchText = $(this).val().toLowerCase();
                $(".table tbody tr").each(function() {
                    var rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(searchText) > -1);
                });
            });

            // Edit Invoice
            $('.edit-invoice').click(function() {
                var id = $(this).data('id');
                var invoice = $(this).data('invoice');
                var date = $(this).data('date');
                var po = $(this).data('po');
                
                $('#edit_invoice_id').val(id);
                $('#edit_ChargeInvoiceNo').val(invoice);
                $('#edit_DateOfChargeInvoice').val(date);
                $('#edit_PurchaseOrderNumber').val(po);
                
                $('#editInvoiceModal').modal('show');
            });

            // Delete Invoice
            $('.delete-invoice').click(function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this invoice?')) {
                    window.location.href = '?action=delete&id=' + $(this).data('id');
                }
            });
        });
    </script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
