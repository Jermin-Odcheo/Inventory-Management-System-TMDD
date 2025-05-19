<?php
// update_user_department.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

// Auth check
$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// For debugging
$requestData = file_get_contents('php://input');
error_log("Received data: " . $requestData);

$data = json_decode($requestData, true);
if (!isset($data['userId']) || !isset($data['departmentId'])) {
    echo json_encode(['success' => false, 'error' => 'Missing essential parameters']);
    exit;
}

$userId = (int)$data['userId'];
// Treat missing or empty oldRoleId as 0
$oldRoleId = isset($data['oldRoleId']) && $data['oldRoleId'] !== ''
    ? (int)$data['oldRoleId']
    : 0;

// Convert roleIds to integers, using 0 for null or non-numeric
$roleIds = [];
if (isset($data['roleIds']) && is_array($data['roleIds'])) {
    foreach ($data['roleIds'] as $rid) {
        $roleIds[] = is_numeric($rid) ? (int)$rid : 0;
    }
}

$departmentId = (int)$data['departmentId'];
$trackChanges = $data['trackChanges'] ?? false;

error_log("Processed data: userId=$userId, oldRoleId=$oldRoleId, departmentId=$departmentId, roleIds=" . json_encode($roleIds));

// Add to audit log
function addToAuditLog($actionType, $details) {
    global $pdo, $currentUserId, $userId, $departmentId, $oldRoleId, $roleIds;
    
    try {
        // Check if the audit_log table exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'audit_log'
        ");
        $stmt->execute();
        $tableExists = (int)$stmt->fetchColumn() > 0;
        
        if (!$tableExists) {
            // Table doesn't exist, just return true without logging
            error_log("Audit log table doesn't exist - skipping audit logging");
            return true;
        }
        
        // Get target user information
        $targetUserQuery = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $targetUserQuery->execute([$userId]);
        $targetUsername = $targetUserQuery->fetchColumn() ?: 'Unknown User';
        
        // Get old role name
        $oldRoleName = 'No Role';
        if ($oldRoleId > 0) {
            $roleQuery = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
            $roleQuery->execute([$oldRoleId]);
            $oldRoleName = $roleQuery->fetchColumn() ?: 'Unknown Role';
        }
        
        // Get new role names
        $newRoleNames = [];
        if (!empty($roleIds)) {
            foreach ($roleIds as $roleId) {
                if ($roleId > 0) {
                    $roleQuery = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                    $roleQuery->execute([$roleId]);
                    $roleName = $roleQuery->fetchColumn();
                    if ($roleName) {
                        $newRoleNames[] = $roleName;
                    }
                }
            }
        }
        
        if (empty($newRoleNames)) {
            $newRoleNames[] = 'No Role';
        }
        
        $newRolesString = implode(", ", $newRoleNames);
        
        // Create a human-readable details message showing the change
        $detailsMessage = "Modified: $oldRoleName -> $newRolesString";
        
        // Create simple strings for OldVal and NewVal with just username and role
        $oldValString = "{\"username\":\"$targetUsername\",\"role\":\"$oldRoleName\"}";
        $newValString = "{\"username\":\"$targetUsername\",\"role\":\"$newRolesString\"}";
        
        // Insert into audit_log table using the correct column names
        $sql = "INSERT INTO audit_log (
            UserID, 
            EntityID, 
            Action, 
            Details, 
            OldVal,
            NewVal,
            Module, 
            Status,
            Date_Time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $currentUserId,
            $userId,
            'Modified',
            $detailsMessage,
            $oldValString,
            $newValString,
            'User Management',
            'Successful'
        ]);
    } catch (Exception $e) {
        // Log the error but don't fail the main operation
        error_log("Error adding to audit log: " . $e->getMessage());
        return true;
    }
}

try {
    $pdo->beginTransaction();

    // Track changes for audit log
    $changeDetails = [
        'userId' => $userId,
        'departmentId' => $departmentId,
        'oldRoleId' => $oldRoleId,
        'newRoleIds' => $roleIds
    ];

    // 1) Remove the old role if it's no longer in the new list
    if ($oldRoleId !== 0 && !in_array($oldRoleId, $roleIds)) {
        $stmtDeleteRole = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND role_id = ? AND department_id = ?
        ");
        $stmtDeleteRole->execute([$userId, $oldRoleId, $departmentId]);
    }
    // 2) If oldRoleId was "none", delete any existing zero-role entry
    else if ($oldRoleId === 0) {
        $stmtDeleteZero = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND role_id = 0 AND department_id = ?
        ");
        $stmtDeleteZero->execute([$userId, $departmentId]);
    }

    // 3) If no roles selected at all, ensure a single "0" record exists
    if (empty($roleIds)) {
        // remove everything for this dept
        $stmtDeleteAll = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND department_id = ?
        ");
        $stmtDeleteAll->execute([$userId, $departmentId]);

        // insert the zero-role placeholder
        $stmtInsertZero = $pdo->prepare("
            INSERT INTO user_department_roles (user_id, department_id, role_id)
            VALUES (?, ?, 0)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
        ");
        $stmtInsertZero->execute([$userId, $departmentId]);
    } else {
        // 4) Remove any existing zero-role so we only have explicit roles
        $stmtDeleteZero = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND role_id = 0 AND department_id = ?
        ");
        $stmtDeleteZero->execute([$userId, $departmentId]);

        // 5) Insert each selected role
        $stmtInsertRole = $pdo->prepare("
            INSERT INTO user_department_roles (user_id, department_id, role_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
        ");

        foreach ($roleIds as $roleId) {
            // skip re-inserting the oldRoleId if it's still valid
            if ($roleId === $oldRoleId) continue;

            // check if already exists
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM user_department_roles
                WHERE user_id = ? AND role_id = ? AND department_id = ?
            ");
            $stmtCheck->execute([$userId, $roleId, $departmentId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                $stmtInsertRole->execute([$userId, $departmentId, $roleId]);
            }
        }
    }

    // Add to audit log if tracking is enabled
    if ($trackChanges) {
        // Get user information for the log
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$currentUserId]);
        $username = $stmt->fetchColumn() ?: 'Unknown User';
        
        // Get target user information
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetUsername = $stmt->fetchColumn() ?: 'Unknown User';
        
        // Get department name
        $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
        $stmt->execute([$departmentId]);
        $departmentName = $stmt->fetchColumn() ?: 'Unknown Department';
        
        // Get role names
        $roleNames = [];
        if (!empty($roleIds)) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $pdo->prepare("SELECT id, role_name FROM roles WHERE id IN ($placeholders)");
            $stmt->execute($roleIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $roleNames[$row['id']] = $row['role_name'];
            }
        }
        
        // Log the action
        addToAuditLog('UPDATE_USER_ROLE', [
            'performed_by' => $username,
            'target_user' => $targetUsername,
            'department' => $departmentName,
            'old_role_id' => $oldRoleId,
            'new_role_ids' => $roleIds,
            'new_role_names' => $roleNames
        ]);
    }

    // 6) Build the response: group department_ids by role_id
    $stmtGetAll = $pdo->prepare("
        SELECT role_id, department_id
        FROM user_department_roles
        WHERE user_id = ?
    ");
    $stmtGetAll->execute([$userId]);
    $combinations = $stmtGetAll->fetchAll(PDO::FETCH_ASSOC);

    $roleMap = [];
    foreach ($combinations as $combo) {
        $r = (int)$combo['role_id'];
        $d = (int)$combo['department_id'];
        $roleMap[$r][] = $d;
    }

    $assignments = [];
    foreach ($roleMap as $r => $depts) {
        $assignments[] = [
            'userId'        => $userId,
            'roleId'        => $r,
            'departmentIds' => array_values(array_unique($depts)),
        ];
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'assignments' => $assignments]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
