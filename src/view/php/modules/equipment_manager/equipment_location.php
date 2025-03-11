<?php
// equipment_location.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Include the header
include('../../general/header.php');

// -----------------------------------------------------------------
// Optionally check for admin privileges (uncomment if needed)
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
//     exit();
// }
// -----------------------------------------------------------------

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

// Function to check user permissions
function userHasPermission($permission) {
    if (isset($_SESSION['user_permissions']) && is_array($_SESSION['user_permissions'])) {
        return in_array($permission, $_SESSION['user_permissions']);
    }
    return false;
}

// Temporarily set these to true to test UI elements
$canAddLocation = true; // We'll add proper permission checking later
$canFilterLocation = true;

// ------------------------
// DELETE EQUIPMENT LOCATION
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Get location details before deletion
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
                'remarks' => $locationData['remarks']
            ]);

            // Delete the location
            $stmt = $pdo->prepare("DELETE FROM equipment_location WHERE equipment_location_id = ?");
            $stmt->execute([$id]);

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
                'Delete',
                'Equipment location deleted',
                $oldValues,
                null,
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
    header('Content-Type: application/json');
    
    try {
        // Get form data
        $assetTag = trim($_POST['asset_tag']);
        $buildingLoc = trim($_POST['building_loc']);
        $floorNo = trim($_POST['floor_no']);
        $specificArea = trim($_POST['specific_area']);
        $personResponsible = trim($_POST['person_responsible']);
        $departmentId = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $remarks = trim($_POST['remarks']);

        // Log incoming data for debugging
        error_log(print_r($_POST, true)); // Log the incoming POST data

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

        // Check for success
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
        error_log('Error: ' . $e->getMessage()); // Log the error message
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update') {
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

    // Proceed with the update
    $updateStmt = $pdo->prepare("UPDATE equipment_location SET asset_tag = ?, building_loc = ?, floor_no = ?, specific_area = ?, person_responsible = ?, department_id = ?, remarks = ? WHERE equipment_location_id = ?");
    $updateStmt->execute([$assetTag, $buildingLoc, $floorNo, $specificArea, $personResponsible, $departmentId, $remarks, $id]);
    
    // Check for success
    if ($updateStmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Location updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No changes made or invalid ID']);
    }
}

// ------------------------
// LOAD EQUIPMENT LOCATION DATA FOR EDITING (if applicable)
// ------------------------
$editEquipmentLocation = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
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
// RETRIEVE ALL EQUIPMENT LOCATIONS
// ------------------------
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
    
    // Debug: Print the query results
    // echo "<pre>"; print_r($equipmentLocations); echo "</pre>";
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
            <!-- Add Location Button -->
            <div class="d-flex justify-content-start mb-3 gap-2">
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                    <i class="bi bi-plus-circle"></i> Add Equipment Location
                </button>
                
                <!-- Filter dropdown -->
                <select class="form-select form-select-sm" id="filterBuilding" style="width: auto;">
                    <option value="">Filter Building Location</option>
                    <?php
                    if (!empty($equipmentLocations)) {
                        $buildingLocations = array_unique(array_column($equipmentLocations, 'building_loc'));
                        foreach($buildingLocations as $building) {
                            if(!empty($building)) {
                                echo "<option value='" . htmlspecialchars($building) . "'>" . htmlspecialchars($building) . "</option>";
                            }
                        }
                    }
                    ?>
                </select>
            </div>

            <!-- Equipment Locations Table -->
            <div class="table-responsive">
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
                            <th>Date Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($equipmentLocations)): ?>
                            <?php foreach ($equipmentLocations as $index => $location): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($location['asset_tag'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($location['building_loc'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($location['floor_no'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($location['specific_area'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($location['person_responsible'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($location['department_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($location['remarks'] ?? ''); ?></td>
                                    <td><?php echo !empty($location['date_created']) ? date('Y-m-d H:i', strtotime($location['date_created'])) : ''; ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-location" 
                                                    data-id="<?php echo $location['equipment_location_id']; ?>"
                                                    data-asset="<?php echo htmlspecialchars($location['asset_tag'] ?? ''); ?>"
                                                    data-building="<?php echo htmlspecialchars($location['building_loc'] ?? ''); ?>"
                                                    data-floor="<?php echo htmlspecialchars($location['floor_no'] ?? ''); ?>"
                                                    data-area="<?php echo htmlspecialchars($location['specific_area'] ?? ''); ?>"
                                                    data-person="<?php echo htmlspecialchars($location['person_responsible'] ?? ''); ?>"
                                                    data-department="<?php echo htmlspecialchars($location['department_id'] ?? ''); ?>"
                                                    data-remarks="<?php echo htmlspecialchars($location['remarks'] ?? ''); ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editLocationModal">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <a href="?action=delete&id=<?php echo $location['equipment_location_id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this location?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">No Equipment Locations found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-control" name="department_id" required>
                            <option value="">Select Department (Optional)</option>
                            <?php
                            try {
                                $deptStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0 ORDER BY department_name");
                                $departments = $deptStmt->fetchAll();
                                foreach($departments as $department) {
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
                                $deptStmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_disabled = 0 ORDER BY department_name");
                                $departments = $deptStmt->fetchAll();
                                foreach($departments as $department) {
                                    echo "<option value='" . htmlspecialchars($department['id']) . "'>" . htmlspecialchars($department['department_name']) . "</option>";
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

$(document).ready(function() {
    $('#addLocationForm').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = submitBtn.text();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Show success message
                    alert(response.message || 'Equipment Location added successfully');
                    // Close the modal
                    $('#addLocationModal').modal('hide');
                    // Reload the page to show new data
                    location.reload();
                } else {
                    // Show error message
                    alert(response.message || 'Error adding Equipment Location');
                    // Reset button state
                    submitBtn.prop('disabled', false).text(originalBtnText);
                }
            },
            error: function(xhr, status, error) {
                // More detailed error handling
                let errorMessage = 'Error submitting form. ';
                try {
                    const response = JSON.parse(xhr.responseText);
                    errorMessage += response.message || error;
                } catch(e) {
                    errorMessage += 'Please try again.';
                }
                alert(errorMessage);
                // Reset button state
                submitBtn.prop('disabled', false).text(originalBtnText);
                console.error('Form submission error:', xhr.responseText);
            }
        });
    });

    // Edit button click handler
    $('.edit-location').on('click', function() {
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
                console.error('Error details:', xhr.responseText);
            }
        });
    });
});
</script>
</body>

</html>
