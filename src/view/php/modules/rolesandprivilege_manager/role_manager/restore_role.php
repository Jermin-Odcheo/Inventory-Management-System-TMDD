<?php
session_start();
require_once('../../../../../../config/ims-tmdd.php');

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

    // Verify that the role exists and is currently disabled.
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_disabled = 1");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found or is not archived.']);
        exit();
    }

    // Store role data for audit log
    $stmtOldPrivs = $pdo->prepare("
        SELECT m.module_name, p.priv_name 
        FROM role_module_privileges rmp
        JOIN modules m ON m.id = rmp.module_id
        JOIN privileges p ON p.id = rmp.privilege_id
        WHERE rmp.role_id = ?
        ORDER BY m.module_name, p.priv_name
    ");
    $stmtOldPrivs->execute([$role_id]);
    $oldPrivilegesData = $stmtOldPrivs->fetchAll(PDO::FETCH_ASSOC);
    
    // Format old privileges by module for better readability
    $formattedOldPrivileges = [];
    foreach ($oldPrivilegesData as $priv) {
        if (!isset($formattedOldPrivileges[$priv['module_name']])) {
            $formattedOldPrivileges[$priv['module_name']] = [];
        }
        $formattedOldPrivileges[$priv['module_name']][] = $priv['priv_name'];
    }
    
    $oldValue = json_encode([
        'role_id' => $role['id'],
        'role_name' => $role['role_name'],
        'modules_and_privileges' => $formattedOldPrivileges
    ]);

    // Restore the role by setting is_disabled to 0
    $stmt = $pdo->prepare("UPDATE roles SET is_disabled = 0 WHERE id = ?");
    if ($stmt->execute([$role_id])) {
        // Get updated role data for audit log
        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $updatedRole = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get updated privileges
        $stmtNewPrivs = $pdo->prepare("
            SELECT m.module_name, p.priv_name 
            FROM role_module_privileges rmp
            JOIN modules m ON m.id = rmp.module_id
            JOIN privileges p ON p.id = rmp.privilege_id
            WHERE rmp.role_id = ?
            ORDER BY m.module_name, p.priv_name
        ");
        $stmtNewPrivs->execute([$role_id]);
        $newPrivilegesData = $stmtNewPrivs->fetchAll(PDO::FETCH_ASSOC);
        
        // Format new privileges by module for better readability
        $formattedNewPrivileges = [];
        foreach ($newPrivilegesData as $priv) {
            if (!isset($formattedNewPrivileges[$priv['module_name']])) {
                $formattedNewPrivileges[$priv['module_name']] = [];
            }
            $formattedNewPrivileges[$priv['module_name']][] = $priv['priv_name'];
        }

        $newValue = json_encode([
            'role_id' => $updatedRole['id'],
            'role_name' => $updatedRole['role_name'],
            'modules_and_privileges' => $formattedNewPrivileges
        ]);

        // Log the action in the audit_log table
        $stmt = $pdo->prepare("INSERT INTO audit_log 
            (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status) 
            VALUES (?, ?, 'Restore', ?, ?, ?, 'Roles and Privileges', NOW(), 'Successful')");
        $stmt->execute([
            $userId,
            $role_id,
            "Role '{$role['role_name']}' has been restored",
            $oldValue,
            $newValue
        ]);

        // Log the restoration action in the role_changes table (keep for compatibility)
        $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, NewRoleName) VALUES (?, ?, 'Restore', ?, ?)");
        $stmt->execute([
            $userId,
            $role_id,
            $role['role_name'],
            $role['role_name']
        ]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Role restored successfully.']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to restore the role. Please try again.']);
    }
} 
catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Check for the specific duplicate entry error for roles
    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'uniq_active_role_name') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'A role with the same name is already active. Please rename the active role before restoring this role.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }

}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 