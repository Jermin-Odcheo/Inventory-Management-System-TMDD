<?php
// charge_invoice.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Include the header
include('../../general/header.php');

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
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <!-- Add jQuery -->
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
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                            <i class="bi bi-plus-circle"></i> Add Charge Invoice
                        </button>
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

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0" id="table">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Invoice Number</th>
                                <th>Invoice Date</th>
                                <th>Created Date</th>
                                <th>Modified Date</th>
                                <th>PO Number</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chargeInvoices as $invoice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($invoice['ChargeInvoiceID']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['ChargeInvoiceNo']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['DateOfChargeInvoice']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($invoice['CreatedDate'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($invoice['ModifiedDate'])); ?></td>
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
                <!-- Pagination Controls -->
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <!-- Pagination Info -->
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span
                                        id="totalRows">0</span> entries
                            </div>
                        </div>

                        <!-- Pagination Controls -->
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
            </div>
        </div>
    </div>

    <!-- Add Invoice Modal -->
    <div class="modal fade" id="addInvoiceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Charge Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addInvoiceForm" method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="ChargeInvoiceNo" class="form-label">Charge Invoice Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ChargeInvoiceNo" required>
                        </div>
                        <div class="mb-3">
                            <label for="DateOfChargeInvoice" class="form-label">Date of Charge Invoice <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="DateOfChargeInvoice" required>
                        </div>
                        <div class="mb-3">
                            <label for="PurchaseOrderNumber" class="form-label">Purchase Order Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="PurchaseOrderNumber" required>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editInvoiceForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_invoice_id">
                        <div class="mb-3">
                            <label for="edit_ChargeInvoiceNo" class="form-label">Charge Invoice Number</label>
                            <input type="text" class="form-control" name="ChargeInvoiceNo" id="edit_ChargeInvoiceNo" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_DateOfChargeInvoice" class="form-label">Date of Charge Invoice</label>
                            <input type="date" class="form-control" name="DateOfChargeInvoice" id="edit_DateOfChargeInvoice" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_PurchaseOrderNumber" class="form-label">Purchase Order Number</label>
                            <input type="text" class="form-control" name="PurchaseOrderNumber" id="edit_PurchaseOrderNumber" required>
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
        $(document).ready(function() {
            // Search functionality
            $('#searchInvoice').on('input', function() {
                filterTable();
            });

            // Date filter change handler
            $('#dateFilter').on('change', function() {
                const value = $(this).val();
                
                // Hide all date inputs container first
                $('#dateInputsContainer').hide();
                $('#monthPickerContainer, #dateRangePickers').hide();
                $('#dateFrom, #dateTo').hide();
                
                switch(value) {
                    case 'month':
                        $('#dateInputsContainer').show();
                        $('#monthPickerContainer').show();
                        $('#dateRangePickers').hide();
                        break;
                    case 'range':
                        $('#dateInputsContainer').show();
                        $('#dateRangePickers').show();
                        $('#monthPickerContainer').hide();
                        $('#dateFrom, #dateTo').show();
                        break;
                    default:
                        filterTable();
                        break;
                }
            });

            // Month and Year select change handler
            $('#monthSelect, #yearSelect').on('change', function() {
                if ($('#monthSelect').val() && $('#yearSelect').val()) {
                    filterTable();
                }
            });

            // Update the filterTable function
            function filterTable() {
                const searchText = $('#searchInvoice').val().toLowerCase();
                const filterType = $('#dateFilter').val();
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                $(".table tbody tr").each(function() {
                    const row = $(this);
                    const rowText = row.text().toLowerCase();
                    const dateCell = row.find('td:eq(2)').text(); // Date is in the 3rd column
                    const date = new Date(dateCell);
                    
                    const searchMatch = rowText.indexOf(searchText) > -1;
                    let dateMatch = true;
                    
                    switch(filterType) {
                        case 'asc':
                            const tbody = $('.table tbody');
                            const rows = tbody.find('tr').toArray();
                            rows.sort((a, b) => {
                                const dateA = new Date($(a).find('td:eq(2)').text());
                                const dateB = new Date($(b).find('td:eq(2)').text());
                                return dateA - dateB;
                            });
                            tbody.append(rows);
                            return;
                            
                        case 'desc':
                            const tbody2 = $('.table tbody');
                            const rows2 = tbody2.find('tr').toArray();
                            rows2.sort((a, b) => {
                                const dateA = new Date($(a).find('td:eq(2)').text());
                                const dateB = new Date($(b).find('td:eq(2)').text());
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

                    row.toggle(searchMatch && dateMatch);
                });
            }

            // Add date range picker change handlers
            $('#dateFrom, #dateTo').on('change', function() {
                if ($('#dateFrom').val() && $('#dateTo').val()) {
                    filterTable();
                }
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

            // Add Invoice form submission
            $('#addInvoiceForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'charge_invoice.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
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
                    error: function(xhr, status, error) {
                        alert('Error submitting form: ' + error);
                    }
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
</body>

</html>
