<?php
/**
 * Save User Role Module
 *
 * This file provides functionality to save and update user role assignments in the system. It handles the association between users and roles, including permission settings. The module ensures proper validation, user authorization, and maintains data consistency during the role assignment process.
 *
 * @package    InventoryManagementSystem
 * @subpackage UserManager
 * @author     TMDD Interns 25'
 */
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

/**
 * Inserts a user-department-role triple into the database if it doesn't already exist.
 * 
 * This function handles the addition of a role assignment for a user in a specific department.
 * It supports null or zero role IDs to indicate no specific role.
 * 
 * @param int $userId The ID of the user to assign the role to.
 * @param int $departmentId The ID of the department for the assignment.
 * @param int|null $roleId The ID of the role to assign, or null/0 for no specific role.
 * @return bool Returns true if the operation was successful or the assignment already exists.
 */
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

/**
 * Adds an entry to the audit log for tracking changes to user role assignments.
 * 
 * This function logs the action performed on user role assignments, including details about the
 * roles, departments, and users involved. It handles cases where the audit log table might not exist
 * and ensures that the main operation is not interrupted by logging errors.
 * 
 * @param string $actionType The type of action performed (e.g., 'Add').
 * @param array $details Additional details about the action, including assignments.
 * @return bool Returns true to indicate the operation was attempted, even if logging failed.
 */
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
        
        // Get all assignments from the details
        if (!isset($details['assignments']) || empty($details['assignments'])) {
            error_log("No assignments found in details for audit log");
            return true;
        }

        // Insert audit log entry for each user assignment
        foreach ($details['assignments'] as $assignment) {
            $targetUserId = $assignment['userId'];
            
            // Get target username
            $userQuery = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $userQuery->execute([$targetUserId]);
            $targetUsername = $userQuery->fetchColumn() ?: 'Unknown User';
            
            // Get role info
            $roleId = $assignment['roleId'] ?? 0;
            $roleInfo = "No Role";
            if ($roleId && $roleId > 0) {
                $roleQuery = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                $roleQuery->execute([$roleId]);
                $roleInfo = $roleQuery->fetchColumn() ?: 'Unknown Role';
            }
            
            // Get department info
            $departmentId = $assignment['departmentIds'][0] ?? null;
            $departmentInfo = "Unknown Department";
            if ($departmentId) {
                $deptQuery = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
                $deptQuery->execute([$departmentId]);
                $departmentInfo = $deptQuery->fetchColumn() ?: 'Unknown Department';
            }
            
            // Create a human-readable details message
            $detailsMessage = "Added: $roleInfo to $targetUsername in $departmentInfo";
            
            // Create simple strings for OldVal and NewVal
            $oldValString = "{}";
            $newValString = json_encode([
                "username" => $targetUsername,
                "role" => $roleInfo,
                "department" => $departmentInfo
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
            $stmt->execute([
                $userId,
                $targetUserId,
                'Add',
                $detailsMessage,
                $oldValString,
                $newValString,
                'User Management',
                'Successful'
            ]);
        }
        
        return true;
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
    /**
     * Begins a database transaction to ensure data consistency during the creation of role assignments.
     */
    $pdo->beginTransaction();

    /**
     * Tracks the newly created user-department-role assignments for potential audit logging.
     * @var array $created Contains details of the newly created assignments.
     */
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

    /**
     * Adds an entry to the audit log if tracking changes is enabled.
     * Retrieves user information to create a detailed log entry.
     */
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

    /**
     * Re-fetches all assignments for each affected user to build a comprehensive response.
     * Groups department IDs by role ID for each user.
     * @var array $uniqueUsers List of unique user IDs affected by the assignments.
     * @var array $allAssignments Comprehensive list of all assignments for affected users.
     */
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
