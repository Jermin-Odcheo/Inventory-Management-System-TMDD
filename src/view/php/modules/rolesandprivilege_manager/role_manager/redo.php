<?php
/**
 * Redo Module
 *
 * This file provides functionality to redo previously undone role-related actions in the system. It handles the reapplication of role modifications, ensuring that changes can be restored while maintaining data integrity. The module supports audit trails and integrates with other modules to maintain system consistency.
 *
 * @package    InventoryManagementSystem
 * @subpackage RolesAndPrivilegeManager
 * @author     TMDD Interns 25'
 */
session_start();
require_once('../../../../../../config/ims-tmdd.php');

/**
 * Check User Authentication
 *
 * Ensures that the user is logged in by checking for the presence of a user ID in the session.
 */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

/**
 * Handle Redo Operation
 *
 * Fetches the last undone action for the current user and reapplies it based on the action type.
 * Updates the database to mark the action as not undone after successful reapplication.
 */
try {
    // Fetch the last undone action from the role_changes table
    $stmt = $pdo->prepare("SELECT * FROM role_changes WHERE UserID = ? AND IsUndone = 1 ORDER BY ChangeID DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $lastUndoAction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lastUndoAction) {
        echo json_encode(['success' => false, 'message' => 'No actions to redo.']);
        exit();
    }

    // Reapply the action based on the original action type
    switch ($lastUndoAction['Action']) {
        case 'Add':
            // If the original action was an addition, reinsert the role
            $stmt = $pdo->prepare("INSERT INTO roles (id, Role_Name) VALUES (?, ?)");
            $stmt->execute([$lastUndoAction['RoleID'], $lastUndoAction['NewRoleName']]);
            break;

        case 'Modified':
            // If the original action was a modification, reapply the new role name and privileges
            $stmt = $pdo->prepare("UPDATE roles SET Role_Name = ? WHERE id = ?");
            $stmt->execute([$lastUndoAction['NewRoleName'], $lastUndoAction['RoleID']]);

            // Reapply privileges
            $newPrivileges = json_decode($lastUndoAction['NewPrivileges'], true);
            $stmtDelete = $pdo->prepare("DELETE FROM role_module_privileges WHERE Role_ID = ?");
            $stmtDelete->execute([$lastUndoAction['RoleID']]);

            $stmtInsert = $pdo->prepare("INSERT INTO role_module_privileges (Role_ID, Privilege_ID) VALUES (?, ?)");
            foreach ($newPrivileges as $privilegeID) {
                $stmtInsert->execute([$lastUndoAction['RoleID'], $privilegeID]);
            }
            break;

        case 'Delete':
            // If the original action was a deletion, delete the role again
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$lastUndoAction['RoleID']]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported action type.']);
            exit();
    }

    // Mark the action as not undone
    $stmt = $pdo->prepare("UPDATE role_changes SET IsUndone = 0 WHERE ChangeID = ?");
    $stmt->execute([$lastUndoAction['ChangeID']]);

    echo json_encode(['success' => true, 'message' => 'Action redone successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
