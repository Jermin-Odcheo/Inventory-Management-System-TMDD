<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set JSON header
header('Content-Type: application/json');

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Retrieve and validate input
$module_name = trim($_POST['module_name'] ?? '');

if (empty($module_name)) {
    echo json_encode(['success' => false, 'message' => 'Module name is required.']);
    exit;
}

// Optional privileges sent via checkboxes, expected as an array of privilege IDs
$privilege_ids = $_POST['privileges'] ?? [];

try {
    // Start transaction
    $pdo->beginTransaction();

    // Insert the new module into the modules table
    $stmt = $pdo->prepare("INSERT INTO modules (module_name) VALUES (:module_name)");
    $stmt->execute(['module_name' => $module_name]);
    $module_id = $pdo->lastInsertId();

    // Process selected privileges if any
    if (!empty($privilege_ids) && is_array($privilege_ids)) {
        // Prepare the statement for linking privileges to the module (role_id 0)
        $linkStmt = $pdo->prepare("INSERT INTO role_module_privileges (module_id, privilege_id, role_id) VALUES (:module_id, :privilege_id, 0)");

        foreach ($privilege_ids as $privilege_id) {
            // Ensure each privilege_id is an integer (adjust validation as needed)
            $privilege_id = (int)$privilege_id;
            if ($privilege_id > 0) {
                $linkStmt->execute(['module_id' => $module_id, 'privilege_id' => $privilege_id]);
            }
        }
    }

    // Commit the transaction
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Module added successfully.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error adding module: ' . $e->getMessage()]);
}
