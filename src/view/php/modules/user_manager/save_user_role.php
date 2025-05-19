<?php
// save_user_role.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

// Auth check
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Insert a user–department–role triple if it doesn't already exist
// Modified to handle null role IDs
function addUserDepartmentRole(int $userId, int $departmentId, $roleId = null): bool {
    global $pdo;
    
    // Different query for null role
    if ($roleId === null || $roleId === 0) {
        $check = $pdo->prepare("
            SELECT COUNT(*) 
              FROM user_department_roles 
             WHERE user_id = ? AND department_id = ? AND (role_id IS NULL OR role_id = 0)
        ");
        $check->execute([$userId, $departmentId]);
        
        if ((int)$check->fetchColumn() === 0) {
            $insert = $pdo->prepare("
                INSERT INTO user_department_roles (user_id, department_id, role_id)
                VALUES (?, ?, 0)
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

// Add to audit log
function addToAuditLog($actionType, $details) {
    global $pdo, $userId;
    
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
        
        // Get target user ID from the first assignment
        $targetUserId = null;
        $targetUsername = "Unknown User";
        $roleInfo = "";
        
        if (isset($details['assignments']) && !empty($details['assignments']) && isset($details['assignments'][0]['userId'])) {
            $targetUserId = $details['assignments'][0]['userId'];
            
            // Get target username
            $userQuery = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $userQuery->execute([$targetUserId]);
            $targetUsername = $userQuery->fetchColumn() ?: 'Unknown User';
            
            // Get role info for the first assignment only (to keep it simple)
            $firstAssignment = $details['assignments'][0];
            $roleId = $firstAssignment['roleId'] ?? 0;
            
            // Get role name
            if ($roleId && $roleId > 0) {
                $roleQuery = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                $roleQuery->execute([$roleId]);
                $roleInfo = $roleQuery->fetchColumn() ?: 'Unknown Role';
            } else {
                $roleInfo = "No Role";
            }
        }
        
        // Create a human-readable details message
        $detailsMessage = "Added: $roleInfo";
        
        // Create simple strings for OldVal and NewVal
        $oldValString = "{}";
        $newValString = "{\"username\":\"$targetUsername\",\"role\":\"$roleInfo\"}";
        
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
            $userId,
            $targetUserId,
            'Add',
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

$data = json_decode(file_get_contents('php://input'), true);

// Check if we have the new format with assignments array
if (isset($data['assignments']) && is_array($data['assignments'])) {
    $assignments = $data['assignments'];
    $trackChanges = $data['trackChanges'] ?? false;
} else {
    // Handle old format for backward compatibility
    $assignments = $data;
    $trackChanges = false;
}

if (!is_array($assignments) || empty($assignments)) {
    echo json_encode(['success' => false, 'error' => 'No assignments to process']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Track the newly created triples (optional)
    $created = [];

    foreach ($assignments as $assignment) {
        $userId       = (int)$assignment['userId'];
        $departmentId = (int)$assignment['departmentId'];
        $roleIds      = is_array($assignment['roleIds']) ? $assignment['roleIds'] : [];

        foreach ($roleIds as $rawRoleId) {
            // Handle null/zero roles explicitly
            $roleId = ($rawRoleId === null || $rawRoleId === 0) ? 0 : (int)$rawRoleId;
            
            if (addUserDepartmentRole($userId, $departmentId, $roleId)) {
                $created[] = [
                    'userId'        => $userId,
                    'roleId'        => $roleId,
                    'departmentIds' => [$departmentId],
                ];
            }
        }
    }

    // Add to audit log if tracking is enabled
    if ($trackChanges) {
        // Get user information for the log
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $username = $stmt->fetchColumn() ?: 'Unknown User';
        
        // Log the action
        addToAuditLog('ADD_USER_ROLE', [
            'performed_by' => $username,
            'assignments' => $created
        ]);
    }

    $pdo->commit();

    // Now re-fetch **all** assignments for each affected user
    $uniqueUsers = array_unique(array_map(fn($a) => (int)$a['userId'], $assignments));
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
            // Handle null/zero role_id
            $rid = ($r['role_id'] === null) ? 0 : (int)$r['role_id'];
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
