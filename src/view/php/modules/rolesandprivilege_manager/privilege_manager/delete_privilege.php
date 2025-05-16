<?php
require_once('../../../../../../config/ims-tmdd.php');
session_start();

header('Content-Type: application/json');

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM privileges WHERE id = ?");
if ($stmt->execute([$id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
