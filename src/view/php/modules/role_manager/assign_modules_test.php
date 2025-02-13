<?php
session_start();
require_once('..\..\..\..\..\config\ims-tmdd.php'); // Database connection

// Fetch all roles
try {
    $stmt = $pdo->prepare("SELECT Role_ID, Role_Name FROM roles ORDER BY Role_Name");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle role selection
$modules = [];
$selectedRoleId = isset($_POST['role_id']) ? $_POST['role_id'] : '';

if ($selectedRoleId) {
    try {
        // Fetch assigned modules for the selected role
        $moduleStmt = $pdo->prepare("
            SELECT DISTINCT m.Module_ID, m.Module_Name 
            FROM modules AS m
            INNER JOIN privileges AS p ON m.Module_ID = p.Module_ID
            INNER JOIN role_privileges AS rp ON p.Privilege_ID = rp.Privilege_ID
            WHERE rp.Role_ID = ?
            ORDER BY m.Module_Name
        ");
        $moduleStmt->execute([$selectedRoleId]);
        $modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch existing privileges for the role
        $privilegeStmt = $pdo->prepare("
            SELECT p.Module_ID, p.Privilege_Name 
            FROM privileges AS p
            INNER JOIN role_privileges AS rp ON p.Privilege_ID = rp.Privilege_ID
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
            SELECT m.Module_ID, m.Module_Name 
            FROM modules AS m
            WHERE m.Module_ID NOT IN (
                SELECT DISTINCT p.Module_ID 
                FROM privileges AS p
                INNER JOIN role_privileges AS rp ON p.Privilege_ID = rp.Privilege_ID
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

// Handle assigning a new module to the role with selected privileges
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_module'])) {
    $newModuleId = $_POST['new_module'];
    $selectedPrivileges = isset($_POST['privileges']) ? $_POST['privileges'] : [];

    try {
        foreach ($selectedPrivileges as $privilegeId) {
            $insertStmt = $pdo->prepare("
                INSERT INTO role_privileges (Role_ID, Privilege_ID) VALUES (?, ?)
            ");
            $insertStmt->execute([$selectedRoleId, $privilegeId]);
        }

        echo "<p style='color: green;'>Module assigned successfully with selected privileges!</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
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
            <option value="<?= $role['Role_ID'] ?>" <?= ($selectedRoleId == $role['Role_ID']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($role['Role_Name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selectedRoleId): ?>
    <h2>Modules & Privileges for Role</h2>
    <table>
        <tr>
            <th>Module Name</th>
            <th>Privileges</th>
            <th>Assign Module</th>
        </tr>
        <?php foreach ($modules as $module): ?>
            <tr>
                <td><?= htmlspecialchars($module['Module_Name']) ?></td>
                <td>
                    <div class="checkbox-group">
                        <?= implode(', ', $privilegesByModule[$module['Module_ID']] ?? []) ?>
                    </div>
                </td>
                <td><button type="button" onclick="openModal()">+ Assign</button></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- Assign Module Modal -->
    <div id="assignModuleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Assign Module</h2>
            <form method="POST">
                <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                <label for="new_module">Choose a Module:</label>
                <select name="new_module" id="new_module" onchange="fetchPrivileges()" required>
                    <option value="">-- Select Module --</option>
                    <?php foreach ($availableModules as $module): ?>
                        <option value="<?= $module['Module_ID'] ?>"><?= htmlspecialchars($module['Module_Name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <br><br>
                <div id="privilegeCheckboxes"></div>
                <br>
                <button type="submit" name="assign_module">Assign</button>
            </form>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
