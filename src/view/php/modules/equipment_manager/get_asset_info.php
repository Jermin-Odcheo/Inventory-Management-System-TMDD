<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$isAjax) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Direct access not allowed.']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Get asset tag from request
$assetTag = $_GET['asset_tag'] ?? '';

if (empty($assetTag)) {
    echo json_encode(['status' => 'error', 'message' => 'Asset tag is required']);
    exit;
}

try {
    // Log the request for debugging
    error_log("Fetching asset info for tag: " . $assetTag);
    
    // Query the equipment_location table to get location and person responsible
    $stmt = $pdo->prepare("SELECT building_loc, specific_area, person_responsible 
                          FROM equipment_location 
                          WHERE asset_tag = ? AND is_disabled = 0
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$assetTag]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        // Log successful data retrieval
        error_log("Asset info found for tag: " . $assetTag);
        
        // Handle null values to prevent JS errors
        $data['building_loc'] = $data['building_loc'] ?? '';
        $data['specific_area'] = $data['specific_area'] ?? '';
        $data['person_responsible'] = $data['person_responsible'] ?? '';
        
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    } else {
        error_log("No location data found for asset tag: " . $assetTag);
        echo json_encode([
            'status' => 'error',
            'message' => 'No location data found for the provided asset tag'
        ]);
    }
} catch (PDOException $e) {
    error_log('Database error in get_asset_info.php: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
} 