<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address for logging.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (isset($_POST['id'])) {
    $poId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($poId === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid purchase order ID']);
        exit();
    }
    try {
        // Update only if the purchase order is archived (is_disabled = 1)
        $stmt = $pdo->prepare("UPDATE purchase_order SET is_disabled = 0 WHERE id = ? AND is_disabled = 1");
        $stmt->execute([$poId]);
        echo json_encode(['status' => 'success', 'message' => 'Purchase order restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else if (isset($_POST['po_ids']) && is_array($_POST['po_ids'])) {
    $poIds = array_filter(array_map('intval', $_POST['po_ids']));
    if(empty($poIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid purchase order IDs provided']);
        exit();
    }
    try {
        $placeholders = implode(",", array_fill(0, count($poIds), '?'));
        $stmt = $pdo->prepare("UPDATE purchase_order SET is_disabled = 0 WHERE id IN ($placeholders) AND is_disabled = 1");
        $stmt->execute($poIds);
        echo json_encode(['status' => 'success', 'message' => 'Selected purchase orders restored successfully']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No purchase order selected']);
}
?> 