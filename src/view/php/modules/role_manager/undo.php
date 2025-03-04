<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

try {
    // Fetch the last action from the role_changes table that hasn't been undone
    $stmt = $pdo->prepare("SELECT * FROM role_changes WHERE UserID = ? AND IsUndone = 0 ORDER BY ChangeID DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $lastAction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lastAction) {
        echo json_encode(['success' => false, 'message' => 'No actions to undo.']);
        exit();
    }

    // Reverse the action based on the action type
    switch ($lastAction['Action']) {
        case 'Add':
            // If the last action was an addition, delete the added role
            $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$lastAction['RoleID']]);
            break;

        case 'Modified':
            // If the last action was a modification, revert to the old role name and privileges
            $stmt = $pdo->prepare("UPDATE roles SET Role_Name = ? WHERE id = ?");
            $stmt->execute([$lastAction['OldRoleName'], $lastAction['RoleID']]);

            // Revert privileges
            $oldPrivileges = json_decode($lastAction['OldPrivileges'], true);
            $stmtDelete = $pdo->prepare("DELETE FROM role_module_privileges WHERE Role_ID = ?");
            $stmtDelete->execute([$lastAction['RoleID']]);

            $stmtInsert = $pdo->prepare("INSERT INTO role_module_privileges (Role_ID, Privilege_ID) VALUES (?, ?)");
            foreach ($oldPrivileges as $privilegeID) {
                $stmtInsert->execute([$lastAction['RoleID'], $privilegeID]);
            }
            break;

        case 'Delete':
            // If the last action was a deletion, reinsert the deleted role
            $stmt = $pdo->prepare("INSERT INTO roles (id, Role_Name) VALUES (?, ?)");
            $stmt->execute([$lastAction['RoleID'], $lastAction['OldRoleName']]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unsupported action type.']);
            exit();
    }

    // Mark the action as undone
    $stmt = $pdo->prepare("UPDATE role_changes SET IsUndone = 1 WHERE ChangeID = ?");
    $stmt->execute([$lastAction['ChangeID']]);

    echo json_encode(['success' => true, 'message' => 'Action undone successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
