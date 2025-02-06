<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check for admin privileges (you should implement your privilege check).
if (!isset($_SESSION['user_id'])) {
    header("Location: add_user.php");
    exit();
}

// If editing, load user data.
$isEditing = isset($_GET['id']);
$userData = [];
if ($isEditing) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE User_ID = ?");
    $stmt->execute([$_GET['id']]);
    $userData = $stmt->fetch();
}

// Fetch available roles.
$stmt = $pdo->prepare("SELECT * FROM roles");
$stmt->execute();
$roles = $stmt->fetchAll();

// If form is submitted.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email      = trim($_POST['email']);
    $firstName  = trim($_POST['first_name']);
    $lastName   = trim($_POST['last_name']);
    $department = trim($_POST['department']);
    $status     = $_POST['status'];
    $roleIDs    = isset($_POST['roles']) ? $_POST['roles'] : [];

    // If adding a new user, you might also collect and hash the password.
    if (!$isEditing) {
        $password = $_POST['password'];
        // Hash the password.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (Email, Password, First_Name, Last_Name, Department, Status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$email, $hashedPassword, $firstName, $lastName, $department, $status]);
        $userID = $pdo->lastInsertId();
    } else {
        // For edit, update user details. (Password update might be handled separately.)
        $stmt = $pdo->prepare("UPDATE users SET Email = ?, First_Name = ?, Last_Name = ?, Department = ?, Status = ? WHERE User_ID = ?");
        $stmt->execute([$email, $firstName, $lastName, $department, $status, $userData['User_ID']]);
        $userID = $userData['User_ID'];
    }

    // Update the user's roles.
    // First, delete existing roles.
    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE User_ID = ?");
    $stmt->execute([$userID]);

    // Then, insert the new roles.
    $stmt = $pdo->prepare("INSERT INTO user_roles (User_ID, Role_ID) VALUES (?, ?)");
    foreach ($roleIDs as $roleID) {
        $stmt->execute([$userID, $roleID]);
    }

    header("Location: manage_users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isEditing ? 'Edit' : 'Add'; ?> User</title>
    <link rel="stylesheet" href="../../../../styles/css/admin.css">
</head>
<body>
    <h1><?php echo $isEditing ? 'Edit' : 'Add'; ?> User</h1>
    <form method="POST" action="">
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['Email'] ?? ''); ?>" required><br>
        
        <?php if (!$isEditing): ?>
            <label>Password:</label>
            <input type="password" name="password" required><br>
        <?php endif; ?>

        <label>First Name:</label>
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($userData['First_Name'] ?? ''); ?>" required><br>
        <label>Last Name:</label>
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($userData['Last_Name'] ?? ''); ?>" required><br>
        <label>Department:</label>
        <input type="text" name="department" value="<?php echo htmlspecialchars($userData['Department'] ?? ''); ?>"><br>
        <label>Status:</label>
        <select name="status">
            <option value="Online" <?php echo (isset($userData['Status']) && $userData['Status'] === 'Online') ? 'selected' : ''; ?>>Online</option>
            <option value="Offline" <?php echo (isset($userData['Status']) && $userData['Status'] === 'Offline') ? 'selected' : ''; ?>>Offline</option>
        </select><br>

        <fieldset>
            <legend>Assign Roles:</legend>
            <?php foreach ($roles as $role): 
                // If editing, check which roles the user already has.
                $isAssigned = false;
                if ($isEditing) {
                    $stmt = $pdo->prepare("SELECT 1 FROM user_roles WHERE User_ID = ? AND Role_ID = ?");
                    $stmt->execute([$userData['User_ID'], $role['Role_ID']]);
                    $isAssigned = (bool) $stmt->fetch();
                }
            ?>
                <input type="checkbox" name="roles[]" value="<?php echo $role['Role_ID']; ?>" <?php echo $isAssigned ? 'checked' : ''; ?>>
                <label><?php echo htmlspecialchars($role['Role_Name']); ?></label><br>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit"><?php echo $isEditing ? 'Update' : 'Add'; ?> User</button>
    </form>
    <a href="user_management.php">Back to User Management</a>
</body>
</html>
