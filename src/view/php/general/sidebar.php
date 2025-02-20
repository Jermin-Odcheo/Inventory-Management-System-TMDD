<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../../../config/ims-tmdd.php'; // Database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user roles
$query = "SELECT GROUP_CONCAT(r.role_name) AS roles 
          FROM users u
          LEFT JOIN user_roles ur ON u.id = ur.user_id
          LEFT JOIN roles r ON ur.role_id = r.id 
          WHERE u.id = ?
          GROUP BY u.id";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$roles = explode(',', $user['roles'] ?? '');

// Define module links
$module_links = [
    'Audit' => '../module/audit_manager/audit.php',
    'Equipment Management' => '../module/equipment_manager/equipment_manager.php',
    'Roles and Privileges' => '../module/role_manager/role_manager.php',
    'User Management' => '../module/user_manager/user_manager.php'
];

$modules = [];

// Fetch allowed modules based on roles
$query = "SELECT DISTINCT m.module_name 
          FROM role_module_privileges rp
          JOIN modules m ON rp.module_id = m.id
          JOIN roles r ON rp.role_id = r.id
          WHERE FIND_IN_SET(r.role_name, ?) > 0";
$stmt = $conn->prepare($query);
$roles_str = implode(',', $roles);
$stmt->bind_param("s", $roles_str);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $modules[] = $row['module_name'];
}
?>

<aside>
    <h3>Accessible Modules</h3>
    <ul>
        <?php if (!empty($modules)): ?>
            <?php foreach ($modules as $module): ?>
                <?php if (isset($module_links[$module])): ?>
                    <li><a href="<?php echo htmlspecialchars($module_links[$module]); ?>"><?php echo htmlspecialchars($module); ?></a></li>
                <?php else: ?>
                    <li><?php echo htmlspecialchars($module); ?> (No link assigned)</li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <li>No modules assigned.</li>
        <?php endif; ?>
    </ul>
</aside>
