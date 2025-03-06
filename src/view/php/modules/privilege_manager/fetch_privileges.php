<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$moduleId = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;
if ($moduleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid module ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.priv_name 
          FROM role_module_privileges mp
          JOIN privileges p ON mp.privilege_id = p.id
         WHERE mp.module_id = :module_id
    ");
    $stmt->execute(['module_id' => $moduleId]);
    $privileges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'privileges' => $privileges]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
