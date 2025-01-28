<?php
session_start();
require_once '../config/ims-tmdd.php';

// Typically, only Admins can manage users
// Check if the user is Admin (role_name = 'Admin') or has some "can_manage_users" privilege
// For simplicity, let's assume role_id 1 is Admin, or we add a privilege check for user management.
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role_id'] != 1) {
    die("Access Denied: Only Admin can manage users.");
}

$stmt = $pdo->query("
    SELECT u.id, u.username, u.email, u.role_id, r.role_name, u.is_active
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY u.id
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <title>User Management</title>
</head>

<body>
    <h1>User Management</h1>
    <p><a href="dashboard.php">Back to Dashboard</a></p>
    <p><a href="user_create.php">Create New User</a></p>

    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Role</th>
            <th>Active?</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo $u['id']; ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo htmlspecialchars($u['role_name']); ?></td>
                <td><?php echo $u['is_active'] ? 'Yes' : 'No'; ?></td>
                <td>
                    <a href="user_edit.php?id=<?php echo $u['id']; ?>">Edit</a>
                    <!-- Perhaps a delete or deactivate link, but be cautious about deleting admin. -->
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>

</html>