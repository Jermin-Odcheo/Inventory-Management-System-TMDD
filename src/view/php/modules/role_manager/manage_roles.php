<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';

// Updated SQL join conditions using the correct key for modules.
$sql = "
    SELECT
        r.id AS Role_ID,
        r.role_name AS Role_Name,
        COALESCE(m.module_name, 'General') AS Module_Name,
        p.priv_name AS Privilege_Name
    FROM roles r
    LEFT JOIN role_module_privileges rmp ON r.id = rmp.role_id
    LEFT JOIN privileges p ON rmp.privilege_id = p.id
    LEFT JOIN modules m ON rmp.module_id = m.id
    ORDER BY r.id ASC, m.module_name, p.priv_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group data by role and module.
$roles = [];
foreach ($roleData as $row) {
    $roleID = $row['Role_ID'];
    if (!isset($roles[$roleID])) {
        $roles[$roleID] = [
            'Role_Name' => $row['Role_Name'],
            'Modules'   => []
        ];
    }
    // Use COALESCE result; if empty, default to "General"
    $moduleName = !empty($row['Module_Name']) ? $row['Module_Name'] : 'General';
    if (!isset($roles[$roleID]['Modules'][$moduleName])) {
        $roles[$roleID]['Modules'][$moduleName] = [];
    }
    // Only add privilege if it's not null or empty.
    if (!empty($row['Privilege_Name'])) {
        $roles[$roleID]['Modules'][$moduleName][] = $row['Privilege_Name'];
    }
}

// Remove duplicate privileges if any.
foreach ($roles as $roleID => &$role) {
    foreach ($role['Modules'] as $moduleName => &$privileges) {
        $privileges = array_unique($privileges);
    }
}
unset($role); // break the reference
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Role Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Flex container to hold sidebar and main content */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        /* Sidebar with fixed width */
        .sidebar {
            width: 300px;
            background-color: #2c3e50;
            color: #fff;
        }
        /* Main content takes remaining space */
        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 300px;
        }
        /* (Optional) Override container-fluid padding if needed */
        .container-fluid {
            padding: 100px 15px;
        }
    </style>
</head>

<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <?php include '../../general/sidebar.php'; ?>
    </div>

    <!-- Main Content Area -->
    <div class="main-content container-fluid">
        <h1>Role Management</h1>

        <!-- Alert container -->
        <div id="alertMessage" style="position: fixed; top: 20px; right: 20px; z-index: 1050;"></div>

        <div class="d-flex justify-content-end mb-3">
            <!-- Add Undo and Redo buttons here -->
            <button type="button" class="btn btn-secondary me-2" id="undoButton">Undo</button>
            <button type="button" class="btn btn-secondary" id="redoButton">Redo</button>
            <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                Create New Role
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                <tr>
                    <th>Role ID</th>
                    <th>Role Name</th>
                    <th>Modules &amp; Privileges</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!empty($roles)): ?>
                    <?php foreach ($roles as $roleID => $role): ?>
                        <tr data-role-id="<?php echo $roleID; ?>">
                            <td><?php echo htmlspecialchars($roleID); ?></td>
                            <td class="role-name"><?php echo htmlspecialchars($role['Role_Name']); ?></td>
                            <td class="privilege-list">
                                <?php foreach ($role['Modules'] as $moduleName => $privileges): ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($moduleName); ?></strong>:
                                        <?php echo !empty($privileges)
                                            ? implode(', ', array_map('htmlspecialchars', $privileges))
                                            : '<em>No privileges</em>'; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-role-btn" data-role-id="<?php echo $roleID; ?>" data-bs-toggle="modal" data-bs-target="#editRoleModal">
                                    Modify
                                </button>
                                <button type="button" class="btn btn-sm btn-danger delete-role-btn" data-role-id="<?php echo $roleID; ?>" data-role-name="<?php echo htmlspecialchars($role['Role_Name']); ?>" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No roles found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div><!-- /.table-responsive -->
    </div><!-- /.main-content -->
</div><!-- /.wrapper -->

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div id="editRoleContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<!-- Confirm Delete Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the role "<span id="roleNamePlaceholder"></span>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="addRoleContent">Loading...</div>
        </div>
    </div>
</div>

<script>
    function showAlert(type, message) {
        const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        `;
        $("#alertMessage").html(alertHtml).fadeIn();

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            $("#alertMessage .alert").fadeOut(() => $(this).remove());
        }, 5000);
    }

    $(document).ready(function() {
        // Load edit role modal content via AJAX.
        $('.edit-role-btn').on('click', function() {
            var roleID = $(this).data('role-id');
            $('#editRoleContent').html("Loading...");
            $.ajax({
                url: 'edit_roles.php',
                type: 'GET',
                data: { id: roleID },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    $('#editRoleContent').html(response);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);
                    $('#editRoleContent').html('<p class="text-danger">Error loading role data. Please try again.</p>');
                }
            });
        });

        // Handle delete role modal.
        $('#confirmDeleteModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var roleID = button.data('role-id');
            var roleName = button.data('role-name');
            $('#roleNamePlaceholder').text(roleName);
            $('#confirmDeleteButton').attr('href', 'delete_role.php?id=' + roleID);
        });

        // Load add role modal content via AJAX.
        $('#addRoleModal').on('show.bs.modal', function () {
            $('#addRoleContent').html("Loading...");
            $.ajax({
                url: 'add_role.php',
                type: 'GET',
                success: function(response) {
                    $('#addRoleContent').html(response);
                },
                error: function() {
                    $('#addRoleContent').html('<p class="text-danger">Error loading form.</p>');
                }
            });
        });

        // Undo button click handler.
        $('#undoButton').on('click', function() {
            $.ajax({
                url: 'undo.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Error processing undo request.');
                }
            });
        });

        // Redo button click handler.
        $('#redoButton').on('click', function() {
            $.ajax({
                url: 'redo.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Error processing redo request.');
                }
            });
        });
    });
</script>
</body>
</html>
