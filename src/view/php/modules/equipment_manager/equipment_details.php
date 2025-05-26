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
            $sql = "SELECT id, asset_tag, asset_description_1, asset_description_2, specifications, 
brand, model, serial_number, location, accountable_individual, rr_no, remarks, date_created, 
date_modified FROM equipment_details WHERE is_disabled = 0 AND ("
                . "asset_tag LIKE ? OR "
                . "asset_description_1 LIKE ? OR "
                . "asset_description_2 LIKE ? OR "
                . "specifications LIKE ? OR "
                . "brand LIKE ? OR "
                . "model LIKE ? OR "
                . "serial_number LIKE ? OR "
                . "location LIKE ? OR "
                . "accountable_individual LIKE ? OR "
                . "rr_no LIKE ? OR "
                . "remarks LIKE ?)";
            $params = array_fill(0, 11, "%$search%");
            if ($filter !== '') {
                $sql .= " AND asset_description_1 = ?";
                $params[] = $filter;
            }
            $sql .= " ORDER BY id DESC";
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
                    echo '<td>' . (!empty($equipment['date_created']) ? date('Y-m-d H:i', 
strtotime($equipment['date_created'])) : '') . '</td>';
                    echo '<td>' . (!empty($equipment['date_created']) ? date('Y-m-d H:i', 
strtotime($equipment['date_created'])) : '') . '</td>';
                    echo '<td>' . (!empty($equipment['date_modified']) ? date('Y-m-d H:i', 
strtotime($equipment['date_modified'])) : '') . '</td>';
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
                    (isset($_POST['rr_no']) && $_POST['rr_no'] !== '' ? (strpos($_POST['rr_no'], 
'RR') === 0 ? $_POST['rr_no'] : 'RR' . $_POST['rr_no']) : null),
                    $date_created,
                    $_POST['remarks'] ?? null
                ];

                $stmt = $pdo->prepare("INSERT INTO equipment_details (
            asset_tag, asset_description_1, asset_description_2, specifications, 
            brand, model, serial_number, location, accountable_individual, rr_no, date_created, 
remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                if ($e instanceof PDOException && isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062 
&& strpos($e->getMessage(), 'asset_tag') !== false) {
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
                    (isset($_POST['rr_no']) && $_POST['rr_no'] !== '' ? (strpos($_POST['rr_no'], 
'RR') === 0 ? $_POST['rr_no'] : 'RR' . $_POST['rr_no']) : null),
                    $_POST['remarks'],
                    $_POST['equipment_id']
                ];

                // [Cascade Fix 2025-05-16T09:52:12+08:00] Always update date_modified when saving, even if no other fields change
                $stmt = $pdo->prepare("UPDATE equipment_details SET 
            asset_tag = ?, asset_description_1 = ?, asset_description_2 = ?, specifications = ?, 
            brand = ?, model = ?, serial_number = ?, location = ?, accountable_individual = ?, 
            rr_no = ?, remarks = ?, date_modified = NOW() WHERE id = ?");
                $stmt->execute($values);

                unset($oldEquipment['id'], $oldEquipment['is_disabled'], 
$oldEquipment['date_created']);
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

                $oldValue = json_encode($detailsData);
                $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 1 WHERE id = ?");
                $stmt->execute([$_POST['details_id']]);
                $detailsData['is_disabled'] = 1;
                $newValue = json_encode($detailsData);

                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $detailsData['id'],
                    'Equipment Details',
                    'Delete',
                    'Equipment details have been removed (soft delete)',
                    $oldValue,
                    $newValue,
                    'Successful'
                ]);

                $pdo->commit();
                $response['status'] = 'success';
                $response['message'] = 'Equipment Details removed successfully.';
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
    $stmt = $pdo->query("SELECT id, asset_tag, asset_description_1, asset_description_2,
                         specifications, brand, model, serial_number, location, 
accountable_individual, rr_no,
                         remarks, date_created, date_modified FROM equipment_details WHERE 
is_disabled = 0 ORDER BY id DESC");
    $equipmentDetails = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Details: " . $e->getMessage();
}

function safeHtml($value)
{
    return htmlspecialchars($value ?? 'N/A');
}
ob_end_clean();
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
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" 
rel="stylesheet">
    <!-- jQuery (loaded in header for RR autofill) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
 
    <style>
        th.sortable.asc::after {
            content: " â–²";
        }

        th.sortable.desc::after {
            content: " â–¼";
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
                    <!-- Toolbar Row -->
                    <div class="filter-container" id="filterContainer">
                        <div class="col-auto">
                            <?php if ($canCreate): ?>
                                <button class="btn btn-dark" data-bs-toggle="modal" 
data-bs-target="#addEquipmentModal">
                                    <i class="bi bi-plus-lg"></i> Create Equipment
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterEquipment">
                                <option value="">Filter Equipment Type</option>
                                <option value="all">All Equipment Types</option>
                                <?php
                                $equipmentTypes = array_unique(array_column($equipmentDetails, 
'asset_description_1'));
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
                            <select class="form-select" id="dateFilter">
                                <option value="">Filter by Date</option>
                                <option value="desc">Newest to Oldest</option>
                                <option value="asc">Oldest to Newest</option>
                                <option value="month">Specific Month</option>
                                <option value="range">Custom Date Range</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" id="searchEquipment" class="form-control"
                                    placeholder="Search equipment...">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                            </div>
                        </div>
                    </div>

                    <!-- Date Inputs Row -->
                    <div id="dateInputsContainer" class="date-inputs-container">
                        <div class="month-picker-container" id="monthPickerContainer">
                            <select class="form-select" id="monthSelect">
                                <option value="">Select Month</option>
                                <?php
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 
'July', 'August', 'September', 'October', 'November', 'December'];
                                foreach ($months as $index => $month) {
                                    echo "<option value='" . ($index + 1) . "'>" . $month . 
"</option>";
                                }
                                ?>
                            </select>
                            <select class="form-select" id="yearSelect">
                                <option value="">Select Year</option>
                                <?php
                                $currentYear = date('Y');
                                for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                    echo "<option value='" . $year . "'>" . $year . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="date-range-container" id="dateRangePickers">
                            <input type="date" class="form-control" id="dateFrom" placeholder="From">
                            <input type="date" class="form-control" id="dateTo" placeholder="To">
                        </div>
                    </div>
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
                                <th class="sortable" data-column="9">Created Date</th>
                                <th class="sortable" data-column="10">Modified Date</th>
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
                                        <td><?= safeHtml($equipment['date_created']); ?></td>
                                        <td><?= !empty($equipment['date_created']) ? date('Y-m-d 
H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                                        <td><?= !empty($equipment['date_modified']) ? date('Y-m-d 
H:i', strtotime($equipment['date_modified'])) : ''; ?></td>
                                        <td><?= safeHtml((strpos($equipment['rr_no'] ?? '', 'RR') === 
0 ? $equipment['rr_no'] : ('RR' . $equipment['rr_no']))); ?></td>
                                        <td><?= safeHtml($equipment['location']); ?></td>
                                        <td><?= safeHtml($equipment['accountable_individual']); 
?></td>
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
                <!-- Pagination Controls -->
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
                </div> <!-- /.Pagination -->
            </div>
        </section>
    </div>

    <!-- Modals -->
    <!-- Add Equipment Modal -->
    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel" 
aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title ">Add New Equipment</h5>
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
                                $assetTags = array_merge($assetTags, 
$stmt1->fetchAll(PDO::FETCH_COLUMN));
                                $stmt2 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_status 
WHERE is_disabled = 0");
                                $assetTags = array_merge($assetTags, 
$stmt2->fetchAll(PDO::FETCH_COLUMN));
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
                                </div>
                                <style>
                                    /* Ensure Select2 input matches form-control size and font */
                                    .select2-container--default .select2-selection--single {
                                        height: 38px;
                                        padding: 6px 12px;
                                        font-size: 1rem;
                                        border: 1px solid #ced4da;
                                        border-radius: 0.375rem;
                                        background-color: #fff;
                                        box-shadow: none;
                                        display: flex;
                                        align-items: center;
                                    }

                                    .select2-container .select2-selection--single 
.select2-selection__rendered {
                                        line-height: 24px;
                                        color: #212529;
                                    }

                                    .select2-container--default .select2-selection--single 
.select2-selection__arrow {
                                        height: 36px;
                                        right: 10px;
                                    }

                                    .select2-container--open .select2-dropdown {
                                        z-index: 9999 !important;
                                    }
                                </style>
                                <script>
                                    $(function() {
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
                                        });

                                        // Initialize Select2 for asset tag dropdown in the add modal
                                        $('#add_equipment_asset_tag').select2({
                                            placeholder: 'Select or type Asset Tag',
                                            allowClear: true,
                                            tags: true, // Allow typing new values
                                            width: '100%',
                                            dropdownParent: $('#addEquipmentModal'),
                                            minimumResultsForSearch: 0,
                                            createTag: function(params) {
                                                var term = $.trim(params.term);
                                                if (term === '') return null;
                                                var exists = false;
                                                $('#add_equipment_asset_tag option').each(function() {
                                                    if ($(this).text().toLowerCase() === 
term.toLowerCase()) exists = true;
                                                });
                                                return exists ? null : {
                                                    id: term,
                                                    text: term
                                                };
                                            }
                                        }).on('select2:select', function(e) {
                                            console.log('Asset tag selected:', e.params.data.id);
                                            var assetTag = e.params.data.id;
                                            
                                            // Reset fields before fetching new asset tag info
                                            const $accountableField = 
$('input[name="accountable_individual"]');
                                            const $locationField = $('input[name="location"]');
                                            
                                            // If fields were previously autofilled, reset them first
                                            if ($accountableField.attr('data-autofill') === 'true') {
                                                $accountableField.val('').prop('readonly', 
false).attr('data-autofill', 'false').removeClass('bg-light');
                                            }
                                            
                                            if ($locationField.attr('data-autofill') === 'true') {
                                                $locationField.val('').prop('readonly', 
false).attr('data-autofill', 'false').removeClass('bg-light');
                                            }
                                            
                                            // Fetch and autofill data based on asset tag
                                            fetchAssetTagInfo(assetTag, 'add', true);
                                        });

                                        // Handle asset tag being cleared
                                        $('#add_equipment_asset_tag').on('select2:clear', function() {
                                            // Reset the Location and Accountable Individual fields
                                            const $accountableField = 
$('input[name="accountable_individual"]');
                                            const $locationField = $('input[name="location"]');
                                            
                                            if ($accountableField.attr('data-autofill') === 'true') {
                                                $accountableField.val('').prop('readonly', 
false).attr('data-autofill', 'false').removeClass('bg-light');
                                            }
                                            
                                            if ($locationField.attr('data-autofill') === 'true') {
                                                $locationField.val('').prop('readonly', 
false).attr('data-autofill', 'false').removeClass('bg-light');
                                            }
                                        });

                                        // Check if asset tag is already selected when add modal opens
                                        $('#addEquipmentModal').on('shown.bs.modal', function() {
                                            // Check if asset tag is already selected
                                            const assetTagValue = $('#add_equipment_asset_tag').val();
                                            if (assetTagValue) {
                                                console.log('Add modal opened with asset tag already selected:', assetTagValue);
                                                // If an asset tag is already selected, trigger the autofill without notification
                                                fetchAssetTagInfo(assetTagValue, 'add', false);
                                            }
                                        });

                                        //select2 for filtering equipment
                                        $('#filterEquipment').select2({
                                            placeholder: 'Filter Equipment Type',
                                            allowClear: true,
                                            width: '100%',
                                            dropdownAutoWidth: true,
                                            minimumResultsForSearch: 0, // always show search box
                                            dropdownParent: $('#filterEquipment').parent() // helps with z-index issues
                                        });
                                        // Also initialize for edit modal if present
                                        if ($('#edit_rr_no').length) {
                                            $('#edit_rr_no').select2({
                                                placeholder: 'Select or search RR Number',
                                                allowClear: true,
                                                width: '100%',
                                                tags: true,
                                                dropdownParent: $('#editEquipmentModal'),
                                                minimumResultsForSearch: 0,
                                                createTag: function(params) {
                                                    var term = $.trim(params.term);
                                                    if (term === '') return null;
                                                    var exists = false;
                                                    $('#edit_rr_no option').each(function() {
                                                        if ($(this).text().toLowerCase() === 
term.toLowerCase()) exists = true;
                                                    });
                                                    return exists ? null : {
                                                        id: term,
                                                        text: term
                                                    };
                                                }
                                            });
                                            $('#edit_rr_no').on('select2:select', function(e) {
                                                var data = e.params.data;
                                                if (data.selected && data.id && 
$(this).find('option[value="' + data.id + '"').length === 0) {
                                                    $.ajax({
                                                        url: 
'modules/equipment_transactions/receiving_report.php',
                                                        method: 'POST',
                                                        data: {
                                                            action: 'create_rr_no',
                                                            rr_no: data.id
                                                        },
                                                        dataType: 'json',
                                                        headers: {
                                                            'X-Requested-With': 'XMLHttpRequest'
                                                        },
                                                        success: function(response) {
                                                            if (response.status === 'success') {
                                                                showToast('RR# ' + data.id + ' created!', 'success');
                                                            } else {
                                                                showToast(response.message || 'Failed to create RR#', 'error');
                                                            }
                                                        },
                                                        error: function() {
                                                            showToast('AJAX error creating RR#', 
'error');
                                                        }
                                                    });
                                                }
                                                
                                                // Reset fields before fetching new RR info
                                                const $accountableField = 
$('#edit_accountable_individual');
                                                const $locationField = $('#edit_location');
                                                
                                                // If fields were previously autofilled, reset them 
first
                                                if ($accountableField.attr('data-autofill') === 
'true') {
                                                    $accountableField.val('').prop('readonly', false);
                                                }
                                                
                                                if ($locationField.attr('data-autofill') === 'true') {
                                                    $locationField.val('').prop('readonly', false);
                                                }
                                                
                                                // Add autofill functionality here
                                                
                                            });
                                            
                                            // Initialize Select2 for asset tag dropdown in the edit modal
                                            $('#edit_equipment_asset_tag').select2({
                                                placeholder: 'Select or type Asset Tag',
                                                allowClear: true,
                                                tags: true, // Allow typing new values
                                                width: '100%',
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
                                                        text: term
                                                    };
                                                }
                                            });
                                            
                                            // Add event handler for asset tag selection in edit modal
                                            $('#edit_equipment_asset_tag').on('select2:select', 
function(e) {
                                                try {
                                                    console.log('Edit modal - Asset tag selected:', e.params.data.id);
                                                    var assetTag = e.params.data.id;
                                                    
                                                    // Reset fields before fetching new asset tag info
                                                    const $accountableField = 
$('#edit_accountable_individual');
                                                    const $locationField = $('#edit_location');
                                                    
                                                    // If fields were previously autofilled, reset them
                                                    if ($accountableField.attr('data-autofill') === 
'true') {
                                                        $accountableField.val('').attr('data-autofill', 'false');
                                                    }
                                                    
                                                    if ($locationField.attr('data-autofill') === 'true') {
                                                        $locationField.val('').attr('data-autofill', 'false');
                                                    }
                                                    
                                                    // Fetch and autofill data based on asset tag
                                                    fetchAssetTagInfo(assetTag, 'edit', true);
                                                } catch (error) {
                                                    console.error('Error in select2:select handler:', error);
                                                }
                                            });
                                            
                                            // Handle asset tag being cleared in edit modal
                                            $('#edit_equipment_asset_tag').on('select2:clear', 
function() {
                                                try {
                                                    // Reset the Location and Accountable Individual fields
                                                    const $accountableField = 
$('#edit_accountable_individual');
                                                    const $locationField = $('#edit_location');
                                                    
                                                    if ($accountableField.attr('data-autofill') === 
'true') {
                                                        $accountableField.val('').attr('data-autofill', 'false');
                                                    }
                                                    
                                                    if ($locationField.attr('data-autofill') === 'true') {
                                                        $locationField.val('').attr('data-autofill', 'false');
                                                    }
                                                } catch (error) {
                                                    console.error('Error in select2:clear handler:', error);
                                                }
                                            });
                                            
                                            // Check if asset tag is already selected when edit modal opens
                                            $('#editEquipmentModal').on('shown.bs.modal', function() {
                                                try {
                                                    // Check if asset tag is already selected
                                                    const assetTagValue = 
$('#edit_equipment_asset_tag').val();
                                                    if (assetTagValue) {
                                                        console.log('Edit modal opened with asset tag already selected:', assetTagValue);
                                                        // If an asset tag is already selected, trigger the autofill without notification
                                                        fetchAssetTagInfo(assetTagValue, 'edit', false);
                                                    }
                                                } catch (error) {
                                                    console.error('Error in modal shown handler:', error);
                                                }
                                            });
                                        }

                                    });
                                </script>
                            </div>
                        </div>
                </div>
                <div class="mb-3 p-4">
                    <div class="row">
                        <div class="mb-3 col-md-6">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" 
data-autofill="false">
                            <small class="text-muted">This field will be autofilled when an Asset Tag 
is selected</small>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="accountable_individual" class="form-label">Accountable 
Individual</label>
                            <input type="text" class="form-control" name="accountable_individual" 
data-autofill="false">
                            <small class="text-muted">This field will be autofilled when an Asset Tag 
is selected</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3 p-4">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea class="form-control" name="remarks" rows="3"></textarea>
                </div>
                <div class="mb-3 text-end p-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
style="margin-right: 4px;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Equipment</button>
                </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    <!-- Edit Equipment Modal -->
    <div class="modal fade" id="editEquipmentModal" tabindex="-1" data-bs-backdrop="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title">Edit Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" 
aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="editEquipmentForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="equipment_id" id="edit_equipment_id">
                        <div class="mb-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag <span 
style="color: red;">*</span></label>
                            <select class="form-select" name="asset_tag" 
id="edit_equipment_asset_tag" required style="width: 100%;">
                                <option value="">Select or type Asset Tag</option>
                                <?php
                                // Use the same $assetTags as above
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
                                    <label for="edit_asset_description_1" 
class="form-label">Description 1</label>
                                    <input type="text" class="form-control" 
name="asset_description_1" id="edit_asset_description_1">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_asset_description_2" 
class="form-label">Description 2</label>
                                    <input type="text" class="form-control" 
name="asset_description_2" id="edit_asset_description_2">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_specifications" class="form-label">Specification</label>
                            <textarea class="form-control" name="specifications" 
id="edit_specifications" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="edit_brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" name="brand" 
id="edit_brand">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_model" class="form-label">Model</label>
                                    <input type="text" class="form-control" name="model" 
id="edit_model">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="edit_serial_number" class="form-label">Serial 
Number</label>
                                    <input type="text" class="form-control" name="serial_number" 
id="edit_serial_number">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_rr_no" class="form-label">RR#</label>
                                    <select class="form-select" name="rr_no" id="edit_rr_no" required>
                                        <option value="">Select RR Number</option>
                                        <?php
                                        // Fetch active RR numbers for dropdown (reuse $rrList if already set, else fetch)
                                        if (!isset($rrList)) {
                                            $stmtRR = $pdo->prepare("SELECT rr_no FROM receive_report 
WHERE is_disabled = 0 ORDER BY rr_no DESC");
                                            $stmtRR->execute();
                                            $rrList = $stmtRR->fetchAll(PDO::FETCH_COLUMN);
                                        }
                                        foreach ($rrList as $rrNo) {
                                            echo '<option value="' . htmlspecialchars($rrNo) . '">' . 
htmlspecialchars($rrNo) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="row">
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
                        <div class="mb-3 text-end p-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" 
style="margin-right: 4px;">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Equipment Modal -->
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

    <!-- Scripts -->
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" 
defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/toast.js"></script>
    <script src="<?php echo BASE_URL; ?>src/control/js/asset_tag_autofill.js"></script>
    <style>
        .filtered-out {
            display: none !important;
        }
        
        /* Style for highlighting updated rows */
        .updated-row {
            animation: highlight-row 3s ease-in-out;
        }
        
        @keyframes highlight-row {
            0% { background-color: rgba(255, 255, 0, 0.5); }
            70% { background-color: rgba(255, 255, 0, 0.5); }
            100% { background-color: transparent; }
        }
    </style>
    <script>
        // Force hide pagination buttons if no data or all fits on one page
        function forcePaginationCheck() {
            const totalRows = parseInt(document.getElementById('totalRows')?.textContent || '0');
            const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect')?.value || '10');
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            const paginationEl = document.getElementById('pagination');

            if (totalRows <= rowsPerPage) {
                if (prevBtn) prevBtn.style.cssText = 'display: none !important';
                if (nextBtn) nextBtn.style.cssText = 'display: none !important';
                if (paginationEl) paginationEl.style.cssText = 'display: none !important';
            }

            // Also check for visible rows (for when filtering is applied)
            const visibleRows = document.querySelectorAll('#equipmentTable tr:not(.filtered-out)').length;
            if (visibleRows <= rowsPerPage) {
                if (prevBtn) prevBtn.style.cssText = 'display: none !important';
                if (nextBtn) nextBtn.style.cssText = 'display: none !important';
                if (paginationEl) paginationEl.style.cssText = 'display: none !important';
            }
        }
        
        // Ensure renderPaginationControls is available
        if (typeof renderPaginationControls !== 'function') {
            function renderPaginationControls(totalPages) {
                const paginationContainer = document.getElementById('pagination');
                if (!paginationContainer) return;
            
                // Clear existing pagination items
                paginationContainer.innerHTML = '';
            
                // Don't render pagination if there's only one page
                if (totalPages <= 1) {
                    paginationContainer.style.display = 'none';
                    return;
                } else {
                    paginationContainer.style.display = '';
                }
            
                const currentPage = window.paginationConfig?.currentPage || 1;
                const maxPagesToShow = 5; // Maximum number of page numbers to show
            
                let startPage, endPage;
                if (totalPages <= maxPagesToShow) {
                    // Show all pages if there are fewer than maxPagesToShow
                    startPage = 1;
                    endPage = totalPages;
                } else {
                    // Calculate start and end pages to show
                    if (currentPage <= Math.ceil(maxPagesToShow / 2)) {
                        startPage = 1;
                        endPage = maxPagesToShow;
                    } else if (currentPage + Math.floor(maxPagesToShow / 2) >= totalPages) {
                        startPage = totalPages - maxPagesToShow + 1;
                        endPage = totalPages;
                    } else {
                        startPage = currentPage - Math.floor(maxPagesToShow / 2);
                        endPage = currentPage + Math.floor(maxPagesToShow / 2);
                    }
                }
            
                // Add "First" page if not starting from page 1
                if (startPage > 1) {
                    addPaginationItem(paginationContainer, 1);
                    if (startPage > 2) {
                        // Add ellipsis if there's a gap
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = '<span class="page-link">...</span>';
                        paginationContainer.appendChild(ellipsis);
                    }
                }
            
                // Add page numbers
                for (let i = startPage; i <= endPage; i++) {
                    addPaginationItem(paginationContainer, i, i === currentPage);
                }
            
                // Add "Last" page if not ending at the last page
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        // Add ellipsis if there's a gap
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = '<span class="page-link">...</span>';
                        paginationContainer.appendChild(ellipsis);
                    }
                    addPaginationItem(paginationContainer, totalPages);
                }
            }
            
            function addPaginationItem(container, page, isActive = false) {
                const li = document.createElement('li');
                li.className = `page-item${isActive ? ' active' : ''}`;
                
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = page;
                
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = page;
                    } else if (window.paginationConfig === undefined) {
                        window.paginationConfig = { currentPage: page };
                    }
                    updatePagination();
                });
                
                li.appendChild(a);
                container.appendChild(li);
            }
        }

        // Custom filterTable function for equipment details
        window.filterTable = function() {
            console.log('Running filterTable function');
            // Get filter values
            const searchText = $('#searchEquipment').val() || '';
            const filterEquipment = $('#filterEquipment').val() || '';
            const dateFilterType = $('#dateFilter').val() || '';
            const selectedMonth = $('#monthSelect').val() || '';
            const selectedYear = $('#yearSelect').val() || '';
            const dateFrom = $('#dateFrom').val() || '';
            const dateTo = $('#dateTo').val() || '';

            // Debug output
            console.log('FILTER VALUES:', {
                searchText: searchText,
                filterEquipment: filterEquipment,
                dateFilterType: dateFilterType,
                selectedMonth: selectedMonth,
                selectedYear: selectedYear,
                dateFrom: dateFrom,
                dateTo: dateTo
            });

            // Make sure we have allRows populated
            if (!window.allRows || window.allRows.length === 0) {
                window.allRows = Array.from(document.querySelectorAll('#equipmentTable tr:not(#noResultsMessage)'));
            }
            
            // Reset filteredRows array
            window.filteredRows = [];

            // Filter each row
            window.allRows.forEach(row => {
                // Get text content for filtering
                const rowText = row.textContent || '';
                
                // Get equipment type column (3rd column, index 2)
                const equipmentTypeCell = row.cells && row.cells.length > 2 ? row.cells[2] : null;
                const equipmentTypeText = equipmentTypeCell ? equipmentTypeCell.textContent.trim() || '' : '';
                
                // Get date column (10th column, index 9 - created date)
                const dateCell = row.cells && row.cells.length > 9 ? row.cells[9] : null;
                const dateText = dateCell ? dateCell.textContent.trim() || '' : '';
                const date = dateText ? new Date(dateText) : null;
                
                // Apply search filter (case insensitive)
                const searchMatch = !searchText || rowText.toLowerCase().includes(searchText.toLowerCase());
                
                // Apply equipment type filter (case insensitive, exact match)
                let equipmentMatch = true;
                if (filterEquipment && filterEquipment !== 'all' && filterEquipment.toLowerCase() !== 'filter equipment type') {
                    equipmentMatch = equipmentTypeText.toLowerCase() === filterEquipment.trim().toLowerCase();
                }
                
                // Apply date filter
                let dateMatch = true;
                if (dateFilterType && date) {
                    if (dateFilterType === 'month' && selectedMonth && selectedYear) {
                        dateMatch = (date.getMonth() + 1 === parseInt(selectedMonth)) && 
                                   (date.getFullYear() === parseInt(selectedYear));
                    } else if (dateFilterType === 'range' && dateFrom && dateTo) {
                        const from = new Date(dateFrom);
                        const to = new Date(dateTo);
                        to.setHours(23, 59, 59); // End of day
                        dateMatch = date >= from && date <= to;
                    }
                }

                // Show or hide row based on filter match
                const shouldShow = searchMatch && equipmentMatch && dateMatch;
                if (shouldShow) {
                    window.filteredRows.push(row);
                }
                
                // Always add the filtered-out class - updatePagination will remove it for visible rows
                row.classList.add('filtered-out');
            });

            // Sort if needed
            if (dateFilterType === 'asc' || dateFilterType === 'desc') {
                window.filteredRows.sort((a, b) => {
                    const dateA = a.cells && a.cells[9] ? new Date(a.cells[9].textContent) : new Date(0);
                    const dateB = b.cells && b.cells[9] ? new Date(b.cells[9].textContent) : new Date(0);
                    return dateFilterType === 'asc' ? dateA - dateB : dateB - dateA;
                });

                // Remove all rows and add back in sorted order
                const tbody = document.getElementById('equipmentTable');
                if (tbody) {
                    window.filteredRows.forEach(row => tbody.appendChild(row));
                }
            }

            // Reset to page 1 and update pagination
            if (typeof paginationConfig !== 'undefined') {
                paginationConfig.currentPage = 1;
            }
            
            // Update pagination to show/hide rows based on current page
            if (typeof updatePagination === 'function') {
                updatePagination();
            }
            
            // Show a message if no results found
            const noResultsMessage = document.getElementById('noResultsMessage');
            if (window.filteredRows.length === 0) {
                if (!noResultsMessage) {
                    // Create and insert a "no results" message if it doesn't exist
                    const tbody = document.getElementById('equipmentTable');
                    if (tbody) {
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
                    }
                } else {
                    noResultsMessage.style.display = 'table-row';
                }
            } else if (noResultsMessage) {
                noResultsMessage.style.display = 'none';
            }
            
            console.log('Filtered rows:', window.filteredRows.length);
        };

        // Set up event listeners for filtering
        $(document).ready(function() {
            // Initialize pagination with the correct table ID
            initPagination({
                tableId: 'equipmentTable',
                currentPage: 1
            });

            // Initialize allRows for pagination.js
            window.allRows = Array.from(document.querySelectorAll('#equipmentTable tr:not(#noResultsMessage)'));
            window.filteredRows = [...window.allRows];
            
            // Make sure pagination is visible
            const totalRows = window.allRows.length;
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || '10');
            const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
            
            if (totalPages > 1) {
                // Force render pagination controls
                renderPaginationControls(totalPages);
                $('#pagination').show();
            }

            // First set up the standard event handlers
            $('#filterEquipment').on('change', function() {
                console.log('Equipment filter changed to:', $(this).val());
                
                // Reset date filters when equipment filter changes
                if ($(this).val() && $(this).val() !== 'all') {
                    $('#dateFilter').val('');
                    $('#monthSelect').val('');
                    $('#yearSelect').val('');
                    $('#dateFrom').val('');
                    $('#dateTo').val('');
                    $('#dateInputsContainer').hide();
                }
                
                filterTable();
            });
            
            // Then initialize Select2 with the proper configuration
            if ($.fn.select2) {
                try {
                    // First destroy any existing instance
                    if ($('#filterEquipment').data('select2')) {
                        $('#filterEquipment').select2('destroy');
                    }
                    
                    // Initialize with proper settings
                    $('#filterEquipment').select2({
                        placeholder: 'Filter Equipment Type',
                        allowClear: true,
                        width: '100%',
                        dropdownAutoWidth: true,
                        minimumResultsForSearch: 0
                    });
                    
                    // Set default value and ensure it's reflected in the UI
                    $('#filterEquipment').val('all').trigger('change');
                } catch (e) {
                    console.error('Error initializing Select2:', e);
                }
            }
            
            // Add reset button if it doesn't exist
            if ($('#resetFilters').length === 0) {
                $('.filter-container').append('<button id="resetFilters" class="btn btn-outline-secondary ms-2">Reset Filters</button>');
            }
            
            // Set up event listeners for filtering
            $('#searchEquipment').on('input', filterTable);
            
            // Reset filters button handler
            $(document).on('click', '#resetFilters', function() {
                // Reset all filter inputs
                $('#searchEquipment').val('');
                
                // Set select value first
                $('#filterEquipment').val('all');
                
                // Then update Select2 UI if it exists
                if ($('#filterEquipment').data('select2')) {
                    $('#filterEquipment').trigger('change.select2');
                }
                
                // Reset other filters
                $('#dateFilter').val('');
                $('#monthSelect').val('');
                $('#yearSelect').val('');
                $('#dateFrom').val('');
                $('#dateTo').val('');
                $('#dateInputsContainer').hide();
                
                // Apply the filter reset
                filterTable();
            });
            
            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();
                console.log('Date filter changed to:', filterType);

                // Hide all containers first
                $('#dateInputsContainer').hide();
                $('#monthPickerContainer').hide();
                $('#dateRangePickers').hide();
                
                // Reset equipment filter if date filter is applied
                if (filterType) {
                    // Set select value first
                    $('#filterEquipment').val('all');
                    
                    // Then update Select2 UI if it exists
                    if ($('#filterEquipment').data('select2')) {
                        $('#filterEquipment').trigger('change.select2');
                    }
                }

                // Show appropriate containers based on selection
                if (filterType === 'month') {
                    $('#dateInputsContainer').show();
                    $('#monthPickerContainer').show();
                } else if (filterType === 'range') {
                    $('#dateInputsContainer').show();
                    $('#dateRangePickers').show();
                } else if (filterType === 'desc' || filterType === 'asc') {
                    // Apply sorting without showing date inputs
                    filterTable();
                }
            });

            // Handle month/year selection changes
            $('#monthSelect, #yearSelect').on('change', function() {
                const month = $('#monthSelect').val();
                const year = $('#yearSelect').val();

                if (month && year) {
                    filterTable();
                }
            });

            // Handle date range changes
            $('#dateFrom, #dateTo').on('change', function() {
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                if (dateFrom && dateTo) {
                    filterTable();
                }
            });
            
            // Check if we need to highlight updated rows
            <?php if ($forceRefresh && !empty($updatedAssetTag)): ?>
            setTimeout(function() {
                const updatedAssetTag = "<?= htmlspecialchars($updatedAssetTag) ?>";
                const rows = document.querySelectorAll('#equipmentTable tr');
                
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length > 1) {
                        const assetTagCell = cells[1]; // Asset tag is in the second column
                        if (assetTagCell.textContent.trim() === updatedAssetTag) {
                            // Scroll to the row
                            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            // Add highlight class
                            row.classList.add('updated-row');
                            // Show toast notification
                            if (typeof showToast === 'function') {
                                showToast('Equipment details for asset tag ' + updatedAssetTag + ' have been updated from location changes', 'success');
                            }
                        }
                    }
                });
            }, 500); // Short delay to ensure DOM is ready
            <?php endif; ?>
            
            // Run initial filter to make sure everything is displayed correctly
            setTimeout(filterTable, 100);
            
            // Make sure pagination is initialized properly
            setTimeout(function() {
                // Check if pagination controls are rendered
                if (document.getElementById('pagination') && document.getElementById('pagination').children.length === 0) {
                    const totalRows = window.allRows.length;
                    const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || '10');
                    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
                    
                    if (totalPages > 1) {
                        renderPaginationControls(totalPages);
                        $('#pagination').show();
                    }
                }
                
                // Make sure the current page display is correct
                if (document.getElementById('currentPage')) {
                    document.getElementById('currentPage').textContent = window.paginationConfig?.currentPage || 1;
                }
                
                // Make sure the rows per page display is correct
                if (document.getElementById('rowsPerPage')) {
                    const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || '10');
                    document.getElementById('rowsPerPage').textContent = Math.min(rowsPerPage, window.filteredRows.length);
                }
            }, 500);
        });
        
        document.querySelectorAll(".sortable").forEach(header => {
            header.style.cursor = "pointer";
            header.addEventListener("click", () => {
                const table = document.querySelector("#edTable");
                const tbody = table.querySelector("tbody");
                const columnIndex = parseInt(header.dataset.column);
                const ascending = !header.classList.contains("asc");

                // Remove asc/desc classes from all headers
                document.querySelectorAll(".sortable").forEach(h => h.classList.remove("asc", "desc"));
                header.classList.add(ascending ? "asc" : "desc");

                const rows = Array.from(tbody.querySelectorAll("tr:not(.filtered-out)")).sort((a, b) => {
                    const aText = a.children[columnIndex] ? a.children[columnIndex].textContent.trim() : '';
                    const bText = b.children[columnIndex] ? b.children[columnIndex].textContent.trim() : '';

                    const isDate = /\d{4}-\d{2}-\d{2}/.test(aText) || !isNaN(Date.parse(aText));
                    const isNumeric = !isNaN(parseFloat(aText)) && !isNaN(parseFloat(bText));

                    if (isNumeric) {
                        return ascending ?
                            parseFloat(aText) - parseFloat(bText) :
                            parseFloat(bText) - parseFloat(aText);
                    } else if (isDate) {
                        return ascending ?
                            new Date(aText) - new Date(bText) :
                            new Date(bText) - new Date(aText);
                    } else {
                        return ascending ?
                            aText.localeCompare(bText) :
                            bText.localeCompare(aText);
                    }
                });

                // Append sorted rows
                rows.forEach(row => tbody.appendChild(row));
                
                // Update filteredRows for pagination
                window.filteredRows = rows;
                
                // Reset to page 1 and update pagination
                paginationConfig.currentPage = 1;
                updatePagination();
            });
        });
        
        $(function() {
            // 1) EDIT button handler
            $(document).on('click', '.edit-equipment', function() {
                try {
                    const d = $(this).data();
                    $('#edit_equipment_id').val(d.id);
                    // make sure the asset-tag exists in the dropdown
                    const $asset = $('#edit_equipment_asset_tag');
                    if (!$asset.find(`option[value="${d.asset}"]`).length) {
                        $asset.append(`<option value="${d.asset}">${d.asset}</option>`);
                    }
                    
                    // First populate all fields from the data attributes
                    $('#edit_asset_description_1').val(d.desc1);
                    $('#edit_asset_description_2').val(d.desc2);
                    $('#edit_specifications').val(d.spec);
                    $('#edit_brand').val(d.brand);
                    $('#edit_model').val(d.model);
                    $('#edit_serial_number').val(d.serial);
                    $('#edit_location').val(d.location);
                    $('#edit_accountable_individual').val(d.accountable);
                    $('#edit_remarks').val(d.remarks);

                    // same for RR#
                    const $rr = $('#edit_rr_no');
                    if (d.rr && !$rr.find(`option[value="${d.rr}"]`).length) {
                        $rr.append(`<option value="${d.rr}">${d.rr}</option>`);
                    }
                    $rr.val(d.rr).trigger('change');
                    
                    // Set the asset tag value last and trigger change to activate autofill
                    $asset.val(d.asset).trigger('change');

                    // show the modal
                    $('#editEquipmentModal').modal('show');
                } catch (error) {
                    console.error('Error in edit-equipment handler:', error);
                    showToast('Error opening edit form. Please try again.', 'error');
                }
            });

            // 2) REMOVE button handler
            let deleteId = null;
            $(document).on('click', '.remove-equipment', function(e) {
                e.preventDefault();
                deleteId = $(this).data('id');
                $('#deleteEDModal').modal('show');
            });

            $('#confirmDeleteBtn').on('click', function() {
                if (!deleteId) return;
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: {
                        action: 'remove',
                        details_id: deleteId
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success(resp) {
                        showToast(resp.message, resp.status === 'success' ? 'success' : 'error');
                        refreshEquipmentList();
                        $('#deleteEDModal').modal('hide');
                        $('.modal-backdrop').remove();
                    },
                    error(xhr, _, err) {
                        showToast(`Error removing equipment: ${err}`, 'error');
                    }
                });
            });

            // 3) Refresh the list (after any CRUD) via AJAX GET
            function refreshEquipmentList() {
                $.get(window.location.href, function(html) {
                    const newTbody = $(html).find('#equipmentTable').html();
                    $('#equipmentTable').html(newTbody);
                    
                    // Update allRows and filteredRows for pagination
                    window.allRows = Array.from(document.querySelectorAll('#equipmentTable tr:not(#noResultsMessage)'));
                    window.filteredRows = [...window.allRows];
                    
                    // Re-initialize pagination
                    initPagination({
                        tableId: 'equipmentTable',
                        currentPage: 1
                    });
                    
                    // Apply any active filters
                    filterTable();
                    
                    // Update the total count
                    const totalRows = window.allRows.length;
                    $('#totalRows').text(totalRows);
                    
                    // Show toast notification
                    showToast('Equipment list refreshed successfully', 'success');
                });
            }

            // 4) CREATE form submit
            $('#addEquipmentForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type=submit]').prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm"></span> Adding…');
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success(resp) {
                        if (resp.status === 'success') {
                            $('#addEquipmentModal').modal('hide');
                            showToast(resp.message, 'success');
                            $('#addEquipmentForm')[0].reset();
                            refreshEquipmentList();
                        } else {
                            showToast(resp.message, 'error');
                        }
                    },
                    error() {
                        showToast('Error creating equipment', 'error');
                    },
                    complete() {
                        btn.prop('disabled', false).text('Create Equipment');
                    }
                });
            });

            // 5) UPDATE form submit
            $('#editEquipmentForm').on('submit', function(e) {
                e.preventDefault();
                
                // Get the original values from data attributes
                const $form = $(this);
                const $editBtn = $('.edit-equipment[data-id="' + $('#edit_equipment_id').val() + '"]');
                const originalValues = {
                    asset_tag: $editBtn.data('asset'),
                    asset_description_1: $editBtn.data('desc1'),
                    asset_description_2: $editBtn.data('desc2'),
                    specifications: $editBtn.data('spec'),
                    brand: $editBtn.data('brand'),
                    model: $editBtn.data('model'),
                    serial_number: $editBtn.data('serial'),
                    location: $editBtn.data('location'),
                    accountable_individual: $editBtn.data('accountable'),
                    rr_no: $editBtn.data('rr'),
                    remarks: $editBtn.data('remarks')
                };
                
                // Get current form values
                const currentValues = {
                    asset_tag: $('#edit_equipment_asset_tag').val(),
                    asset_description_1: $('#edit_asset_description_1').val(),
                    asset_description_2: $('#edit_asset_description_2').val(),
                    specifications: $('#edit_specifications').val(),
                    brand: $('#edit_brand').val(),
                    model: $('#edit_model').val(),
                    serial_number: $('#edit_serial_number').val(),
                    location: $('#edit_location').val(),
                    accountable_individual: $('#edit_accountable_individual').val(),
                    rr_no: $('#edit_rr_no').val(),
                    remarks: $('#edit_remarks').val()
                };
                
                // Check if any values changed
                let hasChanges = false;
                for (const key in originalValues) {
                    // Handle null/undefined/empty string consistently
                    const origVal = originalValues[key] || '';
                    const currVal = currentValues[key] || '';
                    if (origVal.toString().trim() !== currVal.toString().trim()) {
                        hasChanges = true;
                        break;
                    }
                }
                
                // If no changes, show notification and don't submit
                if (!hasChanges) {
                    showToast('No changes detected', 'info');
                    $('#editEquipmentModal').modal('hide');
                    return;
                }
                
                const btn = $(this).find('button[type=submit]').prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm"></span> Saving…');
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success(resp) {
                        if (resp.status === 'success') {
                            $('#editEquipmentModal').modal('hide');
                            showToast(resp.message, 'success');
                            refreshEquipmentList();
                        } else {
                            showToast(resp.message, 'error');
                        }
                    },
                    error(xhr) {
                        let msg = 'Error updating equipment: ';
                        try {
                            msg += JSON.parse(xhr.responseText).message
                        } catch (e) {
                            msg += e
                        }
                        showToast(msg, 'error');
                    },
                    complete() {
                        btn.prop('disabled', false).text('Save Changes');
                    }
                });
            });
        });

        // Ensure updatePagination is available
        if (typeof updatePagination !== 'function') {
            function updatePagination() {
                console.log('Local updatePagination called');
                const tbody = document.getElementById('equipmentTable');
                if (!tbody) {
                    console.error('Could not find tbody with ID equipmentTable');
                    return;
                }
                
                // Use window.filteredRows for pagination
                const rowsToPaginate = window.filteredRows || [];
                const totalRows = rowsToPaginate.length;
                const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
                const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
                const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
                
                // Make sure current page is in valid range
                if (!window.paginationConfig) {
                    window.paginationConfig = { currentPage: 1 };
                } else if (window.paginationConfig.currentPage > totalPages) {
                    window.paginationConfig.currentPage = totalPages;
                } else if (window.paginationConfig.currentPage < 1) {
                    window.paginationConfig.currentPage = 1;
                }
            
                // Calculate start and end indices for current page
                const startIndex = (window.paginationConfig.currentPage - 1) * rowsPerPage;
                const endIndex = Math.min(startIndex + rowsPerPage, totalRows);
            
                // First, hide all rows by adding filtered-out class
                const allTableRows = Array.from(tbody.querySelectorAll('tr:not(#noResultsMessage)'));
                allTableRows.forEach(row => {
                    row.classList.add('filtered-out');
                });
            
                // Then, show only the rows for the current page
                for (let i = startIndex; i < endIndex; i++) {
                    if (rowsToPaginate[i]) {
                        rowsToPaginate[i].classList.remove('filtered-out');
                    }
                }
            
                // Update pagination controls
                const currentPageSpan = document.getElementById('currentPage');
                if (currentPageSpan) {
                    currentPageSpan.textContent = totalRows === 0 ? 0 : window.paginationConfig.currentPage;
                }
            
                const rowsPerPageSpan = document.getElementById('rowsPerPage');
                if (rowsPerPageSpan) {
                    rowsPerPageSpan.textContent = totalRows === 0 ? 0 : Math.min(endIndex, totalRows);
                }
            
                const totalRowsSpan = document.getElementById('totalRows');
                if (totalRowsSpan) {
                    totalRowsSpan.textContent = totalRows;
                }
            
                // Enable/disable prev/next buttons based on current page
                const prevButton = document.getElementById('prevPage');
                const nextButton = document.getElementById('nextPage');
            
                if (prevButton) {
                    prevButton.disabled = window.paginationConfig.currentPage <= 1;
                    prevButton.classList.toggle('disabled', window.paginationConfig.currentPage <= 1);
                    
                    // Show/hide prev button based on current page
                    prevButton.style.display = (window.paginationConfig.currentPage <= 1 || totalRows <= rowsPerPage) ? 'none' : '';
                }
            
                if (nextButton) {
                    nextButton.disabled = window.paginationConfig.currentPage >= totalPages;
                    nextButton.classList.toggle('disabled', window.paginationConfig.currentPage >= totalPages);
                    
                    // Show/hide next button based on current page
                    nextButton.style.display = (window.paginationConfig.currentPage >= totalPages || totalRows <= rowsPerPage) ? 'none' : '';
                }
            
                // Update pagination numbers
                renderPaginationControls(totalPages);
                
                // Show/hide pagination container based on total pages
                const paginationContainer = document.getElementById('pagination');
                if (paginationContainer) {
                    paginationContainer.style.display = (totalPages <= 1 || totalRows <= rowsPerPage) ? 'none' : '';
                }
            }
            
            // Set up event listeners for pagination
            document.getElementById('prevPage')?.addEventListener('click', function() {
                if (window.paginationConfig && window.paginationConfig.currentPage > 1) {
                    window.paginationConfig.currentPage--;
                    updatePagination();
                }
            });
            
            document.getElementById('nextPage')?.addEventListener('click', function() {
                const rowsToPaginate = window.filteredRows || [];
                const totalRows = rowsToPaginate.length;
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect')?.value || '10');
                const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
                
                if (window.paginationConfig && window.paginationConfig.currentPage < totalPages) {
                    window.paginationConfig.currentPage++;
                    updatePagination();
                }
            });
            
            document.getElementById('rowsPerPageSelect')?.addEventListener('change', function() {
                if (window.paginationConfig) {
                    window.paginationConfig.currentPage = 1;
                }
                updatePagination();
            });
        }

        // Ensure renderPaginationControls is available
    </script>
</body>

</html>


