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

// Init RBAC & enforce "Remove" privilege
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Roles and Privileges', 'Remove')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Insufficient privileges']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$moduleId = isset($_POST['module_id']) ? (int)$_POST['module_id'] : 0;
if ($moduleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid module ID']);
    exit;
}

try {
    // Begin transaction.
    $pdo->beginTransaction();

    // Delete module–level privileges (role_id = 0) for this module.
    $stmt = $pdo->prepare("DELETE FROM role_module_privileges WHERE module_id = :module_id AND role_id = 0");
    $stmt->execute(['module_id' => $moduleId]);

    // Delete the module record.
    $stmt = $pdo->prepare("DELETE FROM modules WHERE id = :module_id");
    $stmt->execute(['module_id' => $moduleId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Module deleted successfully.']);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error deleting module: ' . $e->getMessage()]);
}
