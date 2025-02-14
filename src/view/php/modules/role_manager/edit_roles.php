<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access.']));
}

if (!isset($_GET['id'])) {
    die(json_encode(['success' => false, 'message' => 'Role ID not provided.']));
}

$roleID = $_GET['id'];

// Fetch role details
$stmt = $pdo->prepare("SELECT * FROM roles WHERE Role_ID = ?");
$stmt->execute([$roleID]);
$role = $stmt->fetch();

if (!$role) {
    die(json_encode(['success' => false, 'message' => 'Role not found.']));
}

// Fetch current privileges
$stmtCurrent = $pdo->prepare("SELECT Privilege_ID FROM role_privileges WHERE Role_ID = ?");
$stmtCurrent->execute([$roleID]);
$currentPrivileges = $stmtCurrent->fetchAll(PDO::FETCH_COLUMN);

// Fetch all privileges
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
    $moduleName = $priv['Module_Name'];
    if (!isset($groupedPrivileges[$moduleName])) {
        $groupedPrivileges[$moduleName] = [];
    }
    $groupedPrivileges[$moduleName][] = $priv;
}

// Handle form submission via AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']);
    $selectedPrivileges = $_POST['privileges'] ?? [];

    if (empty($roleName)) {
        echo json_encode(['success' => false, 'message' => 'Role name cannot be empty.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update role name
        $stmtUpdate = $pdo->prepare("UPDATE roles SET Role_Name = ? WHERE Role_ID = ?");
        $stmtUpdate->execute([$roleName, $roleID]);

        // Remove old privileges
        $stmtDelete = $pdo->prepare("DELETE FROM role_privileges WHERE Role_ID = ?");
        $stmtDelete->execute([$roleID]);

        // Insert new privileges
        $stmtInsert = $pdo->prepare("INSERT INTO role_privileges (Role_ID, Privilege_ID) VALUES (?, ?)");
        foreach ($selectedPrivileges as $privilegeID) {
            $stmtInsert->execute([$roleID, $privilegeID]);
        }

        $pdo->commit();

        // Fetch updated privileges for the response
        $stmtUpdatedPrivileges = $pdo->prepare("
            SELECT p.Privilege_Name, COALESCE(m.Module_Name, 'General') AS Module_Name
            FROM role_privileges rp
            JOIN privileges p ON rp.Privilege_ID = p.Privilege_ID
            LEFT JOIN modules m ON p.Module_ID = m.Module_ID
            WHERE rp.Role_ID = ?
        ");
        $stmtUpdatedPrivileges->execute([$roleID]);
        $updatedPrivileges = $stmtUpdatedPrivileges->fetchAll(PDO::FETCH_ASSOC);

        // Prepare response
        echo json_encode([
            'success' => true,
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

<!-- Role Edit Form -->
<form method="POST" id="editRoleForm">
    <div class="mb-3">
        <label for="role_name" class="form-label">Role Name:</label>
        <input type="text" class="form-control" id="role_name" name="role_name" value="<?php echo htmlspecialchars($role['Role_Name']); ?>" required>
    </div>

    <h5>Assign Privileges</h5>
    <?php foreach ($groupedPrivileges as $moduleName => $privileges): ?>
        <fieldset class="mb-3 border p-2">
            <legend class="fs-6"><?php echo htmlspecialchars($moduleName); ?></legend>
            <?php foreach ($privileges as $priv): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="privileges[]" value="<?php echo $priv['Privilege_ID']; ?>" 
                        <?php echo in_array($priv['Privilege_ID'], $currentPrivileges) ? 'checked' : ''; ?>>
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

                // Update role name
                row.find('.role-name').text(response.role_name);

                // Update the privileges display dynamically
                let privilegeCell = row.find('.privilege-list');
                privilegeCell.empty(); // Clear old privileges

                let groupedPrivileges = {};
                response.privileges.forEach(priv => {
                    if (!groupedPrivileges[priv.Module_Name]) {
                        groupedPrivileges[priv.Module_Name] = [];
                    }
                    groupedPrivileges[priv.Module_Name].push(priv.Privilege_Name);
                });

                // Append updated privileges
                for (let moduleName in groupedPrivileges) {
                    let privilegesHTML = `
                        <div>
                            <strong>${moduleName}</strong>: ${groupedPrivileges[moduleName].join(', ')}
                        </div>
                    `;
                    privilegeCell.append(privilegesHTML);
                }

                // Hide modal after successful update
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
