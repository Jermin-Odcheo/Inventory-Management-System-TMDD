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

            <!-- <div class="filter-container">
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
                <label for="search-users">SEARCH FOR USERS</label>
                <input type="text" id="search-users" placeholder="Search user...">
            </div> -->

            <!-- Buttons -->
            <div class="col-6 col-md-2 d-grid">
                <button type="submit" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
            </div>

            <div class="col-6 col-md-2 d-grid">
                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary shadow-sm"><i class="bi bi-x-circle"></i> Clear</a>
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
                    <div class="col-12 col-sm-auto">
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
    </script>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/user_roles_management.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for department filter
            $('#dept-filter').select2({
                placeholder: 'All Departments',
                allowClear: true,
                minimumResultsForSearch: 5,
                dropdownParent: $('body'), // Attach to body for proper z-index handling
                matcher: function(params, data) {
                    // If there are no search terms, return all of the data
                    if ($.trim(params.term) === '') {
                        return data;
                    }

                    // Search in both department name and abbreviation
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }

                    // Try to extract abbreviation from the data text (format: "(ABBR) Department Name")
                    const abbr = data.text.match(/^\(([^)]+)\)/);
                    if (abbr && abbr[1].toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }

                    // Return `null` if the term should not be displayed
                    return null;
                }
            });

            // Initialize Select2 for role filter
            $('#role-filter').select2({
                placeholder: 'All Roles',
                allowClear: true,
                width: '100%',
                minimumResultsForSearch: 5,
                dropdownParent: $('body')
            });

            // Initialize Select2 for modal dropdowns
            $('#search-department-dropdown').select2({
                placeholder: 'Select Department',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#add-user-roles-modal .modal-body'),
                matcher: function(params, data) {
                    // If there are no search terms, return all of the data
                    if ($.trim(params.term) === '') {
                        return data;
                    }

                    // Search in both department name and abbreviation
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }

                    // Try to extract abbreviation from the data text (format: "(ABBR) Department Name")
                    const abbr = data.text.match(/^\(([^)]+)\)/);
                    if (abbr && abbr[1].toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }

                    // Return `null` if the term should not be displayed
                    return null;
                }
            }).on('select2:select', function(e) {
                // Get the selected department ID
                const deptId = parseInt($(this).val());
                if (deptId) {
                    // Get the department object
                    const dept = departmentsData.find(d => d.id === deptId);
                    if (dept) {
                        // Add the department to the selection
                        const container = document.getElementById('selected-department-container');

                        // Clear previous selection (only one department allowed)
                        container.innerHTML = '';

                        // Create the selected item element
                        const selectedItem = document.createElement('span');
                        selectedItem.className = 'selected-item';
                        selectedItem.dataset.id = dept.id;
                        selectedItem.innerHTML = `
                            ${dept.department_name}
                            <button class="remove-btn" data-id="${dept.id}" data-type="department">✕</button>
                        `;
                        container.appendChild(selectedItem);

                        // Add remove button event listener
                        selectedItem.querySelector('.remove-btn').addEventListener('click', function() {
                            // Remove the department selection
                            window.selectedDepartment = null;
                            selectedItem.remove();
                        });

                        // Set the selectedDepartment
                        window.selectedDepartment = dept;
                    }
                }

                // Reset the select
                $(this).val(null).trigger('change');
            });

            $('#search-role-dropdown').select2({
                placeholder: 'Select Roles',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#add-user-roles-modal .modal-body')
            }).on('select2:select', function(e) {
                // Get the selected role ID
                const roleId = parseInt($(this).val());
                if (roleId) {
                    // Get the role object
                    const role = rolesData.find(r => r.id === roleId);
                    if (role) {
                        // Add the role to the selection
                        const container = document.getElementById('selected-roles-container');

                        // Check if this role is already selected
                        const alreadySelected = Array.from(container.children).some(
                            child => parseInt(child.dataset.id) === roleId
                        );

                        if (!alreadySelected) {
                            // Create the selected item element
                            const selectedItem = document.createElement('span');
                            selectedItem.className = 'selected-item';
                            selectedItem.dataset.id = role.id;
                            selectedItem.innerHTML = `
                                ${role.role_name}
                                <button class="remove-btn" data-id="${role.id}" data-type="role">✕</button>
                            `;
                            container.appendChild(selectedItem);

                            // Add remove button event listener
                            selectedItem.querySelector('.remove-btn').addEventListener('click', function() {
                                // Remove from the selectedRoles array
                                window.selectedRoles = window.selectedRoles.filter(r => r.id !== role.id);
                                selectedItem.remove();
                            });

                            // Add to the selectedRoles array
                            if (!window.selectedRoles) window.selectedRoles = [];
                            window.selectedRoles.push(role);
                        }
                    }
                }

                // Reset the select
                $(this).val(null).trigger('change');
            });

            $('#search-users-dropdown').select2({
                placeholder: 'Select Users',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#add-user-roles-modal .modal-body')
            }).on('select2:select', function(e) {
                // Get the selected user ID
                const userId = parseInt($(this).val());
                if (userId) {
                    // Get the user object
                    const user = usersData.find(u => u.id === userId);
                    if (user) {
                        // Add the user to the selection
                        const container = document.getElementById('selected-users-container');

                        // Check if this user is already selected
                        const alreadySelected = Array.from(container.children).some(
                            child => parseInt(child.dataset.id) === userId
                        );

                        if (!alreadySelected) {
                            // Create the selected item element
                            const selectedItem = document.createElement('span');
                            selectedItem.className = 'selected-item';
                            selectedItem.dataset.id = user.id;
                            selectedItem.innerHTML = `
                                ${user.username}
                                <button class="remove-btn" data-id="${user.id}" data-type="user">✕</button>
                            `;
                            container.appendChild(selectedItem);

                            // Add remove button event listener
                            selectedItem.querySelector('.remove-btn').addEventListener('click', function() {
                                // Remove from the selectedUsers array
                                window.selectedUsers = window.selectedUsers.filter(u => u.id !== user.id);
                                selectedItem.remove();
                            });

                            // Add to the selectedUsers array
                            if (!window.selectedUsers) window.selectedUsers = [];
                            window.selectedUsers.push(user);
                        }
                    }
                }

                // Reset the select
                $(this).val(null).trigger('change');
            });

            // Always show the placeholder as an option for both filters
            $('#dept-filter').val('').trigger('change');
            $('#role-filter').val('').trigger('change');

            // Find the role name from the role ID
            function getRoleName(roleId) {
                const role = rolesData.find(r => r.id == roleId);
                return role ? role.role_name : '';
            }

            // Direct table filtering without relying on external functions
            function filterTable() {
                const userSearch = $('#search-users').val().toLowerCase();
                const roleFilter = $('#role-filter').val();
                const deptFilter = $('#dept-filter').val().toLowerCase();

                // Get role name for display and filtering
                const roleFilterName = roleFilter ? getRoleName(roleFilter).toLowerCase() : '';
                // Show all rows first
                $('#urTable tbody tr').show();

                // Apply user search filter if present
                if (userSearch) {
                    $('#urTable tbody tr').each(function() {
                        const userCell = $(this).find('td:nth-child(2)').text().toLowerCase();
                        if (!userCell.includes(userSearch)) {
                            $(this).hide();
                        }
                    });
                }

                // Apply role filter if selected
                if (roleFilter) {
                    $('#urTable tbody tr:visible').each(function() {
                        const roleCell = $(this).find('td:nth-child(4)').text().toLowerCase();
                        if (!roleCell.includes(roleFilterName)) {
                            $(this).hide();
                        }
                    });
                }

                // Apply department filter if selected
                if (deptFilter) {
                    $('#urTable tbody tr:visible').each(function() {
                        const deptCell = $(this).find('td:nth-child(3)').text().toLowerCase();
                        if (!deptCell.includes(deptFilter)) {
                            $(this).hide();
                        }
                    });
                }

                // Count unique users instead of rows
                const uniqueUsernames = new Set();
                $('#urTable tbody tr:visible').each(function() {
                    const username = $(this).find('td:nth-child(2)').text().trim();
                    if (username) {
                        uniqueUsernames.add(username);
                    }
                });

                // Update the visibility count - THIS IS THE KEY PART
                const visibleCount = uniqueUsernames.size;

                // Update pagination info
                $('#totalRows').text(visibleCount);
                if (visibleCount > 0) {
                    const rowsPerPage = parseInt($('#rowsPerPageSelect').val()) || 10;
                    $('#rowsPerPage').text(Math.min(rowsPerPage, visibleCount));
                    $('#currentPage').text('1');
                } else {
                    $('#rowsPerPage').text('0');
                    $('#currentPage').text('0');
                }

                // Update pagination controls
                updatePaginationControls(visibleCount);
            }

            // Helper to update pagination visibility
            function updatePaginationControls(visibleCount) {
                const rowsPerPage = parseInt($('#rowsPerPageSelect').val()) || 10;

                if (visibleCount <= rowsPerPage) {
                    $('#prevPage, #nextPage').addClass('d-none');
                    $('#pagination').empty();
                } else {
                    $('#prevPage, #nextPage').removeClass('d-none');
                    // If you have a pagination function, call it here
                }
            }

            // Bind to search input with debounce for performance
            let searchTimer;
            $('#search-users').on('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(filterTable, 300);
            });

            // Bind to select2 changes
            $('#dept-filter, #role-filter').on('change', function() {
                filterTable();
            });

            // Clear filters button
            $('#clear-filters-btn').on('click', function() {
                // Clear all filters
                $('#search-users').val('');
                $('#dept-filter').val('').trigger('change');
                $('#role-filter').val('').trigger('change');

                // Show all rows
                $('#urTable tbody tr').show();

                // Count unique users
                const uniqueUsernames = new Set();
                $('#urTable tbody tr').each(function() {
                    const username = $(this).find('td:nth-child(2)').text().trim();
                    if (username) {
                        uniqueUsernames.add(username);
                    }
                });

                const totalRows = uniqueUsernames.size;
                $('#totalRows').text(totalRows);
                $('#rowsPerPage').text(Math.min(totalRows, parseInt($('#rowsPerPageSelect').val()) || 10));
                $('#currentPage').text('1');

                // Update pagination controls
                updatePaginationControls(totalRows);
            });

            // Run initial filter
            filterTable();

            // Initialize Select2 for department-dropdown (edit modal)
            $('#department-dropdown').select2({
                placeholder: 'Select Role',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#add-department-role-modal .modal-body')
            }).on('select2:select', function(e) {
                // Get the selected role ID
                const roleId = parseInt($(this).val());
                if (roleId) {
                    // Get the role object
                    const role = rolesData.find(r => r.id === roleId);
                    if (role) {
                        // Add the role to the table
                        const tbody = $('#assigned-roles-table tbody');

                        // Check if this role is already selected
                        const alreadySelected = tbody.find(`tr[data-id="${roleId}"]`).length > 0;

                        if (!alreadySelected) {
                            // Create the table row
                            const tr = $(`
                                <tr data-id="${role.id}">
                                    <td>${role.role_name}</td>
                                    <td class="text-end">
                                        <button class="btn-outline-danger delete-btn" data-role-id="${role.id}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `);

                            // Add to table
                            tbody.append(tr);

                            // Add click handler for delete button
                            tr.find('.delete-btn').on('click', function() {
                                // Remove from the selectedRoles array
                                window.selectedRoles = window.selectedRoles.filter(r => r.id !== role.id);
                                tr.remove();
                            });

                            // Add to the selectedRoles array
                            if (!window.selectedRoles) window.selectedRoles = [];
                            window.selectedRoles.push(role);
                        }
                    }
                }

                // Reset the select
                $(this).val(null).trigger('change');
            });
        });
    </script>
</body>

</html>