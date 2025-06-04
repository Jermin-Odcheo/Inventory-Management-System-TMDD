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
    } elseif (!preg_match('/^PO[0-9\/-]+$/', $po_no)) {
        $errors[] = "PO Number must start with 'PO' and can only contain numbers, hyphens (-), and slashes (/).";
    }

    if (empty($errors)) {
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            try {
                // Check if user has Create privilege
                if (!$rbac->hasPrivilege('Equipment Transactions', 'Create')) {
                    throw new Exception('You do not have permission to add purchase orders');
                }

                // Check for duplicate PO number before attempting insert
                $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE po_no = ? AND is_disabled = 0");
                $dupCheck->execute([$po_no]);
                if ($dupCheck->fetchColumn() > 0) {
                    throw new Exception("Purchase Order number '{$po_no}' already exists in the system.");
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
                // Check if it's a duplicate entry error
                if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $errors[] = "Purchase Order number '{$po_no}' already exists in the system.";
                } else {
                    $errors[] = "Error adding Purchase Order: " . $e->getMessage();
                }
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
                        // Check for duplicate PO number if it's being changed
                        if ($oldData['po_no'] !== $po_no) {
                            $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE po_no = ? AND id != ? AND is_disabled = 0");
                            $dupCheck->execute([$po_no, $id]);
                            if ($dupCheck->fetchColumn() > 0) {
                                throw new Exception("Purchase Order number '{$po_no}' already exists in the system.");
                            }
                        }

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
                    // Check if it's a duplicate entry error
                    if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $errors[] = "Purchase Order number '{$po_no}' already exists in the system.";
                    } else {
                        $errors[] = "Error updating Purchase Order: " . $e->getMessage();
                    }
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
        header('Content-Type: application/json');
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
            // Start transaction to ensure all changes succeed or fail together
            $pdo->beginTransaction();
            
            try {
                // Get the PO number we're removing
                $poNo = $oldData['po_no'];
                
                // 1. First clear PO references in any receive_report records
                $rrUpdateStmt = $pdo->prepare("UPDATE receive_report SET po_no = NULL WHERE po_no = ? AND is_disabled = 0");
                $rrUpdateStmt->execute([$poNo]);
                $affectedRRs = $rrUpdateStmt->rowCount();
                
                // 2. Then soft-delete the purchase order
                $poUpdateStmt = $pdo->prepare("UPDATE purchase_order SET is_disabled = 1 WHERE id = ?");
                $poUpdateStmt->execute([$id]);
                
                // If we get here, both operations succeeded
                $pdo->commit();
                
                $_SESSION['success'] = "Purchase Order removed successfully. $affectedRRs linked Receiving Reports were updated.";
                
                // Log the removal with old values and entity id
                logAudit(
                    $pdo,
                    'Remove',
                    json_encode($oldData),
                    null,
                    $id,
                    "Purchase Order {$oldData['po_no']} deleted. $affectedRRs Receiving Reports updated.",
                    'Successful'
                );
            } catch (Exception $innerEx) {
                // Something went wrong during the transaction
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $innerEx; // Re-throw for outer catch
            }
        } else {
            $_SESSION['errors'] = ["Purchase Order not found for Removal."];
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['errors'] = ["Error Removing Purchase Order: " . $e->getMessage()];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
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
            case 'mdy':
                $query .= " AND date_of_order BETWEEN ? AND ?";
                $params[] = $_GET['dateFrom'];
                $params[] = $_GET['dateTo'];
                break;
            case 'month':
                // Month range: get all records between two months (inclusive)
                $from = $_GET['monthFrom'] . '-01';
                $toMonth = $_GET['monthTo'];
                $to = date('Y-m-t', strtotime($toMonth . '-01'));
                $query .= " AND date_of_order BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
            case 'year':
                $from = $_GET['yearFrom'] . '-01-01';
                $to = $_GET['yearTo'] . '-12-31';
                $query .= " AND date_of_order BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
            case 'month_year':
                $from = $_GET['monthYearFrom'] . '-01';
                $toMonth = $_GET['monthYearTo'];
                $to = date('Y-m-t', strtotime($toMonth . '-01'));
                $query .= " AND date_of_order BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
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

// Add the handler for checking PO existence
if (isset($_GET['action']) && $_GET['action'] === 'check_po_exists' && isset($_GET['po_no'])) {
    ob_clean(); // Clear any output buffering
    header('Content-Type: application/json');
    
    try {
        $po_no = trim($_GET['po_no']);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE po_no = ? AND is_disabled = 0");
        $stmt->execute([$po_no]);
        $exists = $stmt->fetchColumn() > 0;
        
        echo json_encode([
            'status' => $exists ? 'exists' : 'not_exists',
            'message' => $exists ? 'PO is valid' : 'PO does not exist or is disabled'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error checking PO: ' . $e->getMessage()
        ]);
    }
    exit;
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
    <script>
        // Function to display toast messages
        function showToast(message, type = 'info', duration = 3000) {
            // Remove any existing toasts
            $('.toast-container').remove();
            
            // Create toast container if it doesn't exist
            let toastContainer = $('<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>');
            
            // Set the appropriate background color based on type
            let bgClass = 'bg-info';
            if (type === 'success') bgClass = 'bg-success';
            if (type === 'error') bgClass = 'bg-danger';
            if (type === 'warning') bgClass = 'bg-warning';
            
            // Create the toast element
            let toast = $(`
                <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header ${bgClass} text-white">
                        <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `);
            
            // Add the toast to the container and the container to the body
            toastContainer.append(toast);
            $('body').append(toastContainer);
            
            // Auto-hide the toast after the specified duration
            setTimeout(function() {
                toast.fadeOut('slow', function() {
                    toastContainer.remove();
                });
            }, duration);
            
            // Add click handler to close button
            toast.find('.btn-close').on('click', function() {
                toast.fadeOut('slow', function() {
                    toastContainer.remove();
                });
            });
        }
    </script>
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
                            <div class="d-flex align-items-center gap-2 flex-wrap">
    <select class="form-select form-select-sm" id="dateFilter" style="width: auto; min-width: 140px;">
        <option value="">Filter by Date</option>
        <option value="mdy">Month-Day-Year Range</option>
        <option value="month">Month Range</option>
        <option value="year">Year Range</option>
        <option value="month_year">Month-Year Range</option>
    </select>
    <div id="dateInputsContainer" class="d-flex align-items-center gap-3 ms-2" style="display: none;">
        <div class="date-group d-none flex-row" id="mdy-group">
            <div class="d-flex flex-column me-2">
                <label for="dateFrom" class="form-label mb-0" style="font-size: 0.9em;">Date From</label>
                <input type="date" id="dateFrom" class="form-control form-control-sm" style="width: 140px;">
            </div>
            <div class="d-flex flex-column">
                <label for="dateTo" class="form-label mb-0" style="font-size: 0.9em;">Date To</label>
                <input type="date" id="dateTo" class="form-control form-control-sm" style="width: 140px;">
            </div>
        </div>
        <div class="date-group d-none flex-row" id="month-group">
            <div class="d-flex flex-column me-2">
                <label for="monthFrom" class="form-label mb-0" style="font-size: 0.9em;">Month From</label>
                <input type="month" id="monthFrom" class="form-control form-control-sm" style="width: 120px;">
            </div>
            <div class="d-flex flex-column">
                <label for="monthTo" class="form-label mb-0" style="font-size: 0.9em;">Month To</label>
                <input type="month" id="monthTo" class="form-control form-control-sm" style="width: 120px;">
            </div>
        </div>
        <div class="date-group d-none flex-row" id="year-group">
            <div class="d-flex flex-column me-2">
                <label for="yearFrom" class="form-label mb-0" style="font-size: 0.9em;">Year From</label>
                <input type="number" id="yearFrom" class="form-control form-control-sm" style="width: 90px;" min="1900" max="2100">
            </div>
            <div class="d-flex flex-column">
                <label for="yearTo" class="form-label mb-0" style="font-size: 0.9em;">Year To</label>
                <input type="number" id="yearTo" class="form-control form-control-sm" style="width: 90px;" min="1900" max="2100">
            </div>
        </div>
        <div class="date-group d-none flex-row" id="monthyear-group">
            <div class="d-flex flex-column me-2">
                <label for="monthYearFrom" class="form-label mb-0" style="font-size: 0.9em;">From (MM-YYYY)</label>
                <input type="month" id="monthYearFrom" class="form-control form-control-sm" style="width: 120px;">
            </div>
            <div class="d-flex flex-column">
                <label for="monthYearTo" class="form-label mb-0" style="font-size: 0.9em;">To (MM-YYYY)</label>
                <input type="month" id="monthYearTo" class="form-control form-control-sm" style="width: 120px;">
            </div>
        </div>
    </div>
    <div class="input-group w-auto" style="min-width:220px;">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" id="searchPO" class="form-control form-control-sm" placeholder="Search purchase order...">
    </div>
    <button type="button" id="applyFilters" class="btn btn-dark btn-sm"><i class="bi bi-funnel"></i> Filter</button>
    <button type="button" id="clearFilters" class="btn btn-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</button>
</div>

                        </div>

                        <div class="table-responsive" id="table">
                            <table id="purchaseTable" class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="sortable" data-sort="id"># <span class="sort-indicator"><span class="arrow-up">▲</span><span class="arrow-down">▼</span></span></th>
                                        <th class="sortable" data-sort="po_no">PO Number <span class="sort-indicator"><span class="arrow-up">▲</span><span class="arrow-down">▼</span></span></th>
                                        <th class="sortable" data-sort="date_of_order">Date of Order <span class="sort-indicator"><span class="arrow-up">▲</span><span class="arrow-down">▼</span></span></th>
                                        <th class="sortable" data-sort="no_of_units">No. of Units <span class="sort-indicator"><span class="arrow-up">▲</span><span class="arrow-down">▼</span></span></th>
                                        <th class="sortable" data-sort="item_specifications">Item Specifications <span class="sort-indicator"><span class="arrow-up">▲</span><span class="arrow-down">▼</span></span></th>
                                        <th class="sortable d-none" data-sort="date_created">Created Date <span class="sort-indicator"></span></th>
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
                                                <td class="d-none"><?php echo date('Y-m-d h:i A', strtotime($po['date_created'])); ?></td>
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
        <link rel="stylesheet" href="src/view/php/modules/equipment_transactions/add_po_modal.css">
<div class="modal fade" id="addPOModal" tabindex="-1">
            <div class="modal-dialog" style="margin-top:100px;">
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
                                <input type="text" class="form-control" name="po_no" id="create_po_no" required pattern="[0-9\-/]+" maxlength="30" title="PO Number can only contain numbers, hyphens (-), and slashes (/)">
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
            <div class="modal-dialog" style="margin-top:100px;">
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
                                <input type="text" class="form-control" name="po_no" id="edit_po_no" required pattern="[0-9\-/]+" maxlength="30" title="PO Number can only contain numbers, hyphens (-), and slashes (/)">
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
        // Initialize pagination when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize with all rows
            window.allRows = Array.from(document.querySelectorAll('#poTable tr'));
            window.filteredRows = [...window.allRows];
            
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
            
            // Force pagination update after initialization
            updatePagination();
            
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
        
        // Function to reinitialize purchase table pagination after AJAX operations
        function reinitPurchaseTableJS() {
            // Initialize with fresh rows
            window.allRows = Array.from(document.querySelectorAll('#poTable tr'));
            window.filteredRows = [...window.allRows];
            
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
            
            // Force pagination update
            updatePagination();
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
            // Use the pagination's search functionality if it exists
            if (typeof window.filteredRows !== 'undefined' && typeof window.allRows !== 'undefined') {
                // Reset to first page when searching
                if (window.paginationConfig) {
                    window.paginationConfig.currentPage = 1;
                }
                
                // Filter the rows based on the search text
                window.filteredRows = window.allRows.filter(row => {
                    return row.textContent.toLowerCase().includes(searchText);
                });
                
                // Update pagination with filtered rows
                updatePagination();
            } else {
                // Fallback to simple toggle if pagination isn't initialized yet
                $("#table tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
                });
            }
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

                // Strip off the "PO" prefix so the <input> only gets allowed chars
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

        // Function to store a toast message that persists through page refresh
        function storeToastMessage(message, type) {
            // Store the message in sessionStorage
            sessionStorage.setItem('toastMessage', message);
            sessionStorage.setItem('toastType', type);
        }

        // Check for stored toast messages on page load
        $(document).ready(function() {
            // Check if we have a stored toast message
            const storedMessage = sessionStorage.getItem('toastMessage');
            const storedType = sessionStorage.getItem('toastType');
            
            if (storedMessage) {
                // Display the toast
                showToast(storedMessage, storedType);
                
                // Clear the stored message so it doesn't show again on future page loads
                sessionStorage.removeItem('toastMessage');
                sessionStorage.removeItem('toastType');
            }
        });

        // Add Purchase Order AJAX submission
        $('#addPOForm').on('submit', function(e) {
            e.preventDefault();
            // Immediately close the modal for instant feedback
            var addModalEl = document.getElementById('addPOModal');
            var addModal = bootstrap.Modal.getInstance(addModalEl);
            if (addModal) {
                addModal.hide();
            }
            // Remove any lingering modal backdrop and restore scrolling
            removeModalBackdrop();
            
            // Store current pagination state
            const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
            const rowsPerPage = $('#rowsPerPageSelect').val();
            
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
                        // Update table without page reload
                        $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                            showToast(response.message, 'success');
                            
                            // Reinitialize pagination with fresh DOM elements
                            reinitPurchaseTableJS();
                            
                            // Reattach event handlers
                            reattachEventHandlers();
                        });
                    } else {
                        // If there's a duplicate entry error, show it and reopen the modal
                        if (response.message && response.message.includes('already exists')) {
                            showToast(response.message, 'error');
                            // Reopen the modal so user can fix the PO number
                            setTimeout(function() {
                                var addModal = new bootstrap.Modal(document.getElementById('addPOModal'));
                                addModal.show();
                            }, 500);
                        } else {
                            showToast(response.message, 'error');
                        }
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
            
            // Store current pagination state
            const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
            const rowsPerPage = $('#rowsPerPageSelect').val();
            
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
                        // Update table without page reload
                        $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                            showToast(response.message, 'success');
                            
                            // Reinitialize pagination with fresh DOM elements
                            reinitPurchaseTableJS();
                            
                            // Reattach event handlers
                            reattachEventHandlers();
                        });
                        
                        // Hide the edit modal
                        var editModalEl = document.getElementById('editPOModal');
                        var editModal = bootstrap.Modal.getInstance(editModalEl);
                        if (editModal) {
                            editModal.hide();
                        }
                    } else {
                        // If there's a duplicate entry error, show it but keep the modal open
                        if (response.message && response.message.includes('already exists')) {
                            showToast(response.message, 'error');
                            // Keep the edit modal open so user can fix the PO number
                        } else {
                            showToast(response.message, 'error');
                            // Hide the modal for other errors
                            var editModalEl = document.getElementById('editPOModal');
                            var editModal = bootstrap.Modal.getInstance(editModalEl);
                            if (editModal) {
                                editModal.hide();
                            }
                        }
                    }
                },
                error: function() {
                    showToast('Error processing request.', 'error');
                }
            });
        });

        // Confirm Remove button in the Remove Modal
        $(document).on('click', '#confirmRemoveBtn', function() {
            if (removeId) {
                // Store current pagination state
                const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
                const rowsPerPage = $('#rowsPerPageSelect').val();
                
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
                            // Update table without page reload
                            $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                                showToast(response.message, 'success');
                                
                                // Reinitialize pagination with fresh DOM elements
                                reinitPurchaseTableJS();
                                
                                // Reattach event handlers
                                reattachEventHandlers();
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
        
        // Function to reattach event handlers to the newly loaded table elements
        function reattachEventHandlers() {
            // Edit button handler
            $('.edit-po').off('click').on('click', function(e) {
                e.preventDefault();
               
                // Extract all data- attributes
                const id = $(this).data('id');
                const rawPo = $(this).data('po') || ''; // e.g. "PO123"
                const date = $(this).data('date') || '';
                const units = $(this).data('units') || '';
                const item = $(this).data('item') || '';

                // Strip off the "PO" prefix so the <input> only gets allowed chars
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
            
            // Remove button handler
            $('.remove-po').off('click').on('click', function(e) {
                e.preventDefault();
                removeId = $(this).data('id');
                var removeModal = new bootstrap.Modal(document.getElementById('removePOModal'));
                removeModal.show();
            });
        }
        
        // Clear filters and reload table
        $('#clearFilters').on('click', function() {
            $('#dateFilter').val('');
            $('#dateInputsContainer input').val('');
            $('#dateInputsContainer .date-group').addClass('d-none');
            $('#dateInputsContainer').hide();
            // Clear search input
            $('#searchPO').val('');
            
            // Fetch fresh data without filters
            const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
            const rowsPerPage = $('#rowsPerPageSelect').val();
            
            $('#purchaseTable').load(location.href + ' #purchaseTable', function() {
                showToast('Filters cleared.', 'success');
                
                // Reinitialize pagination with fresh DOM elements
                reinitPurchaseTableJS();
                
                // Reattach event handlers
                reattachEventHandlers();
            });
        });

        // Date filter UI handling (show/hide label+input pairs for advanced types)
        $('#dateFilter').on('change', function() {
            const filterType = $(this).val();
            const container = $('#dateInputsContainer');
            container.show();
            // Hide all groups first
            container.find('.date-group').addClass('d-none');
            if (!filterType || filterType === 'desc' || filterType === 'asc') {
                container.hide();
                return;
            }
            if (filterType === 'mdy') {
                $('#mdy-group').removeClass('d-none');
            } else if (filterType === 'month') {
                $('#month-group').removeClass('d-none');
            } else if (filterType === 'year') {
                $('#year-group').removeClass('d-none');
            } else if (filterType === 'month_year') {
                $('#monthyear-group').removeClass('d-none');
            }
        });

        // Only trigger filtering when the Filter button is clicked
        $('#applyFilters').on('click', function() {
            const filterType = $('#dateFilter').val();
            if (!filterType) {
                showToast('Please select a filter type.', 'error');
                return;
            }
            let params = {};
            if (filterType === 'mdy') {
                params.dateFrom = $('#dateFrom').val();
                params.dateTo = $('#dateTo').val();
                if (!params.dateFrom || !params.dateTo) {
                    showToast('Please select both Date From and Date To.', 'error');
                    return;
                }
            } else if (filterType === 'month') {
                params.monthFrom = $('#monthFrom').val();
                params.monthTo = $('#monthTo').val();
                if (!params.monthFrom || !params.monthTo) {
                    showToast('Please select both Month From and Month To.', 'error');
                    return;
                }
            } else if (filterType === 'year') {
                params.yearFrom = $('#yearFrom').val();
                params.yearTo = $('#yearTo').val();
                if (!params.yearFrom || !params.yearTo) {
                    showToast('Please select both Year From and Year To.', 'error');
                    return;
                }
            } else if (filterType === 'month_year') {
                params.monthYearFrom = $('#monthYearFrom').val();
                params.monthYearTo = $('#monthYearTo').val();
                if (!params.monthYearFrom || !params.monthYearTo) {
                    showToast('Please select both From and To (MM-YYYY).', 'error');
                    return;
                }
            }
            applyFilter(filterType, params);
        });

        // Function to apply the filter
        function applyFilter(type, params = {}) {
            let filterData = {
                action: 'filter',
                type: type
            };
            // Add extra params for advanced filters
            if (type === 'mdy') {
                filterData.dateFrom = params.dateFrom;
                filterData.dateTo = params.dateTo;
            } else if (type === 'month') {
                // Extract year and month from both
                filterData.monthFrom = params.monthFrom;
                filterData.monthTo = params.monthTo;
            } else if (type === 'year') {
                filterData.yearFrom = params.yearFrom;
                filterData.yearTo = params.yearTo;
            } else if (type === 'month_year') {
                filterData.monthYearFrom = params.monthYearFrom;
                filterData.monthYearTo = params.monthYearTo;
            }
            
            // Store message for filters
            storeToastMessage('Applying filters...', 'info');
            
            // Store the filter settings in sessionStorage so they persist through page reload
            sessionStorage.setItem('filterType', type);
            sessionStorage.setItem('filterParams', JSON.stringify(params));
            
            // Reload page with the filter parameters in the URL
            let url = 'purchase_order.php?action=filter&type=' + type;
            for (const key in params) {
                if (params[key]) {
                    url += '&' + key + '=' + encodeURIComponent(params[key]);
                }
            }
            window.location.href = url + '&t=' + new Date().getTime();
        }

        // Reset filter when empty option is selected
        $('#dateFilter').on('change', function() {
            if (!$(this).val()) {
                window.location.reload();
            }
        });

        // Function to format date with AM/PM (12-hour format)
        function formatDateAMPM(dateString) {
            if (!dateString) return '';
            // Accepts 'YYYY-MM-DD HH:MM:SS' or 'YYYY-MM-DDTHH:MM:SS'
            const d = new Date(dateString.replace(' ', 'T'));
            const year = d.getFullYear();
            const month = (d.getMonth() + 1).toString().padStart(2, '0');
            const day = d.getDate().toString().padStart(2, '0');
            let hours = d.getHours();
            const minutes = d.getMinutes().toString().padStart(2, '0');
            const seconds = d.getSeconds().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            return `${year}-${month}-${day} ${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
        }
    </script>

    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js"></script>
    <script>
        // Initialize pagination when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize with all rows
            window.allRows = Array.from(document.querySelectorAll('#poTable tr'));
            window.filteredRows = [...window.allRows];
            
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
            
            // Force pagination update after initialization
            updatePagination();
            
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
    // Block letters in PO Number fields (Create & Edit)
    function restrictPONumberInput(selector) {
        $(document).on('input', selector, function() {
            // Remove any character that is not a digit, hyphen, or slash
            this.value = this.value.replace(/[^0-9\-/]/g, '');
        });
        $(document).on('keypress', selector, function(e) {
            // Allow only digits, hyphen, slash
            const char = String.fromCharCode(e.which);
            if (!/[0-9\-/]/.test(char)) {
                e.preventDefault();
            }
        });
        // Optional: Block paste of invalid chars
        $(document).on('paste', selector, function(e) {
            const paste = (e.originalEvent || e).clipboardData.getData('text');
            if (/[^0-9\-/]/.test(paste)) {
                e.preventDefault();
            }
        });
    }
    restrictPONumberInput('#create_po_no');
    restrictPONumberInput('#edit_po_no');
    </script>
    <style>
        th.sortable .sort-indicator {
            margin-left: 4px;
            font-size: 0.95em;
            display: inline-flex;
            flex-direction: column;
            vertical-align: middle;
        }
        th.sortable .arrow-up,
        th.sortable .arrow-down {
            color: #999;
            display: block;
            line-height: 0.9;
        }
        th.sortable.asc .arrow-up {
            color: #0d6efd;
            display: block;
        }
        th.sortable.asc .arrow-down {
            display: none;
        }
        th.sortable.desc .arrow-down {
            color: #0d6efd;
            display: block;
        }
        th.sortable.desc .arrow-up {
            display: none;
        }

        /* Fallback if add_po_modal.css is missing */
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable .sort-indicator { margin-left: 4px; font-size: 0.9em; }
        th.sortable.asc .sort-indicator { content: "▲"; }
        th.sortable.desc .sort-indicator { content: "▼"; }
    </style>
    <script>
    // --- SORTABLE COLUMN HEADERS ---
    function initSortableHeaders() {
        const table = document.getElementById('purchaseTable');
        if (!table) return;
        const thead = table.querySelector('thead');
        if (!thead) return;
        let currentSort = { col: null, dir: 'asc' };

        // Add pointer cursor for UX
        thead.querySelectorAll('th.sortable').forEach(th => {
            th.style.cursor = 'pointer';
        });

        function getCellValue(row, idx) {
            return row.children[idx]?.innerText.trim();
        }
        function parseValue(val, sortKey) {
    if (["id", "no_of_units"].includes(sortKey)) return parseFloat(val) || 0;
    if (["date_of_order", "date_created"].includes(sortKey)) return new Date(val);
    return val ? val.toLowerCase() : '';
}
        function getColIdx(sortKey) {
            const headers = Array.from(thead.querySelectorAll('th.sortable'));
            return headers.findIndex(th => th.dataset.sort === sortKey);
        }
        function sortRows(sortKey, dir) {
            const idx = getColIdx(sortKey);
            if (idx === -1) return;
            const colName = thead.querySelectorAll('th.sortable')[idx]?.innerText.split(' ')[0];
            window.filteredRows.sort((a, b) => {
                let vA = getCellValue(a, idx);
                let vB = getCellValue(b, idx);
                vA = parseValue(vA, sortKey);
                vB = parseValue(vB, sortKey);
                if (vA < vB) return dir === 'asc' ? -1 : 1;
                if (vA > vB) return dir === 'asc' ? 1 : -1;
                return 0;
            });
            // Update DOM with sorted rows (first page only, pagination will refresh)
            const tbody = document.getElementById('poTable');
            if (tbody) {
                // Remove all rows
                while (tbody.firstChild) tbody.removeChild(tbody.firstChild);
                // Append sorted rows for current page
                window.filteredRows.forEach(row => tbody.appendChild(row));
            }
        }
        function updateIndicators(activeTh, dir) {
    // Only update indicator arrows, do not touch any other logic
    thead.querySelectorAll('th.sortable').forEach(th => {
        th.classList.remove('asc', 'desc');
        const indicator = th.querySelector('.sort-indicator');
        if (indicator) {
            const up = indicator.querySelector('.arrow-up');
            const down = indicator.querySelector('.arrow-down');
            if (up) { up.style.display = 'block'; up.style.color = '#999'; }
            if (down) { down.style.display = 'block'; down.style.color = '#999'; }
        }
    });
    if (activeTh) {
        activeTh.classList.add(dir);
        const indicator = activeTh.querySelector('.sort-indicator');
        if (indicator) {
            const up = indicator.querySelector('.arrow-up');
            const down = indicator.querySelector('.arrow-down');
            if (dir === 'asc') {
                if (up) { up.style.display = 'block'; up.style.color = '#0d6efd'; }
                if (down) { down.style.display = 'none'; }
            } else if (dir === 'desc') {
                if (up) { up.style.display = 'none'; }
                if (down) { down.style.display = 'block'; down.style.color = '#0d6efd'; }
            }
        }
    }

            thead.querySelectorAll('th.sortable').forEach(th => {
                th.classList.remove('asc', 'desc');
                const indicator = th.querySelector('.sort-indicator');
                if (indicator) indicator.textContent = '';
            });
            if (activeTh) {
                activeTh.classList.add(dir);
                const indicator = activeTh.querySelector('.sort-indicator');
                if (indicator) indicator.textContent = dir === 'asc' ? '▲' : '▼';
            }
        }
        thead.querySelectorAll('th.sortable').forEach(th => {
            th.addEventListener('click', function() {
                const sortKey = th.dataset.sort;
                let dir = 'asc';
                if (currentSort.col === sortKey && currentSort.dir === 'asc') dir = 'desc';
                currentSort = { col: sortKey, dir };
                sortRows(sortKey, dir);
                updateIndicators(th, dir);
                if (window.paginationConfig) window.paginationConfig.currentPage = 1;
                updatePagination();
            });
        });
    }

    // Call on ready and after AJAX reloads
    $(document).ready(function() {
        initSortableHeaders();
    });
    // If you reload the table with AJAX, call initSortableHeaders() after DOM update.
    function reattachEventHandlers() {
        // ...existing handlers...
        initSortableHeaders(); // ensure sorting re-binds after AJAX
    }
    </script>
    <?php include '../../general/footer.php'; ?>

</body>

</html>
