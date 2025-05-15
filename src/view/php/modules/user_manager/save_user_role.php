<?php
// save_user_role.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

// Insert a user–department–role triple if it doesn't already exist
// Modified to handle null role IDs
function addUserDepartmentRole(int $userId, int $departmentId, $roleId = null): bool {
    global $pdo;
    
    // Different query for null role
    if ($roleId === null) {
        $check = $pdo->prepare("
            SELECT COUNT(*) 
              FROM user_department_roles 
             WHERE user_id = ? AND department_id = ? AND role_id IS NULL
        ");
        $check->execute([$userId, $departmentId]);
        
        if ((int)$check->fetchColumn() === 0) {
            $insert = $pdo->prepare("
                INSERT INTO user_department_roles (user_id, department_id, role_id)
                VALUES (?, ?, NULL)
            ");
            return $insert->execute([$userId, $departmentId]);
        }
    } else {
        $roleId = (int)$roleId; // Ensure integer for non-null roles
        $check = $pdo->prepare("
            SELECT COUNT(*) 
              FROM user_department_roles 
             WHERE user_id = ? AND department_id = ? AND role_id = ?
        ");
        $check->execute([$userId, $departmentId, $roleId]);
        
        if ((int)$check->fetchColumn() === 0) {
            $insert = $pdo->prepare("
                INSERT INTO user_department_roles (user_id, department_id, role_id)
                VALUES (?, ?, ?)
            ");
            return $insert->execute([$userId, $departmentId, $roleId]);
        }
    }
    
    return true;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || empty($data)) {
    echo json_encode(['success' => false, 'error' => 'No assignments to process']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Track the newly created triples (optional)
    $created = [];

    foreach ($data as $assignment) {
        $userId       = (int)$assignment['userId'];
        $departmentId = (int)$assignment['departmentId'];
        $roleIds      = is_array($assignment['roleIds']) ? $assignment['roleIds'] : [];

        foreach ($roleIds as $rawRoleId) {
            // Handle null roles explicitly
            $roleId = $rawRoleId === null ? null : (int)$rawRoleId;
            
            if (addUserDepartmentRole($userId, $departmentId, $roleId)) {
                $created[] = [
                    'userId'        => $userId,
                    'roleId'        => $roleId,
                    'departmentIds' => [$departmentId],
                ];
            }
        }
    }

    $pdo->commit();

    // Now re-fetch **all** assignments for each affected user
    $uniqueUsers = array_unique(array_map(fn($a) => (int)$a['userId'], $data));
    $allAssignments = [];

    $fetch = $pdo->prepare("
        SELECT role_id, department_id
          FROM user_department_roles
         WHERE user_id = ?
    ");

    foreach ($uniqueUsers as $uid) {
        $fetch->execute([$uid]);
        $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);

        // group department_ids by role_id
        $byRole = [];
        foreach ($rows as $r) {
            // Handle null role_id
            $rid = $r['role_id'] !== null ? (int)$r['role_id'] : null;
            $did = (int)$r['department_id'];
            
            // Initialize array if needed
            if (!isset($byRole[$rid])) {
                $byRole[$rid] = [];
            }
            
            if (!in_array($did, $byRole[$rid], true)) {
                $byRole[$rid][] = $did;
            }
        }

        foreach ($byRole as $rid => $dids) {
            $allAssignments[] = [
                'userId'        => $uid,
                'roleId'        => $rid,
                'departmentIds' => $dids,
            ];
        }
    }

    echo json_encode([
        'success'     => true,
        'assignments' => $allAssignments,
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage()
    ]);
}
