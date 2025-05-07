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
$roleIds = $data['roleIds']; // Expecting an array of role IDs
$departmentId = $data['departmentId']; // Single department ID

try {
    $pdo->beginTransaction();
    
    // Update the department for this user (single department per user)
    $stmtDept = $pdo->prepare("UPDATE user_departments SET department_id = ? WHERE user_id = ?");
    $stmtDept->execute([$departmentId, $userId]);
    
    if ($stmtDept->rowCount() === 0) {
        // If no rows were updated, insert the department
        $stmtInsertDept = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
        $stmtInsertDept->execute([$userId, $departmentId]);
    }
    
    // Remove the old role if it's not in the new roles list
    if (!in_array($oldRoleId, $roleIds)) {
        $stmtDeleteRole = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
        $stmtDeleteRole->execute([$userId, $oldRoleId]);
    }
    
    // Insert new roles
    $stmtInsertRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    foreach ($roleIds as $roleId) {
        // Skip the old role as it's already in the database
        if ($roleId == $oldRoleId) continue;
        
        // Check if the role already exists
        $stmtCheckRole = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
        $stmtCheckRole->execute([$userId, $roleId]);
        
        if ((int)$stmtCheckRole->fetchColumn() === 0) {
            // Insert the new role
            $stmtInsertRole->execute([$userId, $roleId]);
        }
    }
    
    // Query all updated role-department relationships for this user
    $allAssignments = [];
    
    // Get all roles for this user
    $rolesStmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $rolesStmt->execute([$userId]);
    $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get department for this user
    $deptStmt = $pdo->prepare("SELECT department_id FROM user_departments WHERE user_id = ?");
    $deptStmt->execute([$userId]);
    $deptId = $deptStmt->fetchColumn();
    
    // Create an assignment entry for each role with the same department
    foreach ($roles as $roleId) {
        $allAssignments[] = [
            'userId' => $userId,
            'roleId' => $roleId,
            'departmentIds' => $deptId ? [$deptId] : []
        ];
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'assignments' => $allAssignments
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
