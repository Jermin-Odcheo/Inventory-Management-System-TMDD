<?php
// Start output buffering to prevent "headers already sent" errors.
ob_start();
session_start();
require_once('../../../../../../config/ims-tmdd.php'); // Adjust the path as needed
include '../../../general/header.php';
include '../../../general/footer.php';
include '../../../general/sidebar.php';

// Determine if this is an AJAX request.
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Optionally check for admin privileges
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Init RBAC & enforce "View" privilege
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

// Button flags for RBAC controls
$canCreate = $rbac->hasPrivilege('Roles and Privileges', 'Create');
$canModify = $rbac->hasPrivilege('Roles and Privileges', 'Modify');
$canDelete = $rbac->hasPrivilege('Roles and Privileges', 'Remove');

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set the IP address
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

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
// DELETE DEPARTMENT
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    // Check RBAC permission for delete
    if (!$canDelete) {
        $_SESSION['errors'] = ["You don't have permission to delete departments."];
        header("Location: department_management.php");
        exit;
    }

    $id = $_GET['id'];
    try {
        $pdo->beginTransaction();

        // Get department details before deletion
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$id]);
        $departmentData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($departmentData) {
            $oldValues = json_encode([
                'id' => $departmentData['id'],
                'abbreviation' => $departmentData['abbreviation'],
                'department_name' => $departmentData['department_name']
            ]);

            $newValues = json_encode([
                'id' => $departmentData['id'],
                'abbreviation' => $departmentData['abbreviation'],
                'department_name' => $departmentData['department_name']
            ]);

            // Soft delete - update is_disabled to 1 instead of DELETE
            $stmt = $pdo->prepare("UPDATE departments SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$id]);

            // Insert audit log
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_log (
                    UserID, EntityID, Module, Action, Details, OldVal, NewVal, Status, Date_Time
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $_SESSION['user_id'],
                $id,
                'Department Management',
                'Remove',
                "Department '{$departmentData['department_name']}' has been moved to archive",
                $oldValues,
                $newValues,
                'Successful'
            ]);

            $pdo->commit();
            $_SESSION['success'] = "Department moved to archive successfully.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['errors'] = ["Error archiving Department: " . $e->getMessage()];
    }
    header("Location: department_management.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Create / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
    }

    $DepartmentName    = trim((string)($_POST['department_name']  ?? ''));
    $DepartmentAcronym = trim((string)($_POST['abbreviation']      ?? ''));

    $response = array('status' => '', 'message' => '');

    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        // RBAC: check “Create” privilege
        if (!$canCreate) {
            $response = [
                'status'  => 'error',
                'message' => 'Permission denied: You do not have rights to create departments.'
            ];
            echo json_encode($response);
            exit;
        }

        // Check only for required fields (Acronym and Name)
        if (empty($DepartmentAcronym) || empty($DepartmentName)) {
            $response['status'] = 'error';
            $response['message'] = 'Please fill in all required fields.';
            echo json_encode($response);
            exit;
        }

        try {
            // Check if an active department with the same name & acronym exists
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) 
                  FROM departments 
                 WHERE department_name = ?
                   AND abbreviation    = ?
                   AND is_disabled     = 0
            ");
            $checkStmt->execute([$DepartmentName, $DepartmentAcronym]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception(
                    "Department “{$DepartmentName} ({$DepartmentAcronym})” already exists."
                );
            }

            $pdo->beginTransaction();

            $insertDept = $pdo->prepare("
                INSERT INTO departments 
                    (abbreviation, department_name, is_disabled) 
                VALUES (?, ?, 0)
            ");
            $insertDept->execute([$DepartmentAcronym, $DepartmentName]);
            $newDepartmentId = (int)$pdo->lastInsertId();

            $newValues = json_encode([
                'id'              => $newDepartmentId,
                'abbreviation'    => $DepartmentAcronym,
                'department_name' => $DepartmentName
            ]);

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

            if ($oldDepartment['abbreviation'] === $DepartmentAcronym && 
                $oldDepartment['department_name'] === $DepartmentName) {
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

// ------------------------
// RETRIEVE ALL DEPARTMENTS
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM departments WHERE is_disabled = 0 ORDER BY id");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving departments: " . $e->getMessage();
}

// ------------------------
// LIVE SEARCH DEPARTMENTS
// ------------------------
if (isset($_GET["q"])) {
    // This prevents any HTML output for search queries
    ob_clean();

    $q = isset($_GET["q"]) ? $conn->real_escape_string($_GET["q"]) : '';

    // Only first 3 letters of the query
    $q = substr($q, 0, 3);

    if (strlen($q) > 0) {
        $sql = "SELECT id, department_name, abbreviation FROM departments 
                WHERE id LIKE '%$q%' 
                OR department_name LIKE '%$q%' 
                OR abbreviation LIKE '%$q%' 
                LIMIT 10";

        $result = $conn->query($sql);

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
        echo "<div class='result-item'>Enter at least 1 letter...</div>";
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
    <!-- Include jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Disable default pagination for this page -->
    <script>
        // Set flag to prevent the default pagination from initializing
        window.paginationInitialized = true;
        
        // Override the default pagination functions to prevent conflicts
        window.initPagination = function() { 
            console.log('Default pagination disabled for department management page');
            return {
                update: function() {},
                getConfig: function() { return {}; },
                setConfig: function() {}
            };
        };
        
        // Override updatePagination to prevent conflicts
        window.updatePagination = function() {
            console.log('Using department-specific pagination instead of default');
        };
    </script>
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


                                <div class="table-responsive" id="table">
                                    <div class="d-flex justify-content-start mb-3 gap-2 align-items-center">
                                        <?php if ($canCreate): ?>
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#addDepartmentModal">
                                                <i class="bi bi-plus-circle"></i> Create Department
                                            </button>
                                        <?php endif; ?>
                                        <!-- Sorting Filter UI -->
                                        <div class="d-flex align-items-center gap-2" id="sortFilter">
                                            <label class="mb-0">Sort by:</label>
                                            <div class="dropdown" data-bs-popper="static">
                                                <button class="btn btn-dark btn-sm dropdown-toggle" type="button" id="sortNameBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                    Department Name (A–Z) ▼
                                                </button>
                                                <ul class="dropdown-menu sort-dropdown-menu" aria-labelledby="sortNameBtn">
                                                    <li><a class="dropdown-item sort-dropdown" href="#" data-sort-col="name" data-sort-order="asc">Department Name (A–Z)</a></li>
                                                    <li><a class="dropdown-item sort-dropdown" href="#" data-sort-col="name" data-sort-order="desc">Department Name (Z–A)</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="input-group w-auto" id="livesearch">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" placeholder="Search..." id="eqSearch">
                                        </div>
                                    </div>
                                    <table id="departmentTable" class="table table-striped table-hover align-middle">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>#</th>
                                                <th>Department Acronym</th>
                                                <th>Department Name</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($departments as $department): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($department['id']); ?></td>
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
                                                                <a href="department_management.php?action=delete&id=<?php echo htmlspecialchars($department['id']); ?>"
                                                                    class="delete-btn"
                                                                    data-dept-name="<?php echo htmlspecialchars($department['department_name']); ?>">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <!-- Pagination Controls -->
                                    <div class="container-fluid">
                                        <div class="row align-items-center g-3">
                                            <div class="col-12 col-sm-auto">
                                                <div class="text-muted">
                                                    <?php $totalLogs = count($department); ?>
                                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-auto ms-sm-auto">
                                                <div class="d-flex align-items-center gap-2">
                                                    <button id="prevPage"
                                                        class="btn btn-outline-primary d-flex align-items-center gap-1">
                                                        <i class="bi bi-chevron-left"></i> Previous
                                                    </button>
                                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                                        <option value="10" selected>10</option>
                                                        <option value="20">20</option>
                                                        <option value="30">30</option>
                                                        <option value="50">50</option>
                                                    </select>
                                                    <button id="nextPage"
                                                        class="btn btn-outline-primary d-flex align-items-center gap-1">
                                                        Next <i class="bi bi-chevron-right"></i>
                                                    </button>
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

    <!-- Load department-specific pagination script -->
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/department_pagination.js" defer></script>
    <script>
        console.log('Department management page loaded. Pagination script should initialize on DOMContentLoaded.');
        
        // Prevent the default pagination from initializing for this page
        window.paginationInitialized = true;
    </script>

    <!-- Add Department Modal -->
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

    <!-- Edit Department Modal -->
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
                            <label for="edit_department_id" class="form-label">
                                <i class="bi bi-tag"></i> Department ID <span class="text-danger">*</span>
                            </label>
                            <input type="number" min="1" class="form-control" id="edit_department_id" name="DepartmentID"
                                readonly>
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

    <!-- Delete Confirmation Modal -->
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
                    <a href="#" id="confirmDeleteLink" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Live search filtering is now handled by department_pagination.js
            // No need for this duplicate event listener

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

                $.ajax({
                    url: 'department_management.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Close modal first to prevent backdrop issues
                            $('#addDepartmentModal').modal('hide');
                            
                            // Use a timeout to ensure modal backdrop is removed
                            setTimeout(function() {
                                // Only update the table, not the whole page
                                $('#departmentTable').load(location.href + ' #departmentTable > *', function() {
                                    // Initialize department pagination after table is reloaded
                                    if (typeof window.initDepartmentPagination === 'function') {
                                        window.initDepartmentPagination();
                                    }
                                    showToast(response.message, 'success', 5000);
                                });
                                
                                // Clear modal inputs
                                $('#addDepartmentForm')[0].reset();
                            }, 300);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
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
                
                // Store original values for comparison
                const originalAcronym = $('#edit_department_acronym').data('original-value');
                const originalName = $('#edit_department_name').data('original-value');
                
                // Get current values
                const currentAcronym = $('#edit_department_acronym').val().trim();
                const currentName = $('#edit_department_name').val().trim();
                console.log(currentAcronym + " current name: " + currentName);
                // Check if anything changed
                if (originalAcronym === currentAcronym && originalName === currentName) {
                    showToast('No changes were made to the department.', 'info', 5000);
                    $('#editDepartmentModal').modal('hide');
                    return;
                }

                $.ajax({
                    url: 'department_management.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        // Close modal first to prevent backdrop issues
                        $('#editDepartmentModal').modal('hide');
                        
                        if (response.status === 'success') {
                            // Use a timeout to ensure modal backdrop is removed
                            setTimeout(function() {
                                // Only update the table, not the whole page
                                $('#departmentTable').load(location.href + ' #departmentTable > *', function() {
                                    // Initialize department pagination after table is reloaded
                                    if (typeof window.initDepartmentPagination === 'function') {
                                        window.initDepartmentPagination();
                                    }
                                    showToast(response.message, 'success', 5000);
                                });
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

            // Open Edit Department modal and populate its fields
            $(document).on('click', '.edit-btn', function() {
                var id = $(this).data('id');
                var deptAcronym = $(this).data('department-acronym');
                var deptName = $(this).data('department-name');
                $('#edit_department_hidden_id').val(id);
                $('#edit_department_id').val(id);
                $('#edit_department_acronym').val(deptAcronym).data('original-value', deptAcronym);
                $('#edit_department_name').val(deptName).data('original-value', deptName);
                $('#editDepartmentModal').modal('show');
            });

            // Open Delete Department modal and populate its fields
            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                var deleteUrl = $(this).attr('href');
                var deptName = $(this).data('dept-name');
                $('#deptNameToDelete').text(deptName);
                $('#confirmDeleteLink').attr('href', deleteUrl);
                $('#deleteDepartmentModal').modal('show');
            });

            // Add event listener for delete confirmation
            $('#confirmDeleteLink').on('click', function() {
                // Close the modal when delete is confirmed
                $('#deleteDepartmentModal').modal('hide');
            });

            // Reset form when add modal is closed
            $('#addDepartmentModal').on('hidden.bs.modal', function() {
                $(this).find('form')[0].reset();
            });
            
            // Ensure modal backdrop is removed when modal is hidden
            $('.modal').on('hidden.bs.modal', function() {
                if ($('.modal.show').length > 0) {
                    $('body').addClass('modal-open');
                } else {
                    $('.modal-backdrop').remove();
                }
            });

            // Sorting functionality for Department Name (dropdown version)
            $(document).on('click', '.sort-dropdown', function(e) {
                e.preventDefault();
                var sortCol = $(this).data('sort-col');
                var sortOrder = $(this).data('sort-order');
                var rows = $('#departmentTable tbody tr').get();
                var colIndex = 2; // 2: Department Name
                rows.sort(function(a, b) {
                    var A = $(a).children('td').eq(colIndex).text().toUpperCase();
                    var B = $(b).children('td').eq(colIndex).text().toUpperCase();
                    if (A < B) return sortOrder === 'asc' ? -1 : 1;
                    if (A > B) return sortOrder === 'asc' ? 1 : -1;
                    return 0;
                });
                $.each(rows, function(index, row) {
                    $('#departmentTable tbody').append(row);
                });
                // Update button label
                var label = sortOrder === 'asc' ? 'Department Name (A–Z) ▼' : 'Department Name (Z–A) ▼';
                $('#sortNameBtn').text(label);
                // Re-initialize pagination after sorting
                if (typeof window.initDepartmentPagination === 'function') {
                    window.initDepartmentPagination();
                }
            });

            // Initialize department pagination
            if (typeof window.initDepartmentPagination === 'function') {
                // Ensure we're using the department-specific pagination
                window.paginationInitialized = true;
                
                // Initialize with a slight delay to ensure DOM is fully loaded
                setTimeout(function() {
                    window.initDepartmentPagination();
                    console.log('Department pagination initialized from document ready');
                }, 100);
            } else {
                console.error('Department pagination function not found');
            }
        });
    </script>


</body>

</html>