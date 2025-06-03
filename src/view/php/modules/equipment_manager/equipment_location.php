<?php
require_once '../../../../../config/ims-tmdd.php';
require_once '../../../../control/RBACService.php'; // adjust path as needed
session_start();

// detect AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// 1) Auth guard (always run, AJAX or not)
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit;
    } else {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Management', 'View');

// 3) Button flags
$canCreate = $rbac->hasPrivilege('Equipment Management', 'Create');
$canModify = $rbac->hasPrivilege('Equipment Management', 'Modify');
$canDelete = $rbac->hasPrivilege('Equipment Management', 'Remove');

// ------------------------
// AJAX Handling Section
// ------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    // discard any buffered HTML
    ob_end_clean();
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        if (!$canModify) {
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to modify equipment locations']);
            exit;
        }

        // gather inputs
        $id                = $_POST['id'];
        $assetTag          = $_POST['asset_tag'];
        $buildingLoc       = $_POST['building_loc'];
        $floorNo           = $_POST['floor_no'];
        $specificArea      = $_POST['specific_area'];
        $personResponsible = $_POST['person_responsible'];
        // make department_id nullable
        $departmentId      = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
        $deviceState       = $_POST['device_state'];
        $remarks           = $_POST['remarks'];

        // transaction & audit
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $stmt->execute([$id]);
        $oldLocation = $stmt->fetch(PDO::FETCH_ASSOC);

        $updateStmt = $pdo->prepare("
        UPDATE equipment_location
        SET asset_tag = ?, building_loc = ?, floor_no = ?, specific_area = ?, person_responsible = ?, department_id = ?, device_state = ?, remarks = ?
        WHERE equipment_location_id = ?
    ");
        $updateStmt->execute([
            $assetTag,
            $buildingLoc,
            $floorNo,
            $specificArea,
            $personResponsible,
            $departmentId,    // will be NULL if user left it blank
            $deviceState,
            $remarks,
            $id
        ]);

        if ($updateStmt->rowCount() > 0) {
            $oldValue  = json_encode($oldLocation);
            $newValues = json_encode([
                'asset_tag'          => $assetTag,
                'building_loc'       => $buildingLoc,
                'floor_no'           => $floorNo,
                'specific_area'      => $specificArea,
                'person_responsible' => $personResponsible,
                'department_id'      => $departmentId,
                'device_state'       => $deviceState,
                'remarks'            => $remarks
            ]);
            $auditStmt = $pdo->prepare("
            INSERT INTO audit_log
            (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Equipment Location',
                'Modified',
                'Equipment location modified',
                $oldValue,
                $newValues,
                'Successful'
            ]);
        }

        $pdo->commit();

        // After successfully updating the location, also update the equipment details
        // Format location as "Building, Area" if both are available
        $combinedLocation = '';
        if (!empty($buildingLoc) && !empty($specificArea)) {
            $combinedLocation = $buildingLoc . ', ' . $specificArea;
        } elseif (!empty($buildingLoc)) {
            $combinedLocation = $buildingLoc;
        } elseif (!empty($specificArea)) {
            $combinedLocation = $specificArea;
        }
        
        // Instead of using CURL, directly update the equipment details
        try {
            $pdo->beginTransaction();
            
            // Check if the equipment details record exists
            $checkStmt = $pdo->prepare("SELECT id, location, accountable_individual 
                                  FROM equipment_details 
                                  WHERE asset_tag = ? AND is_disabled = 0
                                  ORDER BY date_created DESC LIMIT 1");
            $checkStmt->execute([$assetTag]);
            $detailsData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($detailsData) {
                // Record exists, update it
                $oldLocation = $detailsData['location'];
                $oldAccountable = $detailsData['accountable_individual'];
                $detailsId = $detailsData['id'];
                
                // Check if there are actual changes to make
                $locationChanged = (!empty($combinedLocation) && $combinedLocation !== $oldLocation);
                $accountableChanged = (!empty($personResponsible) && $personResponsible !== $oldAccountable);
                
                if ($locationChanged || $accountableChanged) {
                    // Build the update query based on what needs to be updated
                    $updateFields = [];
                    $params = [];
                    
                    if ($locationChanged) {
                        $updateFields[] = "location = ?";
                        $params[] = $combinedLocation;
                    }
                    
                    if ($accountableChanged) {
                        $updateFields[] = "accountable_individual = ?";
                        $params[] = $personResponsible;
                    }
                    
                    // Always update date_modified when saving
                    $updateFields[] = "date_modified = NOW()";
                    
                    // Add equipment details ID
                    $params[] = $detailsId;
                    
                    // Execute the update
                    $updateSql = "UPDATE equipment_details SET " . implode(", ", $updateFields) . " WHERE id = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute($params);
                    
                    // Add audit log entry if changes were made
                    if ($updateStmt->rowCount() > 0) {
                        $oldValue = json_encode([
                            'location' => $oldLocation,
                            'accountable_individual' => $oldAccountable
                        ]);
                        
                        $newValue = json_encode([
                            'location' => $locationChanged ? $combinedLocation : $oldLocation,
                            'accountable_individual' => $accountableChanged ? $personResponsible : $oldAccountable
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
                        $_SESSION['equipment_details_updated'] = true;
                        $_SESSION['updated_asset_tag'] = $assetTag;
                    }
                }
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Log the error but don't stop the process
            error_log('Error updating equipment details: ' . $e->getMessage());
        }

        $message = $updateStmt->rowCount() > 0
            ? 'Location updated successfully'
            : 'No changes were made';
        echo json_encode(['status' => 'success', 'message' => $message]);
        exit;
    } elseif ($action === 'add') {
        if (!$canCreate) {
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to create equipment locations']);
            exit;
        }
        try {
            $assetTag          = trim($_POST['asset_tag']);
            $buildingLoc       = trim($_POST['building_loc']);
            $floorNo           = trim($_POST['floor_no']);
            $specificArea      = trim($_POST['specific_area']);
            $personResponsible = trim($_POST['person_responsible']);
            $departmentId      = !empty($_POST['department_id']) ? $_POST['department_id'] : null;
            $deviceState       = trim($_POST['device_state']);
            $remarks           = trim($_POST['remarks']);

            // Validate required fields
            if (empty($assetTag)) {
                throw new Exception('Asset tag is required');
            }

            if (empty($deviceState)) {
                throw new Exception('Device state is required');
            }

            error_log(print_r($_POST, true));
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO equipment_location
                (asset_tag, building_loc, floor_no, specific_area, person_responsible, department_id, device_state, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $assetTag,
                $buildingLoc,
                $floorNo,
                $specificArea,
                $personResponsible,
                $departmentId,
                $deviceState,
                $remarks
            ]);

            if ($stmt->rowCount() > 0) {
                $newLocationId = $pdo->lastInsertId();
                $newValues = json_encode([
                    'asset_tag'          => $assetTag,
                    'building_loc'       => $buildingLoc,
                    'floor_no'           => $floorNo,
                    'specific_area'      => $specificArea,
                    'person_responsible' => $personResponsible,
                    'department_id'      => $departmentId,
                    'device_state'       => $deviceState,
                    'remarks'            => $remarks
                ]);
                $auditStmt = $pdo->prepare("
                    INSERT INTO audit_log
                    (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $auditStmt->execute([
                    $_SESSION['user_id'],
                    $newLocationId,
                    'Equipment Location',
                    'Create',
                    'New equipment location added',
                    null,
                    $newValues,
                    'Successful'
                ]);
                $pdo->commit();
                
                // After successfully adding the location, also update the equipment details
                // Format location as "Building, Area" if both are available
                $combinedLocation = '';
                if (!empty($buildingLoc) && !empty($specificArea)) {
                    $combinedLocation = $buildingLoc . ', ' . $specificArea;
                } elseif (!empty($buildingLoc)) {
                    $combinedLocation = $buildingLoc;
                } elseif (!empty($specificArea)) {
                    $combinedLocation = $specificArea;
                }
                
                // Instead of using CURL, directly update the equipment details
                try {
                    $pdo->beginTransaction();
                    
                    // Check if the equipment details record exists
                    $checkStmt = $pdo->prepare("SELECT id, location, accountable_individual 
                                          FROM equipment_details 
                                          WHERE asset_tag = ? AND is_disabled = 0
                                          ORDER BY date_created DESC LIMIT 1");
                    $checkStmt->execute([$assetTag]);
                    $detailsData = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($detailsData) {
                        // Record exists, update it
                        $oldLocation = $detailsData['location'];
                        $oldAccountable = $detailsData['accountable_individual'];
                        $detailsId = $detailsData['id'];
                        
                        // Check if there are actual changes to make
                        $locationChanged = (!empty($combinedLocation) && $combinedLocation !== $oldLocation);
                        $accountableChanged = (!empty($personResponsible) && $personResponsible !== $oldAccountable);
                        
                        if ($locationChanged || $accountableChanged) {
                            // Build the update query based on what needs to be updated
                            $updateFields = [];
                            $params = [];
                            
                            if ($locationChanged) {
                                $updateFields[] = "location = ?";
                                $params[] = $combinedLocation;
                            }
                            
                            if ($accountableChanged) {
                                $updateFields[] = "accountable_individual = ?";
                                $params[] = $personResponsible;
                            }
                            
                            // Always update date_modified when saving
                            $updateFields[] = "date_modified = NOW()";
                            
                            // Add equipment details ID
                            $params[] = $detailsId;
                            
                            // Execute the update
                            $updateSql = "UPDATE equipment_details SET " . implode(", ", $updateFields) . " WHERE id = ?";
                            $updateStmt = $pdo->prepare($updateSql);
                            $updateStmt->execute($params);
                            
                            // Add audit log entry if changes were made
                            if ($updateStmt->rowCount() > 0) {
                                $oldValue = json_encode([
                                    'location' => $oldLocation,
                                    'accountable_individual' => $oldAccountable
                                ]);
                                
                                $newValue = json_encode([
                                    'location' => $locationChanged ? $combinedLocation : $oldLocation,
                                    'accountable_individual' => $accountableChanged ? $personResponsible : $oldAccountable
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
                                $_SESSION['equipment_details_updated'] = true;
                                $_SESSION['updated_asset_tag'] = $assetTag;
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    // Log the error but don't stop the process
                    error_log('Error updating equipment details: ' . $e->getMessage());
                }
                
                echo json_encode(['status' => 'success', 'message' => 'Equipment Location added successfully']);
            } else {
                throw new Exception('No rows affected, check your input data.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage(), 'debug' => $_POST]);
        }
        exit;
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' && $isAjax
    && isset($_GET['action'], $_GET['id'])
    && $_GET['action'] === 'delete'
) {
    ob_end_clean();
    header('Content-Type: application/json');

    if (!$canDelete) {
        echo json_encode(['status' => 'error', 'message' => 'You do not have permission to delete equipment locations']);
        exit;
    }
    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM equipment_location WHERE equipment_location_id = ?");
        $stmt->execute([$id]);
        $locationData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($locationData) {
            // Get the asset tag from the location data
            $assetTag = $locationData['asset_tag'];

            $oldValues = json_encode([
                'asset_tag'          => $locationData['asset_tag'],
                'building_loc'       => $locationData['building_loc'],
                'floor_no'           => $locationData['floor_no'],
                'specific_area'      => $locationData['specific_area'],
                'person_responsible' => $locationData['person_responsible'],
                'department_id'      => $locationData['department_id'],
                'device_state'       => $locationData['device_state'],
                'remarks'            => $locationData['remarks']
            ]);

            // 1. Update equipment_location to set is_disabled = 1
            $stmt = $pdo->prepare("UPDATE equipment_location SET is_disabled = 1 WHERE equipment_location_id = ?");
            $stmt->execute([$id]);

            // Log the equipment location deletion
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log
                (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Equipment Location',
                'Remove',
                'Equipment location deleted',
                $oldValues,
                null,
                'Successful'
            ]);
            
            // When location is deleted, clear the location and accountable person in equipment details
            $buildingLoc = $locationData['building_loc'] ?? '';
            $specificArea = $locationData['specific_area'] ?? '';
            
            // Build the location string as it would appear in equipment_details
            $existingLocation = '';
            if (!empty($buildingLoc) && !empty($specificArea)) {
                $existingLocation = $buildingLoc . ', ' . $specificArea;
            } elseif (!empty($buildingLoc)) {
                $existingLocation = $buildingLoc;
            } elseif (!empty($specificArea)) {
                $existingLocation = $specificArea;
            }
            
            // Find and clear equipment details records that match this location
            if (!empty($existingLocation)) {
                $detailsStmt = $pdo->prepare("
                    UPDATE equipment_details 
                    SET location = '', accountable_individual = '', date_modified = NOW() 
                    WHERE asset_tag = ? 
                    AND is_disabled = 0 
                    AND location = ?
                ");
                $detailsStmt->execute([$assetTag, $existingLocation]);
                
                if ($detailsStmt->rowCount() > 0) {
                    // Log this change too
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log
                        (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    // We don't know the equipment_details ID, so query for it
                    $idStmt = $pdo->prepare("
                        SELECT id FROM equipment_details 
                        WHERE asset_tag = ? AND is_disabled = 0
                        ORDER BY id DESC LIMIT 1
                    ");
                    $idStmt->execute([$assetTag]);
                    $detailsId = $idStmt->fetchColumn();
                    
                    if ($detailsId) {
                        $auditStmt->execute([
                            $_SESSION['user_id'],
                            $detailsId,
                            'Equipment Details',
                            'Modified',
                            'Equipment details updated due to location deletion',
                            json_encode(['location' => $existingLocation, 'accountable_individual' => $locationData['person_responsible']]),
                            json_encode(['location' => '', 'accountable_individual' => '']),
                            'Successful'
                        ]);
                    }
                }
            }

            // Since equipment_location is the parent of Asset Tag, we don't cascade the deletion
            // to equipment_details or equipment_status

            echo json_encode(['status' => 'success', 'message' => 'Equipment Location deleted successfully']);

            $pdo->commit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Location not found']);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Error deleting Equipment Location: ' . $e->getMessage()]);
    }
    exit;
}

// Only include HTML templates for non-AJAX requests
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';

// ------------------------
// Normal (non-AJAX) Page Logic
// ------------------------

$errors = [];
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

// Live search
$q = $_GET['q'] ?? '';
if (strlen($q) > 0) {
    $stmt = $pdo->prepare("
    SELECT asset_tag, building_loc, floor_no, specific_area, person_responsible, device_state, remarks
    FROM equipment_location
    WHERE asset_tag LIKE :q
       OR building_loc LIKE :q
       OR floor_no LIKE :q
       OR specific_area LIKE :q
       OR person_responsible LIKE :q
       OR device_state LIKE :q
       OR remarks LIKE :q
    LIMIT 10
");
    $likeQ = "%$q%";
    $stmt->execute(['q' => $likeQ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class='result-item'>"
            . "<strong>Asset Tag:</strong> " . htmlspecialchars($row['asset_tag']) . " - "
            . "<strong>Building:</strong> " . htmlspecialchars($row['building_loc']) . " - "
            . "<strong>Area:</strong> " . htmlspecialchars($row['specific_area']) . " - "
            . "<strong>Person:</strong> " . htmlspecialchars($row['person_responsible']) . " - "
            . "<strong>Device State:</strong> " . htmlspecialchars($row['device_state']) . " - "
            . "<strong>Remarks:</strong> " . htmlspecialchars($row['remarks'])
            . "</div>";
    }
    exit;
}

// Fetch all equipment locations
try {
    $stmt = $pdo->query("
        SELECT el.*, d.department_name
        FROM equipment_location el
        LEFT JOIN departments d ON el.department_id = d.id
        WHERE el.is_disabled = 0
        ORDER BY el.date_created DESC
    ");
    $equipmentLocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Locations: " . $e->getMessage();
}

function safeHtml($value)
{
    return htmlspecialchars($value ?? 'N/A');
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Location Management</title>
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        /* Lower modal position for all modals */
        .modal-dialog {
            margin-top: 8vh !important;
            margin-bottom: 2vh !important;
        }
        .filtered-out {
            display: none !important;
        }

        th.sortable.asc::after {
            content: " ▲";
        }

        th.sortable.desc::after {
            content: " ▼";
        }

        /* Select2 custom styling to match other filter elements */
        .select2-container--default .select2-selection--single {
            height: 38px;
            padding: 5px;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 24px;
            color: #212529;
            padding-left: 8px;
        }

        /* Make all filter elements have consistent height */
        .filter-container select,
        .filter-container input,
        .filter-container .select2-container {
            height: calc(1.5em + 0.75rem + 2px);
        }

        /* Style the filter container elements for consistent spacing */
        .filter-container>div {
            padding: 0 8px;
        }

        /* Ensure Select2 container is properly aligned */
        .filter-container .select2-container--default .select2-selection--single {
            display: flex;
            align-items: center;
        }

        /* Match Select2 dropdown to Bootstrap form-select style */
        .select2-dropdown {
            border-color: #ced4da;
        }

        /* Match dropdown item styling */
        .select2-container--default .select2-results__option {
            padding: 6px 12px;
        }
    </style>
</head>

<body>

    <div class="main-container">
        <header class="main-header">
            <h1> Equipment Location Management</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Equipment Locations</h2>
            </div>

            <div class="card-body">
                <div class="container-fluid px-0">
                    <div class="filter-container" id="filterContainer">
                        <!-- Row 1: Create Location + Filters -->
                        <div class="row mb-2 g-2 align-items-center flex-wrap">
                            <div class="col-auto">
                                <?php if ($canCreate): ?>
                                    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                                        <i class="bi bi-plus-lg"></i> Create Location
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="filterBuilding">
                                    <option value="">Filter by Building</option>
                                    <option value="all">All Buildings</option>
                                    <?php
                                    if (!empty($equipmentLocations)) {
                                        $buildings = array_unique(array_column($equipmentLocations, 'building_loc'));
                                        foreach ($buildings as $building) {
                                            echo "<option>" . htmlspecialchars($building) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="dateFilter">
                                    <option value="">Filter by Date</option>
                                    <option value="desc">Newest to Oldest</option>
                                    <option value="asc">Oldest to Newest</option>
                                    <option value="mdy">Month-Day-Year Range</option>
                                    <option value="month">Month Range</option>
                                    <option value="year">Year Range</option>
                                    <option value="month_year">Month-Year Range</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" id="eqSearch" class="form-control" placeholder="Search Equipment...">
                                </div>
                            </div>
                        </div>
                        <!-- Row 2: View Equipment Changes, Filter, Clear -->
                        <div class="row mb-2 g-2 align-items-center">
                            <div class="col-auto d-flex gap-2 align-items-center">
                                <a href="equipLoc_change_log.php" class="btn btn-primary"><i class="bi bi-card-list"></i> View Equipment Changes</a>
                                <button type="button" id="applyFilters" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
                                <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm"><i class="bi bi-x-circle"></i> Clear</button>
                            </div>
                        </div>
                        <!-- Date Inputs Row -->
                        <div id="dateInputsContainer" class="d-flex align-items-center gap-3" style="display: none;">
                            <div class="date-group d-none flex-row" id="mdy-group">
                                <div class="d-flex flex-column me-2">
                                    <label for="dateFrom" class="form-label mb-0" style="font-size: 0.9em;">Date From</label>
                                    <input type="date" id="dateFrom" class="form-control form-control-sm" style="width: 140px;">
                                </div>
                                <div class="d-flex flex-column">
                                    <label for="dateTo" class="form-label mb-0" style="font-size: 0.9em;">Date To</label>
                                    <input type="date" id="dateTo" class="form-control form-control-sm" style="width: 140px;">
                                </div>
                            </div>
                            <div class="date-group d-none flex-row" id="month-group">
                                <div class="d-flex flex-column me-2">
                                    <label for="monthFrom" class="form-label mb-0" style="font-size: 0.9em;">Month From</label>
                                    <input type="month" id="monthFrom" class="form-control form-control-sm" style="width: 120px;">
                                </div>
                                <div class="d-flex flex-column">
                                    <label for="monthTo" class="form-label mb-0" style="font-size: 0.9em;">Month To</label>
                                    <input type="month" id="monthTo" class="form-control form-control-sm" style="width: 120px;">
                                </div>
                            </div>
                            <div class="date-group d-none flex-row" id="year-group">
                                <div class="d-flex flex-column me-2">
                                    <label for="yearFrom" class="form-label mb-0" style="font-size: 0.9em;">Year From</label>
                                    <input type="number" id="yearFrom" class="form-control form-control-sm" style="width: 90px;" min="1900" max="2100">
                                </div>
                                <div class="d-flex flex-column">
                                    <label for="yearTo" class="form-label mb-0" style="font-size: 0.9em;">Year To</label>
                                    <input type="number" id="yearTo" class="form-control form-control-sm" style="width: 90px;" min="1900" max="2100">
                                </div>
                            </div>
                            <div class="date-group d-none flex-row" id="monthyear-group">
                                <div class="d-flex flex-column me-2">
                                    <label for="monthYearFrom" class="form-label mb-0" style="font-size: 0.9em;">From (MM-YYYY)</label>
                                    <input type="month" id="monthYearFrom" class="form-control form-control-sm" style="width: 120px;">
                                </div>
                                <div class="d-flex flex-column">
                                    <label for="monthYearTo" class="form-label mb-0" style="font-size: 0.9em;">To (MM-YYYY)</label>
                                    <input type="month" id="monthYearTo" class="form-control form-control-sm" style="width: 120px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" id="table">
                        <table class="table" id="elTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="number">#</th>
                                    <th class="sortable" data-sort="string">Asset Tag</th>
                                    <th class="sortable" data-sort="string">Building</th>
                                    <th class="sortable" data-sort="string">Floor</th>
                                    <th class="sortable" data-sort="string">Area</th>
                                    <th class="sortable" data-sort="string">Person Responsible</th>
                                    <th class="sortable" data-sort="string">Department</th>
                                    <th class="sortable" data-sort="string">Device State</th>
                                    <th class="sortable" data-sort="string">Remarks</th>
                                    <th class="sortable" data-sort="date">Date Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="locationTbody">
                                <?php if (!empty($equipmentLocations)): ?>
                                    <?php foreach ($equipmentLocations as $index => $loc): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($loc['asset_tag'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($loc['building_loc'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($loc['floor_no'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($loc['specific_area'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($loc['person_responsible'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($loc['department_name'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($loc['device_state'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($loc['remarks'] ?? '') ?></td>
                                            <td><?= date('Y-m-d H:i', strtotime($loc['date_created'])) ?></td>
                                            <td>
                                                <?php if ($canModify): ?>
                                                    <button class="btn btn-sm btn-outline-info edit-location"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editLocationModal"
                                                        data-id="<?= $loc['equipment_location_id'] ?>"
                                                        data-asset="<?= htmlspecialchars($loc['asset_tag'] ?? '') ?>"
                                                        data-building="<?= htmlspecialchars($loc['building_loc'] ?? '') ?>"
                                                        data-floor="<?= htmlspecialchars($loc['floor_no'] ?? '') ?>"
                                                        data-area="<?= htmlspecialchars($loc['specific_area'] ?? '') ?>"
                                                        data-person="<?= htmlspecialchars($loc['person_responsible'] ?? '') ?>"
                                                        data-department="<?= htmlspecialchars($loc['department_id'] ?? '') ?>"
                                                        data-device-state="<?= htmlspecialchars($loc['device_state'] ?? '') ?>"
                                                        data-remarks="<?= htmlspecialchars($loc['remarks'] ?? '') ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($canDelete): ?>
                                                    <button class="btn btn-sm btn-outline-danger delete-location"
                                                        data-id="<?= $loc['equipment_location_id'] ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <td colspan="16" class="text-center py-4">
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-info-circle me-2"></i> No Equipment Location found. Click on "Create Equipment" to add a new entry.
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>


                    <!-- Pagination Controls -->
                    <div class="container-fluid">
                        <div class="row align-items-center g-3">
                            <div class="col-12 col-sm-auto">
                                <div class="text-muted">
                                    <?php $totalLogs = count($equipmentLocations); ?>
                                    <input type="hidden" id="total-users" value="<?= $totalLogs ?>">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto ms-sm-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </button>
                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="30">30</option>
                                        <option value="50">50</option>
                                    </select>
                                    <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <ul class="pagination justify-content-center" id="pagination"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>


    <!-- Add Location Modal -->
    <div class="modal fade" id="addLocationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Equipment Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addLocationForm">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag <span class="text-danger">*</span></label>
                            <select class="form-select" name="asset_tag" id="add_location_asset_tag" required style="width: 100%;">
                                <option value="">Select Asset Tag</option>
                                <?php
                                // Fetch unique asset tags from equipment_details
                                // but exclude those that already have active status records
                                $assetTags = [];

                                // Get all asset tags from equipment_details
                                $stmt1 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_details WHERE is_disabled = 0");
                                $assetTags = array_merge($assetTags, $stmt1->fetchAll(PDO::FETCH_COLUMN));

                                // Get asset tags that already have active location records
                                $stmt2 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_location WHERE is_disabled = 0");
                                $activeLocationTags = $stmt2->fetchAll(PDO::FETCH_COLUMN);

                                // Get asset tags that already have active status records
                                $stmt3 = $pdo->query("SELECT DISTINCT asset_tag FROM equipment_status WHERE is_disabled = 0");
                                $activeStatusTags = $stmt3->fetchAll(PDO::FETCH_COLUMN);

                                // Filter out asset tags that already have active location or status records
                                $availableAssetTags = array_diff($assetTags, $activeLocationTags, $activeStatusTags);

                                // Sort the available asset tags
                                sort($availableAssetTags);

                                foreach ($availableAssetTags as $tag) {
                                    echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="building_loc" class="form-label">Building Location</label>
                            <input type="text" class="form-control" name="building_loc">
                        </div>

                        <div class="mb-3">
                            <label for="floor_no" class="form-label">Floor Number</label>
                            <input type="text" class="form-control" name="floor_no" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="specific_area" class="form-label">Specific Area</label>
                            <input type="text" class="form-control" name="specific_area">
                        </div>

                        <div class="mb-3">
                            <label for="person_responsible" class="form-label">Person Responsible</label>
                            <input type="text" class="form-control" name="person_responsible">
                        </div>

                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-control" id="add_department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php
                                try {
                                    $deptStmt = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name");
                                    $departments = $deptStmt->fetchAll();
                                    foreach ($departments as $department) {
                                        echo "<option value='" . htmlspecialchars($department['id']) . "'>" . htmlspecialchars($department['department_name']) . "</option>";
                                    }
                                } catch (PDOException $e) {
                                    // Handle error if needed
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Device State</label>
                            <select class="form-select" id="devState" name="device_state" required>
                                <option value="Inventory">Inventory</option>
                                <option value="Transferred">Transferred</option>
                                <option value="Borrowed">Borrowed</option>
                                <option value="Returned">Returned</option>
                                <option value="Stationed">Stationed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>

                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="margin-right: 4px;">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Equipment Location</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Location Modal -->
    <div class="modal fade" id="editLocationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form id="editLocationForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_location_id">

                        <div class="mb-3">
                            <label for="edit_asset_tag" class="form-label"><i class="bi bi-tag"></i> Asset Tag <span class="text-danger">*</span></label>
                            <select class="form-select" name="asset_tag" id="edit_location_asset_tag" required style="width: 100%;">
                                <option value="">Select Asset Tag</option>
                                <?php
                                // Use the same $assetTags as above
                                foreach ($assetTags as $tag) {
                                    echo '<option value="' . htmlspecialchars($tag) . '">' . htmlspecialchars($tag) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_building_loc" class="form-label"><i class="bi bi-building"></i> Building Location</label>
                            <input type="text" class="form-control" id="edit_building_loc" name="building_loc">
                        </div>

                        <div class="mb-3">
                            <label for="edit_floor_no" class="form-label"><i class="bi bi-layers"></i> Floor Number</label>
                            <input type="text" class="form-control" id="edit_floor_no" name="floor_no" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="edit_specific_area" class="form-label"><i class="bi bi-pin-map"></i> Specific Area</label>
                            <input type="text" class="form-control" id="edit_specific_area" name="specific_area">
                        </div>

                        <div class="mb-3">
                            <label for="edit_person_responsible" class="form-label"><i class="bi bi-person"></i> Person Responsible</label>
                            <input type="text" class="form-control" id="edit_person_responsible" name="person_responsible">
                        </div>

                        <div class="mb-3">
                            <label for="edit_department_id" class="form-label"><i class="bi bi-building"></i> Department</label>
                            <select class="form-control" id="edit_department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php
                                try {
                                    $deptStmt = $pdo->query("SELECT id, department_name FROM departments ORDER BY department_name");
                                    $departments = $deptStmt->fetchAll();
                                    foreach ($departments as $department) {
                                        echo "<option value='" . htmlspecialchars($department['id']) . "'>" . htmlspecialchars($department['department_name']) . "</option>";
                                    }
                                } catch (PDOException $e) {
                                    // Handle error if needed
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Device State</label>
                            <select class="form-select" id="devState" name="device_state" required>
                                <option value="Inventory">Inventory</option>
                                <option value="Transferred">Transferred</option>
                                <option value="Borrowed">Borrowed</option>
                                <option value="Returned">Returned</option>
                                <option value="Stationed">Stationed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_remarks" class="form-label"><i class="bi bi-chat-left-text"></i>
                                Remarks</label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Location</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Equipment Location Modal -->
    <div class="modal fade" id="deleteEDModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this Equipment Location?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Initialize our own pagination config before loading the generic pagination script -->
    <script>
        // Override default pagination settings to use our table's ID
        window.paginationConfig = {
            tableId: 'locationTbody',      // Our table's tbody ID
            currentPage: 1,
            rowsPerPageSelectId: 'rowsPerPageSelect',
            currentPageId: 'currentPage',
            rowsPerPageId: 'rowsPerPage',
            totalRowsId: 'totalRows',
            prevPageId: 'prevPage',
            nextPageId: 'nextPage',
            paginationId: 'pagination',
            isInitialized: false
        };
        
        // Initialize our data source for pagination
        window.allRows = [];
        window.filteredRows = [];
    </script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Define pagination functions globally
        
        // Helper function to create ellipsis
        function locationCreateEllipsis() {
            const $li = $('<li>').addClass('page-item disabled');
            const $a = $('<a>')
                .addClass('page-link')
                .attr('href', '#')
                .text('...');
            $li.append($a);
            $('#pagination').append($li);
        }
        
        // Function to generate page number buttons
        function locationCreatePageNumbers(currentPage, totalPages) {
            const $pagination = $('#pagination');
            $pagination.empty();

            // Don't show pagination if there's only one page
            if (totalPages <= 1) {
                return;
            }

            // Create a simple function to add page buttons
            function createPageLink(pageNum, isActive = false) {
                const $li = $('<li>').addClass('page-item' + (isActive ? ' active' : ''));
                const $a = $('<a>')
                    .addClass('page-link')
                    .attr('href', '#')
                    .text(pageNum)
                    .click(function(e) {
                        e.preventDefault();
                        window.currentPage = pageNum;
                        locationUpdatePagination();
                    });

                $li.append($a);
                $pagination.append($li);
            }

            // First page
            createPageLink(1, currentPage === 1);

            // Ellipsis after first page if needed
            if (currentPage > 3) {
                locationCreateEllipsis();
            }

            // Pages around current page
            for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
                // Skip if it's the first or last page (already shown)
                if (i === 1 || i === totalPages) continue;
                createPageLink(i, i === currentPage);
            }

            // Ellipsis before last page if needed
            if (currentPage < totalPages - 2) {
                locationCreateEllipsis();
            }

            // Last page if more than one page
            if (totalPages > 1) {
                createPageLink(totalPages, currentPage === totalPages);
            }
        }

        // Function to update the pagination display
        function locationUpdatePagination() {
            const totalRows = window.filteredRows.length;
            const rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);
            const totalPages = Math.ceil(totalRows / rowsPerPage);

            // Clamp currentPage to valid range
            window.currentPage = Math.max(1, Math.min(window.currentPage, totalPages || 1));

            // Update info text
            $('#totalRows').text(totalRows);
            $('#currentPage').text(window.currentPage);
            $('#rowsPerPage').text(Math.min(rowsPerPage, totalRows));

            // Hide all rows first
            $('#locationTbody tr').hide();

            // Calculate range of rows to show
            const start = (window.currentPage - 1) * rowsPerPage;
            const end = Math.min(start + rowsPerPage, totalRows);

            // Show only rows for current page
            for (let i = start; i < end; i++) {
                $(window.filteredRows[i]).show();
            }

            // Enable/disable prev/next buttons
            $('#prevPage').prop('disabled', window.currentPage <= 1);
            $('#nextPage').prop('disabled', window.currentPage >= totalPages || totalPages === 0);

            // Generate page numbers
            locationCreatePageNumbers(window.currentPage, totalPages);

            // Hide pagination controls if not needed
            if (totalPages <= 1) {
                $('#prevPage, #nextPage').hide();
                $('#pagination').hide();
            } else {
                $('#prevPage, #nextPage').show();
                $('#pagination').show();
            }
        }

        // Update filterTable to support new date filter types
        function locationFilterTable() {
            var building = $('#filterBuilding').val().toLowerCase();
            var dateFilter = $('#dateFilter').val();
            var search = $('#eqSearch').val().toLowerCase();
            var dateFrom = $('#dateFrom').val();
            var dateTo = $('#dateTo').val();
            var monthFrom = $('#monthFrom').val();
            var monthTo = $('#monthTo').val();
            var yearFrom = $('#yearFrom').val();
            var yearTo = $('#yearTo').val();
            var monthYearFrom = $('#monthYearFrom').val();
            var monthYearTo = $('#monthYearTo').val();

            var rows = $('#locationTbody tr').get();

            // Sort rows by Date Created if needed
            if (dateFilter === 'desc' || dateFilter === 'asc') {
                rows.sort(function(a, b) {
                    var dateA = $(a).find('td').eq(9).text();
                    var dateB = $(b).find('td').eq(9).text();
                    var dA = new Date(dateA);
                    var dB = new Date(dateB);
                    if (dateFilter === 'desc') {
                        return dB - dA;
                    } else {
                        return dA - dB;
                    }
                });
                // Re-append sorted rows
                $.each(rows, function(idx, row) {
                    $('#locationTbody').append(row);
                });
            }

            // Reset the filtered rows array
            window.filteredRows = [];

            // Now filter rows
            $('#locationTbody tr').each(function() {
                var row = $(this);
                var show = true;

                // Building filter
                if (building && building !== 'all') {
                    var buildingText = row.find('td').eq(2).text().toLowerCase();
                    if (buildingText !== building) show = false;
                }
                // Date filter (advanced types)
                var dateText = row.find('td').eq(9).text().substring(0, 10); // 'YYYY-MM-DD'
                if (dateFilter === 'mdy') {
                    if (dateFrom && dateTo) {
                        if (dateText < dateFrom || dateText > dateTo) show = false;
                    }
                } else if (dateFilter === 'month') {
                    if (monthFrom && monthTo) {
                        var dateMonth = dateText.substring(0, 7); // 'YYYY-MM'
                        var fromMonth = monthFrom;
                        var toMonth = monthTo;
                        if (dateMonth < fromMonth || dateMonth > toMonth) show = false;
                    }
                } else if (dateFilter === 'year') {
                    if (yearFrom && yearTo) {
                        var dateYear = dateText.substring(0, 4); // 'YYYY'
                        if (dateYear < yearFrom || dateYear > yearTo) show = false;
                    }
                } else if (dateFilter === 'month_year') {
                    if (monthYearFrom && monthYearTo) {
                        var dateMonth = dateText.substring(0, 7); // 'YYYY-MM'
                        if (dateMonth < monthYearFrom || dateMonth > monthYearTo) show = false;
                    }
                }
                // Search filter (always applied)
                if (search) {
                    var rowText = row.text().toLowerCase();
                    if (!rowText.includes(search)) show = false;
                }
                
                // Add to filtered rows if it should be shown
                if (show) {
                    window.filteredRows.push(row[0]);
                }
                
                // Hide all rows initially - updatePagination will show the correct ones
                row.hide();
            });
            
            // Update pagination after filtering
            locationUpdatePagination();
        }
        
        // Make functions globally available through window object
        window.locationFilterTable = locationFilterTable;
        window.locationUpdatePagination = locationUpdatePagination;
        window.locationCreatePageNumbers = locationCreatePageNumbers;
        window.locationCreateEllipsis = locationCreateEllipsis;
    </script>
    <script>
        $(document).ready(function() {
    // --- SORTABLE COLUMN HEADERS ---
    $('#elTable th.sortable').css('cursor', 'pointer');
    $('#elTable th.sortable').off('click').on('click', function() {
        var th = $(this);
        var table = th.closest('table');
        var tbody = table.find('tbody');
        var rows = window.filteredRows.length ? window.filteredRows.slice() : tbody.find('tr').get();
        var index = th.index();
        var type = th.data('sort') || 'string';
        var asc = !th.hasClass('asc');

        // Remove sort classes from all headers
        th.siblings('.sortable').removeClass('asc desc');
        th.removeClass('asc desc');
        th.addClass(asc ? 'asc' : 'desc');

        rows.sort(function(a, b) {
            var x = $(a).find('td').eq(index).text().trim();
            var y = $(b).find('td').eq(index).text().trim();
            if (type === 'number') {
                x = parseFloat(x) || 0;
                y = parseFloat(y) || 0;
                return asc ? x - y : y - x;
            } else if (type === 'date') {
                x = new Date(x);
                y = new Date(y);
                var tx = isNaN(x.getTime()) ? (asc ? Infinity : -Infinity) : x.getTime();
                var ty = isNaN(y.getTime()) ? (asc ? Infinity : -Infinity) : y.getTime();
                return asc ? tx - ty : ty - tx;
            } else {
                x = x.toLowerCase();
                y = y.toLowerCase();
                if (x < y) return asc ? -1 : 1;
                if (x > y) return asc ? 1 : -1;
                return 0;
            }
        });

        // Update filteredRows and table
        window.filteredRows = rows;
        tbody.empty();
        $.each(rows, function(i, row) {
            tbody.append(row);
        });
        window.currentPage = 1;
        locationUpdatePagination();
    });

            // Store all table rows for pagination
            const locationRows = Array.from(document.querySelectorAll('#locationTbody tr'));
            window.allRows = locationRows;
            window.filteredRows = locationRows;

            // Initialize allRows for pagination
            window.allRows = Array.from(document.querySelectorAll('#locationTbody tr'));

            // Set default pagination values
            window.currentPage = 1;
            let rowsPerPage = parseInt($('#rowsPerPageSelect').val() || 10);

            // Initialize Select2 for Building dropdown
            if ($.fn.select2) {
                try {
                    // First destroy any existing instance
                    if ($('#filterBuilding').data('select2')) {
                        $('#filterBuilding').select2('destroy');
                    }

                    // Initialize with proper settings
                    $('#filterBuilding').select2({
                        placeholder: 'Filter by Building',
                        allowClear: true,
                        width: '100%',
                        dropdownAutoWidth: true,
                        minimumResultsForSearch: 0
                    });

                    // Set default value
                    $('#filterBuilding').val('all').trigger('change');
                } catch (e) {
                    console.error('Error initializing Select2:', e);
                }
            }

            // Apply Filters button event
            $(document).on('click', '#applyFilters', function() {
                locationFilterTable();
            });

            // Filter event handlers
            // Search filtering now only happens when the Filter button is clicked
            
            // Prevent Enter key from submitting the form when typing in search
            $('#eqSearch').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#applyFilters').click(); // Trigger the Filter button click instead
                    return false;
                }
            });
            
            // Date filter UI handling
            $('#dateFilter').on('change', function() {
                const filterType = $(this).val();
                const container = $('#dateInputsContainer');
                container.show();
                // Hide all groups first
                container.find('.date-group').addClass('d-none');
                if (!filterType || filterType === 'desc' || filterType === 'asc') {
                    container.hide();
                    return;
                }
                if (filterType === 'mdy') {
                    $('#mdy-group').removeClass('d-none');
                } else if (filterType === 'month') {
                    $('#month-group').removeClass('d-none');
                } else if (filterType === 'year') {
                    $('#year-group').removeClass('d-none');
                } else if (filterType === 'month_year') {
                    $('#monthyear-group').removeClass('d-none');
                }
            });

            $('#applyFilters').on('click', function() {
                locationFilterTable();
            });

            $('#clearFilters').on('click', function() {
                // Reset building filter to 'all'
                $('#filterBuilding').val('all').trigger('change');
                // Reset date filter to default (empty)
                $('#dateFilter').val('').trigger('change');
                // Explicitly clear all advanced date inputs
                $('#dateFrom').val('');
                $('#dateTo').val('');
                $('#monthFrom').val('');
                $('#monthTo').val('');
                $('#yearFrom').val('');
                $('#yearTo').val('');
                $('#monthYearFrom').val('');
                $('#monthYearTo').val('');
                // Hide all date input containers
                $('#dateInputsContainer .date-group').addClass('d-none');
                $('#dateInputsContainer').hide();
                // Clear search bar
                $('#eqSearch').val('');
                // Reset filteredRows to allRows so all data is shown
                window.filteredRows = window.allRows;
                window.currentPage = 1;
                // Update pagination which will show the correct rows
                locationUpdatePagination();
            });

            // Initialize pagination on page load
            setTimeout(function() {
                // Make sure filteredRows is populated correctly on initialization
                window.filteredRows = window.allRows;
                locationFilterTable();
                locationUpdatePagination();
            }, 100);

            // Add pagination control event handlers
            $('#prevPage').on('click', function() {
                if (window.currentPage > 1) {
                    window.currentPage--;
                    locationUpdatePagination();
                }
            });

            $('#nextPage').on('click', function() {
                const totalPages = Math.ceil(window.filteredRows.length / parseInt($('#rowsPerPageSelect').val() || 10));
                if (window.currentPage < totalPages) {
                    window.currentPage++;
                    locationUpdatePagination();
                }
            });

            $('#rowsPerPageSelect').on('change', function() {
                window.currentPage = 1; // Reset to first page
                locationUpdatePagination();
            });
            
            // Add back the direct update function
            window.directUpdateEquipmentDetails = function(assetTag, buildingLoc, specificArea, personResponsible) {
                console.log('Directly updating equipment details with:', {
                    assetTag,
                    buildingLoc,
                    specificArea,
                    personResponsible
                });

                // Format location as "Building, Area" if both are available
                let location = '';
                if (buildingLoc && specificArea) {
                    location = buildingLoc + ', ' + specificArea;
                } else if (buildingLoc) {
                    location = buildingLoc;
                } else if (specificArea) {
                    location = specificArea;
                }

                // Make AJAX request to update equipment details
                $.ajax({
                    url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/equipment_details_update.php',
                    method: 'POST',
                    data: {
                        action: 'update_from_location',
                        asset_tag: assetTag,
                        location: location,
                        accountable_individual: personResponsible
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        console.log('Direct equipment details update response:', response);
                        if (response.status === 'success') {
                            showToast('Equipment details updated successfully', 'success');
                        } else if (response.status === 'warning') {
                            console.warn(response.message);
                        } else {
                            console.error('Failed to update equipment details:', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating equipment details:', error);
                    }
                });
            };
        });

        // Delegate event for editing location
        $(document).on('click', '.edit-location', function() {
            // Always clean up before showing modal
            if ($('.modal-backdrop').length) {
                $('.modal-backdrop').remove();
            }
            if ($('body').hasClass('modal-open') && $('.modal.show').length === 0) {
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            }
            // Reset the form and destroy Select2 before populating
            if ($('#edit_location_asset_tag').hasClass('select2-hidden-accessible')) {
                $('#edit_location_asset_tag').select2('destroy');
            }
            if ($('#edit_department_id').hasClass('select2-hidden-accessible')) {
                $('#edit_department_id').select2('destroy');
            }
            $('#editLocationForm')[0].reset();

            var id = $(this).data('id');
            var assetTag = $(this).data('asset');
            var buildingLocation = $(this).data('building');
            var floorNumber = $(this).data('floor');
            var specificArea = $(this).data('area');
            var personResponsible = $(this).data('person');
            var departmentId = $(this).data('department');
            var remarks = $(this).data('remarks');

            // Ensure asset tag is present in the dropdown
            var $assetTagSelect = $('#edit_location_asset_tag');
            if ($assetTagSelect.find('option[value="' + assetTag + '"]').length === 0) {
                $assetTagSelect.append('<option value="' + $('<div>').text(assetTag).html() + '">' + $('<div>').text(assetTag).html() + '</option>');
            }
            $assetTagSelect.val(assetTag).trigger('change');

            $('#edit_location_id').val(id);
            $('#edit_building_loc').val(buildingLocation);
            $('#edit_floor_no').val(floorNumber);
            $('#edit_specific_area').val(specificArea);
            $('#edit_person_responsible').val(personResponsible);
            $('#edit_department_id').val(departmentId).trigger('change');
            $('#edit_remarks').val(remarks);

            // Move modal to body to ensure backdrop always works
            var modalEl = document.getElementById('editLocationModal');
            if (modalEl) {
                // Remove any previous modal instance
                if (modalEl._bootstrapModal) {
                    modalEl._bootstrapModal.hide();
                    modalEl._bootstrapModal.dispose();
                }
                // Move modal to body
                document.body.appendChild(modalEl);
                var editModal = new bootstrap.Modal(modalEl, {
                    backdrop: true
                });
                modalEl._bootstrapModal = editModal;
                editModal.show();
            }
        });

        // Global variable for deletion
        var deleteId = null;

        // Delegate event for delete button to show modal
        $(document).on('click', '.delete-location', function(e) {
            e.preventDefault();
            deleteId = $(this).data('id');
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteEDModal'));
            deleteModal.show();
        });

        // When confirm delete button is clicked, perform AJAX delete
        $('#confirmDeleteBtn').on('click', function() {
            if (deleteId) {
                $.ajax({
                    url: window.location.href,
                    method: 'GET',
                    data: {
                        action: 'delete',
                        id: deleteId
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#elTable').load(location.href + ' #elTable', function() {
                                showToast(response.message, 'success');

                                // Reinitialize pagination after reload
                                window.allRows = Array.from(document.querySelectorAll('#locationTbody tr'));
                                window.filteredRows = [...window.allRows];
                                locationFilterTable();
                                // Explicitly update pagination
                                locationUpdatePagination();
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                        var deleteModalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteEDModal'));
                        deleteModalInstance.hide();
                    },
                    error: function(xhr, status, error) {
                        showToast('Error deleting location: ' + error, 'error');
                    }
                });
            }
        });

        // AJAX submission for Add Location form using toast notifications
        $('#addLocationForm').on('submit', function(e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.data('original-text') || submitBtn.text();

            // Store the original text once
            submitBtn.data('original-text', originalBtnText);
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');

            // Capture form values before submission
            const assetTag = $('#add_location_asset_tag').val();
            const buildingLoc = $('input[name="building_loc"]', this).val();
            const specificArea = $('input[name="specific_area"]', this).val();
            const personResponsible = $('input[name="person_responsible"]', this).val();

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(result) {
                    // Always re-enable the button
                    submitBtn.prop('disabled', false).html(originalBtnText);

                    if (result.status === 'success') {
                        $('#addLocationModal').modal('hide');
                        setTimeout(function() {
                            // Remove lingering modal-backdrop and modal-open
                            if ($('.modal-backdrop').length) {
                                $('.modal-backdrop').remove();
                            }
                            if ($('body').hasClass('modal-open') && $('.modal.show').length === 0) {
                                $('body').removeClass('modal-open');
                                $('body').css('padding-right', '');
                            }
                            // Reset the form and Select2 fields
                            $('#addLocationForm')[0].reset();
                            if ($('#add_location_asset_tag').hasClass('select2-hidden-accessible')) {
                                $('#add_location_asset_tag').val('').trigger('change');
                            }
                            if ($('#add_department_id').hasClass('select2-hidden-accessible')) {
                                $('#add_department_id').val('').trigger('change');
                            }
                        }, 500);
                        $('#elTable').load(location.href + ' #elTable', function() {
                            showToast(result.message, 'success');
                            
                            // After the table is reloaded, check if equipment details were updated
                            if (assetTag && (buildingLoc || specificArea || personResponsible)) {
                                // Show a message that equipment details were also updated
                                setTimeout(function() {
                                    showToast(`Equipment Details for Asset Tag "${assetTag}" also updated with new location/responsible person information`, 'info');
                                }, 1500); // Show this message after a slight delay
                            }

                            // Reinitialize pagination after reload
                            window.allRows = Array.from(document.querySelectorAll('#locationTbody tr'));
                            window.filteredRows = [...window.allRows];
                            locationFilterTable();
                            // Explicitly update pagination
                            locationUpdatePagination();
                        });
                    } else {
                        showToast(result.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false).html(originalBtnText);
                    showToast('Error adding location: ' + error, 'error');
                }
            });
        });

        // Department searchable dropdown (Add Location Modal)
        $('#addLocationModal').on('shown.bs.modal', function() {
            $('#add_department_id').select2({
                dropdownParent: $('#addLocationModal'),
                width: '100%',
                placeholder: 'Select Department',
                allowClear: true
            });
        });
        $('#addLocationModal').on('hidden.bs.modal', function() {
            if ($('#add_department_id').hasClass('select2-hidden-accessible')) {
                $('#add_department_id').select2('destroy');
            }
            $(this).find('form')[0].reset();
        });

        // AJAX submission for Edit Location form using toast notifications
        $('#editLocationForm').on('submit', function(e) {
            e.preventDefault();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalBtnText = submitBtn.text();
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...');

            // Capture form values before submission
            const assetTag = $('#edit_location_asset_tag').val();
            const buildingLoc = $('#edit_building_loc').val();
            const specificArea = $('#edit_specific_area').val();
            const personResponsible = $('#edit_person_responsible').val();

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(result) {
                    // Always re-enable the button
                    submitBtn.prop('disabled', false).text(originalBtnText);

                    // Regardless of changes, show a success toast.
                    if (result.status === 'success') {
                        $('#editLocationModal').modal('hide');
                        setTimeout(function() {
                            // Remove lingering modal-backdrop and modal-open
                            if ($('.modal-backdrop').length) {
                                $('.modal-backdrop').remove();
                            }
                            if ($('body').hasClass('modal-open') && $('.modal.show').length === 0) {
                                $('body').removeClass('modal-open');
                                $('body').css('padding-right', '');
                            }
                            // Always restore scrolling after modal closes
                            $('body').css('overflow', '');
                        }, 500);
                        $('#elTable').load(location.href + ' #elTable', function() {
                            // Capture the values for equipment details update
                            const assetTag = $('#edit_location_asset_tag').val();
                            const buildingLoc = $('#edit_building_loc').val();
                            const specificArea = $('#edit_specific_area').val();
                            const personResponsible = $('#edit_person_responsible').val();

                            showToast(result.message, 'success');
                            
                            // After the table is reloaded, check if equipment details were updated
                            if (assetTag && (buildingLoc || specificArea || personResponsible)) {
                                // Create a combined location string
                                let combinedLocation = '';
                                if (buildingLoc && specificArea) {
                                    combinedLocation = buildingLoc + ', ' + specificArea;
                                } else if (buildingLoc) {
                                    combinedLocation = buildingLoc;
                                } else if (specificArea) {
                                    combinedLocation = specificArea;
                                }
                                
                                // Show a message that equipment details were also updated
                                setTimeout(function() {
                                    showToast(`Equipment Details for Asset Tag "${assetTag}" also updated with new location/responsible person information`, 'info');
                                }, 1500); // Show this message after a slight delay
                            }

                            // Reinitialize pagination after reload
                            window.allRows = Array.from(document.querySelectorAll('#locationTbody tr'));
                            window.filteredRows = [...window.allRows];
                            locationFilterTable();
                            // Explicitly update pagination
                            locationUpdatePagination();
                            // Always restore scrolling after table reload
                            $('body').css('overflow', '');
                        });
                    } else {
                        showToast(result.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false).text(originalBtnText);
                    showToast('Error updating location: ' + error, 'error');
                }
            });
        });

        // Asset Tag Select2 for Add Location Modal
        $('#addLocationModal').on('shown.bs.modal', function() {
            $('#add_location_asset_tag').select2({
                tags: false,
                placeholder: 'Select Asset Tag',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#addLocationModal')
            });
        });
        $('#addLocationModal').on('hidden.bs.modal', function() {
            if ($('#add_location_asset_tag').hasClass('select2-hidden-accessible')) {
                $('#add_location_asset_tag').select2('destroy');
            }
            $(this).find('form')[0].reset();
        });
        // Asset Tag Select2 for Edit Location Modal
        $('#editLocationModal').on('shown.bs.modal', function() {
            // Remove any lingering modal-backdrop and modal-open after showing
            if ($('.modal-backdrop').length > 1) {
                $('.modal-backdrop').not(':last').remove();
            }
            if ($('body').hasClass('modal-open') && $('.modal.show').length === 0) {
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            }

            // Initialize Select2 for the asset tag in edit modal
            $('#edit_location_asset_tag').select2({
                tags: false,
                placeholder: 'Select Asset Tag',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#editLocationModal')
            });
        });
        $('#editLocationModal').on('hidden.bs.modal', function() {
            // Do NOT reset form or destroy Select2 here
        });

        $(document).on('click', '[data-bs-target="#addLocationModal"]', function() {
            // Always clean up before showing modal
            if ($('.modal-backdrop').length) {
                $('.modal-backdrop').remove();
            }
            if ($('body').hasClass('modal-open') && $('.modal.show').length === 0) {
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            }
            // Move modal to body to ensure backdrop always works
            var modalEl = document.getElementById('addLocationModal');
            if (modalEl) {
                // Remove any previous modal instance
                if (modalEl._bootstrapModal) {
                    modalEl._bootstrapModal.hide();
                    modalEl._bootstrapModal.dispose();
                }
                document.body.appendChild(modalEl);
                var addModal = new bootstrap.Modal(modalEl, {
                    backdrop: true
                });
                modalEl._bootstrapModal = addModal;
                addModal.show();
            }
        });
    </script>
</body>

</html>