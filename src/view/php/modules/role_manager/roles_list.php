<?php
session_start();
require_once('..\..\..\..\..\config\ims-tmdd.php'); // Database connection

// Fetch roles and their associated modules (using LEFT JOIN to include roles without modules)
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
        GROUP BY r.Role_Name
        ORDER BY r.Role_Name
    ");

    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/style.css">
    <title>Roles and Modules</title>
</head>
<body>
<main>
    <h1>Roles and Their Associated Modules</h1>
    <table border="1">
        <tr>
            <th>Role Name</th>
            <th>Modules</th>
        </tr>
        <?php foreach ($roles as $role): ?>
            <tr>
                <td><?= $role['Role_Name'] ?></td>
                <td><?= $role['Modules'] ? $role['Modules'] : 'No modules assigned' ?></td>
                <td><button>Add Module</button></td>
            </tr>
        <?php endforeach; ?>
    </table>
</main>
</body>
</html>