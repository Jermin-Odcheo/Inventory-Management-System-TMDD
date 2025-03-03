<?php
// equipment_location.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Include the header
include('../../general/header.php');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address; adjust as needed if you use a proxy.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

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

// ------------------------
// SOFT DELETE EQUIPMENT LOCATION (set is_disabled to 1)
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Get location details before soft deletion
        $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $stmt->execute([$id]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($locationData) {
            // Prepare audit log data
            $oldValues = json_encode([
                'asset_tag' => $locationData['asset_tag'],
                'building_loc' => $locationData['building_loc'],
                'floor_no' => $locationData['floor_no'],
                'specific_area' => $locationData['specific_area'],
                'person_responsible' => $locationData['person_responsible'],
                'department_id' => $locationData['department_id'],
                'remarks' => $locationData['remarks'],
                'is_disabled' => $locationData['is_disabled']
            ]);

            // Soft delete the location by setting is_disabled to 1
            $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 1 WHERE equipment_location_id = ?");
            $stmt->execute([$id]);

            // Insert audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $newValues = json_encode([
                'asset_tag' => $locationData['asset_tag'],
                'building_loc' => $locationData['building_loc'],
                'floor_no' => $locationData['floor_no'],
                'specific_area' => $locationData['specific_area'],
                'person_responsible' => $locationData['person_responsible'],
                'department_id' => $locationData['department_id'],
                'remarks' => $locationData['remarks'],
                'is_disabled' => 1
            ]);

            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Equipment Location',
                'Delete',
                'Equipment location soft deleted',
                $oldValues,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();
            $_SESSION['success'] = "Equipment Location deleted successfully.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['errors'] = ["Error deleting Equipment Location: " . $e->getMessage()];
    }
    header("Location: equipment_location.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form input
    $asset_tag          = trim($_POST['asset_tag'] ?? '');
    $building_loc       = trim($_POST['building_loc'] ?? '');
    $floor_no           = trim($_POST['floor_no'] ?? '');
    $specific_area      = trim($_POST['specific_area'] ?? '');
    $person_responsible = trim($_POST['person_responsible'] ?? '');
    $department_id      = trim($_POST['department_id'] ?? '');
    $remarks            = trim($_POST['remarks'] ?? '');

    $response = array('status' => '', 'message' => '');

    // Validate required fields
    if (empty($asset_tag) || empty($building_loc) || empty($floor_no) || empty($specific_area) || empty($person_responsible)) {
        $response['status'] = 'error';
        $response['message'] = 'Please fill in all required fields.';
        echo json_encode($response);
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            $pdo->beginTransaction();

            // Insert equipment location with is_disabled = 0
            $stmt = $pdo->prepare("INSERT INTO equipment_location (
                asset_tag, 
                building_loc, 
                floor_no, 
                specific_area, 
                person_responsible,
                department_id,
                remarks,
                date_created,
                is_disabled
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)");
            $stmt->execute([$asset_tag, $building_loc, $floor_no, $specific_area, $person_responsible, $department_id, $remarks]);

            $newLocationId = $pdo->lastInsertId();

            // Prepare audit log data
            $newValues = json_encode([
                'asset_tag' => $asset_tag,
                'building_loc' => $building_loc,
                'floor_no' => $floor_no,
                'specific_area' => $specific_area,
                'person_responsible' => $person_responsible,
                'department_id' => $department_id,
                'remarks' => $remarks,
                'is_disabled' => 0
            ]);

            // Insert audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $auditStmt->execute([
                $_SESSION['user_id'],
                $newLocationId,
                'Equipment Location',
                'Add',
                'New equipment location added',
                null,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();

            $response['status'] = 'success';
            $response['message'] = 'Equipment Location has been added successfully.';
            $_SESSION['success'] = "Equipment Location has been added successfully.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $response['status'] = 'error';
            $response['message'] = 'Error adding Equipment Location: ' . $e->getMessage();
            $_SESSION['errors'] = ["Error adding Equipment Location: " . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $pdo->beginTransaction();

            // Get old location details for audit log
            $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
            $stmt->execute([$id]);
            $oldLocation = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update equipment location
            $stmt = $pdo->prepare("UPDATE equipment_location SET 
                asset_tag = ?, 
                building_loc = ?, 
                floor_no = ?, 
                specific_area = ?, 
                person_responsible = ?,
                department_id = ?,
                remarks = ?
                WHERE equipment_location_id = ?");
            $stmt->execute([
                $_POST['asset_tag'],
                $_POST['building_loc'],
                $_POST['floor_no'],
                $_POST['specific_area'],
                $_POST['person_responsible'],
                $_POST['department_id'],
                $_POST['remarks'],
                $id
            ]);

            // Prepare audit log data
            $oldValues = json_encode([
                'asset_tag' => $oldLocation['asset_tag'],
                'building_loc' => $oldLocation['building_loc'],
                'floor_no' => $oldLocation['floor_no'],
                'specific_area' => $oldLocation['specific_area'],
                'person_responsible' => $oldLocation['person_responsible'],
                'department_id' => $oldLocation['department_id'],
                'remarks' => $oldLocation['remarks'],
                'date_created' => $oldLocation['date_created'],
                'is_disabled' => $oldLocation['is_disabled']
            ]);

            $newValues = json_encode([
                'asset_tag' => $_POST['asset_tag'],
                'building_loc' => $_POST['building_loc'],
                'floor_no' => $_POST['floor_no'],
                'specific_area' => $_POST['specific_area'],
                'person_responsible' => $_POST['person_responsible'],
                'department_id' => $_POST['department_id'],
                'remarks' => $_POST['remarks'],
                'date_created' => $oldLocation['date_created'],
                'is_disabled' => $oldLocation['is_disabled']
            ]);

            // Insert audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Equipment Location',
                'Modified',
                'Equipment location modified',
                $oldValues,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();

            // Ensure we're sending a proper JSON response
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Equipment Location has been updated successfully.'
            ]);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Ensure we're sending a proper JSON response for errors too
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Error updating Equipment Location: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}

// ------------------------
// LOAD EQUIPMENT LOCATION DATA FOR EDITING (if applicable)
// ------------------------
$editEquipmentLocation = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ? AND is_disabled = 0");
        $stmt->execute([$id]);
        $editEquipmentLocation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editEquipmentLocation) {
            $_SESSION['errors'] = ["Equipment Location not found for editing."];
            header("Location: equipment_location.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Equipment Location for editing: " . $e->getMessage();
    }
}

// ------------------------
// RETRIEVE ALL ACTIVE EQUIPMENT LOCATIONS (is_disabled = 0)
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM equipment_location WHERE is_disabled = 0 ORDER BY date_created DESC");
    $equipmentLocations = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Locations: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Equipment Location Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            padding-top: 70px;
        }
        .container-fluid {
            margin-left: 300px;
            padding: 20px;
            width: calc(100% - 300px);
        }
        h2.mb-4 {
            margin-top: 5px;
            margin-bottom: 15px !important;
        }
        .card.shadow-sm {
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .container-fluid {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
    <!-- Add these scripts here -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
<?php include '../../general/sidebar.php'; ?>

<div class="container-fluid">
    <h2 class="mb-4">Equipment Location</h2>

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

    <!-- List of Equipment Locations Card -->
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
            <span><i class="bi bi-list-ul"></i> List of Equipment Locations</span>
            <div class="input-group w-auto">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Search..." id="eqSearch">
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($equipmentLocations)): ?>
                <div class="table-responsive">
                    <!-- Add Location Button and Filter -->
                    <div class="d-flex justify-content-start mb-3 gap-2">
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                            <i class="bi bi-plus-circle"></i> Add Equipment Location
                        </button>
                        <select class="form-select form-select-sm" id="filterBuilding" style="width: auto;">
                            <option value="">Filter Building Location</option>
                            <?php
                            $buildingLocations = array_unique(array_column($equipmentLocations, 'building_loc'));
                            foreach($buildingLocations as $building) {
                                if(!empty($building)) {
                                    echo "<option value='" . htmlspecialchars($building) . "'>" . htmlspecialchars($building) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <table class="table table-striped table-hover align-middle" id="table">
                        <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Asset Tag</th>
                            <th>Building Location</th>
                            <th>Floor Number</th>
                            <th>Specific Area</th>
                            <th>Person Responsible</th>
                            <th>Department</th>
                            <th>Remarks</th>
                            <th style="width: 12%">Date Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($equipmentLocations as $location): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($location['equipment_location_id']); ?></td>
                                <td><?php echo htmlspecialchars($location['asset_tag']); ?></td>
                                <td><?php echo htmlspecialchars($location['building_loc']); ?></td>
                                <td><?php echo htmlspecialchars($location['floor_no']); ?></td>
                                <td><?php echo htmlspecialchars($location['specific_area']); ?></td>
                                <td><?php echo htmlspecialchars($location['person_responsible']); ?></td>
                                <td>
                                    <?php
                                    $deptName = 'N/A';
                                    if(!empty($location['department_id'])) {
                                        try {
                                            $deptStmt = $pdo->prepare("SELECT dept_name FROM departments WHERE id = ?");
                                            $deptStmt->execute([$location['department_id']]);
                                            $dept = $deptStmt->fetch(PDO::FETCH_ASSOC);
                                            if($dept) {
                                                $deptName = $dept['dept_name'];
                                            }
                                        } catch (PDOException $e) {
                                            // Handle error
                                        }
                                    }
                                    echo htmlspecialchars($deptName);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($location['remarks']); ?></td>
                                <td><?php echo !empty($location['date_created']) ? date('Y-m-d H:i', strtotime($location['date_created'])) : ''; ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a class="btn btn-sm btn-outline-primary edit-location"
                                           data-id="<?php echo $location['equipment_location_id']; ?>"
                                           data-asset="<?php echo htmlspecialchars($location['asset_tag']); ?>"
                                           data-building="<?php echo htmlspecialchars($location['building_loc']); ?>"
                                           data-floor="<?php echo htmlspecialchars($location['floor_no']); ?>"
                                           data-area="<?php echo htmlspecialchars($location['specific_area']); ?>"
                                           data-person="<?php echo htmlspecialchars($location['person_responsible']); ?>"
                                           data-department="<?php echo htmlspecialchars($location['department_id']); ?>"
                                           data-remarks="<?php echo htmlspecialchars($location['remarks']); ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a class="btn btn-sm btn-outline-danger" href="?action=delete&id=<?php echo htmlspecialchars($location['equipment_location_id']); ?>" onclick="return confirm('Are you sure you want to delete this Equipment Location?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <!-- Pagination Controls -->
                    <div class="container-fluid">
                        <div class="row align-items-center g-3">
                            <!-- Pagination Info -->
                            <div class="col-12 col-sm-auto">
                                <div class="text-muted">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span
                                            id="totalRows">0</span> entries
                                </div>
                            </div>

                            <!-- Pagination Controls -->
                            <div class="col-12 col-sm-auto ms-sm-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                        <i class="bi bi-chevron-left"></i>
                                        Previous
                                    </button>

                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                        <option value="10">10</option>
                                        <option value="20" selected>20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>

                                    <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                        Next
                                        <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="mb-0">No Equipment Locations found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Equipment Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addLocationForm">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="asset_tag" class="form-label">Asset Tag <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="asset_tag" required>
                    </div>

                    <div class="mb-3">
                        <label for="building_loc" class="form-label">Building Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="building_loc" required>
                    </div>

                    <div class="mb-3">
                        <label for="floor_no" class="form-label">Floor Number <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" name="floor_no" required>
                    </div>

                    <div class="mb-3">
                        <label for="specific_area" class="form-label">Specific Area <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="specific_area" required>
                    </div>

                    <div class="mb-3">
                        <label for="person_responsible" class="form-label">Person Responsible <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="person_responsible" required>
                    </div>

                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                        <select class="form-control" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php
                            try {
                                $deptStmt = $pdo->query("SELECT id, dept_name FROM departments WHERE is_disabled = 0 ORDER BY dept_name");
                                $departments = $deptStmt->fetchAll();
                                foreach($departments as $department) {
                                    echo "<option value='" . htmlspecialchars($department['id']) . "'>" . htmlspecialchars($department['dept_name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                // Handle error
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" rows="3"></textarea>
                    </div>

                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-primary">Add Equipment Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Location Modal -->
<div class="modal fade" id="editLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editLocationForm" method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_location_id">

                    <div class="mb-3">
                        <label for="edit_asset_tag" class="form-label">
                            <i class="bi bi-tag"></i> Asset Tag <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="asset_tag" id="edit_asset_tag" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_building_loc" class="form-label">
                            <i class="bi bi-building"></i> Building Location <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_building_loc" name="building_loc" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_floor_no" class="form-label">
                            <i class="bi bi-layers"></i> Floor Number <span class="text-danger">*</span>
                        </label>
                        <input type="number" min="1" class="form-control" id="edit_floor_no" name="floor_no" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_specific_area" class="form-label">
                            <i class="bi bi-pin-map"></i> Specific Area <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_specific_area" name="specific_area" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_person_responsible" class="form-label">
                            <i class="bi bi-person"></i> Person Responsible <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_person_responsible" name="person_responsible" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label">
                            <i class="bi bi-building"></i> Department <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" id="edit_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php
                            try {
                                $deptStmt = $pdo->query("SELECT id, dept_name FROM departments WHERE is_disabled = 0 ORDER BY dept_name");
                                $departments = $deptStmt->fetchAll();
                                foreach($departments as $department) {
                                    echo "<option value='" . htmlspecialchars($department['id']) . "'>" . htmlspecialchars($department['dept_name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                // Handle error
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_remarks" class="form-label">
                            <i class="bi bi-chat-left-text"></i> Remarks
                        </label>
                        <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Location</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
<!-- JavaScript for Real-Time Table Filtering -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Search and filter functionality
        const searchInput = document.getElementById('eqSearch');
        const filterBuilding = document.getElementById('filterBuilding');

        if (searchInput) {
            searchInput.addEventListener('keyup', filterTable);
        }
        if (filterBuilding) {
            filterBuilding.addEventListener('change', filterTable);
        }

        function filterTable() {
            const searchValue = searchInput.value.toLowerCase();
            const filterValue = filterBuilding.value.toLowerCase();
            const rows = document.querySelectorAll('#table tbody tr');

            rows.forEach(function(row) {
                const rowText = row.textContent.toLowerCase();
                const buildingCell = row.querySelector('td:nth-child(3)').textContent.toLowerCase();

                const searchMatch = rowText.indexOf(searchValue) > -1;
                const buildingMatch = !filterValue || buildingCell === filterValue;

                row.style.display = (searchMatch && buildingMatch) ? '' : 'none';
            });
        }
    });

document.addEventListener('DOMContentLoaded', function() {
    // Form submissions
    $('#addLocationForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'equipment_location.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(result) {
                if (result.status === 'success') {
                    // Hide modal and redirect
                    $('#addLocationModal').modal('hide');
                    window.location.href = 'equipment_location.php';
                } else {
                    alert(result.message || 'An error occurred');
                }
            },
            error: function(xhr, status, error) {
                alert('Error submitting the form: ' + error);
            }
        });
    });

    // Edit button click handler
    $('.edit-location').click(function() {
        const id = $(this).data('id');
        const assetTag = $(this).data('asset');
        const buildingLocation = $(this).data('building');
        const floorNumber = $(this).data('floor');
        const specificArea = $(this).data('area');
        const personResponsible = $(this).data('person');
        const remarks = $(this).data('remarks');

        $('#edit_location_id').val(id);
        $('#edit_asset_tag').val(assetTag);
        $('#edit_building_location').val(buildingLocation);
        $('#edit_floor_number').val(floorNumber);
        $('#edit_specific_area').val(specificArea);
        $('#edit_person_responsible').val(personResponsible);
        $('#edit_remarks').val(remarks);

        $('#editLocationModal').modal('show');
    });

    // Edit form submission
    $('#editLocationForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'equipment_location.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(result) {
                if (result.status === 'success') {
                    $('#editLocationModal').modal('hide');
                    window.location.href = 'equipment_location.php';
                } else {
                    alert(result.message || 'An error occurred');
                }
            },
            error: function(xhr, status, error) {
                alert('Error updating location: ' + error);
            }
        });
    });
});
</script>
</body>

</html>
