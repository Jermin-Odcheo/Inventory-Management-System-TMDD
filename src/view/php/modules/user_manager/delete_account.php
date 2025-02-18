<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

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
        
        // Delete user roles first (due to foreign key constraints)
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE User_ID = ?");
        $stmt->execute([$userId]);
        
        // Soft delete the user (set is_deleted to 1)
        $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE User_ID = ?");
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