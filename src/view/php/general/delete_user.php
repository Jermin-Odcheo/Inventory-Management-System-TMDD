<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require '../../../../config/ims-tmdd.php';

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $userId = $_GET['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE User_ID = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
