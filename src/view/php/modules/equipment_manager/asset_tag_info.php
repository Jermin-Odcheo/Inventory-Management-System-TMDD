<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$isAjax) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Direct access not allowed']);
    exit;
}

// Ensure user is logged in
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Check if action and asset_tag are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_asset_info' && isset($_POST['asset_tag'])) {
    $assetTag = trim($_POST['asset_tag']);
    
    // Initialize response
    $response = [
        'status' => 'error',
        'message' => 'No data found for the asset tag',
        'data' => null
    ];
    
    try {
        // First check equipment_location table
        $stmt = $pdo->prepare("SELECT building_loc, specific_area, person_responsible 
                              FROM equipment_location 
                              WHERE asset_tag = ? AND is_disabled = 0 
                              ORDER BY date_created DESC LIMIT 1");
        $stmt->execute([$assetTag]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($locationData) {
            // Format location as "Building, Area" if both are available
            $location = '';
            if (!empty($locationData['building_loc']) && !empty($locationData['specific_area'])) {
                $location = $locationData['building_loc'] . ', ' . $locationData['specific_area'];
            } elseif (!empty($locationData['building_loc'])) {
                $location = $locationData['building_loc'];
            } elseif (!empty($locationData['specific_area'])) {
                $location = $locationData['specific_area'];
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Asset tag information found',
                'data' => [
                    'location' => $location,
                    'accountable_individual' => $locationData['person_responsible'] ?? null
                ]
            ];
        } else {
            // If not found in equipment_location, check equipment_details table
            $stmt = $pdo->prepare("SELECT location, accountable_individual 
                                  FROM equipment_details 
                                  WHERE asset_tag = ? AND is_disabled = 0 
                                  ORDER BY date_created DESC LIMIT 1");
            $stmt->execute([$assetTag]);
            $detailsData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($detailsData) {
                $response = [
                    'status' => 'success',
                    'message' => 'Asset tag information found',
                    'data' => [
                        'location' => $detailsData['location'] ?? null,
                        'accountable_individual' => $detailsData['accountable_individual'] ?? null
                    ]
                ];
            }
        }
    } catch (PDOException $e) {
        $response = [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => null
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Invalid request
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request. Required parameters missing.'
    ]);
    exit;
} 