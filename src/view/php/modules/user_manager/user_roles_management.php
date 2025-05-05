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

// Query all departments (show all regardless of is_disabled)
$stmt = $pdo->query("SELECT id, department_name, abbreviation FROM departments ORDER BY department_name");
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
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .modal.fade .modal-dialog {
            transition: transform .3s ease-out;
        }
        
        /* Sort icon styling */
        .sort-icon {
            cursor: pointer;
            margin-left: 5px;
            font-size: 12px;
            background-color: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 2px 6px;
            display: inline-block;
        }
        
        /* Modern input styling */
        .search-container input,
        .filter-container select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background-color: white;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .search-container input:focus,
        .filter-container select:focus {
            outline: none;
            border-color: #a5b4fc;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
        }
        
        .search-container,
        .filter-container {
            margin-right: 15px;
            margin-bottom: 10px;
        }
        
        .search-container label,
        .filter-container label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
        }
        
        /* Remove search icon */
        .search-container::after {
            content: none !important;
        }
        
        /* User List Modal Styling */
        #current-users-table,
        #departments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
        }
        
        #current-users-table tbody,
        #departments-table tbody {
            display: block;
            max-height: 250px;
            overflow-y: auto;
            width: 100%;
        }
        
        #current-users-table tr,
        #departments-table tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        
        #current-users-table td,
        #departments-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        
        #current-users-table tr:last-child td,
        #departments-table tr:last-child td {
            border-bottom: none;
        }
        
        #current-users-table td:last-child,
        #departments-table td:last-child {
            text-align: right;
            width: 60px;
            padding-right: 25px;
            position: relative;
        }
        
        #current-users-table tr:nth-child(even),
        #departments-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        /* Remove hover effect */
        #current-users-table tr:hover,
        #departments-table tr:hover {
            background-color: transparent;
        }
        
        /* Button styling */
        #current-users-table .delete-btn,
        #departments-table .delete-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        /* Selection containers */
        #selected-users-container, 
        #selected-roles-container,
        #added-departments-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            min-height: 42px;
            background-color: #f9fafb;
            margin-top: 8px;
            margin-bottom: 15px;
        }
        
        /* Selected items */
        .selected-item {
            background-color: #eef2ff;
            border: 1px solid #e0e7ff;
            color: #4f46e5;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
        }
        
        .selected-item .remove-btn {
            background: none;
            border: none;
            color: #4f46e5;
            margin-left: 6px;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            line-height: 1;
        }
        
        .selected-item .remove-btn:hover {
            color: #6366f1;
        }
        
        /* Modal styling */
        .modal-content {
            max-width: 600px;
            width: 95%;
            padding: 20px;
            border-radius: 8px;
        }
        
        .modal-content h2 {
            margin-top: 0;
            text-transform: capitalize;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            font-size: 14px;
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
            <input type="text" id="search-users" placeholder="Search user...">
        </div>
        <div class="filter-container">
            <label for="role-filter">filter by role</label>
            <select id="role-filter">
                <option value="">All</option>
                <?php foreach ($rolesData as $role): ?>
                    <option value="<?php echo $role['id']; ?>">
                        <?php echo htmlspecialchars($role['role_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-container">
            <label for="dept-filter">Filter by Department</label>
            <select id="dept-filter">
                <option value="" selected>All Departments</option>
                <?php foreach ($departmentsData as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                        <?php echo htmlspecialchars($dept['department_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
         <div class="action-buttons">
            <button id="clear-filters-btn" class="clear-filters-btn">Clear Filters</button>
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
                <th>User <span class="sort-icon" id="sort-user">A→Z</span></th>
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
                <label for="search-department-dropdown">select department</label>
                <select id="search-department-dropdown">
                    <option value="">Select one department</option>
                    <?php foreach ($departmentsData as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>selected department</label>
                <div id="selected-department-container"></div>
            </div>
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
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize Select2 for department filter
        $('#dept-filter').select2({
            placeholder: 'All Departments',
            allowClear: true,
            width: '100%'
        });
        // Always show the placeholder as an option
        $('#dept-filter').val('').trigger('change');
        // Ensure filter triggers on Select2 change and clear
        $('#dept-filter').on('change', function() {
            const filterUserId = $('#search-users').val();
            const filterRoleId = $('#role-filter').val();
            const filterDeptId = $(this).val();
            if (typeof renderUserRolesTable === 'function') {
                renderUserRolesTable(filterUserId, filterRoleId, filterDeptId, window.userSortDirection || 'asc');
            }
        });
        // Also clear Select2 when Clear Filters is clicked
        $('#clear-filters-btn').on('click', function() {
            $('#dept-filter').val('').trigger('change');
        });
    });
</script>
</body>
</html>
