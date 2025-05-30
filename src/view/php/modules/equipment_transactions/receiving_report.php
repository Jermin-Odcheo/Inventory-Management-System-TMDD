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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM receive_report WHERE rr_no = ?");
    $stmt->execute([$rr_no]);
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
            
            // Main audit log for RR deletion
            logAudit(
                $pdo,
                'remove',
                'Receiving Report has been remove',
                'Successful',
                json_encode($oldData),
                null,
                $id
            );
            
            // Additional audit log for affected equipment_details
            if ($affectedEquipment > 0) {
                logAudit(
                    $pdo,
                    'cascade-update',
                    "Updated {$affectedEquipment} equipment_details records to remove reference to deleted RR: {$rrNo}",
                    'Successful',
                    json_encode(['rr_no' => $rrNo, 'affected_records' => $affectedEquipment]),
                    null,
                    $id
                );
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Receiving Report deleted successfully. {$affectedEquipment} equipment records updated.";
        } else {
            $_SESSION['errors'] = ["Receiving Report not found for deletion."];
            logAudit(
                $pdo,
                'remove',
                'Receiving Report has been remove',
                'Failed',
                null,
                null,
                $id
            );
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['errors'] = ["Error deleting Receiving Report: " . $e->getMessage()];
        logAudit(
            $pdo,
            'remove',
            'Receiving Report has been remove',
            'Failed',
            json_encode($oldData),
            null,
            $id
        );
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['errors'] = [$e->getMessage()];
        logAudit(
            $pdo,
            'remove',
            'Receiving Report has been remove',
            'Failed',
            json_encode($oldData),
            null,
            $id
        );
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
            $response['message'] = "Error adding Receiving Report: " . $e->getMessage();

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
            $response['message'] = "Error updating Receiving Report: " . $e->getMessage();
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
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" id="dateFilter" style="width: auto;">
                                <option value="">Filter by Date</option>
                                <option value="desc">Newest to Oldest</option>
                                <option value="asc">Oldest to Newest</option>
                                <option value="mdy">Month-Day-Year Range</option>
                                <option value="month">Month Range</option>
                                <option value="year">Year Range</option>
                                <option value="month_year">Month-Year Range</option>
                            </select>
                            <div id="dateInputsContainer" class="d-flex align-items-center gap-3" style="display: none;">
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
                            <button type="button" id="applyFilters" class="btn btn-dark btn-sm ms-2"><i class="bi bi-funnel"></i> Filter</button>
                            <button type="button" id="clearFilters" class="btn btn-secondary btn-sm ms-1"><i class="bi bi-x-circle"></i> Clear</button>
                        </div>
                        <div class="input-group w-auto">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchReport" class="form-control" placeholder="Search report...">
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
                                            <td><?= date('Y-m-d h:i A', strtotime($rr['date_created'])) ?></td>
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
                                                            data-date_created="<?= htmlspecialchars($rr['date_created']) ?>">
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
                                        <td colspan="8">No Receiving Reports found.</td>
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
                                $('#rrTableBody').html(tableBody || '<tr><td colspan="8">No Receiving Reports found.</td></tr>');
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

            // Show/hide date input containers based on filter type (but do not filter automatically)
            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();
                $('#dateInputsContainer').hide();
                $('#monthPickerContainer').hide();
                $('#dateRangePickers').hide();
                if (filterType === 'month') {
                    $('#dateInputsContainer').show();
                    $('#monthPickerContainer').show();
                } else if (filterType === 'range') {
                    $('#dateInputsContainer').show();
                    $('#dateRangePickers').show();
                }
            });

            // Edit button
            $(document).on('click', '.edit-report', function() {
                const btnData = $(this).data();
                $('#edit_report_id').val(btnData.id);
                // Remove RR/PO prefix when showing in modal (for editing)
                let rrVal = btnData.rr ? btnData.rr.replace(/^RR/, '') : '';
                let poVal = btnData.po || '';
                $('#edit_rr_no').val(rrVal);
                $('#edit_accountable_individual').val(btnData.individual);
                // Set select2 dropdown value and trigger change to update the UI
                $('#edit_po_no').val(poVal).trigger('change');
                $('#edit_ai_loc').val(btnData.location);
                $('#edit_date_created').val(btnData.date_created.replace(' ', 'T').substring(0, 16));
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
                        $('#rrTable').load(location.href + ' #rrTable', function() {
                            showToast(response.message, response.status);
                        });
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
                const data = {
                    action: 'add',
                    rr_no: 'RR' + rr,
                    accountable_individual: $('[name=accountable_individual]').val(),
                    po_no: $('#add_po_no').val(),
                    ai_loc: $('[name=ai_loc]').val(),
                    date_created: $('[name=date_created]').val()
                };
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'POST',
                    dataType: 'json',
                    data,
                    success(res) {
                        if (res.status === 'success') {
                            addModal.hide();
                            $('#rrTable').load(location.href + ' #rrTable', () => showToast(res.message, 'success'));
                        } else showToast(res.message, 'error');
                    },
                    error() {
                        showToast('Error processing request.', 'error');
                    }
                });
            });

            // Edit form
            // Client-side validation and prefix for Edit form
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
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'POST',
                    data: dataObj,
                    dataType: 'json',
                    success(response) {
                        if (response.status === 'success') {
                            editModal.hide();
                            $('#rrTable').load(location.href + ' #rrTable', () => {
                                showToast(response.message, 'success');
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

            // Restrict PO Number input to numbers only for Select2 tags (robust)
            function restrictSelect2ToNumbersOnly(selector) {
                function enforceNumericInput(input) {
                    input.addEventListener('input', function() {
                        this.value = this.value.replace(/[^0-9]/g, '');
                    });
                }
                // When Select2 opens, enforce numeric input
                $(document).on('select2:open', function(e) {
                    if (e.target && e.target.id === selector.replace('#', '')) {
                        // Find the search field
                        var searchField = document.querySelector('.select2-container--open .select2-search__field');
                        if (searchField) {
                            enforceNumericInput(searchField);
                        }
                    }
                });
                // Also enforce on dynamically created search fields
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && node.classList.contains('select2-search__field')) {
                                enforceNumericInput(node);
                            }
                        });
                    });
                });
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
            $('#addReportModal').on('shown.bs.modal', function() {
                restrictSelect2ToNumbersOnly('#add_po_no');
            });
            $('#editReportModal').on('shown.bs.modal', function() {
                restrictSelect2ToNumbersOnly('#edit_po_no');
            });
        });

        // Block non-numeric input for RR and PO fields
        function blockNonNumericInput(selector) {
            $(document).on('keydown', selector, function(e) {
                // Allow: backspace, delete, tab, escape, enter, arrows, home, end
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
                    // Allow: Ctrl/cmd+A
                    (e.keyCode === 65 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: Ctrl/cmd+C
                    (e.keyCode === 67 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: Ctrl/cmd+X
                    (e.keyCode === 88 && (e.ctrlKey === true || e.metaKey === true)) ||
                    // Allow: home, end, left, right
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                    return;
                }
                // Ensure that it is a number and stop the keypress
                if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                    e.preventDefault();
                }
            });
        }

        blockNonNumericInput('#add_rr_no');
        blockNonNumericInput('#add_po_no');
        blockNonNumericInput('#edit_rr_no');
        blockNonNumericInput('#edit_po_no');

        $(document).ready(function() {
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

            $('#applyFilters').off('click').on('click', function() {
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

            $('#clearFilters').off('click').on('click', function() {
                $('#dateFilter').val('');
                $('#dateInputsContainer input').val('');
                $('#dateInputsContainer .date-group').addClass('d-none');
                $('#dateInputsContainer').hide();
                window.location.reload();
            });

            function applyFilter(type, params = {}) {
                let filterData = {
                    action: 'filter',
                    type: type
                };
                if (type === 'mdy') {
                    filterData.dateFrom = params.dateFrom;
                    filterData.dateTo = params.dateTo;
                } else if (type === 'month') {
                    filterData.monthFrom = params.monthFrom;
                    filterData.monthTo = params.monthTo;
                } else if (type === 'year') {
                    filterData.yearFrom = params.yearFrom;
                    filterData.yearTo = params.yearTo;
                } else if (type === 'month_year') {
                    filterData.monthYearFrom = params.monthYearFrom;
                    filterData.monthYearTo = params.monthYearTo;
                }
                $.ajax({
                    url: 'receiving_report.php',
                    method: 'GET',
                    data: filterData,
                    success: function(response) {
                        try {
                            const data = JSON.parse(response);
                            if (data.status === 'success') {
                                let tableBody = '';
                                data.reports.forEach(report => {
                                    tableBody += `
                                <tr>
                                    <td>${report.id}</td>
                                    <td>${report.rr_no}</td>
                                    <td>${report.accountable_individual}</td>
                                    <td>${report.po_no}</td>
                                    <td>${report.ai_loc}</td>
                                    <td>${(() => { const d = new Date(report.date_created); return `${d.getFullYear()}-${(d.getMonth()+1).toString().padStart(2,'0')}-${d.getDate().toString().padStart(2,'0')} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true })}` })()}</td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary edit-report"
                                                    data-id="${report.id}"
                                                    data-rr="${report.rr_no}"
                                                    data-individual="${report.accountable_individual}"
                                                    data-po="${report.po_no}"
                                                    data-location="${report.ai_loc}"
                                                    data-date_created="${report.date_created}">
                                                <i class="bi bi-pencil-square"></i> <span>Edit</span>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger delete-report"
                                                    data-id="${report.id}">
                                                <i class="bi bi-trash"></i> <span>Remove</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                                });
                                $('#rrTable tbody').html(tableBody || '<tr><td colspan="8">No Receiving Reports found.</td></tr>');
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
        });
    </script>
</body>

</html>