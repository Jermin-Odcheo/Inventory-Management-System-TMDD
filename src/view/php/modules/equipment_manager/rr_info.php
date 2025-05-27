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

// Check if action and rr_no are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_rr_info' && isset($_POST['rr_no'])) {
    $rrNo = trim($_POST['rr_no']);
    
    // Remove 'RR' prefix if present for consistent querying
    if (strpos($rrNo, 'RR') === 0) {
        $rrNoForQuery = substr($rrNo, 2);
    } else {
        $rrNoForQuery = $rrNo;
        $rrNo = 'RR' . $rrNo; // Add prefix for response
    }
    
    // Initialize response
    $response = [
        'status' => 'error',
        'message' => 'No data found for the RR number',
        'data' => null
    ];
    
    try {
        // First get the PO number from the receive_report table
        $stmt = $pdo->prepare("SELECT po_no FROM receive_report WHERE rr_no = ? OR rr_no = ? AND is_disabled = 0");
        $stmt->execute([$rrNo, 'RR' . $rrNoForQuery]);
        $rrData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rrData && !empty($rrData['po_no'])) {
            $poNo = $rrData['po_no'];
            
            // Now get the charge invoice date from the charge_invoice table
            $stmt = $pdo->prepare("SELECT date_of_purchase FROM charge_invoice 
                                  WHERE po_no = ? AND is_disabled = 0 
                                  ORDER BY date_created DESC LIMIT 1");
            $stmt->execute([$poNo]);
            $ciData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ciData && !empty($ciData['date_of_purchase'])) {
                $response = [
                    'status' => 'success',
                    'message' => 'Charge invoice information found',
                    'data' => [
                        'date_acquired' => $ciData['date_of_purchase']
                    ]
                ];
            } else {
                // We have RR and PO but no CI data
                $response = [
                    'status' => 'partial',
                    'message' => 'PO found but no charge invoice information',
                    'data' => null
                ];
            }
        } else {
            // No RR data found
            $response = [
                'status' => 'error',
                'message' => 'No receiving report found for the provided RR number',
                'data' => null
            ];
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