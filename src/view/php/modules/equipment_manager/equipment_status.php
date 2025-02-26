<?php
session_start();
require_once('../../../../../config/ims-tmdd.php');

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Initialize response array
$response = array('status' => '', 'message' => '');

// Add this after session_start() and database connection
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

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $pdo->beginTransaction();

                    // Insert equipment status
                    $stmt = $pdo->prepare("INSERT INTO equipmentstatus (AssetTag, Status, Remarks, AccountableIndividual, CreatedDate) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['status'],
                        $_POST['remarks'],
                        $_POST['accountable_individual']
                    ]);

                    $newStatusId = $pdo->lastInsertId();

                    // Prepare audit log data
                    $newValues = json_encode([
                        'AssetTag' => $_POST['asset_tag'],
                        'Status' => $_POST['status'],
                        'Remarks' => $_POST['remarks'],
                        'AccountableIndividual' => $_POST['accountable_individual']
                    ]);

                    // Insert audit log
                    $auditStmt = $pdo->prepare("
                        INSERT INTO audit_log (
                            UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $newStatusId,
                        'Equipment Status',
                        'Add',
                        'New equipment status added',
                        null,
                        $newValues,
                        'Successful'
                    ]);

                    $pdo->commit();
                    
                    $response['status'] = 'success';
                    $response['message'] = 'Equipment Status has been added successfully.';
                    $_SESSION['success'] = "Equipment Status has been added successfully.";
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response['status'] = 'error';
                    $response['message'] = 'Error adding status: ' . $e->getMessage();
                    $_SESSION['errors'] = ["Error adding status: " . $e->getMessage()];
                }
                break;

            case 'update':
                try {
                    $pdo->beginTransaction();

                    // Get old status details for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipmentstatus WHERE EquipmentStatusID = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $oldStatus = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Update equipment status
                    $stmt = $pdo->prepare("UPDATE equipmentstatus SET 
                        AssetTag = ?, 
                        Status = ?, 
                        Remarks = ?, 
                        AccountableIndividual = ?
                        WHERE EquipmentStatusID = ?");
                    
                    $stmt->execute([
                        $_POST['asset_tag'],
                        $_POST['status'],
                        $_POST['remarks'],
                        $_POST['accountable_individual'],
                        $_POST['status_id']
                    ]);

                    // Prepare audit log data
                    $oldValues = json_encode([
                        'AssetTag' => $oldStatus['AssetTag'],
                        'Status' => $oldStatus['Status'],
                        'Remarks' => $oldStatus['Remarks'],
                        'AccountableIndividual' => $oldStatus['AccountableIndividual'],
                        'CreatedDate' => $oldStatus['CreatedDate'],
                        'ModifiedDate' => $oldStatus['ModifiedDate']
                    ]);

                    $newValues = json_encode([
                        'AssetTag' => $_POST['asset_tag'],
                        'Status' => $_POST['status'],
                        'Remarks' => $_POST['remarks'],
                        'AccountableIndividual' => $_POST['accountable_individual'],
                        'CreatedDate' => $oldStatus['CreatedDate'],
                        'ModifiedDate' => date('Y-m-d H:i:s')
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
                        $_POST['status_id'],
                        'Equipment Status',
                        'Modified',
                        'Equipment status modified',
                        $oldValues,
                        $newValues,
                        'Successful'
                    ]);

                    $pdo->commit();
                    $response['status'] = 'success';
                    $response['message'] = 'Status updated successfully';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response['status'] = 'error';
                    $response['message'] = 'Error updating status: ' . $e->getMessage();
                }
                echo json_encode($response);
                break;

            case 'delete':
                try {
                    if (!isset($_POST['status_id'])) {
                        throw new Exception('Status ID is required');
                    }

                    // Get status details before deletion for audit log
                    $stmt = $pdo->prepare("SELECT * FROM equipmentstatus WHERE EquipmentStatusID = ?");
                    $stmt->execute([$_POST['status_id']]);
                    $statusData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$statusData) {
                        throw new Exception('Status not found');
                    }

                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Set current user for audit logging
                    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
                    
                    // Prepare audit log data
                    $oldValue = json_encode([
                        'EquipmentStatusID' => $statusData['EquipmentStatusID'],
                        'AssetTag' => $statusData['AssetTag'],
                        'Status' => $statusData['Status'],
                        'Remarks' => $statusData['Remarks']
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
                        $statusData['EquipmentStatusID'],
                        'Equipment Management',
                        'Delete',
                        'Equipment status has been deleted',
                        $oldValue,
                        null,
                        'Successful'
                    ]);

                    // Now perform the delete
                    $stmt = $pdo->prepare("DELETE FROM equipmentstatus WHERE EquipmentStatusID = ?");
                    $stmt->execute([$_POST['status_id']]);
                    
                    // Commit transaction
                    $pdo->commit();
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Equipment Status deleted successfully.',
                        $_SESSION['success'] = "Equipment Status deleted successfully."
                    ];
                } catch (Exception $e) {
                    // Rollback transaction on error
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $response = [
                        'status' => 'error',
                        'message' => 'Error deleting status: ' . $e->getMessage(),
                        $_SESSION['errors'] = ["Error deleting status: " . $e->getMessage()]
                    ];
                }
                
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
                break;
        }
        echo json_encode($response);
        exit;
    }
}

// Add this near the start of your PHP code, after session and database connection
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        // Get status details before deletion for audit log
        $stmt = $pdo->prepare("SELECT * FROM equipmentstatus WHERE EquipmentStatusID = ?");
        $stmt->execute([$id]);
        $statusData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($statusData) {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Set current user for audit logging
            $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
            
            // Prepare audit log data
            $oldValue = json_encode([
                'EquipmentStatusID' => $statusData['EquipmentStatusID'],
                'AssetTag' => $statusData['AssetTag'],
                'Status' => $statusData['Status'],
                'Remarks' => $statusData['Remarks']
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
                $statusData['EquipmentStatusID'],
                'Equipment Management',
                'Delete',
                'Equipment status has been deleted',
                $oldValue,
                null,
                'Successful'
            ]);

            // Now perform the delete
            $stmt = $pdo->prepare("DELETE FROM equipmentstatus WHERE EquipmentStatusID = ?");
            $stmt->execute([$id]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success'] = "Equipment Status deleted successfully.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error deleting Equipment Status: " . $e->getMessage();
    }
    header("Location: equipment_status.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Status Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add sidebar CSS -->
    <link rel="stylesheet" href="/src/view/styles/css/sidebar.css">

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 300px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .search-container {
            width: 250px;
        }
        .search-container input {
            padding-right: 30px;
        }
        .search-container i {
            color: #6c757d;
            pointer-events: none;
        }
        .form-select-sm {
            min-width: 150px;
        }
        
        .d-flex.gap-2 {
            gap: 0.5rem !important;
        }
    </style>

</head>
<body>
    <!-- Include Sidebar -->
    <?php include('../../general/sidebar.php'); ?>
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
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

            <div class="row">
                <main class="col-md-12 px-md-4 py-4">
                    <div class="card shadow">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-list-ul"></i> List of Equipment Status</span>
                            <!-- Move search to header -->
                            <div class="input-group w-auto">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" id="searchStatus" class="form-control" placeholder="Search status...">
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <!-- Add button and filter -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addStatusModal">
                                        <i class="bi bi-plus-circle"></i> Add New Status
                                    </button>
                                    <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                                        <option value="">Filter By Status</option>
                                        <option value="Working">Working</option>
                                        <option value="Defective">Defective</option>
                                        <option value="Replacement">Replacement</option>
                                        <option value="Maintenance">Maintenance</option>
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

                            <!-- Status List Table -->
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-sm mb-0" id="table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 7%">Status ID</th>
                                            <th style="width: 13%">Asset Tag</th>
                                            <th style="width: 15%">Status</th>
                                            <th style="width: 15%">Accountable Individual</th>
                                            <th style="width: 12%">Created Date</th>
                                            <th style="width: 12%">Modified Date</th>
                                            <th style="width: 15%">Remarks</th>
                                            <th style="width: 11%">Operations</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM equipmentstatus ORDER BY CreatedDate DESC");
                                            while ($row = $stmt->fetch()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($row['EquipmentStatusID']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['AssetTag']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['Status']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['AccountableIndividual']) . "</td>";
                                                echo "<td>" . date('Y-m-d H:i', strtotime($row['CreatedDate'])) . "</td>";
                                                echo "<td>" . date('Y-m-d H:i', strtotime($row['ModifiedDate'])) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['Remarks']) . "</td>";
                                                echo "<td>
                                                        <div class='d-flex justify-content-center gap-2'>
                                                            <button class='btn btn-sm btn-outline-primary edit-status' 
                                                                    data-id='" . htmlspecialchars($row['EquipmentStatusID']) . "'
                                                                    data-asset='" . htmlspecialchars($row['AssetTag']) . "'
                                                                    data-status='" . htmlspecialchars($row['Status']) . "'
                                                                    data-accountable='" . htmlspecialchars($row['AccountableIndividual']) . "'
                                                                    data-remarks='" . htmlspecialchars($row['Remarks']) . "'>
                                                                <i class='far fa-edit'></i> Edit
                                                            </button>
                                                            <button class='btn btn-sm btn-outline-danger delete-status' 
                                                                    data-id='" . htmlspecialchars($row['EquipmentStatusID']) . "'>
                                                                <i class='far fa-trash-alt'></i> Delete
                                                            </button>
                                                        </div>
                                                    </td>";
                                                echo "</tr>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<tr><td colspan='7'>Error loading data: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination Controls -->
                            <div class="container-fluid">
                                <div class="row align-items-center g-3">
                                    <!-- Pagination Info -->
                                    <div class="col-12 col-sm-auto">
                                        <div class="text-muted">
                                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span
                                                    id="totalRows">0</span> entries
                                        </div>
                                    </div>

                                    <!-- Pagination Controls -->
                                    <div class="col-12 col-sm-auto ms-sm-auto">
                                        <div class="d-flex align-items-center gap-2">
                                            <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                                <i class="bi bi-chevron-left"></i>
                                                Previous
                                            </button>

                                            <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                                <option value="10">10</option>
                                                <option value="20" selected>20</option>
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                            </select>

                                            <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                                Next
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Add Status Modal -->
    <div class="modal fade" id="addStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addStatusForm">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" name="asset_tag" required>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="Working">Working</option>
                                <option value="Defective">Defective</option>
                                <option value="Replacement">Replacement</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual" required>
                        </div>
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Add Status</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div class="modal fade" id="editStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editStatusForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="status_id" id="edit_status_id">
                        <div class="mb-3">
                            <label for="edit_asset_tag" class="form-label">Asset Tag</label>
                            <input type="text" class="form-control" name="asset_tag" id="edit_asset_tag" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="Working">Working</option>
                                <option value="Defective">Defective</option>
                                <option value="Replacement">Replacement</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_accountable_individual" class="form-label">Accountable Individual</label>
                            <input type="text" class="form-control" name="accountable_individual" id="edit_accountable_individual" required>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add Font Awesome for sidebar icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <!-- Keep existing JavaScript code -->
    <script>
        $(document).ready(function() {
            // Add Status
            $('#addStatusForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        try {
                            const result = JSON.parse(response);
                            if (result.status === 'success') {
                                $('#addStatusModal').modal('hide');
                                location.reload();
                            } else {
                                alert(result.message);
                            }
                        } catch (e) {
                            console.error('Parse error:', e);
                            location.reload();
                        }
                    }
                });
            });

            // Edit Status
            $('.edit-status').click(function() {
                var id = $(this).data('id');
                var asset = $(this).data('asset');
                var status = $(this).data('status');
                var accountable = $(this).data('accountable');
                var remarks = $(this).data('remarks');
                
                $('#edit_status_id').val(id);
                $('#edit_asset_tag').val(asset);
                $('#edit_status').val(status);
                $('#edit_accountable_individual').val(accountable);
                $('#edit_remarks').val(remarks);
                
                $('#editStatusModal').modal('show');
            });

            // Update Status
            $('#editStatusForm').on('submit', function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'equipment_status.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        location.reload();
                    }
                });
            });

            // Search functionality
            $('#searchStatus').on('input', function() {
                filterTable();
            });

            // Filter functionality
            $('#filterStatus').on('change', function() {
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

            // Update the filterTable function
            function filterTable() {
                const searchText = $('#searchStatus').val().toLowerCase();
                const filterStatus = $('#filterStatus').val().toLowerCase();
                const filterType = $('#dateFilter').val();
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                $(".table tbody tr").each(function() {
                    const row = $(this);
                    const rowText = row.text().toLowerCase();
                    const statusCell = row.find('td:eq(2)').text().toLowerCase();
                    const dateCell = row.find('td:eq(4)').text(); // Adjust index based on date column
                    const date = new Date(dateCell);

                    const searchMatch = rowText.indexOf(searchText) > -1;
                    const statusMatch = !filterStatus || statusCell === filterStatus;
                    let dateMatch = true;

                    switch(filterType) {
                        case 'asc':
                            const tbody = $('.table tbody');
                            const rows = tbody.find('tr').toArray();
                            rows.sort((a, b) => {
                                const dateA = new Date($(a).find('td:eq(4)').text());
                                const dateB = new Date($(b).find('td:eq(4)').text());
                                return dateA - dateB;
                            });
                            tbody.append(rows);
                            return;
                            
                        case 'desc':
                            const tbody2 = $('.table tbody');
                            const rows2 = tbody2.find('tr').toArray();
                            rows2.sort((a, b) => {
                                const dateA = new Date($(a).find('td:eq(4)').text());
                                const dateB = new Date($(b).find('td:eq(4)').text());
                                return dateB - dateA;
                            });
                            tbody2.append(rows2);
                            return;
                            
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
                    }

                    row.toggle(searchMatch && statusMatch && dateMatch);
                });
            }

            // Delete Status
            $('.delete-status').click(function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                
                if (confirm('Are you sure you want to delete this status?')) {
                    $.ajax({
                        url: 'equipment_status.php',
                        method: 'POST',
                        data: {
                            action: 'delete',
                            status_id: id
                        },
                        success: function(response) {
                            try {
                                var result = JSON.parse(response);
                                if (result.status === 'success') {
                                    location.reload();
                                } else {
                                    alert(result.message);
                                }
                            } catch (e) {
                                console.error('Parse error:', e);
                                location.reload();
                            }
                        }
                    });
                }
            });
        });
    </script>

    <!-- Add this before closing body tag -->
    <script>
        // Handle logout
        document.querySelector('.logout-btn').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.replace('/src/view/php/general/logout.php');
        });

        // Prevent back button after logout
        window.addEventListener('load', function() {
            if (performance.navigation.type === 2) { // Back/Forward navigation
                location.reload(true);
            }
        });
    </script>
</body>
</html>
