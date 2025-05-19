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

// Query active users
$stmt = $pdo->query("SELECT id, username, email, first_name, last_name, date_created, status FROM users WHERE is_disabled = 0");
$usersData = $stmt->fetchAll();

// Query active roles
$stmt = $pdo->query("SELECT id, role_name FROM roles WHERE is_disabled = 0");
$rolesData = $stmt->fetchAll();

// Query all departments
$stmt = $pdo->query("SELECT id, department_name, abbreviation FROM departments WHERE is_disabled = 0 ORDER BY department_name");
$departmentsData = $stmt->fetchAll();
// Fetch all user–department–role triples
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

    // Use a special key format for null roles
    $key = $roleId !== null ? "{$userId}-{$roleId}" : "{$userId}-null";

    if (!isset($userRoleMap[$key])) {
        $userRoleMap[$key] = [
            'userId'        => $userId,
            'roleId'        => $roleId,
            'departmentIds' => [],
        ];
    }

    // avoid dupes if you need:
    if (!in_array($deptId, $userRoleMap[$key]['departmentIds'], true)) {
        $userRoleMap[$key]['departmentIds'][] = $deptId;
    }
}

// Re-index for numeric array
$userRoleDepartments = array_values($userRoleMap);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/user_module.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <title>User Roles Management</title>
</head>

<body>
    <div class="main-content container-fluid">
        <header>
            <h1>USER ROLES MANAGER</h1>
        </header>

        <div class="filters-container">
            <div class="search-filter">
                <label for="search-users">SEARCH FOR USERS</label>
                <input type="text" id="search-users" placeholder="Search user...">
            </div>
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
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="button" id="clear-filters-btn">Clear Filters</button>
            </div>
            <div class="action-buttons">
                <?php if ($canCreate): ?>
                    <button type="button" id="create-btn" class="btn btn-primary">Add user to role</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Table body will be built via JavaScript -->
        <div class="table-responsive" id="table">
            <table class="table table-striped table-hover" id="urTable">
                <thead>
                    <tr>
                        <!-- Added checkbox column header with "select all" -->
                        <th><?php if ($canRemove): ?><input type="checkbox" id="select-all"><?php endif; ?></th>
                        <th>User <span class="sort-icon" id="sort-user">A→Z</span></th>
                        <th>Departments</th>
                        <th>Roles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Table rows will be dynamically populated via JavaScript -->
                </tbody>
            </table>

            <!-- Bulk Delete Button (initially hidden) -->
            <?php if ($canRemove): ?>
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
    </div>


    <!-- Add User to Roles Modal -->
    <div id="add-user-roles-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add User to Roles</h2>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="search-department-dropdown">Select Department <span class="text-danger">*</span></label>
                    <select id="search-department-dropdown" class="form-control">
                        <option value="">Select one department</option>
                        <?php foreach ($departmentsData as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Department is required</small>
                </div>
                <div class="form-group">
                    <label>Selected Department</label>
                    <div id="selected-department-container"></div>
                </div>
                <div class="form-group">
                    <label for="search-role-dropdown">Search Role/s (optional)</label>
                    <select id="search-role-dropdown" class="form-control">
                        <option value="">Select roles</option>
                        <?php foreach ($rolesData as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Leave empty for assignments without roles</small>
                </div>
                <div class="form-group">
                    <label>Current Role Selection</label>
                    <div id="selected-roles-container"></div>
                </div>
                <div class="form-group">
                    <label for="search-users-dropdown">Search User/s</label>
                    <select id="search-users-dropdown" class="form-control">
                        <option value="">Select users</option>
                        <?php foreach ($usersData as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Current User Selection</label>
                    <div id="selected-users-container"></div>
                </div>
                <div class="form-group">
                    <label>List of Current Users</label>
                    <div class="department-table-container">
                        <table id="current-users-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Optionally pre-populate if needed -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="close-user-roles-modal" class="btn btn-secondary">Cancel</button>
                <button id="save-user-roles" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <!-- Add Department to Role Modal -->
    <div id="add-department-role-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add Role to Department</h2>
            </div>
            <div class="modal-body">
                <!-- Add user and department info sections at the top -->
                <div class="form-group">
                    <label>User</label>
                    <div id="edit-user-info" class="info-field"></div>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <div id="edit-department-info" class="info-field"></div>
                </div>
                <div class="form-group">
                    <label>Add Role to Department (optional)</label>
                    <select id="department-dropdown" class="form-control">
                        <option value="">Select role</option>
                        <?php foreach ($rolesData as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">You can save without adding any roles</small>
                </div>
                <div class="form-group">
                    <label>Added Roles</label>
                    <div id="added-departments-container"></div>
                </div>
                <div class="form-group">
                    <label>List of Roles</label>
                    <div class="department-table-container">
                        <table id="departments-table">
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th></th>
                                </tr>
                            </thead>
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
            </div>
            <div class="modal-footer">
                <button id="close-department-role-modal" class="btn btn-secondary">Cancel</button>
                <button id="save-department-role" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
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
            canDelete: <?php echo json_encode($canRemove); ?>,
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

                minimumResultsForSearch: 5,
                dropdownParent: $('body') // Attach to body for proper z-index handling
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
                dropdownParent: $('#add-user-roles-modal .modal-body')
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

                console.log('Filtering with:', {
                    userSearch,
                    roleFilter,
                    roleFilterName,
                    deptFilter
                });

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

                // Update the visibility count
                const visibleCount = $('#urTable tbody tr:visible').length;
                const totalCount = $('#urTable tbody tr').length;

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

                // Update pagination controls if available
                if (typeof updatePaginationControls === 'function') {
                    updatePaginationControls(visibleCount);
                }

                console.log(`Showing ${visibleCount} of ${totalCount} rows`);
            }

            // Bind to search input with debounce for performance
            let searchTimer;
            $('#search-users').on('input', function() {x    
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

                // Update counts
                const totalRows = $('#urTable tbody tr').length;
                $('#totalRows').text(totalRows);
                $('#rowsPerPage').text(Math.min(totalRows, parseInt($('#rowsPerPageSelect').val()) || 10));
                $('#currentPage').text('1');

                console.log('Filters cleared');
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
                        // Add the role to the selection
                        const container = document.getElementById('added-departments-container');
                        
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
                                <button class="remove-btn" data-id="${role.id}" data-type="role_for_dept">✕</button>
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
        });
    </script>
</body>

</html>