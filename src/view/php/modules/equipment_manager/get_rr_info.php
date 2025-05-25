<?php
session_start();
date_default_timezone_set('Asia/Manila');
ob_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

// Handle AJAX requests only
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$isAjax) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Set content type to JSON
header('Content-Type: application/json');

// GET request for fetching RR info
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['rr_no'])) {
    $rr_no = trim($_GET['rr_no']);
    
    // Prepend RR if not already present
    if (strpos($rr_no, 'RR') !== 0) {
        $rr_no = 'RR' . $rr_no;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT accountable_individual, ai_loc as location FROM receive_report WHERE rr_no = ? AND is_disabled = 0");
        $stmt->execute([$rr_no]);
        $rrData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rrData) {
            echo json_encode([
                'status' => 'success',
                'data' => $rrData
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'RR number not found'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// POST request for creating a new RR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    if (isset($data['action']) && $data['action'] === 'create_rr') {
        // Validate required fields
        if (empty($data['rr_no']) || empty($data['accountable_individual'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'RR number and Accountable Individual are required'
            ]);
            exit;
        }
        
        // Format RR number (ensure it starts with RR)
        $rr_no = trim($data['rr_no']);
        if (strpos($rr_no, 'RR') !== 0) {
            $rr_no = 'RR' . $rr_no;
        }
        
        try {
            // Check if RR already exists
            $stmt = $pdo->prepare("SELECT id FROM receive_report WHERE rr_no = ?");
            $stmt->execute([$rr_no]);
            
            if ($stmt->fetch()) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'RR number already exists'
                ]);
                exit;
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert new RR
            $stmt = $pdo->prepare("INSERT INTO receive_report (rr_no, accountable_individual, ai_loc, po_no) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $rr_no,
                $data['accountable_individual'],
                $data['location'] ?? null,
                $data['po_no'] ?? null
            ]);
            
            // Add audit log entry if needed
            if (isset($_SESSION['user_id'])) {
                $newRRId = $pdo->lastInsertId();
                $auditDetails = json_encode([
                    'rr_no' => $rr_no,
                    'accountable_individual' => $data['accountable_individual'],
                    'location' => $data['location'] ?? null,
                    'po_no' => $data['po_no'] ?? null
                ]);
                
                $auditStmt = $pdo->prepare("INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $newRRId,
                    'Receive Report',
                    'Create',
                    'New Receive Report created',
                    null,
                    $auditDetails,
                    'Successful'
                ]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Receive Report created successfully',
                'data' => [
                    'rr_no' => $rr_no,
                    'accountable_individual' => $data['accountable_individual'],
                    'location' => $data['location'] ?? null
                ]
            ]);
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            echo json_encode([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

// If we reach here, it means the request was not handled
echo json_encode([
    'status' => 'error',
    'message' => 'Invalid request'
]); 