<?php
session_start();
require '../../../../config/ims-tmdd.php'; // adjust path as needed

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'not_logged_in']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Update the user's last_active column to the current time
    $stmt = $pdo->prepare("
        UPDATE users
        SET last_active = NOW()
        WHERE User_ID = :userId
    ");
    $stmt->execute(['userId' => $userId]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
