<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

header('Content-Type: application/json');

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Function to get user's roles
function getUserRoles($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.Role_Name 
            FROM roles r 
            JOIN user_roles ur ON r.Role_ID = ur.Role_ID 
            WHERE ur.User_ID = ?
        ");
        $stmt->execute([$userId]);
        $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $roles;
    } catch (Exception $e) {
        error_log("Error getting user roles: " . $e->getMessage());
        return [];
    }
}

// Function to check if user can delete target
function canDelete($currentUserRoles, $targetUserRoles) {
    error_log("Current user roles: " . print_r($currentUserRoles, true));
    error_log("Target user roles: " . print_r($targetUserRoles, true));
    
    // Super Admin can delete anyone
    if (in_array('Super Admin', $currentUserRoles)) {
        error_log("User is Super Admin - can delete");
        return true;
    }
    
    // Super User can only delete Regular Users
    if (in_array('Super User', $currentUserRoles)) {
        $canDelete = count($targetUserRoles) === 1 && in_array('Regular User', $targetUserRoles);
        error_log("User is Super User - can delete: " . ($canDelete ? 'yes' : 'no'));
        return $canDelete;
    }
    
    error_log("User has no delete permissions");
    return false;
}

try {
    $pdo->beginTransaction();
    
    // Set current user for audit logging
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    
    // Handle single user deletion
    if (isset($_POST['user_id']) && $_POST['action'] === 'soft_delete') {
        $targetUserId = $_POST['user_id'];
        
        // Get roles of both users
        $currentUserRoles = getUserRoles($pdo, $_SESSION['user_id']);
        $targetUserRoles = getUserRoles($pdo, $targetUserId);
        
        if (!canDelete($currentUserRoles, $targetUserRoles)) {
            throw new Exception("You don't have permission to delete this user.");
        }
        
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID = ?");
        if (!$stmt->execute([$targetUserId])) {
            throw new Exception("Failed to update user status.");
        }
        
        $_SESSION['delete_success'] = true; // Set success message flag
        
    } 
    // Handle bulk deletion
    else if (isset($_POST['user_ids']) && $_POST['action'] === 'soft_delete') {
        $targetUserIds = $_POST['user_ids'];
        $currentUserRoles = getUserRoles($pdo, $_SESSION['user_id']);
        
        foreach ($targetUserIds as $targetUserId) {
            $targetUserRoles = getUserRoles($pdo, $targetUserId);
            if (!canDelete($currentUserRoles, $targetUserRoles)) {
                throw new Exception("You don't have permission to delete one or more selected users.");
            }
        }
        
        $placeholders = str_repeat('?,', count($targetUserIds) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID IN ($placeholders)");
        if (!$stmt->execute($targetUserIds)) {
            throw new Exception("Failed to update users status.");
        }
        
        $_SESSION['delete_success'] = true; // Set success message flag
        $_SESSION['deleted_count'] = count($targetUserIds); // Store number of deleted users
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Delete user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
