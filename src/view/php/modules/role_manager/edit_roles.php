<?php
session_start();
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

// 3) Fetch current role privileges.
$stmtCurrent = $pdo->prepare("SELECT CONCAT(module_id, '|', privilege_id) AS combo FROM role_module_privileges WHERE role_id = ?");
$stmtCurrent->execute([$roleID]);
$currentPrivileges = $stmtCurrent->fetchAll(PDO::FETCH_COLUMN);

// 4) Fetch all modules.
$stmtModules = $pdo->query("SELECT * FROM modules ORDER BY id");
$modules = $stmtModules->fetchAll(PDO::FETCH_ASSOC);

// 5) Fetch all privileges.
$stmtPrivileges = $pdo->query("SELECT * FROM privileges ORDER BY priv_name");
$privileges = $stmtPrivileges->fetchAll(PDO::FETCH_ASSOC);

// 6) Handle POST updates (AJAX submission).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']);
    $selected = $_POST['privileges'] ?? [];

    if (empty($roleName)) {
        echo json_encode(['success' => false, 'message' => 'Role name cannot be empty.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update role name.
        $stmtUpdate = $pdo->prepare("UPDATE roles SET role_name = ? WHERE id = ?");
        $stmtUpdate->execute([$roleName, $roleID]);

        // Remove old privileges.
        $stmtDelete = $pdo->prepare("DELETE FROM role_module_privileges WHERE role_id = ?");
        $stmtDelete->execute([$roleID]);

        // Insert new privileges.
        $stmtInsert = $pdo->prepare("INSERT INTO role_module_privileges (role_id, module_id, privilege_id) VALUES (?, ?, ?)");
        foreach ($selected as $value) {
            list($moduleID, $privilegeID) = explode('|', $value);
            $moduleID = ($moduleID === '') ? NULL : $moduleID;
            $stmtInsert->execute([$roleID, $moduleID, $privilegeID]);
        }

        // Log changes in role_changes table.
        $stmtLog = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, NewRoleName, OldPrivileges, NewPrivileges) VALUES (?, ?, 'Modified', ?, ?, ?, ?)");
        $stmtLog->execute([
            $_SESSION['user_id'],
            $roleID,
            $role['role_name'],
            $roleName,
            json_encode($currentPrivileges),
            json_encode($selected)
        ]);

        $pdo->commit();

        // Fetch updated privileges for display.
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
        echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!-- Simplified Modern Edit Role Modal -->
<div class="modal-content border-0 shadow-lg rounded-4">
    <!-- Clean header with minimal design -->
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
                <input type="text" class="form-control"
                       id="role_name" name="role_name" placeholder="Role Name"
                       value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
                <label for="role_name">Role Name</label>
            </div>

            <!-- Permissions section -->
            <h6 class="mb-3">Permissions</h6>

            <!-- Module Navigation Tabs - Simplified -->
            <ul class="nav nav-tabs mb-3" id="modulesTabs" role="tablist">
                <?php foreach ($modules as $index => $mod): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($index === 0) ? 'active' : ''; ?>"
                                id="module-<?php echo $mod['id']; ?>-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#module-<?php echo $mod['id']; ?>"
                                type="button" role="tab">
                            <?php echo htmlspecialchars($mod['module_name']); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Tab Content - Simplified -->
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
                                    // Determine which privileges to show
                                    $showPrivileges = strtolower($mod['module_name']) === 'audit' ?
                                        array_filter($privileges, function ($p) {
                                            return strtolower($p['priv_name']) === 'track';
                                        }) :
                                        $privileges;

                                    foreach ($showPrivileges as $priv):
                                        if (strtolower($mod['module_name']) !== 'audit' || strtolower($priv['priv_name']) === 'track'):
                                            $value = $mod['id'] . '|' . $priv['id'];

                                            // Simplified icon selection
                                            $iconClass = '';
                                            switch (strtolower($priv['priv_name'])) {
                                                case 'view':
                                                    $iconClass = 'bi-eye';
                                                    break;
                                                case 'create':
                                                    $iconClass = 'bi-plus-circle';
                                                    break;
                                                case 'edit':
                                                    $iconClass = 'bi-pencil';
                                                    break;
                                                case 'delete':
                                                    $iconClass = 'bi-trash';
                                                    break;
                                                case 'track':
                                                    $iconClass = 'bi-graph-up';
                                                    break;
                                                default:
                                                    $iconClass = 'bi-shield-check';
                                            }
                                            ?>
                                            <div class="col-md-4 col-lg-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch"
                                                           name="privileges[]" value="<?php echo $value; ?>"
                                                           id="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>"
                                                        <?php echo in_array($value, $currentPrivileges) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label"
                                                           for="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>">
                                                        <i class="bi <?php echo $iconClass; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($priv['priv_name']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
    $('#editRoleForm').on('submit', function (e) {
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
                    // Update table row and close modal
                    const row = $('tr[data-role-id="' + response.role_id + '"]');
                    row.find('.role-name').text(response.role_name);

                    // Update privileges display
                    const privilegeCell = row.find('.privilege-list');
                    privilegeCell.empty();

                    // Group by module
                    const grouped = {};
                    response.privileges.forEach(function (item) {
                        if (!grouped[item.module_name]) grouped[item.module_name] = [];
                        grouped[item.module_name].push(item.priv_name);
                    });

                    Object.keys(grouped).forEach(function (moduleName) {
                        privilegeCell.append(
                            $('<div class="mb-1">').html('<b>' + moduleName + ':</b> ' + grouped[moduleName].join(', '))
                        );
                    });

                    showToast(response.message, 'success');
                } else {
                    showToast(response.message || 'An error occurred while updating the role', 'error');
                }
            },
            error: function () {
                showToast('System error occurred. Please try again.', 'error');
            },
            complete: function () {
                // Restore button state
                submitBtn.html('<i class="bi bi-check2 me-1"></i>Update Role');
                submitBtn.prop('disabled', false);
            }
        });
    });
</script>
