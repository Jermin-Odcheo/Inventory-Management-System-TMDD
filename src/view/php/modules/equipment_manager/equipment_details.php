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
            $sql = "SELECT id, asset_tag, asset_description_1, asset_description_2, specifications, brand, model, serial_number, location, accountable_individual, rr_no, remarks, date_created, date_modified FROM equipment_details WHERE is_disabled = 0 AND ("
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
                    echo '<td>' . (!empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : '') . '</td>';
                    echo '<td>' . (!empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : '') . '</td>';
                    echo '<td>' . (!empty($equipment['date_modified']) ? date('Y-m-d H:i', strtotime($equipment['date_modified'])) : '') . '</td>';
                    echo '<td>' . safeHtml((strpos($equipment['rr_no'] ?? '', 'RR') === 0 ? $equipment['rr_no'] : ('RR' . $equipment['rr_no']))) . '</td>';
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
                            . ' data-accountable="' . safeHtml($equipment['accountable_individual']) . '"'
                            . ' data-rr="' . safeHtml($equipment['rr_no']) . '"'
                            . ' data-date="' . safeHtml($equipment['date_created']) . '"'
                            . ' data-remarks="' . safeHtml($equipment['remarks']) . '">
                            <i class="bi bi-pencil-square"></i>
                        </button>';
                    }
                    if ($canDelete) {
                        echo '<button class="btn btn-outline-danger btn-sm remove-equipment" data-id="' . safeHtml($equipment['id']) . '"><i class="bi bi-trash"></i></button>';
                    }
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="16" class="text-center py-4"><div class="alert alert-warning mb-0"><i class="bi bi-exclamation-circle me-2"></i> No results found for the current filter criteria.</div></td></tr>';
            }
            $html = ob_get_clean();
            echo json_encode(['status' => 'success', 'html' => $html]);
        } catch (Throwable $e) {
            error_log('AJAX Search Error: ' . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'AJAX Search Error: ' . $e->getMessage()]);
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
                    (isset($_POST['rr_no']) && $_POST['rr_no'] !== '' ? (strpos($_POST['rr_no'], 'RR') === 0 ? $_POST['rr_no'] : 'RR' . $_POST['rr_no']) : null),
                    $date_created,
                    $_POST['remarks'] ?? null
                ];

                $stmt = $pdo->prepare("INSERT INTO equipment_details (
            asset_tag, asset_description_1, asset_description_2, specifications, 
            brand, model, serial_number, location, accountable_individual, rr_no, date_created, remarks
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
                if ($e instanceof PDOException && isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062 && strpos($e->getMessage(), 'asset_tag') !== false) {
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
                    (isset($_POST['rr_no']) && $_POST['rr_no'] !== '' ? (strpos($_POST['rr_no'], 'RR') === 0 ? $_POST['rr_no'] : 'RR' . $_POST['rr_no']) : null),
                    $_POST['remarks'],
                    $_POST['equipment_id']
                ];

                // [Cascade Fix 2025-05-16T09:52:12+08:00] Always update date_modified when saving, even if no other fields change
                $stmt = $pdo->prepare("UPDATE equipment_details SET 
            asset_tag = ?, asset_description_1 = ?, asset_description_2 = ?, specifications = ?, 
            brand = ?, model = ?, serial_number = ?, location = ?, accountable_individual = ?, 
            rr_no = ?, remarks = ?, date_modified = NOW() WHERE id = ?");
                $stmt->execute($values);

                unset($oldEquipment['id'], $oldEquipment['is_disabled'], $oldEquipment['date_created']);
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

try {
    $stmt = $pdo->query("SELECT id, asset_tag, asset_description_1, asset_description_2,
                         specifications, brand, model, serial_number, location, accountable_individual, rr_no,
                         remarks, date_created, date_modified FROM equipment_details WHERE is_disabled = 0 ORDER BY id DESC");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <!-- jQuery (loaded in header for RR autofill) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
 
    <style>
        th.sortable.asc::after {
            content: " ▲";
        }

        th.sortable.desc::after {
            content: " ▼";
        }
        
        /* Styling for autofilled fields */
        input[data-autofill="true"] {
            background-color: #f8f9fa !important;  /* Light gray background */
            border-color: #ced4da !important;      /* Standard border color */
            cursor: not-allowed;                   /* Not-allowed cursor */
            color: #6c757d !important;             /* Darker text for better contrast */
        }
        
        /* Add a small indicator that the field was autofilled */
        input[data-autofill="true"]::after {
            content: "Autofilled";
            position: absolute;
            right: 10px;
            font-size: 0.8em;
            color: #6c757d;
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
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Equipment Details</h2>
            </div>
            <div class="card-body">
                <div class="container-fluid px-0">
                    <!-- Toolbar Row -->
                    <div class="filter-container">
                        <div class="col-auto">
                            <?php if ($canCreate): ?>
                                <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                                    <i class="bi bi-plus-lg"></i> Create Equipment
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="filterEquipment">
                                <option value="">Filter Equipment Type</option>
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
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                foreach ($months as $index => $month) {
                                    echo "<option value='" . ($index + 1) . "'>" . $month . "</option>";
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
                                        <td><?= !empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                                        <td><?= !empty($equipment['date_modified']) ? date('Y-m-d H:i', strtotime($equipment['date_modified'])) : ''; ?></td>
                                        <td><?= safeHtml((strpos($equipment['rr_no'] ?? '', 'RR') === 0 ? $equipment['rr_no'] : ('RR' . $equipment['rr_no']))); ?></td>
                                        <td><?= safeHtml($equipment['location']); ?></td>
                                        <td><?= safeHtml($equipment['accountable_individual']); ?></td>
                                        <td><?= safeHtml($equipment['remarks']); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($canModify): ?>
                                                    <button class="btn btn-outline-info btn-sm edit-equipment"
                                                        data-id="<?= safeHtml($equipment['id']); ?>"
                                                        data-asset="<?= safeHtml($equipment['asset_tag']); ?>"
                                                        data-desc1="<?= safeHtml($equipment['asset_description_1']); ?>"
                                                        data-desc2="<?= safeHtml($equipment['asset_description_2']); ?>"
                                                        data-spec="<?= safeHtml($equipment['specifications']); ?>"
                                                        data-brand="<?= safeHtml($equipment['brand']); ?>"
                                                        data-model="<?= safeHtml($equipment['model']); ?>"
                                                        data-serial="<?= safeHtml($equipment['serial_number']); ?>"
                                                        data-location="<?= safeHtml($equipment['location']); ?>"
                                                        data-accountable="<?= safeHtml($equipment['accountable_individual']); ?>"
                                                        data-rr="<?= safeHtml($equipment['rr_no']); ?>"
                                                        data-date="<?= safeHtml($equipment['date_created']); ?>"
                                                        data-remarks="<?= safeHtml($equipment['remarks']); ?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($canDelete): ?>
                                                    <button class="btn btn-outline-danger btn-sm remove-equipment"
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
                                            <i class="bi bi-info-circle me-2"></i> No Equipment Details found. Click on "Create Equipment" to add a new entry.
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
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>
                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
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
    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header p-4">
                    <h5 class="modal-title ">Add New Equipment</h5>
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
                                    <label for="serial_number" class="form-label">Serial Number </label>
                                    <input type="text" class="form-control" name="serial_number">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="add_rr_no" class="form-label">RR#</label>
                                    <select class="form-select rr-select2" name="rr_no" id="add_rr_no" style="width: 100%;">
                                        <option value="">Select or search RR Number</option>
                                        <?php
                                        // Fetch active RR numbers for dropdown
                                        $stmtRR = $pdo->prepare("SELECT rr_no FROM receive_report WHERE is_disabled = 0 ORDER BY rr_no DESC");
                                        $stmtRR->execute();
                                        $rrList = $stmtRR->fetchAll(PDO::FETCH_COLUMN);
                                        foreach ($rrList as $rrNo) {
                                            echo '<option value="' . htmlspecialchars($rrNo) . '">' . htmlspecialchars($rrNo) . '</option>';
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

                                    .select2-container .select2-selection--single .select2-selection__rendered {
                                        line-height: 24px;
                                        color: #212529;
                                    }

                                    .select2-container--default .select2-selection--single .select2-selection__arrow {
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

                                        $('#add_rr_no').on('select2:select', function(e) {
                                            var data = e.params.data;
                                            if (data.selected && data.id && $(this).find('option[value="' + data.id + '"').length === 0) {
                                                // New tag, send AJAX to create RR
                                                $.ajax({
                                                    url: 'modules/equipment_transactions/receiving_report.php',
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
                                                        showToast('AJAX error creating RR#', 'error');
                                                    }
                                                });
                                            }
                                            
                                            // Reset fields before fetching new RR info
                                            const $accountableField = $('input[name="accountable_individual"]');
                                            const $locationField = $('input[name="location"]');
                                            
                                            // If fields were previously autofilled, reset them first
                                            if ($accountableField.attr('data-autofill') === 'true') {
                                                $accountableField.val('').prop('readonly', false);
                                            }
                                            
                                            if ($locationField.attr('data-autofill') === 'true') {
                                                $locationField.val('').prop('readonly', false);
                                            }
                                            
                                            // Add autofill functionality here
                                            fetchRRInfo(data.id, 'add', true);
                                        });

                                        // Handle RR# being cleared
                                        $('#add_rr_no').on('select2:clear', function() {
                                            // Reset the Location and Accountable Individual fields
                                            const $accountableField = $('input[name="accountable_individual"]');
                                            const $locationField = $('input[name="location"]');
                                            
                                            if ($accountableField.attr('data-autofill') === 'true') {
                                                $accountableField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                                            }
                                            
                                            if ($locationField.attr('data-autofill') === 'true') {
                                                $locationField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                                            }
                                        });

                                        // Check if RR# is already selected when modal opens
                                        $('#addEquipmentModal').on('shown.bs.modal', function() {
                                            const rrValue = $('#add_rr_no').val();
                                            if (rrValue) {
                                                // If an RR# is already selected, trigger the autofill without notification
                                                fetchRRInfo(rrValue, 'add', false);
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
                                                        if ($(this).text().toLowerCase() === term.toLowerCase()) exists = true;
                                                    });
                                                    return exists ? null : {
                                                        id: term,
                                                        text: term
                                                    };
                                                }
                                            });
                                            $('#edit_rr_no').on('select2:select', function(e) {
                                                var data = e.params.data;
                                                if (data.selected && data.id && $(this).find('option[value="' + data.id + '"').length === 0) {
                                                    $.ajax({
                                                        url: 'modules/equipment_transactions/receiving_report.php',
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
                                                            showToast('AJAX error creating RR#', 'error');
                                                        }
                                                    });
                                                }
                                                
                                                // Reset fields before fetching new RR info
                                                const $accountableField = $('#edit_accountable_individual');
                                                const $locationField = $('#edit_location');
                                                
                                                // If fields were previously autofilled, reset them first
                                                if ($accountableField.attr('data-autofill') === 'true') {
                                                    $accountableField.val('').prop('readonly', false);
                                                }
                                                
                                                if ($locationField.attr('data-autofill') === 'true') {
                                                    $locationField.val('').prop('readonly', false);
                                                }
                                                
                                                // Add autofill functionality here
                                                fetchRRInfo(data.id, 'edit', true);
                                            });
                                            
                                            // Handle RR# being cleared
                                            $('#edit_rr_no').on('select2:clear', function() {
                                                // Reset the Location and Accountable Individual fields
                                                const $accountableField = $('#edit_accountable_individual');
                                                const $locationField = $('#edit_location');
                                                
                                                if ($accountableField.attr('data-autofill') === 'true') {
                                                    $accountableField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                                                }
                                                
                                                if ($locationField.attr('data-autofill') === 'true') {
                                                    $locationField.val('').prop('readonly', false).attr('data-autofill', 'false').removeClass('bg-light');
                                                }
                                            });
                                            
                                            // Check if RR# is already selected when modal opens
                                            $('#editEquipmentModal').on('shown.bs.modal', function() {
                                                const rrValue = $('#edit_rr_no').val();
                                                if (rrValue) {
                                                    // If an RR# is already selected, trigger the autofill without notification
                                                    fetchRRInfo(rrValue, 'edit', false);
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
                            <input type="text" class="form-control" name="location" data-autofill="false">
                            <small class="text-muted">This field will be autofilled when an RR# is selected</small>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual" data-autofill="false">
                            <small class="text-muted">This field will be autofilled when an RR# is selected</small>
                        </div>
                    </div>
                </div>
                <div class="mb-3 p-4">
                    <label for="remarks" class="form-label">Remarks</label>
                    <textarea class="form-control" name="remarks" rows="3"></textarea>
                </div>
                <div class="mb-3 text-end p-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="margin-right: 4px;">Cancel</button>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="editEquipmentForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="equipment_id" id="edit_equipment_id">
                        <div class="mb-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag <span style="color: red;">*</span></label>
                            <select class="form-select" name="asset_tag" id="edit_equipment_asset_tag" required style="width: 100%;">
                                <option value="">Select or type Asset Tag</option>
                                <?php
                                // Use the same $assetTags as above
                                foreach ($assetTags as $tag) {
                                    echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label for="edit_asset_description_1" class="form-label">Description 1</label>
                                    <input type="text" class="form-control" name="asset_description_1" id="edit_asset_description_1">
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_asset_description_2" class="form-label">Description 2</label>
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
                                    <select class="form-select" name="rr_no" id="edit_rr_no" required>
                                        <option value="">Select RR Number</option>
                                        <?php
                                        // Fetch active RR numbers for dropdown (reuse $rrList if already set, else fetch)
                                        if (!isset($rrList)) {
                                            $stmtRR = $pdo->prepare("SELECT rr_no FROM receive_report WHERE is_disabled = 0 ORDER BY rr_no DESC");
                                            $stmtRR->execute();
                                            $rrList = $stmtRR->fetchAll(PDO::FETCH_COLUMN);
                                        }
                                        foreach ($rrList as $rrNo) {
                                            echo '<option value="' . htmlspecialchars($rrNo) . '">' . htmlspecialchars($rrNo) . '</option>';
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
                                    <input type="text" class="form-control" name="location" id="edit_location" data-autofill="false">
                                    <small class="text-muted">This field will be autofilled when an RR# is selected</small>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label for="edit_accountable_individual" class="form-label">Accountable Individual</label>
                                    <input type="text" class="form-control" name="accountable_individual" id="edit_accountable_individual" data-autofill="false">
                                    <small class="text-muted">This field will be autofilled when an RR# is selected</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
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

    <!-- Scripts -->
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .filtered-out {
            display: none !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize pagination with the correct table ID
            initPagination({
                tableId: 'equipmentTable',
                currentPage: 1
            });

            // Initialize allRows for pagination.js
            window.allRows = Array.from(document.querySelectorAll('#equipmentTable tr'));

            // Call updatePagination after a short delay to ensure pagination.js is loaded
            setTimeout(function() {
                if (typeof updatePagination === 'function') {
                    updatePagination();
                }
            }, 100);

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

            // Run immediately and after a short delay
            forcePaginationCheck();
            setTimeout(forcePaginationCheck, 200);

            // Run after any filter changes
            const searchInput = document.getElementById('searchEquipment');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    setTimeout(forcePaginationCheck, 100);
                });
            }

            // Run after rows per page changes
            const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
            if (rowsPerPageSelect) {
                rowsPerPageSelect.addEventListener('change', function() {
                    setTimeout(forcePaginationCheck, 100);
                });
            }

            // Handle search functionality
            $('#searchEquipment').on('input', function() {
                const searchText = $(this).val().toLowerCase();
                $('#equipmentTable tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    const shouldShow = rowText.includes(searchText);

                    // Use the filtered-out class that pagination.js understands
                    if (shouldShow) {
                        $(this).removeClass('filtered-out');
                    } else {
                        $(this).addClass('filtered-out');
                    }
                });

                // Update pagination after filtering
                if (typeof updatePagination === 'function') {
                    updatePagination();
                }

                // Check if pagination controls should be shown
                forcePaginationCheck();
            });
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

                const rows = Array.from(tbody.querySelectorAll("tr")).sort((a, b) => {
                    const aText = a.children[columnIndex].textContent.trim();
                    const bText = b.children[columnIndex].textContent.trim();

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
            });
        });
        $(function() {
            // 1) EDIT button handler
            $(document).on('click', '.edit-equipment', function() {
                const d = $(this).data();
                $('#edit_equipment_id').val(d.id);
                // make sure the asset-tag exists in the dropdown
                const $asset = $('#edit_equipment_asset_tag');
                if (!$asset.find(`option[value="${d.asset}"]`).length) {
                    $asset.append(`<option value="${d.asset}">${d.asset}</option>`);
                }
                $asset.val(d.asset).trigger('change');

                // same for RR#
                const $rr = $('#edit_rr_no');
                if (d.rr && !$rr.find(`option[value="${d.rr}"]`).length) {
                    $rr.append(`<option value="${d.rr}">${d.rr}</option>`);
                }
                $rr.val(d.rr).trigger('change');

                // populate the rest of the fields
                $('#edit_asset_description_1').val(d.desc1);
                $('#edit_asset_description_2').val(d.desc2);
                $('#edit_specifications').val(d.spec);
                $('#edit_brand').val(d.brand);
                $('#edit_model').val(d.model);
                $('#edit_serial_number').val(d.serial);
                $('#edit_location').val(d.location);
                $('#edit_accountable_individual').val(d.accountable);
                $('#edit_remarks').val(d.remarks);
                
                // Check if RR# exists and apply readonly state to Location and Accountable Individual fields
                if (d.rr) {
                    // Call fetchRRInfo directly with showNotification set to false
                    fetchRRInfo(d.rr, 'edit', false);
                }

                // show the modal
                $('#editEquipmentModal').modal('show');
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
                    initPagination({
                        tableId: 'equipmentTable',
                        currentPage: 1
                    });
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
        
        function fetchRRInfo(rrNo, formType, showNotification = true) {
    $.ajax({
        url: 'get_rr_info.php',
        method: 'GET',
        data: { rr_no: rrNo },
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            if (response.status === 'success' && response.data) {
                if (formType === 'edit') {
                    // For edit form
                    const $accountableField = $('#edit_accountable_individual');
                    const $locationField = $('#edit_location');
                    
                    // Get current values
                    const currentAccountable = $accountableField.val();
                    const currentLocation = $locationField.val();
                    
                    // Only update if empty or matches RR data
                    if (!currentAccountable || currentAccountable === response.data.accountable_individual) {
                    $accountableField.val(response.data.accountable_individual || '');
                    }
                    
                    if (!currentLocation || currentLocation === response.data.location) {
                    $locationField.val(response.data.location || '');
                    }
                    
                    // Make fields readonly and mark as autofilled regardless
                    $accountableField.prop('readonly', true).attr('data-autofill', 'true').addClass('bg-light');
                    $locationField.prop('readonly', true).attr('data-autofill', 'true').addClass('bg-light');
                } else {
                    // For add form
                    const $accountableField = $('input[name="accountable_individual"]');
                    const $locationField = $('input[name="location"]');
                    
                    // Get current values
                    const currentAccountable = $accountableField.val();
                    const currentLocation = $locationField.val();
                    
                    // Only update if empty or matches RR data
                    if (!currentAccountable || currentAccountable === response.data.accountable_individual) {
                    $accountableField.val(response.data.accountable_individual || '');
                    }
                    
                    if (!currentLocation || currentLocation === response.data.location) {
                    $locationField.val(response.data.location || '');
                    }
                    
                    // Make fields readonly and mark as autofilled regardless
                    $accountableField.prop('readonly', true).attr('data-autofill', 'true').addClass('bg-light');
                    $locationField.prop('readonly', true).attr('data-autofill', 'true').addClass('bg-light');
                }
                
                // Only show notification if requested
                if (showNotification) {
                    showToast('Location and Accountable Individual autofilled from RR data', 'info');
                }
            }
        },
        error: function() {
            if (showNotification) {
                showToast('Error fetching RR information', 'error');
            }
        }
    });
} 
    </script>
</body>

</html>
