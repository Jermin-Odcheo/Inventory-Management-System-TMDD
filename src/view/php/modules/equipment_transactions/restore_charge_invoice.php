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
    $ciId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($ciId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid charge invoice ID']);
        exit();
    }
    try {
        $stmt = $pdo->prepare("UPDATE charge_invoice SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$ciId]);
        echo json_encode(['status' => 'success', 'message' => 'Charge invoice restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['ci_ids']) && is_array($_POST['ci_ids'])) {
    $ciIds = array_filter(array_map('intval', $_POST['ci_ids']));
    if(empty($ciIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid charge invoice IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($ciIds), '?'));
        $stmt = $pdo->prepare("UPDATE charge_invoice SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($ciIds);
        echo json_encode(['status' => 'success', 'message' => 'Selected charge invoices restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} 