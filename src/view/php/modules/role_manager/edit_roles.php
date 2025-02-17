<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Only allow logged-in users.
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access.']));
}

if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'Role ID not provided.']));
}

$roleID = $_GET['id'];

// Fetch role details.
$stmt = $pdo->prepare("SELECT * FROM roles WHERE Role_ID = ?");
$stmt->execute([$roleID]);
$role = $stmt->fetch();

if (!$role) {
    die(json_encode(['success' => false, 'message' => 'Role not found.']));
}

// Fetch current privileges for this role.
// Each row in role_privileges associates a Module_ID with a comma‑separated list of privilege IDs.
$stmtCurrent = $pdo->prepare("SELECT Module_ID, Privilege_ID FROM role_privileges WHERE Role_ID = ?");
$stmtCurrent->execute([$roleID]);
$currentPrivilegesRaw = $stmtCurrent->fetchAll(PDO::FETCH_ASSOC);

$currentPrivileges = [];  // e.g. $currentPrivileges[module_id] = array('2','5')
foreach ($currentPrivilegesRaw as $row) {
    $moduleID = $row['Module_ID'];
    $ids = array_map('trim', explode(',', $row['Privilege_ID']));
    $currentPrivileges[$moduleID] = $ids;
}

// Fetch all modules.
$stmtModules = $pdo->query("SELECT * FROM modules ORDER BY Module_Name");
$modules = $stmtModules->fetchAll(PDO::FETCH_ASSOC);

// Fetch all privileges.
$stmtPrivileges = $pdo->query("SELECT * FROM privileges ORDER BY Privilege_Name");
$allPrivileges = $stmtPrivileges->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission via AJAX.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']);
    // Expected: $_POST['privileges'] is an array with keys = Module_ID, values = array of privilege IDs.
    $selectedPrivileges = $_POST['privileges'] ?? [];

    if (empty($roleName)) {
        echo json_encode(['success' => false, 'message' => 'Role name cannot be empty.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update the role name.
        $stmtUpdate = $pdo->prepare("UPDATE roles SET Role_Name = ? WHERE Role_ID = ?");
        $stmtUpdate->execute([$roleName, $roleID]);

        // Delete old privileges for this role.
        $stmtDelete = $pdo->prepare("DELETE FROM role_privileges WHERE Role_ID = ?");
        $stmtDelete->execute([$roleID]);

        // Insert new privileges grouped by module.
        $stmtInsert = $pdo->prepare("INSERT INTO role_privileges (Role_ID, Module_ID, Privilege_ID) VALUES (?, ?, ?)");
        // Loop through each module (even if no privileges are selected, you may choose to skip inserting a row).
        foreach ($selectedPrivileges as $moduleID => $privArray) {
            if (!empty($privArray)) {
                // Create a comma-separated list of privilege IDs.
                $privilegeStr = implode(',', array_map('trim', $privArray));
                $stmtInsert->execute([$roleID, $moduleID, $privilegeStr]);
            }
        }

        $pdo->commit();

        // Now, fetch the updated privileges for the response.
        $stmtUpdated = $pdo->prepare("SELECT Module_ID, Privilege_ID FROM role_privileges WHERE Role_ID = ?");
        $stmtUpdated->execute([$roleID]);
        $updatedPrivilegesRaw = $stmtUpdated->fetchAll(PDO::FETCH_ASSOC);

        // Build a list of updated privileges (grouped by module name) for the AJAX response.
        $updatedPrivileges = [];
        foreach ($updatedPrivilegesRaw as $row) {
            $moduleID = $row['Module_ID'];
            // Get the module name.
            $moduleName = 'General';
            foreach ($modules as $mod) {
                if ($mod['Module_ID'] == $moduleID) {
                    $moduleName = $mod['Module_Name'];
                    break;
                }
            }
            $privIDs = array_map('trim', explode(',', $row['Privilege_ID']));
            foreach ($privIDs as $pid) {
                foreach ($allPrivileges as $priv) {
                    if ($priv['Privilege_ID'] == $pid) {
                        $updatedPrivileges[] = [
                            'Module_Name'    => $moduleName,
                            'Privilege_Name' => $priv['Privilege_Name']
                        ];
                        break;
                    }
                }
            }
        }

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

<!-- Role Edit Form -->
<form method="POST" id="editRoleForm">
    <div class="mb-3">
        <label for="role_name" class="form-label">Role Name:</label>
        <input type="text" class="form-control" id="role_name" name="role_name" value="<?php echo htmlspecialchars($role['Role_Name']); ?>" required>
    </div>

    <h5>Assign Privileges</h5>
    <?php foreach ($modules as $module):
        $moduleID = $module['Module_ID'];
        $moduleName = $module['Module_Name'];
        ?>
        <fieldset class="mb-3 border p-2">
            <legend class="fs-6"><?php echo htmlspecialchars($moduleName); ?></legend>
            <?php foreach ($allPrivileges as $priv):
                // Check if this privilege is currently assigned for this module.
                $checked = '';
                if (isset($currentPrivileges[$moduleID]) && in_array($priv['Privilege_ID'], $currentPrivileges[$moduleID])) {
                    $checked = 'checked';
                }
                ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="privileges[<?php echo $moduleID; ?>][]" value="<?php echo $priv['Privilege_ID']; ?>" <?php echo $checked; ?>>
                    <label class="form-check-label">
                        <?php echo htmlspecialchars($priv['Privilege_Name']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Update Role</button>
</form>

<script>
    $('#editRoleForm').on('submit', function(event) {
        event.preventDefault();
        let formData = $(this).serialize();

        $.ajax({
            url: 'edit_roles.php?id=<?php echo $roleID; ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Role updated successfully.');

                    let row = $('tr[data-role-id="' + response.role_id + '"]');

                    // Update role name in the table.
                    row.find('.role-name').text(response.role_name);

                    // Update the privileges display dynamically.
                    let privilegeCell = row.find('.privilege-list');
                    privilegeCell.empty(); // Clear old privileges.

                    let groupedPrivileges = {};
                    response.privileges.forEach(function(priv) {
                        if (!groupedPrivileges[priv.Module_Name]) {
                            groupedPrivileges[priv.Module_Name] = [];
                        }
                        groupedPrivileges[priv.Module_Name].push(priv.Privilege_Name);
                    });

                    // Append updated privileges.
                    for (let moduleName in groupedPrivileges) {
                        let privilegesHTML = `
                        <div>
                            <strong>${moduleName}</strong>: ${groupedPrivileges[moduleName].join(', ')}
                        </div>
                    `;
                        privilegeCell.append(privilegesHTML);
                    }

                    // Hide the modal (if using one) after successful update.
                    $('#editRoleModal').modal('hide');
                } else {
                    alert('Error updating role: ' + response.message);
                }
            },
            error: function() {
                alert('Error processing request.');
            }
        });
    });
</script>
