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
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
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

        // Check if user exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_disabled = 0");
        $checkStmt->execute([$targetUserId]);
        if (!$checkStmt->fetch()) {
            throw new Exception("User not found or already archived");
        }

        // For soft delete
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

        // For soft delete
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
    } else {
        throw new Exception("No user IDs provided");
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => isset($_POST['user_ids']) ?
            "$rowCount users have been archived successfully" :
            "User has been archived successfully"
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