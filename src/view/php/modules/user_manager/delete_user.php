<?php

/**
 * Deletes or archives user accounts in the Inventory Management System.
 * 
 * This script handles both soft deletion (archiving) and permanent deletion of one or multiple user accounts.
 * It processes input data to identify the user(s) to delete, performs validation and permission checks,
 * logs actions in an audit log, and returns a JSON response indicating the success or failure of the operation.
 * The script prevents self-deletion and ensures only authorized users can perform deletions.
 */
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Ensure clean output buffer
ob_start();

// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

/**
 * Performs authentication check to ensure the user is logged in before proceeding with deletion.
 */
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if the RBACService class exists
if (!class_exists('RBACService')) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'RBACService class not found. There may be an issue with your server configuration.'
    ]);
    exit();
}

// Initialize RBAC Service
try {
    $rbac = new RBACService($pdo, $_SESSION['user_id']);
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => 'Error initializing RBAC service: ' . $e->getMessage()
    ]);
    exit();
}

try {
    /**
     * Begins a database transaction to ensure data consistency during user deletion or archiving.
     */
    $pdo->beginTransaction();

    // Set the current user ID for the trigger
    $pdo->exec("SET @current_user_id = " . $_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'User Management'");

    // Determine if this is a permanent deletion
    $isPermanent = isset($_POST['permanent']) && $_POST['permanent'] == 1;

    /**
     * Handles the deletion or archiving of a single user account based on the provided user ID.
     * Supports both permanent deletion (for archived users) and soft deletion (archiving active users).
     */
    if (isset($_POST['user_id'])) {
        $targetUserId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

        if ($targetUserId === false) {
            throw new Exception("Invalid user ID");
        }

        // Prevent self-deletion
        if ($targetUserId === $_SESSION['user_id']) {
            throw new Exception("Cannot delete your own account");
        }

        // Check for deletion status
        $statusMessage = $isPermanent ? "Permanently deleted user" : "Archived user";
        $actionType = $isPermanent ? "delete" : "remove";

        if ($isPermanent) {
            // For permanent deletion, check that the user is archived (is_disabled=1)
            $checkStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? AND is_disabled = 1");
            $checkStmt->execute([$targetUserId]);
            $userData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$userData) {
                throw new Exception("User not found or not archived for permanent deletion");
            }

            // Create a single audit log entry for permanent deletion
            $details = "Permanently deleted user ID: $targetUserId";
            $oldValues = json_encode([
                'id' => $userData['id'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email']
            ]);
 
            // Permanently delete the archived user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_disabled = 1");
            if (!$stmt->execute([$targetUserId])) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Failed to permanently delete user: " . $errorInfo[2]);
            }
            $rowCount = $stmt->rowCount();
            if ($rowCount === 0) {
                throw new Exception("User not found or not archived for permanent deletion");
            }
        } else {
            // Soft delete: user must be active (is_disabled=0)
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_disabled = 0");
            $checkStmt->execute([$targetUserId]);
            if (!$checkStmt->fetch()) {
                throw new Exception("User not found or already archived");
            }

            // For soft delete: update is_disabled to 1 and status to 'Inactive'
            $stmt = $pdo->prepare("
                UPDATE users 
                SET is_disabled = 1,
                    status = 'Inactive'
                WHERE id = ? 
                AND is_disabled = 0
            ");
            if (!$stmt->execute([$targetUserId])) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Failed to archive user: " . $errorInfo[2]);
            }
            $rowCount = $stmt->rowCount();
            if ($rowCount === 0) {
                throw new Exception("User not found or already archived");
            }
        }
    }
    /**
     * Handles the bulk deletion or archiving of multiple user accounts based on the provided array of user IDs.
     * Supports both permanent deletion (for archived users) and soft deletion (archiving active users).
     */
    else if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
        $targetUserIds = array_filter(array_map('intval', $_POST['user_ids']));

        if (empty($targetUserIds)) {
            throw new Exception("No valid user IDs provided");
        }

        // Prevent self-deletion in bulk
        if (in_array($_SESSION['user_id'], $targetUserIds)) {
            throw new Exception("Cannot delete your own account");
        }

        $placeholders = str_repeat('?,', count($targetUserIds) - 1) . '?';

        if ($isPermanent) {
            // Permanent deletion in bulk: delete only those archived (is_disabled=1)
            // First get user data for audit logs
            $checkStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id IN ($placeholders) AND is_disabled = 1");
            $checkStmt->execute($targetUserIds);
            $usersToDelete = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($usersToDelete)) {
                throw new Exception("No users found or none archived for permanent deletion");
            }
            
            // Create audit logs before deletion for all users
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, 
                    EntityID, 
                    Action, 
                    Details, 
                    OldVal, 
                    NewVal, 
                    Module, 
                    Status, 
                    Date_Time
                ) VALUES (?, ?, ?, ?, ?, NULL, 'User Management', 'Successful', NOW())
            ");
            
            foreach ($usersToDelete as $user) {
                $details = "Permanently deleted user ID: " . $user['id'];
                $oldValues = json_encode([
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email']
                ]);
                
                $auditStmt->execute([$_SESSION['user_id'], $user['id'], 'delete', $details, $oldValues]);
            }
            
            // Now perform the actual deletion
            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND is_disabled = 1");
            if (!$stmt->execute($targetUserIds)) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Failed to permanently delete users: " . $errorInfo[2]);
            }
            $rowCount = $stmt->rowCount();
            if ($rowCount === 0) {
                throw new Exception("No users were deleted");
            }
        } else {
            // Soft delete in bulk: update only active users (is_disabled=0)
            $stmt = $pdo->prepare("
                UPDATE users 
                SET is_disabled = 1,
                    status = 'Inactive'
                WHERE id IN ($placeholders)
                AND is_disabled = 0
            ");
            if (!$stmt->execute($targetUserIds)) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Failed to archive users: " . $errorInfo[2]);
            }
            $rowCount = $stmt->rowCount();
            if ($rowCount === 0) {
                throw new Exception("No users found or all users already archived");
            }
        }
    } else {
        throw new Exception("No user IDs provided");
    }

    $pdo->commit();
    // after $pdo->commit();
    echo json_encode([
        'status'  => 'success',
        'success' => true,
        'message' => isset($_POST['user_ids'])
            ? ($isPermanent
                ? "$rowCount users have been permanently deleted"
                : "$rowCount users have been archived successfully"
            )
            : ($isPermanent
                ? "User has been permanently deleted"
                : "User has been archived successfully"
            )
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Delete user error: " . $e->getMessage());
    echo json_encode([
        'status'  => 'error',
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
