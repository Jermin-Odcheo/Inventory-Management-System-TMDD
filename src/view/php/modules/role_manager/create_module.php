<?php
require_once('..\..\..\..\..\config\ims-tmdd.php'); // Database connection

// Fetch all modules with grouped privileges
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.Module_ID, 
            m.Module_Name, 
            GROUP_CONCAT(p.Privilege_Name ORDER BY p.Privilege_ID SEPARATOR ', ') AS Privileges
        FROM modules AS m
        LEFT JOIN privileges AS p ON m.Module_ID = p.Module_ID
        GROUP BY m.Module_ID, m.Module_Name
        ORDER BY m.Module_ID
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['module_name'])) {
        $moduleName = trim($_POST['module_name']); // Sanitize input

        try {
            // Insert the module name into the database
            $stmt = $pdo->prepare("INSERT INTO modules (Module_Name) VALUES (?)");
            $stmt->execute([$moduleName]);

            // Redirect to prevent form resubmission
            header("Location: create_module.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    } else {
        echo "<p style='color: red;'>Module Name cannot be empty.</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://www.phptutorial.net/app/css/style.css">
    <title>Register</title>
</head>
<body>
<main>
    <form action="create_module.php" method="post">
        <h1>Create Module</h1>
        <div>
            <label for="module_name">Module Name:</label>
            <input type="text" name="module_name" id="module_name">
        </div>

        <button type="submit">Add Module</button>
        <div></div>
        <button type="cancel">Cancel</button>
    </form>
    <a href="create_role.php" class="button-class">Role and Priveleges</a>
    <div></div>
    <a href="roles_list.php" class="button-class">Roles and Their Associated Modules</a>

    <?php if (isset($_GET['success'])): ?>
        <p style="color: green;">Module added successfully!</p>
    <?php endif; ?>

    <div>
        <h2>Available Modules & Privileges</h2>
        <table border="1">
            <thead>
                <tr>
                    <th>Module ID</th>
                    <th>Module Name</th>
                    <th>Privileges</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module): ?>
                    <tr>
                        <td><?= htmlspecialchars($module['Module_ID']) ?></td>
                        <td><?= htmlspecialchars($module['Module_Name']) ?></td>
                        <td><?= htmlspecialchars($module['Privileges'] ?? 'None') ?></td>
                        <td>asdasd</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
