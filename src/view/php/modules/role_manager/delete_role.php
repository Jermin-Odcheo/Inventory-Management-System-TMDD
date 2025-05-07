<?php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

// Ensure the request method is POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Check if the role ID is provided in the POST data.
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'No role ID specified.']);
    exit();
}

$role_id = intval($_POST['id']);
$userId = $_SESSION['user_id'] ?? null;

// Set the user ID for audit purposes
if ($userId) {
    $pdo->exec("SET @current_user_id = " . (int)$userId);
} else {
    $pdo->exec("SET @current_user_id = NULL");
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Verify that the role exists.
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_disabled = 0");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found or already deleted.']);
        exit();
    }

    // Get comprehensive role data with modules and privileges for audit
    $modulePrivilegesSql = "
        SELECT 
            m.module_name,
            GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ') AS privileges
        FROM role_module_privileges rmp
        JOIN modules m ON m.id = rmp.module_id
        JOIN privileges p ON p.id = rmp.privilege_id
        WHERE rmp.role_id = ?
        GROUP BY m.module_name
        ORDER BY m.module_name
    ";
    
    $stmtModules = $pdo->prepare($modulePrivilegesSql);
    $stmtModules->execute([$role_id]);
    $modulePrivileges = $stmtModules->fetchAll(PDO::FETCH_ASSOC);
    
    // Format module privileges for better readability in logs
    $formattedModulePrivileges = [];
    foreach ($modulePrivileges as $mp) {
        $formattedModulePrivileges[$mp['module_name']] = $mp['privileges'];
    }
    
    // Create a comprehensive audit record
    $auditInfo = [
        'role_id' => $role['id'],
        'role_name' => $role['role_name'],
        'modules_and_privileges' => $formattedModulePrivileges
    ];
    
    $oldValue = json_encode($auditInfo, JSON_PRETTY_PRINT);
    $details = "Role '{$role['role_name']}' has been archived";
    // Soft delete - update is_disabled flag instead of deleting
    $stmt = $pdo->prepare("UPDATE roles SET is_disabled = 1 WHERE id = ?");
    if ($stmt->execute([$role_id])) {
        // Get updated role data for audit log
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $updatedRole = $stmt->fetch(PDO::FETCH_ASSOC);
        $newValue = json_encode($updatedRole);

        // Log the action in the audit_log table with comprehensive details
        $stmt = $pdo->prepare("INSERT INTO audit_log 
            (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status) 
            VALUES (?, ?, 'Remove', ?, ?, ?, 'Roles and Privileges', NOW(), 'Successful')");
        $stmt->execute([
            $userId,
            $role_id,
            $details,
            $oldValue,
            $newValue
        ]);

        // Log the deletion action in the role_changes table (keep for compatibility)
        $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, OldPrivileges) 
                               VALUES (?, ?, 'Delete', ?, ?)");
        $stmt->execute([
            $userId,
            $role_id,
            $role['role_name'],
            $oldValue
        ]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Role deleted successfully.']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete the role. Please try again.']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => "Database error: " . $e->getMessage()]);
}

exit();
?>
