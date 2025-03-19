<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

// Ensure clean output buffer
ob_start();

// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

// Initialize RBAC Service
$rbac = new RBACService($pdo, $_SESSION['user_id']);

try {
    // Check if user has delete permission
    if (!$rbac->hasPrivilege('User Management', 'Delete')) {
        throw new Exception("You don't have permission to delete users.");
    }

    $pdo->beginTransaction();

    // Set the current user ID for the trigger
    $pdo->exec("SET @current_user_id = " . $_SESSION['user_id']);
    $pdo->exec("SET @current_module = 'User Management'");

    // Determine if this is a permanent deletion
    $isPermanent = isset($_POST['permanent']) && $_POST['permanent'] == 1;

    // Handle single user deletion
    if (isset($_POST['user_id'])) {
        $targetUserId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);

        if ($targetUserId === false) {
            throw new Exception("Invalid user ID");
        }

        // Prevent self-deletion
        if ($targetUserId === $_SESSION['user_id']) {
            throw new Exception("Cannot delete your own account");
        }

        if ($isPermanent) {
            // For permanent deletion, check that the user is archived (is_disabled=1)
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_disabled = 1");
            $checkStmt->execute([$targetUserId]);
            if (!$checkStmt->fetch()) {
                throw new Exception("User not found or not archived for permanent deletion");
            }

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
    // Handle bulk deletion
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
            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND is_disabled = 1");
            if (!$stmt->execute($targetUserIds)) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Failed to permanently delete users: " . $errorInfo[2]);
            }
            $rowCount = $stmt->rowCount();
            if ($rowCount === 0) {
                throw new Exception("No users found or none archived for permanent deletion");
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
    echo json_encode([
        'success' => true,
        'message' => isset($_POST['user_ids']) ?
            ($isPermanent ? "$rowCount users have been permanently deleted" : "$rowCount users have been archived successfully") :
            ($isPermanent ? "User has been permanently deleted" : "User has been archived successfully")
    ]);


} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete user error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}
?>
