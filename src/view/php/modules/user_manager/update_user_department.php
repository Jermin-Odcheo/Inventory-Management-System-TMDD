<?php
// update_user_department.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

// For debugging
$requestData = file_get_contents('php://input');
error_log("Received data: " . $requestData);

$data = json_decode($requestData, true);
if (!isset($data['userId']) || !isset($data['departmentId'])) {
    echo json_encode(['success' => false, 'error' => 'Missing essential parameters']);
    exit;
}

$userId = (int)$data['userId'];
// Handle null or empty string oldRoleId
$oldRoleId = isset($data['oldRoleId']) && $data['oldRoleId'] !== '' && $data['oldRoleId'] !== null 
    ? (int)$data['oldRoleId'] 
    : null;

// Convert roleIds to integers, but preserve null values
$roleIds = [];
if (isset($data['roleIds']) && is_array($data['roleIds'])) {
    foreach ($data['roleIds'] as $rid) {
        if ($rid === null) {
            // Keep null values as null
            $roleIds[] = null;
        } else {
            // Convert non-null values to integers
            $roleIds[] = (int)$rid;
        }
    }
}

$departmentId = (int)$data['departmentId']; // Single department ID
$preserveExistingDepartments = isset($data['preserveExistingDepartments']) && $data['preserveExistingDepartments'];

error_log("Processed data: userId=$userId, oldRoleId=" . var_export($oldRoleId, true) . 
          ", departmentId=$departmentId, roleIds=" . json_encode($roleIds));

try {
    $pdo->beginTransaction();
    
    // If an old role was specified, and it's not in the new roles list, remove it
    if ($oldRoleId !== null && !in_array($oldRoleId, $roleIds)) {
        $stmtDeleteRole = $pdo->prepare("
            DELETE FROM user_department_roles 
            WHERE user_id = ? AND role_id = ? AND department_id = ?
        ");
        $stmtDeleteRole->execute([$userId, $oldRoleId, $departmentId]);
    } else if ($oldRoleId === null) {
        // If old role was null, delete the null role entry for this department
        $stmtDeleteNullRole = $pdo->prepare("
            DELETE FROM user_department_roles 
            WHERE user_id = ? AND role_id IS NULL AND department_id = ?
        ");
        $stmtDeleteNullRole->execute([$userId, $departmentId]);
    }
    
    // If no roles are selected, ensure department exists with null role
    if (empty($roleIds)) {
        // Remove any existing roles for this department-user combination
        $stmtDeleteRoles = $pdo->prepare("
            DELETE FROM user_department_roles 
            WHERE user_id = ? AND department_id = ?
        ");
        $stmtDeleteRoles->execute([$userId, $departmentId]);
        
        // Insert the department with null role
        $stmtInsertNullRole = $pdo->prepare("
            INSERT INTO user_department_roles (user_id, department_id, role_id) 
            VALUES (?, ?, NULL)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
        ");
        $stmtInsertNullRole->execute([$userId, $departmentId]);
    } else {
        // Insert new roles for the department
        $stmtInsertRole = $pdo->prepare("
            INSERT INTO user_department_roles (user_id, department_id, role_id) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
        ");
        
        foreach ($roleIds as $roleId) {
            // Skip the old role if it's already in the database
            if ($roleId === $oldRoleId) continue;
            
            // For null roles, we need a different SQL query
            if ($roleId === null) {
                // Check if null role already exists
                $stmtCheckNullRole = $pdo->prepare("
                    SELECT COUNT(*) FROM user_department_roles 
                    WHERE user_id = ? AND role_id IS NULL AND department_id = ?
                ");
                $stmtCheckNullRole->execute([$userId, $departmentId]);
                
                if ((int)$stmtCheckNullRole->fetchColumn() === 0) {
                    // Insert the new null role for this department
                    $stmtInsertNullRole = $pdo->prepare("
                        INSERT INTO user_department_roles (user_id, department_id, role_id) 
                        VALUES (?, ?, NULL)
                    ");
                    $stmtInsertNullRole->execute([$userId, $departmentId]);
                }
            } else {
                // Regular non-null role
                // Check if this user-role-department combination already exists
                $stmtCheckRole = $pdo->prepare("
                    SELECT COUNT(*) FROM user_department_roles 
                    WHERE user_id = ? AND role_id = ? AND department_id = ?
                ");
                $stmtCheckRole->execute([$userId, $roleId, $departmentId]);
                
                if ((int)$stmtCheckRole->fetchColumn() === 0) {
                    // Insert the new role for this department
                    $stmtInsertRole->execute([$userId, $departmentId, $roleId]);
                }
            }
        }
    }
    
    // Get all role-department combinations for this user
    $stmtGetAll = $pdo->prepare("
        SELECT role_id, department_id
        FROM user_department_roles
        WHERE user_id = ?
    ");
    $stmtGetAll->execute([$userId]);
    $combinations = $stmtGetAll->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by role_id
    $roleMap = [];
    foreach ($combinations as $combo) {
        $roleId = $combo['role_id'] !== null ? (int)$combo['role_id'] : null;
        $deptId = (int)$combo['department_id'];
        
        if (!isset($roleMap[$roleId])) {
            $roleMap[$roleId] = [];
        }
        
        if (!in_array($deptId, $roleMap[$roleId])) {
            $roleMap[$roleId][] = $deptId;
        }
    }
    
    // Build assignments for the response
    $allAssignments = [];
    foreach ($roleMap as $roleId => $deptIds) {
        $allAssignments[] = [
            'userId' => $userId,
            'roleId' => $roleId,
            'departmentIds' => $deptIds
        ];
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'assignments' => $allAssignments
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>
