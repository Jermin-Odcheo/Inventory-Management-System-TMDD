<?php
/**
 * Create Role Module
 *
 * This file provides functionality to create new roles in the system. It handles the creation of role data, including permissions and settings. The module ensures proper validation, user authorization, and maintains data consistency during the role creation process.
 *
 * @package    InventoryManagementSystem
 * @subpackage RolesAndPrivilegeManager
 * @author     TMDD Interns 25'
 */
session_start();
require_once('../../../../../../config/ims-tmdd.php');// Database connection

/**
 * Fetch Roles and Privileges
 *
 * Retrieves roles grouped by module with their associated privileges from the database for display.
 *
 * @return array The organized data of roles and their associated modules and privileges.
 */
try {
    $stmt = $pdo->prepare("
    SELECT 
        r.id, 
        r.Role_Name, 
        m.id, 
        m.Module_Name,
        GROUP_CONCAT(p.priv_name ORDER BY p.id SEPARATOR ', ') AS Privileges
        FROM roles AS r
        INNER JOIN role_module_privileges AS rp ON r.id = rp.id
        INNER JOIN privileges AS p ON rp.Privilege_ID = p.id
        INNER JOIN modules AS m ON p.id = m.id
        GROUP BY r.id, r.Role_Name, m.id, m.Module_Name
        ORDER BY r.Role_Name, m.id
        ");

$stmt->execute();
$getData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize data by Role_ID
$roles = [];
foreach ($getData as $row) {
    $roles[$row['id']]['Role_Name'] = $row['Role_Name'];
    $roles[$row['id']]['Modules'][] = [
        'Module_ID' => $row['id'],
        'Module_Name' => $row['Module_Name'],
        'Privileges' => explode(', ', $row['Privileges'])
    ];
}

    
    $stmt->execute();
    $getData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize data by Module_ID
    $modules = [];
    foreach ($getData as $row) {
        $modules[$row['id']]['Module_Name'] = $row['Module_Name'];
        $modules[$row['id']]['Roles'][] = [
            'Role_ID' => $row['id'],
            'Role_Name' => $row['Role_Name'],
            'Privileges' => explode(', ', $row['Privileges'])
        ];
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}


/**
 * Process Privilege Updates
 *
 * Updates the privileges for roles based on form submission. Deletes old privileges and inserts new ones.
 *
 * @return void
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    try {
        foreach ($_POST['privileges'] as $roleID => $modulesData) {
            foreach ($modulesData as $moduleID => $privileges) {
                // Delete old privileges for this role & module
                /**
                 * Delete Old Privileges
                 *
                 * Removes existing privileges for a specific role and module before adding new ones.
                 *
                 * @param int $roleID The ID of the role to update.
                 * @param int $moduleID The ID of the module to update privileges for.
                 * @return void
                 */
                $deleteStmt = $pdo->prepare("
                    DELETE FROM role_module_privileges 
                    WHERE Role_ID = ? 
                    AND Privilege_ID IN (SELECT Privilege_ID FROM privileges WHERE Module_ID = ?)
                ");
                $deleteStmt->execute([$roleID, $moduleID]);

                // Insert new privileges
                foreach ($privileges as $privilegeName) {
                    /**
                     * Fetch Privilege ID
                     *
                     * Retrieves the ID of a privilege by its name and associated module.
                     *
                     * @param string $privilegeName The name of the privilege to fetch.
                     * @param int $moduleID The ID of the module associated with the privilege.
                     * @return int|null The ID of the privilege if found, null otherwise.
                     */
                    $privilegeStmt = $pdo->prepare("
                        SELECT id FROM privileges WHERE priv_name = ? AND id = ?
                    ");
                    $privilegeStmt->execute([$privilegeName, $moduleID]);
                    $privilege = $privilegeStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$privilege) continue;

                    // Insert new privilege
                    /**
                     * Insert New Privilege
                     *
                     * Adds a new privilege association for a role in the database.
                     *
                     * @param int $roleID The ID of the role to add the privilege to.
                     * @param int $privilegeID The ID of the privilege to add.
                     * @return void
                     */
                    $insertStmt = $pdo->prepare("
                        INSERT INTO role_module_privileges (Role_ID, Privilege_ID) 
                        VALUES (?, ?)
                    ");
                    $insertStmt->execute([$roleID, $privilege['Privilege_ID']]);
                }
            }
        }
        echo "<p style='color: green;'>Privileges updated successfully!</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
    }
}

/**
 * Add New Role
 *
 * Processes the form submission to add a new role to the database.
 *
 * @return void
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['rolename'])) {
        $moduleName = trim($_POST['rolename']); // Sanitize input

        try {
            //Insert the role name into the database
            $stmt = $pdo->prepare("INSERT INTO roles (Role_Name) VALUES (?)");
            $stmt->execute([$moduleName]);

            //Redirect to prevent form resubmission
            header("Location: create_role.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    } else {
        echo "<p style='color: red;'>Role Name cannot be empty.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/style.css">
    <title>Roles and Privileges</title>
</head>
<body>
<main>
    <form action="create_role.php" method="post">
        <h1>Create Role</h1>
        <div>
            <label for="rolename">Role Name:</label>
            <input type="text" name="rolename" id="rolename">
        </div>
        
        <button type="submit">Add Role</button>
        <div></div>
        <button type="cancel">Cancel</button>
    </form>
    <a href="roles_list.php" class="button-class">Role and Module List</a>
    <div></div>
    <a href="create_module.php" class="button-class">Available Modules & Priveleges</a>

    <h1>Roles and Privileges</h1>
    <form method="POST">
        <table border="1">
            <tr>
                <th>Role Name</th>
                <th>Module Name</th>
                <th>Privileges</th>
            </tr>
            <?php foreach ($roles as $roleID => $roleData): ?>
                <?php foreach ($roleData['Modules'] as $index => $module): ?>
                    <tr>
                        <?php if ($index === 0): ?>
                            <td rowspan="<?= count($roleData['Modules']) ?>"><?= htmlspecialchars($roleData['Role_Name']) ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($module['Module_Name']) ?></td>
                        <td>
                            <?php
                            $allPrivileges = ["View", "Add", "Edit", "Delete"];
                            foreach ($allPrivileges as $privilege) {
                                $checked = in_array($privilege, $module['Privileges']) ? "checked" : "";
                                echo "<input type='checkbox' name='privileges[{$roleID}][{$module['Module_ID']}][]' value='{$privilege}' {$checked}> {$privilege} ";
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </table>
        <br>
        <input type="submit" name="update" value="Update Privileges">
    </form>

</main>
</body>
</html>