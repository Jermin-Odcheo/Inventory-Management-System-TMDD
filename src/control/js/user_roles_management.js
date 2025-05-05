document.addEventListener('DOMContentLoaded', function() {

    // DOM elements
    const addUserRoleBtn = document.getElementById('create-btn');
    const userRolesTable = document.getElementById('urTable');
    const searchUsersInput = document.getElementById('search-users');
    const roleFilterDropdown = document.getElementById('role-filter');
    const deptFilterDropdown = document.getElementById('dept-filter');
    const sortUserBtn = document.getElementById('sort-user');
    const clearFiltersBtn = document.getElementById('clear-filters-btn');

    // Modal elements
    const addUserRolesModal = document.getElementById('add-user-roles-modal');
    const addDepartmentRoleModal = document.getElementById('add-department-role-modal');
    const closeUserRolesModal = document.getElementById('close-user-roles-modal');
    const closeDepartmentRoleModal = document.getElementById('close-department-role-modal');
    const saveUserRolesBtn = document.getElementById('save-user-roles');
    const saveDepartmentRoleBtn = document.getElementById('save-department-role');

    // Delete confirmation modal elements
    const deleteConfirmModal = document.getElementById('delete-confirm-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    // Initialize the delete confirmation modal
    let deleteModal = null;
    if (deleteConfirmModal) {
        deleteModal = new bootstrap.Modal(deleteConfirmModal);
    }
    // Variable to store assignment info pending deletion.
    let pendingDelete = null;

    // Dropdowns
    const searchRoleDropdown = document.getElementById('search-role-dropdown');
    const searchUsersDropdown = document.getElementById('search-users-dropdown');
    const searchDepartmentDropdown = document.getElementById('search-department-dropdown');
    const departmentDropdown = document.getElementById('department-dropdown');

    // Selected containers
    const selectedRolesContainer = document.getElementById('selected-roles-container');
    const selectedUsersContainer = document.getElementById('selected-users-container');
    const selectedDepartmentContainer = document.getElementById('selected-department-container');
    const addedDepartmentsContainer = document.getElementById('added-departments-container');

    // State management
    let selectedRoles = [];
    let selectedUsers = [];
    let selectedDepartment = null; // Single department selection
    let currentEditingData = null;

    // Sorting state
    let userSortDirection = 'asc'; // 'asc' or 'desc'
    // Clear Filters Button Handler
if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', function() {
        // Clear all filter inputs
        if (searchUsersInput) searchUsersInput.value = '';
        if (roleFilterDropdown) roleFilterDropdown.value = '';
        if (deptFilterDropdown) deptFilterDropdown.value = '';
        
        // Reset sort direction to default
        userSortDirection = 'asc';
        if (sortUserBtn) sortUserBtn.innerHTML = 'A→Z';
        
        // Re-render table with no filters
        renderUserRolesTable(null, null, null, userSortDirection);
        
        // Show confirmation toast
        Toast.success('All filters cleared', 3000, 'Success');
    });
}

    // Render user roles table using all active users
    function renderUserRolesTable(filterUserId = null, filterRoleId = null, filterDeptId = null, sortDirection = null) {
        const tbody = $('#urTable tbody');
        tbody.empty();
        let filteredUsers = usersData.filter(user => {
            if (filterUserId && !user.username.toLowerCase().includes(filterUserId.toLowerCase())) {
                return false;
            }
            return true;
        });
        
        // Sort users if requested
        if (sortDirection === 'asc') {
            filteredUsers.sort((a, b) => a.username.localeCompare(b.username));
        } else if (sortDirection === 'desc') {
            filteredUsers.sort((a, b) => b.username.localeCompare(a.username));
        }
        
        filteredUsers.forEach(user => {
            let assignments = userRoleDepartments.filter(assignment => assignment.userId === user.id);
            
            if (filterRoleId) {
                assignments = assignments.filter(assignment => 
                    assignment.roleId === parseInt(filterRoleId)
                );
            }
            
            if (filterDeptId) {
                assignments = assignments.filter(assignment => 
                    assignment.departmentIds.includes(parseInt(filterDeptId))
                );
            }
            
            if ((filterRoleId || filterDeptId) && assignments.length === 0) {
                return;
            }
            
            if (assignments.length === 0) {
                const tr = $(`
                  <tr>
                    <td>${userPrivileges.canDelete ? '<input type="checkbox" class="select-row" value="' + user.id + '">' : ''}</td>
                    <td>${user.username}</td>
                    <td>-</td>
                    <td>-</td>
                    <td>
                      ${userPrivileges.canModify ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="0">
                        <i class="bi bi-pencil-square"></i>
                      </button>` : ''}
                      ${userPrivileges.canDelete ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="0">
                        <i class="bi bi-trash"></i>
                      </button>` : ''}
                    </td>
                  </tr>
                `);
                tbody.append(tr);
            } else {
                assignments.forEach((assignment, index) => {
                    const role = getRoleById(assignment.roleId);
                    const departmentNames = assignment.departmentIds.map(deptId => {
                        const dept = getDepartmentById(deptId);
                        return dept ? dept.department_name : 'Unknown';
                    }).join(', ');
                    let tr;
                    if (index === 0) {
                        tr = $(`
                          <tr>
                            <td rowspan="${assignments.length}">${userPrivileges.canDelete ? '<input type="checkbox" class="select-row" value="' + user.id + '">' : ''}</td>
                            <td rowspan="${assignments.length}">${user.username}</td>
                            <td>${departmentNames}</td>
                            <td>${role.role_name}</td>
                            <td>
                              ${userPrivileges.canModify ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">
                                <i class="bi bi-pencil-square"></i>
                              </button>` : ''}
                              ${userPrivileges.canDelete ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">
                                <i class="bi bi-trash"></i>
                              </button>` : ''}
                            </td>
                          </tr>
                        `);
                    } else {
                        tr = $(`
                          <tr>
                            <td>${departmentNames}</td>
                            <td>${role.role_name}</td>
                            <td>
                              ${userPrivileges.canModify ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">
                                <i class="bi bi-pencil-square"></i>
                              </button>` : ''}
                              ${userPrivileges.canDelete ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">
                                <i class="bi bi-trash"></i>
                              </button>` : ''}
                            </td>
                          </tr>
                        `);
                    }
                    tbody.append(tr);
                });
            }
        });
        if ($.trim(tbody.html()) === '') {
            const tr = $(`
              <tr>
                <td colspan="5">
                  <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <div class="empty-state-message">No matching user roles found</div>
                  </div>
                </td>
              </tr>
            `);
            tbody.append(tr);
            $('#clear-filters-btn').click(function() {
                $('#search-users').val('');
                $('#role-filter').val('');
                $('#dept-filter').val('');
                renderUserRolesTable(null, null, null, userSortDirection);
            });
        }
        addEventListenersToButtons();
        // Update the bulk delete button visibility whenever table is rendered.
        if (userPrivileges.canDelete) {
            toggleBulkDeleteButton();
        }
    }

    // Utility functions remain unchanged
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
        
        // For department, allow only one selection
        if (type === 'department') {
            // Clear previous selection
            container.innerHTML = '';
            selectedDepartment = item;
        } else if (type === 'role') {
            selectedRoles.push(item);
        } else if (type === 'user') {
            selectedUsers.push(item);
        } else if (type === 'role_for_dept') {
            selectedRoles.push(item);
        }
        
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
            if (type === 'department') selectedDepartment = null;
            if (type === 'role_for_dept') selectedRoles = selectedRoles.filter(r => r.id !== item.id);
            selectedItem.remove();
        });
    }

    // Event listeners for dynamically added buttons
    function addEventListenersToButtons() {
        if (userPrivileges.canModify) {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = parseInt(this.dataset.userId);
                    const roleId = parseInt(this.dataset.roleId);
                    
                    // If roleId is 0, it means user doesn't have a role yet, open the add user roles modal
                    if (roleId === 0) {
                        selectedRoles = [];
                        selectedUsers = [];
                        selectedRolesContainer.innerHTML = '';
                        selectedUsersContainer.innerHTML = '';
                        
                        // Pre-select the current user
                        const user = getUserById(userId);
                        if (user) {
                            addItemToSelection('selected-users-container', user, 'user');
                        }
                        
                        addUserRolesModal.style.display = 'block';
                        return;
                    }
                    
                    // Regular edit flow for users with roles
                    const assignment = userRoleDepartments.find(a => a.userId === userId && a.roleId === roleId);
                    if (assignment) {
                        currentEditingData = {
                            userId: assignment.userId,
                            roleId: assignment.roleId,
                            originalDeptIds: [...assignment.departmentIds]
                        };
                        const modalTitle = addDepartmentRoleModal.querySelector('h2');
                        const user = getUserById(userId);
                        
                        modalTitle.textContent = `Edit roles for ${user.username}`;
                        
                        document.getElementById('added-departments-container').innerHTML = '';
                        selectedRoles = [];
                        
                        // Add the current role
                        const role = getRoleById(roleId);
                        addItemToSelection('added-departments-container', role, 'role_for_dept');
                        
                        addDepartmentRoleModal.style.display = 'block';
                    }
                });
            });
        }
        
        if (userPrivileges.canDelete) {
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = parseInt(this.dataset.userId);
                    const roleId = parseInt(this.dataset.roleId);
                    
                    // Don't allow deletion if roleId is 0 (user has no roles yet)
                    if (roleId === 0) {
                        Toast.info('This user has no roles to delete', 5000, 'Info');
                        return;
                    }
                    
                    // Instead of a simple confirm(), show the custom delete modal.
                    pendingDelete = { userId, roleId };
                    // Show the pre-initialized modal
                    if (deleteModal) {
                        deleteModal.show();
                    }
                });
            });
        }
    }

    // Modal selection handlers
    if (searchRoleDropdown) {
        searchRoleDropdown.addEventListener('change', function() {
            const roleId = parseInt(this.value);
            if (roleId) {
                const role = getRoleById(roleId);
                addItemToSelection('selected-roles-container', role, 'role');
                this.value = '';
            }
        });
    }

    if (searchUsersDropdown) {
        searchUsersDropdown.addEventListener('change', function() {
            const userId = parseInt(this.value);
            if (userId) {
                const user = getUserById(userId);
                addItemToSelection('selected-users-container', user, 'user');
                this.value = '';
            }
        });
    }

    if (searchDepartmentDropdown) {
        searchDepartmentDropdown.addEventListener('change', function() {
            const deptId = parseInt(this.value);
            if (deptId) {
                const dept = getDepartmentById(deptId);
                addItemToSelection('selected-department-container', dept, 'department');
                this.value = '';
            }
        });
    }

    if (departmentDropdown) {
        departmentDropdown.addEventListener('change', function() {
            const roleId = parseInt(this.value);
            if (roleId) {
                const role = getRoleById(roleId);
                addItemToSelection('added-departments-container', role, 'role_for_dept');
                this.value = '';
            }
        });
    }

    // Filter handlers
    if (searchUsersInput) {
        searchUsersInput.addEventListener('input', function() {
            const filterUserId = this.value;
            const filterRoleId = roleFilterDropdown ? roleFilterDropdown.value : null;
            const filterDeptId = deptFilterDropdown ? deptFilterDropdown.value : null;
            renderUserRolesTable(filterUserId, filterRoleId, filterDeptId, userSortDirection);
        });
    }

    if (roleFilterDropdown) {
        roleFilterDropdown.addEventListener('change', function() {
            const filterUserId = searchUsersInput ? searchUsersInput.value : null;
            const filterRoleId = this.value;
            const filterDeptId = deptFilterDropdown ? deptFilterDropdown.value : null;
            renderUserRolesTable(filterUserId, filterRoleId, filterDeptId, userSortDirection);
        });
    }

    if (deptFilterDropdown) {
        deptFilterDropdown.addEventListener('change', function() {
            const filterUserId = searchUsersInput ? searchUsersInput.value : null;
            const filterRoleId = roleFilterDropdown ? roleFilterDropdown.value : null;
            const filterDeptId = this.value;
            renderUserRolesTable(filterUserId, filterRoleId, filterDeptId, userSortDirection);
        });
    }
    
    // Sort users handler
    if (sortUserBtn) {
        sortUserBtn.addEventListener('click', function() {
            userSortDirection = userSortDirection === 'asc' ? 'desc' : 'asc';
            // Update the sort icon
            this.innerHTML = userSortDirection === 'asc' ? 'A→Z' : 'Z→A';
            
            const filterUserId = searchUsersInput ? searchUsersInput.value : null;
            const filterRoleId = roleFilterDropdown ? roleFilterDropdown.value : null;
            const filterDeptId = deptFilterDropdown ? deptFilterDropdown.value : null;
            renderUserRolesTable(filterUserId, filterRoleId, filterDeptId, userSortDirection);
        });
    }

    // Modal open/close handlers
    if (addUserRoleBtn && userPrivileges.canCreate) {
        addUserRoleBtn.addEventListener('click', function() {
            selectedRoles = [];
            selectedUsers = [];
            selectedDepartment = null;
            selectedRolesContainer.innerHTML = '';
            selectedUsersContainer.innerHTML = '';
            selectedDepartmentContainer.innerHTML = '';
            addUserRolesModal.style.display = 'block';
        });
    }

    if (closeUserRolesModal) {
        closeUserRolesModal.addEventListener('click', function() {
            addUserRolesModal.style.display = 'none';
        });
    }

    if (closeDepartmentRoleModal) {
        closeDepartmentRoleModal.addEventListener('click', function() {
            addDepartmentRoleModal.style.display = 'none';
        });
    }

    // Delete confirmation modal handlers
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function() {
            pendingDelete = null;
            // Use our pre-initialized modal
            if (deleteModal) {
                deleteModal.hide();
            }
        });
    }
    
    if (confirmDeleteBtn && userPrivileges.canDelete) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (pendingDelete) {
                const { userId, roleId } = pendingDelete;
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
                            renderUserRolesTable(null, null, null, userSortDirection);
                            Toast.success('Role assignment has been removed successfully', 5000, 'Deleted');
                        } else {
                            Toast.error('Failed to delete assignment', 5000, 'Error');
                        }
                    })
                    .catch(error => {
                        console.error(error);
                        Toast.error('Error deleting assignment', 5000, 'Error');
                    })
                    .finally(() => {
                        pendingDelete = null;
                        // Use our pre-initialized modal
                        if (deleteModal) {
                            deleteModal.hide();
                        }
                    });
            }
        });
    }

    // Save handlers
    if (saveUserRolesBtn && userPrivileges.canCreate) {
        saveUserRolesBtn.addEventListener('click', function() {
            if (selectedUsers.length === 0 || selectedRoles.length === 0 || !selectedDepartment) {
                Toast.error('Please select at least one user, one role, and a department', 5000, 'Validation Error');
                return;
            }
            
            let newAssignments = [];
            selectedUsers.forEach(user => {
                // Create entries with multiple roles but single department
                newAssignments.push({
                    userId: user.id,
                    roleIds: selectedRoles.map(role => role.id), // Send all role IDs as an array
                    departmentId: selectedDepartment.id // Single department ID
                });
            });

            // Pre-check for empty newAssignments
            if (newAssignments.length === 0) {
                Toast.error('No valid assignments to create', 5000, 'Error');
                return;
            }

            fetch('save_user_role.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(newAssignments)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the local data
                        if (data.assignments) {
                            // Use server response if available
                            userRoleDepartments = data.assignments;
                        } else {
                            // Otherwise create local entries for each role
                            newAssignments.forEach(assignment => {
                                assignment.roleIds.forEach(roleId => {
                                    userRoleDepartments.push({
                                        userId: assignment.userId,
                                        roleId: roleId,
                                        departmentIds: [assignment.departmentId]
                                    });
                                });
                            });
                        }
                        
                        addUserRolesModal.style.display = 'none';
                        renderUserRolesTable(null, null, null, userSortDirection);
                        Toast.success('New roles assigned successfully', 5000, 'Success');
                    } else {
                        // Display the error message from the server
                        Toast.error(data.error || 'Failed to save assignments', 5000, 'Error');
                    }
                })
                .catch(error => {
                    console.error(error);
                    Toast.error('Error saving assignments', 5000, 'Error');
                });
        });
    }

    if (saveDepartmentRoleBtn && userPrivileges.canModify) {
        saveDepartmentRoleBtn.addEventListener('click', function() {
            if (currentEditingData) {
                const index = userRoleDepartments.findIndex(a => a.userId === currentEditingData.userId && a.roleId === currentEditingData.roleId);
                if (index !== -1) {
                    let updatedRoles = selectedRoles.map(role => role.id);
                    fetch('update_user_department.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            userId: currentEditingData.userId,
                            oldRoleId: currentEditingData.roleId,
                            roleIds: updatedRoles,
                            departmentId: currentEditingData.originalDeptIds[0] // Keep the existing department
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update local data with server response
                                if (data.assignments) {
                                    userRoleDepartments = data.assignments;
                                } else {
                                    // Update the role ID if no server response
                                    if (updatedRoles.length > 0) {
                                        userRoleDepartments[index].roleId = updatedRoles[0];
                                    }
                                }
                                addDepartmentRoleModal.style.display = 'none';
                                renderUserRolesTable(null, null, null, userSortDirection);
                                Toast.success('Roles updated successfully', 5000, 'Success');
                            } else {
                                Toast.error('Failed to update roles', 5000, 'Error');
                            }
                        })
                        .catch(error => {
                            console.error(error);
                            Toast.error('Error updating roles', 5000, 'Error');
                        });
                }
            }
        });
    }

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
            // Use our pre-initialized modal
            if (deleteModal) {
                deleteModal.hide();
            }
        }
        if (event.ctrlKey && event.key === 'k') {
            event.preventDefault();
            searchUsersInput.focus();
        }
        if (userPrivileges.canCreate && event.ctrlKey && event.key === 'n') {
            event.preventDefault();
            addUserRoleBtn.click();
        }
    });

    function toggleBulkDeleteButton() {
        if (!userPrivileges.canDelete) return;
        
        const selectedCount = $('.select-row:checked').length;
        if (selectedCount >= 2) {
            $("#delete-selected").show().prop("disabled", false);
        } else {
            $("#delete-selected").hide().prop("disabled", true);
        }
    }

    // Listen for changes on any checkbox in the table
    if (userPrivileges.canDelete) {
        $(document).on('change', '.select-row', function () {
            toggleBulkDeleteButton();
        });
    }

    // Bulk delete event handler
    if ($("#delete-selected").length && userPrivileges.canDelete) {
        $("#delete-selected").click(function () {
            const selected = $(".select-row:checked").map(function () {
                return $(this).val();
            }).get();
            if (selected.length === 0) {
                showToast('Please select user roles to remove.', 'warning');
                return;
            }
            // Confirm bulk deletion
            if (confirm(`Are you sure you want to remove ${selected.length} selected user role(s)?`)) {
                $.ajax({
                    type: "POST",
                    url: "delete_user_role.php", // Adjust endpoint as needed
                    data: { user_ids: selected },
                    dataType: "json",
                    success: function (response) {
                        if (response.success) {
                            // Uncheck all checkboxes
                            $("#select-all").prop("checked", false);
                            $(".select-row").prop("checked", false);
                            toggleBulkDeleteButton();
                            // Reload or re-render table
                            renderUserRolesTable(null, null, null, userSortDirection);
                            showToast(response.message, 'success');
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error: function () {
                        showToast('Error removing selected user roles.', 'error');
                    }
                });
            }
        });
    }

    // "Select All" checkbox functionality (placed after table render)
    if ($("#select-all").length && userPrivileges.canDelete) {
        $("#select-all").on("change", function () {
            $(".select-row").prop("checked", this.checked);
            toggleBulkDeleteButton();
        });
    }

    // Initial render of the table
    renderUserRolesTable(null, null, null, userSortDirection);

});
