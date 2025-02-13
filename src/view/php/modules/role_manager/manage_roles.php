<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if the logged-in user has permission to manage roles
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// SQL to retrieve roles with their modules and privileges.
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #ffffff; color: #000000; font-family: 'Arial', sans-serif; }
        h1 { color: #000000; text-align: center; margin-top: 20px; font-size: 28px; }
        .content { margin-left: 300px; padding: 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.5); margin-bottom: 20px; }
        .table thead th { background-color: #000000; color: #fff; }
        .table-striped tbody tr:nth-of-type(odd) { background-color: #f8f9fa; }
        .table-hover tbody tr:hover { background-color: #e9ecef; }
        .btn-primary { background-color: #0d6efd; border-color: #0d6efd; }
        .btn-warning { background-color: #ffc107; border-color: #ffc107; color: #000; }
        .btn-danger { background-color: #dc3545; border-color: #dc3545; }
        .btn-info { background-color: #17a2b8; border-color: #17a2b8; }
    </style>
</head>
<body>
    <?php include '../../../php/general/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">Role Management</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">Add New Role</button>

            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
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
                                        <button type="button" class="btn btn-sm btn-warning edit-role-btn" data-role-id="<?php echo $roleID; ?>" data-bs-toggle="modal" data-bs-target="#editRoleModal">Edit</button>
                                        <button type="button" class="btn btn-sm btn-danger delete-role-btn" data-role-id="<?php echo $roleID; ?>" data-role-name="<?php echo htmlspecialchars($role['Role_Name']); ?>" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">Delete</button>
                                        <a href="assign_privileges.php?id=<?php echo $roleID; ?>" class="btn btn-sm btn-info">Assign Privileges</a>
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
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Role & Privileges</h5>
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
                <div class="modal-body">Are you sure you want to delete the role "<span id="roleNamePlaceholder"></span>"?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Load edit role modal
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

            // Handle delete role modal
            $('#confirmDeleteModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget); 
                var roleID = button.data('role-id');
                var roleName = button.data('role-name');

                $('#roleNamePlaceholder').text(roleName);
                $('#confirmDeleteButton').attr('href', 'delete_role.php?id=' + roleID);
            });
        });
    </script>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
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
