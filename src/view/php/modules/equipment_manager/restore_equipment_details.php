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
    $edId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($edId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid equipment details ID']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$edId]);
        echo json_encode(['status' => 'success', 'message' => 'Equipment details restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['ed_ids']) && is_array($_POST['ed_ids'])) {
    $edIds = array_filter(array_map('intval', $_POST['ed_ids']));
    if(empty($edIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid equipment details IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($edIds), '?'));
        $stmt = $pdo->prepare("UPDATE equipment_details SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($edIds);
        echo json_encode(['status' => 'success', 'message' => 'Selected equipment details restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 