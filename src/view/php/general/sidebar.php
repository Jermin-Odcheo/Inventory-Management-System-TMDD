<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include $_SERVER['DOCUMENT_ROOT'] . '/IMS-TMDD RABAC Tester/config/ims-tmdd.php';

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
$stmt->close();

$roles = explode(',', $user['roles'] ?? '');

// Define module links
$module_links = [
    'Audit' => '/IMS-TMDD RABAC Tester/src/view/php/module/audit_manager/audit.php',
    'Equipment Management' => '/IMS-TMDD RABAC Tester/src/view/php/module/equipment_manager/equipment_manager.php',
    'Roles and Privileges' => '/IMS-TMDD RABAC Tester/src/view/php/module/role_manager/role_manager.php',
    'User Management' => '/IMS-TMDD RABAC Tester/src/view/php/module/user_manager/user_manager.php'
];


// Fetch user role IDs
$query = "SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$role_ids = [];
while ($row = $result->fetch_assoc()) {
    $role_ids[] = $row['role_id'];
}

if (empty($role_ids)) {
    die("No roles assigned.");
}

$query = "SELECT m.module_name
         FROM role_module_privileges rp
         JOIN modules m ON rp.module_id = m.id
         WHERE rp.role_id IN (" . implode(',', array_fill(0, count($role_ids), '?')) . ")
         GROUP BY m.module_name";

$stmt = $conn->prepare($query);
$stmt->bind_param(str_repeat('i', count($role_ids)), ...$role_ids);
$stmt->execute();
$result = $stmt->get_result();

$modules = [];
while ($row = $result->fetch_assoc()) {
    $modules[] = $row['module_name'];
}
?>

<!-- Sidebar -->
<div class="list-group">
<a href="/IMS-TMDD RABAC Tester/src/view/php/clients/dashboard.php" 
   class="list-group-item list-group-item-action active">
   Dashboard
</a>
    <h5 class="mt-3">Your Accessible Modules</h5>
    <?php if (!empty($modules)): ?>
        <?php foreach ($modules as $module): ?>
            <a href="<?php echo $module_links[$module] ?? '#'; ?>" class="list-group-item list-group-item-action">
                <?php echo htmlspecialchars($module); ?>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No modules available for your roles.</p>
    <?php endif; ?>
</div>
