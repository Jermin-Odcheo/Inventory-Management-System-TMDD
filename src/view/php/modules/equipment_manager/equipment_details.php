<?php
session_start();
date_default_timezone_set('Asia/Manila');
ob_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed
include '../../general/header.php';
// -------------------------
// Auth and RBAC Setup
// -------------------------
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;

// Initialize RBAC & enforce "View" privilege
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Management', 'View');

// Set button flags based on privileges
$canCreate = $rbac->hasPrivilege('Equipment Management', 'Create');
$canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
$canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');

// -------------------------
// AJAX Request Handling
// -------------------------
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'search') {
        // Debug: Enable error reporting for AJAX
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        // Ensure no output before JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $rbac = new RBACService($pdo, $userId);
            $canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
            $canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');
            $search = trim($_POST['query'] ?? '');
            $filter = trim($_POST['filter'] ?? '');
            $sql = "SELECT ed.id, ed.asset_tag, ed.asset_description_1, ed.asset_description_2, ed.specifications, 
ed.brand, ed.model, ed.serial_number, ed.date_acquired, ed.location, ed.accountable_individual, 
CASE WHEN rr.is_disabled = 1 OR rr.rr_no IS NULL THEN NULL ELSE ed.rr_no END as rr_no, 
ed.remarks, ed.date_created, ed.date_modified 
FROM equipment_details ed
LEFT JOIN receive_report rr ON ed.rr_no = rr.rr_no
WHERE ed.is_disabled = 0 AND ("
                . "ed.asset_tag LIKE ? OR "
                . "ed.asset_description_1 LIKE ? OR "
                . "ed.asset_description_2 LIKE ? OR "
                . "ed.specifications LIKE ? OR "
                . "ed.brand LIKE ? OR "
                . "ed.model LIKE ? OR "
                . "ed.serial_number LIKE ? OR "
                . "ed.location LIKE ? OR "
                . "ed.accountable_individual LIKE ? OR "
                . "ed.rr_no LIKE ? OR "
                . "ed.remarks LIKE ?)";
            $params = array_fill(0, 11, "%$search%");
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
                            . ' data-asset="' . safeHtml($equipment['asset_tag']) . '"'
                            . ' data-desc1="' . safeHtml($equipment['asset_description_1']) . '"'
                            . ' data-desc2="' . safeHtml($equipment['asset_description_2']) . '"'
                            . ' data-spec="' . safeHtml($equipment['specifications']) . '"'
                            . ' data-brand="' . safeHtml($equipment['brand']) . '"'
                            . ' data-model="' . safeHtml($equipment['model']) . '"'
                            . ' data-serial="' . safeHtml($equipment['serial_number']) . '"'
                            . ' data-date-acquired="' . safeHtml($equipment['date_acquired']) . '"'
                            . ' data-location="' . safeHtml($equipment['location']) . '"'
                            . ' data-accountable="' . safeHtml($equipment['accountable_individual'])
                            . '"'
                            . ' data-rr="' . safeHtml($equipment['rr_no']) . '"'
                            . ' data-date="' . safeHtml($equipment['date_created']) . '"'
                            . ' data-remarks="' . safeHtml($equipment['remarks']) . '">
                            <i class="bi bi-pencil-square"></i>
                        </button>';
                    }
                    if ($canDelete) {
                        echo '<button class="btn btn-outline-danger btn-sm remove-equipment" 
data-id="' . safeHtml($equipment['id']) . '"><i class="bi bi-trash"></i></button>';
                    }
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="16" class="text-center py-4"><div class="alert alert-warning 
mb-0"><i class="bi bi-exclamation-circle me-2"></i> No results found for the current filter 
criteria.</div></td></tr>';
            }
            $html = ob_get_clean();
            echo json_encode(['status' => 'success', 'html' => $html]);
        } catch (Throwable $e) {
            error_log('AJAX Search Error: ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'AJAX Search Error: ' .
                $e->getMessage()]);
        }
        exit;
    }
    header('Content-Type: application/json');
    $response = ['status' => '', 'message' => ''];

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
        case 'create':
            if (!$canCreate) {
                $response['status'] = 'error';
                $response['message'] = 'You do not have permission to create equipment details';
                echo json_encode($response);
                exit;
            }

            try {
                $pdo->beginTransaction();
                $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                validateRequiredFields(['asset_tag']);

                $date_created = date('Y-m-d H:i:s');
                $values = [
                    $_POST['asset_tag'],
                    $_POST['asset_description_1'],
                    $_POST['asset_description_2'],
                    $_POST['specifications'],
                    $_POST['brand'] ?? null,
                    $_POST['model'] ?? null,
                    $_POST['serial_number'],
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
                    'asset_tag' => $_POST['asset_tag'],
                    'asset_description_1' => $_POST['asset_description_1'],
                    'asset_description_2' => $_POST['asset_description_2'],
                    'specifications' => $_POST['specifications'],
                    'brand' => $_POST['brand'] ?? null,
                    'model' => $_POST['model'] ?? null,
                    'serial_number' => $_POST['serial_number'],
                    'location' => $_POST['location'] ?? null,
                    'accountable_individual' => $_POST['accountable_individual'] ?? null,
                    'rr_no' => $_POST['rr_no'] ?? null,
                    'date_acquired' => $_POST['date_acquired'] ?? null,
                    'date_created' => $date_created,
                    'remarks' => $_POST['remarks'] ?? null
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

                $pdo->commit();
                $response['status'] = 'success';
                $response['message'] = 'Equipment Details has been added successfully.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (
                    $e instanceof PDOException && isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062
                    && strpos($e->getMessage(), 'asset_tag') !== false
                ) {
                    $response['status'] = 'error';
                    $response['message'] = 'Asset tag already exists. Please use a unique asset tag.';
                } else {
                    $response['status'] = 'error';
                    $response['message'] = $e->getMessage();
                }
            }
            ob_clean();
            echo json_encode($response);
            exit;
        case 'update':
            if (!$canModify) {
                $response['status'] = 'error';
                $response['message'] = 'You do not have permission to modify equipment details';
                echo json_encode($response);
                exit;
            }

            header('Content-Type: application/json');
            $response = ['status' => '', 'message' => ''];
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
                    $_POST['asset_description_1'],
                    $_POST['asset_description_2'],
                    $_POST['specifications'],
                    $_POST['brand'],
                    $_POST['model'],
                    $_POST['serial_number'],
                    $_POST['location'],
                    $_POST['accountable_individual'],
                    (isset($_POST['rr_no']) && $_POST['rr_no'] !== '' ? (strpos(
                        $_POST['rr_no'],
                        'RR'
                    ) === 0 ? $_POST['rr_no'] : 'RR' . $_POST['rr_no']) : null),
                    $_POST['date_acquired'] ?? null,
                    $_POST['remarks'],
                    $_POST['equipment_id']
                ];

                // [Cascade Fix 2025-05-16T09:52:12+08:00] Always update date_modified when saving, even if no other fields change
                $stmt = $pdo->prepare("UPDATE equipment_details SET 
            asset_tag = ?, asset_description_1 = ?, asset_description_2 = ?, specifications = ?, 
            brand = ?, model = ?, serial_number = ?, location = ?, accountable_individual = ?, 
            rr_no = ?, date_acquired = ?, remarks = ?, date_modified = NOW() WHERE id = ?");
                $stmt->execute($values);

                unset(
                    $oldEquipment['id'],
                    $oldEquipment['is_disabled'],
                    $oldEquipment['date_created']
                );
                $oldValue = json_encode($oldEquipment);
                $newValues = json_encode([
                    'asset_tag' => $_POST['asset_tag'],
                    'asset_description_1' => $_POST['asset_description_1'],
                    'asset_description_2' => $_POST['asset_description_2'],
                    'specifications' => $_POST['specifications'],
                    'brand' => $_POST['brand'],
                    'model' => $_POST['model'],
                    'serial_number' => $_POST['serial_number'],
                    'location' => $_POST['location'],
                    'accountable_individual' => $_POST['accountable_individual'],
                    'rr_no' => $_POST['rr_no'],
                    'date_acquired' => $_POST['date_acquired'] ?? null,
                    'remarks' => $_POST['remarks']
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
                $pdo->commit();
                $response['status'] = 'success';
                $response['message'] = 'Equipment updated successfully';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $response['status'] = 'error';
                $response['message'] = $e->getMessage();
            }
            ob_clean(); // Clear any prior output
            echo json_encode($response);
            exit;
        case 'remove':
            if (!$canDelete) {
                $response['status'] = 'error';
                $response['message'] = 'You do not have permission to remove equipment details';
                echo json_encode($response);
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

                // 1. Update equipment_details to set is_disabled = 1
                $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 1 WHERE id = ?");
                $stmt->execute([$_POST['details_id']]);
                $detailsData['is_disabled'] = 1;
                $newValue = json_encode($detailsData);

                // 2. Update equipment_status to set is_disabled = 1 for the same asset tag
                $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE asset_tag = ?");
                $statusStmt->execute([$assetTag]);
                $statusRowsAffected = $statusStmt->rowCount();

                // 3. Update equipment_location to set is_disabled = 1 for the same asset tag
                $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 1 WHERE asset_tag = ?");
                $locationStmt->execute([$assetTag]);
                $locationRowsAffected = $locationStmt->rowCount();

                // Log the main equipment details deletion
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

                // Log the cascaded deletions if any rows were affected
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
                $response['status'] = 'success';
                $response['message'] = 'Equipment Details and related records removed successfully.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $response['status'] = 'error';
                $response['message'] = 'Error removing details: ' . $e->getMessage();
            }
            ob_clean();
            echo json_encode($response);
            exit;
    }
    exit;
}

// -------------------------
// Non-AJAX: Page Setup
// -------------------------
$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? '';
unset($_SESSION['errors'], $_SESSION['success']);

// Check if equipment details have been updated from equipment_location.php
$forceRefresh = false;
if (isset($_SESSION['equipment_details_updated']) && $_SESSION['equipment_details_updated'] === true) {
    $forceRefresh = true;
    $updatedAssetTag = $_SESSION['updated_asset_tag'] ?? '';
    // Clear the session flags
    unset($_SESSION['equipment_details_updated'], $_SESSION['updated_asset_tag']);
    // Add success message
    $success = 'Equipment details updated successfully from location changes.';
}

try {
    // Modified query to join with receive_report table to validate RR numbers
    $stmt = $pdo->query("SELECT ed.id, ed.asset_tag, ed.asset_description_1, ed.asset_description_2,
                         ed.specifications, ed.brand, ed.model, ed.serial_number, ed.date_acquired, ed.location, 
                         ed.accountable_individual, 
                         CASE WHEN rr.is_disabled = 1 OR rr.rr_no IS NULL THEN NULL ELSE ed.rr_no END as rr_no,
                         ed.remarks, ed.date_created, ed.date_modified 
                         FROM equipment_details ed
                         LEFT JOIN receive_report rr ON ed.rr_no = rr.rr_no
                         WHERE ed.is_disabled = 0 
                         ORDER BY ed.id DESC");
    $equipmentDetails = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Details: " . $e->getMessage();
}

function safeHtml($value)
{
    return htmlspecialchars($value ?? 'N/A');
}
ob_end_clean();

// Regular page load continues here...
include('../../general/header.php');

// Initialize RBAC
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;

// Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Management', 'View');

// Button flags
$canCreate = $rbac->hasPrivilege('Equipment Management', 'Create');
$canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
$canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');

// Initialize response array
$response = array('status' => '', 'message' => '');

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

// GET deletion (if applicable)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    // Check if user has Remove privilege
    if (!$rbac->hasPrivilege('Equipment Management', 'Remove')) {
        $_SESSION['errors'] = ["You do not have permission to delete equipment details"];
        header("Location: equipment_details.php");
        exit;
    }

    $id = $_GET['id'];
    try {
        // Get status details before deletion for audit log
        $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
        $stmt->execute([$id]);
        $detailsData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($detailsData) {
            // Begin transaction
            $pdo->beginTransaction();

            // Get the asset tag from the equipment details
            $assetTag = $detailsData['asset_tag'];

            // Set current user for audit logging
            $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);

            // Prepare audit log data for equipment details
            $oldValue = json_encode([
                'equipment_details_id' => $detailsData['id'],
                'asset_tag' => $detailsData['asset_tag'],
                'asset_description_1' => $detailsData['asset_description_1'],
                'asset_description_2' => $detailsData['asset_description_2'],
                'specifications' => $detailsData['specifications'],
                'brand' => $detailsData['brand'],
                'model' => $detailsData['model'],
                'serial_number' => $detailsData['serial_number'],
                'location' => $detailsData['location'],
                'accountable_individual' => $detailsData['accountable_individual'],
                'rr_no' => $detailsData['rr_no'],
                'remarks' => $detailsData['remarks']
            ]);

            // Insert into audit_log for equipment details
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID,
                    EntityID,
                    Module,
                    Action,
                    Details,
                    OldVal,
                    NewVal,
                    Status,
                    Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $auditStmt->execute([
                $_SESSION['user_id'],
                $detailsData['id'],
                'Equipment Details',
                'Remove',
                'Equipment details have been removed',
                $oldValue,
                null,
                'Successful'
            ]);

            // 1. Update equipment_details to set is_disabled = 1
            $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$id]);

            // 2. Update equipment_status to set is_disabled = 1 for the same asset tag
            $statusStmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 1 WHERE asset_tag = ?");
            $statusStmt->execute([$assetTag]);
            $statusRowsAffected = $statusStmt->rowCount();

            // 3. Update equipment_location to set is_disabled = 1 for the same asset tag
            $locationStmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 1 WHERE asset_tag = ?");
            $locationStmt->execute([$assetTag]);
            $locationRowsAffected = $locationStmt->rowCount();

            // Log the cascaded deletions if any rows were affected
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

            // Commit transaction
            $pdo->commit();

            $_SESSION['success'] = "Equipment Details and related records deleted successfully.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error deleting Equipment Details: " . $e->getMessage();
    }
    header("Location: equipment_details.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Details Management</title>
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
        rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        th.sortable.asc::after {
            content: " \2191";
            /* Unicode up arrow */
        }

        th.sortable.desc::after {
            content: " \2193";
            /* Unicode down arrow */
        }


        /* Pagination styling */
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 0.25rem;
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

        /* Hide pagination when no results or only one page */
        .pagination:empty {
            display: none;
        }

        /* Ensure Select2 input matches form-control size and font */
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

        /* Make Select2 match Bootstrap form-control height */
        .select2-container .select2-selection {
            min-height: 38px !important;
        }

        /* Fix padding for the select2 input */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-top: 2px;
        }

        /* Adjust the clear button position */
        .select2-container--default .select2-selection--single .select2-selection__clear {
            margin-right: 20px;
        }

        /* Make the dropdown match Bootstrap styling */
        .select2-dropdown {
            border-color: #ced4da;
            border-radius: 0.375rem;
        }

        /* Make the search field match Bootstrap input */
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            padding: 0.375rem 0.75rem;
        }

        .filtered-out {
            display: none !important;
        }

        /* Style for highlighting updated rows */
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

        /* Filter form styles */
        #equipmentFilterForm .row {
            margin-bottom: 10px;
        }

        #dateInputsContainer {
            padding-top: 10px;
            padding-bottom: 10px;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>

<body>
    <?php
    include '../../general/header.php';
    include '../../general/sidebar.php';
    include '../../general/footer.php';
    ?>

    <div class="main-container">
        <header class="main-header">
            <h1> Equipment Details Management</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between 
align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Equipment Details</h2>
            </div>
            <div class="card-body">
                <div class="container-fluid px-0">
                    <form id="equipmentFilterForm" class="mb-4">
                        <div class="row g-3">
                            <div class="col-auto">
                                <?php if ($canCreate): ?>
                                    <label class="form-label d-none d-md-block">&nbsp;</label>
                                    <button type="button" class="btn btn-dark" data-bs-toggle="modal"
                                        data-bs-target="#addEquipmentModal">
                                        <i class="bi bi-plus-lg"></i> Create Equipment
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label for="filterEquipment" class="form-label">Equipment Type</label>
                                <select class="form-select" id="filterEquipment" name="filterEquipment">
                                    <option value="">All Equipment Types</option>
                                    <?php
                                    $equipmentTypes = array_unique(array_column(
                                        $equipmentDetails,
                                        'asset_description_1'
                                    ));
                                    foreach ($equipmentTypes as $type) {
                                        if (!empty($type)) {
                                            echo "<option value='" . safeHtml($type) . "'>" .
                                                safeHtml($type) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="dateFilter" class="form-label">Date Filter</label>
                                <select class="form-select" id="dateFilter" name="dateFilter">
                                    <option value="">No Date Filter</option>
                                    <option value="desc">Newest to Oldest</option>
                                    <option value="asc">Oldest to Newest</option>
                                    <option value="month_year">Month-Year</option>
                                    <option value="year_range">Year Range</option>
                                    <option value="mdy">Month-Day-Year</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="searchEquipment" class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" id="searchEquipment" name="searchEquipment" class="form-control"
                                        placeholder="Search equipment...">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                </div>
                            </div>
                            <div class="col-6 col-md-2 d-grid">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button type="button" id="applyFilters" class="btn btn-dark">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                            </div>
                            <div class="col-6 col-md-2 d-grid">
                                <label class="form-label d-none d-md-block">&nbsp;</label>
                                <button type="button" id="clearFilters" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                        </div>

                        <div id="dateInputsContainer" class="row g-3 mt-2 d-none">
                            <!-- Month-Year Selector -->
                            <div class="col-md-6 date-filter date-month_year d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="monthSelect" class="form-label">Month</label>
                                        <select class="form-select" id="monthSelect" name="monthSelect">
                                            <option value="">Select Month</option>
                                            <?php
                                            $months = [
                                                'January',
                                                'February',
                                                'March',
                                                'April',
                                                'May',
                                                'June',
                                                'July',
                                                'August',
                                                'September',
                                                'October',
                                                'November',
                                                'December'
                                            ];
                                            foreach ($months as $index => $month) {
                                                echo "<option value='" . ($index + 1) . "'>" . $month .
                                                    "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="yearSelect" class="form-label">Year</label>
                                        <select class="form-select" id="yearSelect" name="yearSelect">
                                            <option value="">Select Year</option>
                                            <?php
                                            $currentYear = date('Y');
                                            for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                                echo "<option value='" . $year . "'>" . $year . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Year Range Selector -->
                            <div class="col-md-6 date-filter date-year_range d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="yearFrom" class="form-label">From Year</label>
                                        <select class="form-select" id="yearFrom" name="yearFrom">
                                            <option value="">Select Year</option>
                                            <?php
                                            for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                                echo "<option value='" . $year . "'>" . $year . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="yearTo" class="form-label">To Year</label>
                                        <select class="form-select" id="yearTo" name="yearTo">
                                            <option value="">Select Year</option>
                                            <?php
                                            for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                                echo "<option value='" . $year . "'>" . $year . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Date Range Picker -->
                            <div class="col-md-6 date-filter date-range d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="dateFrom" class="form-label">From Date</label>
                                        <input type="date" class="form-control" id="dateFrom" name="dateFrom">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="dateTo" class="form-label">To Date</label>
                                        <input type="date" class="form-control" id="dateTo" name="dateTo">
                                    </div>
                                </div>
                            </div>

                            <!-- MDY Picker -->
                            <div class="col-md-6 date-filter date-mdy d-none">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label for="mdyMonth" class="form-label">Month</label>
                                        <select class="form-select" id="mdyMonth" name="mdyMonth">
                                            <option value="">Month</option>
                                            <?php
                                            foreach ($months as $index => $month) {
                                                echo "<option value='" . ($index + 1) . "'>" . $month .
                                                    "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="mdyDay" class="form-label">Day</label>
                                        <select class="form-select" id="mdyDay" name="mdyDay">
                                            <option value="">Day</option>
                                            <?php
                                            for ($day = 1; $day <= 31; $day++) {
                                                echo "<option value='" . $day . "'>" . $day . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="mdyYear" class="form-label">Year</label>
                                        <select class="form-select" id="mdyYear" name="mdyYear">
                                            <option value="">Year</option>
                                            <?php
                                            for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                                echo "<option value='" . $year . "'>" . $year . "</option>";
                                            }
                                            ?>
                                        </select>
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
                                <th class="sortable" data-column="14">Remarks</th>
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
    $acq = $equipment['date_acquired'] ?? '';
    if (empty($acq) || $acq === '0000-00-00' || !strtotime($acq)) {
        echo '';
    } else {
        echo date('Y-m-d', strtotime($acq));
    }
?></td>
                                        <td class="d-none"><?= !empty($equipment['date_created']) ? date('Y-m-d 
H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                                        <td class="d-none"><?= !empty($equipment['date_modified']) ? date('Y-m-d 
H:i', strtotime($equipment['date_modified'])) : ''; ?></td>
                                        <td><?= safeHtml((strpos($equipment['rr_no'] ?? '', 'RR') ===
                                                0 ? $equipment['rr_no'] : ('RR' . $equipment['rr_no']))); ?></td>
                                        <td><?= safeHtml($equipment['location']); ?></td>
                                        <td><?= safeHtml($equipment['accountable_individual']); ?></td>
                                        <td><?= safeHtml($equipment['remarks']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($canModify): ?>
                                                    <button class="btn btn-outline-info btn-sm 
edit-equipment"
                                                        data-id="<?= safeHtml($equipment['id']); ?>"
                                                        data-asset="<?=
                                                                    safeHtml($equipment['asset_tag']); ?>"
                                                        data-desc1="<?=
                                                                    safeHtml($equipment['asset_description_1']); ?>"
                                                        data-desc2="<?=
                                                                    safeHtml($equipment['asset_description_2']); ?>"
                                                        data-spec="<?=
                                                                    safeHtml($equipment['specifications']); ?>"
                                                        data-brand="<?=
                                                                    safeHtml($equipment['brand']); ?>"
                                                        data-model="<?=
                                                                    safeHtml($equipment['model']); ?>"
                                                        data-serial="<?=
                                                                        safeHtml($equipment['serial_number']); ?>"
                                                        data-date-acquired="<?=
                                                                            safeHtml($equipment['date_acquired']); ?>"
                                                        data-location="<?=
                                                                        safeHtml($equipment['location']); ?>"
                                                        data-accountable="<?=
                                                                            safeHtml($equipment['accountable_individual']); ?>"
                                                        data-rr="<?= safeHtml($equipment['rr_no']);
                                                                    ?>"
                                                        data-date="<?=
                                                                    safeHtml($equipment['date_created']); ?>"
                                                        data-remarks="<?=
                                                                        safeHtml($equipment['remarks']); ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($canDelete): ?>
                                                    <button class="btn btn-outline-danger btn-sm 
remove-equipment"
                                                        data-id="<?= safeHtml($equipment['id']); ?>">
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
                                            <i class="bi bi-info-circle me-2"></i> No Equipment
                                            Details found. Click on "Create Equipment" to add a new entry.
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
                                Showing <span id="currentPage">1</span> to <span
                                    id="rowsPerPage">10</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex 
align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <select id="rowsPerPageSelect" class="form-select" style="width: 
auto;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>
                                <button id="nextPage" class="btn btn-outline-primary d-flex 
align-items-center gap-1">
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
        </section>
    </div>

    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title">Add New Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="addEquipmentForm">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag <span style="color: 
red;">*</span></label>
                            <select class="form-select" name="asset_tag" id="add_equipment_asset_tag"
                                required style="width: 100%;">
                                <option value="">Select or type Asset Tag</option>
                                <?php
                                // Fetch unique asset tags from equipment_location and equipment_status
                                $assetTags = [];
                                $stmt1 = $pdo->query("SELECT DISTINCT asset_tag FROM 
equipment_location WHERE is_disabled = 0");
                                $assetTags = array_merge(
                                    $assetTags,
                                    $stmt1->fetchAll(PDO::FETCH_COLUMN)
                                );
                                $stmt2 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_status 
WHERE is_disabled = 0");
                                $assetTags = array_merge(
                                    $assetTags,
                                    $stmt2->fetchAll(PDO::FETCH_COLUMN)
                                );
                                $assetTags = array_unique(array_filter($assetTags));
                                sort($assetTags);
                                foreach ($assetTags as $tag) {
                                    echo '<option value="' . htmlspecialchars($tag) . '">' .
                                        htmlspecialchars($tag) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="asset_description_1" class="form-label">Asset
                                        Description 1</label>
                                    <input type="text" class="form-control"
                                        name="asset_description_1">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="asset_description_2" class="form-label">Asset
                                        Description 2</label>
                                    <input type="text" class="form-control"
                                        name="asset_description_2">
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
                                    <label for="serial_number" class="form-label">Serial Number
                                    </label>
                                    <input type="text" class="form-control" name="serial_number">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="add_rr_no" class="form-label">RR#</label>
                                    <select class="form-select rr-select2" name="rr_no"
                                        id="add_rr_no" style="width: 100%;">
                                        <option value="">Select or search RR Number</option>
                                        <?php
                                        // Fetch active RR numbers for dropdown
                                        $stmtRR = $pdo->prepare("SELECT rr_no FROM receive_report 
WHERE is_disabled = 0 ORDER BY rr_no DESC");
                                        $stmtRR->execute();
                                        $rrList = $stmtRR->fetchAll(PDO::FETCH_COLUMN);
                                        foreach ($rrList as $rrNo) {
                                            echo '<option value="' . htmlspecialchars($rrNo) . '">' .
                                                htmlspecialchars($rrNo) . '</option>';
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
                                <input type="date" class="form-control" name="date_acquired" id="edit_date_acquired" data-autofill="false">
                                <small class="text-muted">This field will be auto-filled when an RR# is selected</small>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="edit_location" class="form-label">Location</label>
                                <input type="text" class="form-control" name="location"
                                    id="edit_location" data-autofill="false">
                                <small class="text-muted">This field will be autofilled when an
                                    Asset Tag is selected</small>
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="edit_accountable_individual"
                                    class="form-label">Accountable Individual</label>
                                <input type="text" class="form-control"
                                    name="accountable_individual" id="edit_accountable_individual" data-autofill="false">
                                <small class="text-muted">This field will be autofilled when an
                                    Asset Tag is selected</small>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" id="edit_remarks"
                            rows="3"></textarea>
                    </div>
                    <div class="mb-3 text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                            style="margin-right: 4px;">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
                                    <label for="edit_location" class="form-label">Location</label>
                                    <input type="text" class="form-control" name="location"
                                        id="edit_location" data-autofill="false">
                                    <small class="text-muted">This field will be autofilled when an
                                        Asset Tag is selected</small>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_accountable_individual"
                                        class="form-label">Accountable Individual</label>
                                    <input type="text" class="form-control"
                                        name="accountable_individual" id="edit_accountable_individual" data-autofill="false">
                                    <small class="text-muted">This field will be autofilled when an
                                        Asset Tag is selected</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks"
                                rows="3"></textarea>
                        </div>
                        <div class="mb-3 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                                style="margin-right: 4px;">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteEDModal" tabindex="-1" data-bs-backdrop="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title">Confirm Removal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    Are you sure you want to remove this Equipment Detail?
                </div>
                <div class="modal-footer p-4">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Remove</button>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js"
        defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/toast.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/asset_tag_autofill.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/rr_autofill.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for RR# dropdowns
            $('#add_rr_no').select2({
                placeholder: 'Select or search RR Number',
                allowClear: true,
                width: '100%',
                tags: true, // Allow new entries
                dropdownParent: $('#addEquipmentModal'), // Attach to modal for proper positioning
                minimumResultsForSearch: 0,
                dropdownPosition: 'below', // Always show dropdown below input
                createTag: function(params) {
                    // Only allow non-empty, non-duplicate RR numbers (numbers only)
                    var term = $.trim(params.term);
                    if (term === '') return null;
                    var exists = false;
                    $('#add_rr_no option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) exists = true;
                    });
                    // Only allow numbers for new tags
                    if (!/^[0-9]+$/.test(term)) {
                        return null;
                    }
                    return exists ? null : {
                        id: term,
                        text: term
                    };
                }
            }).on('select2:select', function(e) {
                var rrNo = e.params.data.id;
                var isNewOption = e.params.data.newOption;

                // If this is a newly created option (doesn't exist in the database)
                if (isNewOption || !e.params.data.element) {
                    // Create a new RR entry in the database
                    $.ajax({
                        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'create_rr.php',
                        method: 'POST',
                        data: {
                            action: 'create_rr',
                            rr_no: rrNo,
                            date_created: new Date().toISOString().slice(0, 19).replace('T', ' ')
                        },
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                showToast('New RR# created successfully', 'success');
                                // Now try to get any related charge invoice data
                                fetchRRInfo(rrNo, 'add', false);
                            } else {
                                showToast(response.message || 'Failed to create RR#', 'warning');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error creating RR:', error);
                            showToast('Error creating RR entry', 'error');
                        }
                    });
                } else {
                    // For existing RR numbers, just fetch the data
                    fetchRRInfo(rrNo, 'add', true);
                }
            });

            $('#edit_rr_no').select2({
                placeholder: 'Select or search RR Number',
                allowClear: true,
                width: '100%',
                tags: true,
                dropdownParent: $('#editEquipmentModal'),
                minimumResultsForSearch: 0,
                createTag: function(params) {
                    // Only allow non-empty, non-duplicate RR numbers (numbers only)
                    var term = $.trim(params.term);
                    if (term === '') return null;
                    var exists = false;
                    $('#edit_rr_no option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) exists = true;
                    });
                    // Only allow numbers for new tags
                    if (!/^[0-9]+$/.test(term)) {
                        return null;
                    }
                    return exists ? null : {
                        id: term,
                        text: term
                    };
                }
            }).on('select2:select', function(e) {
                var rrNo = e.params.data.id;
                var isNewOption = e.params.data.newOption;

                // If this is a newly created option (doesn't exist in the database)
                if (isNewOption || !e.params.data.element) {
                    // Create a new RR entry in the database
                    $.ajax({
                        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'create_rr.php',
                        method: 'POST',
                        data: {
                            action: 'create_rr',
                            rr_no: rrNo,
                            date_created: new Date().toISOString().slice(0, 19).replace('T', ' ')
                        },
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                showToast('New RR# created successfully', 'success');
                                // Now try to get any related charge invoice data
                                fetchRRInfo(rrNo, 'edit', false);
                            } else {
                                showToast(response.message || 'Failed to create RR#', 'warning');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error creating RR:', error);
                            showToast('Error creating RR entry', 'error');
                        }
                    });
                } else {
                    // For existing RR numbers, just fetch the data
                    fetchRRInfo(rrNo, 'edit', true);
                }
            });

            // Initialize Select2 for asset tag dropdowns
            $('#add_equipment_asset_tag').select2({
                placeholder: 'Select or type Asset Tag',
                allowClear: true,
                width: '100%',
                tags: true, // This allows adding new tags/values
                dropdownParent: $('#addEquipmentModal'),
                minimumResultsForSearch: 0,
                createTag: function(params) {
                    var term = $.trim(params.term);
                    if (term === '') return null;

                    // Check if this tag already exists
                    var exists = false;
                    $('#add_equipment_asset_tag option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) {
                            exists = true;
                        }
                    });

                    // If it doesn't exist, allow creating it
                    return exists ? null : {
                        id: term,
                        text: term,
                        newTag: true
                    };
                }
            }).on('select2:select', function(e) {
                var assetTag = e.params.data.id;

                // If this is a valid asset tag, try to fetch related info
                if (assetTag) {
                    console.log('Asset tag selected in add modal:', assetTag);

                    // Reset fields before fetching new asset tag info
                    const $accountableField = $('input[name="accountable_individual"]');
                    const $locationField = $('input[name="location"]');

                    // If fields were previously autofilled, reset them first
                    if ($accountableField.attr('data-autofill') === 'true') {
                        $accountableField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                    }

                    if ($locationField.attr('data-autofill') === 'true') {
                        $locationField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                    }

                    // Only fetch info if this is a pre-existing tag
                    if (!e.params.data.newTag) {
                        // Fetch and autofill data based on asset tag
                        fetchAssetTagInfo(assetTag, 'add', true);
                    }
                }
            });

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

                    // Check if this tag already exists
                    var exists = false;
                    $('#edit_equipment_asset_tag option').each(function() {
                        if ($(this).text().toLowerCase() === term.toLowerCase()) {
                            exists = true;
                        }
                    });

                    // If it doesn't exist, allow creating it
                    return exists ? null : {
                        id: term,
                        text: term,
                        newTag: true
                    };
                }
            }).on('select2:select', function(e) {
                var assetTag = e.params.data.id;

                // If this is a valid asset tag, try to fetch related info
                if (assetTag) {
                    console.log('Asset tag selected in edit modal:', assetTag);

                    // Reset fields before fetching new asset tag info
                    const $accountableField = $('#edit_accountable_individual');
                    const $locationField = $('#edit_location');

                    // If fields were previously autofilled, reset them first
                    if ($accountableField.attr('data-autofill') === 'true') {
                        $accountableField.val('').attr('data-autofill', 'false');
                    }

                    if ($locationField.attr('data-autofill') === 'true') {
                        $locationField.val('').attr('data-autofill', 'false');
                    }

                    // Only fetch info if this is a pre-existing tag
                    if (!e.params.data.newTag) {
                        // Fetch and autofill data based on asset tag
                        fetchAssetTagInfo(assetTag, 'edit', true);
                    }
                }
            });
        });
    </script>
    <script>
        // Custom filterTable function for equipment details
        function filterEquipmentTable() {
            console.log('----------- CUSTOM FILTER FUNCTION CALLED -----------');

            // Get filter values
            const searchText = $('#searchEquipment').val() || '';
            const filterEquipment = $('#filterEquipment').val() || '';
            const dateFilterType = $('#dateFilter').val() || '';

            // Month-Year filter values
            const selectedMonth = $('#monthSelect').val() || '';
            const selectedYear = $('#yearSelect').val() || '';

            // Date Range filter values
            const dateFrom = $('#dateFrom').val() || '';
            const dateTo = $('#dateTo').val() || '';

            // Year Range filter values
            const yearFrom = $('#yearFrom').val() || '';
            const yearTo = $('#yearTo').val() || '';

            // MDY filter values
            const mdyMonth = $('#mdyMonth').val() || '';
            const mdyDay = $('#mdyDay').val() || '';
            const mdyYear = $('#mdyYear').val() || '';

            // Debug output filter values
            console.log('FILTER VALUES:', {
                searchText,
                filterEquipment,
                dateFilterType,
                selectedMonth,
                selectedYear,
                dateFrom,
                dateTo,
                yearFrom,
                yearTo,
                mdyMonth,
                mdyDay,
                mdyYear
            });

            // Get all table rows directly
            const tableRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
            console.log(`Total rows in table: ${tableRows.length}`);

            // If no rows, show message and exit
            if (tableRows.length === 0) {
                console.log('No rows found in table');
                return;
            }

            // Create filtered array
            const filteredRows = [];

            // Apply filters to each row
            tableRows.forEach((row, index) => {
                // Skip non-data rows (like messages)
                if (row.id === 'noResultsMessage' || row.id === 'initialFilterMessage') {
                    return;
                }

                try {
                    // Get all cells in the row
                    const cells = Array.from(row.cells || []);

                    if (cells.length === 0) {
                        console.log(`Row ${index} has no cells`);
                        return;
                    }

                    // Extract text content from all cells for easier access
                    const cellTexts = [];
                    let equipmentTypeText = '';
                    let dateText = '';

                    // Process each cell
                    cells.forEach((cell, cellIndex) => {
                        const text = (cell.textContent || '').trim();
                        cellTexts.push(text.toLowerCase());

                        // Store important column values
                        if (cellIndex === 2) equipmentTypeText = text.toLowerCase(); // Equipment Type (Col 3)
                        if (cellIndex === 9) dateText = text; // Created Date (Col 10)
                    });

                    // Full row text for search
                    const rowText = cellTexts.join(' ');

                    // 1. SEARCH FILTER - Check if search text is in any cell
                    const searchMatch = !searchText || rowText.includes(searchText.toLowerCase());

                    // 2. EQUIPMENT TYPE FILTER - Check equipment type
                    let equipmentMatch = true;
                    if (filterEquipment && filterEquipment !== '') {
                        const filterValue = filterEquipment.toLowerCase();
                        equipmentMatch = equipmentTypeText === filterValue;

                        // Debug for first few rows
                        if (index < 5) {
                            console.log(`Row ${index} equipment: "${equipmentTypeText}" vs filter: "${filterValue}" = ${equipmentMatch}`);
                        }
                    }

                    // 3. DATE FILTER - Apply date filter if selected
                    let dateMatch = true;
                    if (dateFilterType && dateText) {
                        const date = new Date(dateText);
                        if (!isNaN(date.getTime())) { // Valid date
                            if (dateFilterType === 'month_year' && selectedMonth && selectedYear) {
                                // Month-Year filter
                                const month = date.getMonth() + 1; // getMonth is 0-indexed
                                const year = date.getFullYear();
                                dateMatch = (month === parseInt(selectedMonth)) && (year === parseInt(selectedYear));
                                if (index < 5) {
                                    console.log(`Row ${index} month-year filter: ${month}/${year} vs ${selectedMonth}/${selectedYear} = ${dateMatch}`);
                                }
                            } else if (dateFilterType === 'range' && dateFrom && dateTo) {
                                // Date Range filter
                                const fromDate = new Date(dateFrom);
                                const toDate = new Date(dateTo);
                                toDate.setHours(23, 59, 59); // End of day
                                dateMatch = date >= fromDate && date <= toDate;
                                if (index < 5) {
                                    console.log(`Row ${index} date range filter: ${date} between ${fromDate} and ${toDate} = ${dateMatch}`);
                                }
                            } else if (dateFilterType === 'year_range' && yearFrom && yearTo) {
                                // Year Range filter
                                const year = date.getFullYear();
                                dateMatch = (year >= parseInt(yearFrom)) && (year <= parseInt(yearTo));
                                if (index < 5) {
                                    console.log(`Row ${index} year range filter: ${year} between ${yearFrom} and ${yearTo} = ${dateMatch}`);
                                }
                            } else if (dateFilterType === 'mdy' && mdyMonth && mdyDay && mdyYear) {
                                // MDY filter (exact date match)
                                const month = date.getMonth() + 1;
                                const day = date.getDate();
                                const year = date.getFullYear();
                                dateMatch = (month === parseInt(mdyMonth)) &&
                                    (day === parseInt(mdyDay)) &&
                                    (year === parseInt(mdyYear));
                                if (index < 5) {
                                    console.log(`Row ${index} MDY filter: ${month}/${day}/${year} vs ${mdyMonth}/${mdyDay}/${mdyYear} = ${dateMatch}`);
                                }
                            }
                        }
                    }

                    // Final decision - row should be shown if it matches all filters
                    const shouldShow = searchMatch && equipmentMatch && dateMatch;

                    if (shouldShow) {
                        filteredRows.push(row);
                        $(row).show();
                    } else {
                        $(row).hide();
                    }

                } catch (error) {
                    console.error(`Error processing row ${index}:`, error);
                }
            });

            console.log(`Filtered rows: ${filteredRows.length} of ${tableRows.length}`);

            // Hide all rows first
            $(tableRows).hide();

            // Store filtered rows in global variable for pagination
            window.allRows = tableRows;
            window.filteredRows = filteredRows;

            // Sort if needed (date ascending/descending)
            if (dateFilterType === 'asc' || dateFilterType === 'desc') {
                filteredRows.sort((a, b) => {
                    const dateA = a.cells && a.cells[9] ? new Date(a.cells[9].textContent || '') : new Date(0);
                    const dateB = b.cells && b.cells[9] ? new Date(b.cells[9].textContent || '') : new Date(0);
                    return dateFilterType === 'asc' ? dateA - dateB : dateB - dateA;
                });

                // Re-order the DOM elements based on sort
                const tbody = document.getElementById('equipmentTable');
                filteredRows.forEach(row => {
                    tbody.appendChild(row); // Move to the end in sorted order
                });
            }

            // Reset to page 1 after filtering
            window.currentPage = 1;

            // Initialize or reset pagination with the filtered rows
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalRows = window.filteredRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);

            // Update display counts
            $('#currentPage').text(window.currentPage);
            $('#rowsPerPage').text(rowsPerPage);
            $('#totalRows').text(totalRows);

            // Rebuild pagination UI
            buildPaginationButtons(totalPages);
            updatePaginationButtons();

            // Show only the rows for the current page
            showCurrentPageRows();

            // Show "no results" message if no matches found
            if (filteredRows.length === 0) {
                // Remove any existing no results message
                $('#noResultsMessage').remove();

                // Create and insert a "no results" message
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
                // Remove any "no results" message if we have matches
                $('#noResultsMessage').remove();
            }

            console.log('----------- CUSTOM FILTER FUNCTION COMPLETED -----------');

            return filteredRows;
        }

        // Initialize pagination
        function initPagination() {
            // Get pagination configuration
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalRows = window.filteredRows ? window.filteredRows.length : 0;
            const totalPages = Math.ceil(totalRows / rowsPerPage);

            // Set current page to 1 when initializing (if not already set)
            window.currentPage = window.currentPage || 1;

            // If current page is beyond total pages, reset to page 1
            if (window.currentPage > totalPages && totalPages > 0) {
                window.currentPage = 1;
            }

            // Update display counts
            $('#currentPage').text(window.currentPage);
            $('#rowsPerPage').text(rowsPerPage);
            $('#totalRows').text(totalRows);

            // Update pagination buttons
            updatePaginationButtons();

            // Build page number buttons
            buildPaginationButtons(totalPages);

            // Show only the rows for the current page
            showCurrentPageRows();

            console.log(`Pagination initialized: ${totalRows} rows, ${rowsPerPage} per page, ${totalPages} total pages, current page: ${window.currentPage}`);
        }

        // Update the Previous/Next button states
        function updatePaginationButtons() {
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);

            // Disable/enable Previous button
            $('#prevPage').prop('disabled', window.currentPage <= 1);

            // Disable/enable Next button
            $('#nextPage').prop('disabled', window.currentPage >= totalPages);
        }

        // Build the pagination number buttons
        function buildPaginationButtons(totalPages) {
            const $pagination = $('#pagination');
            $pagination.empty();

            // No need for pagination if only one page
            if (totalPages <= 1) {
                return;
            }

            // Calculate range of pages to show
            let startPage = Math.max(1, window.currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);

            // Adjust start if end is maxed out
            if (endPage === totalPages) {
                startPage = Math.max(1, endPage - 4);
            }

            // Add "First" button if not on first page
            if (window.currentPage > 1) {
                $pagination.append(`
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="1"><i class="bi bi-chevron-double-left"></i></a>
                    </li>
                `);
            }

            // Add "Previous" page button
            if (window.currentPage > 1) {
                $pagination.append(`
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="${window.currentPage - 1}"><i class="bi bi-chevron-left"></i></a>
                    </li>
                `);
            }

            // Add ellipsis if needed at start
            if (startPage > 1) {
                $pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }

            // Add page numbers
            for (let i = startPage; i <= endPage; i++) {
                $pagination.append(`
                    <li class="page-item ${window.currentPage === i ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `);
            }

            // Add ellipsis if needed at end
            if (endPage < totalPages) {
                $pagination.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
            }

            // Add "Next" page button
            if (window.currentPage < totalPages) {
                $pagination.append(`
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="${window.currentPage + 1}"><i class="bi bi-chevron-right"></i></a>
                    </li>
                `);
            }

            // Add "Last" button if not on last page
            if (window.currentPage < totalPages) {
                $pagination.append(`
                    <li class="page-item">
                        <a class="page-link" href="#" data-page="${totalPages}"><i class="bi bi-chevron-double-right"></i></a>
                    </li>
                `);
            }

            // Add click handlers to page number buttons
            $('.page-link[data-page]').on('click', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                goToPage(page);
            });
        }

        // Go to a specific page
        function goToPage(page) {
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);

            // Validate page number
            if (page < 1 || page > totalPages) {
                return;
            }

            // Update current page
            window.currentPage = page;

            // Update display
            $('#currentPage').text(window.currentPage);

            // Update buttons
            updatePaginationButtons();
            buildPaginationButtons(totalPages);

            // Show rows for the current page
            showCurrentPageRows();
        }

        // Show only the rows for the current page
        function showCurrentPageRows() {
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const startIndex = (window.currentPage - 1) * rowsPerPage;
            const endIndex = startIndex + rowsPerPage;

            // Ensure filteredRows exists
            if (!window.filteredRows || window.filteredRows.length === 0) {
                console.log('No filtered rows to display');
                // Show "no results" message if there is one
                $('#noResultsMessage').show();
                return;
            }

            console.log(`Showing rows ${startIndex+1} to ${Math.min(endIndex, window.filteredRows.length)} of ${window.filteredRows.length} (Page ${window.currentPage})`);

            // Hide all rows first
            $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').hide();

            // Show only the rows for the current page
            window.filteredRows.slice(startIndex, endIndex).forEach(row => {
                $(row).show();
            });

            // Make sure "no results" message is visible if present
            $('#noResultsMessage').show();
        }

        // Setup events when document is ready
        $(document).ready(function() {
            console.log('Document ready - initializing equipment filters');

            // Initialize with all rows visible
            window.allRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
            window.filteredRows = [...window.allRows];
            window.currentPage = 1;

            // Initialize pagination
            initPagination();

            // Ensure pagination shows only first page on initial load and builds pagination buttons
            setTimeout(function() {
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
                const totalRows = window.filteredRows.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);

                // Force rebuild pagination buttons
                buildPaginationButtons(totalPages);
                showCurrentPageRows();
                updatePaginationButtons();

                console.log('Enforced pagination on initial load - Total pages:', totalPages);
            }, 300);

            // Apply Filter button click handler
            $('#applyFilters').off('click').on('click', function(e) {
                e.preventDefault();
                console.log('Filter button clicked - applying filters');
                filterEquipmentTable();
            });

            // Clear filters button handler
            $('#clearFilters').off('click').on('click', function(e) {
                e.preventDefault();
                console.log('Clear button clicked - resetting filters');

                // Reset all filter inputs
                $('#searchEquipment').val('');

                // Reset select2 dropdown
                if ($('#filterEquipment').data('select2')) {
                    $('#filterEquipment').val(null).trigger('change');
                } else {
                    $('#filterEquipment').val('');
                }

                // Reset Date Filter
                $('#dateFilter').val('');

                // Reset Month-Year values
                $('#monthSelect').val('');
                $('#yearSelect').val('');

                // Reset Date Range values
                $('#dateFrom').val('');
                $('#dateTo').val('');

                // Reset Year Range values
                $('#yearFrom').val('');
                $('#yearTo').val('');

                // Reset MDY values
                $('#mdyMonth').val('');
                $('#mdyDay').val('');
                $('#mdyYear').val('');

                // Hide date inputs container
                $('#dateInputsContainer').addClass('d-none');
                $('.date-filter').addClass('d-none');

                // Reset to show all rows (unfiltered state)
                $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').show();

                // Remove any "no results" message
                $('#noResultsMessage').remove();

                // Reset the filtered rows to include all rows
                window.allRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
                window.filteredRows = [...window.allRows];

                // Reset to page 1
                window.currentPage = 1;

                // Update display counts
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
                const totalRows = window.filteredRows.length;
                $('#currentPage').text(window.currentPage);
                $('#rowsPerPage').text(rowsPerPage);
                $('#totalRows').text(totalRows);

                // Rebuild pagination with all rows
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                buildPaginationButtons(totalPages);
                updatePaginationButtons();

                // Show only the first page of rows
                showCurrentPageRows();

                console.log('Filters cleared, showing all rows');
            });

            // Previous page button
            $('#prevPage').on('click', function(e) {
                e.preventDefault();
                if (window.currentPage > 1) {
                    goToPage(window.currentPage - 1);
                }
            });

            // Next page button
            $('#nextPage').on('click', function(e) {
                e.preventDefault();
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
                const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);
                if (window.currentPage < totalPages) {
                    goToPage(window.currentPage + 1);
                }
            });

            // Rows per page select
            $('#rowsPerPageSelect').on('change', function() {
                // Reset to page 1 when changing rows per page
                window.currentPage = 1;
                initPagination();
            });

            // Date filter type change handler
            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();
                console.log('Date filter changed to:', filterType);

                // Hide all date containers first
                $('#dateInputsContainer').addClass('d-none');
                $('.date-filter').addClass('d-none');

                // Show appropriate containers based on selection
                if (filterType) {
                    $('#dateInputsContainer').removeClass('d-none');

                    // Show specific date input based on filter type
                    if (filterType === 'month_year') {
                        $('.date-month_year').removeClass('d-none');
                    } else if (filterType === 'year_range') {
                        $('.date-year_range').removeClass('d-none');
                    } else if (filterType === 'range') {
                        $('.date-range').removeClass('d-none');
                    } else if (filterType === 'mdy') {
                        $('.date-mdy').removeClass('d-none');
                    }
                }
            });

            // Initialize Select2 for equipment type filter
            try {
                if ($.fn.select2) {
                    $('#filterEquipment').select2({
                        placeholder: 'Filter Equipment Type',
                        allowClear: true,
                        width: '100%',
                        dropdownAutoWidth: true,
                        minimumResultsForSearch: 0
                    }).on('select2:select', function(e) {
                        console.log('Equipment type selected:', e.params.data);
                    }).on('select2:unselect', function() {
                        console.log('Equipment type filter cleared');
                    });

                    console.log('Select2 initialized for equipment filter');
                }
            } catch (e) {
                console.error('Error initializing Select2:', e);
            }

            // Set up column sorting
            $('.sortable').on('click', function() {
                const columnIndex = parseInt($(this).data('column'));
                const currentSortState = $(this).hasClass('asc') ? 'asc' : ($(this).hasClass('desc') ? 'desc' : '');
                let newSortState = '';

                // Toggle sort direction or start with ascending
                if (currentSortState === '') {
                    newSortState = 'asc';
                } else if (currentSortState === 'asc') {
                    newSortState = 'desc';
                } else {
                    newSortState = 'asc'; // Toggle back to ascending
                }

                // Update sort indicators (remove from all columns, add to current column)
                $('.sortable').removeClass('asc desc');
                $(this).addClass(newSortState);

                console.log(`Sorting column ${columnIndex} in ${newSortState} order`);

                // Sort the filtered rows
                window.filteredRows.sort(function(a, b) {
                    const aText = a.cells[columnIndex] ? (a.cells[columnIndex].textContent || '').trim() : '';
                    const bText = b.cells[columnIndex] ? (b.cells[columnIndex].textContent || '').trim() : '';

                    // Check if this is a date column (date formats like YYYY-MM-DD or YYYY-MM-DD HH:MM)
                    const isDate = /^\d{4}-\d{2}-\d{2}/.test(aText) || /^\d{4}-\d{2}-\d{2}/.test(bText);

                    // Check if this is a numeric column
                    const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
                    const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));
                    const isNumeric = !isNaN(aNum) && !isNaN(bNum) &&
                        aText.replace(/[^\d.-]/g, '') !== '' &&
                        bText.replace(/[^\d.-]/g, '') !== '';

                    let comparison = 0;

                    if (isDate) {
                        // Handle dates - convert to timestamps for comparison
                        const dateA = new Date(aText);
                        const dateB = new Date(bText);
                        comparison = dateA - dateB;
                    } else if (isNumeric) {
                        // Handle numbers
                        comparison = aNum - bNum;
                    } else {
                        // Handle strings (case-insensitive)
                        comparison = aText.toLowerCase().localeCompare(bText.toLowerCase());
                    }

                    // Apply sort direction
                    return newSortState === 'asc' ? comparison : -comparison;
                });

                // Re-order the table rows in the DOM based on the new sort order
                const tbody = document.getElementById('equipmentTable');
                window.filteredRows.forEach(row => {
                    tbody.appendChild(row);
                });

                // Reset to page 1 after sorting
                window.currentPage = 1;

                // Update UI to reflect changes
                $('#currentPage').text(window.currentPage);

                // Rebuild pagination
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
                const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);
                buildPaginationButtons(totalPages);
                updatePaginationButtons();

                // Show only the rows for current page
                showCurrentPageRows();
            });

            console.log('Equipment filters initialization complete');
        });
    </script>
    <script>
        $(document).ready(function() {
            // Edit Equipment
            $(document).on('click', '.edit-equipment', function() {
                var id = $(this).data('id');
                var asset = $(this).data('asset');
                var desc1 = $(this).data('desc1');
                var desc2 = $(this).data('desc2');
                var spec = $(this).data('spec');
                var brand = $(this).data('brand');
                var model = $(this).data('model');
                var serial = $(this).data('serial');
                var dateAcquired = $(this).data('date-acquired');
                var location = $(this).data('location');
                var accountable = $(this).data('accountable');
                var rr = $(this).data('rr');
                var remarks = $(this).data('remarks');

                // Ensure asset tag is present in the dropdown
                var $assetTagSelect = $('#edit_equipment_asset_tag');
                if ($assetTagSelect.find('option[value="' + asset + '"]').length === 0) {
                    $assetTagSelect.append('<option value="' + $('<div>').text(asset).html() + '">' + $('<div>').text(asset).html() + '</option>');
                }
                $assetTagSelect.val(asset).trigger('change');

                $('#edit_equipment_id').val(id);
                $('#edit_asset_description_1').val(desc1);
                $('#edit_asset_description_2').val(desc2);
                $('#edit_specifications').val(spec);
                $('#edit_brand').val(brand);
                $('#edit_model').val(model);
                $('#edit_serial_number').val(serial);
                $('#edit_date_acquired').val(dateAcquired);
                $('#edit_location').val(location);
                $('#edit_accountable_individual').val(accountable);
                $('#edit_rr_no').val(rr).trigger('change');
                $('#edit_remarks').val(remarks);

                $('#editEquipmentModal').modal('show');
            });

            // Edit Equipment AJAX submit with toast
            $('#editEquipmentForm').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var submitBtn = $form.find('button[type="submit"]');
                var originalBtnText = submitBtn.text();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                $.ajax({
                    url: '../../modules/equipment_manager/equipment_details.php',
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        try {
                            var result = typeof response === 'object' ? response : JSON.parse(response);
                            submitBtn.prop('disabled', false).text(originalBtnText);
                            if (result.status === 'success') {
                                $('#editEquipmentModal').modal('hide');
                                // Remove any lingering backdrop
                                $('.modal-backdrop').remove();
                                $('body').removeClass('modal-open').css('padding-right', '');
                                showToast(result.message || 'Equipment updated successfully', 'success');
                                // Reload the table section only
                                $('#edTable').load(location.href + ' #edTable > *', function() {
                                    window.allRows = $('#equipmentTable tr:not(#noResultsMessage):not(#initialFilterMessage)').toArray();
                                    window.filteredRows = [...window.allRows];
                                    if (typeof filterEquipmentTable === 'function') filterEquipmentTable();
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
            
            // Add Equipment AJAX submit with toast
            $('#addEquipmentForm').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var submitBtn = $form.find('button[type="submit"]');
                var originalBtnText = submitBtn.text();
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...');
                
                $.ajax({
                    url: '../../modules/equipment_manager/equipment_details.php',
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
                                // Remove any lingering backdrop
                                $('.modal-backdrop').remove();
                                $('body').removeClass('modal-open').css('padding-right', '');
                                
                                $form[0].reset();
                                $('#add_equipment_asset_tag').val(null).trigger('change');
                                $('#add_rr_no').val(null).trigger('change');
                                
                                showToast(response.message || 'Equipment created successfully', 'success');
                                
                                // Reload the table section only
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

            // Delete Equipment
            let deleteEquipmentId = null;
            $(document).on('click', '.remove-equipment', function() {
                deleteEquipmentId = $(this).data('id');
                $('#deleteEDModal').modal('show');
            });

            $('#confirmDeleteBtn').on('click', function() {
                if (!deleteEquipmentId) return;
                $.ajax({
                    url: '../../modules/equipment_manager/equipment_details.php',
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
                            // Remove any lingering backdrop
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open').css('padding-right', '');
                            
                            showToast(response.message || 'Equipment removed successfully', 'success');
                            // Reload the table section only
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
    </script>
</body>

</html>