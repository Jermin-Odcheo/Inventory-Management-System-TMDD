<?php
// purchase_order.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// Check if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // This is an AJAX request, handle it separately
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        $response = array('status' => '', 'message' => '');
        
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Set current user for audit logging
                    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                    
                    // Validate required fields
                    $required_fields = ['asset_tag', 'asset_description_1', 'asset_description_2', 'specifications'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Field {$field} is required");
                        }
                    }
                    
                    // First insert the equipment
                    $stmt = $pdo->prepare("INSERT INTO equipment_details (
                        asset_tag, 
                        asset_description_1, 
                        asset_description_2, 
                        specifications, 
                        brand, 
                        model, 
                        serial_number,
                        location,
                        accountable_individual,
                        rr_no, 
                        date_created, 
                        remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $date_created = !empty($_POST['date_created']) ? $_POST['date_created'] : date('Y-m-d H:i:s');
                    
                    $values = [
                        $_POST['asset_tag'],
                        $_POST['asset_description_1'],
                        $_POST['asset_description_2'],
                        $_POST['specifications'],
                        !empty($_POST['brand']) ? $_POST['brand'] : null,
                        !empty($_POST['model']) ? $_POST['model'] : null,
                        !empty($_POST['serial_number']) ? $_POST['serial_number'] : null,
                        !empty($_POST['location']) ? $_POST['location'] : null,
                        !empty($_POST['accountable_individual']) ? $_POST['accountable_individual'] : null,
                        !empty($_POST['rr_no']) ? $_POST['rr_no'] : null,
                        $date_created,
                        !empty($_POST['remarks']) ? $_POST['remarks'] : null
                    ];
                    
                    $stmt->execute($values);
                    $newEquipmentId = $pdo->lastInsertId();
                    
                    // Prepare the new values for audit log
                    $newValues = json_encode([
                        'asset_tag' => $_POST['asset_tag'],
                        'asset_description_1' => $_POST['asset_description_1'],
                        'asset_description_2' => $_POST['asset_description_2'],
                        'specifications' => $_POST['specifications'],
                        'brand' => $_POST['brand'] ?? null,
                        'model' => $_POST['model'] ?? null,
                        'serial_number' => $_POST['serial_number'] ?? null,
                        'location' => $_POST['location'] ?? null,
                        'accountable_individual' => $_POST['accountable_individual'] ?? null,
                        'rr_no' => $_POST['rr_no'] ?? null,
                        'date_created' => $date_created,
                        'remarks' => $_POST['remarks'] ?? null
                    ]);

                    // Insert into audit_log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID,
                            EntityID,
                            Module,
                            Action,
                            Details,
                            OldVal,
                            NewVal,
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $newEquipmentId,
                        'Equipment Details',
                        'Add',
                        'New equipment added',
                        null,
                        $newValues,
                        'Successful'
                    ]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment Details has been added successfully.';
                    
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response['status'] = 'error';
                    $response['message'] = $e->getMessage();
                }
                echo json_encode($response);
                exit;
                
            case 'delete':
                try {
                    $pdo->beginTransaction();
                    
                    // Get equipment details before deletion
                    $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                    $stmt->execute([$_POST['equipment_id']]);
                    $equipmentData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$equipmentData) {
                        throw new Exception('Equipment not found');
                    }
                    
                    // Delete the equipment
                    $stmt = $pdo->prepare("DELETE FROM equipment_details WHERE id = ?");
                    $stmt->execute([$_POST['equipment_id']]);
                    
                    // Add audit log entry
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID, EntityID, Module, Action, Details, OldVal, Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $_POST['equipment_id'],
                        'Equipment Management',
                        'Delete',
                        'Equipment has been deleted',
                        json_encode($equipmentData),
                        'Successful'
                    ]);
                    
                    $pdo->commit();
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment Details deleted successfully.';
                    
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response['status'] = 'error';
                    $response['message'] = $e->getMessage();
                }
                echo json_encode($response);
                exit;
        }
        exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Set cache-control headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // Redirect to login page
    header("Location: /public/index.php");
    exit();
}

// Include the header
include('../../general/header.php');

// Initialize messages
$errors = [];
$success = "";

// Retrieve any session messages from previous requests
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// ------------------------
// DELETE PURCHASE ORDER
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM purchase_order WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Purchase Order deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Purchase Order: " . $e->getMessage()];
    }
    header("Location: purchase_order.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Ensure we're sending JSON response headers before any output
        header('Content-Type: application/json');
        
        $response = array('status' => '', 'message' => '');
        
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Debug logging
                    error_log('Starting equipment add process');
                    error_log('POST data: ' . print_r($_POST, true));
                    
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Set current user for audit logging
                    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                    
                    // Validate required fields
                    $required_fields = ['asset_tag', 'asset_description_1', 'asset_description_2', 'specifications'];
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            throw new Exception("Field {$field} is required");
                        }
                    }
                    
                    // First insert the equipment
                    $stmt = $pdo->prepare("INSERT INTO equipment_details (
                        asset_tag, 
                        asset_description_1, 
                        asset_description_2, 
                        specifications, 
                        brand, 
                        model, 
                        serial_number,
                        location,
                        accountable_individual,
                        rr_no, 
                        date_created, 
                        remarks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $date_created = !empty($_POST['date_created']) ? $_POST['date_created'] : date('Y-m-d H:i:s');
                    
                    // Prepare values array with proper null handling
                    $values = [
                        $_POST['asset_tag'],
                        $_POST['asset_description_1'],
                        $_POST['asset_description_2'],
                        $_POST['specifications'],
                        !empty($_POST['brand']) ? $_POST['brand'] : null,
                        !empty($_POST['model']) ? $_POST['model'] : null,
                        !empty($_POST['serial_number']) ? $_POST['serial_number'] : null,
                        !empty($_POST['location']) ? $_POST['location'] : null,
                        !empty($_POST['accountable_individual']) ? $_POST['accountable_individual'] : null,
                        !empty($_POST['rr_no']) ? $_POST['rr_no'] : null,
                        $date_created,
                        !empty($_POST['remarks']) ? $_POST['remarks'] : null
                    ];
                    
                    $stmt->execute($values);
                    
                    $newEquipmentId = $pdo->lastInsertId();
                    error_log('New equipment ID: ' . $newEquipmentId);
                    
                    // Prepare the new values for audit log
                    $newValues = json_encode([
                        'asset_tag' => $_POST['asset_tag'],
                        'asset_description_1' => $_POST['asset_description_1'],
                        'asset_description_2' => $_POST['asset_description_2'],
                        'specifications' => $_POST['specifications'],
                        'brand' => $_POST['brand'] ?? null,
                        'model' => $_POST['model'] ?? null,
                        'serial_number' => $_POST['serial_number'] ?? null,
                        'location' => $_POST['location'] ?? null,
                        'accountable_individual' => $_POST['accountable_individual'] ?? null,
                        'rr_no' => $_POST['rr_no'] ?? null,
                        'date_created' => $date_created,
                        'remarks' => $_POST['remarks'] ?? null
                    ]);

                    // Insert into audit_log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID,
                            EntityID,
                            Module,
                            Action,
                            Details,
                            OldVal,
                            NewVal,
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $newEquipmentId,
                        'Equipment Details',
                        'Add',
                        'New equipment added',
                        null,
                        $newValues,
                        'Successful'
                    ]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment Details has been added successfully.';
                    $_SESSION['success'] = "Equipment Details has been added successfully.";
                    error_log('Equipment added successfully');
                    
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Database error: ' . $e->getMessage());
                    $response['status'] = 'error';
                    $response['message'] = 'Error adding equipment: ' . $e->getMessage();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('General error: ' . $e->getMessage());
                    $response['status'] = 'error';
                    $response['message'] = $e->getMessage();
                }
                
                echo json_encode($response);
                exit;
                break;

            case 'update':
                header('Content-Type: application/json'); // Ensure JSON header is set
                try {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Get old equipment details for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                    $stmt->execute([$_POST['equipment_id']]);
                    $oldEquipment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$oldEquipment) {
                        throw new Exception('Equipment not found');
                    }
                    
                    // Update equipment
                    $stmt = $pdo->prepare("UPDATE equipment_details SET 
                        asset_tag = ?,
                        asset_description_1 = ?,
                        asset_description_2 = ?,
                        specifications = ?,
                        brand = ?,
                        model = ?,
                        serial_number = ?,
                        location = ?,
                        accountable_individual = ?,
                        rr_no = ?,
                        date_created = ?,
                        remarks = ?
                        WHERE id = ?");
                    
                    $values = [
                        $_POST['asset_tag'],
                        $_POST['asset_description_1'],
                        $_POST['asset_description_2'],
                        $_POST['specifications'],
                        $_POST['brand'],
                        $_POST['model'],
                        $_POST['serial_number'],
                        $_POST['location'],
                        $_POST['accountable_individual'],
                        $_POST['rr_no'],
                        $_POST['date_created'],
                        $_POST['remarks'],
                        $_POST['equipment_id']
                    ];
                    
                    $stmt->execute($values);

                    // Prepare audit log data
                    $oldValue = json_encode($oldEquipment);
                    $newValues = json_encode([
                        'asset_tag' => $_POST['asset_tag'],
                        'asset_description_1' => $_POST['asset_description_1'],
                        'asset_description_2' => $_POST['asset_description_2'],
                        'specifications' => $_POST['specifications'],
                        'brand' => $_POST['brand'],
                        'model' => $_POST['model'],
                        'serial_number' => $_POST['serial_number'],
                        'location' => $_POST['location'],
                        'accountable_individual' => $_POST['accountable_individual'],
                        'rr_no' => $_POST['rr_no'],
                        'date_created' => $_POST['date_created'],
                        'remarks' => $_POST['remarks']
                    ]);

                    // Insert audit log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID,
                            EntityID,
                            Module,
                            Action,
                            Details,
                            OldVal,
                            NewVal,
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $_POST['equipment_id'],
                        'Equipment Details',
                        'Modified',
                        'Equipment details modified',
                        $oldValue,
                        $newValues,
                        'Successful'
                    ]);

                    // Commit transaction
                    $pdo->commit();
                    
                    // Send success response
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Equipment updated successfully'
                    ]);
                    exit;
                    
                } catch (PDOException $e) {
                    // Rollback on error
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    // Send error response
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error updating equipment: ' . $e->getMessage()
                    ]);
                    exit;
                    
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    // Send error response
                    echo json_encode([
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ]);
                    exit;
                }
                break;

            case 'delete':
                try {
                    // Get equipment details before deletion for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipment_details WHERE id = ?");
                    $stmt->execute([$_POST['equipment_id']]);
                    $equipmentData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Set current user for audit logging
                    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                    
                    // Prepare audit log data
                    $oldValue = json_encode([
                        'id' => $equipmentData['id'],
                        'asset_tag' => $equipmentData['asset_tag'],
                        'asset_description_1' => $equipmentData['asset_description_1'],
                        'asset_description_2' => $equipmentData['asset_description_2'],
                        'specifications' => $equipmentData['specifications'],
                        'brand' => $equipmentData['brand'],
                        'model' => $equipmentData['model'],
                        'serial_number' => $equipmentData['serial_number'],
                        'date_created' => $equipmentData['date_created'],
                    ]);

                    // Insert into audit_log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID,
                            EntityID,
                            Module,
                            Action,
                            Details,
                            OldVal,
                            NewVal,
                            Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $equipmentData['id'],
                        'Equipment Management',
                        'Delete',
                        'Equipment has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);

                    // Now perform the delete
                    $stmt = $pdo->prepare("DELETE FROM equipment_details WHERE id = ?");
                    $stmt->execute([$_POST['equipment_id']]);
                    
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment Details deleted successfully.';
                    $_SESSION['success'] = "Equipment Details deleted successfully.";
                } catch (PDOException $e) {
                    $response['status'] = 'error';
                    $response['message'] = 'Error deleting equipment: ' . $e->getMessage();
                    $_SESSION['errors'] = ["Error deleting equipment: " . $e->getMessage()];
                }
                echo json_encode($response);
                exit;
                break;
        }
    }
}


// ------------------------
// RETRIEVE ALL EQUIPMENT DETAILS
// ------------------------
try {
    $stmt = $pdo->query("SELECT id, asset_tag, asset_description_1, asset_description_2,
                         specifications, brand, model, serial_number, 
                         location, accountable_individual, rr_no,
                         remarks, date_created 
                         FROM equipment_details 
                         ORDER BY id DESC");
    $equipmentDetails = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Details: " . $e->getMessage();
}

function safeHtml($value) {
    return htmlspecialchars($value ?? 'N/A');
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Equipment Details Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <!-- Add this in the head section after Bootstrap CSS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            padding-top: 80px;
            overflow-x: hidden; /* Prevent horizontal scrollbar */
        }
        h2.mb-4 {
            margin-top: 20px;
        }
        .main-content {
            margin-left: 300px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .table-responsive {
            overflow-x: auto; /* Change to auto to allow horizontal scrolling */
            max-width: 100%;
        }
        table {
            width: 100%;
            table-layout: auto; /* Change to auto for dynamic column widths */
        }
        th, td {
            white-space: normal; /* Allow text wrapping */
            text-overflow: clip; /* Remove ellipsis */
            min-width: 100px; /* Set minimum width for columns */
            max-width: 200px; /* Set maximum width for columns */
            padding: 8px; /* Add some padding */
            vertical-align: middle; /* Center content vertically */
        }
        /* Specific column widths */
        table th:nth-child(1), /* ID column */
        table td:nth-child(1) {
            min-width: 50px;
            max-width: 70px;
        }
        table th:nth-child(2), /* Asset Tag */
        table td:nth-child(2) {
            min-width: 100px;
            max-width: 150px;
        }
        table th:last-child, /* Actions column */
        table td:last-child {
            min-width: 160px; /* Ensure enough space for both buttons */
            white-space: nowrap; /* Keep buttons on same line */
        }
        /* Add horizontal scrollbar only when needed */
        .card-body {
            overflow-x: auto;
        }
        /* Style for action buttons to ensure they stay on one line */
        .btn-group {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
            white-space: nowrap;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include '../../general/sidebar.php'; ?>

    <h2 class="mb-4" style="margin-left: 320px;">Equipment Details Management</h2>

    <div class="container-fluid" style="margin-left: 320px; padding: 20px; width: calc(100vw - 340px);">
        <!-- Success Message -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php foreach ($errors as $err): ?>
                    <p><i class="bi bi-exclamation-triangle"></i> <?php echo $err; ?></p>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Card for List of Equipment Details with consistent alignment -->
        <div class="card shadow" style="margin-top: -10px;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul"></i> List of Equipment Details</span>
                <div class="input-group w-auto">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchEquipment" class="form-control" placeholder="Search equipment...">
                </div>
            </div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addEquipmentModal">
                            <i class="bi bi-plus-circle"></i> Add Equipment
                        </button>
                        <select class="form-select form-select-sm" id="filterEquipment" style="width: auto;">
                            <option value="">Filter Equipment Type</option>
                            <?php
                            $equipmentTypes = array_unique(array_column($equipmentDetails, 'asset_description_1'));
                            foreach($equipmentTypes as $type) {
                                if(!empty($type)) {
                                    echo "<option value='" . htmlspecialchars($type) . "'>" . htmlspecialchars($type) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <!-- Add date filter controls -->
                        <div class="d-flex gap-2 align-items-center">
                            <select class="form-select form-select-sm" id="dateFilter" style="width: auto;">
                                <option value="">Filter by Date</option>
                                <option value="desc">Newest to Oldest</option>
                                <option value="asc">Oldest to Newest</option>
                                <option value="month">Specific Month</option>
                                <option value="range">Custom Date Range</option>
                            </select>
                            <!-- Date inputs container -->
                            <div id="dateInputsContainer" style="display: none;">
                                <!-- Month Picker -->
                                <div class="d-flex gap-2" id="monthPickerContainer" style="display: none;">
                                    <select class="form-select form-select-sm" id="monthSelect" style="min-width: 130px;">
                                        <option value="">Select Month</option>
                                        <?php
                                        $months = [
                                            'January', 'February', 'March', 'April', 'May', 'June',
                                            'July', 'August', 'September', 'October', 'November', 'December'
                                        ];
                                        foreach ($months as $index => $month) {
                                            echo "<option value='" . ($index + 1) . "'>" . $month . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <select class="form-select form-select-sm" id="yearSelect" style="min-width: 110px;">
                                        <option value="">Select Year</option>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                            echo "<option value='" . $year . "'>" . $year . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Date Range Pickers -->
                                <div class="d-flex gap-2" id="dateRangePickers" style="display: none;">
                                    <input type="date" class="form-control form-control-sm" id="dateFrom" placeholder="From">
                                    <input type="date" class="form-control form-control-sm" id="dateTo" placeholder="To">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Table for displaying equipment details -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0" id="table">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px">#</th>
                                <th style="width: 100px">Asset Tag</th>
                                <th style="width: 150px">Description 1</th>
                                <th style="width: 150px">Description 2</th>
                                <th style="width: 150px">Specification</th>
                                <th style="width: 100px">Brand</th>
                                <th style="width: 100px">Model</th>
                                <th style="width: 120px">Serial #</th>
                                <th style="width: 120px">Acquired Date</th>
                                <th style="width: 120px">Created Date</th>
                                <th style="width: 120px">Modified Date</th>
                                <th style="width: 100px">RR #</th>
                                <th style="width: 150px">Location</th>
                                <th style="width: 150px">Accountable Individual</th>
                                <th style="width: 150px">Remarks</th>
                                <th style="width: 160px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipmentDetails as $equipment): ?>
                                <tr>
                                    <td><?php echo safeHtml($equipment['id']); ?></td>
                                    <td><?php echo safeHtml($equipment['asset_tag']); ?></td>
                                    <td><?php echo safeHtml($equipment['asset_description_1']); ?></td>
                                    <td><?php echo safeHtml($equipment['asset_description_2']); ?></td>
                                    <td><?php echo safeHtml($equipment['specifications']); ?></td>
                                    <td><?php echo safeHtml($equipment['brand']); ?></td>
                                    <td><?php echo safeHtml($equipment['model']); ?></td>
                                    <td><?php echo safeHtml($equipment['serial_number']); ?></td>
                                    <td><?php echo safeHtml($equipment['date_created']); ?></td>
                                    <td><?php echo !empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                                    <td><?php echo !empty($equipment['date_created']) ? date('Y-m-d H:i', strtotime($equipment['date_created'])) : ''; ?></td>
                                    <td><?php echo safeHtml($equipment['rr_no']); ?></td>
                                    <td><?php echo safeHtml($equipment['location']); ?></td>
                                    <td><?php echo safeHtml($equipment['accountable_individual']); ?></td>
                                    <td><?php echo safeHtml($equipment['remarks']); ?></td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-outline-primary edit-equipment" 
                                               data-id="<?php echo safeHtml($equipment['id']); ?>"
                                               data-asset="<?php echo safeHtml($equipment['asset_tag']); ?>"
                                               data-desc1="<?php echo safeHtml($equipment['asset_description_1']); ?>"
                                               data-desc2="<?php echo safeHtml($equipment['asset_description_2']); ?>"
                                               data-spec="<?php echo safeHtml($equipment['specifications']); ?>"
                                               data-brand="<?php echo safeHtml($equipment['brand']); ?>"
                                               data-model="<?php echo safeHtml($equipment['model']); ?>"
                                               data-serial="<?php echo safeHtml($equipment['serial_number']); ?>"
                                               data-location="<?php echo safeHtml($equipment['location']); ?>"
                                               data-accountable="<?php echo safeHtml($equipment['accountable_individual']); ?>"
                                               data-rr="<?php echo safeHtml($equipment['rr_no']); ?>"
                                               data-date="<?php echo safeHtml($equipment['date_created']); ?>"
                                               data-remarks="<?php echo safeHtml($equipment['remarks']); ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger delete-equipment" 
                                               data-id="<?php echo safeHtml($equipment['id']); ?>"
                                               href="#">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Controls -->
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <!-- Pagination Info -->
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                        id="totalRows">100</span> entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i>
                                    Previous
                                </button>

                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10" selected>10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="50">50</option>
                                </select>

                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    Next
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- New Pagination Page Numbers -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <ul class="pagination justify-content-center" id="pagination"></ul>
                        </div>
                    </div>
                </div> <!-- /.End of Pagination -->
            </div>
        </div>
    </div>

    <!-- Add Equipment Modal -->
    <div class="modal fade" id="addEquipmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addEquipmentForm">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" name="asset_tag" required>
                        </div>
                        <div class="mb-3">
                            <label for="asset_description_1" class="form-label">Description 1</label>
                            <input type="text" class="form-control" name="asset_description_1" required>
                        </div>
                        <div class="mb-3">
                            <label for="asset_description_2" class="form-label">Description 2</label>
                            <input type="text" class="form-control" name="asset_description_2" required>
                        </div>
                        <div class="mb-3">
                            <label for="specifications" class="form-label">Specification</label>
                            <textarea class="form-control" name="specifications" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand" required>
                        </div>
                        <div class="mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" required>
                        </div>
                        <div class="mb-3">
                            <label for="serial_number" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" required>
                        </div>
                        <div class="mb-3">
                            <label for="date_created" class="form-label">Date Created</label>
                            <input type="datetime-local" class="form-control" name="date_created" required>
                        </div>
                        <div class="mb-3">
                            <label for="rr_no" class="form-label">RR#</label>
                            <input type="text" class="form-control" name="rr_no">
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" name="location">
                        </div>
                        <div class="mb-3">
                            <label for="accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual">
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Add Equipment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Equipment Modal -->
    <div class="modal fade" id="editEquipmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Equipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editEquipmentForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="equipment_id" id="edit_equipment_id">
                        <div class="mb-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" name="asset_tag" id="edit_asset_tag">
                        </div>
                        <div class="mb-3">
                            <label for="edit_asset_description_1" class="form-label">Description 1</label>
                            <input type="text" class="form-control" name="asset_description_1" id="edit_asset_description_1">
                        </div>
                        <div class="mb-3">
                            <label for="edit_asset_description_2" class="form-label">Description 2</label>
                            <input type="text" class="form-control" name="asset_description_2" id="edit_asset_description_2">
                        </div>
                        <div class="mb-3">
                            <label for="edit_specifications" class="form-label">Specification</label>
                            <textarea class="form-control" name="specifications" id="edit_specifications" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" name="brand" id="edit_brand">
                        </div>
                        <div class="mb-3">
                            <label for="edit_model" class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" id="edit_model">
                        </div>
                        <div class="mb-3">
                            <label for="edit_serial_number" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" id="edit_serial_number">
                        </div>
                        <div class="mb-3">
                            <label for="edit_date_created" class="form-label">Date Acquired</label>
                            <input type="datetime-local" class="form-control" name="date_created" id="edit_date_created">
                        </div>
                        <div class="mb-3">
                            <label for="edit_rr_no" class="form-label">RR#</label>
                            <input type="text" class="form-control" name="rr_no" id="edit_rr_no">
                        </div>
                        <div class="mb-3">
                            <label for="edit_location" class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="edit_location">
                        </div>
                        <div class="mb-3">
                            <label for="edit_accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual" id="edit_accountable_individual">
                        </div>
                        <div class="mb-3">
                            <label for="edit_remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="edit_remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <!-- JavaScript for Real-Time Table Filtering -->
    <script>
        $(document).ready(function() {
            // Search functionality
            $('#searchEquipment').on('input', function() {
                filterTable();
            });

            // Filter functionality
            $('#filterEquipment').on('change', function() {
                filterTable();
            });

            // Date filter change handler
            $('#dateFilter').on('change', function() {
                const value = $(this).val();
                
                // Hide all date inputs container first
                $('#dateInputsContainer').hide();
                $('#monthPickerContainer, #dateRangePickers').hide();
                $('#dateFrom, #dateTo').hide();
                
                switch(value) {
                    case 'month':
                        $('#dateInputsContainer').show();
                        $('#monthPickerContainer').show();
                        $('#dateRangePickers').hide();
                        break;
                    case 'range':
                        $('#dateInputsContainer').show();
                        $('#dateRangePickers').show();
                        $('#monthPickerContainer').hide();
                        $('#dateFrom, #dateTo').show();
                        break;
                    default:
                        filterTable();
                        break;
                }
            });

            // Month and Year select change handler
            $('#monthSelect, #yearSelect').on('change', function() {
                if ($('#monthSelect').val() && $('#yearSelect').val()) {
                    filterTable();
                }
            });

            // Date range inputs change handler
            $('#dateFrom, #dateTo').on('change', function() {
                if ($('#dateFrom').val() && $('#dateTo').val()) {
                    filterTable();
                }
            });

            function filterTable() {
                const searchText = $('#searchEquipment').val().toLowerCase();
                const filterType = $('#filterEquipment').val();
                const dateFilterType = $('#dateFilter').val();
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                $('.table tbody tr').each(function() {
                    const $row = $(this);
                    const rowText = $row.text().toLowerCase();
                    const typeCell = $row.find('td:eq(2)').text().trim(); // Description 1 column
                    const dateCell = $row.find('td:eq(8)').text();
                    const date = new Date(dateCell);

                    // Search text match
                    const searchMatch = !searchText || rowText.includes(searchText);
                    
                    // Type filter match
                    const typeMatch = !filterType || typeCell === filterType;
                    
                    // Date filter match
                    let dateMatch = true;
                    if (dateFilterType) {
                        switch(dateFilterType) {
                            case 'month':
                                if (selectedMonth && selectedYear) {
                                    dateMatch = date.getMonth() + 1 === parseInt(selectedMonth) && 
                                              date.getFullYear() === parseInt(selectedYear);
                                }
                                break;
                            case 'range':
                                if (dateFrom && dateTo) {
                                    const from = new Date(dateFrom);
                                    const to = new Date(dateTo);
                                    to.setHours(23, 59, 59);
                                    dateMatch = date >= from && date <= to;
                                }
                                break;
                            case 'asc':
                            case 'desc':
                                const tbody = $('.table tbody');
                                const rows = tbody.find('tr').toArray();
                                rows.sort((a, b) => {
                                    const dateA = new Date($(a).find('td:eq(8)').text());
                                    const dateB = new Date($(b).find('td:eq(8)').text());
                                    return dateFilterType === 'asc' ? dateA - dateB : dateB - dateA;
                                });
                                tbody.append(rows);
                                return;
                        }
                    }

                    $row.toggle(searchMatch && typeMatch && dateMatch);
                });
            }
        });
    </script>

    <!-- Bootstrap 5 JS Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Replace your existing JavaScript section at the bottom with this -->
    <script>
    $(document).ready(function() {
        // Add Equipment
        $('#addEquipmentForm').on('submit', function(e) {
            e.preventDefault();
            
            // Remove any existing alerts
            $('#addEquipmentModal .alert').remove();
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...');
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.status === 'success') {
                        // Close modal using Bootstrap 5 method
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addEquipmentModal'));
                        modal.hide();
                        
                        // Show success message
                        const alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('.container-fluid').first().prepend(alertHtml);
                        
                        // Reset form
                        $('#addEquipmentForm')[0].reset();
                        
                        // Refresh the list
                        refreshEquipmentList();
                    } else {
                        const alertHtml = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('#addEquipmentModal .modal-body').prepend(alertHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    const alertHtml = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> Error: ${error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    $('#addEquipmentModal .modal-body').prepend(alertHtml);
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Add Equipment');
                }
            });
        });

        // Delete Equipment handler
        function attachDeleteHandlers() {
            $('.delete-equipment').off('click').on('click', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                
                if (confirm('Are you sure you want to delete this equipment?')) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: {
                            action: 'delete',
                            equipment_id: id
                        },
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                const alertHtml = `
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle"></i> ${response.message}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                                $('.container-fluid').first().prepend(alertHtml);
                                refreshEquipmentList();
                            } else {
                                const alertHtml = `
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle"></i> ${response.message}
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                `;
                                $('.container-fluid').first().prepend(alertHtml);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Delete error:', error);
                            const alertHtml = `
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i> Error deleting equipment: ${error}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            $('.container-fluid').first().prepend(alertHtml);
                        }
                    });
                }
            });
        }

        // Function to refresh equipment list
        function refreshEquipmentList() {
            $.ajax({
                url: window.location.href,
                method: 'GET',
                success: function(response) {
                    // Extract the table body content from the response
                    const newContent = $(response).find('.table tbody').html();
                    // Update the current table body
                    $('.table tbody').html(newContent);
                    // Reattach event handlers
                    attachEventHandlers();
                },
                error: function(xhr, status, error) {
                    console.error('Error refreshing list:', error);
                }
            });
        }

        // Function to attach all event handlers
        function attachEventHandlers() {
            attachDeleteHandlers();
            
            // Edit Equipment handler
            $('.edit-equipment').off('click').on('click', function() {
                const id = $(this).data('id');
                const asset = $(this).data('asset');
                const desc1 = $(this).data('desc1');
                const desc2 = $(this).data('desc2');
                const spec = $(this).data('spec');
                const brand = $(this).data('brand');
                const model = $(this).data('model');
                const serial = $(this).data('serial');
                const location = $(this).data('location');
                const accountable = $(this).data('accountable');
                const rr = $(this).data('rr');
                const date = $(this).data('date');
                const remarks = $(this).data('remarks');
                
                $('#edit_equipment_id').val(id);
                $('#edit_asset_tag').val(asset);
                $('#edit_asset_description_1').val(desc1);
                $('#edit_asset_description_2').val(desc2);
                $('#edit_specifications').val(spec);
                $('#edit_brand').val(brand);
                $('#edit_model').val(model);
                $('#edit_serial_number').val(serial);
                $('#edit_location').val(location);
                $('#edit_accountable_individual').val(accountable);
                $('#edit_rr_no').val(rr);
                $('#edit_date_created').val(date);
                $('#edit_remarks').val(remarks);
                
                $('#editEquipmentModal').modal('show');
            });
        }

        // Initial attachment of event handlers
        attachEventHandlers();

        // Update Equipment form handler
        $('#editEquipmentForm').on('submit', function(e) {
            e.preventDefault();
            
            // Remove any existing alerts
            $('#editEquipmentModal .alert').remove();
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.status === 'success') {
                        // Close modal using Bootstrap 5 method
                        const modal = bootstrap.Modal.getInstance(document.getElementById('editEquipmentModal'));
                        modal.hide();
                        
                        // Show success message
                        const alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('.container-fluid').first().prepend(alertHtml);
                        
                        // Refresh the list
                        refreshEquipmentList();
                    } else {
                        const alertHtml = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('#editEquipmentModal .modal-body').prepend(alertHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Update error:', error);
                    const alertHtml = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> Error updating equipment: ${error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    $('#editEquipmentModal .modal-body').prepend(alertHtml);
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Save Changes');
                }
            });
        });
    });
    </script>
</body>

</html>