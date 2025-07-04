<?php
/**
 * Permanent Delete Role Module
 *
 * This file provides functionality to permanently delete roles from the system. It handles the complete removal of role data, including associated permissions and settings. The module ensures proper validation, user authorization, and maintains data integrity during the deletion process.
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
 * Handle Permanent Role Deletion
 *
 * Manages the permanent deletion process for roles, including both single and bulk deletions.
 * It handles database transactions, verifies role status, deletes associated data, and logs actions.
 */
try {
    // Start transaction
    $pdo->beginTransaction();

    // Handle bulk delete
    if (isset($_POST['ids']) && is_array($_POST['ids'])) {
        $roleIds = array_map('intval', $_POST['ids']);
        $successCount = 0;
        $failedCount = 0;
        $failedRoles = [];

        foreach ($roleIds as $role_id) {
            // Verify that the role exists and is currently disabled (archived)
            $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_disabled = 1");
            $stmt->execute([$role_id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$role) {
                $failedCount++;
                $failedRoles[] = $role_id;
                continue;
            }

            // Store role data for audit log
            $oldValue = json_encode($role);

            // 1. Delete associated role_module_privileges
            $stmt = $pdo->prepare("DELETE FROM role_module_privileges WHERE role_id = ?");
            $stmt->execute([$role_id]);

            // 2. Permanently delete the role
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            if ($stmt->execute([$role_id])) {
                // Log the action in the audit_log table
                $stmt = $pdo->prepare("INSERT INTO audit_log 
                    (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status) 
                    VALUES (?, ?, 'Delete', ?, ?, NULL, 'Roles and Privileges', NOW(), 'Successful')");
                $stmt->execute([
                    $userId,
                    $role_id,
                    "Role '{$role['role_name']}' has been permanently deleted",
                    $oldValue
                ]);

                // Log the permanent deletion action in the role_changes table
                $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName) VALUES (?, ?, 'PermanentDelete', ?)");
                $stmt->execute([
                    $userId,
                    $role_id,
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
            echo json_encode(['success' => true, 'message' => "Successfully deleted {$successCount} role(s)."]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => "Deleted {$successCount} role(s), failed to delete {$failedCount} role(s).",
                'failed_roles' => $failedRoles
            ]);
        }
        exit();
    }

    // Handle single role delete
    $role_id = intval($_POST['id']);

    // Verify that the role exists and is currently disabled (archived)
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ? AND is_disabled = 1");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        echo json_encode(['success' => false, 'message' => 'Role not found or is not archived.']);
        exit();
    }

    // Store role data for audit log
    $oldValue = json_encode($role);

    // 1. Delete associated role_module_privileges
    $stmt = $pdo->prepare("DELETE FROM role_module_privileges WHERE role_id = ?");
    $stmt->execute([$role_id]);

    // 2. Permanently delete the role
    $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    if ($stmt->execute([$role_id])) {
        // Log the action in the audit_log table
        $stmt = $pdo->prepare("INSERT INTO audit_log 
            (UserID, EntityID, Action, Details, OldVal, NewVal, Module, Date_Time, Status) 
            VALUES (?, ?, 'Delete', ?, ?, NULL, 'Roles and Privileges', NOW(), 'Successful')");
        $stmt->execute([
            $userId,
            $role_id,
            "Role '{$role['role_name']}' has been permanently deleted",
            $oldValue
        ]);

        // Log the permanent deletion action in the role_changes table
        $stmt = $pdo->prepare("INSERT INTO role_changes (UserID, RoleID, Action, OldRoleName) VALUES (?, ?, 'PermanentDelete', ?)");
        $stmt->execute([
            $userId,
            $role_id,
            $role['role_name']
        ]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Role permanently deleted successfully.']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to permanently delete the role. Please try again.']);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => "Database error: " . $e->getMessage()]);
}

exit();
