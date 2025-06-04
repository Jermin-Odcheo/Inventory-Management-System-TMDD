<?php

/**
 * Archives user accounts in the Inventory Management System.
 * 
 * This script handles the archiving of one or multiple user accounts by setting their 'is_disabled' status
 * to 1. It processes input data to identify the user(s) to archive, performs validation and permission checks,
 * and returns a JSON response indicating the success or failure of the operation. The script prevents self-deletion
 * and logs actions for debugging purposes.
 */
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure clean output buffer
ob_start();

// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Debug function
function debugLog($message, $data = null) {
    error_log(print_r($message, true));
    if ($data !== null) {
        error_log(print_r($data, true));
    }
}

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

/**
 * Initializes RBAC service to check if the user has the necessary permission to remove accounts.
 */
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
     * Begins a database transaction to ensure data consistency during user account archiving.
     */
    $pdo->beginTransaction();

    // Debug incoming data
    debugLog("POST data:", $_POST);
    debugLog("Session user_id:", $_SESSION['user_id']);

    // Check if user has remove permission
    if (!$rbac->hasPrivilege('User Management', 'Remove')) {
        throw new Exception("You don't have permission to remove users.");
    }

    /**
     * Handles the archiving of a single user account based on the provided user ID.
     * Prevents self-deletion and checks if the user exists before proceeding.
     */
    if (isset($_POST['user_id'])) {
        $targetUserId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        debugLog("Target User ID:", $targetUserId);

        if ($targetUserId === false) {
            throw new Exception("Invalid user ID");
        }

        // Prevent self-deletion
        if ($targetUserId === $_SESSION['user_id']) {
            throw new Exception("Cannot delete your own account");
        }

        // Debug the user existence
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$targetUserId]);
        if (!$checkStmt->fetch()) {
            throw new Exception("User not found");
        }

        // For soft delete
        $stmt = $pdo->prepare("
            UPDATE users 
            SET is_disabled = 1,
                status = 'Inactive'
            WHERE id = ?
        ");

        debugLog("SQL Query:", $stmt->queryString);
        debugLog("Parameters:", [$targetUserId]);

        if (!$stmt->execute([$targetUserId])) {
            $errorInfo = $stmt->errorInfo();
            debugLog("SQL Error:", $errorInfo);
            throw new Exception("Database error: " . $errorInfo[2]);
        }

        $rowCount = $stmt->rowCount();
        debugLog("Affected rows:", $rowCount);

        if ($rowCount === 0) {
            throw new Exception("User not found or already archived");
        }

    }
    /**
     * Handles the bulk archiving of multiple user accounts based on the provided array of user IDs.
     * Prevents self-deletion and validates the input IDs before proceeding.
     */
    else if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
        $targetUserIds = array_filter(array_map('intval', $_POST['user_ids']));
        debugLog("Target User IDs:", $targetUserIds);

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
        ");

        debugLog("SQL Query:", $stmt->queryString);
        debugLog("Parameters:", $targetUserIds);

        if (!$stmt->execute($targetUserIds)) {
            $errorInfo = $stmt->errorInfo();
            debugLog("SQL Error:", $errorInfo);
            throw new Exception("Database error: " . $errorInfo[2]);
        }

        $rowCount = $stmt->rowCount();
        debugLog("Affected rows:", $rowCount);

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
    debugLog("Error:", $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Check for any database triggers
try {
    $triggers = $pdo->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);
    debugLog("Database triggers:", $triggers);
} catch (Exception $e) {
    debugLog("Error fetching triggers:", $e->getMessage());
}
?>