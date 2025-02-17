<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
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
                    $stmt = $pdo->prepare("DELETE FROM equipmentstatus WHERE EquipmentStatusID = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $response['status'] = 'success';
                    $response['message'] = 'Status deleted successfully';
                } catch (PDOException $e) {
                    $response['status'] = 'error';
                    $response['message'] = 'Error deleting status: ' . $e->getMessage();
                }
                break;
        }
        echo json_encode($response);
        exit;
    }
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
</head>
<body>
    <!-- Include Sidebar -->
    <?php include('../../general/sidebar.php'); ?>

    <!-- Main Content -->
    <div class="container-fluid" style="margin-left: 320px; padding: 20px; height: calc(100vh - 40px); width: calc(100vw - 340px); overflow-x: hidden;">
        <div class="card shadow" style="height: 100%;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                <h2 class="card-title mb-0 fs-4">Equipment Status Management</h2>
            </div>
            <div class="card-body p-3" style="overflow: auto;">
                <!-- Add Status Button -->
                <div class="d-flex justify-content-start mb-2">
                    <button type="button" class="btn btn-success btn-sm py-1 px-2" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                        Add New Status
                    </button>
                </div>

                <!-- Status List Table -->
                <div class="table-responsive" style="height: calc(100% - 50px); width: 100%; overflow-x: auto;">
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
                                            <button class='btn btn-sm btn-warning btn-edit edit-status' 
                                                data-id='" . htmlspecialchars($row['EquipmentStatusID']) . "'
                                                data-asset='" . htmlspecialchars($row['AssetTag']) . "'
                                                data-status='" . htmlspecialchars($row['Status']) . "'
                                                data-action='" . htmlspecialchars($row['Action']) . "'
                                                data-remarks='" . htmlspecialchars($row['Remarks']) . "'>Edit</button>
                                            <button class='btn btn-sm btn-danger delete-status' 
                                                data-id='" . htmlspecialchars($row['EquipmentStatusID']) . "'>Delete</button>
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

            // Delete Status
            $('.delete-status').click(function() {
                if (confirm('Are you sure you want to delete this status?')) {
                    var id = $(this).data('id');
                    $.ajax({
                        url: 'equipment_status.php',
                        method: 'POST',
                        data: {
                            action: 'delete',
                            status_id: id
                        },
                        success: function(response) {
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
