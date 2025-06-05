<?php
/**
 * @file create_module.php
 * @brief handles the creation and deletion of modules in the system
 *
 * This script handles the creation and deletion of modules in the system. It fetches existing modules
 * with their associated privileges and provides a form to add new modules with default privileges.
 *
 */
require_once('../../../../../../config/ims-tmdd.php');// Database connection

/**
 * Fetch All Modules
 *
 * Retrieves all modules with their associated privileges from the database for display.
 *
 * @return array The list of modules with grouped privileges.
 */
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id, 
            m.Module_Name, 
            GROUP_CONCAT(p.priv_name ORDER BY p.id SEPARATOR ', ') AS Privileges
        FROM modules AS m
        LEFT JOIN privileges AS p ON m.id = p.id
        GROUP BY m.id, m.Module_Name
        ORDER BY m.id
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

/**
 * Add New Module
 *
 * Processes the form submission to add a new module with default privileges to the database.
 *
 * @return void
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['module_name'])) {
        $moduleName = trim($_POST['module_name']); // Sanitize input

        try {
            /**
             * Begin Database Transaction
             *
             * Starts a transaction to ensure data consistency during the module creation process.
             *
             * @return void
             */
            $pdo->beginTransaction(); // Start transaction

            // Insert the module
            $stmt = $pdo->prepare("INSERT INTO modules (Module_Name) VALUES (?)");
            $stmt->execute([$moduleName]);

            // Retrieve the Module_ID using the module name
            /**
             * Retrieve Module ID
             *
             * Fetches the ID of the newly created module using its name.
             *
             * @param string $moduleName The name of the module to fetch.
             * @return int|null The ID of the module if found, null otherwise.
             */
            $query = $pdo->prepare("SELECT id FROM modules WHERE Module_Name = ? LIMIT 1");
            $query->execute([$moduleName]);
            $module = $query->fetch(PDO::FETCH_ASSOC);

            if ($module) {
                $moduleId = $module['Module_ID'];

                // Insert default privileges for the new module
                /**
                 * Insert Default Privileges
                 *
                 * Adds default privileges (View, Add, Edit, Delete) for the newly created module.
                 *
                 * @param int $moduleId The ID of the module to add privileges to.
                 * @return void
                 */
                $privileges = ['View', 'Add', 'Edit', 'Delete'];
                $privilegeStmt = $pdo->prepare("INSERT INTO privileges (id, priv_name) VALUES (?, ?)");

                foreach ($privileges as $privilege) {
                    $privilegeStmt->execute([$moduleId, $privilege]);
                }
            }

            /**
             * Commit Transaction
             *
             * Commits the database transaction if all operations are successful.
             *
             * @return void
             */
            $pdo->commit(); // Commit transaction
            header("Location: create_module.php?success=1");
            exit();
        } catch (PDOException $e) {
            /**
             * Rollback Transaction on Error
             *
             * Rolls back the database transaction if an error occurs during the module creation process.
             *
             * @return void
             */
            $pdo->rollBack(); // Rollback if an error occurs
            die("Database error: " . $e->getMessage());
        }
    } else {
        echo "<p style='color: red;'>Module Name cannot be empty.</p>";
    }
}

/**
 * Remove Selected Module
 *
 * Deletes a specified module and its associated privileges from the database.
 *
 * @return void
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_module'])) {
    $moduleId = $_POST['remove_module'];

    try {
        /**
         * Begin Database Transaction
         *
         * Starts a transaction to ensure data consistency during the module deletion process.
         *
         * @return void
         */
        $pdo->beginTransaction(); 

        //delete from privileges where `Module_ID` matches
        /**
         * Delete Privileges
         *
         * Removes all privileges associated with the specified module.
         *
         * @param int $moduleId The ID of the module to delete privileges for.
         * @return void
         */
        $deletePrivilegesStmt = $pdo->prepare("DELETE FROM privileges WHERE id = ?");
        $deletePrivilegesStmt->execute([$moduleId]);

        //elete from modules
        /**
         * Delete Module
         *
         * Removes the specified module from the database.
         *
         * @param int $moduleId The ID of the module to delete.
         * @return void
         */
        $deleteModulesStmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
        $deleteModulesStmt->execute([$moduleId]);

        /**
         * Commit Transaction
         *
         * Commits the database transaction if all operations are successful.
         *
         * @return void
         */
        $pdo->commit(); 

        $message = "Module successfully removed!";
    } catch (PDOException $e) {
        /**
         * Rollback Transaction on Error
         *
         * Rolls back the database transaction if an error occurs during the module deletion process.
         *
         * @return void
         */
        $pdo->rollBack(); //rollback 
        $message = "Database error: " . $e->getMessage();
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
                        
                        <td><?= htmlspecialchars($module['id']) ?></td>
                        <td><?= htmlspecialchars($module['Module_Name']) ?></td>
                        <td><?= htmlspecialchars($module['Privileges'] ?? 'None') ?></td>
                        <td>
                        <form method="POST">
                            <button type="submit" name="remove_module"  value="<?=$module['id']?>">Remove</button>
                        </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
