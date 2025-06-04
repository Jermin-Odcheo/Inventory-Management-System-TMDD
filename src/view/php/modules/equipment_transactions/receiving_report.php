<?php
require_once '../../../../../config/ims-tmdd.php';
session_start();

// start buffering all output (header/sidebar/footer HTML will be captured)
ob_start();

include '../../general/header.php';

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
$canDelete = $rbac->hasPrivilege('Equipment Transactions', 'Remove');

// Set audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'Receiving Report'");
} else {
    $pdo->exec("SET @current_user_id = NULL");
    $pdo->exec("SET @current_module = NULL");
}

// Set client IP address
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

// Handler for AJAX RR existence check
if (isset($_POST['action']) && $_POST['action'] === 'check_rr_exists' && isset($_POST['rr_no'])) {
    $rr_no = trim($_POST['rr_no']);
    $current_id = isset($_POST['current_id']) ? (int)$_POST['current_id'] : null;
    
    if ($current_id) {
        // For edit form, exclude the current record
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM receive_report WHERE rr_no = ? AND id != ? AND is_disabled = 0");
        $stmt->execute([$rr_no, $current_id]);
    } else {
        // For add form, check all active records
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
        $stmt->execute([$rr_no]);
    }
    
    $exists = $stmt->fetchColumn() > 0;
    header('Content-Type: application/json');
    echo json_encode(['status' => $exists ? 'exists' : 'not_exists']);
    exit;
}

// Auto-insert minimal RR entry if not exists
if (isset($_POST['action']) && $_POST['action'] === 'auto_add_rr' && isset($_POST['rr_no']) && isset($_POST['date_created'])) {
    $rr_no = $_POST['rr_no'];
    $date_created = $_POST['date_created'];

    // Check if already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
    $stmt->execute([$rr_no]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO receive_report (rr_no, date_created, is_disabled) VALUES (?, ?, 0)");
        $result = $stmt->execute([$rr_no, $date_created]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'inserted', 'debug' => 'Inserted RR: ' . $rr_no]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => $pdo->errorInfo()[2], 'debug' => 'Insert failed for RR: ' . $rr_no]);
        }
    } else {
        echo json_encode(['status' => 'exists', 'debug' => 'RR already exists or is_disabled=0: ' . $rr_no]);
    }
    exit;
}

function is_ajax_request()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Fetch active POs for dropdown
$stmtPO = $pdo->prepare("
  SELECT po_no
    FROM purchase_order
   WHERE is_disabled = 0
   ORDER BY po_no
");
$stmtPO->execute();
$poList = $stmtPO->fetchAll(PDO::FETCH_COLUMN);

// Audit helper
function logAudit($pdo, $action, $details, $status, $oldVal, $newVal, $entityId = null)
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, Status, OldVal, NewVal, Date_Time)
        VALUES (?, ?, 'Receiving Report', ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $entityId, $action, $details, $status, $oldVal, $newVal]);
}

// DELETE
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        // Check if user has Remove privilege
        if (!$rbac->hasPrivilege('Equipment Transactions', 'Remove')) {
            throw new Exception('You do not have permission to delete receiving reports');
        }

        $stmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ?");
        $stmt->execute([$id]);
        $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($oldData) {
            $pdo->beginTransaction();
            
            // First get the RR number for updating related records
            $rrNo = $oldData['rr_no'];
            
            // 1. Update equipment_details to clear RR references
            $equipmentStmt = $pdo->prepare("UPDATE equipment_details SET rr_no = NULL WHERE rr_no = ? AND is_disabled = 0");
            $equipmentStmt->execute([$rrNo]);
            $affectedEquipment = $equipmentStmt->rowCount();
            
            // 2. Mark the receive_report as disabled
            $stmt = $pdo->prepare("UPDATE receive_report SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$id]);

            // Log audit entry for deletion
            logAudit(
                $pdo,
                'delete',
                'Receiving Report ' . $rrNo . ' deleted',
                'Successful',
                json_encode($oldData),
                json_encode(['is_disabled' => 1]),
                $id
            );

            $pdo->commit();
            $_SESSION['success'] = "Receiving Report archived successfully. {$affectedEquipment} equipment records updated.";
        } else {
            $_SESSION['errors'] = ["Receiving Report not found for archiving."];
            // No audit log here
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['errors'] = ["Error archiving Receiving Report: " . $e->getMessage()];
        // No audit log here
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['errors'] = [$e->getMessage()];
        // No audit log here
    }

    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        if (!empty($_SESSION['errors'])) {
            echo json_encode(['status' => 'error', 'message' => $_SESSION['errors'][0]]);
        } else {
            echo json_encode(['status' => 'success', 'message' => $_SESSION['success']]);
        }
        exit;
    }

    header("Location: receiving_report.php");
    exit;
}

// ADD / UPDATE
// Modify the validation section in the POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rr_no = trim($_POST['rr_no'] ?? '');
    $accountable_individual = trim($_POST['accountable_individual'] ?? '');
    $po_no = trim($_POST['po_no'] ?? '');
    $ai_loc = trim($_POST['ai_loc'] ?? '');

    // Enforce RR and PO prefixes BEFORE validation
    if ($rr_no !== '' && strpos($rr_no, 'RR') !== 0) {
        $rr_no = 'RR' . $rr_no;
    }
    if ($po_no !== '' && strpos($po_no, 'PO') !== 0) {
        $po_no = 'PO' . $po_no;
    }
    
    // Check if specified PO exists and is not disabled
    if (!empty($po_no)) {
        $poCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE po_no = ? AND is_disabled = 0");
        $poCheckStmt->execute([$po_no]);
        if ($poCheckStmt->fetchColumn() == 0) {
            // PO doesn't exist or is disabled, clear the field
            $po_no = null;
        }
    }
    
    $is_disabled = 0;
    $date_created = trim($_POST['date_created'] ?? '');
    if (empty($date_created)) {
        $date_created = date('Y-m-d H:i:s');
    } else {
        $date_created = date('Y-m-d H:i:s', strtotime($date_created));
    }

    // Enforce the RR prefix first
    if ($rr_no !== '' && strpos($rr_no, 'RR') !== 0) {
        $rr_no = 'RR' . $rr_no;
    }

    // Then validate…
    if ($rr_no === '') {
        $fieldError = 'RR Number is required.';
    } elseif (!preg_match('/^RR\d+$/', $rr_no)) {
        $fieldError = 'RR Number must be like RR123.';
    }

    if ($fieldError) {
        $_SESSION['errors'] = [$fieldError];
        if (is_ajax_request()) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $fieldError]);
            exit;
        }
        header("Location: receiving_report.php");
        exit;
    }


    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $response = ['status' => '', 'message' => ''];
        try {
            // Check if user has Create privilege
            if (!$rbac->hasPrivilege('Equipment Transactions', 'Create')) {
                throw new Exception('You do not have permission to add receiving reports');
            }

            // Check for duplicate RR number before attempting insert
            $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
            $dupCheck->execute([$rr_no]);
            if ($dupCheck->fetchColumn() > 0) {
                throw new Exception("Receiving Report number '{$rr_no}' already exists in the system.");
            }

            $stmt = $pdo->prepare("
                INSERT INTO receive_report
                    (rr_no, accountable_individual, po_no, ai_loc, date_created, is_disabled)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$rr_no, $accountable_individual, $po_no, $ai_loc, $date_created, $is_disabled]);

            // Get the ID of the newly inserted record
            $entityId = $pdo->lastInsertId();

            $_SESSION['success'] = "Receiving Report has been added successfully.";
            $response['status'] = 'success';
            $response['message'] = $_SESSION['success'];

            // Log audit with updated fields
            logAudit(
                $pdo,
                'create', // Changed from 'add' to 'create'
                'New Receiving Report has been Created',
                'Successful',
                null,
                json_encode([
                    'rr_no' => $rr_no,
                    'accountable_individual' => $accountable_individual,
                    'po_no' => $po_no,
                    'ai_loc' => $ai_loc,
                    'date_created' => $date_created
                ]),
                $entityId
            );
        } catch (PDOException $e) {
            $response['status'] = 'error';
            
            // Check if it's a duplicate entry error
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $response['message'] = "Receiving Report number '{$rr_no}' already exists in the system.";
            } else {
                $response['message'] = "Error adding Receiving Report: " . $e->getMessage();
            }

            // Log audit for failed attempt
            logAudit(
                $pdo,
                'create',
                'New Receiving Report has been Created',
                'Failed',
                null,
                json_encode([
                    'rr_no' => $rr_no,
                    'accountable_individual' => $accountable_individual,
                    'po_no' => $po_no,
                    'ai_loc' => $ai_loc,
                    'date_created' => $date_created
                ]),
                null // EntityID is null since the insert failed
            );
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();

            // Log audit for failed attempt
            logAudit(
                $pdo,
                'create',
                'New Receiving Report has been Created',
                'Failed',
                null,
                json_encode([
                    'rr_no' => $rr_no,
                    'accountable_individual' => $accountable_individual,
                    'po_no' => $po_no,
                    'ai_loc' => $ai_loc,
                    'date_created' => $date_created
                ]),
                null
            );
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        $response = ['status' => '', 'message' => ''];
        try {
            // Check if user has Modify privilege
            if (!$rbac->hasPrivilege('Equipment Transactions', 'Modify')) {
                throw new Exception('You do not have permission to modify receiving reports');
            }

            $stmt = $pdo->prepare("SELECT * FROM receive_report WHERE id = ?");
            $stmt->execute([$id]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($oldData) {
                // Check for duplicate RR number if it's being changed
                if ($oldData['rr_no'] !== $rr_no) {
                    $dupCheck = $pdo->prepare("SELECT COUNT(*) FROM receive_report WHERE rr_no = ? AND id != ? AND is_disabled = 0");
                    $dupCheck->execute([$rr_no, $id]);
                    if ($dupCheck->fetchColumn() > 0) {
                        throw new Exception("Receiving Report number '{$rr_no}' already exists in the system.");
                    }
                }
                
                $oldPoNo = $oldData['po_no'] ?? null;
                $newPoNo = $po_no;
                $logAdd = false;
                $logModified = false;

                if ((empty($oldPoNo) || $oldPoNo === null) && !empty($newPoNo)) {
                    // PO Number is being added
                    $logAdd = true;
                } elseif (!empty($oldPoNo) && $oldPoNo !== $newPoNo && !empty($newPoNo)) {
                    // PO Number is being changed
                    $logModified = true;
                } elseif (
                    ($oldData['rr_no'] !== $rr_no) ||
                    ($oldData['accountable_individual'] !== $accountable_individual) ||
                    ($oldData['ai_loc'] !== $ai_loc) ||
                    ($oldData['date_created'] !== $date_created)
                ) {
                    // Other fields changed
                    $logModified = true;
                }

                $stmt = $pdo->prepare("
                    UPDATE receive_report
                    SET rr_no = ?, accountable_individual = ?, po_no = ?, ai_loc = ?, date_created = ?, is_disabled = ?
                    WHERE id = ?
                ");
                $stmt->execute([$rr_no, $accountable_individual, $po_no, $ai_loc, $date_created, $is_disabled, $id]);
                $_SESSION['success'] = "Receiving Report has been updated successfully.";
                $response['status'] = 'success';
                $response['message'] = $_SESSION['success'];

                if ($logAdd) {
                    logAudit(
                        $pdo,
                        'Add',
                        "Po No '{$newPoNo}' has been created",
                        'Successful',
                        json_encode(['id' => $id]),
                        json_encode(['id' => $id, 'po_no' => $newPoNo]),
                        $id
                    );
                } else if ($logModified) {
                    logAudit(
                        $pdo,
                        'modified',
                        'Receiving Report has been Updated',
                        'Successful',
                        json_encode($oldData),
                        json_encode([
                            'rr_no' => $rr_no,
                            'accountable_individual' => $accountable_individual,
                            'po_no' => $po_no,
                            'ai_loc' => $ai_loc,
                            'date_created' => $date_created
                        ]),
                        $id // Use the ID as EntityID
                    );
                }
            } else {
                $response['status'] = 'error';
                $response['message'] = "Receiving Report not found.";
                logAudit(
                    $pdo,
                    'modified',
                    'Receiving Report has been Updated',
                    'Failed',
                    null,
                    null,
                    $id
                );
            }
        } catch (PDOException $e) {
            $response['status'] = 'error';
            
            // Check if it's a duplicate entry error
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $response['message'] = "Receiving Report number '{$rr_no}' already exists in the system.";
            } else {
                $response['message'] = "Error updating Receiving Report: " . $e->getMessage();
            }
            
            logAudit(
                $pdo,
                'modified',
                'Receiving Report has been Updated',
                'Failed',
                json_encode($oldData),
                null,
                $id
            );
        } catch (Exception $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
            logAudit(
                $pdo,
                'modified',
                'Receiving Report has been Updated',
                'Failed',
                json_encode($oldData),
                null,
                $id
            );
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
// LOAD for edit
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

// FETCH ALL
try {
    $stmt = $pdo->query("SELECT * FROM receive_report WHERE is_disabled = 0 ORDER BY id DESC");
    $receivingReports = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Receiving Reports: " . $e->getMessage();
}

// Add the filter code here, BEFORE ob_end_clean()
if (isset($_GET['action']) && $_GET['action'] === 'filter') {
    try {
        $query = "SELECT * FROM receive_report WHERE is_disabled = 0";
        $params = [];
        switch ($_GET['type']) {
            case 'desc':
                $query .= " ORDER BY date_created DESC";
                break;
            case 'asc':
                $query .= " ORDER BY date_created ASC";
                break;
            case 'mdy':
                $query .= " AND date_created BETWEEN ? AND ?";
                $params[] = $_GET['dateFrom'];
                $params[] = $_GET['dateTo'];
                break;
            case 'month':
                $from = $_GET['monthFrom'] . '-01';
                $toMonth = $_GET['monthTo'];
                $to = date('Y-m-t', strtotime($toMonth . '-01'));
                $query .= " AND date_created BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
            case 'year':
                $from = $_GET['yearFrom'] . '-01-01';
                $to = $_GET['yearTo'] . '-12-31';
                $query .= " AND date_created BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
            case 'month_year':
                $from = $_GET['monthYearFrom'] . '-01';
                $toMonth = $_GET['monthYearTo'];
                $to = date('Y-m-t', strtotime($toMonth . '-01'));
                $query .= " AND date_created BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $filteredReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (is_ajax_request()) {
            ob_clean();
            echo json_encode([
                'status' => 'success',
                'reports' => $filteredReports
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

// Add this new endpoint handler near the top of the file, after session_start() but before HTML output
if (isset($_GET['action']) && $_GET['action'] === 'get_data_json') {
    ob_clean(); // Clear any output buffering
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->query("SELECT * FROM receive_report WHERE is_disabled = 0 ORDER BY id DESC");
        $receivingReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $receivingReports]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
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
    <!-- Select2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <!-- Custom Styles -->
    <link href="../../../styles/css/equipment-transactions.css" rel="stylesheet">
    <style>
        .sortable {
            cursor: pointer;
            position: relative;
            user-select: none;
            width: auto;
        }
        .sort-icon {
            margin-left: 5px;
            display: inline-block;
            width: 8px;
            height: 8px;
            position: relative;
        }
        .sortable[data-sort-direction="asc"] .sort-icon::after {
            content: "▲";
            position: absolute;
            font-size: 10px;
            opacity: 0.8;
            top: -5px;
        }
        .sortable[data-sort-direction="desc"] .sort-icon::after {
            content: "▼";
            position: absolute;
            font-size: 10px;
            opacity: 0.8;
            top: -5px;
        }
        /* Fixed width for table columns */
        #rrTable th:nth-child(1) { width: 5%; }  /* # column */
        #rrTable th:nth-child(2) { width: 12%; } /* RR Number */
        #rrTable th:nth-child(3) { width: 20%; } /* Accountable Individual */
        #rrTable th:nth-child(4) { width: 12%; } /* PO Number */
        #rrTable th:nth-child(5) { width: 15%; } /* Location */
        #rrTable th:nth-child(6) { width: 20%; } /* Created Date */
        #rrTable th:nth-child(7) { width: 16%; } /* Actions */
    </style>
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
        
        // Function to check if a selected PO exists and is active
        function checkPOExists(poNo, callback) {
            if (!poNo || poNo === '') {
                callback(true); // Empty PO is valid
                return;
            }
            
            // Make sure it has PO prefix
            if (!poNo.startsWith('PO')) {
                poNo = 'PO' + poNo;
            }
            
            $.ajax({
                url: 'purchase_order.php',
                method: 'GET',
                data: {
                    action: 'check_po_exists',
                    po_no: poNo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'exists') {
                        callback(true);
                    } else {
                        // Show warning that the PO doesn't exist or is removed
                        showToast(`Warning: The PO "${poNo}" doesn't exist or has been removed. It will be cleared when saving.`, 'warning');
                        callback(false);
                    }
                },
                error: function() {
                    // Allow saving even if check fails
                    callback(true);
                }
            });
        }
    </script>
</head>

<body>
    <?php include('../../general/sidebar.php'); ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Management Title -->
            <h2 class="mb-4">Receiving Report Management</h2>
            <!-- End Management Title -->
            <div class="card shadow" style="margin-top: 20px;">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul"></i> List of Receiving Reports</span>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">

                        <?php if ($canCreate): ?>
                            <button type="button" class="btn btn-success btn-sm" id="openAddBtn">
                                <i class="bi bi-plus-circle"></i> Create Receiving Report
                            </button>
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
        <input type="text" id="searchReport" class="form-control form-control-sm" placeholder="Search report...">
    </div>
    <button type="button" id="applyFilters" class="btn btn-dark btn-sm"><i class="bi bi-funnel"></i> Filter</button>
    <button type="button" id="clearFilters" class="btn btn-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</button>
</div>
                    </div>

                    <div class="table-responsive" id="table">
                        <table id="rrTable" class="table table-striped table-bordered table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th class="sortable" data-sort="id">#<span class="sort-icon"></span></th>
                                    <th class="sortable" data-sort="rr_no">RR Number<span class="sort-icon"></span></th>
                                    <th class="sortable" data-sort="accountable_individual">Accountable Individual<span class="sort-icon"></span></th>
                                    <th class="sortable" data-sort="po_no">PO Number<span class="sort-icon"></span></th>
                                    <th class="sortable" data-sort="ai_loc">Location<span class="sort-icon"></span></th>
                                    
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rrTableBody">
                                <?php if (!empty($receivingReports)): ?>
                                    <?php foreach ($receivingReports as $rr): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rr['id']) ?></td>
                                            <td><?= htmlspecialchars($rr['rr_no']) ?></td>
                                            <td><?= htmlspecialchars($rr['accountable_individual']) ?></td>
                                            <td><?= htmlspecialchars($rr['po_no']) ?></td>
                                            <td><?= htmlspecialchars($rr['ai_loc']) ?></td>
                                            
                                            <td class="text-center">
    <div class="btn-group" role="group">
        <?php if ($canModify): ?>
            <button
    class="btn btn-sm btn-outline-primary edit-report"
    data-id="<?= htmlspecialchars($rr['id']) ?>"
    data-rr="<?= htmlspecialchars($rr['rr_no']) ?>"
    data-individual="<?= htmlspecialchars($rr['accountable_individual']) ?>"
    data-po="<?= htmlspecialchars($rr['po_no']) ?>"
    data-location="<?= htmlspecialchars($rr['ai_loc']) ?>"
    data-date-created="<?= htmlspecialchars($rr['date_created']) ?>">
    <i class="bi bi-pencil-square"></i> <span>Edit</span>
</button>

        <?php endif; ?>
        <?php if ($canDelete): ?>
            <button
                class="btn btn-sm btn-outline-danger delete-report"
                data-id="<?= htmlspecialchars($rr['id']) ?>">
                <i class="bi bi-trash"></i> <span>Remove</span>
            </button>
        <?php endif; ?>
    </div>
</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">No Receiving Reports found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Controls (optional) -->
                    <div class="container-fluid">
                        <div class="row align-items-center g-3">
                            <div class="col-12 col-sm-auto">
                                <div class="text-muted">
                                    <?php $totalLogs = count($receivingReports); ?>
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

    <?php if ($canCreate): ?>
        <!-- Add Report Modal -->
        <div class="modal fade" id="addReportModal" tabindex="-1">
            <div class="modal-dialog" style="margin-top:100px;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Receiving Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addReportForm" method="post">
                            <input type="hidden" name="action" value="add">

                            <div class="mb-3">
                                <label class="form-label">RR Number <span class="text-danger">*</span></label>
                                <input type="number" name="rr_no" id="add_rr_no"
                                    class="form-control" required min="0" step="1" title="Numbers only">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Accountable Individual <span class="text-danger">*</span></label>
                                <input type="text" name="accountable_individual"
                                    class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="add_po_no" class="form-label">Purchase Order Number</label>
                                <select class="form-select" name="po_no" id="add_po_no" style="width: 100%;">
                                    <option value="">— None / Select PO —</option>
                                    <?php foreach ($poList as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>">
                                            <?= htmlspecialchars($opt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Location <span class="text-danger">*</span></label>
                                <input type="text" name="ai_loc" class="form-control" required>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    Confirm
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <?php if ($canModify): ?>
        <!-- Edit Report Modal -->
        <div class="modal fade" id="editReportModal" tabindex="-1">
            <div class="modal-dialog" style="margin-top:100px;">
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
                                <label class="form-label">RR Number <span class="text-danger">*</span></label>
                                <input type="number" name="rr_no" id="edit_rr_no" class="form-control" required min="0"
                                    step="1" title="Numbers only">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Accountable Individual <span class="text-danger">*</span></label>
                                <input type="text" name="accountable_individual" id="edit_accountable_individual"
                                    class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">PO Number <span class="text-danger">*</span></label>
                                <select class="form-select" name="po_no" id="edit_po_no" style="width: 100%;">
                                    <option value="">— None / Select PO —</option>
                                    <?php foreach ($poList as $opt): ?>
                                        <option value="<?= htmlspecialchars($opt) ?>">
                                            <?= htmlspecialchars($opt) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Location <span class="text-danger">*</span></label>
                                <input type="text" name="ai_loc" id="edit_ai_loc" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date Created <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="date_created" id="edit_date_created" class="form-control"
                                    required>
                            </div>
                            <div class="mb-3 text-end">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canDelete): ?>
        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteRRModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this Receiving Report?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php include('../../general/footer.php'); ?>
    <script>
        // Instantiate modals
        const addModal = new bootstrap.Modal(document.getElementById('addReportModal'));
        const editModal = new bootstrap.Modal(document.getElementById('editReportModal'));
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteRRModal'));

        // Check if RR number exists (for real-time validation)
        function checkRRExists(rrNo, currentId = null) {
            return new Promise((resolve, reject) => {
                // Don't check empty values
                if (!rrNo) {
                    resolve(false);
                    return;
                }
                
                // Ensure RR prefix
                if (!rrNo.startsWith('RR')) {
                    rrNo = 'RR' + rrNo;
                }
                
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'POST',
                    data: {
                        action: 'check_rr_exists',
                        rr_no: rrNo,
                        current_id: currentId
                    },
                    dataType: 'json',
                    success: function(response) {
                        resolve(response.status === 'exists');
                    },
                    error: function() {
                        reject(new Error('Failed to check RR number'));
                    }
                });
            });
        }

        // Add real-time validation for RR number fields
        $(document).ready(function() {
            // For add form
            $('#add_rr_no').on('blur', async function() {
                const rrNo = $(this).val();
                if (!rrNo) return;
                
                try {
                    const exists = await checkRRExists(rrNo);
                    if (exists) {
                        showToast(`Receiving Report number 'RR${rrNo}' already exists in the system.`, 'warning');
                        $(this).addClass('is-invalid');
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                } catch (err) {
                    console.error('Error checking RR number:', err);
                }
            });
            
            // For edit form
            $('#edit_rr_no').on('blur', async function() {
                const rrNo = $(this).val();
                const currentId = $('#edit_report_id').val();
                if (!rrNo) return;
                
                try {
                    const exists = await checkRRExists(rrNo, currentId);
                    if (exists) {
                        showToast(`Receiving Report number 'RR${rrNo}' already exists in the system.`, 'warning');
                        $(this).addClass('is-invalid');
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                } catch (err) {
                    console.error('Error checking RR number:', err);
                }
            });
        });

        // Prefill Add Report Modal if opened from equipment creation
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('openAddRR') === '1') {
                const rrNo = sessionStorage.getItem('prefill_rr_no') || '';
                const rrDate = sessionStorage.getItem('prefill_rr_date') || '';
                if (rrNo) {
                    $('#add_rr_no').val(rrNo);
                }
                if (rrDate) {
                    $('#date_created').val(rrDate);
                }
                // Leave Accountable Individual, PO Number, and Location blank
                $('input[name="accountable_individual"]').val('');
                $('#add_po_no').val('').trigger('change');
                $('input[name="ai_loc"]').val('');
                // Show the modal
                addModal.show();
                // Clear sessionStorage after use
                sessionStorage.removeItem('prefill_rr_no');
                sessionStorage.removeItem('prefill_rr_date');
            }
        });

        $(function() {
            // Initialize Select2 dropdowns for PO numbers (creatable)
            $('#addReportModal').on('shown.bs.modal', function() {
                $('#add_po_no').select2({
                    tags: true,
                    dropdownParent: $('#addReportModal'),
                    width: '100%',
                    placeholder: 'Type or select PO…',
                    allowClear: true
                });
            });
            $('#addReportModal').on('hidden.bs.modal', function() {
                if ($('#add_po_no').hasClass('select2-hidden-accessible')) {
                    $('#add_po_no').select2('destroy');
                }
                $(this).find('form')[0].reset();
            });
            $('#editReportModal').on('shown.bs.modal', function() {
                $('#edit_po_no').select2({
                    tags: true,
                    dropdownParent: $('#editReportModal'),
                    width: '100%',
                    placeholder: 'Type or select PO…',
                    allowClear: true
                });
            });
            $('#editReportModal').on('hidden.bs.modal', function() {
                if ($('#edit_po_no').hasClass('select2-hidden-accessible')) {
                    $('#edit_po_no').select2('destroy');
                }
                $(this).find('form')[0].reset();
            });

            // Search filter for reports
            $('#searchReport').on('input', function() {
                var searchText = $(this).val().toLowerCase();
                $("#rrTableBody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
                });
            });

            // Filter table
            $('#searchReport, #filterLocation').on('input change', function() {
                const searchText = $('#searchReport').val().toLowerCase();
                const filterLoc = $('#filterLocation').val().toLowerCase();
                $('#rrTableBody tr').each(function() {
                    const row = $(this);
                    const textMatched = row.text().toLowerCase().includes(searchText);
                    const locMatched = !filterLoc || row.find('td:nth-child(5)').text().toLowerCase() === filterLoc;
                    row.toggle(textMatched && locMatched);
                });
            });

            // Open Add modal
            $('#openAddBtn').on('click', () => addModal.show());
            $('#addReportModal').on('hidden.bs.modal', () => $('#addReportForm')[0].reset());

            // Filter by Date logic for Filter/Clear buttons
            function gatherFilterParams() {
                const filterType = $('#dateFilter').val();
                let params = { action: 'filter', type: filterType };
                if (filterType === 'month') {
                    params.month = $('#monthSelect').val();
                    params.year = $('#yearSelect').val();
                } else if (filterType === 'range') {
                    params.dateFrom = $('#dateFrom').val();
                    params.dateTo = $('#dateTo').val();
                }
                return params;
            }

            $('#applyFilters').on('click', function() {
                const filterType = $('#dateFilter').val();
                if (!filterType) return;
                let params = gatherFilterParams();
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'GET',
                    data: params,
                    success: function(response) {
                        try {
                            const data = typeof response === 'string' ? JSON.parse(response) : response;
                            if (data.status === 'success') {
                                // Update table body with filtered results
                                let tableBody = '';
                                if (data.reports && data.reports.length > 0) {
                                    data.reports.forEach(function(rr) {
                                        tableBody += `<tr>
                                            <td>${rr.id}</td>
                                            <td>${rr.rr_no}</td>
                                            <td>${rr.accountable_individual}</td>
                                            <td>${rr.po_no}</td>
                                            <td>${rr.ai_loc}</td>
                                            <td>${rr.date_created}</td>
                                            <td class="text-center">-</td>
                                        </tr>`;
                                    });
                                }
                                $('#rrTableBody').html(tableBody || '<tr><td colspan="6">No Receiving Reports found.</td></tr>');
                            } else {
                                showToast('Error filtering data: ' + data.message, 'error');
                            }
                        } catch (err) {
                            showToast('Error parsing filter response', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error filtering data', 'error');
                    }
                });
            });

            $('#clearFilters').on('click', function() {
                $('#dateFilter').val('');
                // Hide any date containers just in case
                $('#dateInputsContainer').hide();
                $('#monthPickerContainer').hide();
                $('#dateRangePickers').hide();
                // Reload the table (reset to all data)
                window.location.reload();
            });

            // Show/hide date input containers based on filter type (to match Charge Invoice/Purchase Order)
            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();
                const container = $('#dateInputsContainer');
                container.show();
                // Hide all groups first
                container.find('.date-group').addClass('d-none');
                if (!filterType) {
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
                } else {
                    container.hide();
                }
            });

            // Edit button
            $(document).on('click', '.edit-report', function() {
  const btnData = $(this).data();
  $('#edit_report_id').val(btnData.id);

  // Strip "RR" prefix if needed:
  const rrVal = btnData.rr ? btnData.rr.replace(/^RR/, '') : '';
  $('#edit_rr_no').val(rrVal);

  $('#edit_accountable_individual').val(btnData.individual);
  $('#edit_po_no').val(btnData.po || '').trigger('change');
  $('#edit_ai_loc').val(btnData.location);

  // Now use btnData.dateCreated (camelCase). Only call .replace() if it exists:
  if (btnData.dateCreated) {
    // Convert "YYYY-MM-DD HH:MM:SS" → "YYYY-MM-DDTHH:MM"
    const isoStr = btnData.dateCreated.replace(' ', 'T').substring(0, 16);
    $('#edit_date_created').val(isoStr);
  } else {
    $('#edit_date_created').val('');
  }

  editModal.show();
});


            // Delete button
            let deleteId = null;
            $(document).on('click', '.delete-report', function() {
                deleteId = $(this).data('id');
                deleteModal.show();
            });

            // Confirm delete
            $('#confirmDeleteBtn').on('click', function() {
                if (!deleteId) return;
                
                // Store current pagination state
                const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
                const rowsPerPage = $('#rowsPerPageSelect').val();
                
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'GET',
                    data: {
                        action: 'delete',
                        id: deleteId
                    },
                    dataType: 'json',
                    success(response) {
                        deleteModal.hide();
                        
                        if (response.status === 'success') {
                            // Reload only the table content
                            $('#rrTable').load(location.href + ' #rrTable', function() {
                                showToast(response.message, 'success');
                                
                                // Reset pagination with fresh DOM elements
                                window.allRows = Array.from(document.querySelectorAll('#rrTableBody tr'));
                                window.filteredRows = [...window.allRows];
                                
                                // Initialize pagination with the previous page
                                initPagination({
                                    tableId: 'rrTableBody',
                                    currentPage: currentPage,
                                    rowsPerPageSelectId: 'rowsPerPageSelect',
                                    currentPageId: 'currentPage',
                                    rowsPerPageId: 'rowsPerPage',
                                    totalRowsId: 'totalRows',
                                    prevPageId: 'prevPage',
                                    nextPageId: 'nextPage',
                                    paginationId: 'pagination'
                                });
                                
                                // Ensure pagination updates to show the proper rows
                                updatePagination();
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error() {
                        showToast('Error processing request.', 'error');
                    }
                });
            });

            // Add form
            $('#addReportForm').submit(function(e) {
                e.preventDefault();
                const rr = $('#add_rr_no').val();
                if (!/^\d+$/.test(rr)) {
                    showToast('RR must be numbers only', 'error');
                    return;
                }
                
                // Store current pagination state
                const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
                const rowsPerPage = $('#rowsPerPageSelect').val();
                
                const data = {
                    action: 'add',
                    rr_no: 'RR' + rr,
                    accountable_individual: $('[name=accountable_individual]').val(),
                    po_no: $('#add_po_no').val(),
                    ai_loc: $('[name=ai_loc]').val(),
                    date_created: $('[name=date_created]').val()
                };
                
                // Check if PO exists before submitting
                checkPOExists(data.po_no, function(poValid) {
                    // If PO doesn't exist or is disabled, set po_no to null
                    if (!poValid) {
                        data.po_no = '';
                    }
                    
                    $.ajax({
                        url: 'receiving_report.php',
                        method: 'POST',
                        dataType: 'json',
                        data,
                        success(res) {
                            if (res.status === 'success') {
                                addModal.hide();
                                
                                // Reload only the table content
                                $('#rrTable').load(location.href + ' #rrTable', function() {
                                    showToast(res.message, 'success');
                                    
                                    // Reset pagination with fresh DOM elements
                                    window.allRows = Array.from(document.querySelectorAll('#rrTableBody tr'));
                                    window.filteredRows = [...window.allRows];
                                    
                                    // Initialize pagination with the previous page
                                    initPagination({
                                        tableId: 'rrTableBody',
                                        currentPage: currentPage,
                                        rowsPerPageSelectId: 'rowsPerPageSelect',
                                        currentPageId: 'currentPage',
                                        rowsPerPageId: 'rowsPerPage',
                                        totalRowsId: 'totalRows',
                                        prevPageId: 'prevPage',
                                        nextPageId: 'nextPage',
                                        paginationId: 'pagination'
                                    });
                                    
                                    // Set rows per page to previous value
                                    $('#rowsPerPageSelect').val(rowsPerPage).trigger('change');
                                });
                            } else {
                                // If there's a duplicate entry error, show it but keep the modal open
                                if (res.message && res.message.includes('already exists')) {
                                    showToast(res.message, 'error');
                                    // Keep the add modal open so user can fix the RR number
                                } else {
                                    showToast(res.message, 'error');
                                    // Hide the modal for other errors
                                    addModal.hide();
                                }
                            }
                        },
                        error() {
                            showToast('Error processing request.', 'error');
                        }
                    });
                });
            });

            // Edit form
            $('#editReportForm').on('submit', function(e) {
                let rrNo = $('#edit_rr_no').val();
                let valid = true;
                if (!/^\d+$/.test(rrNo)) {
                    showToast('RR Number must contain numbers only.', 'error');
                    valid = false;
                }
                if (!valid) {
                    e.preventDefault();
                    return false;
                }
                
                // Store current pagination state
                const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
                const rowsPerPage = $('#rowsPerPageSelect').val();
                
                // Build data with prefixed RR number
                const formData = $(this).serializeArray();
                let dataObj = {};
                formData.forEach(function(item) {
                    if (item.name === 'rr_no') {
                        dataObj['rr_no'] = 'RR' + rrNo;
                    } else {
                        dataObj[item.name] = item.value;
                    }
                });

                e.preventDefault();
                
                // Check if PO exists before submitting
                checkPOExists(dataObj.po_no, function(poValid) {
                    // If PO doesn't exist or is disabled, set po_no to null
                    if (!poValid) {
                        dataObj.po_no = '';
                    }
                    
                    $.ajax({
                        url: 'receiving_report.php',
                        method: 'POST',
                        data: dataObj,
                        dataType: 'json',
                        success(response) {
                            if (response.status === 'success') {
                                editModal.hide();
                                
                                // Reload only the table content
                                $('#rrTable').load(location.href + ' #rrTable', function() {
                                    showToast(response.message, 'success');
                                    
                                    // Reset pagination with fresh DOM elements
                                    window.allRows = Array.from(document.querySelectorAll('#rrTableBody tr'));
                                    window.filteredRows = [...window.allRows];
                                    
                                    // Initialize pagination with the previous page
                                    initPagination({
                                        tableId: 'rrTableBody',
                                        currentPage: currentPage,
                                        rowsPerPageSelectId: 'rowsPerPageSelect',
                                        currentPageId: 'currentPage',
                                        rowsPerPageId: 'rowsPerPage',
                                        totalRowsId: 'totalRows',
                                        prevPageId: 'prevPage',
                                        nextPageId: 'nextPage',
                                        paginationId: 'pagination'
                                    });
                                    
                                    // Set rows per page to previous value
                                    $('#rowsPerPageSelect').val(rowsPerPage).trigger('change');
                                });
                            } else {
                                // If there's a duplicate entry error, show it but keep the modal open
                                if (response.message && response.message.includes('already exists')) {
                                    showToast(response.message, 'error');
                                    // Keep the edit modal open so user can fix the RR number
                                } else {
                                    showToast(response.message, 'error');
                                    // Hide the modal for other errors
                                    editModal.hide();
                                }
                            }
                        },
                        error() {
                            showToast('Error processing request.', 'error');
                        }
                    });
                });
            });
        });
    </script>

    <script src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js" defer></script>
    <script>
        // Initialize pagination when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize pagination with this table's ID
            initPagination({
                tableId: 'rrTableBody',
                currentPage: 1,
                rowsPerPageSelectId: 'rowsPerPageSelect',
                currentPageId: 'currentPage',
                rowsPerPageId: 'rowsPerPage',
                totalRowsId: 'totalRows',
                prevPageId: 'prevPage',
                nextPageId: 'nextPage',
                paginationId: 'pagination'
            });

            // After initialization, update pagination to apply page numbers
            updatePagination();

            // Create search functionality for the table
            const searchInput = document.getElementById('searchReport');
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

            // Table sorting functionality
            const sortableHeaders = document.querySelectorAll('.sortable');
            let currentSortColumn = null;
            let currentSortDirection = null;

            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.getAttribute('data-sort');
                    const index = [...this.parentElement.children].indexOf(this);

                    // Toggle sort direction or set to 'asc' if this is a new column
                    let direction;
                    if (currentSortColumn === column) {
                        direction = currentSortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        direction = 'asc';
                    }

                    // Reset all other headers
                    sortableHeaders.forEach(h => {
                        h.removeAttribute('data-sort-direction');
                    });

                    // Set active sort on this header
                    this.setAttribute('data-sort-direction', direction);
                    currentSortColumn = column;
                    currentSortDirection = direction;

                    // Sort the rows
                    const tbody = document.getElementById('rrTableBody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    // Sort based on the content of the cells
                    const sortedRows = rows.sort((a, b) => {
                        // Skip if columns don't exist
                        if (!a.cells[index] || !b.cells[index]) return 0;

                        let aValue = a.cells[index].textContent.trim();
                        let bValue = b.cells[index].textContent.trim();

                        // Special handling for dates
                        if (column === 'date_created') {
                            aValue = new Date(aValue).getTime();
                            bValue = new Date(bValue).getTime();
                            // Handle invalid dates
                            if (isNaN(aValue)) aValue = 0;
                            if (isNaN(bValue)) bValue = 0;
                        }
                        // Special handling for numbers
                        else if (column === 'id') {
                            aValue = parseInt(aValue) || 0;
                            bValue = parseInt(bValue) || 0;
                        }
                        // For regular string comparison
                        else {
                            aValue = aValue.toLowerCase();
                            bValue = bValue.toLowerCase();
                        }

                        if (direction === 'asc') {
                            return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
                        } else {
                            return aValue < bValue ? 1 : aValue > bValue ? -1 : 0;
                        }
                    });

                    // Update the DOM
                    sortedRows.forEach(row => tbody.appendChild(row));

                    // Update pagination with the new order
                    window.allRows = Array.from(document.querySelectorAll('#rrTableBody tr'));
                    window.filteredRows = [...window.allRows];

                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = 1;
                    }
                    updatePagination();
                });
            });
        });
    </script>
</body>

</html>
