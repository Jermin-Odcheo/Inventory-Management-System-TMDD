<?php
// purchase_order.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Set cache-control headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Redirect to login page
    header("Location: /public/index.php");
    exit();
}

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
// DELETE PURCHASE ORDER
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM purchaseorder WHERE PurchaseOrderID = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Purchase Order deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Purchase Order: " . $e->getMessage()];
    }
    header("Location: purchase_order.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $response = array('status' => '', 'message' => '');
        
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Debug line
                    error_log('Received POST data: ' . print_r($_POST, true));
                    
                    $stmt = $pdo->prepare("INSERT INTO equipmentdetails (
                        AssetTag, 
                        AssetDescription1, 
                        AssetDescription2, 
                        Specification, 
                        Brand, 
                        Model, 
                        SerialNumber, 
                        DateAcquired, 
                        AccountableIndividual,
                        Remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['asset_description1'],
                        $_POST['asset_description2'],
                        $_POST['specification'],
                        $_POST['brand'],
                        $_POST['model'],
                        $_POST['serial_number'],
                        $_POST['date_acquired'],
                        $_POST['accountable_individual'],
                        $_POST['remarks']
                    ]);
                    
                    // Debug line
                    error_log('Insert successful. Last Insert ID: ' . $pdo->lastInsertId());
                    
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment added successfully';
                } catch (PDOException $e) {
                    // Debug line
                    error_log('Database error: ' . $e->getMessage());
                    
                    $response['status'] = 'error';
                    $response['message'] = 'Error adding equipment: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit;
                break;

            case 'update':
                try {
                    $stmt = $pdo->prepare("UPDATE equipmentdetails SET 
                        AssetTag = ?,
                        AssetDescription1 = ?,
                        AssetDescription2 = ?,
                        Specification = ?,
                        Brand = ?,
                        Model = ?,
                        SerialNumber = ?,
                        DateAcquired = ?,
                        AccountableIndividual = ?,
                        Remarks = ?
                        WHERE EquipmentDetailsID = ?");
                    
                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['asset_description1'],
                        $_POST['asset_description2'],
                        $_POST['specification'],
                        $_POST['brand'],
                        $_POST['model'],
                        $_POST['serial_number'],
                        $_POST['date_acquired'],
                        $_POST['accountable_individual'],
                        $_POST['remarks'],
                        $_POST['equipment_id']
                    ]);
                    
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment updated successfully';
                } catch (PDOException $e) {
                    $response['status'] = 'error';
                    $response['message'] = 'Error updating equipment: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit;
                break;

            case 'delete':
                try {
                    // Get equipment details before deletion for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipmentdetails WHERE EquipmentDetailsID = ?");
                    $stmt->execute([$_POST['equipment_id']]);
                    $equipmentData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Set current user for audit logging
                    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                    
                    // Prepare audit log data
                    $oldValue = json_encode([
                        'EquipmentDetailsID' => $equipmentData['EquipmentDetailsID'],
                        'AssetTag' => $equipmentData['AssetTag'],
                        'AssetDescription1' => $equipmentData['AssetDescription1'],
                        'AssetDescription2' => $equipmentData['AssetDescription2'],
                        'Specification' => $equipmentData['Specification'],
                        'Brand' => $equipmentData['Brand'],
                        'Model' => $equipmentData['Model'],
                        'SerialNumber' => $equipmentData['SerialNumber'],
                        'DateAcquired' => $equipmentData['DateAcquired'],
                        'AccountableIndividual' => $equipmentData['AccountableIndividual']
                    ]);

                    // Insert into audit_log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID,
                            EntityID,
                            Module,
                            Action,
                            Details,
                            OldVal,
                            NewVal,
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $equipmentData['EquipmentDetailsID'],
                        'Equipment Management',
                        'Delete',
                        'Equipment has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);

                    // Now perform the delete
                    $stmt = $pdo->prepare("DELETE FROM equipmentdetails WHERE EquipmentDetailsID = ?");
                    $stmt->execute([$_POST['equipment_id']]);
                    
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment deleted successfully';
                } catch (PDOException $e) {
                    $response['status'] = 'error';
                    $response['message'] = 'Error deleting equipment: ' . $e->getMessage();
                }
                echo json_encode($response);
                exit;
                break;
        }
    }
}


// ------------------------
// RETRIEVE ALL EQUIPMENT DETAILS
// ------------------------
try {
    $stmt = $pdo->query("SELECT *
                         FROM equipmentdetails 
                         ORDER BY DateAcquired DESC");
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
    <title>Equipment Details</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Add this in the head section after Bootstrap CSS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .main-content {
            width: 100%;
            min-height: 100vh;
        }

        .card {
            margin-bottom: 1rem;
        }

        .form-control {
            font-size: 0.9rem;
        }

        .table {
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            main {
                margin-left: 0 !important;
                max-width: 100% !important;
            }
        }

        .search-container {
            width: 250px;
        }
        .search-container input {
            padding-right: 30px;
        }
        .search-container i {
            color: #6c757d;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <?php include '../../general/sidebar.php'; ?>

    <div class="container-fluid" style="margin-left: 320px; padding: 20px; width: calc(100vw - 340px);">
        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                <h2 class="card-title mb-0 fs-4">Equipment Details Management</h2>
            </div>
            <div class="card-body p-3">
                <!-- Add Equipment Details Button -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                            <i class="bi bi-plus-circle"></i> Add Equipment
                        </button>
                        <select class="form-select form-select-sm" id="filterEquipment" style="width: auto;">
                            <option value="">Filter Equipment Type</option>
                            <?php
                            // Get unique Description1 values instead of brands
                            $equipmentTypes = array_unique(array_column($equipmentDetails, 'AssetDescription1'));
                            foreach($equipmentTypes as $type) {
                                if(!empty($type)) {
                                    echo "<option value='" . htmlspecialchars($type) . "'>" . htmlspecialchars($type) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="search-container position-relative">
                        <input type="text" id="searchEquipment" class="form-control form-control-sm" placeholder="Search equipment...">
                        <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-2"></i>
                    </div>
                </div>

                <!-- Equipment Details List Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Asset Tag</th>
                                <th>Description 1</th>
                                <th>Description 2</th>
                                <th>Specification</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>Date Acquired</th>
                                <th>Accountable Individual</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipmentDetails as $equipment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($equipment['EquipmentDetailsID']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['AssetTag']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['AssetDescription1']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['AssetDescription2']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['Specification']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['Brand']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['Model']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['SerialNumber']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['DateAcquired']); ?></td>
                                    <td><?php echo htmlspecialchars($equipment['AccountableIndividual']); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-outline-primary edit-equipment" 
                                               data-id="<?php echo htmlspecialchars($equipment['EquipmentDetailsID']); ?>"
                                               data-asset="<?php echo htmlspecialchars($equipment['AssetTag']); ?>"
                                               data-desc1="<?php echo htmlspecialchars($equipment['AssetDescription1']); ?>"
                                               data-desc2="<?php echo htmlspecialchars($equipment['AssetDescription2']); ?>"
                                               data-spec="<?php echo htmlspecialchars($equipment['Specification']); ?>"
                                               data-brand="<?php echo htmlspecialchars($equipment['Brand']); ?>"
                                               data-model="<?php echo htmlspecialchars($equipment['Model']); ?>"
                                               data-serial="<?php echo htmlspecialchars($equipment['SerialNumber']); ?>"
                                               data-date="<?php echo htmlspecialchars($equipment['DateAcquired']); ?>"
                                               data-accountable="<?php echo htmlspecialchars($equipment['AccountableIndividual']); ?>"
                                               data-remarks="<?php echo htmlspecialchars($equipment['Remarks']); ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger delete-equipment" 
                                               data-id="<?php echo htmlspecialchars($equipment['EquipmentDetailsID']); ?>"
                                               href="#" onclick="return confirm('Are you sure you want to delete this equipment?');">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
                            <label for="asset_description1" class="form-label">Asset Description 1</label>
                            <input type="text" class="form-control" name="asset_description1" required>
                        </div>
                        <div class="mb-3">
                            <label for="asset_description2" class="form-label">Asset Description 2</label>
                            <input type="text" class="form-control" name="asset_description2" required>
                        </div>
                        <div class="mb-3">
                            <label for="specification" class="form-label">Specification</label>
                            <input type="text" class="form-control" name="specification" required>
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
                            <label for="date_acquired" class="form-label">Date Acquired</label>
                            <input type="date" class="form-control" name="date_acquired" 
                                   max="<?php echo date('Y-m-d'); ?>" 
                                   required>
                        </div>
                        <div class="mb-3">
                            <label for="accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual" required>
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
                            <label for="edit_asset_description1" class="form-label">Asset Description 1</label>
                            <input type="text" class="form-control" name="asset_description1" id="edit_asset_description1">
                        </div>
                        <div class="mb-3">
                            <label for="edit_asset_description2" class="form-label">Asset Description 2</label>
                            <input type="text" class="form-control" name="asset_description2" id="edit_asset_description2">
                        </div>
                        <div class="mb-3">
                            <label for="edit_specification" class="form-label">Specification</label>
                            <input type="text" class="form-control" name="specification" id="edit_specification">
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
                            <label for="edit_date_acquired" class="form-label">Date Acquired</label>
                            <input type="date" class="form-control" name="date_acquired" 
                                   id="edit_date_acquired" 
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="edit_accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual" id="edit_accountable_individual">
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

    <!-- JavaScript for Real-Time Table Filtering -->
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

            function filterTable() {
                var searchText = $('#searchEquipment').val().toLowerCase();
                var filterType = $('#filterEquipment').val().toLowerCase();

                $(".table tbody tr").each(function() {
                    var rowText = $(this).text().toLowerCase();
                    var typeCell = $(this).find('td:eq(2)').text().toLowerCase(); // Index 2 is Description1 column

                    var searchMatch = rowText.indexOf(searchText) > -1;
                    var typeMatch = !filterType || typeCell === filterType;

                    $(this).toggle(searchMatch && typeMatch);
                });
            }
        });
    </script>

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Replace your existing JavaScript section at the bottom with this -->
    <script>
    $(document).ready(function() {
        // Add Equipment
        $('#addEquipmentForm').on('submit', function(e) {
            e.preventDefault();
            console.log('Form submitted', $(this).serialize()); // Debug line
            
            $.ajax({
                url: 'equipment_details.php',
                method: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    console.log('Response:', response); // Debug line
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            $('#addEquipmentModal').modal('hide');
                            location.reload();
                        } else {
                            alert(result.message);
                        }
                    } catch (e) {
                        console.error('Parse error:', e); // Debug line
                        alert('Error processing the request');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error); // Debug line
                    alert('Error submitting the form');
                }
            });
        });

        // Edit Equipment
        $('.edit-equipment').click(function() {
            var id = $(this).data('id');
            var asset = $(this).data('asset');
            var desc1 = $(this).data('desc1');
            var desc2 = $(this).data('desc2');
            var spec = $(this).data('spec');
            var brand = $(this).data('brand');
            var model = $(this).data('model');
            var serial = $(this).data('serial');
            var date = $(this).data('date');
            var accountable = $(this).data('accountable');
            var remarks = $(this).data('remarks');
            
            $('#edit_equipment_id').val(id);
            $('#edit_asset_tag').val(asset);
            $('#edit_asset_description1').val(desc1);
            $('#edit_asset_description2').val(desc2);
            $('#edit_specification').val(spec);
            $('#edit_brand').val(brand);
            $('#edit_model').val(model);
            $('#edit_serial_number').val(serial);
            $('#edit_date_acquired').val(date);
            $('#edit_accountable_individual').val(accountable);
            $('#edit_remarks').val(remarks);
            
            $('#editEquipmentModal').modal('show');
        });

        // Delete Equipment
        $('.delete-equipment').off('click').on('click', function(e) {
            e.preventDefault(); // Prevent any default action
            var id = $(this).data('id');
            
            if (confirm('Are you sure you want to delete this equipment?')) {
                $.ajax({
                    url: 'equipment_details.php',
                    method: 'POST',
                    data: {
                        action: 'delete',
                        equipment_id: id
                    },
                    success: function(response) {
                        var result = JSON.parse(response);
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
                success: function(response) {
                    location.reload();
                }
            });
        });
    });
    </script>
</body>

</html>