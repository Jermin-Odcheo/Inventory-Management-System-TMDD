<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed
include ('../../general/header.php');
// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: /public/index.php");
    exit();
}

// -------------------------
// PROCESS AJAX REQUESTS
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = array('status' => '', 'message' => '');

    switch ($_POST['action']) {
        case 'add':
            try {
                error_log('Received POST data: ' . print_r($_POST, true));

                // Begin transaction and set the current user for audit logging
                $pdo->beginTransaction();
                $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);

                // Insert the equipment details
                $stmt = $pdo->prepare("INSERT INTO equipment_details (
                    asset_tag, 
                    asset_description_1, 
                    asset_description_2, 
                    specifications, 
                    brand, 
                    model, 
                    serial_number, 
                    date_created, 
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['asset_tag'],
                    $_POST['asset_description_1'],
                    $_POST['asset_description_2'],
                    $_POST['specifications'],
                    $_POST['brand'],
                    $_POST['model'],
                    $_POST['serial_number'],
                    $_POST['date_created'],
                    $_POST['remarks']
                ]);

                $newEquipmentId = $pdo->lastInsertId();

                // Prepare new values for audit log
                $newValues = json_encode([
                    'asset_tag' => $_POST['asset_tag'],
                    'asset_description_1' => $_POST['asset_description_1'],
                    'asset_description_2' => $_POST['asset_description_2'],
                    'specifications' => $_POST['specifications'],
                    'brand' => $_POST['brand'],
                    'model' => $_POST['model'],
                    'serial_number' => $_POST['serial_number'],
                    'date_created' => $_POST['date_created'],
                    'remarks' => $_POST['remarks']
                ]);

                // Insert audit log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID,
                    EntityID,
                    Module,
                    Action,
                    Details,
                    OldVal,
                    NewVal,
                    Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $newEquipmentId,
                    'Equipment Details',
                    'Add',
                    'New equipment added',
                    null,
                    $newValues,
                    'Successful'
                ]);

                $pdo->commit();

                $response['status'] = 'success';
                $response['message'] = 'Equipment Details has been added successfully.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Database error: ' . $e->getMessage());
                $response['status'] = 'error';
                $response['message'] = 'Error adding equipment: ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            break;

        case 'update':
            try {
                $pdo->beginTransaction();

                // Get old equipment details for audit log
                $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                $stmt->execute([$_POST['equipment_id']]);
                $oldEquipment = $stmt->fetch(PDO::FETCH_ASSOC);

                // Update the equipment details
                $stmt = $pdo->prepare("UPDATE equipment_details SET 
                    asset_tag = ?,
                    asset_description_1 = ?,
                    asset_description_2 = ?,
                    specifications = ?,
                    brand = ?,
                    model = ?,
                    serial_number = ?,
                    date_created = ?,
                    remarks = ?
                    WHERE id = ?");
                $stmt->execute([
                    $_POST['asset_tag'],
                    $_POST['asset_description_1'],
                    $_POST['asset_description_2'],
                    $_POST['specifications'],
                    $_POST['brand'],
                    $_POST['model'],
                    $_POST['serial_number'],
                    $_POST['date_created'],
                    $_POST['remarks'],
                    $_POST['equipment_id']
                ]);

                // Prepare audit log values
                $oldValue = json_encode([
                    'id' => $oldEquipment['id'],
                    'asset_tag' => $oldEquipment['asset_tag'],
                    'asset_description_1' => $oldEquipment['asset_description_1'],
                    'asset_description_2' => $oldEquipment['asset_description_2'],
                    'specifications' => $oldEquipment['specifications'],
                    'brand' => $oldEquipment['brand'],
                    'model' => $oldEquipment['model'],
                    'serial_number' => $oldEquipment['serial_number'],
                    'date_created' => $oldEquipment['date_created'],
                    'remarks' => $oldEquipment['remarks']
                ]);
                $newValues = json_encode([
                    'asset_tag' => $_POST['asset_tag'],
                    'asset_description_1' => $_POST['asset_description_1'],
                    'asset_description_2' => $_POST['asset_description_2'],
                    'specifications' => $_POST['specifications'],
                    'brand' => $_POST['brand'],
                    'model' => $_POST['model'],
                    'serial_number' => $_POST['serial_number'],
                    'date_created' => $_POST['date_created'],
                    'remarks' => $_POST['remarks']
                ]);

                // Insert audit log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID,
                    EntityID,
                    Module,
                    Action,
                    Details,
                    OldVal,
                    NewVal,
                    Status
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
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $response['status'] = 'error';
                $response['message'] = 'Error updating equipment: ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            break;

        case 'delete':
            try {
                // Get equipment details for audit log
                $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                $stmt->execute([$_POST['equipment_id']]);
                $equipmentData = $stmt->fetch(PDO::FETCH_ASSOC);

                $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);

                // Prepare audit log data
                $oldValue = json_encode([
                    'id' => $equipmentData['id'],
                    'asset_tag' => $equipmentData['asset_tag'],
                    'asset_description_1' => $equipmentData['asset_description_1'],
                    'asset_description_2' => $equipmentData['asset_description_2'],
                    'specifications' => $equipmentData['specifications'],
                    'brand' => $equipmentData['brand'],
                    'model' => $equipmentData['model'],
                    'serial_number' => $equipmentData['serial_number'],
                    'date_created' => $equipmentData['date_created']
                ]);

                // Insert audit log
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID,
                    EntityID,
                    Module,
                    Action,
                    Details,
                    OldVal,
                    NewVal,
                    Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $equipmentData['id'],
                    'Equipment Management',
                    'Delete',
                    'Equipment has been deleted',
                    $oldValue,
                    null,
                    'Successful'
                ]);

                // Perform the delete
                $stmt = $pdo->prepare("DELETE FROM equipment_details WHERE id = ?");
                $stmt->execute([$_POST['equipment_id']]);

                $response['status'] = 'success';
                $response['message'] = 'Equipment Details deleted successfully.';
            } catch (PDOException $e) {
                $response['status'] = 'error';
                $response['message'] = 'Error deleting equipment: ' . $e->getMessage();
            }
            echo json_encode($response);
            exit;
            break;
    }
}

// -------------------------
// NON-AJAX LOGIC (HTML PAGE RENDERING)
// -------------------------
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

try {
    $stmt = $pdo->query("SELECT id, asset_tag, asset_description_1, asset_description_2,
                         specifications, brand, model, serial_number, 
                         invoice_no, rr_no, equipment_location_id, equipment_status_id, 
                         remarks, date_created 
                         FROM equipment_details 
                         ORDER BY id DESC");
    $equipmentDetails = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Details: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Equipment Details Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Custom CSS for layout and responsiveness */
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            padding-top: 80px;
            overflow-x: hidden;
        }
        h2.mb-4 { margin-top: 20px; }
        .main-content { margin-left: 300px; padding: 20px; transition: margin-left 0.3s ease; }
        .table-responsive { overflow-x: auto; max-width: 100%; }
        table { width: 100%; table-layout: auto; }
        th, td {
            white-space: normal;
            text-overflow: clip;
            min-width: 100px;
            max-width: 200px;
            padding: 8px;
            vertical-align: middle;
        }
        table th:nth-child(1),
        table td:nth-child(1) { min-width: 50px; max-width: 70px; }
        table th:nth-child(2),
        table td:nth-child(2) { min-width: 100px; max-width: 150px; }
        table th:last-child,
        table td:last-child { min-width: 160px; white-space: nowrap; }
        .card-body { overflow-x: auto; }
        .btn-group { display: flex; gap: 5px; flex-wrap: nowrap; }
        .btn-group .btn { padding: 0.25rem 0.5rem; white-space: nowrap; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
<?php include '../../general/sidebar.php'; ?>
<h2 class="mb-4" style="margin-left: 320px;">Equipment Details Management</h2>
<div class="container-fluid" style="margin-left: 320px; padding: 20px; width: calc(100vw - 340px);">
    <!-- Success Message -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php foreach ($errors as $err): ?>
                <p><i class="bi bi-exclamation-triangle"></i> <?php echo $err; ?></p>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <!-- Equipment Details Card -->
    <div class="card shadow" style="margin-top: -10px;">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul"></i> List of Equipment Details</span>
            <div class="input-group w-auto">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="searchEquipment" class="form-control" placeholder="Search equipment...">
            </div>
        </div>
        <div class="card-body p-3">
            <!-- Controls for adding/filtering -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                        <i class="bi bi-plus-circle"></i> Add Equipment
                    </button>
                    <select class="form-select form-select-sm" id="filterEquipment" style="width: auto;">
                        <option value="">Filter Equipment Type</option>
                        <?php
                        $equipmentTypes = array_unique(array_column($equipmentDetails, 'asset_description_1'));
                        foreach($equipmentTypes as $type) {
                            if (!empty($type)) {
                                echo "<option value='" . htmlspecialchars($type) . "'>" . htmlspecialchars($type) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <!-- Date Filter Controls -->
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" id="dateFilter" style="width: auto;">
                            <option value="">Filter by Date</option>
                            <option value="desc">Newest to Oldest</option>
                            <option value="asc">Oldest to Newest</option>
                            <option value="month">Specific Month</option>
                            <option value="range">Custom Date Range</option>
                        </select>
                        <div id="dateInputsContainer" style="display: none;">
                            <div class="d-flex gap-2" id="monthPickerContainer" style="display: none;">
                                <select class="form-select form-select-sm" id="monthSelect" style="min-width: 130px;">
                                    <option value="">Select Month</option>
                                    <?php
                                    $months = [
                                        'January', 'February', 'March', 'April', 'May', 'June',
                                        'July', 'August', 'September', 'October', 'November', 'December'
                                    ];
                                    foreach ($months as $index => $month) {
                                        echo "<option value='" . ($index + 1) . "'>" . $month . "</option>";
                                    }
                                    ?>
                                </select>
                                <select class="form-select form-select-sm" id="yearSelect" style="min-width: 110px;">
                                    <option value="">Select Year</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                        echo "<option value='" . $year . "'>" . $year . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="d-flex gap-2" id="dateRangePickers" style="display: none;">
                                <input type="date" class="form-control form-control-sm" id="dateFrom" placeholder="From">
                                <input type="date" class="form-control form-control-sm" id="dateTo" placeholder="To">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Equipment Details Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-sm mb-0" id="table">
                    <thead class="table-dark">
                    <tr>
                        <th style="width: 50px">#</th>
                        <th style="width: 100px">Asset Tag</th>
                        <th style="width: 150px">Description 1</th>
                        <th style="width: 150px">Description 2</th>
                        <th style="width: 150px">Specification</th>
                        <th style="width: 100px">Brand</th>
                        <th style="width: 100px">Model</th>
                        <th style="width: 120px">Serial #</th>
                        <th style="width: 120px">Acquired Date</th>
                        <th style="width: 120px">Created Date</th>
                        <th style="width: 120px">Modified Date</th>
                        <th style="width: 100px">RR #</th>
                        <th style="width: 150px">Location</th>
                        <th style="width: 150px">Accountable Individual</th>
                        <th style="width: 150px">Remarks</th>
                        <th style="width: 160px">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($equipmentDetails as $equipment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($equipment['id']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['asset_tag']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['asset_description_1']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['asset_description_2']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['specifications']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['brand']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['model']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['serial_number']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['date_created']); ?></td>
                            <td><?php echo !empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                            <td><?php echo !empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                            <td><?php echo htmlspecialchars($equipment['rr_no']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['equipment_location_id']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['equipment_status_id']); ?></td>
                            <td><?php echo htmlspecialchars($equipment['remarks']); ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a class="btn btn-sm btn-outline-primary edit-equipment"
                                       data-id="<?php echo htmlspecialchars($equipment['id']); ?>"
                                       data-asset="<?php echo htmlspecialchars($equipment['asset_tag']); ?>"
                                       data-desc1="<?php echo htmlspecialchars($equipment['asset_description_1']); ?>"
                                       data-desc2="<?php echo htmlspecialchars($equipment['asset_description_2']); ?>"
                                       data-spec="<?php echo htmlspecialchars($equipment['specifications']); ?>"
                                       data-brand="<?php echo htmlspecialchars($equipment['brand']); ?>"
                                       data-model="<?php echo htmlspecialchars($equipment['model']); ?>"
                                       data-serial="<?php echo htmlspecialchars($equipment['serial_number']); ?>"
                                       data-date="<?php echo htmlspecialchars($equipment['date_created']); ?>"
                                       data-remarks="<?php echo htmlspecialchars($equipment['remarks']); ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <a class="btn btn-sm btn-outline-danger delete-equipment"
                                       data-id="<?php echo htmlspecialchars($equipment['id']); ?>" href="#">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <div class="container-fluid">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-sm-auto">
                        <div class="text-muted">
                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows">100</span> entries
                        </div>
                    </div>
                    <div class="col-12 col-sm-auto ms-sm-auto">
                        <div class="d-flex align-items-center gap-2">
                            <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                <i class="bi bi-chevron-left"></i>
                                Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                            <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                Next
                                <i class="bi bi-chevron-right"></i>
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

<!-- Add Equipment Modal -->
<div class="modal fade" id="addEquipmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Equipment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addEquipmentForm">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="asset_tag" class="form-label">Asset Tag</label>
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
                        <label for="serial_number" class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="date_created" class="form-label">Date Created</label>
                        <input type="datetime-local" class="form-control" name="date_created" required>
                    </div>
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Add Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Equipment Modal -->
<div class="modal fade" id="editEquipmentModal" tabindex="-1">
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
                        <label for="edit_asset_tag" class="form-label">Asset Tag</label>
                        <input type="text" class="form-control" name="asset_tag" id="edit_asset_tag">
                    </div>
                    <div class="mb-3">
                        <label for="edit_asset_description_1" class="form-label">Description 1</label>
                        <input type="text" class="form-control" name="asset_description_1" id="edit_asset_description_1">
                    </div>
                    <div class="mb-3">
                        <label for="edit_asset_description_2" class="form-label">Description 2</label>
                        <input type="text" class="form-control" name="asset_description_2" id="edit_asset_description_2">
                    </div>
                    <div class="mb-3">
                        <label for="edit_specifications" class="form-label">Specification</label>
                        <textarea class="form-control" name="specifications" id="edit_specifications" rows="3"></textarea>
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
                        <label for="edit_serial_number" class="form-label">Serial Number</label>
                        <input type="text" class="form-control" name="serial_number" id="edit_serial_number">
                    </div>
                    <div class="mb-3">
                        <label for="edit_date_created" class="form-label">Date Acquired</label>
                        <input type="datetime-local" class="form-control" name="date_created" id="edit_date_created">
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

<!-- Pagination Script -->
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

<!-- Real-Time Table Filtering & Date Filter Script -->
<script>
    $(document).ready(function() {
        // Search functionality
        $('#searchEquipment').on('input', function() {
            filterTable();
        });

        // Filter functionality
        $('#filterEquipment').on('change', function() {
            filterTable();
        });

        // Date filter change handler
        $('#dateFilter').on('change', function() {
            const value = $(this).val();
            $('#dateInputsContainer').hide();
            $('#monthPickerContainer, #dateRangePickers').hide();
            $('#dateFrom, #dateTo').hide();
            switch(value) {
                case 'month':
                    $('#dateInputsContainer').show();
                    $('#monthPickerContainer').show();
                    break;
                case 'range':
                    $('#dateInputsContainer').show();
                    $('#dateRangePickers').show();
                    break;
                default:
                    filterTable();
                    break;
            }
        });

        // Month and Year select change handler
        $('#monthSelect, #yearSelect').on('change', function() {
            if ($('#monthSelect').val() && $('#yearSelect').val()) {
                filterTable();
            }
        });

        function filterTable() {
            const searchText = $('#searchEquipment').val().toLowerCase();
            const filterType = $('#filterEquipment').val().toLowerCase();
            const dateFilterType = $('#dateFilter').val();
            const selectedMonth = $('#monthSelect').val();
            const selectedYear = $('#yearSelect').val();
            const dateFrom = $('#dateFrom').val();
            const dateTo = $('#dateTo').val();

            $(".table tbody tr").each(function() {
                const row = $(this);
                const rowText = row.text().toLowerCase();
                const typeCell = row.find('td:eq(2)').text().toLowerCase();
                const dateCell = row.find('td:eq(8)').text();
                const date = new Date(dateCell);
                const searchMatch = rowText.indexOf(searchText) > -1;
                const typeMatch = !filterType || typeCell === filterType;
                let dateMatch = true;

                switch(dateFilterType) {
                    case 'asc':
                        const tbody = $('.table tbody');
                        const rows = tbody.find('tr').toArray();
                        rows.sort((a, b) => {
                            const dateA = new Date($(a).find('td:eq(8)').text());
                            const dateB = new Date($(b).find('td:eq(8)').text());
                            return dateA - dateB;
                        });
                        tbody.append(rows);
                        return;
                    case 'desc':
                        const tbody2 = $('.table tbody');
                        const rows2 = tbody2.find('tr').toArray();
                        rows2.sort((a, b) => {
                            const dateA = new Date($(a).find('td:eq(8)').text());
                            const dateB = new Date($(b).find('td:eq(8)').text());
                            return dateB - dateA;
                        });
                        tbody2.append(rows2);
                        return;
                    case 'month':
                        if (selectedMonth && selectedYear) {
                            dateMatch = date.getMonth() + 1 === parseInt(selectedMonth) &&
                                date.getFullYear() === parseInt(selectedYear);
                        }
                        break;
                    case 'range':
                        if (dateFrom && dateTo) {
                            const from = new Date(dateFrom);
                            const to = new Date(dateTo);
                            to.setHours(23, 59, 59);
                            dateMatch = date >= from && date <= to;
                        }
                        break;
                }
                row.toggle(searchMatch && typeMatch && dateMatch);
            });
        }
    });
</script>

<!-- AJAX Form Handling for Add, Edit, and Delete -->
<script>
    $(document).ready(function() {
        // Add Equipment
        $('#addEquipmentForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted', $(this).serialize());
            $.ajax({
                url: 'equipment_details.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',  // Specify that we expect JSON
                success: function(result) {
                    console.log('Response:', result);
                    if (result.status === 'success') {
                        $('#addEquipmentModal').modal('hide');
                        location.reload();
                    } else {
                        alert(result.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    alert('Error submitting the form');
                }
            });
        });

        // Edit Equipment
        $('.edit-equipment').on('click', function() {
            var id = $(this).data('id');
            var asset = $(this).data('asset');
            var desc1 = $(this).data('desc1');
            var desc2 = $(this).data('desc2');
            var spec = $(this).data('spec');
            var brand = $(this).data('brand');
            var model = $(this).data('model');
            var serial = $(this).data('serial');
            var date = $(this).data('date');
            var remarks = $(this).data('remarks');

            $('#edit_equipment_id').val(id);
            $('#edit_asset_tag').val(asset);
            $('#edit_asset_description_1').val(desc1);
            $('#edit_asset_description_2').val(desc2);
            $('#edit_specifications').val(spec);
            $('#edit_brand').val(brand);
            $('#edit_model').val(model);
            $('#edit_serial_number').val(serial);
            $('#edit_date_created').val(date);
            $('#edit_remarks').val(remarks);

            $('#editEquipmentModal').modal('show');
        });

        // Delete Equipment
        $('.delete-equipment').off('click').on('click', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (confirm('Are you sure you want to delete this equipment?')) {
                $.ajax({
                    url: 'equipment_details.php',
                    method: 'POST',
                    data: { action: 'delete', equipment_id: id },
                    dataType: 'json',
                    success: function(result) {
                        if (result.status === 'success') {
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error deleting equipment: ' + error);
                    }
                });
            }
        });

        // Update Equipment
        $('#editEquipmentForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'equipment_details.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(result) {
                    location.reload();
                }
            });
        });
    });
</script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
