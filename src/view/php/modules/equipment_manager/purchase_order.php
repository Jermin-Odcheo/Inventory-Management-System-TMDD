<?php
session_start();

// Start output buffering to prevent unwanted output for AJAX responses.
ob_start();

require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Add this function to log audit entries
function logAudit($pdo, $action, $oldVal, $newVal) {
    $stmt = $pdo->prepare("INSERT INTO audit_log (UserID, Module, Action, OldVal, NewVal, Date_Time) VALUES (?, 'Purchase Order', ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $action, $oldVal, $newVal]);
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize messages
    $errors = [];
    $success = "";

    $po_no             = trim($_POST['po_no'] ?? '');
    $date_of_order     = trim($_POST['date_of_order'] ?? '');
    $no_of_units       = trim($_POST['no_of_units'] ?? '');
    $item_specifications = trim($_POST['item_specifications'] ?? '');

    if (empty($po_no) || empty($date_of_order) || empty($no_of_units) || empty($item_specifications)) {
        $errors[] = "Please fill in all required fields.";
    }

    if (empty($errors)) {
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            try {
                $stmt = $pdo->prepare("INSERT INTO purchase_order 
                    (po_no, date_of_order, no_of_units, item_specifications, date_created, is_disabled)
                    VALUES (?, ?, ?, ?, NOW(), 0)");
                $stmt->execute([$po_no, $date_of_order, $no_of_units, $item_specifications]);
                logAudit($pdo, 'add', null, json_encode(['po_no' => $po_no, 'date_of_order' => $date_of_order, 'no_of_units' => $no_of_units, 'item_specifications' => $item_specifications]));
                $success = "Purchase Order has been added successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error adding Purchase Order: " . $e->getMessage();
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                $errors[] = "Invalid Purchase Order ID.";
            } else {
                try {
                    // Fetch the current values for OldVal
                    $stmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($oldData) {
                        $stmt = $pdo->prepare("UPDATE purchase_order SET 
                            po_no = ?, date_of_order = ?, no_of_units = ?, item_specifications = ? 
                            WHERE id = ?");
                        $stmt->execute([$po_no, $date_of_order, $no_of_units, $item_specifications, $id]);

                        // Log both old and new values
                        logAudit($pdo, 'modified', json_encode($oldData), json_encode(['po_no' => $po_no, 'date_of_order' => $date_of_order, 'no_of_units' => $no_of_units, 'item_specifications' => $item_specifications]));
                        $success = "Purchase Order has been updated successfully.";
                    } else {
                        $errors[] = "Purchase Order not found.";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error updating Purchase Order: " . $e->getMessage();
                }
            }
        }
    }

    // If AJAX, clear the buffer and return only JSON.
    if (is_ajax_request()) {
        ob_clean();
        $response = empty($errors)
            ? ['status' => 'success', 'message' => $success ?: 'Operation completed successfully']
            : ['status' => 'error', 'message' => $errors[0]];
        echo json_encode($response);
        exit;
    } else {
        // For non-AJAX requests, save messages in session and redirect.
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
        } else {
            $_SESSION['success'] = $success;
        }
        header("Location: purchase_order.php");
        exit;
    }
}

// ------------------------
// DELETE PURCHASE ORDER (soft delete) with AJAX support
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        // Fetch the current values for OldVal before deletion
        $stmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ?");
        $stmt->execute([$id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($oldData) {
            $stmt = $pdo->prepare("DELETE FROM purchase_order WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Purchase Order deleted successfully.";
            // Log the deletion with old values
            logAudit($pdo, 'delete', json_encode($oldData), null);
        } else {
            $_SESSION['errors'] = ["Purchase Order not found for deletion."];
        }
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Purchase Order: " . $e->getMessage()];
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
    header("Location: purchase_order.php");
    exit;
}

// ------------------------
// LOAD PURCHASE ORDER DATA FOR EDITING (if applicable)
// ------------------------
$editPurchaseOrder = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ? AND is_disabled = 0");
        $stmt->execute([$id]);
        $editPurchaseOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editPurchaseOrder) {
            $_SESSION['errors'] = ["Purchase Order not found for editing."];
            header("Location: purchase_order.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Purchase Order for editing: " . $e->getMessage();
    }
}

// ------------------------
// RETRIEVE ALL PURCHASE ORDERS (active only)
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM purchase_order WHERE is_disabled = 0 ORDER BY id DESC");
    $purchaseOrders = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Purchase Orders: " . $e->getMessage();
}

// Add the filter code here, BEFORE ob_end_clean()
if (isset($_GET['action']) && $_GET['action'] === 'filter') {
    try {
        $query = "SELECT * FROM purchase_order WHERE is_disabled = 0";
        $params = [];

        switch ($_GET['type']) {
            case 'desc':
                $query .= " ORDER BY date_of_order DESC";
                break;
            case 'asc':
                $query .= " ORDER BY date_of_order ASC";
                break;
            case 'month':
                $query .= " AND MONTH(date_of_order) = ? AND YEAR(date_of_order) = ?";
                $params[] = $_GET['month'];
                $params[] = $_GET['year'];
                break;
            case 'range':
                $query .= " AND date_of_order BETWEEN ? AND ?";
                $params[] = $_GET['dateFrom'];
                $params[] = $_GET['dateTo'];
                break;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $filteredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (is_ajax_request()) {
            ob_clean();
            echo json_encode([
                'status' => 'success',
                'orders' => $filteredOrders
            ]);
            exit;
        }
    } catch (PDOException $e) {
        if (is_ajax_request()) {
            ob_clean();
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
}

// Now that AJAX POST has been processed (if any),
// flush the output buffer and include the header.
ob_end_clean();
include('../../general/header.php');

// Add this after the DELETE action handling section
if (isset($_GET['action']) && $_GET['action'] === 'filter') {
    try {
        $query = "SELECT * FROM purchase_order WHERE is_disabled = 0";
        $params = [];

        switch ($_GET['type']) {
            case 'desc':
                $query .= " ORDER BY date_of_order DESC";
                break;
            case 'asc':
                $query .= " ORDER BY date_of_order ASC";
                break;
            case 'month':
                $query .= " AND MONTH(date_of_order) = ? AND YEAR(date_of_order) = ?";
                $params[] = $_GET['month'];
                $params[] = $_GET['year'];
                break;
            case 'range':
                $query .= " AND date_of_order BETWEEN ? AND ?";
                $params[] = $_GET['dateFrom'];
                $params[] = $_GET['dateTo'];
                break;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $filteredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (is_ajax_request()) {
            ob_clean();
            echo json_encode([
                'status' => 'success',
                'orders' => $filteredOrders
            ]);
            exit;
        }
    } catch (PDOException $e) {
        if (is_ajax_request()) {
            ob_clean();
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Purchase Order Management</title>
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
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 300px;
            background-color: #2c3e50;
            color: #fff;
        }
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 300px;
        }
        
        /* Fix for Save Changes button hover state */
        .btn-primary:hover {
            color: #fff !important; /* Ensure text stays white on hover */
            background-color: #0b5ed7; /* Darker blue on hover */
            border-color: #0a58ca;
        }
        
        /* Specific styling for the edit form button */
        #editPOForm .btn-primary {
            transition: all 0.2s ease-in-out;
        }
        
        #editPOForm .btn-primary:hover {
            color: #fff !important;
            background-color: #0d6efd;
            border-color: #0d6efd;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .action-btn {
            background: transparent;
            border: none;
            padding: 6px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .edit-po:hover {
            color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .delete-po:hover {
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .bi-pencil-square,
        .bi-trash {
            font-size: 1rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
<?php include('../../general/sidebar.php'); ?>
<div class="wrapper">
    <div class="main-content">
        <div class="container-fluid">

            <!-- Display Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php foreach ($errors as $err): ?>
                        <p><i class="bi bi-exclamation-triangle"></i> <?php echo $err; ?></p>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <h2 class="mb-4">Purchase Order Management</h2>

            <div class="card shadow">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul"></i> List of Purchase Orders</span>
                    <div class="input-group w-auto">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchPO" class="form-control" placeholder="Search purchase order...">
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                data-bs-target="#addPOModal">
                            <i class="bi bi-plus-circle"></i> Create Purchase Order
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
                        <table id="purchaseTable" class="table table-striped table-hover">
                            <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>PO Number</th>
                                <th>Date of Order</th>
                                <th>No. of Units</th>
                                <th>Item Specifications</th>
                                <th>Created Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($purchaseOrders)): ?>
                                <?php foreach ($purchaseOrders as $po): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($po['id']); ?></td>
                                        <td><?php echo htmlspecialchars($po['po_no']); ?></td>
                                        <td><?php echo htmlspecialchars($po['date_of_order']); ?></td>
                                        <td><?php echo htmlspecialchars($po['no_of_units']); ?></td>
                                        <td><?php echo htmlspecialchars($po['item_specifications']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($po['date_created'])); ?></td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button class="action-btn edit-po"
                                                    data-id="<?php echo htmlspecialchars($po['id']); ?>"
                                                    data-po="<?php echo htmlspecialchars($po['po_no']); ?>"
                                                    data-date="<?php echo htmlspecialchars($po['date_of_order']); ?>"
                                                    data-units="<?php echo htmlspecialchars($po['no_of_units']); ?>"
                                                    data-item="<?php echo htmlspecialchars($po['item_specifications']); ?>">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="action-btn delete-po"
                                                    data-id="<?php echo htmlspecialchars($po['id']); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">No Purchase Orders found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <!-- Pagination Controls (optional) -->
                        <div class="container-fluid">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-sm-auto">
                                    <div class="text-muted">
                                        Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of
                                        <span id="totalRows">100</span> entries
                                    </div>
                                </div>
                                <div class="col-12 col-sm-auto ms-sm-auto">
                                    <div class="d-flex align-items-center gap-2">
                                        <button id="prevPage"
                                            class="btn btn-outline-primary d-flex align-items-center gap-1">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </button>
                                        <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                            <option value="10" selected>10</option>
                                            <option value="20">20</option>
                                            <option value="30">30</option>
                                            <option value="50">50</option>
                                        </select>
                                        <button id="nextPage"
                                            class="btn btn-outline-primary d-flex align-items-center gap-1">
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
                        </div> <!-- End main-content -->
                        </div> <!-- End wrapper -->

                        <!-- Delete Purchase Order Modal -->
                        <div class="modal fade" id="deletePOModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Deletion</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete this purchase order?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" id="confirmDeleteBtn"
                                            class="btn btn-danger">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;"></div>

                        <!-- Add Purchase Order Modal -->
                        <div class="modal fade" id="addPOModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Create New Purchase Order</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addPOForm" method="post">
                                            <input type="hidden" name="action" value="add">
                                            <div class="mb-3">
                                                <label for="po_no" class="form-label">PO Number <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="po_no" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="date_of_order" class="form-label">Date of Order <span
                                                        class="text-danger">*</span></label>
                                                <input type="date" class="form-control" name="date_of_order" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="no_of_units" class="form-label">No. of Units <span
                                                        class="text-danger">*</span></label>
                                                <input type="number" class="form-control" name="no_of_units" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="item_specifications" class="form-label">Item Specifications
                                                    <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="item_specifications"
                                                    required>
                                            </div>
                                            <div class="text-end">
                                                <button type="button" class="btn btn-secondary"
                                                    style="margin-right: 4px;" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Confirm
                                                    </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

<!-- Edit Purchase Order Modal -->
<div class="modal fade" id="editPOModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPOForm" method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_po_id">
                    <div class="mb-3">
                        <label for="edit_po_no" class="form-label">PO Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="po_no" id="edit_po_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_date_of_order" class="form-label">Date of Order <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_of_order" id="edit_date_of_order" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_no_of_units" class="form-label">No. of Units <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="no_of_units" id="edit_no_of_units" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_item_specifications" class="form-label">Item Specifications <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_specifications" id="edit_item_specifications" required>
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
    // Utility function to show toast messages
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toastContainer');
        const toastId = 'toast-' + Date.now();
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        
        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
        toast.show();
        
        // Auto-remove the element after hiding
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }

    // Use event delegation so new elements get bound events
    $(document).on('input', '#searchPO', function () {
        var searchText = $(this).val().toLowerCase();
        $("#table tbody tr").filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
        });
    });

    // Trigger Edit Purchase Order Modal using Bootstrap 5 Modal API
    $(document).on('click', '.edit-po', function () {
        var id = $(this).data('id');
        var po = $(this).data('po');
        var date = $(this).data('date');
        var units = $(this).data('units');
        var item = $(this).data('item');

        $('#edit_po_id').val(id);
        $('#edit_po_no').val(po);
        $('#edit_date_of_order').val(date);
        $('#edit_no_of_units').val(units);
        $('#edit_item_specifications').val(item);

        var editModal = new bootstrap.Modal(document.getElementById('editPOModal'));
        editModal.show();
    });

    // Delete Purchase Order using a Delete Modal
    var deleteId = null; // global var to store id for deletion
    $(document).on('click', '.delete-po', function (e) {
        e.preventDefault();
        deleteId = $(this).data('id');
        var deleteModal = new bootstrap.Modal(document.getElementById('deletePOModal'));
        deleteModal.show();
    });

    // Confirm Delete button in the Delete Modal
    $(document).on('click', '#confirmDeleteBtn', function () {
        if (deleteId) {
            $.ajax({
                url: 'purchase_order.php',
                method: 'GET',
                data: { action: 'delete', id: deleteId },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                    // Hide the delete modal after processing
                    var deleteModalEl = document.getElementById('deletePOModal');
                    var modalInstance = bootstrap.Modal.getInstance(deleteModalEl);
                    modalInstance.hide();
                },
                error: function () {
                    showToast('Error processing request.', 'error');
                }
            });
        }
    });

    // Add Purchase Order AJAX submission
    $('#addPOForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'purchase_order.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                        showToast(response.message, 'success');
                    });
                    // Hide the add modal using Bootstrap API
                    var addModalEl = document.getElementById('addPOModal');
                    var addModal = bootstrap.Modal.getInstance(addModalEl);
                    if (addModal) {
                        addModal.hide();
                    }
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function () {
                showToast('Error processing request.', 'error');
            }
        });
    });

    // Edit Purchase Order AJAX submission
    $('#editPOForm').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: 'purchase_order.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                        showToast(response.message, 'success');
                    });
                    var editModalEl = document.getElementById('editPOModal');
                    var editModal = bootstrap.Modal.getInstance(editModalEl);
                    if (editModal) {
                        editModal.hide();
                    }
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('Error processing request.', 'error');
            }
        });
    });

    $('#addPOModal').on('hidden.bs.modal', function () {
        $('#addPOForm')[0].reset();
    });

    // Date filter handling
    $('#dateFilter').on('change', function() {
        const filterType = $(this).val();
        
        // Hide all containers first
        $('#dateInputsContainer').hide();
        $('#monthPickerContainer').hide();
        $('#dateRangePickers').hide();
        
        // Show appropriate containers based on selection
        if (filterType === 'month') {
            $('#dateInputsContainer').show();
            $('#monthPickerContainer').show();
        } else if (filterType === 'range') {
            $('#dateInputsContainer').show();
            $('#dateRangePickers').show();
        } else if (filterType === 'desc' || filterType === 'asc') {
            // Immediately trigger the filter for desc/asc
            applyFilter(filterType);
        }
    });

    // Handle month/year selection changes
    $('#monthSelect, #yearSelect').on('change', function() {
        const month = $('#monthSelect').val();
        const year = $('#yearSelect').val();
        if (month && year) {
            applyFilter('month', { month, year });
        }
    });

    // Handle date range changes
    $('#dateFrom, #dateTo').on('change', function() {
        const dateFrom = $('#dateFrom').val();
        const dateTo = $('#dateTo').val();
        if (dateFrom && dateTo) {
            applyFilter('range', { dateFrom, dateTo });
        }
    });

    // Function to apply the filter
    function applyFilter(type, params = {}) {
        let filterData = {
            action: 'filter',
            type: type
        };

        // Add additional parameters based on filter type
        if (type === 'month') {
            filterData.month = params.month;
            filterData.year = params.year;
        } else if (type === 'range') {
            filterData.dateFrom = params.dateFrom;
            filterData.dateTo = params.dateTo;
        }

        $.ajax({
            url: 'purchase_order.php',
            method: 'GET',
            data: filterData,
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.status === 'success') {
                        // Update table body with filtered results
                        let tableBody = '';
                        data.orders.forEach(po => {
                            tableBody += `
                            <tr>
                                <td>${po.id}</td>
                                <td>${po.po_no}</td>
                                <td>${po.date_of_order}</td>
                                <td>${po.no_of_units}</td>
                                <td>${po.item_specifications}</td>
                                <td>${new Date(po.date_created).toLocaleString()}</td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <button class="action-btn edit-po" data-id="${po.id}" data-po="${po.po_no}"
                                            data-date="${po.date_of_order}" data-units="${po.no_of_units}"
                                            data-item="${po.item_specifications}">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="action-btn delete-po" data-id="${po.id}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            `;
                        });
                        $('#purchaseTable tbody').html(tableBody || '<tr><td colspan="7">No Purchase Orders found.</td></tr>');
                    } else {
                        showToast('Error filtering data: ' + data.message, 'error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    showToast('Error processing response', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showToast('Error filtering data', 'error');
            }
        });
    }

    // Reset filter when empty option is selected
    $('#dateFilter').on('change', function() {
        if (!$(this).val()) {
            window.location.reload();
        }
    });
</script>

<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js" defer></script>
<?php include '../../general/footer.php'; ?>
</body>
</html>