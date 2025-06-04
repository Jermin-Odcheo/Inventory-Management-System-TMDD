<?php
/**
 * Delete Department Script
 *
 * This script handles the deletion of departments in the system. It supports both single and bulk deletion,
 * offers soft and permanent delete options, checks for user permissions, logs actions in the audit log,
 * and returns JSON responses for AJAX requests.
 */
session_start();
require_once('../../../../../../config/ims-tmdd.php');

// Fix the path for RBAC service
require_once(__DIR__ . '/../../../../../../src/control/RBACService.php');

header('Content-Type: application/json');

/**
 * Check AJAX Request
 *
 * Validates that the request is an AJAX request to prevent unauthorized access.
 *
 * @return void
 */
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

/**
 * Check User Login
 *
 * Ensures that the user is logged in before allowing the deletion action.
 *
 * @return void
 */
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to perform this action'
    ]);
    exit;
}

/**
 * Check User Privilege
 *
 * Uses RBAC service to ensure the user has the necessary privilege to delete departments.
 *
 * @return void
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Roles and Privileges', 'Remove')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You do not have permission to delete departments'
    ]);
    exit;
}

/**
 * Set Audit Log Variables
 *
 * Sets session variables for audit logging in MySQL triggers.
 *
 * @return void
 */
$pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
$pdo->exec("SET @current_ip = '" . $_SERVER['REMOTE_ADDR'] . "'");

/**
 * Check Delete Type
 *
 * Determines if the deletion should be permanent or a soft delete (archive).
 *
 * @return bool True for permanent delete, false for soft delete.
 */
$isPermanent = isset($_POST['permanent']) && $_POST['permanent'] == 1;

try {
    /**
     * Begin Database Transaction
     *
     * Starts a transaction to ensure data consistency during the deletion process.
     *
     * @return void
     */
    $pdo->beginTransaction();

    // Check if we're doing bulk or single delete
    if (isset($_POST['dept_ids']) && is_array($_POST['dept_ids'])) {
        // Bulk delete
        /**
         * Bulk Delete Departments
         *
         * Handles the deletion of multiple departments at once, either permanently or as a soft delete.
         *
         * @param array $deptIds Array of department IDs to delete.
         * @param bool $isPermanent True for permanent delete, false for soft delete.
         * @return void
         */
        $deptIds = array_map('intval', $_POST['dept_ids']); // Ensure all IDs are integers
        
        if (empty($deptIds)) {
            throw new Exception("No departments selected for deletion");
        }

        // Get department details before deletion
        /**
         * Fetch Departments Before Deletion
         *
         * Retrieves details of departments before they are deleted for audit logging.
         *
         * @param array $deptIds Array of department IDs to fetch.
         * @return array The list of department details.
         */
        $placeholders = str_repeat('?,', count($deptIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id IN ($placeholders)");
        $stmt->execute($deptIds);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($isPermanent) {
            // Permanent delete - remove from the database
            /**
             * Permanent Delete Departments
             *
             * Permanently removes departments from the database.
             *
             * @param array $deptIds Array of department IDs to delete.
             * @return void
             */
            $deleteQuery = "DELETE FROM departments WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($deleteQuery);
            $stmt->execute($deptIds);

            // Add audit log entries for each permanently deleted department
            /**
             * Log Permanent Bulk Delete Actions
             *
             * Logs the permanent deletion of each department to the audit log.
             *
             * @param array $departments Array of department details to log.
             * @return void
             */
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            foreach ($departments as $dept) {
                $oldValues = json_encode([
                    'id' => $dept['id'],
                    'abbreviation' => $dept['abbreviation'],
                    'department_name' => $dept['department_name']
                ]);

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $dept['id'],
                    'Department Management',
                    'Delete',
                    "Department '{$dept['department_name']}' has been permanently deleted",
                    $oldValues,
                    null,
                    'Successful'
                ]);
            }

            $message = 'Departments permanently deleted successfully';
        } else {
            // Soft delete - set is_disabled = 1
            /**
             * Soft Delete Departments
             *
             * Marks departments as disabled (archived) in the database.
             *
             * @param array $deptIds Array of department IDs to update.
             * @return void
             */
            $updateQuery = "UPDATE departments SET is_disabled = 1 WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute($deptIds);

            // Add audit log entries for each soft-deleted department
            /**
             * Log Soft Bulk Delete Actions
             *
             * Logs the soft deletion (archiving) of each department to the audit log.
             *
             * @param array $departments Array of department details to log.
             * @return void
             */
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            foreach ($departments as $dept) {
                $oldValues = json_encode([
                    'id' => $dept['id'],
                    'abbreviation' => $dept['abbreviation'],
                    'department_name' => $dept['department_name']
                ]);
                
                $newValues = json_encode([
                    'id' => $dept['id'],
                    'abbreviation' => $dept['abbreviation'],
                    'department_name' => $dept['department_name']
                ]);

                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $dept['id'],
                    'Department Management',
                    'Remove',
                    "Department '{$dept['department_name']}' has been moved to archive",
                    $oldValues,
                    $newValues,
                    'Successful'
                ]);
            }

            $message = 'Departments moved to archive successfully';
        }

        /**
         * Commit Transaction
         *
         * Commits the database transaction if all operations are successful.
         *
         * @return void
         */
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => $message
        ]);
    } 
    elseif (isset($_POST['dept_id'])) {
        // Single delete
        /**
         * Single Delete Department
         *
         * Handles the deletion of a single department, either permanently or as a soft delete.
         *
         * @param int $deptId The ID of the department to delete.
         * @param bool $isPermanent True for permanent delete, false for soft delete.
         * @return void
         */
        $deptId = (int)$_POST['dept_id'];
        
        // Get department before deletion
        /**
         * Fetch Department Before Deletion
         *
         * Retrieves details of the department before it is deleted for audit logging.
         *
         * @param int $deptId The ID of the department to fetch.
         * @return array The department details.
         */
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$deptId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$department) {
            throw new Exception("Department not found");
        }

        if ($isPermanent) {
            // Permanent delete - remove from the database
            /**
             * Permanent Delete Department
             *
             * Permanently removes a single department from the database.
             *
             * @param int $deptId The ID of the department to delete.
             * @return void
             */
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$deptId]);
            
            // Add audit log entry
            /**
             * Log Permanent Single Delete Action
             *
             * Logs the permanent deletion of the department to the audit log.
             *
             * @param array $department The department details to log.
             * @return void
             */
            $oldValues = json_encode([
                'id' => $department['id'],
                'abbreviation' => $department['abbreviation'],
                'department_name' => $department['department_name']
            ]);

            $auditStmt = $pdo->prepare("INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $auditStmt->execute([
                $_SESSION['user_id'],
                $deptId,
                'Department Management',
                'Delete',
                "Department '{$department['department_name']}' has been permanently deleted",
                $oldValues,
                null,
                'Successful'
            ]);

            $message = "Department '{$department['department_name']}' permanently deleted successfully";
        } else {
            // Soft delete - set is_disabled = 1
            /**
             * Soft Delete Department
             *
             * Marks a single department as disabled (archived) in the database.
             *
             * @param int $deptId The ID of the department to update.
             * @return void
             */
            $stmt = $pdo->prepare("UPDATE departments SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$deptId]);
            
            // Add audit log entry
            /**
             * Log Soft Single Delete Action
             *
             * Logs the soft deletion (archiving) of the department to the audit log.
             *
             * @param array $department The department details to log.
             * @return void
             */
            $oldValues = json_encode([
                'id' => $department['id'],
                'abbreviation' => $department['abbreviation'],
                'department_name' => $department['department_name']
            ]);
            
            $newValues = json_encode([
                'id' => $department['id'],
                'abbreviation' => $department['abbreviation'],
                'department_name' => $department['department_name']
            ]);

            $auditStmt = $pdo->prepare("INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $auditStmt->execute([
                $_SESSION['user_id'],
                $deptId,
                'Department Management',
                'Remove',
                "Department '{$department['department_name']}' has been moved to archive",
                $oldValues,
                $newValues,
                'Successful'
            ]);

            $message = "Department '{$department['department_name']}' moved to archive successfully";
        }

        /**
         * Commit Transaction
         *
         * Commits the database transaction if all operations are successful.
         *
         * @return void
         */
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => $message
        ]);
    } 
    else {
        throw new Exception("No department ID provided");
    }
} 
catch (Exception $e) {
    /**
     * Rollback Transaction on Error
     *
     * Rolls back the database transaction if an error occurs during the deletion process.
     *
     * @return void
     */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 