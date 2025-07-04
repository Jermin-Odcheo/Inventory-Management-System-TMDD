<?php
/**
 * Equipment Location Update Module
 *
 * This file provides functionality to update equipment locations in the system. It handles the modification of location assignments, including tracking and historical records. The module ensures proper validation, user authorization, and maintains data consistency during the location update process.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentManager
 * @author     TMDD Interns 25'
 */
session_start();
date_default_timezone_set('Asia/Manila');
require_once('../../../../../config/ims-tmdd.php'); // Adjust path as needed

/**
 * Detects if the request is an AJAX request.
 * @var bool
 */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

/**
 * AJAX Request Validation
 * 
 * Ensures that the request is an AJAX request; otherwise, denies access with an error message.
 */
if (!$isAjax) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Direct access not allowed']);
    exit;
}

/**
 * Session Validation
 * 
 * Checks if the user is logged in by validating the session variable. If not, returns an unauthorized access error.
 */
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

/**
 * RBAC Privilege Check
 * 
 * Initializes the RBAC service and ensures the user has the 'Modify' privilege for equipment management.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Equipment Management', 'Modify')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify equipment locations']);
    exit;
}

/**
 * Request Action Validation
 * 
 * Validates that the request method is POST and the action is 'update_from_details' with required parameters.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_from_details') {
    // Get parameters
    /**
     * Asset tag from POST request, trimmed for consistency.
     * @var string
     */
    $assetTag = trim($_POST['asset_tag'] ?? '');
    /**
     * Building location from POST request, trimmed for consistency.
     * @var string
     */
    $buildingLoc = trim($_POST['building_loc'] ?? '');
    /**
     * Specific area from POST request, trimmed for consistency.
     * @var string
     */
    $specificArea = trim($_POST['specific_area'] ?? '');
    /**
     * Person responsible from POST request, trimmed for consistency.
     * @var string
     */
    $personResponsible = trim($_POST['person_responsible'] ?? '');
    
    // Validate required parameters
    if (empty($assetTag)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Asset tag is required']);
        exit;
    }
    
    try {
        // First, check if the equipment location record exists
        $stmt = $pdo->prepare("SELECT equipment_location_id, building_loc, specific_area, person_responsible 
                              FROM equipment_location 
                              WHERE asset_tag = ? AND is_disabled = 0
                              ORDER BY date_created DESC LIMIT 1");
        $stmt->execute([$assetTag]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Start transaction
        $pdo->beginTransaction();
        
        if ($locationData) {
            // Update existing record
            $oldBuildingLoc = $locationData['building_loc'];
            $oldSpecificArea = $locationData['specific_area'];
            $oldPersonResponsible = $locationData['person_responsible'];
            $locationId = $locationData['equipment_location_id'];
            
            // Check if there are actual changes to make
            $buildingChanged = (!empty($buildingLoc) && $buildingLoc !== $oldBuildingLoc);
            $areaChanged = (!empty($specificArea) && $specificArea !== $oldSpecificArea);
            $personChanged = (!empty($personResponsible) && $personResponsible !== $oldPersonResponsible);
            
            if ($buildingChanged || $areaChanged || $personChanged) {
                // Build the update query based on what needs to be updated
                $updateFields = [];
                $params = [];
                
                if ($buildingChanged) {
                    $updateFields[] = "building_loc = ?";
                    $params[] = $buildingLoc;
                }
                
                if ($areaChanged) {
                    $updateFields[] = "specific_area = ?";
                    $params[] = $specificArea;
                }
                
                if ($personChanged) {
                    $updateFields[] = "person_responsible = ?";
                    $params[] = $personResponsible;
                }
                
                // Add equipment location ID
                $params[] = $locationId;
                
                // Execute the update
                $updateSql = "UPDATE equipment_location SET " . implode(", ", $updateFields) . " WHERE equipment_location_id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($params);
                
                // Add audit log entry
                $oldValue = json_encode([
                    'building_loc' => $oldBuildingLoc,
                    'specific_area' => $oldSpecificArea,
                    'person_responsible' => $oldPersonResponsible
                ]);
                
                $newValue = json_encode([
                    'building_loc' => $buildingChanged ? $buildingLoc : $oldBuildingLoc,
                    'specific_area' => $areaChanged ? $specificArea : $oldSpecificArea,
                    'person_responsible' => $personChanged ? $personResponsible : $oldPersonResponsible
                ]);
                
                $auditStmt = $pdo->prepare("INSERT INTO audit_log 
                    (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $locationId,
                    'Equipment Location',
                    'Modified',
                    'Equipment location updated from details change',
                    $oldValue,
                    $newValue,
                    'Successful'
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Equipment location updated successfully',
                    'changes' => [
                        'building_loc' => $buildingChanged,
                        'specific_area' => $areaChanged,
                        'person_responsible' => $personChanged
                    ]
                ]);
            } else {
                // No changes needed
                $pdo->commit();
                echo json_encode([
                    'status' => 'success',
                    'message' => 'No changes needed to equipment location',
                    'changes' => []
                ]);
            }
        } else {
            // Create new record
            // Set default values for empty fields
            $floorNo = ''; // Default floor number
            $departmentId = null; // Default department ID
            $deviceState = 'inventory'; // Default device state
            $remarks = ''; // Default remarks
            
            $insertStmt = $pdo->prepare("INSERT INTO equipment_location 
                (asset_tag, building_loc, floor_no, specific_area, person_responsible, department_id, device_state, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $insertStmt->execute([
                $assetTag,
                $buildingLoc,
                $floorNo,
                $specificArea,
                $personResponsible,
                $departmentId,
                $deviceState,
                $remarks
            ]);
            
            $newLocationId = $pdo->lastInsertId();
            
            // Add audit log entry
            $newValue = json_encode([
                'asset_tag' => $assetTag,
                'building_loc' => $buildingLoc,
                'floor_no' => $floorNo,
                'specific_area' => $specificArea,
                'person_responsible' => $personResponsible,
                'department_id' => $departmentId,
                'device_state' => $deviceState,
                'remarks' => $remarks
            ]);
            
            $auditStmt = $pdo->prepare("INSERT INTO audit_log 
                (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $auditStmt->execute([
                $_SESSION['user_id'],
                $newLocationId,
                'Equipment Location',
                'Create',
                'Equipment location created from details',
                null,
                $newValue,
                'Successful'
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            echo json_encode([
                'status' => 'success',
                'message' => 'New equipment location created successfully',
                'location_id' => $newLocationId
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