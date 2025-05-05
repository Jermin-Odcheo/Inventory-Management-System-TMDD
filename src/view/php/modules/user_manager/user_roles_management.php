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
        
        /* Modern search box styling */
        .search-container {
            position: relative;
            width: 250px;
            margin-bottom: 10px;
        }
        
        .search-container input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background-color: white;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        .search-container input:focus {
            outline: none;
            border-color: #a5b4fc;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        
        .filters-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-filter, .filter-container, .search-container {
            display: flex;
            flex-direction: column;
            min-width: 220px;
        }
        
        .search-filter label, .filter-container label, .search-container label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 5px;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        /* Table column spacing */
        #urTable th, #urTable td {
            padding: 10px 12px;
        }
        
        /* Sort icon styling */
        .sort-icon {
            cursor: pointer;
            margin-left: 8px;
            font-size: 14px;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 2px 6px;
            color: #4b5563;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }
        
        .sort-icon:hover {
            background-color: #e5e7eb;
            color: #1f2937;
        }
        
        .sort-icon.active {
            background-color: #eef2ff;
            border-color: #a5b4fc;
            color: #4f46e5;
        }
        
        /* Clear filters button */
        #clear-filters {
            background-color: #f9fafb;
            color: #4b5563;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: none; /* Hidden by default */
            height: 38px;
        }
        
        #clear-filters:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        #clear-filters:active {
            background-color: #e5e7eb;
        }
        
        .filter-actions {
            display: flex;
            align-items: flex-end;
            gap: 12px;
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
        <div class="search-container">
            <label for="search-users">search for users</label>
            <input type="text" id="search-users" placeholder="search user">
        </div>
        <div class="filter-container">
            <label for="role-filter-dropdown">filter by role</label>
            <select id="filter-dropdown">
                <option value="">All</option>
                <?php foreach ($rolesData as $role): ?>
                    <option value="<?php echo $role['id']; ?>">
                        <?php echo htmlspecialchars($role['role_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-container">
            <label for="department-filter-dropdown">filter by department</label>
            <select id="department-filter-dropdown">
                <option value="">All</option>
                <?php foreach ($departmentsData as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-container" style="min-width: auto;">
            <label>&nbsp;</label>
            <button id="clear-filters">Reset filters</button>
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
<<<<<<< Updated upstream
                <th><?php if ($canDelete): ?><input type="checkbox" id="select-all"><?php endif; ?></th>
                <th>User</th>
                <th>Role</th>
=======
                <th><input type="checkbox" id="select-all"></th>
                <th>User <span class="sort-icon" id="sort-user">A→Z</span></th>
>>>>>>> Stashed changes
                <th>Departments</th>
                <th>Role</th>
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
        <h2>Add role to department modal</h2>
        <div class="modal-body">
            <h3>DEPARTMENT TITLE</h3>
            <div class="form-group">
                <label>Add role to department</label>
                <select id="department-dropdown">
                    <option value="">Select role</option>
                    <?php foreach ($rolesData as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>ADDED ROLES</label>
                <div id="added-departments-container"></div>
            </div>
            <div class="form-group">
                <label>List of Roles</label>
                <table id="departments-table">
                    <tbody>
                    <?php foreach ($rolesData as $role): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                            <td>
                                <button class="delete-btn" data-role-id="<?php echo $role['id']; ?>">
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
