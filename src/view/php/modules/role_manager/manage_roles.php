<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';

// Updated SQL join conditions using the correct key for modules.
$sql = "
SELECT 
    r.id AS Role_ID,
    r.role_name AS Role_Name,
    m.id AS Module_ID,
    m.module_name AS Module_Name,
    COALESCE((
      SELECT GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ')
      FROM role_module_privileges rmp2
      JOIN privileges p ON p.id = rmp2.privilege_id
      WHERE rmp2.role_id = r.id
        AND rmp2.module_id = m.id
    ), 'No privileges') AS Privileges
FROM roles r
CROSS JOIN modules m
ORDER BY r.id, m.id;
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
    // Use the correct alias "Privileges" from your query
    if (!empty($row['Privileges'])) {
        $roles[$roleID]['Modules'][$moduleName][] = $row['Privileges'];
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
    <!-- Ensure toast.js is loaded on the page -->

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
        .main-content.container-fluid {
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

        <div class="d-flex justify-content-end mb-3">
            <!-- Add Undo and Redo buttons here -->
            <button type="button" class="btn btn-secondary me-2" id="undoButton">Undo</button>
            <button type="button" class="btn btn-secondary" id="redoButton">Redo</button>
            <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                Create New Role
            </button>
        </div>

        <div class="table-responsive" id="table">
            <table id="rolesTable" class="table table-striped table-hover">
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
            <!-- Pagination Controls (optional) -->
            <div class="container-fluid">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-sm-auto">
                        <div class="text-muted">
                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                    id="totalRows">100</span> entries
                        </div>
                    </div>
                    <div class="col-12 col-sm-auto ms-sm-auto">
                        <div class="d-flex align-items-center gap-2">
                            <button id="prevPage"
                                    class="btn btn-outline-primary d-flex align-items-center gap-1">
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                            <button id="nextPage"
                                    class="btn btn-outline-primary d-flex align-items-center gap-1">
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <ul class="pagination justify-content-center" id="pagination"></ul>
                    </div>
                </div>
            </div>
        </div><!-- /.table-responsive -->

    </div><!-- /.main-content -->
</div><!-- /.wrapper -->

<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

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
    $(document).ready(function() {
        // 1. Load edit role modal content via AJAX.
        $('.edit-role-btn').on('click', function() {
            var roleID = $(this).data('role-id');
            $('#editRoleContent').html("Loading...");
            $.ajax({
                url: 'edit_roles.php',
                type: 'GET',
                data: { id: roleID },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    $('#editRoleContent').html(response);
                    // Ensure the modal form has a hidden input with id="roleID"
                    $('#roleID').val(roleID);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    $('#editRoleContent').html('<p class="text-danger">Error loading role data. Please try again.</p>');
                }
            });
        });

        // 2. Handle delete role modal: store role info when modal is shown.
        $('#confirmDeleteModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var roleID = button.data('role-id');
            var roleName = button.data('role-name');
            $('#roleNamePlaceholder').text(roleName);
            // Store roleID on the confirm button.
            $('#confirmDeleteButton').data('role-id', roleID);
        });

        // 3. Confirm delete role using event delegation.
        $(document).on('click', '#confirmDeleteButton', function(e) {
            e.preventDefault();
            $(this).blur(); // Remove focus to avoid ARIA issues.
            var roleID = $(this).data('role-id');
            $.ajax({
                type: "POST",
                url: "delete_role.php",
                data: { id: roleID },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#rolesTable').load(location.href + ' #rolesTable', function() {
                            showToast(response.message, 'success');
                        });
                        $('#confirmDeleteModal').modal('hide');
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function() {
                    showToast('Error deleting role.', 'error');
                }
            });
        });

        // 4. Load add role modal content via AJAX.
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

        // 5. Handle "Add Role" form submission via AJAX.
        $(document).on('submit', '#addRoleForm', function (e) {
            e.preventDefault();
            var actionUrl = $(this).attr('action');
            $.ajax({
                url: actionUrl,
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Reload the table and then show the toast.
                        $('#rolesTable').load(location.href + ' #rolesTable', function() {
                            showToast(response.message, 'success');
                        });
                        // Optionally, reset the form.
                        $('#addRoleForm')[0].reset();
                    } else {
                        showToast(response.message, 'error');
                    }
                },
            });
        });

        // 6. Handle "Edit Role" form submission via AJAX.
        $(document).on('submit', '#editRoleForm', function (e) {
            e.preventDefault();
            const submitBtn = $('button[type="submit"]', this);
            // Disable button and show loading state
            submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span> Updating...');
            submitBtn.prop('disabled', true);
            $.ajax({
                url: 'edit_roles.php?id=<?php echo $roleID; ?>',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Update table row with new role name
                        const row = $('tr[data-role-id="' + response.role_id + '"]');
                        row.find('.role-name').text(response.role_name);
                        // Update the modules & privileges cell
                        const privilegeCell = row.find('.privilege-list');
                        privilegeCell.empty();
                        if (response.privileges.length === 0) {
                            privilegeCell.html('<em>No privileges</em>');
                        } else {
                            // Group privileges by module name
                            const grouped = {};
                            response.privileges.forEach(function (item) {
                                if (!grouped[item.module_name]) {
                                    grouped[item.module_name] = [];
                                }
                                grouped[item.module_name].push(item.priv_name);
                            });
                            Object.keys(grouped).forEach(function (moduleName) {
                                privilegeCell.append(
                                    $('<div class="mb-1">').html('<b>' + moduleName + ':</b> ' + grouped[moduleName].join(', '))
                                );
                            });
                        }
                    } else {
                        showToast(response.message || 'An error occurred while updating the role', 'error');
                    }
                },
                error: function () {
                    showToast('System error occurred. Please try again.', 'error');
                },
                complete: function () {
                    // Restore button state.
                    submitBtn.html('<i class="bi bi-check2 me-1"></i>Update Role');
                    submitBtn.prop('disabled', false);
                }
            });
        });

        // 7. Undo button click handler.
        $('#undoButton').on('click', function() {
            $.ajax({
                url: 'undo.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#rolesTable').load(location.href + ' #rolesTable', function() {
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function() {
                    showToast('Error processing undo request.', 'error');
                }
            });
        });

        // 8. Redo button click handler.
        $('#redoButton').on('click', function() {
            $.ajax({
                url: 'redo.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#rolesTable').load(location.href + ' #rolesTable', function() {
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message, 'error');
                    }
                },
                error: function() {
                    showToast('Error processing redo request.', 'error');
                }
            });
        });
    });
</script>

<?php include '../../general/footer.php'; ?>
</body>
</html>
