<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

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

    // Retrieve the selected privileges (an array of privilege IDs)
    $selectedPrivileges = $_POST['privileges'] ?? [];

    try {
        // Remove all existing privilege links for this module.
        $stmt = $pdo->prepare("DELETE FROM role_module_privileges WHERE module_id = :module_id");
        $stmt->execute(['module_id' => $moduleId]);

        // Insert each selected privilege.
        $stmtInsertLink = $pdo->prepare("INSERT INTO role_module_privileges (module_id, privilege_id) VALUES (:module_id, :privilege_id)");
        foreach ($selectedPrivileges as $privId) {
            $stmtInsertLink->execute([
                'module_id'    => $moduleId,
                'privilege_id' => $privId
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Privileges updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
