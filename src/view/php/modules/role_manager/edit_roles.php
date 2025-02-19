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
$stmt = $pdo->prepare("SELECT * FROM roles WHERE Role_ID = ?");
$stmt->execute([$roleID]);
$role = $stmt->fetch();
if (!$role) {
    die(json_encode(['success' => false, 'message' => 'Role not found.']));
}

// 3) Fetch current privileges.
$stmtCurrent = $pdo->prepare("SELECT Privilege_ID FROM role_privileges WHERE Role_ID = ?");
$stmtCurrent->execute([$roleID]);
$currentPrivileges = $stmtCurrent->fetchAll(PDO::FETCH_COLUMN);

// 4) Fetch all privileges and group them by module.
$sql = "
    SELECT p.Privilege_ID, p.Privilege_Name, COALESCE(m.Module_Name, 'General') AS Module_Name
    FROM privileges p
    LEFT JOIN modules m ON p.Module_ID = m.Module_ID
    ORDER BY Module_Name, p.Privilege_Name
";
$stmtPrivileges = $pdo->query($sql);
$allPrivileges = $stmtPrivileges->fetchAll(PDO::FETCH_ASSOC);

$groupedPrivileges = [];
foreach ($allPrivileges as $priv) {
    $groupedPrivileges[$priv['Module_Name']][] = $priv;
}

// 5) Handle POST updates (AJAX).
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']);
    $selectedPrivileges = $_POST['privileges'] ?? [];

    if (empty($roleName)) {
        echo json_encode(['success' => false, 'message' => 'Role name cannot be empty.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update role name.
        $stmtUpdate = $pdo->prepare("UPDATE roles SET Role_Name = ? WHERE Role_ID = ?");
        $stmtUpdate->execute([$roleName, $roleID]);

        // Remove old privileges.
        $stmtDelete = $pdo->prepare("DELETE FROM role_privileges WHERE Role_ID = ?");
        $stmtDelete->execute([$roleID]);

        // Insert new privileges.
        $stmtInsert = $pdo->prepare("INSERT INTO role_privileges (Role_ID, Privilege_ID) VALUES (?, ?)");
        foreach ($selectedPrivileges as $privilegeID) {
            $stmtInsert->execute([$roleID, $privilegeID]);
        }

        $pdo->commit();

        // Fetch updated privileges to return in JSON.
        $stmtUpdatedPrivileges = $pdo->prepare("
            SELECT p.Privilege_Name, COALESCE(m.Module_Name, 'General') AS Module_Name
            FROM role_privileges rp
            JOIN privileges p ON rp.Privilege_ID = p.Privilege_ID
            LEFT JOIN modules m ON p.Module_ID = m.Module_ID
            WHERE rp.Role_ID = ?
        ");
        $stmtUpdatedPrivileges->execute([$roleID]);
        $updatedPrivileges = $stmtUpdatedPrivileges->fetchAll(PDO::FETCH_ASSOC);

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

<!--
    ONLY the inner structure for your existing modal in manage_roles.php.
    Do not include another <div class="modal"> or <div class="modal-dialog"> or <div class="modal-content">.
-->

<div class="modal-header">
    <h5 class="modal-title">Edit Role</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <form id="editRoleForm" method="POST">
        <!-- Role Name -->
        <div class="mb-3">
            <label for="role_name" class="form-label">Role Name</label>
            <input type="text" class="form-control" id="role_name" name="role_name"
                   value="<?php echo htmlspecialchars($role['Role_Name']); ?>" required>
        </div>

        <!-- Grouped Privileges -->
        <?php foreach ($groupedPrivileges as $moduleName => $privileges): ?>
            <div class="mb-2">
                <div class="small fw-bold text-secondary"><?php echo htmlspecialchars($moduleName); ?></div>
                <div class="d-flex flex-wrap">
                    <?php foreach ($privileges as $priv): ?>
                        <div class="form-check form-check-inline me-2">
                            <input class="form-check-input" type="checkbox"
                                   name="privileges[]"
                                   value="<?php echo $priv['Privilege_ID']; ?>"
                                   id="priv_<?php echo $priv['Privilege_ID']; ?>"
                                <?php echo in_array($priv['Privilege_ID'], $currentPrivileges) ? 'checked' : ''; ?>>
                            <label class="form-check-label small" for="priv_<?php echo $priv['Privilege_ID']; ?>">
                                <?php echo htmlspecialchars($priv['Privilege_Name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
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
        let formData = $(this).serialize();
        $.ajax({
            url: 'edit_roles.php?id=<?php echo $roleID; ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Role updated successfully.');
                    // Update the table row in manage_roles.php
                    let row = $('tr[data-role-id="' + response.role_id + '"]');
                    row.find('.role-name').text(response.role_name);

                    // Rebuild the privilege list
                    let privilegeCell = row.find('.privilege-list').empty();
                    let grouped = {};
                    response.privileges.forEach(function(priv) {
                        if (!grouped[priv.Module_Name]) {
                            grouped[priv.Module_Name] = [];
                        }
                        grouped[priv.Module_Name].push(priv.Privilege_Name);
                    });
                    for (let module in grouped) {
                        privilegeCell.append(
                            '<div><strong>' + module + '</strong>: ' + grouped[module].join(', ') + '</div>'
                        );
                    }
                    $('#editRoleModal').modal('hide');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Error processing request.');
            }
        });
    });
</script>
