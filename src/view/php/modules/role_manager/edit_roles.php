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
// We encode each current combination as "module_id|privilege_id" for easy checking in the form.
$stmtCurrent = $pdo->prepare("SELECT CONCAT(module_id, '|', privilege_id) AS combo FROM role_module_privileges WHERE role_id = ?");
$stmtCurrent->execute([$roleID]);
$currentPrivileges = $stmtCurrent->fetchAll(PDO::FETCH_COLUMN);

// 4) Fetch all modules.
// We expect these to be exactly the four modules:
// 1. User Management, 2. Audit, 3. Roles and Privileges, 4. Equipment Management.
$stmtModules = $pdo->query("SELECT * FROM modules ORDER BY id");
$modules = $stmtModules->fetchAll(PDO::FETCH_ASSOC);

// 5) Fetch all privileges.
$stmtPrivileges = $pdo->query("SELECT * FROM privileges ORDER BY priv_name");
$privileges = $stmtPrivileges->fetchAll(PDO::FETCH_ASSOC);

// 6) Handle POST updates (AJAX submission).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']);
    // Expect each checkbox value as "module_id|privilege_id"
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
            // Ensure moduleID is valid; if empty (shouldn't happen), set to NULL.
            $moduleID = ($moduleID === '') ? NULL : $moduleID;
            $stmtInsert->execute([$roleID, $moduleID, $privilegeID]);
        }

        // (Optional) Log changes in role_changes table.
        $stmtLog = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, NewRoleName, OldPrivileges, NewPrivileges) VALUES (?, ?, 'Modified', ?, ?, ?, ?)");
        // For logging, we store the raw arrays.
        $stmtLog->execute([
            $_SESSION['user_id'],
            $roleID,
            $role['role_name'], // old role name
            $roleName,          // new role name
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
            'success'    => true,
            'role_id'    => $roleID,
            'role_name'  => $roleName,
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

<!-- The HTML below is the inner structure for your modal in manage_roles.php -->

<div class="modal-header">
    <h5 class="modal-title">Edit Role</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <form id="editRoleForm" method="POST">
        <!-- Role Name -->
        <div class="mb-3">
            <label for="role_name" class="form-label">Role Name</label>
            <input type="text" class="form-control" id="role_name" name="role_name" value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
        </div>

        <!-- For each module, list privileges as checkboxes.
             For the Audit module, only display the "Track" privilege. -->
        <?php foreach ($modules as $mod): ?>
            <div class="mb-2">
                <div class="small fw-bold text-secondary"><?php echo htmlspecialchars($mod['module_name']); ?></div>
                <div class="d-flex flex-wrap">
                    <?php if (strtolower($mod['module_name']) === 'audit'): ?>
                        <?php
                        // Only show the privilege "Track" for Audit.
                        foreach ($privileges as $priv):
                            if (strtolower($priv['priv_name']) === 'track'):
                                $value = $mod['id'] . '|' . $priv['id'];
                                ?>
                                <div class="form-check form-check-inline me-2">
                                    <input class="form-check-input" type="checkbox" name="privileges[]" value="<?php echo $value; ?>" id="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>"
                                        <?php echo in_array($value, $currentPrivileges) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>">
                                        <?php echo htmlspecialchars($priv['priv_name']); ?>
                                    </label>
                                </div>
                            <?php endif;
                        endforeach; ?>
                    <?php else: ?>
                        <?php
                        // For other modules, display all privileges.
                        foreach ($privileges as $priv):
                            $value = $mod['id'] . '|' . $priv['id'];
                            ?>
                            <div class="form-check form-check-inline me-2">
                                <input class="form-check-input" type="checkbox" name="privileges[]" value="<?php echo $value; ?>" id="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>"
                                    <?php echo in_array($value, $currentPrivileges) ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="priv_<?php echo $mod['id'] . '_' . $priv['id']; ?>">
                                    <?php echo htmlspecialchars($priv['priv_name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </form>
</div>

<div class="modal-footer">
    <button type="submit" form="editRoleForm" class="btn btn-primary">Update Role</button>
</div>

<script>
    // Handle AJAX form submission.
    $('#editRoleForm').on('submit', function(e) {
        e.preventDefault();
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);

        $.ajax({
            url: 'edit_roles.php?id=<?php echo $roleID; ?>',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Optionally update the role row display in manage_roles.php.
                    const row = $('tr[data-role-id="' + response.role_id + '"]');
                    row.find('.role-name').text(response.role_name);
                    const privilegeCell = row.find('.privilege-list');
                    privilegeCell.empty();

                    // Group updated privileges by module.
                    const grouped = {};
                    response.privileges.forEach(function(item) {
                        if (!grouped[item.module_name]) {
                            grouped[item.module_name] = [];
                        }
                        grouped[item.module_name].push(item.priv_name);
                    });
                    Object.keys(grouped).forEach(function(moduleName) {
                        privilegeCell.append(
                            $('<div>').html('<strong>' + moduleName + '</strong>: ' + grouped[moduleName].join(', '))
                        );
                    });

                    $('#editRoleModal').modal('hide');
                    showAlert('success', 'Role updated successfully!');
                } else {
                    showAlert('danger', 'Error: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                showAlert('danger', 'Error processing request.');
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });

    // Optional: Global AJAX error logging.
    $(document).ajaxError(function(event, xhr, settings, error) {
        console.group('AJAX Error Details');
        console.log('Event:', event);
        console.log('XHR:', xhr);
        console.log('Settings:', settings);
        console.log('Error:', error);
        console.log('Response Text:', xhr.responseText);
        console.groupEnd();
    });
</script>
