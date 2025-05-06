<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id'])) {
    $esId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($esId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment status ID']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id = ? AND is_disabled = 1");
        $stmt->execute([$esId]);
        echo json_encode(['status' => 'success', 'message' => 'Equipment status restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['es_ids']) && is_array($_POST['es_ids'])) {
    $esIds = array_filter(array_map('intval', $_POST['es_ids']));
    if(empty($esIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment status IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($esIds), '?'));
        $stmt = $pdo->prepare("UPDATE equipment_status SET is_disabled = 0 WHERE equipment_status_id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($esIds);
        echo json_encode(['status' => 'success', 'message' => 'Selected equipment statuses restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 