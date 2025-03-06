<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moduleId = isset($_POST['module_id']) ? (int)$_POST['module_id'] : 0;
    if ($moduleId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid module ID']);
        exit;
    }

    try {
        // 1) Delete all links to privileges
        $stmt = $pdo->prepare("DELETE FROM role_module_privileges WHERE module_id = :module_id");
        $stmt->execute(['module_id' => $moduleId]);

        // 2) Delete the module itself
        $stmt = $pdo->prepare("DELETE FROM modules WHERE id = :module_id");
        $stmt->execute(['module_id' => $moduleId]);

        echo json_encode(['success' => true, 'message' => 'Module deleted successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// If we reach here, it’s an invalid request
echo json_encode(['success' => false, 'message' => 'Invalid request']);
