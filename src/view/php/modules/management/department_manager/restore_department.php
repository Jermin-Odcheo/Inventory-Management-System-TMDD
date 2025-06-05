<?php
/**
 * @file restore_department.php
 * @brief handles the restoration of departments in the system
 *
 * This script handles the restoration of departments in the system. It supports both single and bulk restoration,
 * checks for user permissions, logs actions in the audit log, and returns JSON responses for AJAX requests.
 */
session_start();
require_once('../../../../../../config/ims-tmdd.php');

// Define the root path to make includes more reliable
// define('ROOT_PATH', realpath(dirname(__FILE__) . '/../../../../../'));

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
 * Ensures that the user is logged in before allowing the restoration action.
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
 * Uses RBAC service to ensure the user has the necessary privilege to restore departments.
 *
 * @return void
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Roles and Privileges', 'Modify')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You do not have permission to restore departments'
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

try {
    /**
     * Begin Database Transaction
     *
     * Starts a transaction to ensure data consistency during the restoration process.
     *
     * @return void
     */
    $pdo->beginTransaction();

    // Check if we're doing bulk or single restore
    if (isset($_POST['dept_ids']) && is_array($_POST['dept_ids'])) {
        // Bulk restore
        /**
         * Bulk Restore Departments
         *
         * Handles the restoration of multiple departments at once.
         *
         * @param array $deptIds Array of department IDs to restore.
         * @return void
         */
        $deptIds = array_map('intval', $_POST['dept_ids']); // Ensure all IDs are integers
        
        if (empty($deptIds)) {
            throw new Exception("No departments selected for restore");
        }
        
        // Get all departments before update
        /**
         * Fetch Departments Before Restore
         *
         * Retrieves details of departments before they are restored for audit logging.
         *
         * @param array $deptIds Array of department IDs to fetch.
         * @return array The list of department details.
         */
        $placeholders = str_repeat('?,', count($deptIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id IN ($placeholders)");
        $stmt->execute($deptIds);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update departments is_disabled = 0
        /**
         * Update Departments Status
         *
         * Sets the is_disabled flag to 0 to restore the departments.
         *
         * @param array $deptIds Array of department IDs to update.
         * @return void
         */
        $updateQuery = "UPDATE departments SET is_disabled = 0 WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute($deptIds);

        // Add audit log entries for each department
        /**
         * Log Bulk Restore Actions
         *
         * Logs the restoration of each department to the audit log.
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
                'Restored',
                "Department '{$dept['department_name']}' has been restored",
                $oldValues,
                $newValues,
                'Successful'
            ]);
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
            'message' => 'Departments restored successfully'
        ]);
    } 
    elseif (isset($_POST['id'])) {
        // Single restore
        /**
         * Single Restore Department
         *
         * Handles the restoration of a single department.
         *
         * @param int $deptId The ID of the department to restore.
         * @return void
         */
        $deptId = (int)$_POST['id'];
        
        // Get department before update
        /**
         * Fetch Department Before Restore
         *
         * Retrieves details of the department before it is restored for audit logging.
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
        
        // Update department is_disabled = 0
        /**
         * Update Department Status
         *
         * Sets the is_disabled flag to 0 to restore the department.
         *
         * @param int $deptId The ID of the department to update.
         * @return void
         */
        $stmt = $pdo->prepare("UPDATE departments SET is_disabled = 0 WHERE id = ?");
        $stmt->execute([$deptId]);
        
        // Add audit log entry
        /**
         * Log Single Restore Action
         *
         * Logs the restoration of the department to the audit log.
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
            'Restored',
            "Department '{$department['department_name']}' has been restored",
            $oldValues,
            $newValues,
            'Successful'
        ]);

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
            'message' => "Department '{$department['department_name']}' restored successfully"
        ]);
    } 
    else {
        throw new Exception("No department ID provided");
    }
} 
catch (PDOException $e) {
    /**
     * Rollback Transaction on PDO Error
     *
     * Rolls back the database transaction if a PDO error occurs during the restoration process.
     *
     * @return void
     */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Check for the specific duplicate entry error for departments
    if ($e->getCode() == 23000 && 
        strpos($e->getMessage(), 'Duplicate entry') !== false && 
        strpos($e->getMessage(), 'uq_dept_active') !== false) {
        
        echo json_encode([
            'status' => 'error',
            'message' => 'A department with the same abbreviation is already active. Please check existing departments before restoring.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
catch (Exception $e) {
    /**
     * Rollback Transaction on General Error
     *
     * Rolls back the database transaction if a general error occurs during the restoration process.
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