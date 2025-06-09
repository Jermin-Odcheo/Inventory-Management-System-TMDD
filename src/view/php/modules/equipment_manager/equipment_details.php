<?php
/**
 * Equipment Details Module
 *
 * This file provides comprehensive functionality for managing equipment details in the system. It handles the creation, modification, and tracking of equipment information, including specifications, maintenance records, and historical data. The module ensures proper validation, user authorization, and maintains data consistency across the system.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentManager
 * @author     TMDD Interns 25'
 */
session_start();
date_default_timezone_set('Asia/Manila');
ob_start();
require_once('../../../../../config/ims-tmdd.php');

// -------------------------
// Auth and RBAC Setup
// -------------------------
/**
 * Session Validation
 * 
 * Validates if a user is logged in by checking the session variable. If not, redirects to the login page.
 */
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;

/**
 * RBAC Initialization and Privilege Check
 * 
 * Initializes the RBAC service and enforces the 'View' privilege for equipment management.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Management', 'View');

/**
 * Permission Flags for UI Controls
 * @var bool
 */
$canCreate = $rbac->hasPrivilege('Equipment Management', 'Create');
/**
 * Permission Flag for Modify Action
 * @var bool
 */
$canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
/**
 * Permission Flag for Delete Action
 * @var bool
 */
$canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');

// -------------------------
// AJAX Request Handling
// -------------------------
/**
 * Detects if the request is an AJAX request.
 * @var bool
 */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * AJAX Request Processing
 * 
 * Handles AJAX POST requests for various actions like fetching details, searching, creating, updating, and deleting equipment records.
 */
if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Return JSON for any AJAX call
    header('Content-Type: application/json');

    // Helper: Validate required fields
    function validateRequiredFields(array $fields)
    {
        foreach ($fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
    }

    switch ($_POST['action']) {
        // 1) Fetch single equipment details (for Edit modal)
        case 'get_details':
            if (!$canModify) {
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
                exit;
            }
            try {
                $id = (int)$_POST['equipment_id'];
                $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new Exception('Equipment not found');
                }
                // Process date_acquired for JSON response
                $date_acquired_from_db_json = $row['date_acquired'] ?? '';
                $processed_date_acquired_json = ''; // Default to blank

                if (!empty($date_acquired_from_db_json) && $date_acquired_from_db_json !== '0000-00-00') {
                    $timestamp_json = strtotime($date_acquired_from_db_json);
                    if ($timestamp_json !== false) {
                        $year_json = (int)date('Y', $timestamp_json);
                        if ($year_json >= 1) {
                            $formatted_date_json = date('Y-m-d', $timestamp_json);
                            if ($formatted_date_json !== '-0001-11-30') {
                                $processed_date_acquired_json = $formatted_date_json;
                            }
                        }
                    }
                }

                // Return all relevant fields
                echo json_encode([
                    'status' => 'success',
                    'data'   => [
                        'id'                    => $row['id'],
                        'asset_tag'             => $row['asset_tag'],
                        'asset_description_1'   => $row['asset_description_1'],
                        'asset_description_2'   => $row['asset_description_2'],
                        'specifications'        => $row['specifications'],
                        'brand'                 => $row['brand'],
                        'model'                 => $row['model'],
                        'serial_number'         => $row['serial_number'],
                        'date_acquired'         => $processed_date_acquired_json,
                        'location'              => $row['location'],
                        'accountable_individual' => $row['accountable_individual'],
                        'rr_no'                 => $row['rr_no'],
                        'remarks'               => $row['remarks']
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;

            // 2) Search (filter & pagination) - same as before
        case 'search':
            try {
                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(E_ALL);
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                $userId = $_SESSION['user_id'] ?? null;
                $rbac   = new RBACService($pdo, $userId);
                $canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
                $canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');
                $search = trim($_POST['query'] ?? '');
                $filter = trim($_POST['filter'] ?? '');
                $sql = "SELECT ed.id, ed.asset_tag, ed.asset_description_1, ed.asset_description_2, ed.specifications, 
ed.brand, ed.model, ed.serial_number, ed.date_acquired, ed.location, ed.accountable_individual, 
CASE WHEN rr.is_disabled = 1 OR rr.rr_no IS NULL THEN NULL ELSE ed.rr_no END AS rr_no, 
ed.remarks, ed.date_created, ed.date_modified 
FROM equipment_details ed
LEFT JOIN receive_report rr ON ed.rr_no = rr.rr_no
WHERE ed.is_disabled = 0 AND (
    ed.asset_tag LIKE ? OR
    ed.asset_description_1 LIKE ? OR
    ed.asset_description_2 LIKE ? OR
    ed.specifications LIKE ? OR
    ed.brand LIKE ? OR
    ed.model LIKE ? OR
    ed.serial_number LIKE ? OR
    ed.location LIKE ? OR
    ed.accountable_individual LIKE ? OR
    ed.rr_no LIKE ? OR
    ed.remarks LIKE ?
)";
                $params = array_fill(0, 11, "%{$search}%");
                if ($filter !== '') {
                    $sql .= " AND ed.asset_description_1 = ?";
                    $params[] = $filter;
                }
                $sql .= " ORDER BY ed.id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $equipmentDetails = $stmt->fetchAll();

                ob_start();
                if (!empty($equipmentDetails)) {
                    foreach ($equipmentDetails as $equipment) {
                        echo '<tr>';
                        echo '<td>' . safeHtml($equipment['id']) . '</td>';
                        echo '<td>' . safeHtml($equipment['asset_tag']) . '</td>';
                        echo '<td>' . safeHtml($equipment['asset_description_1']) . '</td>';
                        echo '<td>' . safeHtml($equipment['asset_description_2']) . '</td>';
                        echo '<td>' . safeHtml($equipment['specifications']) . '</td>';
                        echo '<td>' . safeHtml($equipment['brand']) . '</td>';
                        echo '<td>' . safeHtml($equipment['model']) . '</td>';
                        echo '<td>' . safeHtml($equipment['serial_number']) . '</td>';
                        echo '<td>' . (!empty($equipment['date_acquired']) ? date(
                            'Y-m-d',
                            strtotime($equipment['date_acquired'])
                        ) : '') . '</td>';
                        echo '<td>' . (!empty($equipment['date_created']) ? date(
                            'Y-m-d H:i',
                            strtotime($equipment['date_created'])
                        ) : '') . '</td>';
                        echo '<td>' . (!empty($equipment['date_modified']) ? date(
                            'Y-m-d H:i',
                            strtotime($equipment['date_modified'])
                        ) : '') . '</td>';
                        echo '<td>' . safeHtml((strpos($equipment['rr_no'] ?? '', 'RR') === 0 ?
                            $equipment['rr_no'] : ('RR' . $equipment['rr_no']))) . '</td>';
                        echo '<td>' . safeHtml($equipment['location']) . '</td>';
                        echo '<td>' . safeHtml($equipment['accountable_individual']) . '</td>';
                        echo '<td>' . safeHtml($equipment['remarks']) . '</td>';
                        echo '<td>';
                        echo '<div class="btn-group">';
                        if ($canModify) {
                            echo '<button class="btn btn-outline-info btn-sm edit-equipment"'
                                . ' data-id="' . safeHtml($equipment['id']) . '"'
                                . '><i class="bi bi-pencil-square"></i></button>';
                        }
                        if ($canDelete) {
                            echo '<button class="btn btn-outline-danger btn-sm remove-equipment" '
                                . 'data-id="' . safeHtml($equipment['id']) . '"><i class="bi bi-trash"></i></button>';
                        }
                        echo '</div>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="16" class="text-center py-4"><div class="alert alert-warning mb-0">'
                        . '<i class="bi bi-exclamation-circle me-2"></i> No results found for the current filter criteria.'
                        . '</div></td></tr>';
                }
                $html = ob_get_clean();
                echo json_encode(['status' => 'success', 'html' => $html]);
            } catch (Throwable $e) {
                error_log('AJAX Search Error: ' . $e->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'AJAX Search Error: ' . $e->getMessage()]);
            }
            exit;

            // 3) Create new equipment
        case 'create':
            if (!$canCreate) {
                echo json_encode(['status' => 'error', 'message' => 'You do not have permission to create equipment details']);
                exit;
            }
            try {
                $pdo->beginTransaction();
                $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                validateRequiredFields(['asset_tag']);

                $date_created = date('Y-m-d H:i:s');
                $values = [
                    $_POST['asset_tag'],
                    $_POST['asset_description_1'] ?? null,
                    $_POST['asset_description_2'] ?? null,
                    $_POST['specifications'] ?? null,
                    $_POST['brand'] ?? null,
                    $_POST['model'] ?? null,
                    $_POST['serial_number'] ?? null,
                    $_POST['location'] ?? null,
                    $_POST['accountable_individual'] ?? null,
                    (isset($_POST['rr_no']) && $_POST['rr_no'] !== '' ? (strpos(
                        $_POST['rr_no'],
                        'RR'
                    ) === 0 ? $_POST['rr_no'] : 'RR' . $_POST['rr_no']) : null),
                    $_POST['date_acquired'] ?? null,
                    $date_created,
                    $_POST['remarks'] ?? null
                ];

                $stmt = $pdo->prepare("INSERT INTO equipment_details (
                    asset_tag, asset_description_1, asset_description_2, specifications, 
                    brand, model, serial_number, location, accountable_individual, rr_no, date_acquired, date_created, 
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute($values);
                $newEquipmentId = $pdo->lastInsertId();

                $newValues = json_encode([
                    'asset_tag'             => $_POST['asset_tag'],
                    'asset_description_1'   => $_POST['asset_description_1'] ?? null,
                    'asset_description_2'   => $_POST['asset_description_2'] ?? null,
                    'specifications'        => $_POST['specifications'] ?? null,
                    'brand'                 => $_POST['brand'] ?? null,
                    'model'                 => $_POST['model'] ?? null,
                    'serial_number'         => $_POST['serial_number'] ?? null,
                    'location'              => $_POST['location'] ?? null,
                    'accountable_individual' => $_POST['accountable_individual'] ?? null,
                    'rr_no'                 => $_POST['rr_no'] ?? null,
                    'date_acquired'         => $_POST['date_acquired'] ?? null,
                    'date_created'          => $date_created,
                    'remarks'               => $_POST['remarks'] ?? null
                ]);
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $newEquipmentId,
                    'Equipment Details',
                    'Create',
                    'New equipment created',
                    null,
                    $newValues,
                    'Successful'
                ]);

                // If date_acquired is provided and there's an RR number, update related charge_invoice
                $dateAcquired = $_POST['date_acquired'] ?? null;
                $updatedInvoiceCount = 0;

                if (!empty($dateAcquired)) {
                    try {
                        // Only proceed if we have an RR number
                        $rrNo = $_POST['rr_no'] ?? null;
                        if (!empty($rrNo)) {
                            // Ensure RR number has the RR prefix
                            if (strpos($rrNo, 'RR') !== 0) {
                                $rrNo = 'RR' . $rrNo;
                            }

                            // 1. Get the PO number from the receive_report table
                            $rrStmt = $pdo->prepare("SELECT po_no FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
                            $rrStmt->execute([$rrNo]);
                            $rrData = $rrStmt->fetch(PDO::FETCH_ASSOC);

                            if ($rrData && !empty($rrData['po_no'])) {
                                $poNo = $rrData['po_no'];

                                // 2. Update the charge_invoice connected to this PO
                                $ciStmt = $pdo->prepare("
                                    UPDATE charge_invoice 
                                    SET date_of_purchase = ? 
                                    WHERE po_no = ? AND is_disabled = 0
                                ");
                                $ciStmt->execute([$dateAcquired, $poNo]);

                                $updatedInvoices = $ciStmt->rowCount();
                                if ($updatedInvoices > 0) {
                                    // Track the total number of updated invoices
                                    $updatedInvoiceCount = $updatedInvoices;

                                    // 3. Log the changes to audit_log
                                    $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                                        UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                                    $auditStmt->execute([
                                        $_SESSION['user_id'],
                                        $newEquipmentId,
                                        'Charge Invoice',
                                        'Modified',
                                        'Charge invoice date updated from new equipment details',
                                        null,
                                        json_encode(['new_date' => $dateAcquired, 'po_no' => $poNo, 'updated_invoices' => $updatedInvoices]),
                                        'Successful'
                                    ]);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Log error but don't stop the main transaction
                        error_log("Error updating charge invoice date during equipment creation: " . $e->getMessage());
                    }
                }

                $pdo->commit();
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Equipment Details has been added successfully' .
                        ($updatedInvoiceCount > 0 ? " (also updated {$updatedInvoiceCount} charge invoice(s))" : ""),
                    'updated_invoice_count' => $updatedInvoiceCount
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (
                    $e instanceof PDOException && isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062
                    && strpos($e->getMessage(), 'asset_tag') !== false
                ) {
                    echo json_encode(['status' => 'error', 'message' => 'Asset tag already exists. Please use a unique asset tag.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                }
            }
            exit;

            // 4) Update existing equipment
        case 'update':
            if (!$canModify) {
                echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify equipment details']);
                exit;
            }
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                $stmt->execute([$_POST['equipment_id']]);
                $oldEquipment = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$oldEquipment) {
                    throw new Exception('Equipment not found');
                }

                $values = [
                    $_POST['asset_tag'],
                    $_POST['asset_description_1'] ?? null,
                    $_POST['asset_description_2'] ?? null,
                    $_POST['specifications'] ?? null,
                    $_POST['brand'] ?? null,
                    $_POST['model'] ?? null,
                    $_POST['serial_number'] ?? null,
                    $_POST['location'] ?? null,
                    $_POST['accountable_individual'] ?? null,
                    (isset($_POST['rr_no']) && $_POST['rr_no'] !== '' ? (strpos(
                        $_POST['rr_no'],
                        'RR'
                    ) === 0 ? $_POST['rr_no'] : 'RR' . $_POST['rr_no']) : null),
                    $_POST['date_acquired'] ?? null,
                    $_POST['remarks'] ?? null,
                    $_POST['equipment_id']
                ];

                $stmt = $pdo->prepare("UPDATE equipment_details SET 
                    asset_tag = ?, asset_description_1 = ?, asset_description_2 = ?, specifications = ?, 
                    brand = ?, model = ?, serial_number = ?, location = ?, accountable_individual = ?, 
                    rr_no = ?, date_acquired = ?, remarks = ?, date_modified = NOW() 
                    WHERE id = ?");
                $stmt->execute($values);

                unset($oldEquipment['id'], $oldEquipment['is_disabled'], $oldEquipment['date_created']);
                $oldValue = json_encode($oldEquipment);
                $newValues = json_encode([
                    'asset_tag'             => $_POST['asset_tag'],
                    'asset_description_1'   => $_POST['asset_description_1'] ?? null,
                    'asset_description_2'   => $_POST['asset_description_2'] ?? null,
                    'specifications'        => $_POST['specifications'] ?? null,
                    'brand'                 => $_POST['brand'] ?? null,
                    'model'                 => $_POST['model'] ?? null,
                    'serial_number'         => $_POST['serial_number'] ?? null,
                    'location'              => $_POST['location'] ?? null,
                    'accountable_individual' => $_POST['accountable_individual'] ?? null,
                    'rr_no'                 => $_POST['rr_no'] ?? null,
                    'date_acquired'         => $_POST['date_acquired'] ?? null,
                    'remarks'               => $_POST['remarks'] ?? null
                ]);
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $_POST['equipment_id'],
                    'Equipment Details',
                    'Modified',
                    'Equipment details modified',
                    $oldValue,
                    $newValues,
                    'Successful'
                ]);

                // If date_acquired was changed, update related charge_invoice
                $oldDateAcquired = $oldEquipment['date_acquired'] ?? null;
                $newDateAcquired = $_POST['date_acquired'] ?? null;
                $updatedInvoiceCount = 0;

                if ($oldDateAcquired !== $newDateAcquired && !empty($newDateAcquired)) {
                    try {
                        // Only proceed if we have an RR number
                        $rrNo = $_POST['rr_no'] ?? null;
                        if (!empty($rrNo)) {
                            // Ensure RR number has the RR prefix
                            if (strpos($rrNo, 'RR') !== 0) {
                                $rrNo = 'RR' . $rrNo;
                            }

                            // 1. Get the PO number from the receive_report table
                            $rrStmt = $pdo->prepare("SELECT po_no FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
                            $rrStmt->execute([$rrNo]);
                            $rrData = $rrStmt->fetch(PDO::FETCH_ASSOC);

                            if ($rrData && !empty($rrData['po_no'])) {
                                $poNo = $rrData['po_no'];

                                // 2. Update the charge_invoice connected to this PO
                                $ciStmt = $pdo->prepare("
                                    UPDATE charge_invoice 
                                    SET date_of_purchase = ? 
                                    WHERE po_no = ? AND is_disabled = 0
                                ");
                                $ciStmt->execute([$newDateAcquired, $poNo]);

                                $updatedInvoices = $ciStmt->rowCount();
                                if ($updatedInvoices > 0) {
                                    // Track the total number of updated invoices
                                    $updatedInvoiceCount = $updatedInvoices;

                                    // 3. Log the changes to audit_log
                                    $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                                        UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                                    $auditStmt->execute([
                                        $_SESSION['user_id'],
                                        $_POST['equipment_id'],
                                        'Charge Invoice',
                                        'Modified',
                                        'Charge invoice date updated from equipment details',
                                        json_encode(['old_date' => $oldDateAcquired, 'po_no' => $poNo]),
                                        json_encode(['new_date' => $newDateAcquired, 'po_no' => $poNo, 'updated_invoices' => $updatedInvoices]),
                                        'Successful'
                                    ]);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Log error but don't stop the main transaction
                        error_log("Error updating charge invoice date: " . $e->getMessage());
                    }
                }

                $pdo->commit();
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Equipment updated successfully' .
                        ($updatedInvoiceCount > 0 ? " (also updated {$updatedInvoiceCount} charge invoice(s))" : ""),
                    'updated_invoice_count' => $updatedInvoiceCount
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;

            // 5) Soft delete equipment (and cascade)
        case 'remove':
            if (!$canDelete) {
                echo json_encode(['status' => 'error', 'message' => 'You do not have permission to remove equipment details']);
                exit;
            }
            try {
                if (!isset($_POST['details_id'])) {
                    throw new Exception('Details ID is required');
                }
                $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                $stmt->execute([$_POST['details_id']]);
                $detailsData = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$detailsData) {
                    throw new Exception('Details not found');
                }
                $pdo->beginTransaction();

                // Get the asset tag from the equipment details
                $assetTag = $detailsData['asset_tag'];

                $oldValue = json_encode($detailsData);

                // 1) Soft-delete main record
                $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 1 WHERE id = ?");
                $stmt->execute([$_POST['details_id']]);
                $detailsData['is_disabled'] = 1;
                $newValue = json_encode($detailsData);

                // 2) Soft-delete status entries
                $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE asset_tag = ?");
                $statusStmt->execute([$assetTag]);
                $statusRowsAffected = $statusStmt->rowCount();

                // 3) Soft-delete location entries
                $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 1 WHERE asset_tag = ?");
                $locationStmt->execute([$assetTag]);
                $locationRowsAffected = $locationStmt->rowCount();

                // Insert audit log for main record
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $detailsData['id'],
                    'Equipment Details',
                    'Remove',
                    'Equipment details have been removed (soft delete)',
                    $oldValue,
                    $newValue,
                    'Successful'
                ]);

                // Audit log for cascaded status deletions
                if ($statusRowsAffected > 0) {
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $detailsData['id'],
                        'Equipment Status',
                        'Remove',
                        'Equipment status entries for asset tag ' . $assetTag . ' have been removed (cascaded delete)',
                        json_encode(['asset_tag' => $assetTag, 'rows_affected' => $statusRowsAffected]),
                        null,
                        'Successful'
                    ]);
                }

                // Audit log for cascaded location deletions
                if ($locationRowsAffected > 0) {
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $detailsData['id'],
                        'Equipment Location',
                        'Remove',
                        'Equipment location entries for asset tag ' . $assetTag . ' have been removed (cascaded delete)',
                        json_encode(['asset_tag' => $assetTag, 'rows_affected' => $locationRowsAffected]),
                        null,
                        'Successful'
                    ]);
                }

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Equipment Details and related records removed successfully.']);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['status' => 'error', 'message' => 'Error removing details: ' . $e->getMessage()]);
            }
            exit;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
            exit;
    }
}

// -------------------------
// Non-AJAX: Page Setup
// -------------------------
/**
 * Error and Success Message Handling
 * 
 * Retrieves and clears error or success messages from the session for display on the page.
 */
$errors  = $_SESSION['errors']  ?? [];
$success = $_SESSION['success'] ?? '';
unset($_SESSION['errors'], $_SESSION['success']);

/**
 * Force Refresh Check
 * 
 * Checks if equipment details have been updated from another script to force a page refresh.
 * @var bool
 */
$forceRefresh = false;
if (isset($_SESSION['equipment_details_updated']) && $_SESSION['equipment_details_updated'] === true) {
    $forceRefresh = true;
    /**
     * Updated asset tag from session, if available.
     * @var string
     */
    $updatedAssetTag = $_SESSION['updated_asset_tag'] ?? '';
    unset($_SESSION['equipment_details_updated'], $_SESSION['updated_asset_tag']);
    $success = 'Equipment details updated successfully from location changes.';
}

/**
 * Fetch Equipment Details
 * 
 * Retrieves all active equipment details from the database for display in the table.
 */
try {
    $stmt = $pdo->query("SELECT ed.id, ed.asset_tag, ed.asset_description_1, ed.asset_description_2, ed.specifications, ed.brand, ed.model, ed.serial_number, ed.date_acquired, ed.location, ed.accountable_individual, CASE WHEN rr.is_disabled = 1 OR rr.rr_no IS NULL THEN NULL ELSE ed.rr_no END AS rr_no, ed.remarks, ed.date_created, ed.date_modified FROM equipment_details ed LEFT JOIN receive_report rr ON ed.rr_no = rr.rr_no WHERE ed.is_disabled = 0 ORDER BY ed.id DESC");
    /**
     * Array of equipment detail records fetched from the database.
     * @var array
     */
    $equipmentDetails = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Details: " . $e->getMessage();
}

/**
 * Utility Function for HTML Escaping
 * 
 * Escapes HTML content to prevent XSS attacks when displaying data.
 * @param string $value The value to escape
 * @return string The escaped HTML content
 */
function safeHtml($value)
{
    return htmlspecialchars($value ?? '');
}
ob_end_clean();

// Include header (common)
include('../../general/header.php');

// Re-initialize RBAC for display
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Management', 'View');

// Button flags
$canCreate = $rbac->hasPrivilege('Equipment Management', 'Create');
$canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
$canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Details Management</title>
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        /* Sortable header styles */
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px !important;
        }

        th.sortable::after {
            content: "\f0dc";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            right: 8px;
            color: #999;
        }

        th.sortable.asc::after {
            content: "\f0de";
            color: #0d6efd;
        }

        th.sortable.desc::after {
            content: "\f0dd";
            color: #0d6efd;
        }

        /* Pagination styling */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .page-item:first-child .page-link {
            margin-left: 0;
            border-top-left-radius: 0.25rem;
            border-bottom-left-radius: 0.25rem;
        }

        .page-item:last-child .page-link {
            border-top-right-radius: 0.25rem;
            border-bottom-right-radius: 0.25rem;
        }

        .page-item.active .page-link {
            z-index: 3;
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .page-link {
            position: relative;
            display: block;
            padding: 0.5rem 0.75rem;
            margin-left: -1px;
            line-height: 1.25;
            color: #0d6efd;
            background-color: #fff;
            border: 1px solid #dee2e6;
            text-decoration: none;
        }

        .page-link:hover {
            z-index: 2;
            color: #0056b3;
            text-decoration: none;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        .page-link:focus {
            z-index: 3;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .pagination:empty {
            display: none;
        }

        /* Select2 styling to match Bootstrap */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            padding: 6px 12px;
            font-size: 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            background-color: #fff;
            box-shadow: none;
            display: flex;
            align-items: center;
        }

        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 24px;
            color: #212529;
            padding-left: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 10px;
        }

        .select2-container--open .select2-dropdown {
            z-index: 9999 !important;
        }

        .select2-container .select2-selection {
            min-height: 38px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-top: 2px;
        }

        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 20px;
        }

        .select2-dropdown {
            border-color: #ced4da;
            border-radius: 0.375rem;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
        }

        /* Fix for Select2 dropdown positioning in modals */
        .modal .select2-container {
            width: 100% !important;
        }

        .modal .select2-container--open .select2-dropdown {
            position: fixed !important;
            width: auto !important;
            min-width: 100% !important;
            max-height: 200px !important;
            overflow-y: auto !important;
        }

        .modal .select2-container--open .select2-dropdown--below {
            margin-top: 5px !important;
        }

        .modal .select2-container--open .select2-dropdown--above {
            margin-bottom: 5px !important;
        }

        .modal .select2-results__option {
            padding: 4px 8px !important;
            font-size: 0.9em !important;
        }

        /* Specific styles for RR dropdown */
        .rr-select2 + .select2-container .select2-selection--single {
            height: 32px !important;
        }

        .rr-select2 + .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 32px !important;
            padding-left: 8px !important;
        }

        .rr-select2 + .select2-container .select2-selection--single .select2-selection__arrow {
            height: 30px !important;
        }

        /* Adjust RR dropdown width */
        .rr-select2 + .select2-container--open .select2-dropdown {
            width: 180px !important;
            min-width: 180px !important;
            max-width: 180px !important;
        }

        .rr-select2 + .select2-container--open .select2-results__option {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }

        .rr-select2 + .select2-container--open .select2-search--dropdown {
            padding: 4px !important;
        }

        .rr-select2 + .select2-container--open .select2-search--dropdown .select2-search__field {
            padding: 4px !important;
            width: 100% !important;
        }

        .filtered-out {
            display: none !important;
        }

        /* Highlight animation for updated rows */
        .updated-row {
            animation: highlight-row 3s ease-in-out;
        }

        @keyframes highlight-row {
            0% {
                background-color: rgba(255, 255, 0, 0.5);
            }

            70% {
                background-color: rgba(255, 255, 0, 0.5);
            }

            100% {
                background-color: transparent;
            }
        }

        /* Filter form spacing */
        #equipmentFilterForm .row {
            margin-bottom: 10px;
        }

        #dateInputsContainer {
            padding-top: 10px;
            padding-bottom: 10px;
            border-top: 1px solid #e9ecef;
        }

        /* Z-index fixes for modals */
        .main-header {
            z-index: 1030 !important;
        }

        .modal {
            z-index: 9999 !important;
        }

        .modal-backdrop {
            z-index: 9998 !important;
        }

        .modal-dialog {
            z-index: 10000 !important;
            margin-top: 50px;
        }

        .select2-container--open {
            z-index: 10001 !important;
        }

        .select2-dropdown {
            z-index: 10001 !important;
        }

        .modal-content {
            z-index: 10000 !important;
        }

        body.modal-open {
            padding-right: 0 !important;
        }

        .modal-open .main-header,
        .modal-open .header {
            z-index: 1029 !important;
            opacity: 0.5 !important;
            pointer-events: none !important;
        }
    </style>
</head>

<body>
    <?php
    include '../../general/sidebar.php';
    include '../../general/footer.php';
    
    // Add equipment-details class to body
    echo '<script>document.body.classList.add("equipment-details");</script>';
    ?>

    <div class="main-container">
        <header class="main-header">
            <h1>Equipment Details - Asset Details Management</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Asset Details</h2>

            </div>
            <div class="card-body">
                <div class="container-fluid px-0">
                    <form id="equipmentFilterForm" class="mb-4">
                        <div class="row g-3">
                            <div class="col-auto col-md-2 d-grid">
                                <?php if ($canCreate): ?>
                                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                                        <i class="bi bi-plus-lg"></i> Create Equipment
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label for="filterEquipment" class="form-label">Equipment Type</label>
                                <select class="form-select" id="filterEquipment" name="filterEquipment">
                                    <option value="">All Equipment Types</option>
                                    <?php
                                    $equipmentTypes = array_unique(array_column($equipmentDetails, 'asset_description_1'));
                                    foreach ($equipmentTypes as $type) {
                                        if (!empty($type)) {
                                            echo "<option value='" . safeHtml($type) . "'>" . safeHtml($type) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filterBrand" class="form-label">Brand</label>
                                <select class="form-select select2-filter" id="filterBrand" name="filterBrand">
                                    <option value="">All Brands</option>
                                    <?php
                                    $brands = array_unique(array_column($equipmentDetails, 'brand'));
                                    foreach ($brands as $brand) {
                                        if (!empty($brand)) {
                                            echo "<option value='" . safeHtml($brand) . "'>" . safeHtml($brand) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filterLocation" class="form-label">Location</label>
                                <select class="form-select select2-filter" id="filterLocation" name="filterLocation">
                                    <option value="">All Locations</option>
                                    <?php
                                    $locations = array_unique(array_column($equipmentDetails, 'location'));
                                    foreach ($locations as $location) {
                                        if (!empty($location)) {
                                            echo "<option value='" . safeHtml($location) . "'>" . safeHtml($location) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="dateFilter" class="form-label">Date Filter</label>
                                <select class="form-select" id="dateFilter" name="dateFilter">
                                    <option value="">No Date Filter</option>
                                    <option value="mdy">Month-Day-Year</option>
                                    <option value="month">Month Range</option>
                                    <option value="year">Year Range</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="searchEquipment" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" id="searchEquipment" name="searchEquipment" class="form-control" placeholder="Search equipment...">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2 d-grid">
                                <button type="button" id="applyFilters" class="btn btn-dark">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                            </div>
                            <div class="col-6 col-md-2 d-grid">
                                <button type="button" id="clearFilters" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                        </div>

                        <div id="dateInputsContainer" class="row g-3 mt-2 d-none">
                            <!-- MDY Picker -->
                            <div class="col-md-6 date-filter date-mdy d-none">
                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold">From (YYYY-MM-DD)</label>
                                        <input type="date" class="form-control shadow-sm" id="mdyFrom" name="mdyFrom" placeholder="e.g., 2023-01-01">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold">To (YYYY-MM-DD)</label>
                                        <input type="date" class="form-control shadow-sm" id="mdyTo" name="mdyTo" placeholder="e.g., 2023-12-31">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Month Range Picker -->
                            <div class="col-md-6 date-filter date-month d-none">
                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold">Month From</label>
                                        <input type="month" class="form-control shadow-sm" id="monthFrom" name="monthFrom">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold">Month To</label>
                                        <input type="month" class="form-control shadow-sm" id="monthTo" name="monthTo">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Year Range Picker -->
                            <div class="col-md-6 date-filter date-year d-none">
                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold">Year From</label>
                                        <input type="number" class="form-control shadow-sm" id="yearFrom" name="yearFrom" min="1900" max="2100" placeholder="YYYY">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold">Year To</label>
                                        <input type="number" class="form-control shadow-sm" id="yearTo" name="yearTo" min="1900" max="2100" placeholder="YYYY">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-responsive" id="table">
                    <table class="table" id="edTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-column="0">#</th>
                                <th class="sortable" data-column="1">Asset Tag</th>
                                <th class="sortable" data-column="2">Desc 1</th>
                                <th class="sortable" data-column="3">Desc 2</th>
                                <th class="sortable" data-column="4">Specification</th>
                                <th class="sortable" data-column="5">Brand</th>
                                <th class="sortable" data-column="6">Model</th>
                                <th class="sortable" data-column="7">Serial #</th>
                                <th class="sortable" data-column="8">Acquired Date</th>
                                <th class="sortable d-none" data-column="9">Created Date</th>
                                <th class="sortable d-none" data-column="10">Modified Date</th>
                                <th class="sortable" data-column="11">RR #</th>
                                <th class="sortable" data-column="12">Location</th>
                                <th class="sortable" data-column="13">Accountable Individual</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="equipmentTable">
                            <?php if (!empty($equipmentDetails)): ?>
                                <?php foreach ($equipmentDetails as $equipment): ?>
                                    <tr>
                                        <td><?= safeHtml($equipment['id']); ?></td>
                                        <td><?= safeHtml($equipment['asset_tag']); ?></td>
                                        <td><?= safeHtml($equipment['asset_description_1']); ?></td>
                                        <td><?= safeHtml($equipment['asset_description_2']); ?></td>
                                        <td><?= safeHtml($equipment['specifications']); ?></td>
                                        <td><?= safeHtml($equipment['brand']); ?></td>
                                        <td><?= safeHtml($equipment['model']); ?></td>
                                        <td><?= safeHtml($equipment['serial_number']); ?></td>
                                        <td><?php
                                            $acq_table = $equipment['date_acquired'] ?? '';
                                            $display_date_table = ''; // Default to blank
                                            if (!empty($acq_table) && $acq_table !== '0000-00-00') {
                                                // Attempt to parse the date
                                                $timestamp_table = strtotime($acq_table);
                                                // Check if strtotime was successful
                                                if ($timestamp_table !== false) {
                                                    $year_table = (int)date('Y', $timestamp_table);
                                                    // Only display if the year is 1 or greater
                                                    if ($year_table >= 1) {
                                                        $formatted_date_table = date('Y-m-d', $timestamp_table);
                                                        // Final check for the specific problematic string
                                                        if ($formatted_date_table !== '-0001-11-30') {
                                                            $display_date_table = $formatted_date_table;
                                                        }
                                                    }
                                                }
                                            }
                                            echo $display_date_table;
                                            ?></td>
                                        <td class="d-none"><?= !empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                                        <td class="d-none"><?= !empty($equipment['date_modified']) ? date('Y-m-d H:i', strtotime($equipment['date_modified'])) : ''; ?></td>
                                        <td><?= safeHtml((strpos($equipment['rr_no'] ?? '', 'RR') === 0 ? $equipment['rr_no'] : ('RR' . $equipment['rr_no']))); ?></td>
                                        <td><?= safeHtml($equipment['location']); ?></td>
                                        <td><?= safeHtml($equipment['accountable_individual']); ?></td>
                                        <td><?= safeHtml($equipment['remarks']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($canModify): ?>
                                                    <button class="btn btn-outline-info btn-sm edit-equipment" data-id="<?= safeHtml($equipment['id']); ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($canDelete): ?>
                                                    <button class="btn btn-outline-danger btn-sm remove-equipment" data-id="<?= safeHtml($equipment['id']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="16" class="text-center py-4">
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-info-circle me-2"></i> No Equipment Details found. Click on "Create Equipment" to add a new entry.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                <?php $totalLogs = count($equipmentDetails); ?>
                                <input type="hidden" id="total-users" value="<?= $totalLogs ?>">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>
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
        </section>
    </div>

    <!-- Add Equipment Modal -->
    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title">Add New Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="addEquipmentForm">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag <span style="color: red;">*</span></label>
                            <select class="form-select" name="asset_tag" id="add_equipment_asset_tag" required style="width: 100%;">
                                <option value="">Select or type Asset Tag</option>
                                <?php
                                // Fetch unique asset tags from equipment_location and equipment_status
                                $assetTags = [];
                                $stmt1 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_location WHERE is_disabled = 0");
                                $assetTags = array_merge($assetTags, $stmt1->fetchAll(PDO::FETCH_COLUMN));
                                $stmt2 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_status WHERE is_disabled = 0");
                                $assetTags = array_merge($assetTags, $stmt2->fetchAll(PDO::FETCH_COLUMN));
                                $assetTags = array_unique(array_filter($assetTags));
                                sort($assetTags);
                                foreach ($assetTags as $tag) {
                                    echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="asset_description_1" class="form-label">Asset Description 1</label>
                                    <input type="text" class="form-control" name="asset_description_1">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="asset_description_2" class="form-label">Asset Description 2</label>
                                    <input type="text" class="form-control" name="asset_description_2">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="specifications" class="form-label">Specification</label>
                            <textarea class="form-control" name="specifications" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" name="brand">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" name="model">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" name="serial_number">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="add_rr_no" class="form-label">RR#</label>
                                    <select class="form-select rr-select2" name="rr_no" id="add_rr_no" style="width: 100%;">
                                        <option value="">Select or search RR Number</option>
                                        <?php
                                        $stmtRR = $pdo->prepare("SELECT rr_no FROM receive_report WHERE is_disabled = 0 ORDER BY rr_no DESC");
                                        $stmtRR->execute();
                                        $rrList = $stmtRR->fetchAll(PDO::FETCH_COLUMN);
                                        foreach ($rrList as $rrNo) {
                                            echo '<option value="' . htmlspecialchars($rrNo) . '">' . htmlspecialchars($rrNo) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <small class="text-muted">Selecting an RR# will auto-fill the acquired date from Charge Invoice</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="add_date_acquired" class="form-label">Date Acquired</label>
                                    <input type="date" class="form-control" name="date_acquired" id="add_date_acquired" data-autofill="false">
                                    <small class="text-muted">Auto-filled when an RR# is selected</small>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="add_location" class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location" id="add_location" data-autofill="false">
                                    <small class="text-muted">Auto-filled when an Asset Tag is selected</small>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="add_accountable_individual" class="form-label">Accountable Individual</label>
                                    <input type="text" class="form-control" name="accountable_individual" id="add_accountable_individual" data-autofill="false">
                                    <small class="text-muted">Auto-filled when an Asset Tag is selected</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="add_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="add_remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="margin-right: 4px;">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Equipment Modal -->
    <div class="modal fade" id="editEquipmentModal" tabindex="-1" aria-labelledby="editEquipmentLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title">Edit Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="editEquipmentForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="equipment_id" id="edit_equipment_id">
                        <div class="mb-3">
                            <label for="edit_equipment_asset_tag" class="form-label">Asset Tag <span style="color: red;">*</span></label>
                            <select class="form-select" name="asset_tag" id="edit_equipment_asset_tag" required>
                                <option value="">Select or type Asset Tag</option>
                                <?php
                                // Populate same asset tags as in "Add" modal
                                foreach ($assetTags as $tag) {
                                    echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="edit_asset_description_1" class="form-label">Asset Description 1</label>
                                    <input type="text" class="form-control" name="asset_description_1" id="edit_asset_description_1">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_asset_description_2" class="form-label">Asset Description 2</label>
                                    <input type="text" class="form-control" name="asset_description_2" id="edit_asset_description_2">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_specifications" class="form-label">Specification</label>
                            <textarea class="form-control" name="specifications" id="edit_specifications" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="edit_brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" name="brand" id="edit_brand">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_model" class="form-label">Model</label>
                                    <input type="text" class="form-control" name="model" id="edit_model">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="edit_serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" name="serial_number" id="edit_serial_number">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_rr_no" class="form-label">RR#</label>
                                    <select class="form-select rr-select2" name="rr_no" id="edit_rr_no" style="width: 100%;">
                                        <option value="">Select or search RR Number</option>
                                        <?php
                                        foreach ($rrList as $rrNo) {
                                            echo '<option value="' . htmlspecialchars($rrNo) . '">' . htmlspecialchars($rrNo) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <small class="text-muted">Selecting an RR# will auto-fill the acquired date from Charge Invoice</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="edit_date_acquired" class="form-label">Date Acquired</label>
                                    <input type="date" class="form-control" name="date_acquired" id="edit_date_acquired">
                                    <small class="text-muted">Auto-filled when an RR# is selected</small>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_location" class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location" id="edit_location">
                                    <small class="text-muted">Auto-filled from database or by selecting an asset tag</small>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_accountable_individual" class="form-label">Accountable Individual</label>
                                    <input type="text" class="form-control" name="accountable_individual" id="edit_accountable_individual">
                                    <small class="text-muted">Auto-filled from database or by selecting an asset tag</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="margin-right: 4px;">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Equipment Modal -->
    <div class="modal fade" id="deleteEDModal" tabindex="-1" data-bs-backdrop="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title">Confirm Removal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    Are you sure you want to remove this Equipment Detail?
                </div>
                <div class="modal-footer p-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Remove</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Libraries and Custom Scripts -->
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/toast.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/asset_tag_autofill.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/rr_autofill.js"></script>
    <script>
        $(document).ready(function() {
            // ========= Initialize Select2 dropdowns =========

            // RR# for Add modal
            $('#add_rr_no').select2({
                placeholder: 'Select or search RR Number',
                allowClear: true,
                width: '100%',
                tags: true,
                dropdownParent: $('#addEquipmentModal'),
                minimumResultsForSearch: 0,
                dropdownPosition: 'below',
                createTag: function(params) {
                    var term = $.trim(params.term);
                    if (term === '') return null;
                    var exists = false;
                    $('#add_rr_no option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) exists = true;
                    });
                    if (!/^[0-9]+$/.test(term)) return null;
                    return exists ? null : {
                        id: term,
                        text: term
                    };
                }
            });

            // RR# for Edit modal
            $('#edit_rr_no').select2({
                placeholder: 'Select or search RR Number',
                allowClear: true,
                width: '100%',
                tags: true,
                dropdownParent: $('#editEquipmentModal'),
                minimumResultsForSearch: 0,
                dropdownPosition: 'below',
                createTag: function(params) {
                    var term = $.trim(params.term);
                    if (term === '') return null;
                    var exists = false;
                    $('#edit_rr_no option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) exists = true;
                    });
                    if (!/^[0-9]+$/.test(term)) return null;
                    return exists ? null : {
                        id: term,
                        text: term
                    };
                }
            });

            // Asset Tag for Add modal
            $('#add_equipment_asset_tag').select2({
                placeholder: 'Select or type Asset Tag',
                allowClear: true,
                width: '100%',
                tags: true,
                dropdownParent: $('#addEquipmentModal'),
                minimumResultsForSearch: 0,
                createTag: function(params) {
                    var term = $.trim(params.term);
                    if (term === '') return null;
                    var exists = false;
                    $('#add_equipment_asset_tag option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) exists = true;
                    });
                    return exists ? null : {
                        id: term,
                        text: term,
                        newTag: true
                    };
                }
            }).on('select2:select', function(e) {
                var assetTag = e.params.data.id;
                const $accountableField = $('#add_accountable_individual');
                const $locationField = $('#add_location');
                if ($accountableField.attr('data-autofill') === 'true') {
                    $accountableField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                }
                if ($locationField.attr('data-autofill') === 'true') {
                    $locationField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                }
                if (!e.params.data.newTag) {
                    fetchAssetTagInfo(assetTag, 'add', true);
                }
            });

            // Asset Tag for Edit modal
            $('#edit_equipment_asset_tag').select2({
                placeholder: 'Select or type Asset Tag',
                allowClear: true,
                width: '100%',
                tags: true,
                dropdownParent: $('#editEquipmentModal'),
                minimumResultsForSearch: 0,
                createTag: function(params) {
                    var term = $.trim(params.term);
                    if (term === '') return null;
                    var exists = false;
                    $('#edit_equipment_asset_tag option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) exists = true;
                    });
                    return exists ? null : {
                        id: term,
                        text: term,
                        newTag: true
                    };
                }
            }).on('select2:select', function(e) {
                var assetTag = e.params.data.id;
                const $accountableField = $('#edit_accountable_individual');
                const $locationField = $('#edit_location');
                if ($accountableField.attr('data-autofill') === 'true') {
                    $accountableField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                }
                if ($locationField.attr('data-autofill') === 'true') {
                    $locationField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                }
                if (!e.params.data.newTag) {
                    fetchAssetTagInfo(assetTag, 'edit', true);
                }
            });

            // Modal show/hide z-index fix
            $('.modal').on('show.bs.modal', function() {
                $('.main-header, .header').css({
                    'z-index': '1029',
                    'opacity': '0.5',
                    'pointer-events': 'none'
                });
            });
            $('.modal').on('hidden.bs.modal', function() {
                $('.main-header, .header').css({
                    'z-index': '1030',
                    'opacity': '1',
                    'pointer-events': 'auto'
                });
            });

            // Initialize Select2 for filters
            $('.select2-filter').select2({
                placeholder: function() {
                    return $(this).data('placeholder') || 'Select...';
                },
                allowClear: true,
                width: '100%',
                dropdownAutoWidth: true,
                minimumResultsForSearch: 0
            });
            $('#filterBrand').data('placeholder', 'Search Brand...');
            $('#filterLocation').data('placeholder', 'Search Location...');

            try {
                if ($.fn.select2) {
                    $('#filterEquipment').select2({
                        placeholder: 'Filter Equipment Type',
                        allowClear: true,
                        width: '100%',
                        dropdownAutoWidth: true,
                        minimumResultsForSearch: 0
                    });
                }
            } catch (e) {
                console.error('Error initializing Select2:', e);
            }
        });

        // ===== Filter & Pagination Logic =====
        function filterEquipmentTable() {
            const searchText = $('#searchEquipment').val() || '';
            const filterEquipment = $('#filterEquipment').val() || '';
            const filterBrand = $('#filterBrand').val() || '';
            const filterLocation = $('#filterLocation').val() || '';
            const dateFilterType = $('#dateFilter').val() || '';
            
            // Get date values for different filter types
            const mdyFrom = $('#mdyFrom').val() || '';
            const mdyTo = $('#mdyTo').val() || '';
            const monthFrom = $('#monthFrom').val() || '';
            const monthTo = $('#monthTo').val() || '';
            const yearFrom = $('#yearFrom').val() || '';
            const yearTo = $('#yearTo').val() || '';

            const tableRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();

            const filteredRows = [];

            tableRows.forEach((row) => {
                if (row.id === 'noResultsMessage' || row.id === 'initialFilterMessage') return;

                const cells = Array.from(row.cells || []);
                if (cells.length === 0) return;

                let equipmentTypeText = '',
                    brandText = '',
                    locationText = '',
                    dateText = '';
                const cellTexts = cells.map((cell, idx) => {
                    const text = (cell.textContent || '').trim();
                    if (idx === 2) equipmentTypeText = text.toLowerCase();
                    if (idx === 5) brandText = text.toLowerCase();
                    if (idx === 12) locationText = text.toLowerCase();
                    if (idx === 8) dateText = text;
                    return text.toLowerCase();
                });
                const rowText = cellTexts.join(' ');

                const searchMatch = !searchText || rowText.includes(searchText.toLowerCase());
                const equipmentMatch = !filterEquipment || equipmentTypeText === filterEquipment.toLowerCase();
                const brandMatch = !filterBrand || brandText === filterBrand.toLowerCase();
                const locationMatch = !filterLocation || locationText === filterLocation.toLowerCase();

                let dateMatch = true;
                if (dateFilterType && dateText) {
                    const date = new Date(dateText);
                    if (!isNaN(date.getTime())) {
                        // Month-Day-Year filter
                        if (dateFilterType === 'mdy' && (mdyFrom || mdyTo)) {
                            const fromDate = mdyFrom ? new Date(mdyFrom) : new Date(0);
                            const toDate = mdyTo ? new Date(mdyTo) : new Date(8640000000000000);
                            toDate.setHours(23, 59, 59, 999);
                            dateMatch = date >= fromDate && date <= toDate;
                        } 
                        // Month Range filter
                        else if (dateFilterType === 'month' && (monthFrom || monthTo)) {
                            const year = date.getFullYear();
                            const month = date.getMonth() + 1; // JavaScript months are 0-based
                            const dateYearMonth = `${year}-${month.toString().padStart(2, '0')}`;
                            
                            let fromYearMonth = '0000-01';
                            if (monthFrom) {
                                fromYearMonth = monthFrom;
                            }
                            
                            let toYearMonth = '9999-12';
                            if (monthTo) {
                                toYearMonth = monthTo;
                            }
                            
                            dateMatch = dateYearMonth >= fromYearMonth && dateYearMonth <= toYearMonth;
                        } 
                        // Year Range filter
                        else if (dateFilterType === 'year' && (yearFrom || yearTo)) {
                            const year = date.getFullYear();
                            const fromYear = yearFrom ? parseInt(yearFrom) : 0;
                            const toYear = yearTo ? parseInt(yearTo) : 9999;
                            
                            dateMatch = year >= fromYear && year <= toYear;
                        }
                    } else {
                        dateMatch = false;
                    }
                }

                const shouldShow = searchMatch && equipmentMatch && brandMatch && locationMatch && dateMatch;
                if (shouldShow) {
                    filteredRows.push(row);
                } else {
                    $(row).hide();
                }
            });

            // Store filtered rows and reset pagination
            window.allRows = tableRows;
            window.filteredRows = filteredRows;
            window.currentPage = 1;

            // Update pagination counters
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalRows = filteredRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);

            $('#currentPage').text(window.currentPage);
            $('#rowsPerPage').text(Math.min(rowsPerPage, totalRows));
            $('#totalRows').text(totalRows);

            // Update pagination controls
            buildPaginationButtons(totalPages);
            updatePaginationButtons();
            
            // Show only the first page of results
            showCurrentPageRows();

            // Show no results message if needed
            if (filteredRows.length === 0) {
                $('#noResultsMessage').remove();
                const tbody = document.getElementById('equipmentTable');
                const noResultsRow = document.createElement('tr');
                noResultsRow.id = 'noResultsMessage';
                noResultsRow.innerHTML = `
                    <td colspan="16" class="text-center py-4">
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-circle me-2"></i> No results found for the current filter criteria.
                        </div>
                    </td>
                `;
                tbody.appendChild(noResultsRow);
                $(noResultsRow).show();
            } else {
                $('#noResultsMessage').remove();
            }

            return filteredRows;
        }

        function initPagination() {
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalRows = window.filteredRows ? window.filteredRows.length : 0;
            const totalPages = Math.ceil(totalRows / rowsPerPage);

            window.currentPage = window.currentPage || 1;
            if (window.currentPage > totalPages && totalPages > 0) {
                window.currentPage = 1;
            }

            $('#currentPage').text(window.currentPage);
            $('#rowsPerPage').text(rowsPerPage);
            $('#totalRows').text(totalRows);

            buildPaginationButtons(totalPages);
            showCurrentPageRows();
        }

        function buildPaginationButtons(totalPages) {
            const $pagination = $('#pagination');
            $pagination.empty();
            if (totalPages <= 1) return;

            // Add Previous button
            $pagination.append(`
                <li class="page-item ${window.currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${window.currentPage - 1}" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `);

            let startPage = Math.max(1, window.currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            if (endPage === totalPages) {
                startPage = Math.max(1, endPage - 4);
            }

            for (let i = startPage; i <= endPage; i++) {
                $pagination.append(`
                    <li class="page-item ${window.currentPage === i ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `);
            }

            // Add Next button
            $pagination.append(`
                <li class="page-item ${window.currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${window.currentPage + 1}" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `);

            $('.page-link[data-page]').on('click', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                if (!$(this).parent().hasClass('disabled')) {
                    goToPage(page);
                }
            });
        }

        function goToPage(page) {
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);
            if (page < 1 || page > totalPages) return;

            window.currentPage = page;
            $('#currentPage').text(window.currentPage);
            buildPaginationButtons(totalPages);
            showCurrentPageRows();
        }

        function showCurrentPageRows() {
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const startIndex = (window.currentPage - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;

            if (!window.filteredRows || window.filteredRows.length === 0) {
                $('#noResultsMessage').show();
                return;
            }

            // First hide all rows
            $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').hide();
            
            // Then only show the rows for the current page
            window.filteredRows.slice(startIndex, endIndex).forEach(row => {
                $(row).show();
            });
            
            // Make sure no results message is visible if needed
            if ($('#noResultsMessage').length > 0) {
                $('#noResultsMessage').show();
            }
        }

        $(document).ready(function() {
            window.allRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
            window.filteredRows = [...window.allRows];
            window.currentPage = 1;

            initPagination();
            setTimeout(function() {
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
                const totalRows = window.filteredRows.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                buildPaginationButtons(totalPages);
                showCurrentPageRows();
            }, 300);

            $('#rowsPerPageSelect').on('change', function() {
                if (window.paginationConfig) {
                    window.paginationConfig.currentPage = 1;
                }
                window.currentPage = 1;
                
                const rowsPerPage = parseInt($(this).val() || 10);
                const totalRows = window.filteredRows ? window.filteredRows.length : 0;
                
                $('#currentPage').text(1);
                $('#rowsPerPage').text(Math.min(rowsPerPage, totalRows));
                $('#totalRows').text(totalRows);
                
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                buildPaginationButtons(totalPages);
                showCurrentPageRows();
            });

            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();
                $('#dateInputsContainer').addClass('d-none');
                $('.date-filter').addClass('d-none');
                if (filterType) {
                    $('#dateInputsContainer').removeClass('d-none');
                    if (filterType === 'month') {
                        $('.date-month').removeClass('d-none');
                    } else if (filterType === 'year') {
                        $('.date-year').removeClass('d-none');
                    } else if (filterType === 'mdy') {
                        $('.date-mdy').removeClass('d-none');
                    }
                }
                
                // Clear any existing validation errors when changing filter type
                $('#filterError').remove();
            });

            $('.sortable').on('click', function() {
                const columnIndex = parseInt($(this).data('column'));
                const currentSortState = $(this).hasClass('asc') ? 'asc' : ($(this).hasClass('desc') ? 'desc' : '');
                let newSortState = '';
                if (currentSortState === '') {
                    newSortState = 'asc';
                } else if (currentSortState === 'asc') {
                    newSortState = 'desc';
                } else {
                    newSortState = 'asc';
                }

                $('.sortable').removeClass('asc desc');
                $(this).addClass(newSortState);

                window.filteredRows.sort(function(a, b) {
                    const aText = a.cells[columnIndex] ? (a.cells[columnIndex].textContent || '').trim() : '';
                    const bText = b.cells[columnIndex] ? (b.cells[columnIndex].textContent || '').trim() : '';

                    const isDate = /^\d{4}-\d{2}-\d{2}/.test(aText) || /^\d{4}-\d{2}-\d{2}/.test(bText);
                    const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
                    const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
                    const isNumeric = !isNaN(aNum) && !isNaN(bNum) &&
                        aText.replace(/[^\d.-]/g, '') !== '' &&
                        bText.replace(/[^\d.-]/g, '') !== '';

                    let comparison = 0;
                    if (isDate) {
                        const dateA = new Date(aText);
                        const dateB = new Date(bText);
                        comparison = dateA - dateB;
                    } else if (isNumeric) {
                        comparison = aNum - bNum;
                    } else {
                        comparison = aText.toLowerCase().localeCompare(bText.toLowerCase());
                    }
                    return newSortState === 'asc' ? comparison : -comparison;
                });

                const tbody = document.getElementById('equipmentTable');
                window.filteredRows.forEach(row => {
                    tbody.appendChild(row);
                });

                window.currentPage = 1;
                $('#currentPage').text(window.currentPage);

                const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
                const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);
                buildPaginationButtons(totalPages);
                updatePaginationButtons();
                showCurrentPageRows();
            });
        });

        // ==================== Edit Equipment (fetch via AJAX) ====================
        $(document).ready(function() {
            $(document).on('click', '.edit-equipment', function() {
                var equipmentId = $(this).data('id');
                $('#edit_equipment_id').val(equipmentId);
                $('#editEquipmentModal').modal('show');

                // Clear existing fields
                $('#edit_equipment_asset_tag').val('').trigger('change');
                $('#edit_asset_description_1').val('');
                $('#edit_asset_description_2').val('');
                $('#edit_specifications').val('');
                $('#edit_brand').val('');
                $('#edit_model').val('');
                $('#edit_serial_number').val('');
                $('#edit_date_acquired').val('');
                $('#edit_location').val('').attr('data-autofill', 'false').removeClass('bg-light');
                $('#edit_accountable_individual').val('').attr('data-autofill', 'false').removeClass('bg-light');
                $('#edit_rr_no').val('').trigger('change.select2');
                $('#edit_remarks').val('');

                // Fetch details via AJAX
                $.ajax({
                    url: 'equipment_details.php',
                    method: 'POST',
                    data: {
                        action: 'get_details',
                        equipment_id: equipmentId
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            const d = response.data;
                            // Populate fields
                            var $assetTagSelect = $('#edit_equipment_asset_tag');
                            if ($assetTagSelect.find('option[value="' + d.asset_tag + '"]').length === 0) {
                                $assetTagSelect.append('<option value="' + $('<div>').text(d.asset_tag).html() + '">' + $('<div>').text(d.asset_tag).html() + '</option>');
                            }
                            $assetTagSelect.val(d.asset_tag).trigger('change');

                            $('#edit_asset_description_1').val(d.asset_description_1);
                            $('#edit_asset_description_2').val(d.asset_description_2);
                            $('#edit_specifications').val(d.specifications);
                            $('#edit_brand').val(d.brand);
                            $('#edit_model').val(d.model);
                            $('#edit_serial_number').val(d.serial_number);
                            $('#edit_date_acquired').val(d.date_acquired);
                            $('#edit_location').val(d.location).attr('data-autofill', 'true').addClass('bg-light');
                            $('#edit_accountable_individual').val(d.accountable_individual).attr('data-autofill', 'true').addClass('bg-light');

                            if (d.rr_no !== null && d.rr_no !== '') {
                                $('#edit_rr_no').val(d.rr_no).trigger('change.select2');
                            }
                            $('#edit_remarks').val(d.remarks);
                        } else {
                            showToast(response.message || 'Failed to fetch details.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('XHRError:', xhr.responseText);
                        showToast('Error fetching equipment details.', 'error');
                    }
                });
            });

            // Submit edited equipment via AJAX
            $('#editEquipmentForm').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var submitBtn = $form.find('button[type="submit"]');
                var originalBtnText = submitBtn.text();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                $.ajax({
                    url: 'equipment_details.php',
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        try {
                            var result = typeof response === 'object' ? response : JSON.parse(response);
                            submitBtn.prop('disabled', false).text(originalBtnText);
                            if (result.status === 'success') {
                                $('#editEquipmentModal').modal('hide');
                                $('.modal-backdrop').remove();
                                $('body').removeClass('modal-open').css('padding-right', '');
                                showToast(result.message || 'Equipment updated successfully', 'success');
                                // Reload table content only
                                $('#edTable').load(location.href + ' #edTable > *', function() {
                                    window.allRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
                                    window.filteredRows = [...window.allRows];
                                    if (typeof filterEquipmentTable === 'function') filterEquipmentTable();

                                    // Show additional notification if invoices were updated
                                    if (result.updated_invoice_count && result.updated_invoice_count > 0) {
                                        setTimeout(function() {
                                            showToast(`Updated date in ${result.updated_invoice_count} charge invoice(s)`, 'info');
                                        }, 800); // Small delay for better UX
                                    }
                                });
                            } else {
                                showToast(result.message || 'Failed to update equipment.', 'error');
                            }
                        } catch (e) {
                            submitBtn.prop('disabled', false).text(originalBtnText);
                            showToast('Error processing the request', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        submitBtn.prop('disabled', false).text(originalBtnText);
                        showToast('Error updating equipment.', 'error');
                    }
                });
            });
        });

        // ==================== Add Equipment ====================
        $(document).ready(function() {
            $('#addEquipmentForm').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var submitBtn = $form.find('button[type="submit"]');
                var originalBtnText = submitBtn.text();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...');

                $.ajax({
                    url: 'equipment_details.php',
                    method: 'POST',
                    data: $form.serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        try {
                            submitBtn.prop('disabled', false).text(originalBtnText);
                            if (response.status === 'success') {
                                $('#addEquipmentModal').modal('hide');
                                $('.modal-backdrop').remove();
                                $('body').removeClass('modal-open').css('padding-right', '');

                                $form[0].reset();
                                $('#add_equipment_asset_tag').val(null).trigger('change');
                                $('#add_rr_no').val(null).trigger('change');

                                showToast(response.message || 'Equipment created successfully', 'success');
                                // Reload table content only
                                $('#edTable').load(location.href + ' #edTable > *', function() {
                                    window.allRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
                                    window.filteredRows = [...window.allRows];
                                    if (typeof filterEquipmentTable === 'function') filterEquipmentTable();
                                });
                            } else {
                                showToast(response.message || 'Failed to create equipment.', 'error');
                            }
                        } catch (e) {
                            console.error('Error processing response:', e);
                            submitBtn.prop('disabled', false).text(originalBtnText);
                            showToast('Error processing the request', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        submitBtn.prop('disabled', false).text(originalBtnText);
                        showToast('Error creating equipment.', 'error');
                    }
                });
            });
        });

        // ==================== Delete Equipment ====================
        $(document).ready(function() {
            let deleteEquipmentId = null;
            $(document).on('click', '.remove-equipment', function() {
                deleteEquipmentId = $(this).data('id');
                $('#deleteEDModal').modal('show');
            });

            $('#confirmDeleteBtn').on('click', function() {
                if (!deleteEquipmentId) return;
                $.ajax({
                    url: 'equipment_details.php',
                    method: 'POST',
                    data: {
                        action: 'remove',
                        details_id: deleteEquipmentId
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#deleteEDModal').modal('hide');
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css('padding-right', '');
                            showToast(response.message || 'Equipment removed successfully', 'success');
                            // Reload table content only
                            $('#edTable').load(location.href + ' #edTable > *', function() {
                                window.allRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
                                window.filteredRows = [...window.allRows];
                                if (typeof filterEquipmentTable === 'function') filterEquipmentTable();
                            });
                        } else {
                            showToast(response.message || 'Failed to remove equipment.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error removing equipment.', 'error');
                    }
                });
            });
        });

        // Apply filters button click handler
        $('#applyFilters').on('click', function(e) {
            // Validate date filters before applying
            let isValid = true;
            let errorMessage = '';
            let dateFilterType = $('#dateFilter').val();
            
            if (dateFilterType) {
                let fromDate, toDate;
                
                if (dateFilterType === 'mdy') {
                    fromDate = $('#mdyFrom').val();
                    toDate = $('#mdyTo').val();
                    
                    // Check if dates are empty
                    if (fromDate && !toDate) {
                        isValid = false;
                        errorMessage = 'Please select both From and To dates.';
                    } else if (!fromDate && toDate) {
                        isValid = false;
                        errorMessage = 'Please select both From and To dates.';
                    } else if (fromDate && toDate) {
                        // Check if from date is greater than to date
                        if (new Date(fromDate) > new Date(toDate)) {
                            isValid = false;
                            errorMessage = 'From date cannot be greater than To date.';
                        }
                    }
                } else if (dateFilterType === 'month') {
                    fromDate = $('#monthFrom').val();
                    toDate = $('#monthTo').val();
                    
                    // Check if dates are empty
                    if (fromDate && !toDate) {
                        isValid = false;
                        errorMessage = 'Please select both From and To months.';
                    } else if (!fromDate && toDate) {
                        isValid = false;
                        errorMessage = 'Please select both From and To months.';
                    } else if (fromDate && toDate) {
                        // Check if from date is greater than to date
                        if (new Date(fromDate) > new Date(toDate)) {
                            isValid = false;
                            errorMessage = 'From month cannot be greater than To month.';
                        }
                    }
                } else if (dateFilterType === 'year') {
                    fromDate = $('#yearFrom').val();
                    toDate = $('#yearTo').val();
                    
                    // Check if years are empty
                    if (fromDate && !toDate) {
                        isValid = false;
                        errorMessage = 'Please enter both From and To years.';
                    } else if (!fromDate && toDate) {
                        isValid = false;
                        errorMessage = 'Please enter both From and To years.';
                    } else if (fromDate && toDate) {
                        // Check if from year is greater than to year
                        if (parseInt(fromDate) > parseInt(toDate)) {
                            isValid = false;
                            errorMessage = 'From year cannot be greater than To year.';
                        }
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                $('#filterError').remove();
                $('#dateInputsContainer').css('position', 'relative');
                $('#dateInputsContainer').append('<div id="filterError" class="validation-tooltip" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: #d9534f; color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.85em; z-index: 1000; margin-top: 5px; white-space: nowrap; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">' + errorMessage + '<div style="position: absolute; top: -5px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 5px solid #d9534f;"></div></div>');
                setTimeout(function() {
                    $('#filterError').fadeOut('slow', function() {
                        $(this).remove();
                    });
                }, 3000);
                return false;
            }
            
            $('#filterError').remove();
            filterEquipmentTable();
            showToast('Filters applied successfully', 'success');
        });
        
        // Clear filters button click handler
        $('#clearFilters').on('click', function() {
            // Clear all filter inputs
            $('#searchEquipment').val('');
            $('#filterEquipment').val('').trigger('change');
            $('#filterBrand').val('').trigger('change');
            $('#filterLocation').val('').trigger('change');
            $('#dateFilter').val('').trigger('change');
            $('#mdyFrom').val('');
            $('#mdyTo').val('');
            $('#monthFrom').val('');
            $('#monthTo').val('');
            $('#yearFrom').val('');
            $('#yearTo').val('');
            
            // Remove any error messages
            $('#filterError').remove();
            
            // Reset filters and show all rows
            window.filteredRows = [...window.allRows];
            window.currentPage = 1;
            
            // Update pagination and redisplay rows
            filterEquipmentTable();
            showToast('Filters cleared successfully', 'success');
        });
    </script>
</body>

</html>