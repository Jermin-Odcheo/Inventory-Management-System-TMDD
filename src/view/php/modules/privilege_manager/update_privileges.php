<?php
// First line: Start output buffering
ob_start();
session_start();
require_once('../../../../../config/ims-tmdd.php');
require_once('../../clients/admins/RBACService.php');

// Set JSON header
header('Content-Type: application/json');

// Clean any buffered output before sending JSON
ob_clean();

// Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$userId = (int)$userId;

// Init RBAC & enforce "Modify" privilege
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Roles and Privileges', 'Modify')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Insufficient privileges']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate the module ID.
$moduleId = isset($_POST['module_id']) ? (int)$_POST['module_id'] : 0;
if ($moduleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid module ID']);
    exit;
}

// Set the default role identifier for module-level privileges.
$defaultRoleId = 0;  // Ensure this value is never null.

// Retrieve posted privileges.
$rawPrivileges = $_POST['privileges'] ?? [];
$selectedPrivileges = [];

if (is_array($rawPrivileges)) {
    // Determine if the privileges array is sequential (values) or associative (keys).
    if (array_keys($rawPrivileges) === range(0, count($rawPrivileges) - 1)) {
        $selectedPrivileges = array_map('intval', $rawPrivileges);
    } else {
        $selectedPrivileges = array_map('intval', array_keys($rawPrivileges));
    }
}

// Filter out any invalid privilege IDs.
$selectedPrivileges = array_filter($selectedPrivileges, function($id) {
    return $id > 0;
});

try {
    // Begin a transaction.
    $pdo->beginTransaction();

    // Fetch current module privileges (for module-level entries with role_id = 0).
    $stmt = $pdo->prepare("SELECT privilege_id FROM role_module_privileges WHERE module_id = :module_id AND role_id = :role_id");
    $stmt->bindValue(':module_id', $moduleId, PDO::PARAM_INT);
    $stmt->bindValue(':role_id', $defaultRoleId, PDO::PARAM_INT);
    $stmt->execute();
    $currentPrivileges = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $currentPrivileges = array_map('intval', $currentPrivileges);
    sort($currentPrivileges);
    sort($selectedPrivileges);

    // If no changes are detected, do nothing.
    if ($currentPrivileges === $selectedPrivileges) {
        echo json_encode(['success' => true, 'message' => 'No changes detected.']);
        exit;
    }

    // Delete existing module-level privileges for this module.
    $stmtDelete = $pdo->prepare("DELETE FROM role_module_privileges WHERE module_id = :module_id AND role_id = :role_id");
    $stmtDelete->bindValue(':module_id', $moduleId, PDO::PARAM_INT);
    $stmtDelete->bindValue(':role_id', $defaultRoleId, PDO::PARAM_INT);
    $stmtDelete->execute();

    // Insert each new privilege (if any) with the default role_id.
    if (!empty($selectedPrivileges)) {
        $stmtInsert = $pdo->prepare("INSERT INTO role_module_privileges (module_id, privilege_id, role_id) VALUES (:module_id, :privilege_id, :role_id)");
        foreach ($selectedPrivileges as $privId) {
            $stmtInsert->bindValue(':module_id', $moduleId, PDO::PARAM_INT);
            $stmtInsert->bindValue(':privilege_id', $privId, PDO::PARAM_INT);
            $stmtInsert->bindValue(':role_id', $defaultRoleId, PDO::PARAM_INT);
            $stmtInsert->execute();
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Module privileges updated successfully.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Error updating privileges: ' . $e->getMessage()]);
}
