<?php
session_start();
include '../../../../../config/ims-tmdd.php'; // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details, roles, and departments
$query = "SELECT u.id, u.username, u.roles, u.departments FROM users u WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch allowed modules based on roles
$roles = explode(',', $user['roles']);
$modules = [];

foreach ($roles as $role) {
    $role = trim($role);
    $query = "SELECT m.name FROM role_privileges rp 
              JOIN modules m ON rp.module_id = m.id 
              WHERE rp.role_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['name'], $modules)) {
            $modules[] = $row['name'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="styles.css"> <!-- Add Bootstrap or custom styles -->
</head>
<body>
    <nav>
        <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
        <a href="logout.php">Logout</a>
    </nav>
    
    <div class="container">
        <aside>
            <h3>Accessible Modules</h3>
            <ul>
                <?php foreach ($modules as $module): ?>
                    <li><?php echo htmlspecialchars($module); ?></li>
                <?php endforeach; ?>
            </ul>
        </aside>
        
        <main>
            <h2>Dashboard</h2>
            <p>Your assigned roles: <?php echo htmlspecialchars($user['roles']); ?></p>
            <p>Your departments: <?php echo htmlspecialchars($user['departments']); ?></p>
        </main>
    </div>
</body>
</html>
