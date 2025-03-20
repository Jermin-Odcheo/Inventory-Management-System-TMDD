<?php
// user_roles_management.php
session_start();
// Include configuration (assumes config.php defines a PDO instance in $pdo)
require_once('../../../../../config/ims-tmdd.php');
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';


// Note: This main file does not include header/sidebar/footer to avoid extra HTML
// that would break JSON responses from AJAX endpoints. Instead, you can include them
// around the main content if needed (but not in the endpoint files).

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
        'userId'        => $userId,
        'roleId'        => $roleId,
        'departmentIds' => $departments
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- BASE_URL is assumed to be defined in your config -->
    <link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>src/view/styles/css/user_roles_management.css?ref=v1">
    <title>User Roles Management</title>
</head>
<body>
<div class="main-content container-fluid">
    <header>
        <h1>USER ROLES MANAGER</h1>
        <div class="search-container">
            <input type="text" id="search-users" placeholder="search user">
        </div>
    </header>
    <div class="filters-container">
        <div class="search-role">
            <label for="search-roles">search for role</label>
            <input type="text" id="search-roles">
        </div>
        <div class="filter-container">
            <label for="filter-dropdown">filter</label>
            <select id="filter-dropdown">
                <option value="">All</option>
                <?php foreach($departmentsData as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="action-buttons">
            <button id="add-user-role-btn">add user to role</button>
        </div>
    </div>
    <!-- Table body will be built via JavaScript -->
    <table id="user-roles-table">
        <thead>
        <tr>
            <th>User</th>
            <th>Role</th>
            <th>Departments</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
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
                    <?php foreach($rolesData as $role): ?>
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
                    <?php foreach($usersData as $user): ?>
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
                    <?php foreach($departmentsData as $dept): ?>
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
                    <?php foreach($departmentsData as $dept): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                            <td><button class="delete-btn" data-dept-id="<?php echo $dept['id']; ?>">🗑️</button></td>
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

<!-- Pass PHP data to JavaScript -->
<script>
    let usersData = <?php echo json_encode($usersData); ?>;
    let rolesData = <?php echo json_encode($rolesData); ?>;
    let departmentsData = <?php echo json_encode($departmentsData); ?>;
    let userRoleDepartments = <?php echo json_encode($userRoleDepartments); ?>;
</script>

<!-- Inline Toast.js Code -->
<script>
    // Ultra-modern toast notification system with enhanced animations
    function showToast(message, type = 'info', duration = 5000, title = null) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `custom-toast toast-${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');
        const header = document.createElement('div');
        header.className = 'toast-header';
        const icon = document.createElement('div');
        icon.className = 'toast-icon';
        let iconContent = '';
        switch(type) {
            case 'success': iconContent = '✓'; break;
            case 'error': iconContent = '×'; break;
            case 'warning': iconContent = '!'; break;
            case 'info': default: iconContent = 'i'; break;
        }
        icon.textContent = iconContent;
        header.appendChild(icon);
        const titleElement = document.createElement('h5');
        titleElement.className = 'toast-title';
        if (title) {
            titleElement.textContent = title;
        } else {
            switch(type) {
                case 'success': titleElement.textContent = 'Success'; break;
                case 'error': titleElement.textContent = 'Error'; break;
                case 'warning': titleElement.textContent = 'Warning'; break;
                case 'info': default: titleElement.textContent = 'Information'; break;
            }
        }
        header.appendChild(titleElement);
        const closeBtn = document.createElement('button');
        closeBtn.className = 'toast-close';
        closeBtn.innerHTML = '×';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.onclick = function(event) {
            event.stopPropagation();
            dismissToast(toast);
        };
        header.appendChild(closeBtn);
        toast.appendChild(header);
        const body = document.createElement('div');
        body.className = 'toast-body';
        if (message.includes('<') && message.includes('>')) {
            body.innerHTML = message;
        } else {
            body.textContent = message;
        }
        toast.appendChild(body);
        const progress = document.createElement('div');
        progress.className = 'toast-progress';
        if (duration > 0) {
            progress.style.animation = `countdown ${duration}ms linear forwards`;
        }
        toast.appendChild(progress);
        const delay = Math.min(container.children.length * 100, 300);
        setTimeout(() => {
            container.appendChild(toast);
            void toast.offsetWidth;
            toast.classList.add('show');
            toast.classList.add('new-toast');
            setTimeout(() => { toast.classList.remove('new-toast'); }, 500);
            if (duration > 0) {
                const dismissTimeout = setTimeout(() => { dismissToast(toast); }, duration);
                toast._dismissTimeout = dismissTimeout;
            }
            toast.addEventListener('click', function(event) {
                if (event.target !== closeBtn && !closeBtn.contains(event.target)) {
                    // Optional: custom action on toast click
                }
            });
        }, delay);
        return toast;
    }

    function dismissToast(toast) {
        if (toast._dismissTimeout) {
            clearTimeout(toast._dismissTimeout);
        }
        toast.classList.add('hide');
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
                const container = document.getElementById('toastContainer');
                if (container && container.children.length === 0) {
                    document.body.removeChild(container);
                }
            }
        }, 600);
    }

    const Toast = {
        success: function(message, duration = 5000, title = null) {
            return showToast(message, 'success', duration, title);
        },
        error: function(message, duration = 5000, title = null) {
            return showToast(message, 'error', duration, title);
        },
        warning: function(message, duration = 5000, title = null) {
            return showToast(message, 'warning', duration, title);
        },
        info: function(message, duration = 5000, title = null) {
            return showToast(message, 'info', duration, title);
        }
    };

    function dismissAllToasts() {
        const container = document.getElementById('toastContainer');
        if (container) {
            const toasts = container.querySelectorAll('.custom-toast');
            toasts.forEach(toast => { dismissToast(toast); });
        }
    }
</script>

<!-- Main JavaScript Code -->
<script>
    document.addEventListener('DOMContentLoaded', function() {

        // DOM elements
        const addUserRoleBtn = document.getElementById('add-user-role-btn');
        const userRolesTable = document.getElementById('user-roles-table');
        const searchUsersInput = document.getElementById('search-users');
        const searchRolesInput = document.getElementById('search-roles');
        const filterDropdown = document.getElementById('filter-dropdown');

        // Modal elements
        const addUserRolesModal = document.getElementById('add-user-roles-modal');
        const addDepartmentRoleModal = document.getElementById('add-department-role-modal');
        const closeUserRolesModal = document.getElementById('close-user-roles-modal');
        const closeDepartmentRoleModal = document.getElementById('close-department-role-modal');
        const saveUserRolesBtn = document.getElementById('save-user-roles');
        const saveDepartmentRoleBtn = document.getElementById('save-department-role');

        // Dropdowns
        const searchRoleDropdown = document.getElementById('search-role-dropdown');
        const searchUsersDropdown = document.getElementById('search-users-dropdown');
        const departmentDropdown = document.getElementById('department-dropdown');

        // Selected containers
        const selectedRolesContainer = document.getElementById('selected-roles-container');
        const selectedUsersContainer = document.getElementById('selected-users-container');
        const addedDepartmentsContainer = document.getElementById('added-departments-container');

        // State management
        let selectedRoles = [];
        let selectedUsers = [];
        let selectedDepartments = [];
        let currentEditingData = null;

        // Render user roles table using all active users.
        function renderUserRolesTable(filterUserId = null, filterRoleName = null, filterDepartmentId = null) {
            const tbody = userRolesTable.querySelector('tbody');
            tbody.innerHTML = '';
            // Filter active users based on username
            let filteredUsers = usersData.filter(user => {
                if (filterUserId && !user.username.toLowerCase().includes(filterUserId.toLowerCase())) {
                    return false;
                }
                return true;
            });
            // For each user, find assignments (if any)
            filteredUsers.forEach(user => {
                let assignments = userRoleDepartments.filter(assignment => assignment.userId === user.id);
                if (filterRoleName) {
                    assignments = assignments.filter(assignment => {
                        const role = getRoleById(assignment.roleId);
                        return role && role.role_name.toLowerCase().includes(filterRoleName.toLowerCase());
                    });
                }
                if (filterDepartmentId) {
                    assignments = assignments.filter(assignment =>
                        assignment.departmentIds.includes(parseInt(filterDepartmentId))
                    );
                }
                // If role/department filters are active and no assignments match, skip this user.
                if ((filterRoleName || filterDepartmentId) && assignments.length === 0) {
                    return;
                }
                if (assignments.length === 0) {
                    // No assignments: show one row with empty Role/Department cells and an Add Role button.
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                  <td>${user.username}</td>
                  <td></td>
                  <td></td>
                  <td>
                      <button class="add-role-btn" data-user-id="${user.id}">Add Role</button>
                  </td>
              `;
                    tbody.appendChild(tr);
                } else {
                    // For users with assignments, group rows.
                    assignments.forEach((assignment, index) => {
                        const role = getRoleById(assignment.roleId);
                        const departmentNames = assignment.departmentIds.map(deptId => {
                            const dept = getDepartmentById(deptId);
                            return dept ? dept.department_name : 'Unknown';
                        }).join(', ');
                        const tr = document.createElement('tr');
                        if (index === 0) {
                            tr.innerHTML = `
                          <td rowspan="${assignments.length}">${user.username}</td>
                          <td>${role.role_name}</td>
                          <td>${departmentNames}</td>
                          <td>
                              <button class="edit-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">✏️</button>
                              <button class="delete-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">🗑️</button>
                          </td>
                      `;
                        } else {
                            tr.innerHTML = `
                          <td>${role.role_name}</td>
                          <td>${departmentNames}</td>
                          <td>
                              <button class="edit-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">✏️</button>
                              <button class="delete-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">🗑️</button>
                          </td>
                      `;
                        }
                        tbody.appendChild(tr);
                    });
                }
            });
            if (tbody.innerHTML.trim() === '') {
                const tr = document.createElement('tr');
                tr.innerHTML = `
              <td colspan="4">
                  <div class="empty-state">
                      <div class="empty-state-icon">🔍</div>
                      <div class="empty-state-message">No matching user roles found</div>
                      <button class="empty-state-action" id="clear-filters-btn">Clear filters</button>
                  </div>
              </td>
          `;
                tbody.appendChild(tr);
                document.getElementById('clear-filters-btn').addEventListener('click', () => {
                    searchUsersInput.value = '';
                    searchRolesInput.value = '';
                    filterDropdown.value = '';
                    renderUserRolesTable();
                });
            }
            addEventListenersToButtons();
        }

        function getUserById(id) {
            return usersData.find(user => user.id === id) || { username: 'Unknown User' };
        }

        function getRoleById(id) {
            return rolesData.find(role => role.id === id) || { role_name: 'Unknown Role' };
        }

        function getDepartmentById(id) {
            return departmentsData.find(dept => dept.id === id) || { department_name: 'Unknown Dept' };
        }

        // Handling selection in modals
        function addItemToSelection(containerId, item, type) {
            const container = document.getElementById(containerId);
            if (containerId === 'selected-roles-container' && selectedRoles.some(r => r.id === item.id)) return;
            if (containerId === 'selected-users-container' && selectedUsers.some(u => u.id === item.id)) return;
            if (containerId === 'added-departments-container' && selectedDepartments.some(d => d.id === item.id)) return;
            if (type === 'role') selectedRoles.push(item);
            if (type === 'user') selectedUsers.push(item);
            if (type === 'department') selectedDepartments.push(item);
            const selectedItem = document.createElement('span');
            selectedItem.className = 'selected-item';
            selectedItem.dataset.id = item.id;
            selectedItem.innerHTML = `
          ${item.role_name || item.username || item.department_name}
          <button class="remove-btn" data-id="${item.id}" data-type="${type}">✕</button>
      `;
            container.appendChild(selectedItem);
            selectedItem.querySelector('.remove-btn').addEventListener('click', function() {
                if (type === 'role') selectedRoles = selectedRoles.filter(r => r.id !== item.id);
                if (type === 'user') selectedUsers = selectedUsers.filter(u => u.id !== item.id);
                if (type === 'department') selectedDepartments = selectedDepartments.filter(d => d.id !== item.id);
                selectedItem.remove();
            });
        }

        // Event listeners for dynamically added buttons
        function addEventListenersToButtons() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = parseInt(this.dataset.userId);
                    const roleId = parseInt(this.dataset.roleId);
                    const assignment = userRoleDepartments.find(a => a.userId === userId && a.roleId === roleId);
                    if (assignment) {
                        currentEditingData = {
                            userId: assignment.userId,
                            roleId: assignment.roleId,
                            originalDeptIds: [...assignment.departmentIds]
                        };
                        const modalTitle = addDepartmentRoleModal.querySelector('h2');
                        const roleTitle = addDepartmentRoleModal.querySelector('h3');
                        const user = getUserById(userId);
                        const role = getRoleById(roleId);
                        modalTitle.textContent = `Edit departments for ${user.username}`;
                        roleTitle.textContent = role.role_name.toUpperCase();
                        addedDepartmentsContainer.innerHTML = '';
                        selectedDepartments = [];
                        assignment.departmentIds.forEach(deptId => {
                            const dept = getDepartmentById(deptId);
                            addItemToSelection('added-departments-container', dept, 'department');
                        });
                        addDepartmentRoleModal.style.display = 'block';
                    }
                });
            });
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = parseInt(this.dataset.userId);
                    const roleId = parseInt(this.dataset.roleId);
                    if (confirm('Are you sure you want to delete this role assignment?')) {
                        // Send AJAX request to delete assignment from the database
                        fetch('delete_user_role.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ userId, roleId })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    userRoleDepartments = userRoleDepartments.filter(a => !(a.userId === userId && a.roleId === roleId));
                                    renderUserRolesTable();
                                    Toast.success('Role assignment has been removed successfully', 5000, 'Deleted');
                                } else {
                                    Toast.error('Failed to delete assignment', 5000, 'Error');
                                }
                            })
                            .catch(error => {
                                console.error(error);
                                Toast.error('Error deleting assignment', 5000, 'Error');
                            });
                    }
                });
            });
            document.querySelectorAll('.add-role-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = parseInt(this.dataset.userId);
                    addUserRolesModal.style.display = 'block';
                    // Optionally, store userId for the new assignment
                });
            });
        }

        // Modal selection handlers
        searchRoleDropdown.addEventListener('change', function() {
            const roleId = parseInt(this.value);
            if (roleId) {
                const role = getRoleById(roleId);
                addItemToSelection('selected-roles-container', role, 'role');
                this.value = '';
            }
        });

        searchUsersDropdown.addEventListener('change', function() {
            const userId = parseInt(this.value);
            if (userId) {
                const user = getUserById(userId);
                addItemToSelection('selected-users-container', user, 'user');
                this.value = '';
            }
        });

        departmentDropdown.addEventListener('change', function() {
            const deptId = parseInt(this.value);
            if (deptId) {
                const dept = getDepartmentById(deptId);
                addItemToSelection('added-departments-container', dept, 'department');
                this.value = '';
            }
        });

        // Filter handlers
        searchUsersInput.addEventListener('input', function() {
            const filterUserId = this.value;
            const filterRoleName = searchRolesInput.value;
            const filterDepartmentId = filterDropdown.value;
            renderUserRolesTable(filterUserId, filterRoleName, filterDepartmentId);
        });

        searchRolesInput.addEventListener('input', function() {
            const filterUserId = searchUsersInput.value;
            const filterRoleName = this.value;
            const filterDepartmentId = filterDropdown.value;
            renderUserRolesTable(filterUserId, filterRoleName, filterDepartmentId);
        });

        filterDropdown.addEventListener('change', function() {
            const filterUserId = searchUsersInput.value;
            const filterRoleName = searchRolesInput.value;
            const filterDepartmentId = this.value;
            renderUserRolesTable(filterUserId, filterRoleName, filterDepartmentId);
        });

        // Modal open/close handlers
        addUserRoleBtn.addEventListener('click', function() {
            selectedRoles = [];
            selectedUsers = [];
            selectedRolesContainer.innerHTML = '';
            selectedUsersContainer.innerHTML = '';
            addUserRolesModal.style.display = 'block';
        });

        closeUserRolesModal.addEventListener('click', function() {
            addUserRolesModal.style.display = 'none';
        });

        closeDepartmentRoleModal.addEventListener('click', function() {
            addDepartmentRoleModal.style.display = 'none';
        });

        // Save handlers
        saveUserRolesBtn.addEventListener('click', function() {
            if (selectedUsers.length === 0 || selectedRoles.length === 0) {
                Toast.error('Please select at least one user and one role', 5000, 'Validation Error');
                return;
            }
            let newAssignments = [];
            selectedUsers.forEach(user => {
                selectedRoles.forEach(role => {
                    const exists = userRoleDepartments.find(a => a.userId === user.id && a.roleId === role.id);
                    if (!exists) {
                        newAssignments.push({
                            userId: user.id,
                            roleId: role.id,
                            departmentIds: []
                        });
                    }
                });
            });
            // Send AJAX request to save new assignments to the database
            fetch('save_user_role.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(newAssignments)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userRoleDepartments = [...userRoleDepartments, ...newAssignments];
                        addUserRolesModal.style.display = 'none';
                        renderUserRolesTable();
                        Toast.success(`${newAssignments.length} new role assignments added`, 5000, 'Success');
                    } else {
                        Toast.error('Failed to save assignments', 5000, 'Error');
                    }
                })
                .catch(error => {
                    console.error(error);
                    Toast.error('Error saving assignments', 5000, 'Error');
                });
        });

        saveDepartmentRoleBtn.addEventListener('click', function() {
            if (selectedDepartments.length === 0) {
                Toast.error('Please select at least one department', 5000, 'Validation Error');
                return;
            }
            if (currentEditingData) {
                const index = userRoleDepartments.findIndex(a => a.userId === currentEditingData.userId && a.roleId === currentEditingData.roleId);
                if (index !== -1) {
                    let updatedDepartments = selectedDepartments.map(dept => dept.id);
                    // Send AJAX request to update departments for the assignment
                    fetch('update_user_department.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            userId: currentEditingData.userId,
                            roleId: currentEditingData.roleId,
                            departmentIds: updatedDepartments
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                userRoleDepartments[index].departmentIds = updatedDepartments;
                                addDepartmentRoleModal.style.display = 'none';
                                renderUserRolesTable();
                                Toast.success('Departments updated successfully', 5000, 'Success');
                            } else {
                                Toast.error('Failed to update departments', 5000, 'Error');
                            }
                        })
                        .catch(error => {
                            console.error(error);
                            Toast.error('Error updating departments', 5000, 'Error');
                        });
                }
            }
        });

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === addUserRolesModal) {
                addUserRolesModal.style.display = 'none';
            }
            if (event.target === addDepartmentRoleModal) {
                addDepartmentRoleModal.style.display = 'none';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                addUserRolesModal.style.display = 'none';
                addDepartmentRoleModal.style.display = 'none';
            }
            if (event.ctrlKey && event.key === 'k') {
                event.preventDefault();
                searchUsersInput.focus();
            }
            if (event.ctrlKey && event.key === 'n') {
                event.preventDefault();
                addUserRoleBtn.click();
            }
        });

        // Initial render of the table
        renderUserRolesTable();
    });
</script>
</body>
</html>
