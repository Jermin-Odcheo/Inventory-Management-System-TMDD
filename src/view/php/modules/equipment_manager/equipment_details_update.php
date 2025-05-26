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

// Initialize RBAC & enforce "Modify" privilege
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Equipment Management', 'Modify')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify equipment details']);
    exit;
}

// Check if action and required parameters are provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_from_location') {
    // Get parameters
    $assetTag = trim($_POST['asset_tag'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $accountableIndividual = trim($_POST['accountable_individual'] ?? '');
    
    // Validate required parameters
    if (empty($assetTag)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Asset tag is required']);
        exit;
    }
    
    try {
        // First, check if the equipment details record exists
        $stmt = $pdo->prepare("SELECT id, location, accountable_individual 
                              FROM equipment_details 
                              WHERE asset_tag = ? AND is_disabled = 0
                              ORDER BY date_created DESC LIMIT 1");
        $stmt->execute([$assetTag]);
        $detailsData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Start transaction
        $pdo->beginTransaction();
        
        if ($detailsData) {
            // Update existing record
            $oldLocation = $detailsData['location'];
            $oldAccountable = $detailsData['accountable_individual'];
            $detailsId = $detailsData['id'];
            
            // Check if there are actual changes to make
            $locationChanged = (!empty($location) && $location !== $oldLocation);
            $accountableChanged = (!empty($accountableIndividual) && $accountableIndividual !== $oldAccountable);
            
            if ($locationChanged || $accountableChanged) {
                // Build the update query based on what needs to be updated
                $updateFields = [];
                $params = [];
                
                if ($locationChanged) {
                    $updateFields[] = "location = ?";
                    $params[] = $location;
                }
                
                if ($accountableChanged) {
                    $updateFields[] = "accountable_individual = ?";
                    $params[] = $accountableIndividual;
                }
                
                // Always update date_modified when saving
                $updateFields[] = "date_modified = NOW()";
                
                // Add equipment details ID
                $params[] = $detailsId;
                
                // Execute the update
                $updateSql = "UPDATE equipment_details SET " . implode(", ", $updateFields) . " WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($params);
                
                // Add audit log entry
                $oldValue = json_encode([
                    'location' => $oldLocation,
                    'accountable_individual' => $oldAccountable
                ]);
                
                $newValue = json_encode([
                    'location' => $locationChanged ? $location : $oldLocation,
                    'accountable_individual' => $accountableChanged ? $accountableIndividual : $oldAccountable
                ]);
                
                $auditStmt = $pdo->prepare("INSERT INTO audit_log 
                    (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $detailsId,
                    'Equipment Details',
                    'Modified',
                    'Equipment details updated from location change',
                    $oldValue,
                    $newValue,
                    'Successful'
                ]);
                
                // Set a session flag to indicate that equipment details have been updated
                // This will be used to force a refresh on the equipment_details.php page
                $_SESSION['equipment_details_updated'] = true;
                $_SESSION['updated_asset_tag'] = $assetTag;
                
                // Commit transaction
                $pdo->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Equipment details updated successfully',
                    'changes' => [
                        'location' => $locationChanged,
                        'accountable_individual' => $accountableChanged
                    ],
                    'refresh_needed' => true
                ]);
            } else {
                // No changes needed
                $pdo->commit();
                echo json_encode([
                    'status' => 'success',
                    'message' => 'No changes needed to equipment details',
                    'changes' => []
                ]);
            }
        } else {
            // Record doesn't exist - we don't create new equipment details from location
            // as equipment details requires more information than we have
            $pdo->commit();
            echo json_encode([
                'status' => 'warning',
                'message' => 'No equipment details record found for this asset tag'
            ]);
        }
    } catch (PDOException $e) {
        // Rollback transaction if active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        // Rollback transaction if active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    // Invalid request
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request. Required parameters missing.'
    ]);
} 