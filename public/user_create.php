<?php
session_start();
require_once '../config/ims-tmdd.php';

// Only Admin or privileged user can create users
$user = $_SESSION['user'] ?? null;
if (!$user || $user['role_id'] != 1) {
    die("Access Denied: Only Admin can create new users.");
}

// Fetch roles for a dropdown
$roleStmt = $pdo->query("SELECT id, role_name FROM roles ORDER BY id");
$roles = $roleStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email    = $_POST['email'] ?? '';
    $role_id  = $_POST['role_id'] ?? 2; // default role?

    // Hash the password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, email, role_id, is_active)
        VALUES (:username, :passhash, :email, :role_id, 1)
    ");
    $stmt->execute([
        'username' => $username,
        'passhash' => $passwordHash,
        'email'    => $email,
        'role_id'  => $role_id
    ]);

    header('Location: user_index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Create User</title>
</head>

<body>
    <h1>Create User</h1>
    <p><a href="user_index.php">Back to User List</a></p>
    <form method="POST">
        <label>Username:
            <input type="text" name="username" required>
        </label><br><br>

        <label>Password:
            <input type="password" name="password" required>
        </label><br><br>

        <label>Email:
            <input type="email" name="email">
        </label><br><br>

        <label>Role:
            <select name="role_id">
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo $r['id']; ?>">
                        <?php echo htmlspecialchars($r['role_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label><br><br>

        <button type="submit">Create</button>
    </form>
</body>

</html>