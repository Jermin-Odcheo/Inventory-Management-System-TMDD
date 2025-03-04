<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all modules with grouped privileges
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id, 
            m.Module_Name, 
            GROUP_CONCAT(p.priv_name ORDER BY p.id SEPARATOR ', ') AS Privileges
        FROM modules AS m
        LEFT JOIN privileges AS p ON m.id = p.id
        GROUP BY m.id, m.Module_Name
        ORDER BY m.id
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission for adding a module
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['module_name'])) {
        $moduleName = trim($_POST['module_name']);

        try {
            $stmt = $pdo->prepare("INSERT INTO modules (Module_Name) VALUES (?)");
            $stmt->execute([$moduleName]);
            header("Location: manage_privileges.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    } else {
        echo "<p class='text-danger'>Module Name cannot be empty.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Module Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #ffffff; color: #000000; font-family: 'Arial', sans-serif; }
        .content { margin-left: 300px; padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5); }
        .table thead th { background-color: #000000; color: #fff; }
    </style>
</head>
<body>
    <?php include '../../../php/general/sidebar.php'; ?>
    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Module Management</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModuleModal">Add New Module</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Module ID</th>
                            <th>Module Name</th>
                            <th>Privileges</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $module): ?>
                            <tr>
                                <td><?= htmlspecialchars($module['id']) ?></td>
                                <td><?= htmlspecialchars($module['Module_Name']) ?></td>
                                <td><?= htmlspecialchars($module['Privileges'] ?? 'None') ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning edit-privileges-btn" data-module-id="<?= $module['id'] ?>" data-bs-toggle="modal" data-bs-target="#editPrivilegesModal">Edit Privileges</button>
                                    <button type="button" class="btn btn-sm btn-danger delete-module-btn" data-module-id="<?= $module['id'] ?>" data-module-name="<?= htmlspecialchars($module['Module_Name']) ?>" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Module Modal -->
    <div class="modal fade" id="addModuleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addModuleForm">
                        <div class="mb-3">
                            <label for="module_name" class="form-label">Module Name:</label>
                            <input type="text" name="module_name" id="module_name" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Module</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Privileges Modal -->
    <div class="modal fade" id="editPrivilegesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Privileges</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editPrivilegesForm">
                        <input type="hidden" id="editModuleId" name="module_id">
                        <div class="mb-3">
                            <label for="privileges" class="form-label">Privileges:</label>
                            <textarea id="privileges" name="privileges" class="form-control" rows="5" placeholder="Enter privileges, separated by commas"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Delete Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this module?</p>
                    <input type="hidden" id="deleteModuleId" name="deleteModuleId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Handle form submission for adding a module
        $('#addModuleForm').on('submit', function(event) {
            event.preventDefault();
            let formData = $(this).serialize();

            $.ajax({
                url: 'manage_privileges.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Module added successfully.');
                        $('#addModuleModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error submitting form.');
                }
            });
        });

        // Fetch privileges for the selected module when the edit button is clicked
        $('.edit-privileges-btn').on('click', function() {
            var moduleId = $(this).data('module-id');
            $('#editModuleId').val(moduleId);

            $.ajax({
                url: 'fetch_privileges.php',
                type: 'GET',
                data: { module_id: moduleId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#privileges').val(response.privileges.join(', '));
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error fetching privileges.');
                }
            });
        });

        // Handle form submission for editing privileges
        $('#editPrivilegesForm').on('submit', function(event) {
            event.preventDefault();
            let formData = $(this).serialize();

            $.ajax({
                url: 'update_privileges.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Privileges updated successfully.');
                        $('#editPrivilegesModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error updating privileges.');
                }
            });
        });

        // Handle module deletion
        $('.delete-module-btn').on('click', function() {
            var moduleId = $(this).data('module-id');
            $('#deleteModuleId').val(moduleId);
        });

        $('#confirmDeleteBtn').on('click', function() {
            var moduleId = $('#deleteModuleId').val();

            $.ajax({
                url: 'delete_module.php',
                type: 'POST',
                data: { module_id: moduleId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Module deleted successfully.');
                        $('#confirmDeleteModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error deleting module.');
                }
            });
        });
    });
    </script>
</body>
</html>
