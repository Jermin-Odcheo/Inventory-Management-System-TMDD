<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

// Only allow logged-in users.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
