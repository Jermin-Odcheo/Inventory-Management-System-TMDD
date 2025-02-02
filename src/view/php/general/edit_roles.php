<?php
session_start();
require_once('../../../../config/ims-tmdd.php');

// Optional: Check if the logged-in user has permission to edit roles.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if a Role ID is provided via GET.
if (!isset($_GET['id'])) {
    die("Role ID not provided.");
}

$roleID = $_GET['id'];

// Retrieve role details.
$stmt = $pdo->prepare("SELECT * FROM roles WHERE Role_ID = ?");
$stmt->execute([$roleID]);
$role = $stmt->fetch();

if (!$role) {
    die("Role not found.");
}

// Retrieve currently assigned privileges for the role.
$stmtCurrent = $pdo->prepare("SELECT Privilege_ID FROM role_privileges WHERE Role_ID = ?");
$stmtCurrent->execute([$roleID]);
$currentPrivileges = $stmtCurrent->fetchAll(PDO::FETCH_COLUMN);

// Retrieve all privileges along with their module details.
$sql = "
    SELECT 
        p.Privilege_ID, 
        p.Privilege_Name, 
        COALESCE(m.Module_ID, 0) AS Module_ID, 
        COALESCE(m.Module_Name, 'General') AS Module_Name
    FROM privileges p
    LEFT JOIN modules m ON p.Module_ID = m.Module_ID
    ORDER BY Module_Name, p.Privilege_Name
";
$stmtPrivileges = $pdo->query($sql);
$allPrivileges = $stmtPrivileges->fetchAll(PDO::FETCH_ASSOC);

// Group privileges by module.
$groupedPrivileges = [];
foreach ($allPrivileges as $priv) {
    $moduleName = $priv['Module_Name'];
    if (!isset($groupedPrivileges[$moduleName])) {
        $groupedPrivileges[$moduleName] = [];
    }
    $groupedPrivileges[$moduleName][] = $priv;
}

$error = '';
$message = '';

// Process the form submission.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $roleName = trim($_POST['role_name']);
    $selectedPrivileges = isset($_POST['privileges']) ? $_POST['privileges'] : [];

    if (empty($roleName)) {
        $error = "Role name cannot be empty.";
    } else {
        try {
            // Begin transaction to update role name and privileges.
            $pdo->beginTransaction();

            // Update the role name.
            $stmtUpdate = $pdo->prepare("UPDATE roles SET Role_Name = ? WHERE Role_ID = ?");
            $stmtUpdate->execute([$roleName, $roleID]);

            // Delete existing privilege assignments.
            $stmtDelete = $pdo->prepare("DELETE FROM role_privileges WHERE Role_ID = ?");
            $stmtDelete->execute([$roleID]);

            // Insert new privilege assignments.
            $stmtInsert = $pdo->prepare("INSERT INTO role_privileges (Role_ID, Privilege_ID) VALUES (?, ?)");
            foreach ($selectedPrivileges as $privilegeID) {
                $stmtInsert->execute([$roleID, $privilegeID]);
            }

            $pdo->commit();

            $message = "Role and privileges updated successfully.";
            // Update current privileges and role data for display.
            $currentPrivileges = $selectedPrivileges;
            $role['Role_Name'] = $roleName;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating role: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Role &amp; Privileges: <?php echo htmlspecialchars($role['Role_Name']); ?></title>
    <link rel="stylesheet" href="../../styles/css/admin.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .error {
            color: #b30000;
            background: #ffd6d6;
            padding: 10px;
            margin-bottom: 20px;
        }
        .message {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            margin-bottom: 20px;
        }
        form label {
            display: block;
            margin-bottom: 5px;
        }
        form input[type="text"] {
            width: 300px;
            padding: 8px;
            margin-bottom: 15px;
        }
        fieldset {
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 10px;
        }
        legend {
            font-weight: bold;
        }
        .checkbox-group {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        .checkbox-group label {
            margin-right: 10px;
        }
        button {
            padding: 10px 20px;
            background-color: #0066cc;
            border: none;
            color: #fff;
            cursor: pointer;
        }
        button:hover {
            background-color: #005bb5;
        }
        a {
            text-decoration: none;
            color: #0066cc;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Edit Role &amp; Privileges</h1>
    
    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($message)): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="role_name">Role Name:</label>
        <input type="text" id="role_name" name="role_name" value="<?php echo htmlspecialchars($role['Role_Name']); ?>" required>
        
        <h2>Assign Privileges</h2>
        <?php foreach ($groupedPrivileges as $moduleName => $privileges): ?>
            <fieldset>
                <legend><?php echo htmlspecialchars($moduleName); ?></legend>
                <?php foreach ($privileges as $priv): ?>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="privileges[]" value="<?php echo $priv['Privilege_ID']; ?>"
                                <?php echo in_array($priv['Privilege_ID'], $currentPrivileges) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($priv['Privilege_Name']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>

        <button type="submit">Update Role &amp; Privileges</button>
    </form>
    
    <p><a href="manage_roles.php">Back to Role Management</a></p>
</body>
</html>
