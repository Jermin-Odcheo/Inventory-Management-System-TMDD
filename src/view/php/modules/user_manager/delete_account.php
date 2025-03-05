<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    try {
        // Set the current user ID for audit logging
        $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        $userId = $_SESSION['user_id'];

        // Get user details before deletion for audit log
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get user roles before deletion
        $stmt = $pdo->prepare("
            SELECT r.Role_Name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.Role_ID 
            WHERE ur.User_ID = ?
        ");
        $stmt->execute([$userId]);
        $userRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Prepare audit log data
        $oldValue = [
            'User_ID' => $userData['User_ID'],
            'Email' => $userData['Email'],
            'First_Name' => $userData['First_Name'],
            'Last_Name' => $userData['Last_Name'],
            'Department' => $userData['Department'],
            'Status' => $userData['Status'],
            'Roles' => implode(', ', $userRoles),
            'is_deleted' => '0'
        ];

        $newValue = $oldValue;
        $newValue['is_deleted'] = '1';
        
        // Insert into audit_log
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_log (
                UserID, 
                Module, 
                Action, 
                Details, 
                OldVal, 
                NewVal, 
                Status,
                EntityID
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $auditStmt->execute([
            $userId,
            'User Management',
            'soft delete',
            'User account has been moved to archive',
            json_encode($oldValue),
            json_encode($newValue),
            'Successful',
            $userId
        ]);
        
        // Delete user roles first (due to foreign key constraints)
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE User_ID = ?");
        $stmt->execute([$userId]);
        
        // Soft delete the user (set is_deleted to 1)
        $stmt = $pdo->prepare("UPDATE users SET is_disabled = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Commit transaction
        $pdo->commit();
        
        // Clear session
        session_destroy();
        
        echo json_encode(['success' => true]);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error deleting account: ' . $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
} 