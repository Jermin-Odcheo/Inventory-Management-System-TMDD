<?php
session_start();
require_once('../../../../../../config/ims-tmdd.php');

// Define the root path to make includes more reliable
// define('ROOT_PATH', realpath(dirname(__FILE__) . '/../../../../../'));

// Fix the path for RBAC service
require_once(__DIR__ . '/../../../../../../src/control/RBACService.php');

header('Content-Type: application/json');

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to perform this action'
    ]);
    exit;
}

// Init RBAC & enforce "Modify" privilege
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Roles and Privileges', 'Modify')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You do not have permission to restore departments'
    ]);
    exit;
}

// Set the audit log session variables for MySQL triggers
$pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
$pdo->exec("SET @current_ip = '" . $_SERVER['REMOTE_ADDR'] . "'");

try {
    $pdo->beginTransaction();

    // Check if we're doing bulk or single restore
    if (isset($_POST['dept_ids']) && is_array($_POST['dept_ids'])) {
        // Bulk restore
        $deptIds = array_map('intval', $_POST['dept_ids']); // Ensure all IDs are integers
        
        if (empty($deptIds)) {
            throw new Exception("No departments selected for restore");
        }
        
        // Get all departments before update
        $placeholders = str_repeat('?,', count($deptIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id IN ($placeholders)");
        $stmt->execute($deptIds);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update departments is_disabled = 0
        $updateQuery = "UPDATE departments SET is_disabled = 0 WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute($deptIds);

        // Add audit log entries for each department
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

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

        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Departments restored successfully'
        ]);
    } 
    elseif (isset($_POST['id'])) {
        // Single restore
        $deptId = (int)$_POST['id'];
        
        // Get department before update
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$deptId]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$department) {
            throw new Exception("Department not found");
        }
        
        // Update department is_disabled = 0
        $stmt = $pdo->prepare("UPDATE departments SET is_disabled = 0 WHERE id = ?");
        $stmt->execute([$deptId]);
        
        // Add audit log entry
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

        $auditStmt = $pdo->prepare("
            INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
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
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 