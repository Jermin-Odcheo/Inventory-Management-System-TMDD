<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';

if (isset($_GET['specific_area'])) {
    $specific_area = $_GET['specific_area'];
    
    if ($specific_area === 'all') {
        // Return all locations without filtering
        $stmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc");
    } else {
        // Filter by specific_area
        $stmt = $pdo->prepare("SELECT DISTINCT building_loc FROM equipment_location WHERE specific_area = ? ORDER BY building_loc");
        $stmt->execute([$specific_area]);
    }

    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/json');
    echo json_encode($locations);
}
?>
