<?php
require_once '../../../../../config/ims-tmdd.php';
session_start();

// start buffering all output (header/sidebar/footer HTML will be captured)
ob_start();

include '../../general/header.php';

// For AJAX requests, we want to handle them separately
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
$canRemove = $rbac->hasPrivilege('Equipment Transactions', 'Remove');

function is_ajax_request()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Add this function to log audit entries
/**
 * Logs an audit entry including Details and Status.
 *
 * @param PDO    $pdo
 * @param string $action   e.g. 'Create', 'Modify', 'Remove'
 * @param mixed  $oldVal   JSON or null
 * @param mixed  $newVal   JSON or null
 * @param int    $entityId optional PO ID
 * @param string $details  human-readable summary
 * @param string $status   e.g. 'Successful' or 'Failed'
 */
function logAudit($pdo, $action, $oldVal, $newVal, $entityId = null, $details = '', $status = 'Successful')
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_log
          (UserID, EntityID, Module, Action, OldVal, NewVal, Details, Status, Date_Time)
        VALUES (?, ?, 'Purchase Order', ?, ?, ?, ?, ?, NOW())
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


// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize messages
    $errors = [];
    $success = "";

    $po_no             = trim($_POST['po_no'] ?? '');
    // Enforce PO prefix before validation
    if ($po_no !== '' && strpos($po_no, 'PO') !== 0) {
        $po_no = 'PO' . $po_no;
    }
    $date_of_order     = trim($_POST['date_of_order'] ?? '');
    $no_of_units       = trim($_POST['no_of_units'] ?? '');
    $item_specifications = trim($_POST['item_specifications'] ?? '');

    if (empty($po_no) || empty($date_of_order) || empty($no_of_units) || empty($item_specifications)) {
        $errors[] = "Please fill in all required fields.";
    } elseif (!preg_match('/^PO\d+$/', $po_no)) {
        $errors[] = "PO Number must be in the format PO followed by numbers (e.g., PO123).";
    }

    if (empty($errors)) {
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            try {
                // Check if user has Create privilege
                if (!$rbac->hasPrivilege('Equipment Transactions', 'Create')) {
                    throw new Exception('You do not have permission to add purchase orders');
                }

                $stmt = $pdo->prepare("INSERT INTO purchase_order 
                    (po_no, date_of_order, no_of_units, item_specifications, date_created, is_disabled)
                    VALUES (?, ?, ?, ?, NOW(), 0)");
                $stmt->execute([$po_no, $date_of_order, $no_of_units, $item_specifications]);
                $payload = json_encode([
                    'po_no'               => $po_no,
                    'date_of_order'       => $date_of_order,
                    'no_of_units'         => $no_of_units,
                    'item_specifications' => $item_specifications
                ]);
                logAudit(
                    $pdo,
                    'Create',
                    null,
                    $payload,
                    $pdo->lastInsertId(),             // the new PO ID
                    "Purchase Order {$po_no} created",
                    'Successful'
                );

                $success = "Purchase Order has been added successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error adding Purchase Order: " . $e->getMessage();
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                $errors[] = "Invalid Purchase Order ID.";
            } else {
                try {
                    // 1) permission
                    if (!$rbac->hasPrivilege('Equipment Transactions', 'Modify')) {
                        throw new Exception('You do not have permission to modify purchase orders');
                    }

                    // 2) fetch existing row
                    $stmt = $pdo->prepare("SELECT po_no, date_of_order, no_of_units, item_specifications FROM purchase_order WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$oldData) {
                        $errors[] = "Purchase Order not found.";
                    } else {
                        // 3) prepare new values
                        $newData = [
                            'po_no'               => $po_no,
                            'date_of_order'       => $date_of_order,
                            'no_of_units'         => $no_of_units,
                            'item_specifications' => $item_specifications
                        ];

                        // 4) compute diffs
                        $fieldLabels = [
                            'po_no'               => 'PO No',
                            'date_of_order'       => 'Date of Order',
                            'no_of_units'         => 'No of Units',
                            'item_specifications' => 'Item Specifications'
                        ];
                        $oldSubset   = [];
                        $newSubset   = [];
                        $detailParts = [];

                        foreach ($fieldLabels as $key => $label) {
                            $oldVal = $oldData[$key];
                            $newVal = $newData[$key];

                            // normalize numeric fields by casting to int, others by trimming strings
                            if ($key === 'no_of_units') {
                                $oldNorm = (string)(int)$oldVal;
                                $newNorm = (string)(int)$newVal;
                            } else {
                                $oldNorm = trim((string)$oldVal);
                                $newNorm = trim((string)$newVal);
                            }

                            // only record a change if the normalized values differ
                            if ($oldNorm !== $newNorm) {
                                $oldSubset[$key]   = $oldNorm;
                                $newSubset[$key]   = $newNorm;
                                $detailParts[]     = "The {$label} was changed from '{$oldNorm}' to '{$newNorm}'.";
                            }
                        }


                        // 5) only run the update + audit if something actually changed
                        if (!empty($newSubset)) {
                            // run the UPDATE
                            $stmt = $pdo->prepare("
                        UPDATE purchase_order SET 
                          po_no               = ?, 
                          date_of_order       = ?, 
                          no_of_units         = ?, 
                          item_specifications = ? 
                        WHERE id = ?
                    ");
                            $stmt->execute([
                                $newData['po_no'],
                                $newData['date_of_order'],
                                $newData['no_of_units'],
                                $newData['item_specifications'],
                                $id
                            ]);

                            // log only the real changes
                            logAudit(
                                $pdo,
                                'Modified',
                                json_encode($oldSubset),
                                json_encode($newSubset),
                                $id,
                                implode(' ', $detailParts),
                                'Successful'
                            );

                            $success = "Purchase Order has been updated successfully.";
                        } else {
                            // no fields actually changed
                            $success = "No changes detected; nothing was updated.";
                        }
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error updating Purchase Order: " . $e->getMessage();
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
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
// REMOVE PURCHASE ORDER (soft delete) with AJAX support
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        // Check if user has Remove privilege
        if (!$rbac->hasPrivilege('Equipment Transactions', 'Remove')) {
            throw new Exception('You do not have permission to remove purchase orders');
        }

        // Fetch the current values for OldVal before deletion
        $stmt = $pdo->prepare("SELECT * FROM purchase_order WHERE id = ?");
        $stmt->execute([$id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($oldData) {
            $stmt = $pdo->prepare("UPDATE purchase_order SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "Purchase Order removed successfully.";
            // Log the removal with old values and entity id
            logAudit(
                $pdo,
                'Remove',
                json_encode($oldData),
                null,
                $id,
                "Purchase Order {$oldData['po_no']} removed",
                'Successful'
            );
        } else {
            $_SESSION['errors'] = ["Purchase Order not found for Removal."];
        }
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error Removing Purchase Order: " . $e->getMessage()];
    } catch (Exception $e) {
        $_SESSION['errors'] = [$e->getMessage()];
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

// Add this after the REMOVE action handling section
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../../styles/css/equipment-transactions.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="wrapper">
        <div class="main-content">
            <div class="container-fluid">
                <?php include '../../general/sidebar.php'; ?>
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
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <?php if ($canCreate): ?>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#addPOModal">
                                    <i class="bi bi-plus-circle"></i> Create Purchase Order
                                </button>
                            <?php else: ?>
                                <div></div>
                            <?php endif; ?>
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
                            <div class="input-group w-auto">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="searchPO" class="form-control" placeholder="Search purchase order...">
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
                                <tbody id="poTable">
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
                                                        <?php if ($canModify): ?>
                                                            <a class="btn btn-sm btn-outline-primary edit-po"
                                                                data-id="<?php echo htmlspecialchars($po['id']); ?>"
                                                                data-po="<?php echo htmlspecialchars($po['po_no']); ?>"
                                                                data-date="<?php echo htmlspecialchars($po['date_of_order']); ?>"
                                                                data-units="<?php echo htmlspecialchars($po['no_of_units']); ?>"
                                                                data-item="<?php echo htmlspecialchars($po['item_specifications']); ?>">
                                                                <i class="bi bi-pencil-square"></i> <span>Edit</span>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($canRemove): ?>
                                                            <a class="btn btn-sm btn-outline-danger remove-po"
                                                                data-id="<?php echo htmlspecialchars($po['id']); ?>"
                                                                href="#">
                                                                <i class="bi bi-trash"></i> <span>Remove</span>
                                                            </a>
                                                        <?php endif; ?>
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
                            <div class="container-fluid">
                                <div class="row align-items-center g-3">
                                    <div class="col-12 col-sm-auto">
                                        <div class="text-muted">
                                            <?php $totalLogs = count($purchaseOrders); ?>
                                            <input type="hidden" id="total-users" value="<?= $totalLogs ?>">
                                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
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
        </div> </div> <?php if ($canRemove): ?>
        <div class="modal fade" id="removePOModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Removal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to remove this purchase order?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmRemoveBtn"
                            class="btn btn-danger">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <?php if ($canCreate): ?>
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
                                <input type="number" class="form-control" name="po_no" min="0" step="1" required pattern="\d*" inputmode="numeric">
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
    <?php endif; ?>

    <?php if ($canModify): ?>
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
                                <input type="number" class="form-control" name="po_no" id="edit_po_no" min="0" step="1" required pattern="\d*" inputmode="numeric">
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
    <?php endif; ?>

    <script>
        // Placeholder for showToast function if not defined elsewhere
        function showToast(message, type) {
            console.log(`Toast (${type}): ${message}`);
            // In a real application, you'd show a Bootstrap toast here
            // Example:
            // const toastEl = document.getElementById('liveToast');
            // const toastBody = toastEl.querySelector('.toast-body');
            // toastBody.textContent = message;
            // toastEl.classList.remove('text-bg-success', 'text-bg-danger');
            // toastEl.classList.add(type === 'success' ? 'text-bg-success' : 'text-bg-danger');
            // const toast = new bootstrap.Toast(toastEl);
            // toast.show();
        }

        // Function to ensure modal backdrop is removed
        function removeModalBackdrop() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => {
                backdrop.remove();
            });
            // Also ensure body scrolling is re-enabled if Bootstrap failed to do so
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }

        // Use event delegation so new elements get bound events
        $(document).on('input', '#searchPO', function() {
            var searchText = $(this).val().toLowerCase();
            $("#table tbody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
            });
        });

        $(document).ready(function() {
      

            $(document).on('click', '.edit-po', function(e) {
                e.preventDefault();
               

                // Extract all data- attributes
                const id = $(this).data('id');
                const rawPo = $(this).data('po') || ''; // e.g. "PO123"
                const date = $(this).data('date') || '';
                const units = $(this).data('units') || '';
                const item = $(this).data('item') || '';

                // Strip off the "PO" prefix so the <input type="number"> only gets digits
                const numericPo = rawPo.replace(/^PO/, '');

                // Fill the Edit form
                $('#edit_po_id').val(id);
                $('#edit_po_no').val(numericPo);
                $('#edit_date_of_order').val(date);
                $('#edit_no_of_units').val(units);
                $('#edit_item_specifications').val(item);

                // Show the modal (using getOrCreateInstance is a bit safer)
                const modalEl = document.getElementById('editPOModal');
                const editModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                editModal.show();
            });
        });


        // Remove Purchase Order using a Remove Modal
        var removeId = null; // global var to store id for removal
        $(document).on('click', '.remove-po', function(e) {
            e.preventDefault();
            removeId = $(this).data('id');
            var removeModal = new bootstrap.Modal(document.getElementById('removePOModal'));
            removeModal.show();
        });

        // Confirm Remove button in the Remove Modal
        $(document).on('click', '#confirmRemoveBtn', function() {
            if (removeId) {
                $.ajax({
                    url: 'purchase_order.php',
                    method: 'GET',
                    data: {
                        action: 'remove',
                        id: removeId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                        // Hide the remove modal after processing
                        var removeModalEl = document.getElementById('removePOModal');
                        var modalInstance = bootstrap.Modal.getInstance(removeModalEl);
                        modalInstance.hide();
                    },
                    error: function() {
                        showToast('Error processing request.', 'error');
                    }
                });
            }
        });

        // Add Purchase Order AJAX submission
        $('#addPOForm').on('submit', function(e) {
            e.preventDefault();
            // Add PO prefix before sending
            let poNo = $('input[name="po_no"]', this).val();
            let formData = $(this).serializeArray();
            let dataObj = {};
            formData.forEach(function(item) {
                if (item.name === 'po_no') {
                    dataObj['po_no'] = 'PO' + poNo;
                } else {
                    dataObj[item.name] = item.value;
                }
            });
            $.ajax({
                url: 'purchase_order.php',
                method: 'POST',
                data: dataObj,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                            showToast(response.message, 'success');
                        });
                        // Hide the add modal using Bootstrap API
                        var addModalEl = document.getElementById('addPOModal');
                        var addModal = bootstrap.Modal.getInstance(addModalEl);
                        if (addModal) {
                            addModal.hide();
                            // Listen for the hidden event to ensure backdrop is gone
                            $(addModalEl).on('hidden.bs.modal', function () {
                                removeModalBackdrop(); // Call this to clean up
                                $(this).off('hidden.bs.modal'); // Remove listener to prevent multiple calls
                            });
                        } else {
                            // If modal instance wasn't found, force backdrop removal anyway
                            removeModalBackdrop();
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

        // Edit Purchase Order AJAX submission
        $('#editPOForm').on('submit', function(e) {
            e.preventDefault();
            // Add PO prefix before sending
            let poNo = $('#edit_po_no').val();
            let formData = $(this).serializeArray();
            let dataObj = {};
            formData.forEach(function(item) {
                if (item.name === 'po_no') {
                    dataObj['po_no'] = 'PO' + poNo;
                } else {
                    dataObj[item.name] = item.value;
                }
            });
            $.ajax({
                url: 'purchase_order.php',
                method: 'POST',
                data: dataObj,
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
                            // Listen for the hidden event to ensure backdrop is gone
                            $(editModalEl).on('hidden.bs.modal', function () {
                                removeModalBackdrop(); // Call this to clean up
                                $(this).off('hidden.bs.modal'); // Remove listener to prevent multiple calls
                            });
                        } else {
                            // If modal instance wasn't found, force backdrop removal anyway
                            removeModalBackdrop();
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

        $('#addPOModal').on('hidden.bs.modal', function() {
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
                applyFilter('month', {
                    month,
                    year
                });
            }
        });

        // Handle date range changes
        $('#dateFrom, #dateTo').on('change', function() {
            const dateFrom = $('#dateFrom').val();
            const dateTo = $('#dateTo').val();
            if (dateFrom && dateTo) {
                applyFilter('range', {
                    dateFrom,
                    dateTo
                });
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
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-outline-primary edit-po"
                                               data-id="${po.id}"
                                               data-po="${po.po_no}"
                                               data-date="${po.date_of_order}"
                                               data-units="${po.no_of_units}"
                                               data-item="${po.item_specifications}">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger delete-po"
                                               data-id="${po.id}"
                                               href="#">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
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

    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js"></script>
    <script>
        // Initialize pagination when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize pagination with the purchase table ID
            initPagination({
                tableId: 'poTable',
                currentPage: 1,
                rowsPerPageSelectId: 'rowsPerPageSelect',
                currentPageId: 'currentPage',
                rowsPerPageId: 'rowsPerPage',
                totalRowsId: 'totalRows',
                prevPageId: 'prevPage',
                nextPageId: 'nextPage',
                paginationId: 'pagination'
            });
            
            // Create search functionality for the table
            const searchInput = document.getElementById('searchPO');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchText = this.value.toLowerCase();
                    
                    // Filter the rows based on the search text
                    window.filteredRows = window.allRows.filter(row => {
                        return row.textContent.toLowerCase().includes(searchText);
                    });
                    
                    // Reset to first page and update pagination
                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = 1;
                    }
                    updatePagination();
                });
            }

            // Handle rows per page change
            const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
            if (rowsPerPageSelect) {
                rowsPerPageSelect.addEventListener('change', function() {
                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = 1;
                    }
                    updatePagination();
                });
            }
        });
    </script>
    <?php include '../../general/footer.php'; ?>

</body>

</html>
