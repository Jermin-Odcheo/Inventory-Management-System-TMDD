<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['module_id'])) {
    $moduleId = intval($_POST['module_id']);

    try {
        // Start a transaction
        $pdo->beginTransaction();

        // Delete associated privileges
        $stmt = $pdo->prepare("DELETE FROM privileges WHERE Module_ID = ?");
        $stmt->execute([$moduleId]);

        // Delete the module
        $stmt = $pdo->prepare("DELETE FROM modules WHERE Module_ID = ?");
        $stmt->execute([$moduleId]);

        // Commit the transaction
        $pdo->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Rollback the transaction in case of error
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
