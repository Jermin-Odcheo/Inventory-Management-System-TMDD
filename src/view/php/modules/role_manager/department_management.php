<?php
// equipment_location.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// -----------------------------------------------------------------
// Optionally check for admin privileges (uncomment if needed)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../../../public/index.php");
    exit();
}
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
    $DepartmentID          = trim($_POST['DepartmentID'] ?? '');
    $DepartmentAcronym  = trim($_POST['DepartmentAcronym'] ?? '');
    $DepartmentName       = trim($_POST['DepartmentName'] ?? '');

    $response = array('status' => '', 'message' => '');

    // Validate required fields
    if (empty($DepartmentID) || empty($DepartmentAcronym) || empty($DepartmentName)) {
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
            $stmt = $pdo->prepare("INSERT INTO departments (
                Department_ID, 
                Department_Acronym, 
                Department_Name 
            ) VALUES (?, ?, ?)");
            $stmt->execute([$DepartmentID, $DepartmentAcronym, $DepartmentName]);
            
            $newDepartmentId = $pdo->lastInsertId();

            // Prepare audit log data
            $newValues = json_encode([
                'Department_ID' => $DepartmentID,
                'Department_Acronym' => $DepartmentAcronym,
                'Department_Name' => $DepartmentName
            ]);
            // Insert audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $auditStmt->execute([
                $_SESSION['user_id'],
                $newDepartmentId,
                'Department Management',
                'Add',
                'New department added',
                null,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();
            
            $response['status'] = 'success';
            $response['message'] = 'Department has been added successfully.';
            $_SESSION['success'] = "Department has been added successfully.";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $response['status'] = 'error';
            $response['message'] = 'Error adding Department: ' . $e->getMessage();
            $_SESSION['errors'] = ["Error adding Department: " . $e->getMessage()];
        }
        echo json_encode($response);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $pdo->beginTransaction();
    
            // Get old department details for audit log
            $stmt = $pdo->prepare("SELECT * FROM departments WHERE Department_ID = ?");
            $stmt->execute([$id]);
            $oldDepartment = $stmt->fetch(PDO::FETCH_ASSOC);
    
            // Update department details
            $stmt = $pdo->prepare("UPDATE departments SET 
                Department_Acronym = ?, 
                Department_Name = ?
                WHERE Department_ID = ?");
            $stmt->execute([
                $_POST['DepartmentAcronym'],
                $_POST['DepartmentName'],
                $id
            ]);
    
            // Prepare audit log data
            $oldValues = json_encode([
                'Department_Acronym' => $oldDepartment['Department_Acronym'],
                'Department_Name' => $oldDepartment['Department_Name']
            ]);
    
            $newValues = json_encode([
                'Department_Acronym' => $_POST['DepartmentAcronym'],
                'Department_Name' => $_POST['DepartmentName']
            ]);
    
            // Insert audit log
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Departments',
                'Modified',
                'Department details modified',
                $oldValues,
                $newValues,
                'Successful'
            ]);
    
            $pdo->commit();
            
            // JSON response
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Department has been updated successfully.'
            ]);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // JSON error response
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Error updating Department: ' . $e->getMessage()
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
// RETRIEVE ALL DEPARTMENTS
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY Department_ID");
    $departments = $stmt->fetchAll();
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
                <h2 class="mb-4">Department Management</h2>

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

                <!-- List of Departments Card -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                        <span><i class="bi bi-list-ul"></i> List of Departments</span>
                        <div class="input-group w-auto">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search..." id="eqSearch">
                        </div>
                    </div>
                      <div class="card-body">
                        <?php if (!empty($departments)): ?>
                            <div class="table-responsive">
                                <!-- Add Department Button and Filter -->
                                <div class="d-flex justify-content-start mb-3 gap-2">
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                                        <i class="bi bi-plus-circle"></i> Add Department
                                    </button>
                                </div>
                                
                                <table class="table table-striped table-hover align-middle" id="table">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Department Acronym</th>
                                        <th>Department Name</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($departments as $department): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($department['Department_ID']); ?></td>
                                            <td><?php echo htmlspecialchars($department['Department_Acronym']); ?></td>
                                            <td><?php echo htmlspecialchars($department['Department_Name']); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a class="btn btn-sm btn-outline-primary edit-department" 
                                                    data-id="<?php echo htmlspecialchars($department['Department_ID']); ?>"
                                                    data-department-id="<?php echo htmlspecialchars($department['Department_ID']); ?>"
                                                    data-department-acronym="<?php echo htmlspecialchars($department['Department_Acronym']); ?>"
                                                    data-department-name="<?php echo htmlspecialchars($department['Department_Name']); ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                    <a class="btn btn-sm btn-outline-danger" href="?action=delete&id=<?php echo htmlspecialchars($department['Department_ID']); ?>" onclick="return confirm('Are you sure you want to delete this department?');">
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
            </main>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDepartmentForm" method="post">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="DepartmentID" class="form-label">
                            <i class="bi bi-tag"></i> Department ID <span class="text-danger">*</span>
                        </label>
                        <input type="number" min="1" class="form-control" name="DepartmentID" id="DepartmentID" required>
                    </div>

                    <div class="mb-3">
                        <label for="BuildingLocation" class="form-label">
                            <i class="bi bi-building"></i> Department Acronym <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="DepartmentAcronym" name="DepartmentAcronym" required>
                    </div>

                    <div class="mb-3">
                        <label for="DepartmentName" class="form-label">
                            <i class="bi bi-layers"></i> Department Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="DepartmentName" name="DepartmentName" required>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editDepartmentForm" method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_department_hidden_id">
                    
                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label">
                            <i class="bi bi-tag"></i> Department ID <span class="text-danger">*</span>
                        </label>
                        <input type="number" min="1" class="form-control" id="edit_department_id" name="DepartmentID" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_department_acronym" class="form-label">
                            <i class="bi bi-building"></i> Department Acronym <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_department_acronym" name="DepartmentAcronym" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_department_name" class="form-label">
                            <i class="bi bi-layers"></i> Department Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="edit_department_name" name="DepartmentName" required>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Department</button>
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
    $('#addDepartmentForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'department_management.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(result) {
                if (result.status === 'success') {
                    // Hide modal and redirect
                    $('#addDepartmentModal').modal('hide');
                    window.location.href = 'department_management.php';
                } else {
                    alert(result.message || 'An error occurred');
                }
            },
            error: function(xhr, status, error) {
                console.log(xhr.responseText);
                alert('Error submitting the form: ' + error);
            }
        });
    });

    // Edit button click handler for departments
    $('.edit-department').click(function() {
        const id = $(this).data('id');
        const departmentId = $(this).data('department-id');
        const departmentAcronym = $(this).data('department-acronym');
        const departmentName = $(this).data('department-name');

        // Populate modal fields
        $('#edit_department_hidden_id').val(id); // Hidden field for ID
        $('#edit_department_id').val(departmentId);
        $('#edit_department_acronym').val(departmentAcronym);
        $('#edit_department_name').val(departmentName);

        // Show the modal
        $('#editDepartmentModal').modal('show');
    });

    // Edit form submission
    $('#editDepartmentForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'department_management.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(result) {
                if (result.status === 'success') {
                    $('#editDepartmentModal').modal('hide');
                    window.location.href = 'department_management.php';
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
