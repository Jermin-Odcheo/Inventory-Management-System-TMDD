<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../../../../config/ims-tmdd.php');

// 1) Check session and role ID.
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access.']));
}
if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'Role ID not provided.']));
}
$roleID = $_GET['id'];

// 2) Fetch role details.
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$roleID]);
$role = $stmt->fetch();
if (!$role) {
    die(json_encode(['success' => false, 'message' => 'Role not found.']));
}

// 3) Fetch current role privileges (only the ones assigned to this role).
$stmtCurrent = $pdo->prepare("
    SELECT CONCAT(module_id, '|', privilege_id) AS combo
      FROM role_module_privileges
     WHERE role_id = ?
");
$stmtCurrent->execute([$roleID]);
$currentPrivileges = $stmtCurrent->fetchAll(PDO::FETCH_COLUMN);

// 4) Fetch all modules.
$stmtModules = $pdo->query("SELECT * FROM modules ORDER BY id");
$modules = $stmtModules->fetchAll(PDO::FETCH_ASSOC);

// (Optional) You may or may not need this if you only plan on displaying module-level privileges
// $stmtPrivileges = $pdo->query("SELECT * FROM privileges ORDER BY priv_name");
// $privileges = $stmtPrivileges->fetchAll(PDO::FETCH_ASSOC);

// 6) Handle POST updates (AJAX submission).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']);
    $selected = $_POST['privileges'] ?? [];

    if (empty($roleName)) {
        echo json_encode(['success' => false, 'message' => 'Role name cannot be empty.']);
        exit;
    }

    // Check if the new role name already exists for a different role.
    $stmtDuplicate = $pdo->prepare("SELECT id FROM roles WHERE role_name = ? AND id != ?");
    $stmtDuplicate->execute([$roleName, $roleID]);
    if ($stmtDuplicate->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Role name already exists.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update role name.
        $stmtUpdate = $pdo->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
        $stmtUpdate->execute([$roleName, $roleID]);

        // Remove old privileges for this role.
        $stmtDelete = $pdo->prepare("DELETE FROM role_module_privileges WHERE role_id = ?");
        $stmtDelete->execute([$roleID]);

        // Insert new privileges for this role.
        $stmtInsert = $pdo->prepare("
            INSERT INTO role_module_privileges (role_id, module_id, privilege_id)
            VALUES (?, ?, ?)
        ");
        foreach ($selected as $value) {
            list($moduleID, $privilegeID) = explode('|', $value);
            $moduleID = ($moduleID === '') ? null : $moduleID;
            $stmtInsert->execute([$roleID, $moduleID, $privilegeID]);
        }

        // Log changes in role_changes table.
        $stmtLog = $pdo->prepare("
            INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, NewRoleName, OldPrivileges, NewPrivileges)
            VALUES (?, ?, 'Modified', ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $_SESSION['user_id'],
            $roleID,
            $role['role_name'],
            $roleName,
            json_encode($currentPrivileges),
            json_encode($selected)
        ]);

        $pdo->commit();

        // Fetch updated privileges for display in the UI.
        $stmtUpdated = $pdo->prepare("
            SELECT m.module_name, p.priv_name 
              FROM role_module_privileges rp
              JOIN modules m ON rp.module_id = m.id
              JOIN privileges p ON rp.privilege_id = p.id
             WHERE rp.role_id = ?
        ");
        $stmtUpdated->execute([$roleID]);
        $updatedPrivileges = $stmtUpdated->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Role updated successfully.',
            'role_id' => $roleID,
            'role_name' => $roleName,
            'privileges' => $updatedPrivileges
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating role: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!-- Simplified Modern Edit Role Modal -->
<div class="modal-content border-0 shadow-lg rounded-4">
    <div class="modal-header bg-primary py-3 px-4 border-0">
        <h5 class="modal-title text-white m-0 d-flex align-items-center">
            <i class="bi bi-shield-lock me-2"></i>Edit Role
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body p-4">
        <form id="editRoleForm" method="POST">
            <!-- Role name input -->
            <div class="form-floating mb-4">
                <input type="text"
                       class="form-control"
                       id="role_name"
                       name="role_name"
                       placeholder="Role Name"
                       value="<?php echo htmlspecialchars($role['role_name']); ?>"
                       required>
                <label for="role_name">Role Name</label>
            </div>

            <!-- Permissions section -->
            <h6 class="mb-3">Permissions</h6>

            <!-- Module Navigation Tabs -->
            <ul class="nav nav-tabs mb-3" id="modulesTabs" role="tablist">
                <?php foreach ($modules as $index => $mod): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($index === 0) ? 'active' : ''; ?>"
                                id="module-<?php echo $mod['id']; ?>-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#module-<?php echo $mod['id']; ?>"
                                type="button"
                                role="tab">
                            <?php echo htmlspecialchars($mod['module_name']); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="modulesContent">
                <?php foreach ($modules as $index => $mod): ?>
                    <div class="tab-pane fade <?php echo ($index === 0) ? 'show active' : ''; ?>"
                         id="module-<?php echo $mod['id']; ?>"
                         role="tabpanel">
                        <div class="card shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center py-2">
                                <span><?php echo htmlspecialchars($mod['module_name']); ?></span>
                                <span class="badge bg-light text-primary">Module</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php
                                    // Only retrieve privileges that have been assigned to this module at module-level (role_id = 0).
                                    $moduleAssignedStmt = $pdo->prepare("
                                        SELECT p.id, p.priv_name
                                          FROM role_module_privileges rmp
                                          JOIN privileges p ON p.id = rmp.privilege_id
                                         WHERE rmp.module_id = :moduleId
                                           AND rmp.role_id = 0
                                    ");
                                    $moduleAssignedStmt->execute([':moduleId' => $mod['id']]);
                                    $moduleAssignedPrivileges = $moduleAssignedStmt->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($moduleAssignedPrivileges as $priv):
                                        $value = $mod['id'] . '|' . $priv['id'];

                                        // Optional: Map priv_name to an icon
                                        $iconClass = match(strtolower($priv['priv_name'])) {
                                            'view'   => 'bi-eye',
                                            'create' => 'bi-plus-circle',
                                            'edit'   => 'bi-pencil',
                                            'delete' => 'bi-trash',
                                            'track'  => 'bi-graph-up',
                                            default  => 'bi-shield-check',
                                        };
                                        ?>
                                        <div class="col-md-4 col-lg-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       role="switch"
                                                       name="privileges[]"
                                                       value="<?php echo $value; ?>"
                                                       id="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>"
                                                    <?php echo in_array($value, $currentPrivileges) ? 'checked' : ''; ?>>
                                                <label class="form-check-label"
                                                       for="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>">
                                                    <i class="bi <?php echo $iconClass; ?> me-1"></i>
                                                    <?php echo htmlspecialchars($priv['priv_name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div><!-- /.tab-pane -->
                <?php endforeach; ?>
            </div><!-- /.tab-content -->
        </form>
    </div>

    <!-- Footer with actions -->
    <div class="modal-footer border-top py-3">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="editRoleForm" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i>Update Role
        </button>
    </div>
</div>

<!-- Add this to your CSS -->
<style>
    .nav-tabs .nav-link {
        color: #495057;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom-color: #0d6efd;
        border-bottom-width: 2px;
    }
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
</style>

<script>
    // Handle the Edit Role form submission via AJAX.
    $(document).off('submit', '#editRoleForm').on('submit', '#editRoleForm', function (e) {
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
                    // Update table row with new role name and privileges
                    const row = $('tr[data-role-id="' + response.role_id + '"]');
                    row.find('.role-name').text(response.role_name);

                    const privilegeCell = row.find('.privilege-list');
                    privilegeCell.empty();

                    // Group privileges by module name for display
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

                    submitBtn.blur();
                    // Hide the modal using Bootstrap’s modal method.
                    $('#editRoleModal').modal('hide');

                    // Reload roles table and show toast message
                    $('#rolesTable').load(location.href + ' #rolesTable', function () {
                        updatePagination();
                        showToast(response.message, 'success');
                    });
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

    // Remove lingering modal backdrop when any modal is hidden.
    $('#editRoleModal, #addRoleModal, #confirmDeleteModal').on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    });
</script>
