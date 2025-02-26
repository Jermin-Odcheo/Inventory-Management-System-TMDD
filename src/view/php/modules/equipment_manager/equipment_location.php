<?php
// equipment_location.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

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

// ------------------------
// DELETE EQUIPMENT LOCATION
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Get location details before deletion
        $stmt = $pdo->prepare("SELECT * FROM equipmentlocation WHERE EquipmentLocationID = ?");
        $stmt->execute([$id]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($locationData) {
            // Prepare audit log data
            $oldValues = json_encode([
                'AssetTag' => $locationData['AssetTag'],
                'BuildingLocation' => $locationData['BuildingLocation'],
                'FloorNumber' => $locationData['FloorNumber'],
                'SpecificArea' => $locationData['SpecificArea'],
                'PersonResponsible' => $locationData['PersonResponsible'],
                'Remarks' => $locationData['Remarks']
            ]);

            // Delete the location
            $stmt = $pdo->prepare("DELETE FROM equipmentlocation WHERE EquipmentLocationID = ?");
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
    // Retrieve and sanitize form input
    $AssetTag          = trim($_POST['AssetTag'] ?? '');
    $BuildingLocation  = trim($_POST['BuildingLocation'] ?? '');
    $FloorNumber       = trim($_POST['FloorNumber'] ?? '');
    $SpecificArea      = trim($_POST['SpecificArea'] ?? '');
    $PersonResponsible = trim($_POST['PersonResponsible'] ?? '');
    $Remarks           = trim($_POST['Remarks'] ?? '');

    $response = array('status' => '', 'message' => '');

    // Validate required fields
    if (empty($AssetTag) || empty($BuildingLocation) || empty($FloorNumber) || empty($SpecificArea) || empty($PersonResponsible)) {
        $response['status'] = 'error';
        $response['message'] = 'Please fill in all required fields.';
        echo json_encode($response);
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            $pdo->beginTransaction();

            // Insert equipment location
            $stmt = $pdo->prepare("INSERT INTO equipmentlocation (
                AssetTag, 
                BuildingLocation, 
                FloorNumber, 
                SpecificArea, 
                PersonResponsible, 
                Remarks,
                CreatedDate
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$AssetTag, $BuildingLocation, $FloorNumber, $SpecificArea, $PersonResponsible, $Remarks]);
            
            $newLocationId = $pdo->lastInsertId();

            // Prepare audit log data
            $newValues = json_encode([
                'AssetTag' => $AssetTag,
                'BuildingLocation' => $BuildingLocation,
                'FloorNumber' => $FloorNumber,
                'SpecificArea' => $SpecificArea,
                'PersonResponsible' => $PersonResponsible,
                'Remarks' => $Remarks
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
            $stmt = $pdo->prepare("SELECT * FROM equipmentlocation WHERE EquipmentLocationID = ?");
            $stmt->execute([$id]);
            $oldLocation = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update equipment location
            $stmt = $pdo->prepare("UPDATE equipmentlocation SET 
                AssetTag = ?, 
                BuildingLocation = ?, 
                FloorNumber = ?, 
                SpecificArea = ?, 
                PersonResponsible = ?, 
                Remarks = ?
                WHERE EquipmentLocationID = ?");
            $stmt->execute([
                $_POST['AssetTag'],
                $_POST['BuildingLocation'],
                $_POST['FloorNumber'],
                $_POST['SpecificArea'],
                $_POST['PersonResponsible'],
                $_POST['Remarks'],
                $id
            ]);

            // Prepare audit log data
            $oldValues = json_encode([
                'AssetTag' => $oldLocation['AssetTag'],
                'BuildingLocation' => $oldLocation['BuildingLocation'],
                'FloorNumber' => $oldLocation['FloorNumber'],
                'SpecificArea' => $oldLocation['SpecificArea'],
                'PersonResponsible' => $oldLocation['PersonResponsible'],
                'Remarks' => $oldLocation['Remarks'],
                'CreatedDate' => $oldLocation['CreatedDate'],
                'ModifiedDate' => $oldLocation['ModifiedDate']
            ]);

            $newValues = json_encode([
                'AssetTag' => $_POST['AssetTag'],
                'BuildingLocation' => $_POST['BuildingLocation'],
                'FloorNumber' => $_POST['FloorNumber'],
                'SpecificArea' => $_POST['SpecificArea'],
                'PersonResponsible' => $_POST['PersonResponsible'],
                'Remarks' => $_POST['Remarks'],
                'CreatedDate' => $oldLocation['CreatedDate'],
                'ModifiedDate' => date('Y-m-d H:i:s')
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
        $stmt = $pdo->prepare("SELECT * FROM equipmentlocation WHERE EquipmentLocationID = ?");
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
    $stmt = $pdo->query("SELECT * FROM equipmentlocation ORDER BY CreatedDate DESC");
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
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .main-content {
            margin-left: 300px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    <!-- Add these scripts here -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
<?php include '../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <main class="col-md-12 px-md-4 py-4">
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
                                        $buildingLocations = array_unique(array_column($equipmentLocations, 'BuildingLocation'));
                                        foreach($buildingLocations as $building) {
                                            if(!empty($building)) {
                                                echo "<option value='" . htmlspecialchars($building) . "'>" . htmlspecialchars($building) . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <table class="table table-striped table-hover align-middle" id="eqTable">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Asset Tag</th>
                                        <th>Building Location</th>
                                        <th>Floor Number</th>
                                        <th>Specific Area</th>
                                        <th>Person Responsible</th>
                                        <th>Remarks</th>
                                        <th style="width: 12%">Created Date</th>
                                        <th style="width: 12%">Modified Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($equipmentLocations as $location): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($location['EquipmentLocationID']); ?></td>
                                            <td><?php echo htmlspecialchars($location['AssetTag']); ?></td>
                                            <td><?php echo htmlspecialchars($location['BuildingLocation']); ?></td>
                                            <td><?php echo htmlspecialchars($location['FloorNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($location['SpecificArea']); ?></td>
                                            <td><?php echo htmlspecialchars($location['PersonResponsible']); ?></td>
                                            <td><?php echo htmlspecialchars($location['Remarks']); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($location['CreatedDate'])); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($location['ModifiedDate'])); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a class="btn btn-sm btn-outline-primary edit-location" 
                                                       data-id="<?php echo $location['EquipmentLocationID']; ?>"
                                                       data-asset="<?php echo htmlspecialchars($location['AssetTag']); ?>"
                                                       data-building="<?php echo htmlspecialchars($location['BuildingLocation']); ?>"
                                                       data-floor="<?php echo htmlspecialchars($location['FloorNumber']); ?>"
                                                       data-area="<?php echo htmlspecialchars($location['SpecificArea']); ?>"
                                                       data-person="<?php echo htmlspecialchars($location['PersonResponsible']); ?>"
                                                       data-remarks="<?php echo htmlspecialchars($location['Remarks']); ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                    <a class="btn btn-sm btn-outline-danger" href="?action=delete&id=<?php echo htmlspecialchars($location['EquipmentLocationID']); ?>" onclick="return confirm('Are you sure you want to delete this Equipment Location?');">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="mb-0">No Equipment Locations found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- Add Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addLocationForm" method="post">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="AssetTag" class="form-label">
                            <i class="bi bi-tag"></i> Asset Tag <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="AssetTag" id="AssetTag" required>
                    </div>

                    <div class="mb-3">
                        <label for="BuildingLocation" class="form-label">
                            <i class="bi bi-building"></i> Building Location <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="BuildingLocation" name="BuildingLocation" required>
                    </div>

                    <div class="mb-3">
                        <label for="FloorNumber" class="form-label">
                            <i class="bi bi-layers"></i> Floor Number <span class="text-danger">*</span>
                        </label>
                        <input type="number" min="1" class="form-control" id="FloorNumber" name="FloorNumber" required>
                    </div>

                    <div class="mb-3">
                        <label for="SpecificArea" class="form-label">
                            <i class="bi bi-pin-map"></i> Specific Area <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="SpecificArea" name="SpecificArea" required>
                    </div>

                    <div class="mb-3">
                        <label for="PersonResponsible" class="form-label">
                            <i class="bi bi-person"></i> Person Responsible <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="PersonResponsible" name="PersonResponsible" required>
                    </div>

                    <div class="mb-3">
                        <label for="Remarks" class="form-label">
                            <i class="bi bi-chat-left-text"></i> Remarks
                        </label>
                        <textarea class="form-control" id="Remarks" name="Remarks" rows="3"></textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Location</button>
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
                        <input type="text" class="form-control" name="AssetTag" id="edit_asset_tag" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_building_location" class="form-label">
                            <i class="bi bi-building"></i> Building Location <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_building_location" name="BuildingLocation" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_floor_number" class="form-label">
                            <i class="bi bi-layers"></i> Floor Number <span class="text-danger">*</span>
                        </label>
                        <input type="number" min="1" class="form-control" id="edit_floor_number" name="FloorNumber" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_specific_area" class="form-label">
                            <i class="bi bi-pin-map"></i> Specific Area <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_specific_area" name="SpecificArea" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_person_responsible" class="form-label">
                            <i class="bi bi-person"></i> Person Responsible <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_person_responsible" name="PersonResponsible" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_remarks" class="form-label">
                            <i class="bi bi-chat-left-text"></i> Remarks
                        </label>
                        <textarea class="form-control" id="edit_remarks" name="Remarks" rows="3"></textarea>
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
            const rows = document.querySelectorAll('#eqTable tbody tr');

            rows.forEach(function(row) {
                const rowText = row.textContent.toLowerCase();
                const buildingCell = row.querySelector('td:nth-child(3)').textContent.toLowerCase();

                const searchMatch = rowText.indexOf(searchValue) > -1;
                const buildingMatch = !filterValue || buildingCell === filterValue;

                row.style.display = (searchMatch && buildingMatch) ? '' : 'none';
            });
        }
    });
</script>

<!-- Then at the bottom of the file, replace all script tags with this single script block -->
<script>
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
