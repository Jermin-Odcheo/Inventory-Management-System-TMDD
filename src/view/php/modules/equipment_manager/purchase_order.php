<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Include the header (this should load common assets)
include('../../general/header.php');

// Set audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'Purchase Order'");
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
    $po_no = trim($_POST['po_no'] ?? '');
    $date_of_order = trim($_POST['date_of_order'] ?? '');
    $no_of_units = trim($_POST['no_of_units'] ?? '');
    $item_specifications = trim($_POST['item_specifications'] ?? '');

    if (empty($po_no) || empty($date_of_order) || empty($no_of_units) || empty($item_specifications)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
        if (is_ajax_request()) {
            echo json_encode(['status' => 'error', 'message' => $_SESSION['errors'][0]]);
            exit;
        }
        header("Location: purchase_order.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO purchase_order (po_no, date_of_order, no_of_units, item_specifications, date_created, is_disabled)
                                   VALUES (?, ?, ?, ?, NOW(), 0)");
            $stmt->execute([$po_no, $date_of_order, $no_of_units, $item_specifications]);
            $_SESSION['success'] = "Purchase Order has been added successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error adding Purchase Order: " . $e->getMessage()];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            $_SESSION['errors'] = ["Invalid Purchase Order ID."];
            if (is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => $_SESSION['errors'][0]]);
                exit;
            }
            header("Location: purchase_order.php");
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE purchase_order SET po_no = ?, date_of_order = ?, no_of_units = ?, item_specifications = ? WHERE id = ?");
            $stmt->execute([$po_no, $date_of_order, $no_of_units, $item_specifications, $id]);
            $_SESSION['success'] = "Purchase Order has been updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error updating Purchase Order: " . $e->getMessage()];
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
    header("Location: purchase_order.php");
    exit;
}

// ------------------------
// DELETE PURCHASE ORDER (soft delete)
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("UPDATE purchase_order SET is_disabled = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Purchase Order deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Purchase Order: " . $e->getMessage()];
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
    </style>
</head>
<body>
<?php include('../../general/sidebar.php'); ?>
<div class="wrapper">
    <div class="main-content"
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
                        <i class="bi bi-plus-circle"></i> Add Purchase Order
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
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-outline-primary edit-po"
                                               data-id="<?php echo htmlspecialchars($po['id']); ?>"
                                               data-po="<?php echo htmlspecialchars($po['po_no']); ?>"
                                               data-date="<?php echo htmlspecialchars($po['date_of_order']); ?>"
                                               data-units="<?php echo htmlspecialchars($po['no_of_units']); ?>"
                                               data-item="<?php echo htmlspecialchars($po['item_specifications']); ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger delete-po"
                                               data-id="<?php echo htmlspecialchars($po['id']); ?>"
                                               href="#">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
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
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                            id="totalRows">100</span> entries
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
</div>

<div id="toastContainer"></div>

<!-- Add Purchase Order Modal -->
<div class="modal fade" id="addPOModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addPOForm" method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="po_no" class="form-label">PO Number <span class="text-danger">*</span></label>
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
                        <label for="item_specifications" class="form-label">Item Specifications <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_specifications" required>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Add Purchase Order</button>
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
                        <label for="edit_date_of_order" class="form-label">Date of Order <span
                                    class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date_of_order" id="edit_date_of_order" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_no_of_units" class="form-label">No. of Units <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="no_of_units" id="edit_no_of_units" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_item_specifications" class="form-label">Item Specifications <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_specifications" id="edit_item_specifications"
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


<!-- JavaScript for functionality -->
<script>

    $(document).ready(function () {
        // Search filter for purchase orders
        $('#searchPO').on('input', function () {
            var searchText = $(this).val().toLowerCase();
            $("#table tbody tr").filter(function () {
                $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
            });
        });

        // Trigger Edit Purchase Order Modal
        $('.edit-po').click(function () {
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
            $('#editPOModal').modal('show');
        });

        // Delete Purchase Order confirmation
        $('.delete-po').click(function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (confirm('Are you sure you want to delete this purchase order?')) {
                window.location.href = '?action=delete&id=' + id;
            }
        });

        // Add Purchase Order AJAX submission
        $('#addPOForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'purchase_order.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.status === 'success') {
                            $('#addPOModal').modal('hide');
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

        // Edit Purchase Order AJAX submission
        $('#editPOForm').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: 'purchase_order.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function (response) {
                    try {
                        var result = JSON.parse(response);
                        if (result.status === 'success') {
                            $('#editPOModal').modal('hide');
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
