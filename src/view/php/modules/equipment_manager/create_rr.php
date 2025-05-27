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

// Initialize RBAC
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check if action and RR# are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_rr' && isset($_POST['rr_no'])) {
    $rrNo = trim($_POST['rr_no']);
    $dateCreated = $_POST['date_created'] ?? date('Y-m-d H:i:s');
    
    // Ensure RR has proper prefix
    if (strpos($rrNo, 'RR') !== 0) {
        $rrNo = 'RR' . $rrNo;
    }
    
    // Initialize response
    $response = [
        'status' => 'error',
        'message' => 'Failed to create RR record'
    ];
    
    try {
        // First check if user has create privileges for Equipment Transactions
        if (!$rbac->hasPrivilege('Equipment Transactions', 'Create')) {
            throw new Exception('You do not have permission to create receiving reports');
        }
        
        // Check if RR already exists
        $stmt = $pdo->prepare("SELECT id FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
        $stmt->execute([$rrNo]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // RR already exists, return success with existing ID
            $response = [
                'status' => 'success',
                'message' => 'RR already exists',
                'rr_id' => $existing['id'],
                'rr_no' => $rrNo
            ];
        } else {
            // Insert new RR with minimal info
            $stmt = $pdo->prepare("INSERT INTO receive_report (rr_no, date_created, is_disabled) VALUES (?, ?, 0)");
            $result = $stmt->execute([$rrNo, $dateCreated]);
            
            if ($result) {
                $newId = $pdo->lastInsertId();
                
                // Log the creation in audit_log
                $newValues = json_encode([
                    'rr_no' => $rrNo,
                    'date_created' => $dateCreated,
                    'is_disabled' => 0
                ]);
                
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $newId,
                    'Receiving Report',
                    'Create',
                    'New RR created from Equipment Details page',
                    null,
                    $newValues,
                    'Successful'
                ]);
                
                $response = [
                    'status' => 'success',
                    'message' => 'RR created successfully',
                    'rr_id' => $newId,
                    'rr_no' => $rrNo
                ];
            }
        }
    } catch (PDOException $e) {
        // Database error
        $response = [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ];
        
        // Log the error
        error_log('RR Creation Error: ' . $e->getMessage());
    } catch (Exception $e) {
        // Permission or other error
        $response = [
            'status' => 'error',
            'message' => $e->getMessage()
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