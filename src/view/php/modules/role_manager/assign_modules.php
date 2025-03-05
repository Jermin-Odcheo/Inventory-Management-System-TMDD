<?php
session_start();
require_once('..\..\..\..\..\config\ims-tmdd.php'); // Database connection

// Fetch all roles
try {
    $stmt = $pdo->prepare("SELECT id, Role_Name FROM roles ORDER BY Role_Name");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
// Handle role selection
$modules = [];
$selectedRoleId = isset($_POST['role_id']) ? $_POST['role_id'] : '';
//remove module from a role
if (isset($_POST['remove_module']) && isset($_POST['module_id'])) {
    try {
        $moduleId = $_POST['module_id'];

        // Delete module privileges for the role
        $deleteStmt = $pdo->prepare("
            DELETE FROM role_module_privileges 
            WHERE Role_ID = ? 
            AND Privilege_ID IN (SELECT Privilege_ID FROM privileges WHERE Module_ID = ?)
        ");
        $deleteStmt->execute([$selectedRoleId, $moduleId]);

        echo "<p style='color: green;'>Module removed successfully!</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
    }
}



if ($selectedRoleId) {
    try {
        // Fetch modules and privileges for the selected role
        $moduleStmt = $pdo->prepare("
            SELECT DISTINCT m.id, m.Module_Name 
            FROM modules AS m
            INNER JOIN privileges AS p ON m.id = p.id
            INNER JOIN role_module_privileges AS rp ON p.id = rp.Privilege_ID
            WHERE rp.Role_ID = ?
            ORDER BY m.Module_Name
        ");
        $moduleStmt->execute([$selectedRoleId]);
        $modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch existing privileges for the role
        $privilegeStmt = $pdo->prepare("
            SELECT p.id, p.priv_name 
            FROM privileges AS p
            INNER JOIN role_module_privileges AS rp ON p.id = rp.Privilege_ID
            WHERE rp.Role_ID = ?
        ");
        $privilegeStmt->execute([$selectedRoleId]);
        $rolePrivileges = $privilegeStmt->fetchAll(PDO::FETCH_ASSOC);

        // Organize privileges by Module_ID
        $privilegesByModule = [];
        foreach ($rolePrivileges as $priv) {
            $privilegesByModule[$priv['Module_ID']][] = $priv['Privilege_Name'];
        }

        // Fetch available modules (not already assigned)
        $availableModulesStmt = $pdo->prepare("
            SELECT m.id, m.Module_Name 
            FROM modules AS m
            WHERE m.id NOT IN (
                SELECT DISTINCT p.id 
                FROM privileges AS p
                INNER JOIN role_module_privileges AS rp ON p.id = rp.Privilege_ID
                WHERE rp.Role_ID = ?
            )
            ORDER BY m.Module_Name
        ");
        $availableModulesStmt->execute([$selectedRoleId]);
        $availableModules = $availableModulesStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Handle privilege updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_privileges'])) {
    try {
        foreach ($_POST['privileges'] as $moduleID => $privileges) {
            // Remove existing privileges for this role & module
            $deleteStmt = $pdo->prepare("
                DELETE FROM role_module_privileges 
                WHERE Role_ID = ? 
                AND Privilege_ID IN (SELECT Privilege_ID FROM privileges WHERE Module_ID = ?)
            ");
            $deleteStmt->execute([$selectedRoleId, $moduleID]);

            // Insert selected privileges
            foreach ($privileges as $privilegeName) {
                $privilegeStmt = $pdo->prepare("
                    SELECT id FROM privileges WHERE priv_name = ? AND id = ?
                ");
                $privilegeStmt->execute([$privilegeName, $moduleID]);
                $privilege = $privilegeStmt->fetch(PDO::FETCH_ASSOC);
                if (!$privilege) continue;

                // Insert new privilege
                $insertStmt = $pdo->prepare("
                    INSERT INTO role_module_privileges (Role_ID, Privilege_ID) 
                    VALUES (?, ?)
                ");
                $insertStmt->execute([$selectedRoleId, $privilege['Privilege_ID']]);
            }
        }
        echo "<p style='color: green;'>Privileges updated successfully!</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
    }
}

//add module
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_module'])) {
    $roleId = $_POST['role_id'];
    $moduleId = $_POST['module_id'];
    echo($roleId . " trying to add module " . $moduleId);
    try {
        $checkStmt = $pdo->prepare("
            SELECT * FROM role_module_privileges 
            WHERE Role_ID = ? 
            AND Privilege_ID IN (SELECT Privilege_ID FROM privileges WHERE Module_ID = ?)
        ");
        $checkStmt->execute([$roleId, $moduleId]);

        if ($checkStmt->rowCount() == 0) {
            $insertStmt = $pdo->prepare("
                INSERT INTO role_module_privileges (Role_ID, Privilege_ID) 
                SELECT ?, id FROM privileges WHERE id = ?
            ");
            $insertStmt->execute([$roleId, $moduleId]);
            echo("Module successfully assigned!");
        } 
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Role & Manage Privileges</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid black; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 40%; text-align: center; }
        .close { color: red; float: right; font-size: 28px; cursor: pointer; }
        .close:hover { color: darkred; }
    </style>
    <script>
        function openModal() {
            document.getElementById("assignModuleModal").style.display = "block";
        }
        function closeModal() {
            document.getElementById("assignModuleModal").style.display = "none";
        }

        function fetchPrivileges() {
            var moduleId = document.getElementById("new_module").value;
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "fetch_privileges.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById("privilegeCheckboxes").innerHTML = xhr.responseText;
                }
            };
            xhr.send("module_id=" + moduleId);
        }
    </script>
</head>
<body>

<h1>Select a Role</h1>
<form method="POST">
    <label for="role">Choose Role:</label>
    <select name="role_id" id="role" onchange="this.form.submit()">
        <option value="">-- Select Role --</option>
        <?php foreach ($roles as $role): ?>
            <option value="<?= $role['id'] ?>" <?= ($selectedRoleId == $role['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($role['Role_Name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selectedRoleId): ?>
    <h2>Modules & Privileges for Role</h2>
    <button type="button" onclick="openModal()">Assign Module</button>
    <form method="POST">
        <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
        <table>
            <tr>
                <th>Module Name</th>
                <th>Privileges</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($modules as $module): ?>
                <tr>
                    <td><?= htmlspecialchars($module['Module_Name']) ?></td>
                    <td>
                        <div class="checkbox-group">
                            <?php
                            $allPrivileges = ["View", "Add", "Edit", "Delete"];
                            foreach ($allPrivileges as $privilege) {
                                $checked = isset($privilegesByModule[$module['id']]) && in_array($privilege, $privilegesByModule[$module['id']]) ? "checked" : "";
                                echo "<label><input type='checkbox' name='privileges[{$module['id']}][]' value='{$privilege}' {$checked}> {$privilege}</label>";
                            }
                            ?>
                        </div>
                    </td>
                    <td>
                    <form method="POST">
                        <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                        <a>Module ID: <?= $module['id'] ?></a>
                        <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                        <button type="submit" name="remove_module">Remove</button>
                    </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <br>
        <button type="submit" name="update_privileges">Update Privileges</button>
    </form>


    <!-- Assign Module Modal -->
    <div id="assignModuleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Assign Module</h2>
            <form method="POST">
                <label for="new_module">Choose a Module:</label>
                <select name="new_module" id="new_module" onchange="fetchPrivileges()" required>
                    <option value="">-- Select Module --</option>
                    <?php foreach ($availableModules as $module): ?>
                        <option value="<?= $module['id'] ?>"><?= htmlspecialchars($module['Module_Name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <br><br>
                <div id="privilegeCheckboxes"></div>
                <br>
                <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                <button type="submit" name="assign_module">Assign</button>
            </form>
        </div>
    </div>

<?php endif; ?>

</body>
</html>
