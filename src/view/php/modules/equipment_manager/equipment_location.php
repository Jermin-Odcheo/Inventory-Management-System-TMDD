<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// ------------------------
// AJAX Handling Section
// ------------------------
// Check if the request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        // UPDATE EQUIPMENT LOCATION
        $id = $_POST['id'];
        $assetTag = $_POST['asset_tag'];
        $buildingLoc = $_POST['building_loc'];
        $floorNo = $_POST['floor_no'];
        $specificArea = $_POST['specific_area'];
        $personResponsible = $_POST['person_responsible'];
        $departmentId = $_POST['department_id'];
        $remarks = $_POST['remarks'];

        // Validate department ID
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        if ($stmt->fetchColumn() == 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid department ID']);
            exit;
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Get old location details for audit log
        $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $stmt->execute([$id]);
        $oldLocation = $stmt->fetch(PDO::FETCH_ASSOC);

        // Proceed with the update
        $updateStmt = $pdo->prepare("UPDATE equipment_location 
            SET asset_tag = ?, building_loc = ?, floor_no = ?, specific_area = ?, person_responsible = ?, department_id = ?, remarks = ?
            WHERE equipment_location_id = ?");
        $updateStmt->execute([$assetTag, $buildingLoc, $floorNo, $specificArea, $personResponsible, $departmentId, $remarks, $id]);

        if ($updateStmt->rowCount() > 0) {
            // Prepare audit log data
            $oldValue = json_encode($oldLocation);
            $newValues = json_encode([
                'asset_tag' => $assetTag,
                'building_loc' => $buildingLoc,
                'floor_no' => $floorNo,
                'specific_area' => $specificArea,
                'person_responsible' => $personResponsible,
                'department_id' => $departmentId,
                'remarks' => $remarks
            ]);

            // Insert into audit_log
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
                $oldValue,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Location updated successfully']);
        } else {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'No changes made or invalid ID']);
        }
        exit;
    } elseif ($action === 'add') {
        // ADD EQUIPMENT LOCATION
        try {
            // Get form data
            $assetTag = trim($_POST['asset_tag']);
            $buildingLoc = trim($_POST['building_loc']);
            $floorNo = trim($_POST['floor_no']);
            $specificArea = trim($_POST['specific_area']);
            $personResponsible = trim($_POST['person_responsible']);
            $departmentId = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
            $remarks = trim($_POST['remarks']);

            // Debug: Log incoming POST data
            error_log(print_r($_POST, true));

            // Start transaction
            $pdo->beginTransaction();

            // Insert into equipment_location table
            $sql = "INSERT INTO equipment_location (
                asset_tag, 
                building_loc, 
                floor_no, 
                specific_area, 
                person_responsible, 
                department_id, 
                remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $assetTag,
                $buildingLoc,
                $floorNo,
                $specificArea,
                $personResponsible,
                $departmentId,
                $remarks
            ]);

            if ($stmt->rowCount() > 0) {
                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Equipment Location added successfully']);
            } else {
                throw new Exception('No rows affected, check your input data.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Handle AJAX delete requests via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $isAjax && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Get location details before deletion
        $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $stmt->execute([$id]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($locationData) {
            $oldValues = json_encode([
                'asset_tag' => $locationData['asset_tag'],
                'building_loc' => $locationData['building_loc'],
                'floor_no' => $locationData['floor_no'],
                'specific_area' => $locationData['specific_area'],
                'person_responsible' => $locationData['person_responsible'],
                'department_id' => $locationData['department_id'],
                'remarks' => $locationData['remarks']
            ]);

            $stmt = $pdo->prepare("DELETE FROM equipment_location WHERE equipment_location_id = ?");
            $stmt->execute([$id]);

            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Equipment Location',
                'Delete',
                'Equipment location deleted',
                $oldValues,
                null,
                'Successful'
            ]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Equipment Location deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Location not found']);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Error deleting Equipment Location: ' . $e->getMessage()]);
    }
    exit;
}


// (Now your normal non-AJAX HTML output begins)

$errors = [];
$success = "";
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}


// ------------------------
// LIVE SEARCH DEPARTMENTS
// ------------------------
$q = isset($_GET["q"]) ? $conn->real_escape_string($_GET["q"]) : '';
if (strlen($q) > 0) {
    $sql = "SELECT asset_tag, building_loc, floor_no, specific_area, person_responsible, remarks 
                FROM equipment_location 
                WHERE asset_tag LIKE '%$q%' 
                OR building_loc LIKE '%$q%' 
                OR specific_area LIKE '%$q%' 
                OR person_responsible LIKE '%$q%' 
                OR remarks LIKE '%$q%'
                LIMIT 10";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "<div class='result-item'>"
                . "<strong>Asset Tag:</strong> " . htmlspecialchars($row['asset_tag']) . " - "
                . "<strong>Building:</strong> " . htmlspecialchars($row['building_loc']) . " - "
                . "<strong>Area:</strong> " . htmlspecialchars($row['specific_area']) . " - "
                . "<strong>Person:</strong> " . htmlspecialchars($row['person_responsible']) . " - "
                . "<strong>Remarks:</strong> " . htmlspecialchars($row['remarks']) .
                "</div>";
        }
    } else {
        echo "<div class='result-item'>No results found</div>";
    }
    exit;
}

// Retrieve all equipment locations...
try {
    $stmt = $pdo->query("
        SELECT el.*, 
               d.department_name 
        FROM equipment_location el 
        LEFT JOIN departments d ON el.department_id = d.id 
        WHERE el.is_disabled = 0 
        ORDER BY el.date_created DESC
    ");
    $equipmentLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Locations: " . $e->getMessage();
}

function safeHtml($value)
{
    return htmlspecialchars($value ?? 'N/A');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Location Management</title>

    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
<?php
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';
?>

<div class="main-container">
    <header class="main-header">
        <h1> Equipment Location Management</h1>
    </header>

    <section class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h2><i class="bi bi-list-task"></i> List of Equipment Locations</h2>
        </div>

        <div class="card-body">
            <div class="container-fluid px-0">
                <div class="row align-items-center g-2">
                    <div class="col-auto">
                        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                            <i class="bi bi-plus-lg"></i> Create Location
                        </button>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="filterBuilding">
                            <option value="">Filter by Building</option>
                            <?php
                            if (!empty($equipmentLocations)) {
                                $buildings = array_unique(array_column($equipmentLocations, 'building_loc'));
                                foreach ($buildings as $building) {
                                    echo "<option>" . htmlspecialchars($building) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" id="eqSearch" class="form-control" placeholder="Search Equipment...">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive" id="table">
                <table class="table" id="elTable">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Asset Tag</th>
                        <th>Building</th>
                        <th>Floor</th>
                        <th>Area</th>
                        <th>Person Responsible</th>
                        <th>Department</th>
                        <th>Remarks</th>
                        <th>Date Created</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($equipmentLocations)): ?>
                        <?php foreach ($equipmentLocations as $index => $loc): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($loc['asset_tag']) ?></td>
                                <td><?= htmlspecialchars($loc['building_loc']) ?></td>
                                <td><?= htmlspecialchars($loc['floor_no']) ?></td>
                                <td><?= htmlspecialchars($loc['specific_area']) ?></td>
                                <td><?= htmlspecialchars($loc['person_responsible']) ?></td>
                                <td><?= htmlspecialchars($loc['department_name']) ?></td>
                                <td><?= htmlspecialchars($loc['remarks']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($loc['date_created'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal"
                                            data-bs-target="#editLocationModal"
                                            data-id="<?= $loc['equipment_location_id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <button class="btn btn-sm btn-outline-danger delete-location"
                                            data-id="<?= $loc['equipment_location_id'] ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">No Equipment Locations Found.</td>
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
        </div>
    </section>
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
                        <label for="building_loc" class="form-label">Building Location <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="building_loc" required>
                    </div>
                    <div class="mb-3">
                        <label for="floor_no" class="form-label">Floor Number <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" name="floor_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="specific_area" class="form-label">Specific Area <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="specific_area" required>
                    </div>
                    <div class="mb-3">
                        <label for="person_responsible" class="form-label">Person Responsible <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="person_responsible" required>
                    </div>
                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department <span
                                    class="text-danger">*</span></label>
                        <select class="form-control" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php
                            try {
                                $deptStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0 ORDER BY department_name");
                                $departments = $deptStmt->fetchAll();
                                foreach ($departments as $department) {
                                    echo "<option value='" . htmlspecialchars($department['id']) . "'>" . htmlspecialchars($department['department_name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading departments</option>";
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
                        <label for="edit_asset_tag" class="form-label"><i class="bi bi-tag"></i> Asset Tag <span
                                    class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="asset_tag" id="edit_asset_tag" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_building_loc" class="form-label"><i class="bi bi-building"></i> Building
                            Location <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_building_loc" name="building_loc" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_floor_no" class="form-label"><i class="bi bi-layers"></i> Floor Number <span
                                    class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" id="edit_floor_no" name="floor_no" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_specific_area" class="form-label"><i class="bi bi-pin-map"></i> Specific Area
                            <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_specific_area" name="specific_area" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_person_responsible" class="form-label"><i class="bi bi-person"></i> Person
                            Responsible <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_person_responsible" name="person_responsible"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label"><i class="bi bi-building"></i> Department
                            <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php
                            try {
                                $deptStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0 ORDER BY department_name");
                                $departments = $deptStmt->fetchAll();
                                foreach ($departments as $department) {
                                    echo "<option value='" . htmlspecialchars($department['id']) . "'>" . htmlspecialchars($department['department_name']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                // Handle error if needed
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_remarks" class="form-label"><i class="bi bi-chat-left-text"></i>
                            Remarks</label>
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

<!-- Delete Equipment Location Modal -->
<div class="modal fade" id="deleteEDModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this Equipment Location?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('eqSearch');

        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                const searchValue = searchInput.value.trim();

                if (searchValue.length > 0) {
                    fetch(`search_equipment_location.php?q=${encodeURIComponent(searchValue)}`)
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById('liveSearchResults').innerHTML = data;
                        })
                        .catch(error => console.error('Error:', error));
                } else {
                    document.getElementById('liveSearchResults').innerHTML = "";
                }
            });
        }
    });

    $(document).ready(function () {
        // Real-time search & filter
        $('#eqSearch, #filterBuilding').on('input change', function () {
            filterTable();
        });

        function filterTable() {
            const searchValue = $('#eqSearch').val().toLowerCase();
            const filterValue = $('#filterBuilding').val().toLowerCase();
            const rows = $('#table tbody tr');

            rows.each(function () {
                const rowText = $(this).text().toLowerCase();
                const buildingCell = $(this).find('td:nth-child(3)').text().toLowerCase();
                $(this).toggle(rowText.includes(searchValue) && (!filterValue || buildingCell === filterValue));
            });
        }

        // Delegate event for editing location
        $(document).on('click', '.edit-location', function () {
            var id = $(this).data('id');
            var assetTag = $(this).data('asset');
            var buildingLocation = $(this).data('building');
            var floorNumber = $(this).data('floor');
            var specificArea = $(this).data('area');
            var personResponsible = $(this).data('person');
            var departmentId = $(this).data('department');
            var remarks = $(this).data('remarks');

            $('#edit_location_id').val(id);
            $('#edit_asset_tag').val(assetTag);
            $('#edit_building_loc').val(buildingLocation);
            $('#edit_floor_no').val(floorNumber);
            $('#edit_specific_area').val(specificArea);
            $('#edit_person_responsible').val(personResponsible);
            $('#edit_department_id').val(departmentId);
            $('#edit_remarks').val(remarks);

            $('#editLocationModal').modal('show');
        });

        // Global variable for deletion
        var deleteId = null;

        // Delegate event for delete button to show modal
        $(document).on('click', '.delete-location', function (e) {
            e.preventDefault();
            deleteId = $(this).data('id');
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteEDModal'));
            deleteModal.show();
        });

        // When confirm delete button is clicked, perform AJAX delete
        $('#confirmDeleteBtn').on('click', function () {
            if (deleteId) {
                $.ajax({
                    url: window.location.href,
                    method: 'GET',
                    data: {
                        action: 'delete',
                        id: deleteId
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            $('#elTable').load(location.href + ' #elTable', function () {
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                        var deleteModalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteEDModal'));
                        deleteModalInstance.hide();
                    },
                    error: function (xhr, status, error) {
                        showToast('Error deleting location: ' + error, 'error');
                    }
                });
            }
        });

        // AJAX submission for Add Location form using toast notifications
        $('#addLocationForm').on('submit', function (e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.text();
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                success: function (result) {
                    if (result.status === 'success') {
                        $('#addLocationModal').modal('hide');
                        $('#elTable').load(location.href + ' #elTable', function () {
                            showToast(result.message, 'success');
                        });
                    } else {
                        showToast(result.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error updating location: ' + error, 'error');
                }
            });
        });

        // AJAX submission for Edit Location form using toast notifications
        $('#editLocationForm').on('submit', function (e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.text();
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');

            $.ajax({
                url: 'equipment_location.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                success: function (result) {
                    if (result.status === 'success') {
                        $('#editLocationModal').modal('hide');
                        $('#elTable').load(location.href + ' #elTable', function () {
                            showToast(result.message, 'success');
                        });
                    } else {
                        showToast(result.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showToast('Error updating location: ' + error, 'error');
                }
            });
        });
    });
</script>

</body>
</html>