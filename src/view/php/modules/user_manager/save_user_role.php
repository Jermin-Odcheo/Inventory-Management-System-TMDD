<?php
// save_user_role.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}
if (empty($data)) {
    echo json_encode(['success' => false, 'error' => 'No assignments to process']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Insert roles
    $stmtRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    
    // Insert department (one per user)
    $stmtDept = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
    
    // Track all created assignments to return to client
    $createdAssignments = [];
    
    foreach ($data as $assignment) {
        $userId = $assignment['userId'];
        $departmentId = $assignment['departmentId'];
        $roleIds = $assignment['roleIds'];
        
        // Insert or update department (single department per user-role)
        // Check if user already has a department assignment
        $checkDept = $pdo->prepare("SELECT COUNT(*) FROM user_departments WHERE user_id = ?");
        $checkDept->execute([$userId]);
        $hasDept = (int)$checkDept->fetchColumn() > 0;
        
        if ($hasDept) {
            // Update existing department
            $stmtUpdateDept = $pdo->prepare("UPDATE user_departments SET department_id = ? WHERE user_id = ?");
            $stmtUpdateDept->execute([$departmentId, $userId]);
        } else {
            // Insert new department
            $stmtDept->execute([$userId, $departmentId]);
        }
        
        // Process each role ID
        foreach ($roleIds as $roleId) {
            // Check if the role already exists for this user
            $checkRole = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
            $checkRole->execute([$userId, $roleId]);
            
            if ((int)$checkRole->fetchColumn() === 0) {
                // Insert the role if it doesn't exist
                $stmtRole->execute([$userId, $roleId]);
                
                // Add to created assignments
                $createdAssignments[] = [
                    'userId' => $userId,
                    'roleId' => $roleId,
                    'departmentIds' => [$departmentId]
                ];
            }
        }
    }
    
    $pdo->commit();
    
    // Query all user-role-department relationships for this user to return
    $allAssignments = [];
    foreach ($data as $assignment) {
        $userId = $assignment['userId'];
        
        // Get all roles for this user
        $rolesStmt = $pdo->prepare("
            SELECT role_id FROM user_roles WHERE user_id = ?
        ");
        $rolesStmt->execute([$userId]);
        $roles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get department for this user
        $deptStmt = $pdo->prepare("
            SELECT department_id FROM user_departments WHERE user_id = ?
        ");
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
    }
    
    echo json_encode([
        'success' => true,
        'assignments' => $allAssignments
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Check for duplicate entry error (MySQL error code 1062)
    if ($e->errorInfo[1] == 1062) {
        echo json_encode(['success' => false, 'error' => 'User already has that role.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
}
?>
