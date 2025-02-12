<?php
session_start();
require_once('..\..\..\..\..\config\ims-tmdd.php'); // Database connection

//fetch roles and their associated modules
try {
    $stmt = $pdo->prepare("
        SELECT 
            r.Role_ID, 
            r.Role_Name, 
            GROUP_CONCAT(DISTINCT m.Module_Name ORDER BY m.Module_Name SEPARATOR ', ') AS Modules
        FROM roles AS r
        LEFT JOIN role_privileges AS rp ON r.Role_ID = rp.Role_ID
        LEFT JOIN privileges AS p ON rp.Privilege_ID = p.Privilege_ID
        LEFT JOIN modules AS m ON p.Module_ID = m.Module_ID
        GROUP BY r.Role_ID, r.Role_Name
        ORDER BY r.Role_Name
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

//Fetch modules for modal
try {
    $moduleStmt = $pdo->prepare("
        SELECT 
            m.Module_ID, 
            m.Module_Name, 
            GROUP_CONCAT(p.Privilege_Name ORDER BY p.Privilege_ID SEPARATOR ', ') AS Privileges
        FROM modules AS m
        LEFT JOIN privileges AS p ON m.Module_ID = p.Module_ID
        GROUP BY m.Module_ID, m.Module_Name
        ORDER BY m.Module_ID
    ");
    $moduleStmt->execute();
    $modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

//handle role selection for the modal
$selectedRoleName = "";
$selectedRoleId = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['open_modal'])) {
    $selectedRoleName = $_POST['selected_role_name'];
    $selectedRoleId = $_POST['selected_role_id'];
}

//add module to role
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_module'])) {
    $roleId = $_POST['role_id'];
    $moduleId = $_POST['module_id'];

    try {
        $checkStmt = $pdo->prepare("
            SELECT * FROM role_privileges 
            WHERE Role_ID = ? 
            AND Privilege_ID IN (SELECT Privilege_ID FROM privileges WHERE Module_ID = ?)
        ");
        $checkStmt->execute([$roleId, $moduleId]);

        if ($checkStmt->rowCount() == 0) {
            $insertStmt = $pdo->prepare("
                INSERT INTO role_privileges (Role_ID, Privilege_ID) 
                SELECT ?, Privilege_ID FROM privileges WHERE Module_ID = ?
            ");
            $insertStmt->execute([$roleId, $moduleId]);
            echo("Module successfully assigned!");
        } 
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
}

//remove module from a role
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_module'])) {
    $roleId = $_POST['role_id'];
    $moduleId = $_POST['module_id'];

    try {
        $deleteStmt = $pdo->prepare("
            DELETE FROM role_privileges 
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
                        <input type="hidden" name="selected_role_id" value="<?= $role['Role_ID'] ?>">
                        <button type="submit" name="open_modal" class="btn btn-info btn-lg">Open Modal</button>
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
                                        <td><?= $module['Module_ID'] ?></td>
                                        <td><?= $module['Module_Name'] ?></td>
                                        <td><?= htmlspecialchars($module['Privileges'] ?? 'None') ?></td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">
                                                <input type="hidden" name="module_id" value="<?= $module['Module_ID'] ?>">
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
