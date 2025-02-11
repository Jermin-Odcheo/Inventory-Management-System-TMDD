<?php
session_start();
require_once('..\..\..\..\..\config\ims-tmdd.php'); // Database connection

// Fetch roles grouped by module with privileges
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.Module_ID, 
            m.Module_Name, 
            r.Role_ID, 
            r.Role_Name,
            GROUP_CONCAT(p.Privilege_Name ORDER BY p.Privilege_ID SEPARATOR ', ') AS Privileges
        FROM roles AS r
        INNER JOIN role_privileges AS rp ON r.Role_ID = rp.Role_ID
        INNER JOIN privileges AS p ON rp.Privilege_ID = p.Privilege_ID
        INNER JOIN modules AS m ON p.Module_ID = m.Module_ID
        GROUP BY m.Module_ID, m.Module_Name, r.Role_ID, r.Role_Name
        ORDER BY m.Module_ID, r.Role_Name
    ");
    
    $stmt->execute();
    $getData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize data by Module_ID
    $modules = [];
    foreach ($getData as $row) {
        $modules[$row['Module_ID']]['Module_Name'] = $row['Module_Name'];
        $modules[$row['Module_ID']]['Roles'][] = [
            'Role_ID' => $row['Role_ID'],
            'Role_Name' => $row['Role_Name'],
            'Privileges' => explode(', ', $row['Privileges'])
        ];
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}


//Process privilege updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    try {
        foreach ($_POST['privileges'] as $roleID => $modulesData) {
            foreach ($modulesData as $moduleID => $privileges) {
                // Delete old privileges for this role & module
                $deleteStmt = $pdo->prepare("
                    DELETE FROM role_privileges 
                    WHERE Role_ID = ? 
                    AND Privilege_ID IN (SELECT Privilege_ID FROM privileges WHERE Module_ID = ?)
                ");
                $deleteStmt->execute([$roleID, $moduleID]);

                // Insert new privileges
                foreach ($privileges as $privilegeName) {
                    $privilegeStmt = $pdo->prepare("
                        SELECT Privilege_ID FROM privileges WHERE Privilege_Name = ? AND Module_ID = ?
                    ");
                    $privilegeStmt->execute([$privilegeName, $moduleID]);
                    $privilege = $privilegeStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$privilege) continue;

                    // Insert new privilege
                    $insertStmt = $pdo->prepare("
                        INSERT INTO role_privileges (Role_ID, Privilege_ID) 
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

//Add role
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

    <h1>Roles and Privileges</h1>
    <form method="POST">
        <table border="1">
            <tr>
                <th>Module Name</th>
                <th>Role Name</th>
                <th>Privileges</th>
            </tr>
            <?php foreach ($modules as $moduleID => $moduleData): ?>
                <?php foreach ($moduleData['Roles'] as $index => $role): ?>
                    <tr>
                        <?php if ($index === 0): ?>
                            <td rowspan="<?= count($moduleData['Roles']) ?>"><?= htmlspecialchars($moduleData['Module_Name']) ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($role['Role_Name']) ?></td>
                        <td>
                            <?php
                            $allPrivileges = ["View", "Add", "Edit", "Delete"];
                            foreach ($allPrivileges as $privilege) {
                                $checked = in_array($privilege, $role['Privileges']) ? "checked" : "";
                                echo "<input type='checkbox' name='privileges[{$role['Role_ID']}][{$moduleID}][]' value='{$privilege}' {$checked}> {$privilege} ";
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