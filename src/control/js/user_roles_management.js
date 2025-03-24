document.addEventListener('DOMContentLoaded', function() {

    // DOM elements
    const addUserRoleBtn = document.getElementById('create-btn');
    const userRolesTable = document.getElementById('urTable');
    const searchUsersInput = document.getElementById('search-users');
    const searchRolesInput = document.getElementById('search-filters');
    const filterDropdown = document.getElementById('filter-dropdown');

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
    // Variable to store assignment info pending deletion.
    let pendingDelete = null;

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
        const tbody = $('#urTable tbody');
        tbody.empty();
        let filteredUsers = usersData.filter(user => {
            if (filterUserId && !user.username.toLowerCase().includes(filterUserId.toLowerCase())) {
                return false;
            }
            return true;
        });
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
            if ((filterRoleName || filterDepartmentId) && assignments.length === 0) {
                return;
            }
            if (assignments.length === 0) {
                const tr = $(`
                  <tr>
                    <td><input type="checkbox" class="select-row" value="${user.id}"></td>
                    <td>${user.username}</td>
                    <td></td>
                    <td></td>
                    <td><button class="add-role-btn" data-user-id="${user.id}">Add Role</button></td>
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
                            <td rowspan="${assignments.length}"><input type="checkbox" class="select-row" value="${user.id}"></td>
                            <td rowspan="${assignments.length}">${user.username}</td>
                            <td>${role.role_name}</td>
                            <td>${departmentNames}</td>
                            <td>
                              <button class="edit-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">✏️</button>
                              <button class="delete-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">🗑️</button>
                            </td>
                          </tr>
                        `);
                    } else {
                        tr = $(`
                          <tr>
                            <td>${role.role_name}</td>
                            <td>${departmentNames}</td>
                            <td>
                              <button class="edit-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">✏️</button>
                              <button class="delete-btn" data-user-id="${user.id}" data-role-id="${assignment.roleId}">🗑️</button>
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
                    <button class="empty-state-action" id="clear-filters-btn">Clear filters</button>
                  </div>
                </td>
              </tr>
            `);
            tbody.append(tr);
            $('#clear-filters-btn').click(function() {
                $('#search-users').val('');
                $('#search-filters').val('');
                $('#filter-dropdown').val('');
                renderUserRolesTable();
            });
        }
        addEventListenersToButtons();
        // Update the bulk delete button visibility whenever table is rendered.
        toggleBulkDeleteButton();
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
                // Instead of a simple confirm(), show the custom delete modal.
                pendingDelete = { userId, roleId };
                deleteConfirmModal.style.display = 'block';
            });
        });
        document.querySelectorAll('.add-role-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = parseInt(this.dataset.userId);
                addUserRolesModal.style.display = 'block';
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

    // Delete confirmation modal handlers
    document.getElementById('cancel-delete-btn').addEventListener('click', function() {
        pendingDelete = null;
        deleteConfirmModal.style.display = 'none';
    });
    document.getElementById('confirm-delete-btn').addEventListener('click', function() {
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
                        renderUserRolesTable();
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
                    deleteConfirmModal.style.display = 'none';
                });
        }
    });

    // Close delete modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === deleteConfirmModal) {
            deleteConfirmModal.style.display = 'none';
        }
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
            deleteConfirmModal.style.display = 'none';
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

    function toggleBulkDeleteButton() {
        const selectedCount = $('.select-row:checked').length;
        if (selectedCount >= 2) {
            $("#delete-selected").show().prop("disabled", false);
        } else {
            $("#delete-selected").hide().prop("disabled", true);
        }
    }

    // Listen for changes on any checkbox in the table
    $(document).on('change', '.select-row', function () {
        toggleBulkDeleteButton();
    });

    // Bulk delete event handler
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
                        renderUserRolesTable();
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

    // "Select All" checkbox functionality (placed after table render)
    $("#select-all").on("change", function () {
        $(".select-row").prop("checked", this.checked);
        toggleBulkDeleteButton();
    });

    // Initial render of the table
    renderUserRolesTable();

});