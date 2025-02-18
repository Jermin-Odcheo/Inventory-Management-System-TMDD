<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

$sql = "
    SELECT 
        r.Role_ID,
        r.Role_Name,
        COALESCE(m.Module_Name, 'General') AS Module_Name,
        p.Privilege_Name
    FROM roles r
    LEFT JOIN role_privileges rp ON r.Role_ID = rp.Role_ID
    LEFT JOIN privileges p ON rp.Privilege_ID = p.Privilege_ID
    LEFT JOIN modules m ON p.Module_ID = m.Module_ID
    ORDER BY r.Role_ID ASC, m.Module_Name, p.Privilege_Name
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
    $moduleName = $row['Module_Name'];
    if (!isset($roles[$roleID]['Modules'][$moduleName])) {
        $roles[$roleID]['Modules'][$moduleName] = [];
    }
    if ($row['Privilege_Name'] !== null) {
        $roles[$roleID]['Modules'][$moduleName][] = $row['Privilege_Name'];
    }
}
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
            padding: 0 15px;
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
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                Add New Role
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
                                        <?php echo !empty($privileges) ? implode(', ', array_map('htmlspecialchars', $privileges)) : '<em>No privileges</em>'; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-warning edit-role-btn" data-role-id="<?php echo $roleID; ?>" data-bs-toggle="modal" data-bs-target="#editRoleModal">
                                    Edit
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
            <div class="modal-header">
                <h5 class="modal-title">Edit Role &amp; Privileges</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="editRoleContent">
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
    $(document).ready(function() {
        // Load edit role modal content via AJAX.
        $('.edit-role-btn').on('click', function() {
            var roleID = $(this).data('role-id');
            $('#editRoleContent').html("Loading...");
            $.ajax({
                url: 'edit_roles.php',
                type: 'GET',
                data: { id: roleID },
                success: function(response) {
                    $('#editRoleContent').html(response);
                },
                error: function() {
                    $('#editRoleContent').html('<p class="text-danger">Error loading role data.</p>');
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
    });
</script>
</body>
</html>
