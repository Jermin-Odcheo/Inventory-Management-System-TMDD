<?php
/**
 * Department Management Module
 *
 * This file provides comprehensive functionality for managing departments within the system. It handles the creation, modification, and management of departments, including their relationships with users and equipment. The module ensures proper data validation, user authorization, and maintains data consistency across the Inventory Management System.
 *
 * @package    InventoryManagementSystem
 * @subpackage Management
 * @author     TMDD Interns 25'
 */
// Start output buffering to prevent "headers already sent" errors.
ob_start();
session_start();
require_once('../../../../../../config/ims-tmdd.php'); // Adjust the path as needed
include '../../../general/header.php';
include '../../../general/footer.php';
include '../../../general/sidebar.php';

// Determine if this is an AJAX request.
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Handle search request
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    ob_clean(); // Clear any output buffers
    header('Content-Type: application/json');
    
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    try {
        if (!empty($searchTerm)) {
            $searchStmt = $pdo->prepare("
                SELECT id, abbreviation, department_name 
                FROM departments 
                WHERE (department_name LIKE ? OR abbreviation LIKE ?)
                AND is_disabled = 0
                ORDER BY id DESC
            ");
            $searchTerm = "%{$searchTerm}%";
            $searchStmt->execute([$searchTerm, $searchTerm]);
        } else {
            $searchStmt = $pdo->query("
                SELECT id, abbreviation, department_name 
                FROM departments 
                WHERE is_disabled = 0 
                ORDER BY id DESC
            ");
        }
        
        $results = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Check if the user is logged in.
 *
 * @return void
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

/**
 * Initialize RBAC and enforce "View" privilege.
 *
 * @return void
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Administration', 'View');

/**
 * Button flags for RBAC controls.
 *
 * @return void
 */
$canCreate = $rbac->hasPrivilege('Administration', 'Create');
$canModify = $rbac->hasPrivilege('Administration', 'Modify');
$canDelete = $rbac->hasPrivilege('Administration', 'Remove');

/**
 * Set the audit log session variables for MySQL triggers.
 *
 * @return void
 */
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

/**
 * Set the IP address.
 *
 * @return void
 */
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

/**
 * Initialize messages.
 *
 * @return void
 */
$errors = [];
$success = "";

/**
 * Retrieve any session messages from previous requests.
 *
 * @return void
 */
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

/**
 * Process form submissions (Create / Update).
 *
 * @return void
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
    }
    /**
     * @var string $DepartmentName The name of the department.
     * @var string $DepartmentAcronym The acronym of the department.
     */
    $DepartmentName    = trim((string)($_POST['department_name']  ?? ''));
    $DepartmentAcronym = trim((string)($_POST['abbreviation']      ?? ''));

    /**
     * @var array $response The response array.
     */
    $response = array('status' => '', 'message' => '');

    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        /**
         * RBAC: check "Create" privilege.
         *
         * @return void
         */
        if (!$canCreate) {
            $response = [
                'status'  => 'error',
                'message' => 'Permission denied: You do not have rights to create departments.'
            ];
            echo json_encode($response);
            exit;
        }

        /**
         * Check only for required fields (Acronym and Name).
         *
         * @return void
         */
        if (empty($DepartmentAcronym) || empty($DepartmentName)) {
            $response['status'] = 'error';
            $response['message'] = 'Please fill in all required fields.';
            echo json_encode($response);
            exit;
        }

        try {
            /**
             * Check if an active department with the same name or acronym exists.
             *
             * @return void
             */
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) 
                  FROM departments 
                 WHERE (department_name = ? OR abbreviation = ?)
                   AND is_disabled = 0
            ");
            $checkStmt->execute([$DepartmentName, $DepartmentAcronym]);
            if ($checkStmt->fetchColumn() > 0) {
                /**
                 * Check which one caused the conflict.
                 * @var PDOStatement $nameCheck The prepared statement to check if the department name exists.
                 * @var PDOStatement $acronymCheck The prepared statement to check if the department acronym exists.
                 * @return void
                 */
                $nameCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_name = ? AND is_disabled = 0");
                $nameCheck->execute([$DepartmentName]);
                $acronymCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE abbreviation = ? AND is_disabled = 0");
                $acronymCheck->execute([$DepartmentAcronym]);
                
                if ($nameCheck->fetchColumn() > 0) {
                    throw new Exception('Department name "' . $DepartmentName . '" already exists.');
                } else {
                    throw new Exception('Department acronym "' . $DepartmentAcronym . '" already exists.');
                }
            }

            $pdo->beginTransaction();

            /**
             * Insert the new department.
             *
             * @var PDOStatement $insertDept The prepared statement to insert the new department.
             */
            $insertDept = $pdo->prepare("
                INSERT INTO departments 
                    (abbreviation, department_name, is_disabled) 
                VALUES (?, ?, 0)
            ");
            $insertDept->execute([$DepartmentAcronym, $DepartmentName]);
            $newDepartmentId = (int)$pdo->lastInsertId();

            /**
             * @var string $newValues The new values of the department.
             */
            $newValues = json_encode([
                'id'              => $newDepartmentId,
                'abbreviation'    => $DepartmentAcronym,
                'department_name' => $DepartmentName
            ]);

            /**
             * @var PDOStatement $auditStmt The prepared statement to insert the audit log.
             */
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, 
                    Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $_SESSION['user_id'],
                $newDepartmentId,
                'Department Management',
                'Create',
                "Department '{$DepartmentName}' has been created",
                null,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();

            $response = [
                'status'  => 'success',
                'message' => 'Department has been created successfully.'
            ];
            $_SESSION['success'] = $response['message'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $response = [
                'status'  => 'error',
                'message' => $e->getMessage()
            ];
            $_SESSION['errors'] = [$e->getMessage()];
        }

        echo json_encode($response);
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        if (!$canModify) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Permission denied: You do not have rights to modify departments.'
            ]);
            exit;
        }

        /**
         * @var string $id The ID of the department.
         * @var string $DepartmentAcronym The acronym of the department.
         * @var string $DepartmentName The name of the department.
         */
        $id = $_POST['id'];
        $DepartmentAcronym = trim($_POST['DepartmentAcronym']);
        $DepartmentName = trim($_POST['DepartmentName']);

        try {
            $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            $oldDepartment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldDepartment) {
                throw new Exception('Department not found.');
            }

            if (
                $oldDepartment['abbreviation'] === $DepartmentAcronym &&
                $oldDepartment['department_name'] === $DepartmentName
            ) {
                echo json_encode([
                    'status' => 'info',
                    'message' => 'No changes were made to the department.'
                ]);
                exit;
            }

            if ($oldDepartment['department_name'] !== $DepartmentName) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_name = ? AND id != ? AND is_disabled = 0");
                $checkStmt->execute([$DepartmentName, $id]);
                if ($checkStmt->fetchColumn() > 0) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Department name '{$DepartmentName}' already exists."
                    ]);
                    exit;
                }
            }

            if ($oldDepartment['abbreviation'] !== $DepartmentAcronym) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE abbreviation = ? AND id != ? AND is_disabled = 0");
                $checkStmt->execute([$DepartmentAcronym, $id]);
                if ($checkStmt->fetchColumn() > 0) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => "Department acronym '{$DepartmentAcronym}' already exists."
                    ]);
                    exit;
                }
            }

            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("
                UPDATE departments SET 
                    abbreviation = ?, 
                    department_name = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $DepartmentAcronym,
                $DepartmentName,
                $id
            ]);

            $oldValues = json_encode([
                'id' => $oldDepartment['id'],
                'abbreviation' => $oldDepartment['abbreviation'],
                'department_name' => $oldDepartment['department_name']
            ]);
            $newValues = json_encode([
                'id' => $id,
                'abbreviation' => $DepartmentAcronym,
                'department_name' => $DepartmentName
            ]);

            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Department Management',
                'Modified',
                "Department '{$DepartmentName}' details modified",
                $oldValues,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'Department has been updated successfully.'
            ]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'status' => 'error',
                'message' => 'Error updating Department: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}

/**
 * Retrieve all departments.
 *
 * @return void
 */
try {
    $stmt = $pdo->query("SELECT * FROM departments WHERE is_disabled = 0 ORDER BY id DESC");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving departments: " . $e->getMessage();
}

/**
 * Retrieve all departments.
 *
 * @return void
 */
if (isset($_GET["q"])) {
    /**
     * This prevents any HTML output for search queries.
     *
     * @return void
     */
    ob_clean();

    $q = isset($_GET["q"]) ? $conn->real_escape_string($_GET["q"]) : '';

    if (strlen($q) > 0) {
        /**
         * @var string $sql The SQL query to retrieve the departments.
         */
        $sql = "SELECT id, department_name, abbreviation 
                FROM departments 
                WHERE (department_name LIKE '%$q%' 
                OR abbreviation LIKE '%$q%')
                AND is_disabled = 0
                ORDER BY id DESC";
        /**
         * @var mysqli_result $result The result of the SQL query.
         */
        $result = $conn->query($sql);

        /**
         * @var int $num_rows The number of rows in the result.
         */
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<div class='result-item'>"
                    . "<strong>ID:</strong> " . htmlspecialchars($row['id']) . " - "
                    . "<strong>Department Name:</strong> " . htmlspecialchars($row['department_name']) . " - "
                    . "<strong>Abbreviation:</strong> " . htmlspecialchars($row['abbreviation'])
                    . "</div>";
            }
        } else {
            echo "<div class='result-item'>No results found</div>";
        }
        exit;
    } else {
        echo "<div class='result-item'>Enter search terms...</div>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8">
    <title>Department Management</title>
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


        .main-content h2.mb-4 {
            margin-top: 4vh;
        }

        /* Button Styles */
        .edit-btn,
        .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: 9999px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
        }

        .edit-btn {
            color: #4f46e5;
        }

        .delete-btn {
            color: #ef4444;
        }

        /* Edit button hover state */
        .edit-btn:hover {
            background-color: #eef2ff;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Delete button hover state - maintain elevation but override color */
        .delete-btn:hover {
            background-color: #fee2e2;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            color: #ef4444 !important;
            /* Force the color to remain red */
        }

        /* Explicitly override any hover effects on the icon itself */
        .delete-btn:hover i.bi-trash {
            color: #ef4444 !important;
            /* Keep the trash icon red on hover */
        }

        .edit-btn:active,
        .delete-btn:active {
            transform: translateY(0);
        }

        /* Ensure the sort filter button stays black and text visible on hover */
        #sortFilter .btn.dropdown-toggle,
        #sortFilter .btn.dropdown-toggle:focus,
        #sortFilter .btn.dropdown-toggle:hover,
        #sortFilter .btn.dropdown-toggle:active {
            background-color: #fff !important;
            color: #212529 !important;
            border-color: #212529 !important;
            box-shadow: none !important;
        }

        #sortFilter .dropdown-menu.sort-dropdown-menu {
            background: #fff !important;
            color: #212529 !important;
            min-width: 220px;
            z-index: 1055;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .15);
        }

        #sortFilter .dropdown-menu.sort-dropdown-menu .dropdown-item,
        #sortFilter .dropdown-menu.sort-dropdown-menu .dropdown-item:active,
        #sortFilter .dropdown-menu.sort-dropdown-menu .dropdown-item.active,
        #sortFilter .dropdown-menu.sort-dropdown-menu .dropdown-item:focus,
        #sortFilter .dropdown-menu.sort-dropdown-menu .dropdown-item:hover {
            color: #212529 !important;
            background: #fff !important;
        }

        /* Prevent clipping by making .table-responsive position: static */
        .table-responsive {
            position: static !important;
        }

        /* Sortable header styles */
        .sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
        }

        .sortable:hover {
            background-color: #2c3034;
        }

        .sortable i {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.8em;
            opacity: 0.5;
        }

        .sortable:hover i {
            opacity: 1;
        }

        .sortable.asc i:before {
            content: "\f0de";
            opacity: 1;
        }

        .sortable.desc i:before {
            content: "\f0dd";
            opacity: 1;
        }
    </style>

</head>

<body>


    <div class="main-content">
        <div class="container-fluid">
            <div class="row">
                <main class="col-md-12 px-md-4 py-4">
                    <h2 class="mb-4">Department Management</h2>

                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                            <span><i class="bi bi-list-ul"></i> List of Departments</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($departments)): ?>

                                <div class="filter-container">
                                    <div class="d-flex justify-content-start mb-3 gap-2 align-items-center">
                                        <?php if ($canCreate): ?>
                                            <button type="button" class="btn btn-success btn-dark" data-bs-toggle="modal"
                                                data-bs-target="#addDepartmentModal">
                                                <i class="bi bi-plus-lg"></i> Create Department
                                            </button>
                                        <?php endif; ?>

                                        <div class="input-group w-auto" style="min-width:220px;">
                                            <input type="text" id="searchInput" class="form-control" placeholder="Search departments...">
                                            <button class="btn btn-outline-secondary" type="button" id="searchButton">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>

                                        <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                                            <i class="bi bi-x-circle"></i> Clear
                                        </button>
                                    </div>
                                </div>


                                <!-- Table -->
                                <div class="table-responsive" id="table">
                                    <table id="departmentTable" class="table table-striped table-hover align-middle">
                                        <thead class="table-dark">
                                            <tr>
                                                <th class="sortable" data-sort="acronym">Department Acronym <i class="fas fa-sort"></i></th>
                                                <th class="sortable" data-sort="name">Department Name <i class="fas fa-sort"></i></th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($departments as $department): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($department['abbreviation']); ?></td>
                                                    <td><?php echo htmlspecialchars($department['department_name']); ?></td>
                                                    <td class="text-center">
                                                        <div class="btn-group" role="group">
                                                            <?php if ($canModify): ?>
                                                                <button type="button"
                                                                    class="edit-btn"
                                                                    data-id="<?php echo htmlspecialchars($department['id']); ?>"
                                                                    data-department-acronym="<?php echo htmlspecialchars($department['abbreviation']); ?>"
                                                                    data-department-name="<?php echo htmlspecialchars($department['department_name']); ?>">
                                                                    <i class="bi bi-pencil-square"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($canDelete): ?>
                                                                <button type="button"
                                                                    class="delete-btn"
                                                                    data-id="<?php echo htmlspecialchars($department['id']); ?>"
                                                                    data-dept-name="<?php echo htmlspecialchars($department['department_name']); ?>">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="container-fluid">
                                        <div class="row align-items-center g-3">
                                            <div class="col-12 col-sm-auto">
                                                <div class="text-muted">
                                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPageDisplay">10</span> of <span id="totalRows"><?= count($departments) ?></span> entries
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-auto ms-sm-auto">
                                                <div class="d-flex align-items-center gap-2">
                                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                                        <option value="10" selected>10</option>
                                                        <option value="20">20</option>
                                                        <option value="30">30</option>
                                                        <option value="50">50</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <ul class="pagination justify-content-center" id="pagination"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="mb-0">No Departments found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Add new Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addDepartmentForm" method="post">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="DepartmentAcronym" class="form-label">
                                <i class="bi bi-building"></i> Department Acronym <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="DepartmentAcronym" name="abbreviation" required>
                        </div>
                        <div class="mb-3">
                            <label for="DepartmentName" class="form-label">
                                <i class="bi bi-layers"></i> Department Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="DepartmentName" name="department_name" required>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Add Department</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editDepartmentForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_department_hidden_id">
                        <div class="mb-3">
                        </div>
                        <div class="mb-3">
                            <label for="edit_department_acronym" class="form-label">
                                <i class="bi bi-building"></i> Department Acronym <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="edit_department_acronym" name="DepartmentAcronym"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_department_name" class="form-label">
                                <i class="bi bi-layers"></i> Department Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="edit_department_name" name="DepartmentName"
                                required>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Department</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteDepartmentModal" tabindex="-1" aria-labelledby="deleteDepartmentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDepartmentModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the department: <strong id="deptNameToDelete"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Global variables for pagination state
            let currentPage = 1;
            let rowsPerPage = 10;
            let allTableRows = []; // To store all rows for client-side pagination
            let deptIdToDelete = null;

            // Function to render table rows for the current page
            function renderTableRows() {
                const tableBody = $('#departmentTable tbody');
                tableBody.empty(); // Clear existing rows

                const startIndex = (currentPage - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                const rowsToDisplay = allTableRows.slice(startIndex, endIndex);

                rowsToDisplay.forEach(row => {
                    tableBody.append(row);
                });

                // Update pagination info text
                const totalRows = allTableRows.length;
                const displayedStart = totalRows === 0 ? 0 : startIndex + 1;
                const displayedEnd = Math.min(endIndex, totalRows);
                $('#currentPage').text(currentPage);
                $('#rowsPerPageDisplay').text(displayedEnd);
                $('#totalRows').text(totalRows);

                updatePaginationControls();
            }

            // Function to update pagination buttons and page numbers
            function updatePaginationControls() {
                const totalPages = Math.ceil(allTableRows.length / rowsPerPage);
                const paginationUl = $('#pagination');
                paginationUl.empty();

                // Add Previous button
                const prevLi = $('<li>').addClass('page-item');
                const prevButton = $('<button>').addClass('page-link').html('&laquo;');
                if (currentPage === 1) {
                    prevLi.addClass('disabled');
                }
                prevButton.on('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        renderTableRows();
                    }
                });
                prevLi.append(prevButton);
                paginationUl.append(prevLi);

                // Add page number buttons
                for (let i = 1; i <= totalPages; i++) {
                    const li = $('<li>').addClass('page-item');
                    const button = $('<button>').addClass('page-link').text(i);
                    if (i === currentPage) {
                        li.addClass('active');
                    }
                    button.on('click', function() {
                        currentPage = i;
                        renderTableRows();
                    });
                    li.append(button);
                    paginationUl.append(li);
                }

                // Add Next button
                const nextLi = $('<li>').addClass('page-item');
                const nextButton = $('<button>').addClass('page-link').html('&raquo;');
                if (currentPage === totalPages) {
                    nextLi.addClass('disabled');
                }
                nextButton.on('click', function() {
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderTableRows();
                    }
                });
                nextLi.append(nextButton);
                paginationUl.append(nextLi);
            }

            // Function to initialize/re-initialize pagination
            window.initDepartmentPagination = function(initialPage = 1, initialRowsPerPage = 10) {
                // Get all rows from the table (before any filtering/slicing)
                allTableRows = $('#departmentTable tbody tr').get();
                
                // Set initial state
                rowsPerPage = initialRowsPerPage;
                $('#rowsPerPageSelect').val(rowsPerPage); // Update dropdown

                // Calculate max possible page for the given rowsPerPage
                const totalPages = Math.ceil(allTableRows.length / rowsPerPage);
                currentPage = Math.min(initialPage, totalPages || 1); // Ensure current page is valid

                renderTableRows();
            };

            // Function to attach all event listeners for interactive elements within the table
            function attachTableEventListeners() {
                // Event listener for rows per page select (re-attach)
                $('#rowsPerPageSelect').off('change').on('change', function() {
                    rowsPerPage = parseInt($(this).val());
                    currentPage = 1; // Reset to first page when rows per page changes
                    renderTableRows();
                });

                // Open Edit Department modal and populate its fields (delegated event listener)
                $(document).off('click', '.edit-btn').on('click', '.edit-btn', function() {
                    var id = $(this).data('id');
                    var deptAcronym = $(this).data('department-acronym');
                    var deptName = $(this).data('department-name');
                    $('#edit_department_hidden_id').val(id);
                    $('#edit_department_acronym').val(deptAcronym).data('original-value', deptAcronym);
                    $('#edit_department_name').val(deptName).data('original-value', deptName);
                    $('#editDepartmentModal').modal('show');
                });

                // Open Delete Department modal and populate its fields (delegated event listener)
                // This handler sets the global deptIdToDelete
                $(document).off('click', '.delete-btn').on('click', '.delete-btn', function(e) {
                    e.preventDefault();
                    deptIdToDelete = $(this).data('id'); // Set the global variable
                    var deptName = $(this).data('dept-name');
                    $('#deptNameToDelete').text(deptName);
                    $('#deleteDepartmentModal').modal('show');
                });

                // Remove the old sort dropdown code and add header sorting
                $('.sortable').off('click').on('click', function() {
                    const sortCol = $(this).data('sort');
                    const currentOrder = $(this).hasClass('asc') ? 'desc' : 'asc';
                    
                    // Reset all headers
                    $('.sortable').removeClass('asc desc');
                    // Set current header state
                    $(this).addClass(currentOrder);

                    let rows = allTableRows;
                    const colIndex = sortCol === 'acronym' ? 0 : 1;

                    rows.sort(function(a, b) {
                        const A = $(a).children('td').eq(colIndex).text().toUpperCase();
                        const B = $(b).children('td').eq(colIndex).text().toUpperCase();
                        if (A < B) return currentOrder === 'asc' ? -1 : 1;
                        if (A > B) return currentOrder === 'asc' ? 1 : -1;
                        return 0;
                    });

                    allTableRows = rows;
                    currentPage = 1;
                    renderTableRows();
                    updatePaginationControls();
                });
            }

            // Check for success or error messages from PHP session
            <?php if (!empty($success)): ?>
                showToast("<?php echo addslashes($success); ?>", "success", 5000);
            <?php endif; ?>

            <?php if (!empty($errors) && is_array($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    showToast("<?php echo addslashes($error); ?>", "error", 5000);
                <?php endforeach; ?>
            <?php endif; ?>

            // AJAX: Add Department form submission
            $('#addDepartmentForm').on('submit', function(e) {
                e.preventDefault();

                const currentPageBeforeAction = currentPage; // Capture current page
                const currentRowsPerPageBeforeAction = rowsPerPage;

                $.ajax({
                    url: 'department_management.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#addDepartmentModal').modal('hide');

                            setTimeout(function() {
                                $('#table').load(location.href + ' #table > *', function() {
                                    // Now use the captured currentPage instead of 1
                                    window.initDepartmentPagination(currentPageBeforeAction, currentRowsPerPageBeforeAction);
                                    attachTableEventListeners(); // Re-attach listeners after table reload
                                    showToast(response.message, 'success', 5000);
                                });

                                $('#addDepartmentForm')[0].reset();
                            }, 300);
                        } else {
                            showToast(response.message, 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        let errorMsg = 'Error submitting form';
                        if (xhr.responseText) {
                            try {
                                const errorObj = JSON.parse(xhr.responseText);
                                errorMsg = errorObj.message || errorMsg;
                            } catch (e) {
                                errorMsg += ': ' + error;
                            }
                        }
                        showToast(errorMsg, 'error', 5000);
                    }
                });
            });

            // AJAX: Edit Department form submission
            $('#editDepartmentForm').on('submit', function(e) {
                e.preventDefault();

                const originalAcronym = $('#edit_department_acronym').data('original-value');
                const originalName = $('#edit_department_name').data('original-value');

                const currentAcronym = $('#edit_department_acronym').val().trim();
                const currentName = $('#edit_department_name').val().trim();

                if (originalAcronym === currentAcronym && originalName === currentName) {
                    showToast('No changes were made to the department.', 'info', 5000);
                    $('#editDepartmentModal').modal('hide');
                    return;
                }

                const currentPageBeforeAction = currentPage;
                const currentRowsPerPageBeforeAction = rowsPerPage;

                $.ajax({
                    url: 'department_management.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        $('#editDepartmentModal').modal('hide');

                        if (response.status === 'success') {
                            setTimeout(function() {
                                $('#table').load(location.href + ' #table > *', function() {
                                    window.initDepartmentPagination(currentPageBeforeAction, currentRowsPerPageBeforeAction);
                                    attachTableEventListeners(); // Re-attach listeners after table reload
                                    showToast(response.message, 'success', 5000);
                                });

                                // Corrected: Reset the edit form, not the add form
                                $('#editDepartmentForm')[0].reset();
                            }, 300);

                        } else if (response.status === 'info') {
                            showToast(response.message, 'info', 5000);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        let errorMsg = 'Error updating department';
                        if (xhr.responseText) {
                            try {
                                const errorObj = JSON.parse(xhr.responseText);
                                errorMsg = errorObj.message || errorMsg;
                            } catch (e) {
                                errorMsg += ': ' + error;
                            }
                        }
                        showToast(errorMsg, 'error', 5000);
                    }
                });
            });

            // AJAX: Handle delete confirmation
            $('#confirmDeleteBtn').on('click', function() {
                if (deptIdToDelete) {
                    const currentPageBeforeAction = currentPage;
                    const currentRowsPerPageBeforeAction = rowsPerPage;
                    const currentSortColumn = $('.sortable.asc, .sortable.desc').data('sort');
                    const currentSortOrder = $('.sortable.asc, .sortable.desc').hasClass('asc') ? 'asc' : 'desc';

                    $.ajax({
                        url: 'delete_department.php',
                        method: 'POST',
                        data: {
                            dept_id: deptIdToDelete
                        },
                        dataType: 'json',
                        success: function(response) {
                            $('#deleteDepartmentModal').modal('hide');
                            if (response.status === 'success') {
                                setTimeout(function() {
                                    $('#table').load(location.href + ' #table > *', function() {
                                        // Reinitialize pagination with previous state
                                        window.initDepartmentPagination(currentPageBeforeAction, currentRowsPerPageBeforeAction);
                                        
                                        // Reapply sorting if it was active
                                        if (currentSortColumn) {
                                            const sortHeader = $(`.sortable[data-sort="${currentSortColumn}"]`);
                                            sortHeader.addClass(currentSortOrder);
                                            const rows = allTableRows;
                                            const colIndex = currentSortColumn === 'acronym' ? 0 : 1;
                                            
                                            rows.sort(function(a, b) {
                                                const A = $(a).children('td').eq(colIndex).text().toUpperCase();
                                                const B = $(b).children('td').eq(colIndex).text().toUpperCase();
                                                if (A < B) return currentSortOrder === 'asc' ? -1 : 1;
                                                if (A > B) return currentSortOrder === 'asc' ? 1 : -1;
                                                return 0;
                                            });
                                            
                                            allTableRows = rows;
                                            renderTableRows();
                                        }
                                        
                                        attachTableEventListeners();
                                        showToast(response.message, 'success', 5000);
                                    });
                                }, 300);
                            } else {
                                showToast(response.message || 'An error occurred during deletion', 'error', 5000);
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#deleteDepartmentModal').modal('hide');
                            console.error('AJAX Error:', xhr.responseText);
                            let errorMsg = 'Error deleting department';
                            if (xhr.responseText) {
                                try {
                                    const errorObj = JSON.parse(xhr.responseText);
                                    errorMsg = errorObj.message || errorMsg;
                                } catch (e) {
                                    errorMsg += ': ' + error;
                                }
                            }
                            showToast(errorMsg, 'error', 5000);
                        }
                    });
                }
            });

            // Reset form when add modal is closed
            $('#addDepartmentModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
            });

            // Ensure modal backdrop is removed and scrolling is re-enabled when ALL modals are hidden
            $('.modal').on('hidden.bs.modal', function() {
                if ($('.modal.show').length === 0) {
                    $('body').removeClass('modal-open');
                    $('body').css('overflow', '');
                    $('.modal-backdrop').remove();
                }
            });

            // Initial setup
            window.initDepartmentPagination();
            attachTableEventListeners();

            // Set initial sort state to newest first (by ID)
            const rows = allTableRows;
            rows.sort(function(a, b) {
                const idA = parseInt($(a).find('.edit-btn').data('id'));
                const idB = parseInt($(b).find('.edit-btn').data('id'));
                return idB - idA; // Sort by ID descending (newest first)
            });
            allTableRows = rows;
            renderTableRows();

            // Add search functionality
            let searchTimeout;
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    performSearch();
                }, 300); // 300ms delay to prevent too many requests
            });

            function performSearch() {
                const searchTerm = $('#searchInput').val().trim();
                
                $.ajax({
                    url: 'search_departments.php',
                    method: 'GET',
                    data: {
                        search: searchTerm
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            showToast(response.error, 'error', 5000);
                            return;
                        }

                        // Clear existing table rows
                        const tableBody = $('#departmentTable tbody');
                        tableBody.empty();

                        // Add new rows from search results
                        response.forEach(function(dept) {
                            const row = $('<tr>');
                            row.append($('<td>').text(dept.abbreviation));
                            row.append($('<td>').text(dept.department_name));
                            
                            const actionsCell = $('<td>').addClass('text-center');
                            const btnGroup = $('<div>').addClass('btn-group').attr('role', 'group');
                            
                            <?php if ($canModify): ?>
                            const editBtn = $('<button>')
                                .addClass('edit-btn')
                                .attr('type', 'button')
                                .attr('data-id', dept.id)
                                .attr('data-department-acronym', dept.abbreviation)
                                .attr('data-department-name', dept.department_name)
                                .html('<i class="bi bi-pencil-square"></i>');
                            btnGroup.append(editBtn);
                            <?php endif; ?>
                            
                            <?php if ($canDelete): ?>
                            const deleteBtn = $('<button>')
                                .addClass('delete-btn')
                                .attr('type', 'button')
                                .attr('data-id', dept.id)
                                .attr('data-dept-name', dept.department_name)
                                .html('<i class="bi bi-trash"></i>');
                            btnGroup.append(deleteBtn);
                            <?php endif; ?>
                            
                            actionsCell.append(btnGroup);
                            row.append(actionsCell);
                            tableBody.append(row);
                        });

                        // Update pagination with all search results
                        allTableRows = $('#departmentTable tbody tr').get();
                        currentPage = 1;
                        renderTableRows();
                        updatePaginationControls();
                        
                        // Reattach event listeners
                        attachTableEventListeners();
                    },
                    error: function(xhr, status, error) {
                        console.error('Search Error:', error);
                        showToast('Error performing search: ' + error, 'error', 5000);
                    }
                });
            }

            // Update clear filters to also clear search and reload all data
            $('#clearFilters').on('click', function() {
                // Clear search input
                $('#searchInput').val('');
                
                // Reset sort headers
                $('.sortable').removeClass('asc desc');
                
                // Reset to initial sort (newest first)
                const initialSortHeader = $('.sortable[data-sort="acronym"]');
                initialSortHeader.addClass('desc');
                
                // Reload all data from server
                $.ajax({
                    url: 'search_departments.php',
                    method: 'GET',
                    data: {
                        search: ''
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.error) {
                            showToast(response.error, 'error', 5000);
                            return;
                        }

                        // Clear existing table rows
                        const tableBody = $('#departmentTable tbody');
                        tableBody.empty();

                        // Add all rows back
                        response.forEach(function(dept) {
                            const row = $('<tr>');
                            row.append($('<td>').text(dept.abbreviation));
                            row.append($('<td>').text(dept.department_name));
                            
                            const actionsCell = $('<td>').addClass('text-center');
                            const btnGroup = $('<div>').addClass('btn-group').attr('role', 'group');
                            
                            <?php if ($canModify): ?>
                            const editBtn = $('<button>')
                                .addClass('edit-btn')
                                .attr('type', 'button')
                                .attr('data-id', dept.id)
                                .attr('data-department-acronym', dept.abbreviation)
                                .attr('data-department-name', dept.department_name)
                                .html('<i class="bi bi-pencil-square"></i>');
                            btnGroup.append(editBtn);
                            <?php endif; ?>
                            
                            <?php if ($canDelete): ?>
                            const deleteBtn = $('<button>')
                                .addClass('delete-btn')
                                .attr('type', 'button')
                                .attr('data-id', dept.id)
                                .attr('data-dept-name', dept.department_name)
                                .html('<i class="bi bi-trash"></i>');
                            btnGroup.append(deleteBtn);
                            <?php endif; ?>
                            
                            actionsCell.append(btnGroup);
                            row.append(actionsCell);
                            tableBody.append(row);
                        });

                        // Update pagination with all data
                        allTableRows = $('#departmentTable tbody tr').get();
                        currentPage = 1;
                        renderTableRows();
                        updatePaginationControls();
                        
                        // Reattach event listeners
                        attachTableEventListeners();
                    },
                    error: function(xhr, status, error) {
                        console.error('Clear Error:', error);
                        showToast('Error clearing filters: ' + error, 'error', 5000);
                    }
                });
            });
        });
    </script>


</body>

</html>