<?php
/**
 * Restore Role Module
 *
 * This file provides functionality to restore previously deleted roles in the system. It handles the recovery of role data, including associated permissions and settings. The module ensures proper validation, user authorization, and maintains data consistency during the restoration process.
 *
 * @package    InventoryManagementSystem
 * @subpackage RolesAndPrivilegeManager
 * @author     TMDD Interns 25'
 */
session_start();
require_once('../../../../../../config/ims-tmdd.php');

/**
 * Validate Request Method
 *
 * Ensures that the request method is POST to prevent unauthorized or incorrect access.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

/**
 * Check for Role ID(s)
 *
 * Verifies that either a single role ID or an array of role IDs is provided in the request.
 */
if (!isset($_POST['id']) && !isset($_POST['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No role ID(s) specified.']);
    exit();
}

/**
 * Set User ID for Audit
 *
 * Retrieves the user ID from the session for audit purposes and sets it in the database context.
 */
$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
    $pdo->exec("SET @current_user_id = " . (int)$userId);
} else {
    $pdo->exec("SET @current_user_id = NULL");
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

/**
 * Handle Role Restoration
 *
 * Manages the restoration process for roles, including both single and bulk restorations.
 * It handles database transactions, verifies role status, logs actions, and formats privilege data.
 */
try {
    // Start transaction
    $pdo->beginTransaction();

    // Handle bulk restore
    if (isset($_POST['ids']) && is_array($_POST['ids'])) {
        $roleIds = array_map('intval', $_POST['ids']);
        $successCount = 0;
        $failedCount = 0;
        $failedRoles = [];

        foreach ($roleIds as $role_id) {
            // Verify that the role exists and is currently disabled
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_disabled = 1");
            $stmt->execute([$role_id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$role) {
                $failedCount++;
                $failedRoles[] = $role_id;
                continue;
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
            
            // Format old privileges by module
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

            // Restore the role
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
                
                // Format new privileges
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

                // Log the action
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

                // Log in role_changes table
                $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName, NewRoleName) VALUES (?, ?, 'Restore', ?, ?)");
                $stmt->execute([
                    $userId,
                    $role_id,
                    $role['role_name'],
                    $role['role_name']
                ]);

                $successCount++;
            } else {
                $failedCount++;
                $failedRoles[] = $role_id;
            }
        }

        $pdo->commit();
        
        if ($failedCount === 0) {
            echo json_encode(['success' => true, 'message' => "Successfully restored {$successCount} role(s)."]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => "Restored {$successCount} role(s), failed to restore {$failedCount} role(s).",
                'failed_roles' => $failedRoles
            ]);
        }
        exit();
    }

    // Handle single role restore
    $role_id = intval($_POST['id']);

    // Verify that the role exists and is currently disabled
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
    
    // Format old privileges by module
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

    // Restore the role
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
        
        // Format new privileges
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

        // Log the action
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

        // Log in role_changes table
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
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Check for the specific duplicate entry error for roles
    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'uq_roles_active') !== false) {
        echo json_encode([
            'status' => 'error',
            'message' => 'A role with the same name is already active. Please check existing roles before restoring.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 