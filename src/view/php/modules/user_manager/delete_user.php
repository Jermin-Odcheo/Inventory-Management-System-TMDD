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
    
    if (in_array('Super Admin', $currentUserRoles)) {
        return true;
    }
    
    if (in_array('Super User', $currentUserRoles)) {
        return count($targetUserRoles) === 1 && in_array('Regular User', $targetUserRoles);
    }
    
    return false;
}

try {
    $pdo->beginTransaction();
    
    // Set current user for audit logging
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
    
    // Check if this is a permanent delete
    $isPermanentDelete = isset($_POST['permanent']) && $_POST['permanent'] === '1';
    
    // Handle single user deletion
    if (isset($_POST['user_id'])) {
        $targetUserId = $_POST['user_id'];
        
        // Get roles of both users
        $currentUserRoles = getUserRoles($pdo, $_SESSION['user_id']);
        $targetUserRoles = getUserRoles($pdo, $targetUserId);
        
        if (!canDelete($currentUserRoles, $targetUserRoles)) {
            throw new Exception("You don't have permission to delete this user.");
        }
        
        if ($isPermanentDelete) {
            // For permanent delete
            $stmt = $pdo->prepare("DELETE FROM users WHERE User_ID = ? AND is_deleted = 1");
        } else {
            // For soft delete
            $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID = ?");
        }
        
        if (!$stmt->execute([$targetUserId])) {
            throw new Exception("Failed to " . ($isPermanentDelete ? "permanently delete" : "soft delete") . " user.");
        }
        
        $_SESSION['delete_success'] = true;
        
    } 
    // Handle bulk deletion
    else if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
        $targetUserIds = $_POST['user_ids'];
        $currentUserRoles = getUserRoles($pdo, $_SESSION['user_id']);
        
        foreach ($targetUserIds as $targetUserId) {
            $targetUserRoles = getUserRoles($pdo, $targetUserId);
            if (!canDelete($currentUserRoles, $targetUserRoles)) {
                throw new Exception("You don't have permission to delete one or more selected users.");
            }
        }
        
        $placeholders = str_repeat('?,', count($targetUserIds) - 1) . '?';
        
        if ($isPermanentDelete) {
            // For permanent delete
            $stmt = $pdo->prepare("DELETE FROM users WHERE User_ID IN ($placeholders) AND is_deleted = 1");
        } else {
            // For soft delete
            $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID IN ($placeholders)");
        }
        
        if (!$stmt->execute($targetUserIds)) {
            throw new Exception("Failed to " . ($isPermanentDelete ? "permanently delete" : "soft delete") . " users.");
        }
        
        $_SESSION['delete_success'] = true;
        $_SESSION['deleted_count'] = count($targetUserIds);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Delete user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
