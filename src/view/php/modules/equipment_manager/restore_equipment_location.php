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
    $elId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($elId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment location ID']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id = ? AND is_disabled = 1");
        $stmt->execute([$elId]);
        echo json_encode(['status' => 'success', 'message' => 'Equipment location restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['el_ids']) && is_array($_POST['el_ids'])) {
    $elIds = array_filter(array_map('intval', $_POST['el_ids']));
    if(empty($elIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment location IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($elIds), '?'));
        $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 0 WHERE equipment_location_id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($elIds);
        echo json_encode(['status' => 'success', 'message' => 'Selected equipment locations restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 