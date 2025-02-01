<?php
session_start();
require_once '../config/ims-tmdd.php';

$userSession = $_SESSION['user'] ?? null;
if (!$userSession || $userSession['role_id'] != 1) {
    die("Access Denied: Only Admin can manage users.");
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Missing user ID.");
}

// Fetch user data
$stmt = $pdo->prepare("
    SELECT id, username, email, role_id, is_active
    FROM users
    WHERE id = :id
");
$stmt->execute(['id' => $id]);
$userToEdit = $stmt->fetch();
if (!$userToEdit) {
    die("User not found.");
}

// Fetch roles
$roleStmt = $pdo->query("SELECT id, role_name FROM roles ORDER BY id");
$roles = $roleStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email    = $_POST['email'] ?? '';
    $role_id  = $_POST['role_id'] ?? 2;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // If admin wants to change password, only update if newPassword is set
    $newPassword = $_POST['password'] ?? null;

    if ($newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateSql = "
            UPDATE users
            SET username = :username,
                password_hash = :passhash,
                email = :email,
                role_id = :role_id,
                is_active = :is_active
            WHERE id = :id
        ";
        $updateParams = [
            'username'  => $username,
            'passhash'  => $passwordHash,
            'email'     => $email,
            'role_id'   => $role_id,
            'is_active' => $is_active,
            'id'        => $id
        ];
    } else {
        $updateSql = "
            UPDATE users
            SET username = :username,
                email = :email,
                role_id = :role_id,
                is_active = :is_active
            WHERE id = :id
        ";
        $updateParams = [
            'username'  => $username,
            'email'     => $email,
            'role_id'   => $role_id,
            'is_active' => $is_active,
            'id'        => $id
        ];
    }

    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($updateParams);

    header('Location: users_index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Edit User</title>
</head>

<body>
    <h1>Edit User</h1>
    <p><a href="user_index.php">Back to User List</a></p>
    <form method="POST">
        <label>Username:
            <input type="text" name="username" value="<?php echo htmlspecialchars($userToEdit['username']); ?>" required>
        </label><br><br>

        <label>Email:
            <input type="email" name="email" value="<?php echo htmlspecialchars($userToEdit['email']); ?>">
        </label><br><br>

        <label>Role:
            <select name="role_id">
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo $r['id']; ?>" <?php if ($userToEdit['role_id'] == $r['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($r['role_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>

        <label>Active?
            <input type="checkbox" name="is_active" <?php if ($userToEdit['is_active']) echo 'checked'; ?>>
        </label><br><br>

        <label>New Password (leave blank to keep existing):
            <input type="password" name="password">
        </label><br><br>

        <button type="submit">Update</button>
    </form>
</body>

</html>