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
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

// 3) Button flags
$canCreate = $rbac->hasPrivilege('User Management', 'Create');
$canModify = $rbac->hasPrivilege('User Management', 'Modify');
$canRemove = $rbac->hasPrivilege('User Management', 'Remove');
$canTrack  = $rbac->hasPrivilege('User Management', 'Track');

// --- START SORTING IMPLEMENTATION ---

// Define allowed sortable columns and their corresponding database columns/aliases
$sortMap = [
    'username'    => 'u.username',
    'departments' => 'departments_concat', // Alias from GROUP_CONCAT
    'roles'       => 'roles_concat',       // Alias from GROUP_CONCAT
];

// Get sort parameters from GET request
$sortBy = $_GET['sort_by'] ?? 'username'; // Default sort by username
$sortDir = strtolower($_GET['sort_order'] ?? 'asc'); // Default sort order 'asc'

// Validate sort by parameter against the allowed map
if (!isset($sortMap[$sortBy])) {
    $sortBy = 'username'; // Fallback to default if invalid
}

// Validate sort direction
if (!in_array($sortDir, ['asc', 'desc'])) {
    $sortDir = 'asc'; // Fallback to default if invalid
}

// Construct the ORDER BY clause dynamically
$orderByClause = "ORDER BY " . $sortMap[$sortBy] . " " . $sortDir;

// --- END SORTING IMPLEMENTATION ---

// Query active users with their roles and departments for display and sorting
// Use GROUP_CONCAT to get all departments and roles for a user in a single row
$stmt = $pdo->query(
    "SELECT
        u.id,
        u.username,
        u.email,
        u.first_name,
        u.last_name,
        u.date_created,
        u.status,
        GROUP_CONCAT(DISTINCT d.department_name ORDER BY d.department_name SEPARATOR ', ') AS departments_concat,
        GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name SEPARATOR ', ') AS roles_concat
    FROM
        users u
    LEFT JOIN
        user_department_roles udr ON u.id = udr.user_id
    LEFT JOIN
        departments d ON udr.department_id = d.id
    LEFT JOIN
        roles r ON udr.role_id = r.id
    WHERE
        u.is_disabled = 0
    GROUP BY
        u.id, u.username, u.email, u.first_name, u.last_name, u.date_created, u.status
    {$orderByClause}" // Apply dynamic ORDER BY clause here
);
$usersData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Store the actual count of users (after filtering for is_disabled)
$totalUsers = count($usersData);

// Query active roles (for dropdowns)
$stmt = $pdo->query("SELECT id, role_name FROM roles WHERE is_disabled = 0");
$rolesData = $stmt->fetchAll();

// Query all departments (for dropdowns)
$stmt = $pdo->query("SELECT id, department_name, abbreviation FROM departments WHERE is_disabled = 0 ORDER BY department_name");
$departmentsData = $stmt->fetchAll();

// Fetch all user–department–role triples (for detailed client-side mapping in modals)
// This is still needed as the main query above only provides concatenated strings
// and the client-side logic needs individual department and role IDs for editing.
$stmt = $pdo->query(
    "SELECT user_id, role_id, department_id
     FROM user_department_roles
     WHERE 1"
);
$triples = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build a map keyed by "userId–roleId" to collect department IDs
$userRoleMap = [];
foreach ($triples as $t) {
    $userId = (int)$t['user_id'];
    $roleId = $t['role_id'] !== null ? (int)$t['role_id'] : null;
    $deptId = (int)$t['department_id'];

    // Use a special key format for null roles to group assignments correctly
    $key = $roleId !== null ? "{$userId}-{$roleId}" : "{$userId}-null";

    if (!isset($userRoleMap[$key])) {
        $userRoleMap[$key] = [
            'userId'        => $userId,
            'roleId'        => $roleId,
            'departmentIds' => [],
        ];
    }

    // Add department ID to the list for this user-role combination
    if (!in_array($deptId, $userRoleMap[$key]['departmentIds'], true)) {
        $userRoleMap[$key]['departmentIds'][] = $deptId;
    }
}

// Re-index for numeric array for JavaScript consumption
$userRoleDepartments = array_values($userRoleMap);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_module.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <title>User Roles Management</title>
    <style>
        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .pagination .page-item {
            margin: 0 2px;
        }

        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: white;
        }

        .pagination .page-link {
            color: #0d6efd;
            border: 1px solid #dee2e6;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            text-decoration: none;
        }

        .pagination .page-link:hover {
            background-color: #e9ecef;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
        }
        
        /* Enhanced pagination styles */
        .pagination {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 0;
        }
        
        .pagination .page-item .page-link {
            min-width: 36px;
            height: 36px;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem;
            border-radius: 0.25rem;
        }
        
        .pagination .page-item .page-link i {
            font-size: 0.875rem;
        }
        
        /* Sortable column header styles */
        .sortable, .sort-header {
            cursor: pointer;
            position: relative;
            padding-right: 20px !important;
        }

        .sortable:hover, .sort-header:hover {
            background-color: #f8f9fa;
        }

        .sortable i, .sort-header i {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .sortable:hover i, .sort-header:hover i {
            color: #0d6efd;
        }
        
        /* Style for active sort column */
        .sort-header.active-sort {
            background-color: #e9f0ff;
            font-weight: 600;
        }
        
        .sort-header.active-sort i {
            color: #0d6efd;
            font-weight: bold;
        }
        
        /* Center pagination on mobile */
        @media (max-width: 767.98px) {
            .pagination {
                justify-content: center;
                margin: 0.5rem 0;
            }
            
            /* Center info text on mobile */
            .text-muted {
                text-align: center;
                margin-bottom: 0.5rem;
            }
            
            /* Center prev/next buttons on mobile */
            .justify-content-md-end {
                justify-content: center !important;
                margin-top: 0.5rem;
            }
        }
    .container-fluid{
        padding: 20px
    }
        /* Improved filters container styling */
        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            
        }

        .filter-container {
            flex: 1 1 220px;
            min-width: 200px;
            margin-bottom: 0.5rem;
        }

        .search-filter {
            flex: 1 1 220px;
            min-width: 200px;
            margin-bottom: 0.5rem;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            width: 100%;
        }

        /* Button containers in filters */
        .filters-container .col-6 {
            flex: 0 0 auto;
            width: auto;
            min-width: 120px;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 767.98px) {
            .filters-container {
                flex-direction: column;
            }

            .filter-container, 
            .search-filter, 
            .filters-container .col-6 {
                width: 100%;
                flex: 0 0 100%;
            }
        }

        /* Filter button states */
        .btn-filtering {
            background-color: #6c757d !important; 
            opacity: 0.8;
            pointer-events: none;
        }
        
        /* Center pagination on mobile */
        @media (max-width: 767.98px) {
            .pagination {
                justify-content: center;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>

<body>
    <div class="main-content container-fluid">
        <header>
            <h1>USER ROLES MANAGER</h1>
        </header>

        <div class="filters-container">
            <?php if ($canCreate): ?>
                <button type="button" id="create-btn" class="btn btn-dark">
                <i class="bi bi-plus-lg"></i> Add User/s to role</button>
            <?php endif; ?>

            <div class="filter-container">
                <label for="role-filter">FILTER BY ROLE</label>
                <select id="role-filter">
                    <option value="">All Roles</option>
                    <?php foreach ($rolesData as $role): ?>
                        <option value="<?php echo $role['id']; ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-container">
                <label for="dept-filter">FILTER BY DEPARTMENT</label>
                <select id="dept-filter">
                    <option value="" selected>All Departments</option>
                    <?php foreach ($departmentsData as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department_name']); ?>">
                            <?php echo '(' . htmlspecialchars($dept['abbreviation']) . ') ' . htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-filter">
                <label for="search-users">SEARCH</label>
                <input type="text" id="search-users" placeholder="Search users, departments, roles...">
            </div>

            <!-- Buttons -->
            <div class="col-6 col-md-2 d-grid">
                <button type="button" id="filter-btn" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
            </div>

            <div class="col-6 col-md-2 d-grid">
                <button type="button" id="clear-btn" class="btn btn-secondary shadow-sm"><i class="bi bi-x-circle"></i> Clear</button>
            </div>

            <div class="action-buttons">
                <?php if ($rbac->hasPrivilege('User Management', 'Modify')): ?>
                    <a href="user_management.php" class="btn btn-primary"> Manage User Accounts</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive" id="table">
            <table class="table table-striped table-hover" id="urTable">
                <thead>
                    <tr>
                        <th><?php if ($canRemove): ?><input type="checkbox" id="select-all"><?php endif; ?></th>
                        <th><a href="#" class="sort-header" data-sort="username">User <i class="bi bi-caret-up-fill sort-icon"></i></a></th>
                        <th><a href="#" class="sort-header" data-sort="departments">Departments <i class="bi bi-caret-up-fill sort-icon"></i></a></th>
                        <th><a href="#" class="sort-header" data-sort="roles">Roles <i class="bi bi-caret-up-fill sort-icon"></i></a></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersData)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersData as $user): ?>
                            <tr>
                                <td><?php if ($canRemove): ?><input type="checkbox" class="select-row" value="<?= htmlspecialchars($user['id']); ?>"><?php endif; ?></td>
                                <td><?= htmlspecialchars($user['username']); ?></td>
                                <td><?= htmlspecialchars($user['departments_concat'] ?? 'Not assigned'); ?></td>
                                <td><?= htmlspecialchars($user['roles_concat'] ?? 'No roles assigned'); ?></td>
                                <td>
                                    <?php if ($canModify): ?>
                                        <button class="btn-outline-primary edit-btn" 
                                            data-user-id="<?= htmlspecialchars($user['id']); ?>"
                                            data-username="<?= htmlspecialchars($user['username']); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canRemove): ?>
                                        <button class="btn-outline-danger delete-btn"
                                            data-user-id="<?= htmlspecialchars($user['id']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($canRemove): ?>
                <div class="mb-3">
                    <button type="button" id="delete-selected" class="btn btn-danger" style="display: none;" disabled>
                        Remove Selected User Roles
                    </button>
                </div>
            <?php endif; ?>
            <div class="container-fluid">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md-4">
                        <div class="text-muted">
                            <?php
                            // Use the actual user count, not the number of rows in the table
                            $rowsPerPage = 10; // Default rows per page
                            $displayEnd = min($rowsPerPage, $totalUsers);
                            ?>
                            <input type="hidden" id="total-users" value="<?= $totalUsers ?>">
                            <input type="hidden" id="actual-user-count" value="<?= $totalUsers ?>">
                            Showing <span id="currentPage">1</span> to <span id="rowsPerPage"> <?= $displayEnd ?></span> of <span id="totalRows"><?= $totalUsers ?></span> entries
                        </div>
                    </div>
                    <div class="col-12 col-md-4 text-center">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm d-inline-flex justify-content-center mb-0" id="pagination"></ul>
                        </nav>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="d-flex align-items-center gap-2 justify-content-md-end">
                            <button id="prevPage" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                            <select id="rowsPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                            <button id="nextPage" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div id="add-user-roles-modal" class="modal">
        <div class="modal-content">
            <h2>add user to roles modal</h2>
            <div class="modal-body">
                <div class="form-group">
                    <label for="search-department-dropdown">select department <span class="text-danger">*</span></label>
                    <select id="search-department-dropdown">
                        <option value="">Select one department</option>
                        <?php foreach ($departmentsData as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo '(' . htmlspecialchars($dept['abbreviation']) . ') ' . htmlspecialchars($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Department is required</small>
                </div>
                <div class="form-group">
                    <label>selected department</label>
                    <div id="selected-department-container"></div>
                </div>
                <div class="form-group">
                    <label for="search-role-dropdown">search role/s (optional)</label>
                    <select id="search-role-dropdown">
                        <option value="">Select roles</option>
                        <?php foreach ($rolesData as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Leave empty for assignments without roles</small>
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

    <div id="add-department-role-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Role to Department</h2>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>User</label>
                    <div id="edit-user-info" class="info-field"></div>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <div id="edit-department-info" class="info-field"></div>
                </div>
                <div class="form-group">
                    <label for="department-dropdown">Add Role to Department (optional)</label>
                    <select id="department-dropdown" class="form-control">
                        <option value="">Select role</option>
                        <?php foreach ($rolesData as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endforeach; ?>

                    </select>
                    <small class="form-text text-muted">You can save without adding any roles</small>
                </div>
                <div class="form-group">
                    <label>Assigned Roles Table</label>
                    <div class="department-table-container">
                        <table class="table table-striped table-hover" id="assigned-roles-table">
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th class="text-end" style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="close-department-role-modal" class="btn btn-secondary">Cancel</button>
                <button id="save-department-role" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="delete-confirm-modal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lower">
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

    <script>
        let usersData = <?php echo json_encode($usersData); ?>;
        let rolesData = <?php echo json_encode($rolesData); ?>;
        let departmentsData = <?php echo json_encode($departmentsData); ?>;
        let userRoleDepartments = <?php echo json_encode($userRoleDepartments); ?>;

        // Pass RBAC privileges to JavaScript
        const userPrivileges = {
            canCreate: <?php echo json_encode($canCreate); ?>,
            canModify: <?php echo json_encode($canModify); ?>,
            canDelete: <?php echo json_encode($canRemove); ?>,
            canTrack: <?php echo json_encode($canTrack); ?>
        };
        // Pass current sort state to JavaScript
        var currentSortBy = "<?php echo htmlspecialchars($sortBy); ?>";
        var currentSortOrder = "<?php echo htmlspecialchars($sortDir); ?>";
        
        // Make sure global window variables are set
        window.currentSortBy = currentSortBy;
        window.currentSortOrder = currentSortOrder;
        
        // Log the initial sort state for debugging
        console.log('Initial sort state:', { 
            currentSortBy: window.currentSortBy,
            currentSortOrder: window.currentSortOrder
        });
    </script>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/user_roles_management.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/user_roles_fixes.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Make sure sort variables are properly initialized
            window.currentSortBy = currentSortBy || "username";
            window.currentSortOrder = currentSortOrder || "asc";
            
            // Ensure the sort headers reflect the current sort state on page load
            $(document).ready(function() {
                const urlParams = new URLSearchParams(window.location.search);
                const urlSortBy = urlParams.get('sort_by');
                const urlSortOrder = urlParams.get('sort_order');
                
                // If URL has sort parameters, use them
                if (urlSortBy) {
                    window.currentSortBy = urlSortBy;
                }
                
                if (urlSortOrder) {
                    window.currentSortOrder = urlSortOrder;
                }
                
                // Update the sort header visual state
                $('.sort-header').removeClass('active-sort');
                $('.sort-icon').removeClass('bi-caret-up-fill bi-caret-down-fill').addClass('bi-caret-up-fill');
                
                const activeHeader = $(`.sort-header[data-sort="${window.currentSortBy}"]`);
                if (activeHeader.length) {
                    activeHeader.addClass('active-sort');
                    const icon = activeHeader.find('.sort-icon');
                    icon.removeClass('bi-caret-up-fill bi-caret-down-fill');
                    
                    if (window.currentSortOrder === 'asc') {
                        icon.addClass('bi-caret-up-fill');
                    } else {
                        icon.addClass('bi-caret-down-fill');
                    }
                }
            });
            
            // Initialize pagination for user roles table
            window.paginationConfig = {
                tableId: 'urTable',
                rowsPerPageSelectId: 'rowsPerPageSelect',
                currentPageId: 'currentPage',
                rowsPerPageId: 'rowsPerPage',
                totalRowsId: 'totalRows',
                prevPageId: 'prevPage',
                nextPageId: 'nextPage',
                paginationId: 'pagination',
                currentPage: 1
            };

            // Initialize rows arrays for pagination and filtering
            window.allRows = Array.from(document.querySelectorAll('#urTable tbody tr'));
            window.filteredRows = [...window.allRows];

            // Initialize event listeners for pagination buttons
            document.getElementById('prevPage').addEventListener('click', function(e) {
                e.preventDefault();
                if (window.paginationConfig.currentPage > 1) {
                    window.paginationConfig.currentPage--;
                    window.updatePagination();
                }
            });
            
            document.getElementById('nextPage').addEventListener('click', function(e) {
                e.preventDefault();
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value) || 10;
                
                // Get the right number of users for pagination, not rows
                const userIds = getUserIdsFromRows(window.filteredRows);
                const totalPages = Math.ceil(userIds.length / rowsPerPage);
                
                if (window.paginationConfig.currentPage < totalPages) {
                    window.paginationConfig.currentPage++;
                    window.updatePagination();
                }
            });
            
            // Listen for rows per page changes
            document.getElementById('rowsPerPageSelect').addEventListener('change', function() {
                window.paginationConfig.currentPage = 1; // Reset to first page
                window.updatePagination();
            });
            
            // Helper function to get unique user IDs from rows
            function getUserIdsFromRows(rows) {
                const userIds = new Set();
                rows.forEach(row => {
                    let userId = null;
                    const checkbox = row.querySelector('.select-row');
                    if (checkbox) {
                        userId = checkbox.value;
                    } else {
                        const editBtn = row.querySelector('.edit-btn');
                        if (editBtn) {
                            userId = editBtn.getAttribute('data-user-id');
                        }
                    }
                    if (userId) {
                        userIds.add(userId);
                    }
                });
                return Array.from(userIds);
            }
            
            // Override the standard updatePagination function to handle user grouping properly
            window.updatePagination = function() {
                console.log('Updating pagination');
                
                // Get all rows currently in the table
                const rows = Array.from(document.querySelectorAll('#urTable tbody tr'));
                
                // Skip pagination if we're showing the empty state
                if (rows.length === 1 && rows[0].cells.length === 1 && rows[0].cells[0].colSpan === 5) {
                    console.log('Empty state detected, skipping pagination');
                    
                    // Update count displays to show 0
                    const totalRowsEl = document.getElementById('totalRows');
                    if (totalRowsEl) totalRowsEl.textContent = '0';
                    
                    const currentPageEl = document.getElementById('currentPage');
                    if (currentPageEl) currentPageEl.textContent = '0';
                    
                    const rowsPerPageEl = document.getElementById('rowsPerPage');
                    if (rowsPerPageEl) rowsPerPageEl.textContent = '0';
                    
                    // Hide pagination elements
                    forcePaginationCheck();
                    return;
                }
                
                // Group rows by user ID
                const userGroups = {};
                rows.forEach(row => {
                    // Extract user ID from the row
                    let userId = null;
                    const checkbox = row.querySelector('.select-row');
                    if (checkbox) {
                        userId = checkbox.value;
                    } else {
                        // Try to get from edit button data attribute as fallback
                        const editBtn = row.querySelector('.edit-btn');
                        if (editBtn) {
                            userId = editBtn.getAttribute('data-user-id');
                        }
                    }
                    
                    if (userId) {
                        if (!userGroups[userId]) {
                            userGroups[userId] = [];
                        }
                        userGroups[userId].push(row);
                    }
                });
                
                // Get unique user IDs and count
                const uniqueUserIds = Object.keys(userGroups);
                const totalUniqueUsers = uniqueUserIds.length;
                
                // Get pagination settings
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value) || 10;
                const currentPage = window.paginationConfig.currentPage || 1;
                const totalPages = Math.ceil(totalUniqueUsers / rowsPerPage);
                
                // Adjust current page if it's out of range
                if (currentPage > totalPages && totalPages > 0) {
                    window.paginationConfig.currentPage = totalPages;
                }
                
                // Update total rows display
                const totalRowsEl = document.getElementById('totalRows');
                if (totalRowsEl) {
                    totalRowsEl.textContent = totalUniqueUsers;
                }
                
                // Calculate which users to show on current page
                const startUserIndex = (currentPage - 1) * rowsPerPage;
                const endUserIndex = Math.min(startUserIndex + rowsPerPage, totalUniqueUsers);
                const visibleUserIds = uniqueUserIds.slice(startUserIndex, endUserIndex);
                
                // Update pagination display
                const rowsPerPageEl = document.getElementById('rowsPerPage');
                if (rowsPerPageEl) {
                    rowsPerPageEl.textContent = Math.min(endUserIndex, totalUniqueUsers);
                }
                
                const currentPageEl = document.getElementById('currentPage');
                if (currentPageEl) {
                    currentPageEl.textContent = totalUniqueUsers > 0 ? startUserIndex + 1 : 0;
                }
                
                // Hide all rows first
                rows.forEach(row => row.style.display = 'none');
                
                // Show only rows for users on the current page
                visibleUserIds.forEach(userId => {
                    const userRows = userGroups[userId] || [];
                    userRows.forEach(row => row.style.display = '');
                });
                
                // Generate pagination links
                generatePaginationLinks(currentPage, totalPages);
                
                // Update prev/next button visibility
                forcePaginationCheck();
            };
            
            // Function to generate pagination links
            function generatePaginationLinks(currentPage, totalPages) {
                const paginationEl = document.getElementById('pagination');
                if (!paginationEl) return;
                
                paginationEl.innerHTML = '';
                
                // Don't show pagination if there's only one page or no pages
                if (totalPages <= 1) return;
                
                // Previous button
                const prevLi = document.createElement('li');
                prevLi.className = 'page-item' + (currentPage <= 1 ? ' disabled' : '');
                const prevLink = document.createElement('a');
                prevLink.className = 'page-link';
                prevLink.href = '#';
                prevLink.innerHTML = '<i class="bi bi-chevron-left"></i>';
                prevLink.setAttribute('aria-label', 'Previous');
                prevLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (currentPage > 1) {
                        window.paginationConfig.currentPage--;
                        window.updatePagination();
                    }
                });
                prevLi.appendChild(prevLink);
                paginationEl.appendChild(prevLi);
                
                // Calculate page range to show
                let startPage = Math.max(1, currentPage - 1);
                let endPage = Math.min(totalPages, startPage + 2);
                
                // Adjust if we're near the end
                if (endPage - startPage < 2 && startPage > 1) {
                    startPage = Math.max(1, endPage - 2);
                }
                
                // First page if not in range
                if (startPage > 1) {
                    const firstLi = document.createElement('li');
                    firstLi.className = 'page-item';
                    const firstLink = document.createElement('a');
                    firstLink.className = 'page-link';
                    firstLink.href = '#';
                    firstLink.textContent = '1';
                    firstLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.paginationConfig.currentPage = 1;
                        window.updatePagination();
                    });
                    firstLi.appendChild(firstLink);
                    paginationEl.appendChild(firstLi);
                    
                    // Add ellipsis if needed
                    if (startPage > 2) {
                        const ellipsisLi = document.createElement('li');
                        ellipsisLi.className = 'page-item disabled';
                        const ellipsisSpan = document.createElement('span');
                        ellipsisSpan.className = 'page-link';
                        ellipsisSpan.textContent = '...';
                        ellipsisLi.appendChild(ellipsisSpan);
                        paginationEl.appendChild(ellipsisLi);
                    }
                }
                
                // Page numbers
                for (let i = startPage; i <= endPage; i++) {
                    const pageLi = document.createElement('li');
                    pageLi.className = 'page-item' + (i === currentPage ? ' active' : '');
                    const pageLink = document.createElement('a');
                    pageLink.className = 'page-link';
                    pageLink.href = '#';
                    pageLink.textContent = i;
                    pageLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.paginationConfig.currentPage = i;
                        window.updatePagination();
                    });
                    pageLi.appendChild(pageLink);
                    paginationEl.appendChild(pageLi);
                }
                
                // Last page if not in range
                if (endPage < totalPages) {
                    // Add ellipsis if needed
                    if (endPage < totalPages - 1) {
                        const ellipsisLi = document.createElement('li');
                        ellipsisLi.className = 'page-item disabled';
                        const ellipsisSpan = document.createElement('span');
                        ellipsisSpan.className = 'page-link';
                        ellipsisSpan.textContent = '...';
                        ellipsisLi.appendChild(ellipsisSpan);
                        paginationEl.appendChild(ellipsisLi);
                    }
                    
                    const lastLi = document.createElement('li');
                    lastLi.className = 'page-item';
                    const lastLink = document.createElement('a');
                    lastLink.className = 'page-link';
                    lastLink.href = '#';
                    lastLink.textContent = totalPages;
                    lastLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.paginationConfig.currentPage = totalPages;
                        window.updatePagination();
                    });
                    lastLi.appendChild(lastLink);
                    paginationEl.appendChild(lastLi);
                }
                
                // Next button
                const nextLi = document.createElement('li');
                nextLi.className = 'page-item' + (currentPage >= totalPages ? ' disabled' : '');
                const nextLink = document.createElement('a');
                nextLink.className = 'page-link';
                nextLink.href = '#';
                nextLink.innerHTML = '<i class="bi bi-chevron-right"></i>';
                nextLink.setAttribute('aria-label', 'Next');
                nextLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (currentPage < totalPages) {
                        window.paginationConfig.currentPage++;
                        window.updatePagination();
                    }
                });
                nextLi.appendChild(nextLink);
                paginationEl.appendChild(nextLi);
            }

            // Define a flag to control automatic filtering
            let allowAutoFiltering = false;
            
            function filterTable() {
                // Check if this was called from an event that should be ignored
                if (!allowAutoFiltering && window.filterCalledFrom === 'auto') {
                    console.log('Automatic filtering prevented');
                    window.filterCalledFrom = null;
                    return;
                }
                
                // Double-check if this was triggered by the filter button
                const calledByButton = window.filterCalledFrom === 'button';
                if (!calledByButton) {
                    console.log('Filtering not triggered by button - prevented');
                    window.filterCalledFrom = null;
                    return;
                }
                
                console.log('Filtering table (allowed)');
                const searchText = $('#search-users').val().toLowerCase();
                const roleFilter = $('#role-filter').val();
                const deptFilter = $('#dept-filter').val().toLowerCase(); // Use value instead of text

                // Get the department filter text (not just the value)
                const deptFilterText = deptFilter ? $('#dept-filter option:selected').text().toLowerCase() : '';

                // Make sure allRows is initialized
                if (!window.allRows || window.allRows.length === 0) {
                    window.allRows = Array.from(document.querySelectorAll('#urTable tbody tr'));
                }

                // Convert role ID to name for filtering
                const roleFilterName = roleFilter ? 
                    rolesData.find(r => r.id == roleFilter)?.role_name.toLowerCase() : '';

                console.log('Filtering with:', { 
                    searchText, 
                    roleFilter, 
                    deptFilter,
                    deptFilterText,
                    roleFilterName 
                });

                // Create a copy of all rows to work with
                const allRowsCopy = window.allRows.map(row => row.cloneNode(true));
                
                // Reset the table body
                const tbody = document.querySelector('#urTable tbody');
                
                // Group rows by user ID to handle multiple departments/roles per user
                const userGroups = {};
                allRowsCopy.forEach(row => {
                    // Extract user ID from the row
                    let userId = null;
                    const checkbox = row.querySelector('.select-row');
                    if (checkbox) {
                        userId = checkbox.value;
                    } else {
                        // Try to get from edit button data attribute as fallback
                        const editBtn = row.querySelector('.edit-btn');
                        if (editBtn) {
                            userId = editBtn.getAttribute('data-user-id');
                        }
                    }
                    
                    if (userId) {
                        if (!userGroups[userId]) {
                            userGroups[userId] = {
                                rows: [],
                                userData: {
                                    username: row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '',
                                    departments: row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '',
                                    roles: row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || ''
                                }
                            };
                        }
                        userGroups[userId].rows.push(row);
                    }
                });

                // Filter users based on criteria
                const filteredUserIds = [];
                Object.keys(userGroups).forEach(userId => {
                    const userData = userGroups[userId].userData;
                    
                    // Apply search filter across all columns (username, departments, roles)
                    const matchesSearch = !searchText || 
                        userData.username.includes(searchText) ||
                        userData.departments.includes(searchText) || 
                        userData.roles.includes(searchText);
                    
                    // Apply role filter using role name instead of ID
                    let matchesRole = !roleFilter;
                    if (roleFilter && roleFilterName) {
                        // Check for exact match
                        matchesRole = userData.roles.includes(roleFilterName);
                        
                        // If no exact match, try checking for partial/case-insensitive match
                        if (!matchesRole) {
                            const rolesLower = userData.roles.toLowerCase();
                            matchesRole = rolesLower.includes(roleFilterName.toLowerCase());
                        }
                    }
                    
                    // Debug logging for role filtering
                    if (roleFilter && roleFilterName) {
                        console.log(`User ${userData.username} - Role check:`, {
                            roleFilterName: roleFilterName,
                            userRoles: userData.roles,
                            matches: userData.roles.includes(roleFilterName)
                        });
                    }
                    
                    // Apply department filter using the value
                    let matchesDept = true;
                    if (deptFilter && deptFilter !== '') {
                        const userDepts = userData.departments.split(', ').map(d => d.trim().toLowerCase());
                        matchesDept = userDepts.includes(deptFilter);
                    }
                    
                    if (matchesSearch && matchesRole && matchesDept) {
                        filteredUserIds.push(userId);
                    }
                });

                // Clear the table
                tbody.innerHTML = '';
                
                // Collect all rows for filtered users
                window.filteredRows = [];
                filteredUserIds.forEach(userId => {
                    userGroups[userId].rows.forEach(row => {
                        window.filteredRows.push(row);
                        tbody.appendChild(row);
                    });
                });

                console.log('Filtered users:', filteredUserIds.length);
                console.log('Filtered rows:', window.filteredRows.length);

                // Show empty state if no results
                if (window.filteredRows.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center">
                                <div class="empty-state">
                                    <div class="empty-state-icon">🔍</div>
                                    <div class="empty-state-message">No matching user roles found</div>
                                </div>
                            </td>
                        </tr>
                    `;
                }

                // Rebind event handlers to the rows
                rebindRowEvents();
                
                // Reset to first page and update pagination
                window.paginationConfig.currentPage = 1;
                window.updatePagination();
                
                // Reset flag after successful filtering
                window.filterCalledFrom = null;
            }
            
            // Mark filterTable as page-specific to prevent automatic triggering from pagination.js
            window.filterTable = filterTable;
            window.filterTable.isPageSpecific = true;
            
            // Function to rebind events to table rows
            function rebindRowEvents() {
                // Rebind edit button click events
                document.querySelectorAll('#urTable .edit-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.getAttribute('data-user-id');
                        const username = this.getAttribute('data-username');
                        openEditModal(userId, username);
                    });
                });
                
                // Rebind delete button click events
                document.querySelectorAll('#urTable .delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = this.getAttribute('data-user-id');
                        openDeleteModal(userId);
                    });
                });
                
                // Rebind checkbox events if they exist
                document.querySelectorAll('#urTable .select-row').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        if (typeof updateDeleteSelectedButton === 'function') {
                            updateDeleteSelectedButton();
                        }
                    });
                });
            }

            // ONLY bind filter function to filter button click event
            $('#filter-btn').on('click', function() {
                console.log('Filter button clicked in PHP');
                
                // Add visual feedback that filtering is happening
                $(this).addClass('btn-filtering').html('<i class="bi bi-hourglass-split"></i> Filtering...');
                
                // Set a small timeout to allow the UI to update before filtering
                setTimeout(() => {
                    window.filterCalledFrom = 'button';
                    filterTable();
                    
                    // Check if we have any results after filtering
                    if (window.filteredRows && window.filteredRows.length === 0) {
                        console.log('No matching results found');
                        
                        // Use Toast notification if available
                        if (typeof Toast !== 'undefined') {
                            const deptFilter = $('#dept-filter').val();
                            const deptFilterText = $('#dept-filter option:selected').text();
                            
                            let message = 'No matching results found.';
                            if (deptFilter) {
                                message += ' Try a different department filter.';
                            }
                            
                            Toast.info(message, 3000, 'Filter Results');
                        }
                    }
                    
                    // Reset button after filtering is done
                    $(this).removeClass('btn-filtering').html('<i class="bi bi-funnel"></i> Filter');
                }, 100);
            });
            
            // Handle clear filters button
            $('#clear-btn').on('click', function() {
                console.log('Clear button clicked - Reloading page');
                // Reload the page without any filter parameters
                window.location.href = window.location.pathname;
            });

            // REMOVE THE DUPLICATE SORTING HANDLER AND USE ONLY THE ONE FROM THE JS FILE
            // Unbind any existing click handlers from sort headers
            $('.sort-header').off('click');
            
            // Re-bind the sort handler with a fixed implementation
            $('.sort-header').on('click', function(e) {
                e.preventDefault();
                const sortField = $(this).data('sort');
                
                // Toggle sort direction or set to ascending if changing column
                let newSortOrder = 'asc';
                
                // If clicking the same header that's already active, toggle sort order
                if (window.currentSortBy === sortField) {
                    newSortOrder = (window.currentSortOrder === 'asc') ? 'desc' : 'asc';
                }
                
                // Add loading indicator to the clicked header
                $('.sort-header').removeClass('sorting');
                $(this).addClass('sorting');
                $(this).find('.sort-icon')
                    .removeClass('bi-caret-up-fill bi-caret-down-fill')
                    .addClass('bi-hourglass-split');
                
                // Update URL with sort parameters
                const urlParams = new URLSearchParams(window.location.search);
                
                // Set the new sort parameters
                urlParams.set('sort_by', sortField);
                urlParams.set('sort_order', newSortOrder);
                
                // Preserve existing filters
                const searchValue = $('#search-users').val();
                const roleFilterValue = $('#role-filter').val();
                const deptFilterValue = $('#dept-filter').val();
                
                if (searchValue) {
                    urlParams.set('search', encodeURIComponent(searchValue));
                } else {
                    urlParams.delete('search');
                }
                
                if (roleFilterValue) {
                    urlParams.set('role', encodeURIComponent(roleFilterValue));
                } else {
                    urlParams.delete('role');
                }
                
                if (deptFilterValue) {
                    urlParams.set('department', encodeURIComponent(deptFilterValue));
                } else {
                    urlParams.delete('department');
                }
                
                // Save the sort state to window variables before reloading
                window.currentSortBy = sortField;
                window.currentSortOrder = newSortOrder;
                
                console.log(`Sorting by ${sortField} in ${newSortOrder} order`);
                
                // Add a short delay to show the loading indicator
                setTimeout(function() {
                    // Reload the page with the new URL parameters for server-side sorting
                    window.location.href = window.location.pathname + '?' + urlParams.toString();
                }, 300);
            });
            
            // Function to update sort icons (up/down arrows) based on current sort state
            function updateSortIcons() {
                // Remove all active classes first
                $('.sort-header').removeClass('active-sort');
                $('.sort-icon').removeClass('bi-caret-up-fill bi-caret-down-fill').addClass('bi-caret-up-fill');
                
                // Only proceed if we have a valid sort field
                if (!window.currentSortBy) return;
                
                // Find the matching sort header
                const activeHeader = $(`.sort-header[data-sort="${window.currentSortBy}"]`);
                if (activeHeader.length) {
                    // Add active class to the header
                    activeHeader.addClass('active-sort');
                    
                    // Set the appropriate icon based on sort direction
                    const icon = activeHeader.find('.sort-icon');
                    icon.removeClass('bi-caret-up-fill bi-caret-down-fill');
                    
                    if (window.currentSortOrder === 'asc') {
                        icon.addClass('bi-caret-up-fill');
                    } else {
                        icon.addClass('bi-caret-down-fill');
                    }
                }
            }
            
            // Initialize the table with proper sort icons
            updateSortIcons();
        });
    </script>
</body>
</html>