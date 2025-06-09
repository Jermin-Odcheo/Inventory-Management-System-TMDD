<?php
/**
 * Delete User Role Module
 *
 * This file provides functionality to remove role assignments from users in the system. It handles the deletion of role associations, ensuring proper cleanup of permissions and access rights. The module ensures proper validation, user authorization, and maintains data consistency during the role removal process.
 *
 * @package    InventoryManagementSystem
 * @subpackage UserManager
 * @author     TMDD Interns 25'
 */
// delete_user_role.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

/**
 * Performs authentication check to ensure the user is logged in before proceeding with role deletion.
 */
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

try {
    /**
     * Begins a database transaction to ensure data consistency during role deletion.
     */
    $pdo->beginTransaction();

    /**
     * Checks if there are actual roles to delete before proceeding.
     * Returns an error if no roles exist to delete when removeAll is true.
     */
    $checkRoles = $pdo->prepare("
        SELECT COUNT(*) AS cnt
        FROM user_department_roles
        WHERE user_id = ? AND department_id = ? AND role_id IS NOT NULL AND role_id != 0
    ");
    $checkRoles->execute([$userId, $departmentId]);
    $roleCount = (int)$checkRoles->fetchColumn();

    if ($roleCount === 0 && $removeAll) {
        // No actual roles to delete, return no changes
        $pdo->commit();
        echo json_encode([
            'success' => false,
            'error' => 'No roles to delete for this user in this department',
            'noChanges' => true
        ]);
        exit;
    }

    /**
     * Retrieves information for audit logging before deletion if tracking is enabled.
     */
    $oldRoleNames = [];
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
            $stmt = $pdo->prepare("
                SELECT r.role_name 
                FROM user_department_roles udr
                LEFT JOIN roles r ON udr.role_id = r.id
                WHERE udr.user_id = ? AND udr.department_id = ? AND udr.role_id IS NOT NULL AND udr.role_id != 0
            ");
            $stmt->execute([$userId, $departmentId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['role_name'])) {
                    $oldRoleNames[] = $row['role_name'];
                }
            }
            error_log("Found roles before deletion: " . implode(", ", $oldRoleNames));
        } else {
            // Original logic for single role
            $roleName = "No Role";
            if ($roleId !== 0) {
                $stmt = $pdo->prepare("SELECT role_name FROM roles WHERE id = ?");
                $stmt->execute([$roleId]);
                $roleName = $stmt->fetchColumn() ?: 'Unknown Role';
                $oldRoleNames = [$roleName];
            }
        }
    }

    /**
     * Handles the deletion of roles based on the removeAll flag.
     * If removeAll is true, deletes all roles and inserts a zero-role entry.
     * Otherwise, deletes a specific role and ensures a zero-role entry if no roles remain.
     */
    if ($removeAll) {
        // Delete ALL roles for this user in this department
        $del = $pdo->prepare("
            DELETE FROM user_department_roles
            WHERE user_id = ? AND department_id = ?
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
                WHERE user_id = ? AND department_id = ? AND (role_id IS NULL OR role_id = 0)
            ");
            $del->execute([$userId, $departmentId]);
        } else {
            $del = $pdo->prepare("
                DELETE FROM user_department_roles
                WHERE user_id = ? AND department_id = ? AND role_id = ?
            ");
            $del->execute([$userId, $departmentId, $roleId]);
        }

        // Check if that was the last row for this (user, department)
        $check = $pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM user_department_roles
            WHERE user_id = ? AND department_id = ?
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

    /**
     * Adds an audit log entry if tracking changes is enabled and roles were deleted.
     */
    if ($trackChanges && !empty($oldRoleNames)) {
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
        $detailsMessage = "$targetUsername roles in $departmentName: Removed " . implode(", ", $oldRoleNames);

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

    /**
     * Re-fetches all current assignments for the user to build the response.
     * Groups department IDs by role ID for a structured output.
     */
    $fetch = $pdo->prepare("
        SELECT role_id, department_id
        FROM user_department_roles
        WHERE user_id = ?
    ");
    $fetch->execute([$userId]);
    $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);

    // Group department_ids by role_id
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

    // Build result array
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