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
                $oldPoNo = $old['po_no'] ?? null;
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
                    ($old['invoice_no'] !== $invoice_no) ||
                    ($old['date_of_purchase'] !== $date_of_purchase) ||
                    ($oldPoNo !== $newPoNo)
                ) {
                    // Other fields changed (or PO number changed)
                    $logModified = true;
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
        switch (
            $_GET['type'] ?? ''
        ) {
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
<style>
  /* Ensure table header is always visible and dark */
  #invoiceTable thead.table-dark th {
    background-color: #212529 !important;
    color: #fff !important;
    opacity: 1 !important;
  }
  #invoiceTable thead.table-dark {
    background-color: #212529 !important;
  }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Ensure toast.js is loaded for showToast -->
<script src="/src/control/js/toast.js"></script>
<script>
var canModify = <?php echo json_encode($canModify); ?>;
var canDelete = <?php echo json_encode($canDelete); ?>;

// Place JS function here
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

$(document).ready(function() {
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
        $('#edit_po_no').val(po).trigger('change'); // set the dropdown and trigger Select2 update

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
                    action: 'removed',
                    id: deleteInvoiceId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        $('#invoiceTable').load(location.href + ' #invoiceTable', function() {
                            showToast(response.message, 'success');
                            reinitInvoiceTableJS();
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
                        reinitInvoiceTableJS();
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
                        reinitInvoiceTableJS();
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

                showToast('Error processing request. Please try again.', 'error');

                // Remove modal backdrop in case of error
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
                $('body').css('padding-right', '');
            }
        });
    });

    // Create search functionality for the table
    const searchInput = document.getElementById('searchInvoice');
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

    // Initialize allRows and filteredRows for client-side filtering/pagination
    function initInvoiceRows() {
        window.allRows = Array.from(document.querySelectorAll('#invoiceTable tbody tr'));
        window.filteredRows = [...window.allRows];
    }
    initInvoiceRows();

    // After AJAX reloads, reinitialize allRows/filteredRows and rebind events
    function reinitInvoiceTableJS() {
        initInvoiceRows();
        if (typeof updatePagination === 'function') updatePagination();
    }

    // Fix clear button: reset filters, search, and show all rows by reloading all data via AJAX
    $('#clearFilters').off('click').on('click', function(e) {
        e.preventDefault();
        // Reset filter dropdowns
        $('#dateFilter').val('');
        // Hide and clear all date input containers/fields
        $('#dateInputsContainer').hide().find('input').val('');
        $('#dateInputsContainer .date-group').addClass('d-none');
        // Clear search input
        $('#searchInvoice').val('');
        // AJAX: fetch all data (no filter)
        $.ajax({
            url: 'charge_invoice.php',
            method: 'GET',
            data: {},
            success: function(response) {
                // Parse the full HTML page, extract the table body
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = response;
                var newTbody = $(tempDiv).find('#invoiceTable tbody').html();
                if (newTbody) {
                    $('#invoiceTable tbody').html(newTbody);
                }
                // Reinitialize allRows, filteredRows, and pagination
                if (typeof reinitInvoiceTableJS === 'function') reinitInvoiceTableJS();
                $('#invoiceTable tbody tr').show();
                showToast('Filters cleared.', 'success');
            },
            error: function() {
                showToast('Error clearing filters.', 'error');
            }
        });
    });

    // After AJAX reloads (add/edit/delete/filter), call reinitInvoiceTableJS
    // Example: $('#invoiceTable').load(..., function() { reinitInvoiceTableJS(); });

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
        applyInvoiceFilter(filterType, params);
    });

    function applyInvoiceFilter(type, params = {}) {
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
            url: 'charge_invoice.php',
            method: 'GET',
            data: filterData,
            success: function(response) {
                try {
                    const data = JSON.parse(response);
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
                        <input type="text" id="searchInvoice" class="form-control" placeholder="Search invoice...">
                    </div>
                </div>

                <div class="table-responsive" id="table">
                    <table id="invoiceTable" class="table table-striped table-bordered table-hover">
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

    <div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;"></div>

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

    <?php include '../../general/footer.php'; ?>
</body>

</html>