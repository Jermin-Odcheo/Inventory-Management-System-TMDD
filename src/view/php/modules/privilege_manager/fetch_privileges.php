<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['module_id'])) {
    $moduleId = intval($_GET['module_id']);

    try {
        $stmt = $pdo->prepare("SELECT Privilege_Name FROM privileges WHERE Module_ID = ?");
        $stmt->execute([$moduleId]);
        $privileges = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode(['success' => true, 'privileges' => $privileges]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
