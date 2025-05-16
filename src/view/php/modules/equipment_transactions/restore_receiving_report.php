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
    $rrId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($rrId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid receiving report ID']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE receive_report SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$rrId]);
        echo json_encode(['status' => 'success', 'message' => 'Receiving report restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['rr_ids']) && is_array($_POST['rr_ids'])) {
    $rrIds = array_filter(array_map('intval', $_POST['rr_ids']));
    if(empty($rrIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid receiving report IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($rrIds), '?'));
        $stmt = $pdo->prepare("UPDATE receive_report SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($rrIds);
        echo json_encode(['status' => 'success', 'message' => 'Selected receiving reports restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 