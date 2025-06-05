<?php
/**
 * @file roles_list.php
 * @brief handles the display and assignment of modules to roles within the system
 *
 * This script manages the display and assignment of modules to roles within the system.
 * It fetches roles and their associated modules from the database, displays them in a table,
 * and provides a modal interface for assigning or removing modules from specific roles.
 */
session_start();
require_once('../../../../../../config/ims-tmdd.php'); // Database connection

/**
 * Fetch Roles and Associated Modules
 *
 * Retrieves all roles from the database along with their associated modules.
 * The data is grouped by role ID and name, with modules concatenated into a single string.
 */
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.id, 
            r.Role_Name, 
            GROUP_CONCAT(DISTINCT m.Module_Name ORDER BY m.Module_Name SEPARATOR ', ') AS Modules
        FROM roles AS r
        LEFT JOIN role_module_privileges AS rp ON r.id = rp.Role_ID
        LEFT JOIN privileges AS p ON rp.Privilege_ID = p.id
        LEFT JOIN modules AS m ON p.id = m.id
        GROUP BY r.id, r.Role_Name
        ORDER BY r.Role_Name
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

/**
 * Fetch Modules and Privileges
 *
 * Retrieves all modules from the database along with their associated privileges.
 * The data is grouped by module ID and name, with privileges concatenated into a single string.
 */
try {
    $moduleStmt = $pdo->prepare("
        SELECT 
            m.id, 
            m.Module_Name, 
            GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ') AS Privileges
        FROM modules AS m
        LEFT JOIN privileges AS p ON m.id = p.id
        GROUP BY m.id, m.Module_Name
        ORDER BY m.id
    ");
    $moduleStmt->execute();
    $modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

/**
 * Handle Role Selection for Modal
 *
 * Processes form submission to select a role for editing its module assignments.
 * Sets the selected role's name and ID for use in the modal.
 */
$selectedRoleName = "";
$selectedRoleId = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['open_modal'])) {
    $selectedRoleName = $_POST['selected_role_name'];
    $selectedRoleId = $_POST['selected_role_id'];
}

/**
 * Assign Module to Role
 *
 * Handles the assignment of a module to a selected role.
 * Checks if the module is already assigned to avoid duplicates, then inserts the relationship.
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_module'])) {
    $roleId = $_POST['role_id'];
    $moduleId = $_POST['module_id'];

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

/**
 * Remove Module from Role
 *
 * Handles the removal of a module from a selected role.
 * Deletes the relationship between the role and the module's privileges.
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_module'])) {
    $roleId = $_POST['role_id'];
    $moduleId = $_POST['module_id'];

    try {
        $deleteStmt = $pdo->prepare("
            DELETE FROM role_module_privileges 
            WHERE Role_ID = ? 
            AND Privilege_ID IN (SELECT Privilege_ID FROM privileges WHERE Module_ID = ?)
        ");
        $deleteStmt->execute([$roleId, $moduleId]);

        $message = "Module successfully removed!";
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
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <title>Roles and Modules</title>
</head>
<body>
<main>
    <h1>Roles and Their Associated Modules</h1>
    <a href="create_role.php" class="button-class">Role and Module List</a>
    <div></div>
    <a href="create_module.php" class="button-class">Available Modules & Priveleges</a>

    <!-- Roles Table -->
    <table border="1">
        <tr>
            <th>Role Name</th>
            <th>Modules</th>
            <th>Action</th>
        </tr>
        <?php foreach ($roles as $role): ?>
            <tr>
                <td><?= $role['Role_Name'] ?></td>
                <td><?= $role['Modules'] ? $role['Modules'] : 'No modules assigned' ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="selected_role_name" value="<?= $role['Role_Name'] ?>">
                        <input type="hidden" name="selected_role_id" value="<?= $role['id'] ?>">
                        <button type="submit" name="open_modal" class="btn btn-info btn-lg">Edit Modules</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- Modal -->
    <?php if (!empty($selectedRoleName) && !empty($selectedRoleId)): ?>
        <div class="modal show">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Assign Module to Role: <?= htmlspecialchars($selectedRoleName) ?></h4>
                        <form method="POST">
                            <button type="submit" name="close_modal" class="close">&times;</button>
                        </form>
                    </div>
                    <div class="modal-body">
                        <h2>Available Modules</h2>
                        <table border="1">
                            <thead>
                                <tr>
                                    <th>Module ID</th>
                                    <th>Module Name</th>
                                    <th>Privileges</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $module): ?>
                                    <tr>
                                        <td><?= $module['id'] ?></td>
                                        <td><?= $module['Module_Name'] ?></td>
                                        <td><?= htmlspecialchars($module['Privileges'] ?? 'None') ?></td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                                                <input type="hidden" name="module_id" value="<?= $module['id'] ?>">
                                                <button type="submit" name="assign_module" class="btn btn-success">Assign</button>
                                                <button type="submit" name="remove_module">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
