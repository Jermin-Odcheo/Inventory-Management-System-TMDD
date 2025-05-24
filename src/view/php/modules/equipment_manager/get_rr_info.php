<?php
session_start();
date_default_timezone_set('Asia/Manila');
ob_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

// Check for AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$isAjax) {
    http_response_code(403);
    echo "Direct access not allowed";
    exit;
}

// Initialize response
$response = [
    'status' => 'error',
    'message' => 'Invalid request',
    'data' => null
];

// Check for RR number parameter
if (!isset($_GET['rr_no']) || empty($_GET['rr_no'])) {
    $response['message'] = 'RR number is required';
    echo json_encode($response);
    exit;
}

// Sanitize input
$rr_no = trim($_GET['rr_no']);

// Add RR prefix if missing
if (strpos($rr_no, 'RR') !== 0) {
    $rr_no = 'RR' . $rr_no;
}

try {
    // Query the database for RR information
    $stmt = $pdo->prepare("SELECT accountable_individual, ai_loc FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
    $stmt->execute([$rr_no]);
    $rrInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rrInfo) {
        $response = [
            'status' => 'success',
            'message' => 'RR information retrieved successfully',
            'data' => [
                'accountable_individual' => $rrInfo['accountable_individual'],
                'location' => $rrInfo['ai_loc']
            ]
        ];
    } else {
        $response['message'] = 'RR number not found or inactive';
    }
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit; 