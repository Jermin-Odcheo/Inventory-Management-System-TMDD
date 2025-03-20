<?php
// delete_user_role.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['userId']) || !isset($data['roleId'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$userId = (int)$data['userId'];
$roleId = (int)$data['roleId'];

try {
    // For a soft delete, you might update an is_disabled flag instead.
    $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
    $stmt->execute([$userId, $roleId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
