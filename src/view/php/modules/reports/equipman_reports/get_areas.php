<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';

if (isset($_GET['building_loc'])) {
    $building_loc = $_GET['building_loc'];
    
    if ($building_loc === 'all') {
        // Return all specific_area without filtering
        $stmt = $pdo->query("SELECT DISTINCT specific_area FROM equipment_location ORDER BY specific_area");
    } else {
        // Filter by building_loc
        $stmt = $pdo->prepare("SELECT DISTINCT specific_area FROM equipment_location WHERE building_loc = ? ORDER BY specific_area");
        $stmt->execute([$building_loc]);
    }

    $areas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/json');
    echo json_encode($areas);
}
?>
