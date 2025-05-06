<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (
    isset($_POST['id']) && isset($_POST['permanent']) && $_POST['permanent'] == 1 && isset($_POST['module'])
) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $module = $_POST['module'];
    if ($id === false) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
        exit();
    }
    try {
        if ($module === 'Equipment Location') {
            $stmt = $pdo->prepare("DELETE FROM equipment_location WHERE equipment_location_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Equipment location permanently deleted.']);
        } elseif ($module === 'Equipment Status') {
            $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE equipment_status_id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Equipment status permanently deleted.']);
        } elseif ($module === 'Equipment Details') {
            $stmt = $pdo->prepare("DELETE FROM equipment_details WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Equipment details permanently deleted.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Unknown module for permanent delete.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
echo json_encode(['status' => 'error', 'message' => 'Invalid request.']); 