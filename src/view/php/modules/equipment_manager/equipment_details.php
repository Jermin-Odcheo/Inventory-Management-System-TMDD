<?php
session_start();
ob_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

// -------------------------
// AJAX Request Handling
// -------------------------
if (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' &&
    $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])
) {
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
            try {
                $pdo->beginTransaction();
                $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                validateRequiredFields(['asset_tag', 'asset_description_1', 'asset_description_2', 'specifications', 'serial_number']);

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
                    $_POST['rr_no'] ?? null,
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
            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
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
                    $_POST['rr_no'],
                    $_POST['date_created'],
                    $_POST['remarks'],
                    $_POST['equipment_id']
                ];
                $stmt = $pdo->prepare("UPDATE equipment_details SET 
            asset_tag = ?, asset_description_1 = ?, asset_description_2 = ?, specifications = ?, 
            brand = ?, model = ?, serial_number = ?, location = ?, accountable_individual = ?, 
            rr_no = ?, date_created = ?, remarks = ? WHERE id = ?");
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
            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
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
            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
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
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: /public/index.php");
    exit();
}
$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? '';
unset($_SESSION['errors'], $_SESSION['success']);

try {
    $stmt = $pdo->query("SELECT id, asset_tag, asset_description_1, asset_description_2,
                         specifications, brand, model, serial_number, location, accountable_individual, rr_no,
                         remarks, date_created FROM equipment_details WHERE is_disabled = 0 ORDER BY id DESC");
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
    <style>
        /* Hide pagination buttons when table is empty */
        .table tbody:empty ~ .container-fluid #prevPage,
        .table tbody:empty ~ .container-fluid #nextPage {
            display: none !important;
        }
        
        /* Hide pagination info when table is empty */
        .table tbody:empty ~ .container-fluid .text-muted #currentPage,
        .table tbody:empty ~ .container-fluid .text-muted #rowsPerPage,
        .table tbody:empty ~ .container-fluid .text-muted #totalRows {
            display: inline-block;
            min-width: 10px;
        }
        
        /* Ensure empty tbody has some height for CSS selectors to work */
        .table tbody:empty {
            display: block;
            min-height: 10px;
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
                    <div class="row align-items-lg-center g-2">
                        <div class="col-auto">
                            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                                <i class="bi bi-plus-lg"></i> Create Equipment
                            </button>
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
                    <div class="row mt-3 g-2" id="dateInputsContainer" style="display: none;">
                        <div class="col-md-6">
                            <div class="d-flex gap-2" id="monthPickerContainer" style="display: none;">
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
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex gap-2" id="dateRangePickers" style="display: none;">
                                <input type="date" class="form-control" id="dateFrom" placeholder="From">
                                <input type="date" class="form-control" id="dateTo" placeholder="To">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" id="table">
                    <table class="table" id="edTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Asset Tag</th>
                                <th>Desc 1</th>
                                <th>Desc 2</th>
                                <th>Specification</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Serial #</th>
                                <th>Acquired Date</th>
                                <th>Created Date</th>
                                <th>Modified Date</th>
                                <th>RR #</th>
                                <th>Location</th>
                                <th>Accountable Individual</th>
                                <th>Remarks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                        <td><?= !empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                                        <td><?= safeHtml($equipment['rr_no']); ?></td>
                                        <td><?= safeHtml($equipment['location']); ?></td>
                                        <td><?= safeHtml($equipment['accountable_individual']); ?></td>
                                        <td><?= safeHtml($equipment['remarks']); ?></td>
                                        <td>
                                            <div class="btn-group">
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

                                                <button class="btn btn-outline-danger btn-sm remove-equipment"
                                                    data-id="<?= safeHtml($equipment['id']); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                    id="totalRows">100</span>
                                entries
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
                        <div class="row mt-3">
                            <div class="col-12">
                                <ul class="pagination justify-content-center" id="pagination"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End of Pagination Controls -->


            </div>
        </section>
    </div>

    <!-- Modals -->
    <!-- Add Equipment Modal -->
    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-labelledby="addEquipmentLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addEquipmentForm">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag <span style="color: red;">*</span></label>
                            <input type="text" class="form-control" name="asset_tag" required>
                        </div>
                        <div class="mb-3">
                            <label for="asset_description_1" class="form-label">Description 1</label>
                            <input type="text" class="form-control" name="asset_description_1" required>
                        </div>
                        <div class="mb-3">
                            <label for="asset_description_2" class="form-label">Description 2</label>
                            <input type="text" class="form-control" name="asset_description_2" required>
                        </div>
                        <div class="mb-3">
                            <label for="specifications" class="form-label">Specification</label>
                            <textarea class="form-control" name="specifications" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand" required>
                        </div>
                        <div class="mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" required>
                        </div>
                        <div class="mb-3">
                            <label for="serial_number" class="form-label">Serial Number <span style="color: red;">*</span></label>
                            <input type="text" class="form-control" name="serial_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="rr_no" class="form-label">RR#</label>
                            <input type="text" class="form-control" name="rr_no">
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" name="location">
                        </div>
                        <div class="mb-3">
                            <label for="accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual">
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3 text-end">
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editEquipmentForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="equipment_id" id="edit_equipment_id">
                        <div class="mb-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag <span style="color: red;">*</span></label>
                            <input type="text" class="form-control" name="asset_tag" id="edit_asset_tag" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_asset_description_1" class="form-label">Description 1</label>
                            <input type="text" class="form-control" name="asset_description_1"
                                id="edit_asset_description_1">
                        </div>
                        <div class="mb-3">
                            <label for="edit_asset_description_2" class="form-label">Description 2</label>
                            <input type="text" class="form-control" name="asset_description_2"
                                id="edit_asset_description_2">
                        </div>
                        <div class="mb-3">
                            <label for="edit_specifications" class="form-label">Specification</label>
                            <textarea class="form-control" name="specifications" id="edit_specifications"
                                rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand" id="edit_brand">
                        </div>
                        <div class="mb-3">
                            <label for="edit_model" class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" id="edit_model">
                        </div>
                        <div class="mb-3">
                            <label for="edit_serial_number" class="form-label">Serial Number <span style="color: red;">*</span></label>
                            <input type="text" class="form-control" name="serial_number" id="edit_serial_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_rr_no" class="form-label">RR#</label>
                            <input type="text" class="form-control" name="rr_no" id="edit_rr_no">
                        </div>
                        <div class="mb-3">
                            <label for="edit_location" class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="edit_location">
                        </div>
                        <div class="mb-3">
                            <label for="edit_accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual"
                                id="edit_accountable_individual">
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
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Removal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to remove this Equipment Detail?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Remove</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script>
        // Override updatePagination function to properly handle empty tables
        document.addEventListener('DOMContentLoaded', function() {
            // Keep reference to original function
            const originalUpdatePagination = window.updatePagination;
            
            // Override with custom implementation that ensures empty tables have no pagination
            window.updatePagination = function() {
                // Get row count directly from table
                const rowCount = document.querySelectorAll('#edTable tbody tr').length;
                console.log('Override updatePagination called, row count:', rowCount);
                
                // If table is empty, hide pagination elements
                if (rowCount === 0) {
                    console.log('Table is empty, hiding pagination');
                    document.getElementById('currentPage').textContent = '0';
                    document.getElementById('rowsPerPage').textContent = '0';
                    document.getElementById('totalRows').textContent = '0';
                    document.getElementById('prevPage').style.display = 'none';
                    document.getElementById('nextPage').style.display = 'none';
                    document.getElementById('pagination').innerHTML = '';
                    return;
                }
                
                // Otherwise call original function
                if (originalUpdatePagination) {
                    originalUpdatePagination();
                }
            };
            
            // Initial check - directly hide pagination if table is empty
            if (document.querySelectorAll('#edTable tbody tr').length === 0) {
                document.getElementById('currentPage').textContent = '0';
                document.getElementById('rowsPerPage').textContent = '0';
                document.getElementById('totalRows').textContent = '0';
                document.getElementById('prevPage').style.display = 'none';
                document.getElementById('nextPage').style.display = 'none';
                document.getElementById('pagination').innerHTML = '';
            }
        });
        
        $(document).ready(function() {
            // Track if rows are being filtered
            let isFiltering = false;
            let filteredRowCount = 0;
            
            // Direct approach to check for empty table
            if ($('#edTable tbody tr').length === 0) {
                console.log('Table is empty - hiding pagination');
                $('#prevPage, #nextPage').css({'display': 'none', 'visibility': 'hidden'});
                $('#currentPage, #rowsPerPage, #totalRows').text('0');
                $('#pagination').empty();
            }
            
            // Check if table is empty on page load and hide pagination if needed
            const tableRows = $('.table tbody tr').length;
            
            // Bind search/filter events
            $('#searchEquipment, #filterEquipment').on('input change', filterTable);
            $('#dateFilter').on('change', function() {
                const value = $(this).val();
                $('#dateInputsContainer, #monthPickerContainer, #dateRangePickers, #dateFrom, #dateTo').hide();
                if (value === 'month') {
                    $('#dateInputsContainer').show();
                    $('#monthPickerContainer').show();
                } else if (value === 'range') {
                    $('#dateInputsContainer').show();
                    $('#dateRangePickers').show();
                    $('#dateFrom, #dateTo').show();
                } else {
                    filterTable();
                }
            });
            $('#monthSelect, #yearSelect, #dateFrom, #dateTo').on('change', function() {
                if (($('#monthSelect').val() && $('#yearSelect').val()) || ($('#dateFrom').val() && $('#dateTo').val())) {
                    filterTable();
                }
            });

            function filterTable() {
                const searchText = $('#searchEquipment').val().toLowerCase();
                const filterType = $('#filterEquipment').val();
                const dateFilterType = $('#dateFilter').val();
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                // Determine if any filter is active
                const hasTextFilter = !!searchText;
                const hasTypeFilter = !!filterType;
                const hasDateFilter = (dateFilterType === 'month' && selectedMonth && selectedYear) ||
                    (dateFilterType === 'range' && dateFrom && dateTo) ||
                    dateFilterType === 'asc' || dateFilterType === 'desc';

                isFiltering = hasTextFilter || hasTypeFilter || hasDateFilter;

                // Reset visibility first if sorting
                if (dateFilterType === 'asc' || dateFilterType === 'desc') {
                    $('.table tbody tr').show();
                    const tbody = $('.table tbody');
                    const rows = tbody.find('tr').toArray();
                    rows.sort((a, b) => {
                        const dateA = new Date($(a).find('td:eq(8)').text());
                        const dateB = new Date($(b).find('td:eq(8)').text());
                        return dateFilterType === 'asc' ? dateA - dateB : dateB - dateA;
                    });
                    tbody.append(rows);

                    // Update pagination after sort
                    if (typeof updatePagination === 'function') {
                        updatePagination();
                    }
                    return;
                }

                // Count rows that match filter
                filteredRowCount = 0;

                $('.table tbody tr').each(function() {
                    const $row = $(this);
                    const rowText = $row.text().toLowerCase();
                    const typeCell = $row.find('td:eq(2)').text().trim();
                    const dateCell = $row.find('td:eq(8)').text();
                    const date = new Date(dateCell);

                    const searchMatch = !searchText || rowText.includes(searchText);
                    const typeMatch = !filterType || typeCell === filterType;
                    let dateMatch = true;
                    if (dateFilterType === 'month' && selectedMonth && selectedYear) {
                        dateMatch = (date.getMonth() + 1 === parseInt(selectedMonth)) && (date.getFullYear() === parseInt(selectedYear));
                    } else if (dateFilterType === 'range' && dateFrom && dateTo) {
                        const from = new Date(dateFrom);
                        const to = new Date(dateTo);
                        to.setHours(23, 59, 59);
                        dateMatch = date >= from && date <= to;
                    }

                    const matches = searchMatch && typeMatch && dateMatch;
                    if (isFiltering) {
                        // When filtering, show only matching rows
                        $row.toggle(matches);
                        if (matches) filteredRowCount++;
                    } else {
                        // When not filtering, show all rows
                        $row.show();
                    }
                });

                // Update pagination information manually if filtering shows no results
                if (isFiltering && filteredRowCount === 0) {
                    // Hide pagination controls immediately for empty result set
                    $('#currentPage, #rowsPerPage, #totalRows').text('0');
                    $('#prevPage, #nextPage').css('display', 'none');
                    $('#pagination').empty();
                    
                    // Show "No results" message if not already present
                    if ($('.table tbody tr.no-results').length === 0) {
                        $('.table tbody').append(`
                            <tr class="no-results">
                                <td colspan="16" class="text-center py-4">
                                    <div class="alert alert-warning mb-0">
                                        <i class="bi bi-exclamation-circle me-2"></i> No results found for the current filter criteria.
                                    </div>
                                </td>
                            </tr>
                        `);
                    }
                } else {
                    // Remove "No results" message if it exists
                    $('.table tbody tr.no-results').remove();
                    
                    // Show pagination controls (updatePagination will properly set their visibility)
                    $('#prevPage, #nextPage').css('display', '');

                    // Reset to page 1 when filtering changes results
                    if (typeof currentPage !== 'undefined') {
                        currentPage = 1;
                    }

                    // Update pagination
                    if (typeof updatePagination === 'function') {
                        updatePagination();
                    }
                }
            }

            // Edit Equipment event
            $(document).on('click', '.edit-equipment', function() {
                const data = $(this).data();
                $('#edit_equipment_id').val(data.id);
                $('#edit_asset_tag').val(data.asset);
                $('#edit_asset_description_1').val(data.desc1);
                $('#edit_asset_description_2').val(data.desc2);
                $('#edit_specifications').val(data.spec);
                $('#edit_brand').val(data.brand);
                $('#edit_model').val(data.model);
                $('#edit_serial_number').val(data.serial);
                $('#edit_location').val(data.location);
                $('#edit_accountable_individual').val(data.accountable);
                $('#edit_rr_no').val(data.rr);
                $('#edit_remarks').val(data.remarks);
                $('#editEquipmentModal').modal('show');
            });

            // Remove Equipment event
            var deleteId = null;
            $(document).on('click', '.remove-equipment', function(e) {
                e.preventDefault();
                deleteId = $(this).data('id');
                new bootstrap.Modal(document.getElementById('deleteEDModal')).show();
            });

            $('#confirmDeleteBtn').on('click', function() {
                if (deleteId) {
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
                        success: function(response) {
                            showToast(response.message, response.status === 'success' ? 'success' : 'error');
                            refreshEquipmentList();
                            bootstrap.Modal.getInstance(document.getElementById('deleteEDModal')).hide();
                            $('.modal-backdrop').remove();
                        },
                        error: function(xhr, status, error) {
                            showToast('Error removing equipment: ' + error, 'error');
                        }
                    });
                }
            });

            function refreshEquipmentList() {
                $.ajax({
                    url: window.location.href,
                    method: 'GET',
                    success: function(response) {
                        // Extract just the table body content from the response
                        const $responseHtml = $(response);
                        const newTbodyContent = $responseHtml.find('.table tbody').html();
                        
                        // Update the table with new content
                        $('.table tbody').html(newTbodyContent);
                        
                        // Remove any lingering no-results messages
                        $('.table tbody tr.no-results').remove();

                        // Reset filtering state
                        isFiltering = false;
                        filteredRowCount = 0;

                        // Check if there are any rows after refresh
                        const rowCount = $('.table tbody tr').length;

                        if (rowCount === 0) {
                            // If no rows, hide pagination elements
                            $('#currentPage, #rowsPerPage, #totalRows').text('0');
                            $('#prevPage, #nextPage').css('display', 'none');
                            $('#pagination').empty();
                        } else {
                            // Show pagination controls (updatePagination will set proper visibility)
                            $('#prevPage, #nextPage').css('display', '');

                            // Reset to page 1 when refreshing
                            if (typeof currentPage !== 'undefined') {
                                currentPage = 1;
                            }

                            // Update pagination
                            if (typeof updatePagination === 'function') {
                                // Small delay to ensure DOM is fully updated
                                setTimeout(updatePagination, 50);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error refreshing list:', error);
                    }
                });
            }

            $(document).off('submit', '#addEquipmentForm').on('submit', '#addEquipmentForm', function(e) {
                e.preventDefault();
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('addEquipmentModal')).hide();
                            showToast(response.message, 'success');
                            $('#addEquipmentForm')[0].reset();
                            refreshEquipmentList();
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error creating equipment: ' + error, 'error');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html('Create Equipment');
                    }
                });
            });

            $('#editEquipmentForm').off('submit').on('submit', function(e) {
                e.preventDefault();
                const submitBtn = $(this).find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('editEquipmentModal')).hide();
                            showToast(response.message, 'success');
                            refreshEquipmentList();
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMsg = 'Error updating equipment: ';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMsg += response.message || error;
                        } catch (e) {
                            errorMsg += error;
                        }
                        showToast(errorMsg, 'error');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html('Save Changes');
                    }
                });
            });

        });
    </script>
</body>

</html>