<?php
/**
 * @file undo.php
 * @brief handles the undoing of the last role change action performed by a logged-in user
 *
 * This script handles the undoing of the last role change action performed by a logged-in user.
 * It supports reversing actions such as adding, modifying, or deleting roles in the system.
 * The script checks for the last action that hasn't been undone and performs the necessary
 * database operations to revert it. The action is then marked as undone in the database.
 *
 */
session_start();
header('Content-Type: application/json');
require_once('../../../../../config/ims-tmdd.php');
/**
 * @var int|null $user_id The user ID of the current user.
 */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}
/**
 * @var PDOStatement $stmt The prepared statement to fetch the last action from the role_changes table.
 */
try {
    // Fetch the last action from the role_changes table that hasn't been undone
    $stmt = $pdo->prepare("SELECT * FROM role_changes WHERE UserID = ? AND IsUndone = 0 ORDER BY ChangeID DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $lastAction = $stmt->fetch(PDO::FETCH_ASSOC);
/**
 * @var bool $lastAction The last action from the role_changes table.
 */
    if (!$lastAction) {
        echo json_encode(['success' => false, 'message' => 'No actions to undo.']);
        exit();
    }
/**
 * @var string $lastAction['Action'] The action type of the last action.
 */
    // Reverse the action based on the action type
    switch ($lastAction['Action']) {
        case 'Add':
            // If the last action was an addition, delete the added role
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$lastAction['RoleID']]);
            break;
/**
 * @var array $oldPrivileges The old privileges of the last action.
 */
        case 'Modified':
            // If the last action was a modification, revert to the old role name and privileges
            $stmt = $pdo->prepare("UPDATE roles SET Role_Name = ? WHERE id = ?");
            $stmt->execute([$lastAction['OldRoleName'], $lastAction['RoleID']]);
/**
 * @var PDOStatement $stmtDelete The prepared statement to delete the old privileges.
 */
            // Revert privileges
            $oldPrivileges = json_decode($lastAction['OldPrivileges'], true);
            $stmtDelete = $pdo->prepare("DELETE FROM role_module_privileges WHERE Role_ID = ?");
            $stmtDelete->execute([$lastAction['RoleID']]);
/**
 * @var PDOStatement $stmtInsert The prepared statement to insert the old privileges.
 */
            $stmtInsert = $pdo->prepare("INSERT INTO role_module_privileges (Role_ID, Privilege_ID) VALUES (?, ?)");
            foreach ($oldPrivileges as $privilegeID) {
                $stmtInsert->execute([$lastAction['RoleID'], $privilegeID]);
            }
            break;
/**
 * @var PDOStatement $stmt The prepared statement to insert the old role.
 */
        case 'Delete':
            // If the last action was a deletion, reinsert the deleted role
            $stmt = $pdo->prepare("INSERT INTO roles (id, Role_Name) VALUES (?, ?)");
            $stmt->execute([$lastAction['RoleID'], $lastAction['OldRoleName']]);
            break;
/**
 * @var string $lastAction['Action'] The action type of the last action.
 */
        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported action type.']);
            exit();
    }
/**
 * @var PDOStatement $stmt The prepared statement to mark the action as undone.
 */
    // Mark the action as undone
    $stmt = $pdo->prepare("UPDATE role_changes SET IsUndone = 1 WHERE ChangeID = ?");
    $stmt->execute([$lastAction['ChangeID']]);
/**
 * @var string $lastAction['ChangeID'] The change ID of the last action.
 */
    echo json_encode(['success' => true, 'message' => 'Action undone successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
