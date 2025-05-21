<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';

header('Content-Type: application/json');

try {
    if (!isset($pdo)) {
        throw new Exception('Database connection not established');
    }

    $action = $_GET['action'] ?? '';
    $response = [];

    if ($action === 'get_locations') {
        $stmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc");
        $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($action === 'get_specific_areas') {
        $building_loc = $_GET['building_loc'] ?? '';
        if ($building_loc && $building_loc !== 'all') {
            $stmt = $pdo->prepare("SELECT DISTINCT specific_area FROM equipment_location WHERE building_loc = ? ORDER BY specific_area");
            $stmt->execute([$building_loc]);
            $response['specific_areas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = $pdo->query("SELECT DISTINCT specific_area FROM equipment_location ORDER BY specific_area");
            $response['specific_areas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } elseif ($action === 'get_locations_for_area') {
        $specific_area = $_GET['specific_area'] ?? '';
        if ($specific_area && $specific_area !== 'all') {
            $stmt = $pdo->prepare("SELECT DISTINCT building_loc FROM equipment_location WHERE specific_area = ? ORDER BY building_loc");
            $stmt->execute([$specific_area]);
            $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $stmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc");
            $response['locations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } else {
        throw new Exception('Invalid action');
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
