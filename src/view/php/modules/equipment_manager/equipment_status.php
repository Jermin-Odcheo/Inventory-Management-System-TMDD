<?php
// equipment_status.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// -----------------------------------------------------------------
// Optionally check for admin privileges (uncomment if needed)
// if (!isset($_SESSION['user_id'])) {
//     header("Location: add_user.php");
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

// Initialize response array
$response = array('status' => '', 'message' => '');

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt = $pdo->prepare("INSERT INTO equipmentstatus (AssetTag, Status, Action, Remarks) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['status'],
                        $_POST['action_taken'],
                        $_POST['remarks']
                    ]);
                    $response['status'] = 'success';
                    $response['message'] = 'Status added successfully';
                } catch (PDOException $e) {
                    $response['status'] = 'error';
                    $response['message'] = 'Error adding status: ' . $e->getMessage();
                }
                break;

            case 'update':
                try {
                    $stmt = $pdo->prepare("UPDATE equipmentstatus SET AssetTag = ?, Status = ?, Action = ?, Remarks = ? WHERE EquipmentStatusID = ?");
                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['status'],
                        $_POST['action_taken'],
                        $_POST['remarks'],
                        $_POST['status_id']
                    ]);
                    $response['status'] = 'success';
                    $response['message'] = 'Status updated successfully';
                } catch (PDOException $e) {
                    $response['status'] = 'error';
                    $response['message'] = 'Error updating status: ' . $e->getMessage();
                }
                break;

            case 'delete':
                try {
                    if (!isset($_POST['status_id'])) {
                        throw new Exception('Status ID is required');
                    }

                    // Get status details before deletion for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipmentstatus WHERE EquipmentStatusID = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $statusData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$statusData) {
                        throw new Exception('Status not found');
                    }

                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Set current user for audit logging
                    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                    
                    // Prepare audit log data
                    $oldValue = json_encode([
                        'EquipmentStatusID' => $statusData['EquipmentStatusID'],
                        'AssetTag' => $statusData['AssetTag'],
                        'Status' => $statusData['Status'],
                        'Action' => $statusData['Action'],
                        'Remarks' => $statusData['Remarks']
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
                        $statusData['EquipmentStatusID'],
                        'Equipment Management',
                        'Delete',
                        'Equipment status has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);

                    // Now perform the delete
                    $stmt = $pdo->prepare("DELETE FROM equipmentstatus WHERE EquipmentStatusID = ?");
                    $stmt->execute([$_POST['status_id']]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Status deleted successfully'
                    ];
                } catch (Exception $e) {
                    // Rollback transaction on error
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response = [
                        'status' => 'error',
                        'message' => 'Error deleting status: ' . $e->getMessage()
                    ];
                }
                
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                break;
        }
        echo json_encode($response);
        exit;
    }
}

// Add this near the start of your PHP code, after session and database connection
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        // Get status details before deletion for audit log
        $stmt = $pdo->prepare("SELECT * FROM equipmentstatus WHERE EquipmentStatusID = ?");
        $stmt->execute([$id]);
        $statusData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($statusData) {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Set current user for audit logging
            $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
            
            // Prepare audit log data
            $oldValue = json_encode([
                'EquipmentStatusID' => $statusData['EquipmentStatusID'],
                'AssetTag' => $statusData['AssetTag'],
                'Status' => $statusData['Status'],
                'Action' => $statusData['Action'],
                'Remarks' => $statusData['Remarks']
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
                $statusData['EquipmentStatusID'],
                'Equipment Management',
                'Delete',
                'Equipment status has been deleted',
                $oldValue,
                null,
                'Successful'
            ]);

            // Now perform the delete
            $stmt = $pdo->prepare("DELETE FROM equipmentstatus WHERE EquipmentStatusID = ?");
            $stmt->execute([$id]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success'] = "Equipment Status deleted successfully.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error deleting Equipment Status: " . $e->getMessage();
    }
    header("Location: equipment_status.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Status Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add sidebar CSS -->
    <link rel="stylesheet" href="/src/view/styles/css/sidebar.css">
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
        .form-select-sm {
            min-width: 150px;
        }
        
        .d-flex.gap-2 {
            gap: 0.5rem !important;
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include('../../general/sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <main class="col-md-12 px-md-4 py-4">
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                            <h2 class="card-title mb-0 fs-4">Equipment Status Management</h2>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                                        <i class="bi bi-plus-circle"></i> Add New Status
                                    </button>
                                    <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                                        <option value="">Filter By Status</option>
                                        <?php
                                        try {
                                            $statusTypes = $pdo->query("SELECT DISTINCT Status FROM equipmentstatus WHERE Status IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
                                            foreach($statusTypes as $status) {
                                                echo "<option value='" . htmlspecialchars($status) . "'>" . htmlspecialchars($status) . "</option>";
                                            }
                                        } catch (PDOException $e) {
                                            // Handle error silently
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="search-container position-relative">
                                    <input type="text" id="searchStatus" class="form-control form-control-sm" placeholder="Search status...">
                                    <i class="bi bi-search position-absolute top-50 end-0 translate-middle-y me-2"></i>
                                </div>
                            </div>

                            <!-- Status List Table -->
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 7%">Status ID</th>
                                            <th style="width: 13%">Asset Tag</th>
                                            <th style="width: 15%">Status</th>
                                            <th style="width: 18%">Action</th>
                                            <th style="width: 22%">Remarks</th>
                                            <th style="width: 15%">Operations</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM equipmentstatus");
                                            while ($row = $stmt->fetch()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['EquipmentStatusID']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['AssetTag']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['Status']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['Action']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['Remarks']) . "</td>";
                                                echo "<td>
                                                        <div class='d-flex justify-content-center gap-2'>
                                                            <button class='btn btn-sm btn-outline-primary edit-status' 
                                                                    data-id='" . htmlspecialchars($row['EquipmentStatusID']) . "'
                                                                    data-asset='" . htmlspecialchars($row['AssetTag']) . "'
                                                                    data-status='" . htmlspecialchars($row['Status']) . "'
                                                                    data-action='" . htmlspecialchars($row['Action']) . "'
                                                                    data-remarks='" . htmlspecialchars($row['Remarks']) . "'>
                                                                <i class='far fa-edit'></i> Edit
                                                            </button>
                                                            <button class='btn btn-sm btn-outline-danger delete-status' 
                                                                    data-id='" . htmlspecialchars($row['EquipmentStatusID']) . "'>
                                                                <i class='far fa-trash-alt'></i> Delete
                                                            </button>
                                                        </div>
                                                    </td>";
                                                echo "</tr>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<tr><td colspan='6'>Error loading data: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Add Status Modal -->
    <div class="modal fade" id="addStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addStatusForm">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" name="asset_tag" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="Working">Working</option>
                                <option value="Defective">Defective</option>
                                <option value="Replacement">Replacement</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="action_taken" class="form-label">Action Taken</label>
                            <input type="text" class="form-control" name="action_taken" required>
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Add Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div class="modal fade" id="editStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editStatusForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="status_id" id="edit_status_id">
                        <div class="mb-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" name="asset_tag" id="edit_asset_tag" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="Working">Working</option>
                                <option value="Defective">Defective</option>
                                <option value="Replacement">Replacement</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_action_taken" class="form-label">Action Taken</label>
                            <input type="text" class="form-control" name="action_taken" id="edit_action_taken" required>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add Font Awesome for sidebar icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <!-- Keep existing JavaScript code -->
    <script>
        $(document).ready(function() {
            // Add Status
            $('#addStatusForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        location.reload();
                    }
                });
            });

            // Edit Status
            $('.edit-status').click(function() {
                var id = $(this).data('id');
                var asset = $(this).data('asset');
                var status = $(this).data('status');
                var action = $(this).data('action');
                var remarks = $(this).data('remarks');
                
                $('#edit_status_id').val(id);
                $('#edit_asset_tag').val(asset);
                $('#edit_status').val(status);
                $('#edit_action_taken').val(action);
                $('#edit_remarks').val(remarks);
                
                $('#editStatusModal').modal('show');
            });

            // Update Status
            $('#editStatusForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        location.reload();
                    }
                });
            });

            // Search functionality
            $('#searchStatus').on('input', function() {
                filterTable();
            });

            // Filter functionality
            $('#filterStatus').on('change', function() {
                filterTable();
            });

            function filterTable() {
                var searchText = $('#searchStatus').val().toLowerCase();
                var filterStatus = $('#filterStatus').val().toLowerCase();

                $(".table tbody tr").each(function() {
                    var rowText = $(this).text().toLowerCase();
                    var statusCell = $(this).find('td:eq(2)').text().toLowerCase(); // Adjust index based on status column

                    var searchMatch = rowText.indexOf(searchText) > -1;
                    var statusMatch = !filterStatus || statusCell === filterStatus;

                    $(this).toggle(searchMatch && statusMatch);
                });
            }

            // Delete Status
            $('.delete-status').click(function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                
                if (confirm('Are you sure you want to delete this status?')) {
                    $.ajax({
                        url: 'equipment_status.php',
                        method: 'POST',
                        data: {
                            action: 'delete',
                            status_id: id
                        },
                        success: function(response) {
                            try {
                                var result = JSON.parse(response);
                                if (result.status === 'success') {
                                    location.reload();
                                } else {
                                    alert(result.message);
                                }
                            } catch (e) {
                                location.reload();
                            }
                        },
                        error: function() {
                            location.reload();
                        }
                    });
                }
            });
        });
    </script>

    <!-- Add this before closing body tag -->
    <script>
        // Handle logout
        document.querySelector('.logout-btn').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.replace('/src/view/php/general/logout.php');
        });

        // Prevent back button after logout
        window.addEventListener('load', function() {
            if (performance.navigation.type === 2) { // Back/Forward navigation
                location.reload(true);
            }
        });
    </script>
</body>
</html>
