<?php
// update_user_department.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['userId']) || !isset($data['roleId']) || !isset($data['departmentIds'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$userId = (int)$data['userId'];
$roleId = (int)$data['roleId'];
$departmentIds = $data['departmentIds']; // Expecting an array of department IDs

try {
    // Remove all existing department assignments for this user.
    $stmt = $pdo->prepare("DELETE FROM user_departments WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Insert new department assignments.
    $stmtInsert = $pdo->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
    foreach ($departmentIds as $deptId) {
        $stmtInsert->execute([$userId, $deptId]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
