<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check for admin privileges (you should implement your privilege check).
if (!isset($_SESSION['user_id'])) {
    header("Location: add_user.php");
    exit();
}
// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    // For anonymous actions, you might set a default.
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address; adjust as needed if you use a proxy, etc.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

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

$successMessage = ''; // Variable to hold the success message

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
        $successMessage = "User added successfully!";
    } else {
        // For edit, update user details. (Password update might be handled separately.)
        $stmt = $pdo->prepare("UPDATE users SET Email = ?, First_Name = ?, Last_Name = ?, Department = ?, Status = ? WHERE User_ID = ?");
        $stmt->execute([$email, $firstName, $lastName, $department, $status, $userData['User_ID']]);
        $userID = $userData['User_ID'];
        $successMessage = "User updated successfully!";
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isEditing ? 'Edit' : 'Add'; ?> User</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../../styles/css/admin.css">
</head>
<body>
<?php include '../../general/sidebar.php'; ?>
<div class="container mt-5">
    <h1 class="mb-4"><?php echo $isEditing ? 'Edit' : 'Add'; ?> User</h1>

    <?php if ($successMessage): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $successMessage; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="email" class="form-label">Email:</label>
            <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($userData['Email'] ?? ''); ?>" class="form-control" required>
        </div>

        <?php if (!$isEditing): ?>
            <div class="mb-3">
                <label for="password" class="form-label">Password:</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label for="first_name" class="form-label">First Name:</label>
            <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($userData['First_Name'] ?? ''); ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="last_name" class="form-label">Last Name:</label>
            <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($userData['Last_Name'] ?? ''); ?>" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="department" class="form-label">Department:</label>
            <input type="text" name="department" id="department" value="<?php echo htmlspecialchars($userData['Department'] ?? ''); ?>" class="form-control">
        </div>
        <div class="mb-3">
            <label for="status" class="form-label">Status:</label>
            <select name="status" id="status" class="form-select">
                <option value="Online" <?php echo (isset($userData['Status']) && $userData['Status'] === 'Online') ? 'selected' : ''; ?>>Online</option>
                <option value="Offline" <?php echo (isset($userData['Status']) && $userData['Status'] === 'Offline') ? 'selected' : ''; ?>>Offline</option>
            </select>
        </div>

        <fieldset class="mb-3">
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
                <div class="form-check">
                    <input type="checkbox" name="roles[]" value="<?php echo $role['Role_ID']; ?>" id="role_<?php echo $role['Role_ID']; ?>" class="form-check-input" <?php echo $isAssigned ? 'checked' : ''; ?>>
                    <label for="role_<?php echo $role['Role_ID']; ?>" class="form-check-label"><?php echo htmlspecialchars($role['Role_Name']); ?></label>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit" class="btn btn-primary"><?php echo $isEditing ? 'Update' : 'Add'; ?> User</button>
    </form>
    <div class="mt-3">
        <a href="user_management.php" class="btn btn-secondary">Back to User Management</a>
    </div>
</div>

<!-- Bootstrap JS Bundle (Optional, if you need interactive components) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
