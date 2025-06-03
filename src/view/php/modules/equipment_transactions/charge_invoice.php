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
 * @param string $action    e.g. 'create', 'modified', 'remove', 'delete'
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

            // ──────────────────────────── DUPLICATE CHECK ────────────────────────────
            $dupCheck = $pdo->prepare("
                SELECT COUNT(*) 
                  FROM charge_invoice 
                 WHERE invoice_no = ? 
                   AND is_disabled = 0
            ");
            $dupCheck->execute([$invoice_no]);
            if ($dupCheck->fetchColumn() > 0) {
                // Friendly error if invoice_no already exists (and is not disabled)
                throw new Exception("Invoice number '{$invoice_no}' already exists.");
            }
            // ─────────────────────────────────────────────────────────────────────────

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
        } catch (PDOException $e) {
            // Check if it's a duplicate entry error
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $_SESSION['errors'] = ["Invoice number '{$invoice_no}' already exists."];
            } else {
                $_SESSION['errors'] = ["Database error: " . $e->getMessage()];
            }
        } catch (Exception $e) {
            $_SESSION['errors'] = ["{$e->getMessage()}"];
        }
    }

    // UPDATE
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

            // Check for duplicate invoice number if it's changed
            if ($old['invoice_no'] !== $invoice_no) {
                $dupCheck = $pdo->prepare("
                    SELECT COUNT(*) 
                      FROM charge_invoice 
                     WHERE invoice_no = ? 
                       AND id != ?
                       AND is_disabled = 0
                ");
                $dupCheck->execute([$invoice_no, $id]);
                if ($dupCheck->fetchColumn() > 0) {
                    throw new Exception("Invoice number '{$invoice_no}' already exists.");
                }
            }

            // run update...
            $upd = $pdo->prepare("
              UPDATE charge_invoice
                 SET invoice_no       = ?,
                     date_of_purchase = ?,
                     po_no            = ?
               WHERE id = ? 
                 AND is_disabled = 0
            ");
            $upd->execute([$invoice_no, $date_of_purchase, $po_no, $id]);

            if ($upd->rowCount() > 0) {
                $oldPoNo = $old['po_no'] ?? null;
                $newPoNo = $po_no;
                $logAdd = false;
                $logModified = false;
                $updatedEquipmentCount = 0;

                if ((empty($oldPoNo) || $oldPoNo === null) && !empty($newPoNo)) {
                    // PO Number is being added
                    $logAdd = true;
                } elseif (!empty($oldPoNo) && $oldPoNo !== $newPoNo && !empty($newPoNo)) {
                    // PO Number is being changed
                    $logModified = true;
                } elseif (
                    ($old['invoice_no'] !== $invoice_no) ||
                    ($old['date_of_purchase'] !== $date_of_purchase) ||
                    ($oldPoNo !== $newPoNo)
                ) {
                    // Other fields changed (or PO number changed)
                    $logModified = true;
                }

                // If date_of_purchase was changed, update related equipment_details
                if ($old['date_of_purchase'] !== $date_of_purchase) {
                    try {
                        // First, get all RR numbers associated with this purchase order
                        $rrStmt = $pdo->prepare("
                            SELECT rr_no 
                            FROM receive_report 
                            WHERE po_no = ? 
                            AND is_disabled = 0
                        ");
                        $rrStmt->execute([$po_no]);
                        $relatedRRs = $rrStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        // If we found related RRs, update equipment_details
                        if (!empty($relatedRRs)) {
                            $placeholders = str_repeat('?,', count($relatedRRs) - 1) . '?';
                            
                            // Update all equipment_details records that reference these RR numbers
                            $updateStmt = $pdo->prepare("
                                UPDATE equipment_details 
                                SET date_acquired = ?, date_modified = NOW() 
                                WHERE rr_no IN ($placeholders) 
                                AND is_disabled = 0
                            ");
                            
                            // First parameter is the new date_of_purchase, followed by all RR numbers
                            $params = array_merge([$date_of_purchase], $relatedRRs);
                            $updateStmt->execute($params);
                            
                            $updatedRows = $updateStmt->rowCount();
                            if ($updatedRows > 0) {
                                // Store the number of updated equipment records to include in the response
                                $updatedEquipmentCount = $updatedRows;
                                
                                // Log the cascade update in the audit_log
                                logAudit(
                                    $pdo,
                                    'Modified',
                                    json_encode([
                                        'action' => 'cascade_update',
                                        'from_charge_invoice_id' => $id,
                                        'old_date' => $old['date_of_purchase']
                                    ]),
                                    json_encode([
                                        'action' => 'cascade_update',
                                        'from_charge_invoice_id' => $id,
                                        'new_date' => $date_of_purchase,
                                        'updated_equipment_count' => $updatedRows
                                    ]),
                                    $id,
                                    "Cascaded date update to $updatedRows equipment records",
                                    'Successful'
                                );
                            }
                        }
                    } catch (Exception $e) {
                        // Log error but don't stop the main transaction
                        error_log("Error updating equipment_details dates: " . $e->getMessage());
                    }
                }

                if ($logAdd) {
                    logAudit(
                        $pdo,
                        'Add',
                        json_encode(['id' => $id]),
                        json_encode(['id' => $id, 'po_no' => $newPoNo]),
                        $id,
                        "Po No '{$newPoNo}' has been created",
                        'Successful'
                    );
                } else if ($logModified) {
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
                }

                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'Charge Invoice updated successfully.', 'updated_equipment_count' => $updatedEquipmentCount]);
                exit;
            }
            throw new Exception('No changes made or record not found.');
        } catch (PDOException $e) {
            // Check if it's a duplicate entry error
            if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => "Invoice number '{$invoice_no}' already exists."]);
                exit;
            }
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        } catch (Exception $e) {
            // Could also logAudit(..., 'Failed') here if desired
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    // 5) Final AJAX / redirect response
    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        $resp = [
            'status' => 'success',
            'message' => $_SESSION['success'] ?? 'Operation completed successfully'
        ];
        if (!empty($_SESSION['errors'])) {
            $resp = [
                'status' => 'error',
                'message' => $_SESSION['errors'][0]
            ];
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
if (($_GET['action'] ?? '') === 'removed' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        if (!$canDelete) {
            throw new Exception('No permission to remove invoices.');
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
                'remove',
                json_encode($old),
                null,
                $id,
                "Charge Invoice {$old['invoice_no']} removed",
                'Successful'
            );

            $_SESSION['success'] = "Charge Invoice Removed successfully.";
        } else {
            $_SESSION['errors'] = ["Charge Invoice not found for deletion."];
        }
    } catch (Exception $e) {
        $_SESSION['errors'] = [$e->getMessage()];
    }
    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        $resp = [
            'status' => 'success',
            'message' => $_SESSION['success'] ?? 'Done'
        ];
        if (!empty($_SESSION['errors'])) {
            $resp = [
                'status' => 'error',
                'message' => $_SESSION['errors'][0]
            ];
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
// LIST ALL (including invoices without a PO)
// ------------------------
try {
    $stmt = $pdo->prepare("
        SELECT 
            ci.*,
            po.date_of_order,
            po.no_of_units,
            po.item_specifications
        FROM charge_invoice AS ci
        LEFT JOIN purchase_order AS po
            ON ci.po_no = po.po_no
        WHERE
            ci.is_disabled = 0
          AND (
                ci.po_no      IS NULL      -- no PO selected
             OR ci.po_no      = ''        -- empty string
             OR po.is_disabled = 0        -- PO exists and is not disabled
          )
        ORDER BY ci.id DESC
    ");
    $stmt->execute();
    $chargeInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving Charge Invoices: " . $e->getMessage();
}

// ------------------------
// FILTER CHARGE INVOICES (AJAX)
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'filter') {
    try {
        $query = "
            SELECT 
                ci.*,
                po.date_of_order,
                po.no_of_units,
                po.item_specifications
            FROM charge_invoice AS ci
            LEFT JOIN purchase_order AS po
                ON ci.po_no = po.po_no
            WHERE ci.is_disabled = 0
              AND (
                    ci.po_no IS NULL
                 OR ci.po_no = ''
                 OR po.is_disabled = 0
              )
        ";
        $params = [];
        switch ($_GET['type'] ?? '') {
            case 'desc':
                $query .= " ORDER BY ci.date_of_purchase DESC";
                break;
            case 'asc':
                $query .= " ORDER BY ci.date_of_purchase ASC";
                break;
            case 'mdy':
                $query .= " AND ci.date_of_purchase BETWEEN ? AND ?";
                $params[] = $_GET['dateFrom'];
                $params[] = $_GET['dateTo'];
                break;
            case 'month':
                $from = $_GET['monthFrom'] . '-01';
                $toMonth = $_GET['monthTo'];
                $to = date('Y-m-t', strtotime($toMonth . '-01'));
                $query .= " AND ci.date_of_purchase BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
            case 'year':
                $from = $_GET['yearFrom'] . '-01-01';
                $to = $_GET['yearTo'] . '-12-31';
                $query .= " AND ci.date_of_purchase BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
            case 'month_year':
                $from = $_GET['monthYearFrom'] . '-01';
                $toMonth = $_GET['monthYearTo'];
                $to = date('Y-m-t', strtotime($toMonth . '-01'));
                $query .= " AND ci.date_of_purchase BETWEEN ? AND ?";
                $params[] = $from;
                $params[] = $to;
                break;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $filteredInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (is_ajax_request()) {
            ob_clean();
            echo json_encode([
                'status' => 'success',
                'invoices' => $filteredInvoices
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
    <title>Charge Invoice Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="../../../styles/css/equipment-transactions.css" rel="stylesheet">

    <!-- ────────────────────────────────────────────────────────────────────────────── -->
    <!-- COPY-PASTE OF SORTABLE CSS FROM receiving_report.php:                                   -->
    <style>
        /* …Exact same ".sortable" & ".sort-icon" rules from receiving_report.php… */

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
        
        /* Custom darker modal backdrop */
        .modal-backdrop {
            --bs-backdrop-opacity: 0.7 !important; /* Increase from default 0.5 */
            background-color: #000;
        }
        
        /* Optional: Add a subtle shadow to modal content for better visibility */
        .modal-content {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }
    </style>

    <!-- jQuery (required for Select2 + AJAX, already used below) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Pagination.js (as before) -->
    <script src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js"></script>

</head>

<body>
<?php include('../../general/sidebar.php'); ?>
<div class="main-content">
    <!-- The page now displays notifications only via toast messages -->

    <h2 class="mb-4">Charge Invoice Management</h2>

    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul"></i> List of Charge Invoices</span>
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
                        <input type="text" id="searchInvoice" class="form-control form-control-sm" placeholder="Search invoice...">
                    </div>
                    <button type="button" id="applyFilters" class="btn btn-dark btn-sm"><i class="bi bi-funnel"></i> Filter</button>
                    <button type="button" id="clearFilters" class="btn btn-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</button>
                </div>
            </div>

            <div class="table-responsive" id="table">
                <table id="invoiceTable" class="table table-striped table-bordered table-hover">
                    <thead class="table-dark">
                    <tr>
                        <!-- Added class="sortable" and data-sort attributes, plus <span class="sort-icon"></span> -->
                        <th class="sortable" data-sort="id">#<span class="sort-icon"></span></th>
                        <th class="sortable" data-sort="invoice_no">Invoice Number<span class="sort-icon"></span></th>
                        <th class="sortable" data-sort="date_of_purchase">Purchase Date<span class="sort-icon"></span></th>
                        <th class="sortable" data-sort="po_no">PO Number<span class="sort-icon"></span></th>
                        <th class="sortable" data-sort="date_created">Created Date<span class="sort-icon"></span></th>
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
                                <td><?php echo date('Y-m-d h:i A', strtotime($invoice['date_created'] ?? '')); ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <?php if ($canModify): ?>
                                            <a class="btn btn-sm btn-outline-primary edit-invoice"
                                               data-id="<?php echo htmlspecialchars($invoice['id']); ?>"
                                               data-invoice="<?php echo htmlspecialchars($invoice['invoice_no'] ?? ''); ?>"
                                               data-date="<?php echo htmlspecialchars($invoice['date_of_purchase'] ?? ''); ?>"
                                               data-po="<?php echo htmlspecialchars($invoice['po_no'] ?? ''); ?>">
                                                <i class="bi bi-pencil-square"></i> <span>Edit</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <a class="btn btn-sm btn-outline-danger delete-invoice"
                                               data-id="<?php echo htmlspecialchars($invoice['id']); ?>"
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
                                <?php $totalLogs = count($chargeInvoices); ?>
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

<?php if ($canCreate): ?>
    <!-- Add Invoice Modal -->
    <div class="modal fade" id="addInvoiceModal" tabindex="-1">
        <div class="modal-dialog" style="margin-top:100px;">
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
                            <select class="form-select" name="po_no" id="add_po_no">
                                <option value="">— None —</option>
                                <?php foreach ($poList as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" style="margin-right: 4px;" data-bs-dismiss="modal">Cancel</button>
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
        <div class="modal-dialog" style="margin-top:100px;">
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
                            <label for="po_no" class="form-label">Purchase Order Number</label>
                            <select class="form-select" name="po_no" id="edit_po_no">
                                <option value="">— None —</option>
                                <?php foreach ($poList as $opt): ?>
                                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
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
<!-- Footer for notifications -->
<?php include('../../general/footer.php'); ?>
<!-- ────────────────────────────────────────────────────────────────────────────── -->
<!-- JAVASCRIPT (including sorting logic) -->
<script>
    // ───────────── COMMON FUNCTIONS ────────────────────────────────────────────────
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

    // Expose privileges to JS
    var canModify = <?php echo json_encode($canModify); ?>;
    var canDelete = <?php echo json_encode($canDelete); ?>;

    // ───────────── SORTING LOGIC ────────────────────────────────────────────────
    function attachSortingHandlers() {
        // 1) Remove any existing listeners by replacing each header
        document.querySelectorAll('.sortable').forEach(header => {
            header.replaceWith(header.cloneNode(true));
        });

        // 2) Re-select the fresh headers and attach click listeners
        let currentSortColumn = null;
        let currentSortDirection = null;
        const freshHeaders = document.querySelectorAll('.sortable');

        freshHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                const index = Array.from(this.parentElement.children).indexOf(this);

                // Toggle or default to ascending
                let direction;
                if (currentSortColumn === column) {
                    direction = (currentSortDirection === 'asc') ? 'desc' : 'asc';
                } else {
                    direction = 'asc';
                }

                // Clear other headers' sort-direction
                freshHeaders.forEach(h => h.removeAttribute('data-sort-direction'));

                // Mark this header as active
                this.setAttribute('data-sort-direction', direction);
                currentSortColumn = column;
                currentSortDirection = direction;

                // Sort the rows
                const tbody = document.getElementById('invoiceTable').querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                const sortedRows = rows.sort((a, b) => {
                    // If cells don't exist, treat as equal
                    if (!a.cells[index] || !b.cells[index]) return 0;

                    let aValue = a.cells[index].textContent.trim();
                    let bValue = b.cells[index].textContent.trim();

                    // Date column special handling
                    if (column === 'date_of_purchase' || column === 'date_created') {
                        let aDate = new Date(aValue.replace(' ', 'T'));
                        let bDate = new Date(bValue.replace(' ', 'T'));
                        aValue = isNaN(aDate.getTime()) ? 0 : aDate.getTime();
                        bValue = isNaN(bDate.getTime()) ? 0 : bDate.getTime();
                    }
                    // ID column special handling
                    else if (column === 'id') {
                        aValue = parseInt(aValue) || 0;
                        bValue = parseInt(bValue) || 0;
                    }
                    // Otherwise, string compare
                    else {
                        aValue = aValue.toLowerCase();
                        bValue = bValue.toLowerCase();
                    }

                    if (direction === 'asc') {
                        return aValue > bValue ? 1 : (aValue < bValue ? -1 : 0);
                    } else {
                        return aValue < bValue ? 1 : (aValue > bValue ? -1 : 0);
                    }
                });

                // Re-append sorted rows
                sortedRows.forEach(row => tbody.appendChild(row));

                // Reinitialize pagination arrays and go back to page 1
                window.allRows = Array.from(document.querySelectorAll('#invoiceTable tbody tr'));
                window.filteredRows = [...window.allRows];
                if (window.paginationConfig) {
                    window.paginationConfig.currentPage = 1;
                }
                updatePagination();
            });
        });
    }
</script>

<script>
    // Instantiate modals
    const addModal = new bootstrap.Modal(document.getElementById('addInvoiceModal'));
    const editModal = new bootstrap.Modal(document.getElementById('editInvoiceModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteInvoiceModal'));

    let deleteInvoiceId = null;

    $(document).ready(function() {
        // ─────────── SETUP Select2 ───────────────────────────────────────────────
        $('#add_po_no').select2({
            dropdownParent: $('#addInvoiceModal'),
            width: '100%',
            placeholder: 'Type or select PO…',
            allowClear: true
        });
        $('#edit_po_no').select2({
            dropdownParent: $('#editInvoiceModal'),
            width: '100%',
            placeholder: 'Type or select PO…',
            allowClear: true
        });

        // Always clean up modal backdrop and body class after modal is hidden
        $('#addInvoiceModal').on('hidden.bs.modal', function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('overflow', '');
            $('body').css('padding-right', '');
        });
        $('#editInvoiceModal').on('hidden.bs.modal', function() {
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

        // ─────────── SEARCH FILTER ─────────────────────────────────────────────────
        $('#searchInvoice').on('input', function() {
            var searchText = $(this).val().toLowerCase();

            // Filter rows based on search text and update pagination
            window.filteredRows = window.allRows.filter(row => {
                return row.textContent.toLowerCase().indexOf(searchText) > -1;
            });

            // Reset to first page and update pagination
            if (window.paginationConfig) {
                window.paginationConfig.currentPage = 1;
            }
            updatePagination();
        });

        // ─────────── INITIALIZE PAGINATION ─────────────────────────────────────────
        // Store all table rows in a global variable for filtering/sorting
        window.allRows = Array.from(document.querySelectorAll('#invoiceTable tbody tr'));
        window.filteredRows = [...window.allRows];

        try {
            initPagination({
                tableId: 'invoiceTable tbody',
                currentPage: 1,
                rowsPerPageSelectId: 'rowsPerPageSelect',
                currentPageId: 'currentPage',
                rowsPerPageId: 'rowsPerPage',
                totalRowsId: 'totalRows',
                prevPageId: 'prevPage',
                nextPageId: 'nextPage',
                paginationId: 'pagination'
            });
            updatePagination();
        } catch (e) {
            console.error("Error initializing pagination:", e);
            manualInitPagination();
        }

        // ─────────── Attach sorting handlers on initial load ──────────────────────
        attachSortingHandlers();

        // ─────────── SORTABLE HEADER LOGIC ─────────────────────────────────────────
        // (All logic now lives inside attachSortingHandlers())

        // ─────────── PAGINATION BUTTON HANDLERS ───────────────────────────────────
        const prevPageBtn = document.getElementById('prevPage');
        const nextPageBtn = document.getElementById('nextPage');

        if (prevPageBtn && nextPageBtn) {
            $('#prevPage').off('click').on('click', function(e) {
                e.preventDefault();
                if (!window.paginationConfig) {
                    directPrevPage();
                    return false;
                }
                if (window.paginationConfig.currentPage > 1) {
                    window.paginationConfig.currentPage--;
                    updatePagination();
                }
                return false;
            });
            $('#nextPage').off('click').on('click', function(e) {
                e.preventDefault();
                if (!window.paginationConfig) {
                    directNextPage();
                    return false;
                }
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
                const maxPage = Math.ceil(window.filteredRows.length / rowsPerPage);
                if (window.paginationConfig.currentPage < maxPage) {
                    window.paginationConfig.currentPage++;
                    updatePagination();
                }
                return false;
            });
        }

        // ─────────── PAGE NUMBER CLICK HANDLER ─────────────────────────────────────
        $(document).on('click', '.page-link', function(e) {
            e.preventDefault();
            const pageNumber = $(this).text();
            if (pageNumber === '…' || $(this).parent().hasClass('active')) {
                return false;
            }
            if (window.paginationConfig) {
                window.paginationConfig.currentPage = parseInt(pageNumber);
                updatePagination();
            }
            return false;
        });

        // Handle rows per page change
        $('#rowsPerPageSelect').on('change', function() {
            if (window.paginationConfig) {
                window.paginationConfig.currentPage = 1;
            }
            updatePagination();
        });

        // ─────────── REINITIALIZE PAGINATION (AFTER AJAX) ─────────────────────────
        function reinitializePagination() {
            // Get fresh rows
            window.allRows = Array.from(document.querySelectorAll('#invoiceTable tbody tr'));

            // Apply any active search filter
            const searchText = $('#searchInvoice').val().toLowerCase();
            if (searchText) {
                window.filteredRows = window.allRows.filter(row => {
                    return row.textContent.toLowerCase().indexOf(searchText) > -1;
                });
            } else {
                window.filteredRows = [...window.allRows];
            }

            if (window.paginationConfig) {
                window.paginationConfig.currentPage = 1;
            }
            updatePagination();
        }

        // ─────────── DIRECT FALLBACK PAGINATION (IN CASE pagination.js FAILS) ────────
        function directPrevPage() {
            const allRows = $('#invoiceTable tbody tr').toArray();
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);

            let visibleRows = allRows.filter(row => $(row).is(':visible'));
            let hiddenRows = allRows.filter(row => !$(row).is(':visible'));

            if (hiddenRows.length > 0) {
                const startIndex = Math.max(0, allRows.indexOf(visibleRows[0]) - rowsPerPage);
                $(allRows).hide();
                for (let i = startIndex; i < startIndex + rowsPerPage && i < allRows.length; i++) {
                    $(allRows[i]).show();
                }
                const total = allRows.length;
                $('#currentPage').text(startIndex + 1);
                $('#rowsPerPage').text(Math.min(startIndex + rowsPerPage, total));
                $('#totalRows').text(total);
                $('#prevPage').prop('disabled', startIndex <= 0);
                $('#nextPage').prop('disabled', startIndex + rowsPerPage >= total);
            }
        }

        function directNextPage() {
            const allRows = $('#invoiceTable tbody tr').toArray();
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            let visibleRows = allRows.filter(row => $(row).is(':visible'));
            let lastVisibleIndex = allRows.indexOf(visibleRows[visibleRows.length - 1]);

            if (lastVisibleIndex < allRows.length - 1) {
                const startIndex = lastVisibleIndex + 1;
                $(allRows).hide();
                for (let i = startIndex; i < startIndex + rowsPerPage && i < allRows.length; i++) {
                    $(allRows[i]).show();
                }
                const total = allRows.length;
                $('#currentPage').text(startIndex + 1);
                $('#rowsPerPage').text(Math.min(startIndex + rowsPerPage, total));
                $('#totalRows').text(total);
                $('#prevPage').prop('disabled', startIndex <= 0);
                $('#nextPage').prop('disabled', startIndex + rowsPerPage >= total);
            }
        }

        function manualInitPagination() {
            console.warn("Using manual pagination implementation");
            const allRows = $('#invoiceTable tbody tr').toArray();
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const total = allRows.length;

            $(allRows).hide();
            for (let i = 0; i < Math.min(rowsPerPage, total); i++) {
                $(allRows[i]).show();
            }

            $('#currentPage').text(1);
            $('#rowsPerPage').text(Math.min(rowsPerPage, total));
            $('#totalRows').text(total);
            $('#prevPage').prop('disabled', true);
            $('#nextPage').prop('disabled', rowsPerPage >= total);

            $('#rowsPerPageSelect').on('change', function() {
                const newRowsPerPage = parseInt($(this).val());
                $(allRows).hide();
                for (let i = 0; i < Math.min(newRowsPerPage, total); i++) {
                    $(allRows[i]).show();
                }
                $('#currentPage').text(1);
                $('#rowsPerPage').text(Math.min(newRowsPerPage, total));
                $('#totalRows').text(total);
                $('#prevPage').prop('disabled', true);
                $('#nextPage').prop('disabled', newRowsPerPage >= total);
            });
        }

        // ─────────── EVENT HANDLERS FOR ADD / EDIT / DELETE VIA AJAX ─────────────────
        function attachRowEventHandlers() {
            // Attach edit button handlers
            $('.edit-invoice').off('click').on('click', function() {
                const id = $(this).data('id');
                const invoice = $(this).data('invoice') || '';
                const date = $(this).data('date') || '';
                const po = $(this).data('po') || '';

                // Fill inputs in the edit modal
                $('#edit_invoice_id').val(id);
                $('#edit_invoice_no').val(invoice.replace(/^CI/, '')); // strip CI
                $('#edit_date_of_purchase').val(date);
                $('#edit_po_no').val(po).trigger('change');

                // Show the modal
                const modalEl = document.getElementById('editInvoiceModal');
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });

            // Attach delete button handlers
            $('.delete-invoice').off('click').on('click', function(e) {
                e.preventDefault();
                deleteInvoiceId = $(this).data('id');
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteInvoiceModal'));
                deleteModal.show();
            });
        }
        attachRowEventHandlers();

        // CONFIRM DELETE via AJAX
        $('#confirmDeleteInvoiceBtn').on('click', function() {
            if (typeof deleteInvoiceId === 'undefined') return;
            const currentPageBeforeDelete = window.paginationConfig ? window.paginationConfig.currentPage : 1;

            var deleteModalEl = document.getElementById('deleteInvoiceModal');
            var deleteModalInstance = bootstrap.Modal.getInstance(deleteModalEl);
            if (deleteModalInstance) {
                deleteModalInstance.hide();
            }
            setTimeout(function() {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
                $('body').css('padding-right', '');
            }, 100);

            $.ajax({
                url: 'charge_invoice.php',
                method: 'GET',
                data: {
                    action: 'removed',
                    id: deleteInvoiceId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#invoiceTable').load(location.href + ' #invoiceTable', function() {
                            showToast(response.message, 'success');
                            if (window.paginationConfig) {
                                window.paginationConfig.currentPage = currentPageBeforeDelete;
                            }
                            reinitializePagination();
                            attachRowEventHandlers();
                            attachSortingHandlers();
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('overflow', '');
                    $('body').css('padding-right', '');
                },
                error: function() {
                    showToast('Error processing request.', 'error');
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('overflow', '');
                    $('body').css('padding-right', '');
                }
            });
        });

        // ADD INVOICE via AJAX
        $('#addInvoiceForm').on('submit', function(e) {
            let invoiceNo = $(this).find('input[name="invoice_no"]').val();
            let valid = /^\d+$/.test(invoiceNo);
            if (!valid) {
                showToast('Invoice Number must contain numbers only.', 'error');
                e.preventDefault();
                return false;
            }

            const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
            const rowsPerPage = $('#rowsPerPageSelect').val();

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
                            window.paginationConfig.currentPage = 1;
                            reinitializePagination();
                            attachRowEventHandlers();
                            attachSortingHandlers();
                        });
                        var addModalEl = document.getElementById('addInvoiceModal');
                        var addModal = bootstrap.Modal.getOrCreateInstance(addModalEl);
                        if (addModal) {
                            addModal.hide();
                        }
                        $('#addInvoiceForm')[0].reset();
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    showToast('Error processing request. Please try again.', 'error');
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('overflow', '');
                    $('body').css('padding-right', '');
                }
            });
        });

        // EDIT INVOICE via AJAX
        $('#editInvoiceForm').on('submit', function(e) {
            let invoiceNo = $(this).find('input[name="invoice_no"]').val();
            let valid = /^\d+$/.test(invoiceNo);
            if (!valid) {
                showToast('Invoice Number must contain numbers only.', 'error');
                e.preventDefault();
                return false;
            }

            const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
            const rowsPerPage = $('#rowsPerPageSelect').val();

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
                            reinitializePagination();
                            attachRowEventHandlers();
                            attachSortingHandlers();
                        });

                        var editModalEl = document.getElementById('editInvoiceModal');
                        var editModal = bootstrap.Modal.getInstance(editModalEl);
                        if (editModal) {
                            editModal.hide();
                        }
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('overflow', '');
                        $('body').css('padding-right', '');
                        
                        // If equipment records were updated, show an additional notification
                        if (response.updated_equipment_count && response.updated_equipment_count > 0) {
                            setTimeout(function() {
                                showToast(`Updated date_acquired for ${response.updated_equipment_count} equipment record(s)`, 'info');
                            }, 500); // Small delay for better UX
                        }
                    } else {
                        showToast(response.message || 'Error updating invoice', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    showToast('Error processing request. Please try again.', 'error');
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('overflow', '');
                    $('body').css('padding-right', '');
                }
            });
        });

        // ─────────── DATE FILTER UI HANDLING ───────────────────────────────────────
        $('#dateFilter').on('change', function() {
            const filterType = $(this).val();
            const container = $('#dateInputsContainer');
            container.show();
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

        // ─────────── CLEAR FILTERS ─────────────────────────────────────────────────
        $('#clearFilters').off('click').on('click', function(e) {
            e.preventDefault();
            $('#dateFilter').val('');
            $('#dateInputsContainer').hide().find('input').val('');
            $('#dateInputsContainer .date-group').addClass('d-none');
            $('#searchInvoice').val('');
            $.ajax({
                url: 'charge_invoice.php',
                method: 'GET',
                data: {},
                success: function(response) {
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = response;
                    var newTbody = $(tempDiv).find('#invoiceTable tbody').html();
                    if (newTbody) {
                        $('#invoiceTable tbody').html(newTbody);
                    }
                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = 1;
                    }
                    reinitializePagination();
                    attachRowEventHandlers();
                    attachSortingHandlers();
                    showToast('Filters cleared.', 'success');
                },
                error: function() {
                    showToast('Error clearing filters.', 'error');
                }
            });
        });

        // ─────────── APPLY DATE FILTERS ─────────────────────────────────────────────
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

            let filterData = {
                action: 'filter',
                type: filterType
            };
            Object.keys(params).forEach(key => {
                filterData[key] = params[key];
            });

            $.ajax({
                url: 'charge_invoice.php',
                method: 'GET',
                data: filterData,
                success: function(response) {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.status === 'success') {
                            let tableBody = '';
                            data.invoices.forEach(invoice => {
                                let formattedDate = '';
                                if (invoice.date_created) {
                                    formattedDate = formatDateAMPM(invoice.date_created);
                                }
                                tableBody += `
                                <tr>
                                    <td>${invoice.id}</td>
                                    <td>${invoice.invoice_no || ''}</td>
                                    <td>${invoice.date_of_purchase || ''}</td>
                                    <td>${invoice.po_no || ''}</td>
                                    <td>${formattedDate}</td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            ${canModify ? `
                                            <a class="btn btn-sm btn-outline-primary edit-invoice"
                                                data-id="${invoice.id}"
                                                data-invoice="${invoice.invoice_no || ''}"
                                                data-date="${invoice.date_of_purchase || ''}"
                                                data-po="${invoice.po_no || ''}">
                                                <i class="bi bi-pencil-square"></i> <span>Edit</span>
                                            </a>` : ''}
                                            ${canDelete ? `
                                            <a class="btn btn-sm btn-outline-danger delete-invoice"
                                                data-id="${invoice.id}"
                                                href="#">
                                                <i class="bi bi-trash"></i> <span>Remove</span>
                                            </a>` : ''}
                                        </div>
                                    </td>
                                </tr>
                                `;
                            });
                            $('#invoiceTable tbody').html(tableBody || '<tr><td colspan="6">No Charge Invoices found.</td></tr>');
                            reinitializePagination();
                            attachRowEventHandlers();
                            attachSortingHandlers();
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
        });
    });
</script>
</body>

</html>