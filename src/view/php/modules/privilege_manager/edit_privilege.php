<?php
require_once('../../../../../config/ims-tmdd.php');
session_start();

header('Content-Type: application/json');

$id = $_POST['id'] ?? '';
$privName = trim($_POST['priv_name'] ?? '');

if (!$id || $privName === '') {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

$stmt = $pdo->prepare("UPDATE privileges SET priv_name = ? WHERE id = ?");
if ($stmt->execute([$privName, $id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
