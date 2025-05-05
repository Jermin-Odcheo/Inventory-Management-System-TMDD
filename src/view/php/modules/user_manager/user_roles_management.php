<?php
// user_roles_management.php
session_start();
// Include configuration (assumes config.php defines a PDO instance in $pdo)
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ../../../../../public/index.php');
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

// 3) Button flags
$canCreate = $rbac->hasPrivilege('User Management', 'Create');
$canModify = $rbac->hasPrivilege('User Management', 'Modify');
$canDelete = $rbac->hasPrivilege('User Management', 'Remove');
$canTrack  = $rbac->hasPrivilege('User Management', 'Track');

// Query active users
$stmt = $pdo->query("SELECT id, username, email, first_name, last_name, date_created, status FROM users WHERE is_disabled = 0");
$usersData = $stmt->fetchAll();

// Query active roles
$stmt = $pdo->query("SELECT id, role_name FROM roles WHERE is_disabled = 0");
$rolesData = $stmt->fetchAll();

// Query active departments
$stmt = $pdo->query("SELECT id, department_name, abbreviation FROM departments WHERE is_disabled = 0");
$departmentsData = $stmt->fetchAll();

// Query user_roles assignments
$stmt = $pdo->query("SELECT user_id, role_id FROM user_roles");
$userRoles = $stmt->fetchAll();

// Query user_departments assignments
$stmt = $pdo->query("SELECT user_id, department_id FROM user_departments");
$userDepartmentsRaw = $stmt->fetchAll();

// Build a map: user_id => array of department_ids
$userDepartmentsMap = [];
foreach ($userDepartmentsRaw as $ud) {
    $userId = (int)$ud['user_id'];
    $deptId = (int)$ud['department_id'];
    if (!isset($userDepartmentsMap[$userId])) {
        $userDepartmentsMap[$userId] = [];
    }
    $userDepartmentsMap[$userId][] = $deptId;
}

// Build userRoleDepartments array: for each user_role assignment, attach the user's department IDs.
$userRoleDepartments = [];
foreach ($userRoles as $assignment) {
    $userId = (int)$assignment['user_id'];
    $roleId = (int)$assignment['role_id'];
    $departments = isset($userDepartmentsMap[$userId]) ? $userDepartmentsMap[$userId] : [];
    $userRoleDepartments[] = [
        'userId' => $userId,
        'roleId' => $roleId,
        'departmentIds' => $departments
    ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- BASE_URL is assumed to be defined in your config -->
    <link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>src/view/styles/css/user_roles_management.css?ref=v1">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    <style>
        .modal.fade .modal-dialog {
            transition: transform .3s ease-out;
        }
    </style>
    <title>User Roles Management</title>
</head>
<body>
<div class="main-content container-fluid">
    <header>
        <h1>USER ROLES MANAGER</h1>
    </header>
    <div class="filters-container">
        <div class="search-filter">
            <label for="search-filters">search for role</label>
            <input type="text" id="search-filters">
        </div>
        <div class="search-container">
            <label for="search-filters">search for users</label>
            <input type="text" id="search-users" placeholder="search user">
        </div>
        <div class="filter-container">
            <label for="filter-dropdown">filter</label>
            <select id="filter-dropdown">
                <option value="">All</option>
                <?php foreach ($departmentsData as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="action-buttons">
            <?php if ($canCreate): ?>
            <button id="create-btn">Create user to role</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Table body will be built via JavaScript -->
    <div class="table-responsive" id="table">
        <table class="table table-striped table-hover" id="urTable">
            <thead>
            <tr>
                <!-- Added checkbox column header with "select all" -->
                <th><?php if ($canDelete): ?><input type="checkbox" id="select-all"><?php endif; ?></th>
                <th>User</th>
                <th>Role</th>
                <th>Departments</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <!-- Table rows will be dynamically populated via JavaScript -->
            </tbody>
        </table>
    </div>
    <!-- Bulk Delete Button (initially hidden) -->
    <?php if ($canDelete): ?>
    <div class="mb-3">
        <button type="button" id="delete-selected" class="btn btn-danger" style="display: none;" disabled>
            Remove Selected User Roles
        </button>
    </div>
    <?php endif; ?>
    <!-- Pagination Controls -->
    <div class="container-fluid">
        <div class="row align-items-center g-3">
            <div class="col-12 col-sm-auto">
                <div class="text-muted">
                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows">100</span> entries
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


<!-- Add User to Roles Modal -->
<div id="add-user-roles-modal" class="modal">
    <div class="modal-content">
        <h2>add user to roles modal</h2>
        <div class="modal-body">
            <div class="form-group">
                <label for="search-role-dropdown">search role/s</label>
                <select id="search-role-dropdown">
                    <option value="">Select roles</option>
                    <?php foreach ($rolesData as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>current role selection</label>
                <div id="selected-roles-container"></div>
            </div>
            <div class="form-group">
                <label for="search-users-dropdown">search user/s</label>
                <select id="search-users-dropdown">
                    <option value="">Select users</option>
                    <?php foreach ($usersData as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>current user selection</label>
                <div id="selected-users-container"></div>
            </div>
            <div class="form-group">
                <label>list of current users</label>
                <table id="current-users-table">
                    <tbody>
                    <!-- Optionally pre-populate if needed -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button id="close-user-roles-modal">Cancel</button>
            <button id="save-user-roles">Save</button>
        </div>
    </div>
</div>

<!-- Add Department to Role Modal -->
<div id="add-department-role-modal" class="modal">
    <div class="modal-content">
        <h2>Add department to role modal</h2>
        <div class="modal-body">
            <h3>ROLE TITLE</h3>
            <div class="form-group">
                <label>Add department to role</label>
                <select id="department-dropdown">
                    <option value="">Select department</option>
                    <?php foreach ($departmentsData as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>ADDED DEPARTMENTS</label>
                <div id="added-departments-container"></div>
            </div>
            <div class="form-group">
                <label>List of Departments</label>
                <table id="departments-table">
                    <tbody>
                    <?php foreach ($departmentsData as $dept): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                            <td>
                                <button class="delete-btn" data-dept-id="<?php echo $dept['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button id="close-department-role-modal">Cancel</button>
            <button id="save-department-role">Save</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="delete-confirm-modal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this role assignment?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-delete-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Pass PHP data to JavaScript -->
<script>
    let usersData = <?php echo json_encode($usersData); ?>;
    let rolesData = <?php echo json_encode($rolesData); ?>;
    let departmentsData = <?php echo json_encode($departmentsData); ?>;
    let userRoleDepartments = <?php echo json_encode($userRoleDepartments); ?>;
    
    // Pass RBAC privileges to JavaScript
    const userPrivileges = {
        canCreate: <?php echo json_encode($canCreate); ?>,
        canModify: <?php echo json_encode($canModify); ?>,
        canDelete: <?php echo json_encode($canDelete); ?>,
        canTrack: <?php echo json_encode($canTrack); ?>
    };
</script>

<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/user_roles_management.js" defer></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
</body>
</html>
