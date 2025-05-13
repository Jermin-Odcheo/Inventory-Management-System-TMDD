<?php
// update_user_department.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['userId']) || !isset($data['oldRoleId']) || !isset($data['roleIds']) || !isset($data['departmentId'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$userId = (int)$data['userId'];
$oldRoleId = (int)$data['oldRoleId'];
$roleIds = array_map('intval', $data['roleIds']); // Ensure all role IDs are integers
$departmentId = (int)$data['departmentId']; // Single department ID
$preserveExistingDepartments = isset($data['preserveExistingDepartments']) && $data['preserveExistingDepartments'];

try {
    $pdo->beginTransaction();
    
    // If the old role is not in the new roles list, remove it
    if (!in_array($oldRoleId, $roleIds)) {
        $stmtDeleteRole = $pdo->prepare("
            DELETE FROM user_department_roles 
            WHERE user_id = ? AND role_id = ? AND department_id = ?
        ");
        $stmtDeleteRole->execute([$userId, $oldRoleId, $departmentId]);
    }
    
    // Insert new roles for the department
    $stmtInsertRole = $pdo->prepare("
        INSERT INTO user_department_roles (user_id, department_id, role_id) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
    ");
    
    foreach ($roleIds as $roleId) {
        // Skip the old role if it's already in the database
        if ($roleId == $oldRoleId) continue;
        
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
        $roleId = (int)$combo['role_id'];
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
