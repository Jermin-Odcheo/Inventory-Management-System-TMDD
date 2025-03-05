<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {
    $moduleId = intval($_GET['id']);

    try {
        // Updated query to match database structure
        $stmt = $pdo->prepare("
            SELECT p.priv_name 
            FROM privileges p
            JOIN role_module_privileges rmp ON p.id = rmp.privilege_id
            JOIN user_roles ur ON rmp.role_id = ur.role_id
            WHERE rmp.module_id = ? AND ur.user_id = ?
        ");
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