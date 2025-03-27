<?php
// save_user_role.php
session_start();
require_once('../../../../../config/ims-tmdd.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if ($data === null) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}
if (empty($data)) {
    echo json_encode(['success' => false, 'error' => 'User already has that role.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    foreach ($data as $assignment) {
        $stmt->execute([$assignment['userId'], $assignment['roleId']]);
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    // Check for duplicate entry error (MySQL error code 1062)
    if ($e->errorInfo[1] == 1062) {
        echo json_encode(['success' => false, 'error' => 'User already has that role.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
}
?>
