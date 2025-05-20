<?php
// delete_user_role.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

// Auth check
$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (
    !isset($data['userId']) ||
    !isset($data['departmentId'])
) {
    echo json_encode(['success' => false, 'error' => 'Missing essential parameters']);
    exit;
}

$userId       = (int)$data['userId'];
$roleId       = isset($data['roleId']) && is_numeric($data['roleId'])
    ? (int)$data['roleId']
    : 0; // Use 0 instead of null for consistency
$departmentId = (int)$data['departmentId'];
$removeAll    = $data['removeAll'] ?? false; // New flag to indicate removing all roles
$trackChanges = $data['trackChanges'] ?? false;

// If removeAll is set, we'll skip the role-specific delete and remove all roles for this department
if ($removeAll) {
    error_log("Removing ALL roles for user $userId in department $departmentId");
}

// Add to audit log
function addToAuditLog($actionType, $details) {
    global $pdo, $currentUserId, $userId, $departmentId, $roleId, $removeAll;
    
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
        
        // Get role name
        $roleName = 'No Role';
        if ($roleId && $roleId > 0) {
            $roleQuery = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
            $roleQuery->execute([$roleId]);
            $roleName = $roleQuery->fetchColumn() ?: 'Unknown Role';
        }
        
        // Get department name
        $departmentQuery = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
        $departmentQuery->execute([$departmentId]);
        $departmentName = $departmentQuery->fetchColumn() ?: 'Unknown Department';
        
        // Create a human-readable details message formatted for UI display
        $detailsMessage = "$targetUsername roles in $departmentName: Removed '$roleName'";
        
        // Create OldVal and NewVal JSON strings with consistent format
        $oldValJSON = json_encode([
            "username" => $targetUsername,
            "department" => $departmentName,
            "roles" => [$roleName]
        ]);
        
        $newValJSON = json_encode([
            "username" => $targetUsername,
            "department" => $departmentName,
            "roles" => ["No Role"]
        ]);
        
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
            'Remove',
            $detailsMessage,
            $oldValJSON,
            $newValJSON,
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

    // Get information for audit log before deletion
    if ($trackChanges) {
        // Get user information
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
        
        // For removeAll, collect all role names before deletion
        if ($removeAll) {
            // Get all existing roles for this user in this department
            $stmt = $pdo->prepare("
                SELECT r.role_name 
                FROM user_department_roles udr
                LEFT JOIN roles r ON udr.role_id = r.id
                WHERE udr.user_id = ? AND udr.department_id = ? AND udr.role_id IS NOT NULL AND udr.role_id <> 0
            ");
            $stmt->execute([$userId, $departmentId]);
            $roleNames = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['role_name'])) {
                    $roleNames[] = $row['role_name'];
                }
            }
            
            // Log what we found
            error_log("Found roles before deletion: " . implode(", ", $roleNames));
            
            // Store for use after the deletion
            $oldRoleNames = $roleNames;
        } else {
            // Original logic for single role
            $roleName = "No Role";
            if ($roleId !== 0) {
                $stmt = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                $stmt->execute([$roleId]);
                $roleName = $stmt->fetchColumn() ?: 'Unknown Role';
            }
        }
    }

    // Handle the deletion based on removeAll flag
    if ($removeAll) {
        // Delete ALL roles for this user in this department
        $del = $pdo->prepare("
            DELETE FROM user_department_roles
             WHERE user_id       = ?
               AND department_id = ?
        ");
        $del->execute([$userId, $departmentId]);
        
        // Insert a zero-role entry so the department remains visible
        $ins = $pdo->prepare("
            INSERT INTO user_department_roles
               (user_id, department_id, role_id)
            VALUES (?, ?, 0)
        ");
        $ins->execute([$userId, $departmentId]);
        
        error_log("Removed all roles for user $userId in department $departmentId and inserted zero-role entry");
    } else {
        // Original logic for deleting a specific role
        if ($roleId === 0) {
            $del = $pdo->prepare("
                DELETE FROM user_department_roles
                 WHERE user_id       = ?
                   AND department_id = ?
                   AND (role_id IS NULL OR role_id = 0)
            ");
            $del->execute([$userId, $departmentId]);
        } else {
            $del = $pdo->prepare("
                DELETE FROM user_department_roles
                 WHERE user_id       = ?
                   AND department_id = ?
                   AND role_id       = ?
            ");
            $del->execute([$userId, $departmentId, $roleId]);
        }

        // 1a) if that was the last row for this (user, department),
        //      re-insert a 0-role row so the dept still shows up
        $check = $pdo->prepare("
            SELECT COUNT(*) AS cnt
              FROM user_department_roles
             WHERE user_id       = ?
               AND department_id = ?
        ");
        $check->execute([$userId, $departmentId]);
        if ((int)$check->fetchColumn() === 0) {
            $ins = $pdo->prepare("
                INSERT INTO user_department_roles
                   (user_id, department_id, role_id)
                VALUES (?, ?, 0)
            ");
            $ins->execute([$userId, $departmentId]);
        }
    }

    // Add to audit log if tracking is enabled
    if ($trackChanges) {
        // For removeAll, use the roles we collected before deletion
        if ($removeAll) {
            // If no roles were found, use placeholder
            if (empty($oldRoleNames)) {
                $oldRoleNames = ["No Role"];
            }
            
            $rolesString = implode(", ", $oldRoleNames);
            
            // Create OldVal and NewVal JSON strings
            $oldValJSON = json_encode([
                "username" => $targetUsername,
                "department" => $departmentName,
                "roles" => $oldRoleNames
            ]);
            
            $newValJSON = json_encode([
                "username" => $targetUsername,
                "department" => $departmentName,
                "roles" => ["No Role"]
            ]);
            
            // Log what we're about to insert
            error_log("Audit log OldVal: " . $oldValJSON);
            error_log("Audit log NewVal: " . $newValJSON);
            
            // Create more descriptive details that will show properly in the UI
            $detailsMessage = "$targetUsername roles in $departmentName: Removed all roles";
            
            // Add to audit log with all roles info
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Action, Details, OldVal, NewVal, Module, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $currentUserId,
                $userId,
                'Remove',
                $detailsMessage,
                $oldValJSON,
                $newValJSON,
                'User Management',
                'Successful'
            ]);
        } else {
            // Single role removal logging
            // Create OldVal and NewVal JSON strings
            $oldValJSON = json_encode([
                "username" => $targetUsername,
                "department" => $departmentName,
                "roles" => [$roleName]
            ]);
            
            $newValJSON = json_encode([
                "username" => $targetUsername,
                "department" => $departmentName,
                "roles" => ["No Role"] // After removal will be No Role
            ]);
            
            // Create a details message formatted for UI display
            $detailsMessage = "$targetUsername roles in $departmentName: Removed '$roleName'";
            
            // Add to audit log
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Action, Details, OldVal, NewVal, Module, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $currentUserId,
                $userId,
                'Remove',
                $detailsMessage,
                $oldValJSON,
                $newValJSON,
                'User Management',
                'Successful'
            ]);
        }
    }

    // 2) re-fetch everything for this user
    $fetch = $pdo->prepare("
        SELECT role_id, department_id
          FROM user_department_roles
         WHERE user_id = ?
    ");
    $fetch->execute([$userId]);
    $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);

    // 3) group department_ids by role_id
    $byRole = [];
    foreach ($rows as $r) {
        $rid = ($r['role_id'] === null) ? 0 : (int)$r['role_id'];
        $did = (int)$r['department_id'];

        if (!isset($byRole[$rid])) {
            $byRole[$rid] = [];
        }
        if (!in_array($did, $byRole[$rid], true)) {
            $byRole[$rid][] = $did;
        }
    }

    // 4) build result array
    $assignments = [];
    foreach ($byRole as $rid => $dids) {
        $assignments[] = [
            'userId'        => $userId,
            'roleId'        => $rid,
            'departmentIds' => $dids,
        ];
    }

    $pdo->commit();

    echo json_encode([
        'success'     => true,
        'assignments' => $assignments
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error'   => 'DB error: ' . $e->getMessage()
    ]);
}
