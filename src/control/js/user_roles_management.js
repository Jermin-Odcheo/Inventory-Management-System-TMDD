document.addEventListener("DOMContentLoaded", function () {
  // DOM elements
  const addUserRoleBtn = document.getElementById("create-btn");
  const userRolesTable = document.getElementById("urTable");
  const searchUsersInput = document.getElementById("search-users");
  const roleFilterDropdown = document.getElementById("role-filter");
  const deptFilterDropdown = document.getElementById("dept-filter");
  // const sortUserBtn = document.getElementById("sort-user"); // REMOVED: No longer needed for client-side sorting
  const clearFiltersBtn = document.getElementById("clear-filters-btn");

  // Modal elements
  const addUserRolesModal = document.getElementById("add-user-roles-modal");
  const addDepartmentRoleModal = document.getElementById(
    "add-department-role-modal"
  );
  const closeUserRolesModal = document.getElementById("close-user-roles-modal");
  const closeDepartmentRoleModal = document.getElementById(
    "close-department-role-modal"
  );
  const saveUserRolesBtn = document.getElementById("save-user-roles");
  const saveDepartmentRoleBtn = document.getElementById("save-department-role");

  // Delete confirmation modal elements
  const deleteConfirmModal = document.getElementById("delete-confirm-modal");
  const cancelDeleteBtn = document.getElementById("cancel-delete-btn");
  const confirmDeleteBtn = document.getElementById("confirm-delete-btn");
  // Initialize the delete confirmation modal
  let deleteModal = null;
  if (deleteConfirmModal) {
    deleteModal = new bootstrap.Modal(deleteConfirmModal);
  }
  // Variable to store assignment info pending deletion.
  let pendingDelete = null;

  // Dropdowns
  const searchRoleDropdown = document.getElementById("search-role-dropdown");
  const searchUsersDropdown = document.getElementById("search-users-dropdown");
  const searchDepartmentDropdown = document.getElementById(
    "search-department-dropdown"
  );
  const departmentDropdown = document.getElementById("department-dropdown");

  // Selected containers
  const selectedRolesContainer = document.getElementById(
    "selected-roles-container"
  );
  const selectedUsersContainer = document.getElementById(
    "selected-users-container"
  );
  const selectedDepartmentContainer = document.getElementById(
    "selected-department-container"
  );
  const addedDepartmentsContainer = document.getElementById(
    "added-departments-container"
  );

  // State management
  let selectedRoles = [];
  let selectedUsers = [];
  let selectedDepartment = null; // Single department selection
  let currentEditingData = null;

  // REMOVED: No longer needed for client-side sorting
  // let userSortDirection = "asc"; // 'asc' or 'desc'

  // --- START NEW SORTING IMPLEMENTATION (Client-Side Interaction) ---

  // Function to update URL with filter and sort parameters and reload the page
  function updateUrlAndReload() {
      const urlParams = new URLSearchParams(window.location.search);

      // Get current filter values
      const searchValue = searchUsersInput.value;
      const roleFilterValue = roleFilterDropdown.value;
      const deptFilterValue = deptFilterDropdown.value;

      // Set/update search parameter
      if (searchValue) {
          urlParams.set('search', encodeURIComponent(searchValue));
      } else {
          urlParams.delete('search');
      }

      // Set/update role filter parameter
      if (roleFilterValue) {
          urlParams.set('role', encodeURIComponent(roleFilterValue));
      } else {
          urlParams.delete('role');
      }

      // Set/update department filter parameter
      if (deptFilterValue) {
          urlParams.set('department', encodeURIComponent(deptFilterValue));
      } else {
          urlParams.delete('department');
      }

      // Construct the new URL and navigate
      window.location.href = window.location.pathname + '?' + urlParams.toString();
  }

  // Event listener for sort headers (delegated to document for dynamic content)
  $(document).on('click', '.sort-header', function(e) {
      e.preventDefault(); // Prevent default link behavior
      const sortBy = $(this).data('sort'); // Get the column to sort by (e.g., 'username', 'departments')
      const urlParams = new URLSearchParams(window.location.search); // Get current URL parameters

      let currentSortBy = urlParams.get('sort_by');
      let currentSortOrder = urlParams.get('sort_order');

      let newSortOrder = 'asc'; // Default new sort order

      // If clicking the same header, toggle sort order
      if (sortBy === currentSortBy) {
          newSortOrder = (currentSortOrder === 'asc') ? 'desc' : 'asc';
      }

      // Set the new sort parameters
      urlParams.set('sort_by', sortBy);
      urlParams.set('sort_order', newSortOrder);

      // Preserve existing filters (search, role, department)
      // This is crucial to prevent filters from being removed on sort
      const searchValue = searchUsersInput.value;
      const roleFilterValue = roleFilterDropdown.value;
      const deptFilterValue = deptFilterDropdown.value;

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

      // Reload the page with the new URL parameters
      window.location.href = window.location.pathname + '?' + urlParams.toString();
  });

  // Function to update sort icons (up/down arrows) based on current URL parameters
  function updateSortIcons() {
      const urlParams = new URLSearchParams(window.location.search);
      // Get current sort state, default to 'username' ascending if not set
      const activeSortBy = urlParams.get('sort_by') || 'username';
      const activeSortOrder = urlParams.get('sort_order') || 'asc';

      // Remove all sort icons from all headers first
      $('.sort-icon').removeClass('bi-caret-up-fill bi-caret-down-fill');

      // Find the active sort header and add the correct icon
      const activeHeader = $(`th a.sort-header[data-sort="${activeSortBy}"]`);
      if (activeHeader.length) {
          const icon = activeHeader.find('.sort-icon');
          if (activeSortOrder === 'asc') {
              icon.addClass('bi-caret-up-fill'); // Set to up arrow for ascending
          } else {
              icon.addClass('bi-caret-down-fill'); // Set to down arrow for descending
          }
      }
  }

  // --- END NEW SORTING IMPLEMENTATION (Client-Side Interaction) ---

  // Clear Filters Button Handler
  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener("click", function () {
      // Clear all filter inputs
      if (searchUsersInput) searchUsersInput.value = "";
      if (roleFilterDropdown) roleFilterDropdown.value = "";
      if (deptFilterDropdown) deptFilterDropdown.value = "";

      // Reload the page without any filter or sort parameters
      window.location.href = window.location.pathname;

      // Show confirmation toast
      Toast.success("All filters cleared", 3000, "Success");
    });
  }

  // Render user roles table using data passed from PHP (already sorted by PHP)
  function renderUserRolesTable() {
    const tbody = $("#urTable tbody");
    tbody.empty();

    // Get current filter values from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const urlSearch = urlParams.get('search');
    const urlRole = urlParams.get('role');
    const urlDepartment = urlParams.get('department');

    // Filter usersData (which is already sorted by PHP) based on URL filters
    let filteredUsers = usersData.filter((user) => {
        let matchesSearch = true;
        if (urlSearch) {
            const searchLower = urlSearch.toLowerCase();
            
            // Check username, email, first_name, last_name
            matchesSearch = user.username.toLowerCase().includes(searchLower) ||
                            user.email.toLowerCase().includes(searchLower) ||
                            (user.first_name && user.first_name.toLowerCase().includes(searchLower)) ||
                            (user.last_name && user.last_name.toLowerCase().includes(searchLower));
                            
            // If no match yet, also check departments_concat and roles_concat fields
            if (!matchesSearch && user.departments_concat) {
                matchesSearch = user.departments_concat.toLowerCase().includes(searchLower);
            }
            
            if (!matchesSearch && user.roles_concat) {
                matchesSearch = user.roles_concat.toLowerCase().includes(searchLower);
            }
        }

        if (!matchesSearch) return false;

        let matchesRole = true;
        if (urlRole) {
            // Check if this user has any assignment with the selected role ID
            matchesRole = userRoleDepartments.some(assignment =>
                assignment.userId === user.id && assignment.roleId === parseInt(urlRole)
            );
        }
        if (!matchesRole) return false;

        let matchesDepartment = true;
        if (urlDepartment) {
            // Extract department name without the abbreviation
            const deptNameToMatch = urlDepartment.replace(/^\([^)]+\)\s*/, '').trim();
            
            // Check if this user has any assignment in the selected department name
            matchesDepartment = userRoleDepartments.some(assignment =>
                assignment.userId === user.id && assignment.departmentIds.some(deptId => {
                    const dept = getDepartmentById(deptId);
                    // More flexible department matching
                    return dept && (
                        dept.department_name === deptNameToMatch || 
                        dept.department_name.toLowerCase() === deptNameToMatch.toLowerCase()
                    );
                })
            );
            
            // If still no match, try checking the departments_concat field directly
            if (!matchesDepartment && user.departments_concat) {
                const deptParts = user.departments_concat.toLowerCase().split(',').map(d => d.trim());
                matchesDepartment = deptParts.some(d => d === deptNameToMatch.toLowerCase());
            }
        }
        if (!matchesDepartment) return false;

        return true;
    });

    // Track the number of unique users displayed for pagination
    const uniqueUsernames = new Set();

    filteredUsers.forEach((user) => {
      // Find all assignments for this user
      let assignments = userRoleDepartments.filter(
        (assignment) => assignment.userId === user.id
      );

      // Consolidate assignments by department and role for display
      const displayData = consolidateAssignments(user, assignments);

      if (displayData.length === 0) {
        // If no assignments or filters caused all to be removed, display 'No Department/Role'
        // This case handles users who might exist but have no relevant assignments after filtering
        const tr = $(`
            <tr>
              <td>${
                userPrivileges.canDelete
                  ? '<input type="checkbox" class="select-row" value="' +
                    user.id +
                    '">'
                  : ""
              }</td>
              <td>${user.username}</td>
              <td>No Department</td>
              <td>No Role Assigned</td>
              <td>
                ${
                  userPrivileges.canModify
                    ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="null" data-dept-id="null">
                  <i class="bi bi-pencil-square"></i>
                </button>`
                    : ""
                }
                ${
                  userPrivileges.canDelete
                    ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="null" data-dept-id="null">
                  <i class="bi bi-trash"></i>
                </button>`
                    : ""
                }
              </td>
            </tr>
          `);
        tbody.append(tr);
        uniqueUsernames.add(user.username); // Still count the user
      } else {
        uniqueUsernames.add(user.username); // Count the user if they have any displayable data
        displayData.forEach((row, rowIndex) => {
          const roleIdAttr = row.firstRoleId === null ? "null" : row.firstRoleId;
          const deptIdAttr = row.firstDeptId === null ? "null" : row.firstDeptId;

          let tr;
          if (rowIndex === 0) {
            tr = $(`
                <tr>
                  <td rowspan="${displayData.length}">${
                  userPrivileges.canDelete
                    ? '<input type="checkbox" class="select-row" value="' +
                    user.id +
                    '">'
                  : ""
                }</td>
                  <td rowspan="${displayData.length}">${user.username}</td>
                  <td>${row.departmentName}</td>
                  <td>${row.roleNames}</td>
                  <td>
                    ${
                      userPrivileges.canModify
                        ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="${roleIdAttr}" data-dept-id="${deptIdAttr}">
                      <i class="bi bi-pencil-square"></i>
                    </button>`
                        : ""
                    }
                    ${
                      userPrivileges.canDelete
                        ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="${roleIdAttr}" data-dept-id="${deptIdAttr}">
                      <i class="bi bi-trash"></i>
                    </button>`
                        : ""
                    }
                  </td>
                </tr>
              `);
          } else {
            tr = $(`
                <tr>
                  <td>${row.departmentName}</td>
                  <td>${row.roleNames}</td>
                  <td>
                    ${
                      userPrivileges.canModify
                        ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="${roleIdAttr}" data-dept-id="${deptIdAttr}">
                      <i class="bi bi-pencil-square"></i>
                    </button>`
                        : ""
                    }
                    ${
                      userPrivileges.canDelete
                        ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="${roleIdAttr}" data-dept-id="${deptIdAttr}">
                      <i class="bi bi-trash"></i>
                    </button>`
                        : ""
                    }
                  </td>
                </tr>
              `);
          }
          tbody.append(tr);
        });
      }
    });

    // After rendering the table, update the pagination info
    const totalDisplayUsers = uniqueUsernames.size;
    $('#totalRows').text(totalDisplayUsers);

    const rowsPerPage = parseInt($('#rowsPerPageSelect').val()) || 10;
    $('#rowsPerPage').text(Math.min(rowsPerPage, totalDisplayUsers));
    $('#currentPage').text('1');

    // Update pagination controls (assuming pagination.js provides this function)
    if (typeof updatePaginationControls === 'function') {
      updatePaginationControls(totalDisplayUsers);
    }

    // Update the hidden input for pagination to ensure correct counting
    document.getElementById('total-users').value = totalDisplayUsers;
    
    // Make sure all rows are properly initialized for pagination
    window.allRows = Array.from(document.querySelectorAll('#urTable tbody tr'));
    window.filteredRows = [...window.allRows];
    
    // Reset to first page
    if (window.paginationConfig) {
      window.paginationConfig.currentPage = 1;
    }
    
    // Force pagination update after rendering
    if (typeof window.updatePagination === 'function') {
      window.updatePagination();
    }

    if ($.trim(tbody.html()) === "") {
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
      // Re-bind clear filters button to reload page
      $("#clear-filters-btn").off('click').on('click', function () {
        window.location.href = window.location.pathname; // Reload to clear URL params
      });
    }
    addEventListenersToButtons();
    // Update the bulk delete button visibility whenever table is rendered.
    if (userPrivileges.canDelete) {
      toggleBulkDeleteButton();
    }
  }

  // New function to consolidate assignments for display (copied from previous working version)
  function consolidateAssignments(user, assignments) {
      const deptRoleMap = new Map(); // Map: departmentId -> Set of roleIds

      assignments.forEach(assignment => {
          assignment.departmentIds.forEach(deptId => {
              if (!deptRoleMap.has(deptId)) {
                  deptRoleMap.set(deptId, new Set());
              }
              // Add roleId (or null if no role) to the set for this department
              deptRoleMap.get(deptId).add(assignment.roleId);
          });
      });

      const consolidatedRows = [];
      deptRoleMap.forEach((roleIds, deptId) => {
          const department = getDepartmentById(deptId);
          const deptName = department ? department.department_name : 'No Department';

          let rolesList = Array.from(roleIds).map(roleId => {
              const role = getRoleById(roleId);
              return role ? role.role_name : 'No Role Assigned';
          });

          // Filter out empty or "No Role Assigned" if other valid roles exist
          const hasRegularRoles = rolesList.some(name => name !== "No Role Assigned" && name.trim() !== "");
          if (hasRegularRoles) {
              rolesList = rolesList.filter(name => name !== "No Role Assigned" && name.trim() !== "");
          }

          const displayRoleNames = rolesList.join(", ") || "No Role Assigned";

          // Find the first roleId (can be null) and departmentId for action buttons
          let firstRoleId = null;
          let firstDeptId = deptId; // Use the department ID directly

          // Try to find an actual assignment for this department to get a specific roleId for the button
          const specificAssignment = assignments.find(a => a.departmentIds.includes(deptId));
          if (specificAssignment) {
              firstRoleId = specificAssignment.roleId;
          }

          consolidatedRows.push({
              departmentName: deptName,
              roleNames: displayRoleNames,
              firstRoleId: firstRoleId,
              firstDeptId: firstDeptId
          });
      });
      return consolidatedRows;
  }


  // Utility functions remain unchanged
  function getUserById(id) {
    return usersData.find((user) => user.id === id);
  }
  function getRoleById(id) {
    // Handle null/zero roles
    if (id === null || id === 0) {
      return { id: 0, role_name: "No Role Assigned" };
    }

    // Try to find the role in our data
    const role = rolesData.find((role) => role.id === id);
    if (role) {
      return role;
    }

    // For unknown roles, check if it's a valid integer
    if (Number.isInteger(id) && id > 0) {
      // Log the missing role for debugging
      console.warn(`Role ID ${id} not found in available roles data`);
      return null;
    }

    // For invalid IDs
    console.error(`Invalid role ID: ${id}`);
    return { id: id, role_name: "" };
  }
  function getDepartmentById(id) {
    return (
      departmentsData.find((dept) => dept.id === id) || { department_name: "" }
    );
  }

  // Handling selection in modals
  function addItemToSelection(containerId, item, type) {
    const container = document.getElementById(containerId);

    // Check for duplicates based on type
    if (
      type === "role" &&
      selectedRoles.some((r) => r.id === item.id)
    ) {
      Toast.info(`${item.role_name} is already selected`, 2000, "Info");
      return;
    }

    if (
      type === "user" &&
      selectedUsers.some((u) => u.id === item.id)
    ) {
      Toast.info(`${item.username} is already selected`, 2000, "Info");
      return;
    }

    if (
      type === "role_for_dept" &&
      selectedRoles.some((r) => r.id === item.id)
    ) {
      Toast.info(`${item.role_name} is already selected`, 2000, "Info");
      return;
    }

    // For department, allow only one selection
    if (type === "department") {
      // Clear previous selection
      container.innerHTML = "";
      selectedDepartment = item;
    } else if (type === "role") {
      selectedRoles.push(item);
    } else if (type === "user") {
      selectedUsers.push(item);
    } else if (type === "role_for_dept") {
      selectedRoles.push(item);
    }

    const selectedItem = document.createElement("span");
    selectedItem.className = "selected-item";
    selectedItem.dataset.id = item.id;
    selectedItem.innerHTML = `
          ${item.role_name || item.username || item.department_name}
          <button class="remove-btn" data-id="${
            item.id
          }" data-type="${type}">✕</button>
      `;
    container.appendChild(selectedItem);
    selectedItem
      .querySelector(".remove-btn")
      .addEventListener("click", function () {
        if (type === "role")
          selectedRoles = selectedRoles.filter((r) => r.id !== item.id);
        if (type === "user")
          selectedUsers = selectedUsers.filter((u) => u.id !== item.id);
        if (type === "department") selectedDepartment = null;
        if (type === "role_for_dept")
          selectedRoles = selectedRoles.filter((r) => r.id !== item.id);
        selectedItem.remove();
      });
  }

  // Event listeners for dynamically added buttons
  function addEventListenersToButtons() {
    if (userPrivileges.canModify) {
      document.querySelectorAll(".edit-btn").forEach((button) => {
        button.addEventListener("click", function () {
          const userId = parseInt(this.dataset.userId);
          const roleIdStr = this.dataset.roleId;
          // Handle roleId properly: "null" or "0" means null role
          const roleId =
            roleIdStr === "null" || roleIdStr === "0"
              ? 0
              : parseInt(roleIdStr);

       

          // If roleId is 0, it means user doesn't have a role yet, but may have a department
          // We should still open the edit modal with the current department information
          if (roleId === 0) {
            // Try to find any assignment for this user
            let userAssignment = userRoleDepartments.find(
              (a) => a.userId === userId
            );

            // If we found an assignment, use it to get department info
            if (userAssignment) {
              currentEditingData = {
                userId: userId,
                roleId: 0,
                originalDeptIds: [...userAssignment.departmentIds],
              };

              const modalTitle = addDepartmentRoleModal.querySelector("h2");
              const user = getUserById(userId);

              // Get department information from the data-dept-id attribute or the assignment
              const departmentId =
                parseInt(this.dataset.deptId) ||
                userAssignment.departmentIds[0];
              const department = getDepartmentById(departmentId);

              // Update DOM elements with user and department info
              const userInfoElement = document.getElementById("edit-user-info");
              const departmentInfoElement = document.getElementById(
                "edit-department-info"
              );

              if (userInfoElement) {
                userInfoElement.textContent =
                  user && user.username ? user.username : "";
              }

              if (departmentInfoElement) {
                departmentInfoElement.textContent =
                  department && department.department_name
                    ? department.department_name
                    : "";
              }

              // Update the currentEditingData with the department
              currentEditingData.departmentId = departmentId;

              modalTitle.textContent = `Edit roles for ${user.username}`;

              // Clear the roles table
              document.querySelector('#assigned-roles-table tbody').innerHTML = "";
              selectedRoles = [];
              window.selectedRoles = [];

              // Filter valid user role assignments
              const userRoles = userRoleDepartments.filter(assignment => {
                const role = getRoleById(assignment.roleId);
                return (
                  assignment.userId === userId &&
                  assignment.departmentIds.includes(departmentId) &&
                  role && role.role_name && role.id !== 0
                );
              });

           

              // Add all roles to the table
              userRoles.forEach(userRole => {
                const role = getRoleById(userRole.roleId);
                if (!role) return;

                const tbody = document.querySelector('#assigned-roles-table tbody');
                const tr = document.createElement('tr');
                tr.dataset.id = role.id;
                tr.innerHTML = `
                  <td>${role.role_name}</td>
                  <td class="text-end">
                    <button class="btn-outline-danger delete-btn" data-role-id="${role.id}">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                `;
                tbody.appendChild(tr);

                const deleteBtn = tr.querySelector('.delete-btn');
                deleteBtn.addEventListener('click', function (e) {
                  e.stopPropagation();
                  const roleId = parseInt(this.getAttribute('data-role-id'));
                  window.selectedRoles = window.selectedRoles.filter(r => r.id !== roleId);
                  tr.remove();
                });

                window.selectedRoles.push(role);
              });

              // Show the modal
              addDepartmentRoleModal.style.display = "block";
              return;
            } else {
              // If no assignment exists yet, open the add user roles modal
              selectedRoles = [];
              selectedUsers = [];
              selectedRolesContainer.innerHTML = "";
              selectedUsersContainer.innerHTML = "";

              // Pre-select the current user
              const user = getUserById(userId);
              if (user) {
                addItemToSelection("selected-users-container", user, "user");
              }

              addUserRolesModal.style.display = "block";
              return;
            }
          }

          // Regular edit flow for users with roles - find the assignment
          let assignment = null;

          // Find the assignment matching userId and roleId
          for (const a of userRoleDepartments) {
            if (a.userId === userId && a.roleId === roleId) {
              assignment = a;
              break;
            }
          }

          if (assignment) {
            currentEditingData = {
              userId: assignment.userId,
              roleId: assignment.roleId,
              originalDeptIds: [...assignment.departmentIds],
            };
            const modalTitle = addDepartmentRoleModal.querySelector("h2");
            const user = getUserById(userId);

            // Get department information
            const departmentId =
              parseInt(this.dataset.deptId) || assignment.departmentIds[0];
            const department = getDepartmentById(departmentId);

            // Get the DOM elements for user and department info
            const userInfoElement = document.getElementById("edit-user-info");
            const departmentInfoElement = document.getElementById(
              "edit-department-info"
            );

            // Add user and department details to the modal
            if (userInfoElement) {
              userInfoElement.textContent =
                user && user.username ? user.username : "";
            }

            if (departmentInfoElement) {
              departmentInfoElement.textContent =
                department && department.department_name
                  ? department.department_name
                  : "";
            }

            // Update the currentEditingData to include the specific department
            currentEditingData.departmentId = departmentId;

            modalTitle.textContent = `Edit roles for ${user.username}`;

            // Clear the roles table
            document.querySelector('#assigned-roles-table tbody').innerHTML = "";
            selectedRoles = [];
            window.selectedRoles = [];

            // Find all roles associated with this user and department
            const userRoles = userRoleDepartments.filter(assignment =>
              assignment.userId === userId &&
              assignment.departmentIds.includes(departmentId) &&
              getRoleById(assignment.roleId) // Make sure the role still exists
            );

            // Add all roles to the table
            userRoles.forEach(userRole => {
              const role = getRoleById(userRole.roleId);

              if (role && role.id !== 0) {

                // Create a new table row for the role
                const tbody = document.querySelector('#assigned-roles-table tbody');
                const tr = document.createElement('tr');
                tr.dataset.id = role.id;
                tr.innerHTML = `
                  <td>${role.role_name}</td>
                  <td class="text-end">
                    <button class="btn-outline-danger delete-btn" data-role-id="${role.id}">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                `;

                // Add to table
                tbody.appendChild(tr);

                // Add click handler for delete button
                const deleteBtn = tr.querySelector('.delete-btn');
                deleteBtn.addEventListener('click', function(e) {
                  // Stop event propagation to prevent other handlers from firing
                  e.stopPropagation();

                  // Get the role ID from the button's data attribute
                  const roleId = parseInt(this.getAttribute('data-role-id'));


                  // Remove from the selectedRoles array
                  if (window.selectedRoles) {
                    window.selectedRoles = window.selectedRoles.filter(r => r.id !== roleId);
                  }

                  // Remove the table row
                  tr.remove();

                });

                // Add to the selectedRoles array
                if (!window.selectedRoles) window.selectedRoles = [];
                window.selectedRoles.push(role);
              }
            });

            addDepartmentRoleModal.style.display = "block";
          } else {
            console.error(
              "Could not find assignment for userId",
              userId,
              "roleId",
              roleId
            );
            Toast.error("Could not find assignment data", 5000, "Error");
          }
        });
      });
    }

    if (userPrivileges.canDelete) {
      document.querySelectorAll(".delete-btn").forEach((button) => {
        button.addEventListener("click", function () {
          const userId = parseInt(this.dataset.userId);
          const roleIdStr = this.dataset.roleId;
          // Handle roleId properly: "null" or "0" means null role
          const roleId = roleIdStr === "null" || roleIdStr === "0" ? null : parseInt(roleIdStr);
          const departmentId = parseInt(this.dataset.deptId) || null;

          // Check if the user has actual roles in this department
          const userAssignments = userRoleDepartments.filter(
            (a) => a.userId === userId && a.departmentIds.includes(departmentId) && a.roleId !== null && a.roleId !== 0
          );

          // If no actual roles exist, show "No Changes" message and stop
          if (!userAssignments.length) {
            Toast.info("No roles to delete for this user in this department", 5000, "No Changes");
            return;
          }

          // Get user and department info for the confirmation message
          const user = getUserById(userId);
          let departmentName = "this department";
          if (departmentId) {
            const dept = getDepartmentById(departmentId);
            if (dept && dept.department_name) {
              departmentName = dept.department_name;
            }
          }

          // Update the confirmation message
          const confirmMsg = document.querySelector('#delete-confirm-modal .modal-body p');
          if (confirmMsg && user) {
            confirmMsg.innerHTML = `Are you sure you want to remove all roles for <strong>${user.username}</strong> in <strong>${departmentName}</strong>?<br><small>The user will still be listed under this department but with no roles.</small>`;
          }

          // Show the delete confirmation modal
          pendingDelete = { userId, roleId, departmentId };
          if (deleteModal) {
            deleteModal.show();
          }
        });
      });
    }
  }
  // Modal selection handlers
  if (searchRoleDropdown) {
    searchRoleDropdown.addEventListener("change", function () {
      const roleId = parseInt(this.value);
      if (roleId) {
        const role = getRoleById(roleId);
        addItemToSelection("selected-roles-container", role, "role");
        this.value = "";
        // Reset Select2 to show placeholder after selection
        $(this).val(null).trigger('change');
      }
    });
  }

  if (searchUsersDropdown) {
    searchUsersDropdown.addEventListener("change", function () {
      const userId = parseInt(this.value);
      if (userId) {
        const user = getUserById(userId);
        addItemToSelection("selected-users-container", user, "user");
        this.value = "";
        // Reset Select2 to show placeholder after selection
        $(this).val(null).trigger('change');
      }
    });
  }

  if (searchDepartmentDropdown) {
    searchDepartmentDropdown.addEventListener("change", function () {
      const deptId = parseInt(this.value);
      if (deptId) {
        const dept = getDepartmentById(deptId);
        addItemToSelection("selected-department-container", dept, "department");
        this.value = "";
        // Reset Select2 to show placeholder after selection
        $(this).val(null).trigger('change');
      }
    });
  }

  if (departmentDropdown) {
    departmentDropdown.addEventListener("change", function () {

      const roleId = parseInt(this.value);
      if (roleId) {
        const role = getRoleById(roleId);

        // Check if this role is already in the table
        const tbody = document.querySelector('#assigned-roles-table tbody');
        const existingRow = Array.from(tbody.querySelectorAll('tr')).find(
          row => parseInt(row.dataset.id) === roleId
        );

        if (existingRow) {
          Toast.info(`${role.role_name} is already selected`, 2000, "Info");
          return;
        }

        // Create a new table row for the role
        const tr = document.createElement('tr');
        tr.dataset.id = role.id;
        tr.innerHTML = `
          <td>${role.role_name}</td>
          <td class="text-end">
            <button class="btn-outline-danger delete-btn" data-role-id="${role.id}">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        `;

        // Add to table
        tbody.appendChild(tr);

        // Add click handler for delete button
        const deleteBtn = tr.querySelector('.delete-btn');
        deleteBtn.addEventListener('click', function(e) {
          // Stop event propagation to prevent other handlers from firing
          e.stopPropagation();

          // Get the role ID from the button's data attribute
          const roleId = parseInt(this.getAttribute('data-role-id'));

            
          // Remove from the selectedRoles array
          if (window.selectedRoles) {
            window.selectedRoles = window.selectedRoles.filter(r => r.id !== roleId);
          }

          // Remove the table row
          tr.remove();

        });

        // Add to the selectedRoles array
        if (!window.selectedRoles) window.selectedRoles = [];
        window.selectedRoles.push(role);

        this.value = "";
        // Reset Select2 to show placeholder after selection
        $(this).val(null).trigger('change');
      }
    });
  }

  // DISABLED: These automatic triggers are removed to only filter when the filter button is clicked
  /*
  if (searchUsersInput) {
    searchUsersInput.addEventListener("input", updateUrlAndReload);
  }

  if (roleFilterDropdown) {
    roleFilterDropdown.addEventListener("change", updateUrlAndReload);
  }

  if (deptFilterDropdown) {
    deptFilterDropdown.addEventListener("change", updateUrlAndReload);
  }
  */
  
  // New function to handle filter button click
  function handleFilterButtonClick() {
    console.log('Filter button clicked in JS');
    
    // Get filter values
    const searchText = searchUsersInput ? searchUsersInput.value.toLowerCase() : '';
    const roleFilter = roleFilterDropdown ? roleFilterDropdown.value : '';
    const deptFilter = deptFilterDropdown ? deptFilterDropdown.value : '';
    
    console.log('Filtering with:', { searchText, roleFilter, deptFilter });
    
    // If we're using URL-based filtering, update the URL and reload
    const useUrlFiltering = false; // Set to true if you want URL-based filtering
    
    if (useUrlFiltering) {
      // Update URL parameters and reload
      const urlParams = new URLSearchParams(window.location.search);
      
      if (searchText) {
        urlParams.set('search', encodeURIComponent(searchText));
      } else {
        urlParams.delete('search');
      }
      
      if (roleFilter) {
        urlParams.set('role', encodeURIComponent(roleFilter));
      } else {
        urlParams.delete('role');
      }
      
      if (deptFilter) {
        urlParams.set('department', encodeURIComponent(deptFilter));
      } else {
        urlParams.delete('department');
      }
      
      // Reload with new URL parameters
      window.location.href = window.location.pathname + '?' + urlParams.toString();
    } else {
      // Use client-side filtering
      // This will call the filterTable function defined in the PHP file
      if (typeof window.filterTable === 'function') {
        window.filterTable();
      }
    }
  }
  
  // Expose the handler globally so it can be removed if needed
  window.handleFilterButtonClick = handleFilterButtonClick;
  
  // DO NOT attach the handler here - it's handled in the PHP file
  // const filterBtn = document.getElementById('filter-btn');
  // if (filterBtn) {
  //   filterBtn.addEventListener('click', handleFilterButtonClick);
  // }

  // Modal open/close handlers
  if (addUserRoleBtn && userPrivileges.canCreate) {
    addUserRoleBtn.addEventListener("click", function () {
      // Reset all selections
      selectedRoles = [];
      selectedUsers = [];
      selectedDepartment = null;
      window.selectedRoles = [];
      window.selectedUsers = [];
      window.selectedDepartment = null;

      // Clear all containers
      if (selectedRolesContainer) selectedRolesContainer.innerHTML = "";
      if (selectedUsersContainer) selectedUsersContainer.innerHTML = "";
      if (selectedDepartmentContainer) selectedDepartmentContainer.innerHTML = "";

      // Clear the roles table
      if (document.querySelector('#assigned-roles-table tbody')) {
        document.querySelector('#assigned-roles-table tbody').innerHTML = "";
      }

      // Show the modal
      addUserRolesModal.style.display = "block";
    });
  }

  if (closeUserRolesModal) {
    closeUserRolesModal.addEventListener("click", function () {
      addUserRolesModal.style.display = "none";
    });
  }

  if (closeDepartmentRoleModal) {
    closeDepartmentRoleModal.addEventListener("click", function () {
      // Clear the roles table
      if (document.querySelector('#assigned-roles-table tbody')) {
        document.querySelector('#assigned-roles-table tbody').innerHTML = "";
      }

      // Reset role selections
      selectedRoles = [];
      window.selectedRoles = [];

      // Hide the modal
      addDepartmentRoleModal.style.display = "none";
    });
  }

  // Delete confirmation modal handlers
  if (cancelDeleteBtn) {
    cancelDeleteBtn.addEventListener("click", function () {
      pendingDelete = null;
      // Use our pre-initialized modal
      if (deleteModal) {
        deleteModal.hide();
      }
    });
  }

  if (confirmDeleteBtn && userPrivileges.canDelete) {
    confirmDeleteBtn.addEventListener("click", function () {
      if (pendingDelete) {
        const { userId, departmentId } = pendingDelete;

        if (!departmentId) {
          Toast.error(
            "No department ID provided for this assignment",
            5000,
            "Error"
          );
          if (deleteModal) {
            deleteModal.hide();
          }
          return;
        }


        // Send AJAX request to delete all assignments for this user-department
        fetch("delete_user_role.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            userId,
            departmentId,
            removeAll: true,  // New flag to indicate removing all roles for this department
            trackChanges: true  // Add flag to track changes in audit log
          }),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              // Update local data
              if (data.assignments && data.assignments.length > 0) {
                // The backend returned updated assignments for this user
                // Replace only the assignments for this user
                const otherUserAssignments = userRoleDepartments.filter(
                  (a) => a.userId !== userId
                );
                userRoleDepartments = [
                  ...otherUserAssignments,
                  ...data.assignments,
                ];
              } else {
                // Remove all assignments for this user in this department
                userRoleDepartments = userRoleDepartments.filter(
                  (a) => !(a.userId === userId && a.departmentIds.includes(departmentId))
                );
              }

              // Re-render the table with the updated data
              renderUserRolesTable(); // No sortDirection needed here anymore
              
              // Trigger event for pagination reinitialization
              $(document).trigger('userRoleDeleted');
              
              Toast.success(
                "All roles removed successfully",
                5000,
                "Removed"
              );
            } else if (data.noChanges) {
              // Handle case where no roles were deleted
              Toast.info(
                data.error || "No roles to delete for this user in this department",
                5000,
                "No Changes"
              );
            } else {
              Toast.error(
                data.error || "Failed to delete assignment",
                5000,
                "Error"
              );
            }
          })
          .catch((error) => {
            console.error(error);
            Toast.error("Error deleting assignment", 5000, "Error");
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

  // Save handler for adding users to roles
  if (saveUserRolesBtn && userPrivileges.canCreate) {
    saveUserRolesBtn.addEventListener("click", function () {


      // Use window.selectedUsers and window.selectedDepartment which are set by the Select2 handlers
      const usersToSave = window.selectedUsers || selectedUsers || [];
      const departmentToSave = window.selectedDepartment || selectedDepartment;
      const rolesToSave = window.selectedRoles || selectedRoles || [];

      // Changed validation: only users and department are required, roles are optional
      if (!usersToSave || usersToSave.length === 0) {
        Toast.error(
          "Please select at least one user",
          5000,
          "Validation Error"
        );
        return;
      }

      if (!departmentToSave) {
        Toast.error(
          "Please select a department",
          5000,
          "Validation Error"
        );
        return;
      }

      let newAssignments = [];
      usersToSave.forEach((user) => {
        // If no roles are selected, create an assignment with role ID 0
        if (rolesToSave.length === 0) {
          newAssignments.push({
            userId: user.id,
            roleIds: [0], // Use 0 for no role
            departmentId: departmentToSave.id,
          });
        } else {
          // Otherwise create entries with the selected roles
          newAssignments.push({
            userId: user.id,
            roleIds: rolesToSave.map((role) => role.id),
            departmentId: departmentToSave.id,
          });
        }
      });

      // Pre-check for empty newAssignments
      if (newAssignments.length === 0) {
        Toast.error("No valid assignments to create", 5000, "Error");
        return;
      }

      // Check if assignments already exist
      let noChanges = true;
      for (const assignment of newAssignments) {
        const userId = assignment.userId;
        const departmentId = assignment.departmentId;

        for (const roleId of assignment.roleIds) {
          // Check if this exact user-department-role assignment already exists
          const existingAssignment = userRoleDepartments.find(
            (a) =>
              a.userId === userId &&
              a.roleId === roleId &&
              a.departmentIds.includes(departmentId)
          );

          // If any assignment doesn't exist yet, we have changes to save
          if (!existingAssignment) {
            noChanges = false;
            break;
          }
        }

        if (!noChanges) break;
      }

      // If no changes, show notification and exit
      if (noChanges) {
        Toast.info(
          "No changes to save - these assignments already exist",
          3000,
          "Information"
        );
        return;
      }

      fetch("save_user_role.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          assignments: newAssignments,
          trackChanges: true  // Add flag to track changes in audit log
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Update the local data
            if (data.assignments) {
              // Merge the server response with existing data rather than replacing
              const newAssignmentsFromServer = data.assignments;

              // For each new assignment from server, update or add to userRoleDepartments
              newAssignmentsFromServer.forEach((newAssignment) => {
                const existingIndex = userRoleDepartments.findIndex(
                  (a) =>
                    a.userId === newAssignment.userId &&
                    a.roleId === newAssignment.roleId
                );

                if (existingIndex !== -1) {
                  // CRITICAL CHANGE: Don't replace department IDs, merge them
                  // Check if department already exists to avoid duplicates
                  newAssignment.departmentIds.forEach((deptId) => {
                    if (
                      !userRoleDepartments[
                        existingIndex
                      ].departmentIds.includes(deptId)
                    ) {
                      userRoleDepartments[existingIndex].departmentIds.push(
                        deptId
                      );
                    }
                  });
                } else {
                  // Add new assignment
                  userRoleDepartments.push(newAssignment);
                }
              });
            } else {
              // Client-side creation for each role (if no server response)
              newAssignments.forEach((assignment) => {
                assignment.roleIds.forEach((roleId) => {
                  // Check if this assignment already exists
                  const existingIndex = userRoleDepartments.findIndex(
                    (a) => a.userId === assignment.userId && a.roleId === roleId
                  );

                  if (existingIndex !== -1) {
                    // CRITICAL CHANGE: Don't replace, add if not present
                    if (
                      !userRoleDepartments[
                        existingIndex
                      ].departmentIds.includes(assignment.departmentId)
                    ) {
                      userRoleDepartments[existingIndex].departmentIds.push(
                        assignment.departmentId
                      );
                    }
                  } else {
                    // Create new assignment
                    userRoleDepartments.push({
                      userId: assignment.userId,
                      roleId: roleId,
                      departmentIds: [assignment.departmentId],
                    });
                  }
                });
              });
            }

            // Reset selections for next time
            selectedUsers = [];
            selectedRoles = [];
            selectedDepartment = null;
            selectedUsersContainer.innerHTML = "";
            selectedRolesContainer.innerHTML = "";
            selectedDepartmentContainer.innerHTML = "";

            // Close modal and refresh table
            addUserRolesModal.style.display = "none";
            renderUserRolesTable(); // No sortDirection needed here anymore
            
            // Trigger event for pagination reinitialization
            $(document).trigger('userRoleCreated');
            
            Toast.success("New roles assigned successfully", 5000, "Success");
          } else {
            // Display the error message from the server
            Toast.error(
              data.error || "Failed to save assignments",
              5000,
              "Error"
            );
          }
        })
        .catch((error) => {
          console.error(error);
          Toast.error("Error saving assignments", 5000, "Error");
        });
    });
  }

  // Update function for modifying departments
  if (saveDepartmentRoleBtn && userPrivileges.canModify) {
    saveDepartmentRoleBtn.addEventListener("click", function () {
      if (currentEditingData) {


        // Use window.selectedRoles which is set by the Select2 handlers
        const rolesToUpdate = window.selectedRoles || selectedRoles || [];

        // Map selected roles to their IDs
        let updatedRoles = rolesToUpdate.map((role) => role.id);

        // If no roles are selected, explicitly set to empty array
        // This will trigger the backend to assign null/zero role
        if (updatedRoles.length === 0) {
          updatedRoles = [];
        }

        // Convert oldRoleId to 0 for send to the server if it's null in our data
        const oldRoleId =
          currentEditingData.roleId === null ? 0 : currentEditingData.roleId;

        // Ensure the department ID is properly set
        const departmentId =
          currentEditingData.departmentId ||
          (currentEditingData.originalDeptIds &&
          currentEditingData.originalDeptIds.length > 0
            ? currentEditingData.originalDeptIds[0]
            : null);

        if (!departmentId) {
          Toast.error(
            "No department ID found for this assignment",
            5000,
            "Error"
          );
          return;
        }

        // Always consider it a change when there are roles selected or when removing roles
        let hasChanges = true;


        // Find all existing roles for this user and department
        const existingRoles = userRoleDepartments
          .filter(a => a.userId === currentEditingData.userId && a.departmentIds.includes(departmentId))
          .map(a => a.roleId);

       

        // Check if there are actual changes
        const rolesAdded = updatedRoles.filter(roleId => !existingRoles.includes(roleId));
        const rolesRemoved = existingRoles.filter(roleId => !updatedRoles.includes(roleId) && roleId !== 0 && roleId !== null);


        // Always continue with the update if roles were added or removed
        if (rolesAdded.length > 0 || rolesRemoved.length > 0) {
         
        } else {
         
          Toast.info("No changes detected", 3000, "Info");
          addDepartmentRoleModal.style.display = "none";
          return;
        }

        // Ensure window.selectedRoles is properly set
        if (!window.selectedRoles) {
          window.selectedRoles = [];
        }

        // Map selected roles to their IDs to ensure we're sending the correct data
        updatedRoles = window.selectedRoles.map(role => role.id);

        // Log the update payload for debugging
        const updatePayload = {
          userId: currentEditingData.userId,
          oldRoleId: oldRoleId,
          roleIds: updatedRoles,
          departmentId: departmentId,
          preserveExistingDepartments: true,
          trackChanges: true
        };

        // Check if we're removing all roles
        if (updatedRoles.length === 0) {
          Toast.info("Removing all roles from this department...", 2000, "Processing");
        }

        fetch("update_user_department.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(updatePayload),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              // Log the server response for debugging

              // Update local data with server response
              if (data.assignments) {
                // Properly replace user's assignments with server response
                // First, remove all existing assignments for this user
                userRoleDepartments = userRoleDepartments.filter(
                  (a) => a.userId !== currentEditingData.userId
                );

                // Then add the new assignments from the server
                userRoleDepartments = [
                  ...userRoleDepartments,
                  ...data.assignments,
                ];

              } else {

                // Remove existing roles for this user and department
                userRoleDepartments = userRoleDepartments.filter(assignment =>
                  !(assignment.userId === currentEditingData.userId &&
                    assignment.departmentIds.includes(departmentId))
                );

                // Add the updated roles
                if (updatedRoles.length > 0) {
                  updatedRoles.forEach(roleId => {
                    userRoleDepartments.push({
                      userId: currentEditingData.userId,
                      roleId: roleId,
                      departmentIds: [departmentId]
                    });
                  });
                }
              }

              // Close the modal
              addDepartmentRoleModal.style.display = "none";

              // Completely re-render the table with fresh data
              renderUserRolesTable(); // No sortDirection needed here anymore
              
              // Trigger event for pagination reinitialization
              $(document).trigger('userRoleModified');
              
              Toast.success("Roles updated successfully", 5000, "Success");
            } else {
              Toast.error(data.error || "Failed to update roles", 5000, "Error");
            }
          })
          .catch((error) => {
            console.error(error);
            Toast.error("Error updating roles", 5000, "Error");
          });
      }
    });
  }

  // Close modals when clicking outside
  window.addEventListener("click", function (event) {
    if (event.target === addUserRolesModal) {
      addUserRolesModal.style.display = "none";
    }
    if (event.target === addDepartmentRoleModal) {
      addDepartmentRoleModal.style.display = "none";
    }
  });

  // Keyboard shortcuts
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      addUserRolesModal.style.display = "none";
      addDepartmentRoleModal.style.display = "none";
      // Use our pre-initialized modal
      if (deleteModal) {
        deleteModal.hide();
      }
    }
    if (event.ctrlKey && event.key === "k") {
      event.preventDefault();
      searchUsersInput.focus();
    }
    if (userPrivileges.canCreate && event.ctrlKey && event.key === "n") {
      event.preventDefault();
      addUserRoleBtn.click();
    }
  });

  function toggleBulkDeleteButton() {
    if (!userPrivileges.canDelete) return;

    const selectedCount = $(".select-row:checked").length;
    if (selectedCount >= 2) {
      $("#delete-selected").show().prop("disabled", false);
    } else {
      $("#delete-selected").hide().prop("disabled", true);
    }
  }

  // Listen for changes on any checkbox in the table
  if (userPrivileges.canDelete) {
    $(document).on("change", ".select-row", function () {
      toggleBulkDeleteButton();
    });
  }

  // Bulk delete event handler
  if ($("#delete-selected").length && userPrivileges.canDelete) {
    $("#delete-selected").click(function () {
      const selected = $(".select-row:checked")
        .map(function () {
          return $(this).val();
        })
        .get();
      if (selected.length === 0) {
        showToast("Please select user roles to remove.", "warning");
        return;
      }
      // Confirm bulk deletion
      // IMPORTANT: Changed from window.confirm to a custom modal if you have one.
      // If not, you'll need to implement a custom modal or use a library.
      // For now, I'll use a basic confirm for demonstration, but recommend a custom UI.
      if (
        confirm(
          `Are you sure you want to remove ${selected.length} selected user role(s)?`
        )
      ) {
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
              renderUserRolesTable(); // No sortDirection needed here anymore
              showToast(response.message, "success");
            } else {
              showToast(response.message, "error");
            }
          },
          error: function () {
            showToast("Error removing selected user roles.", "error");
          },
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

  // Initial render of the table and update sort icons on page load
  renderUserRolesTable();
  updateSortIcons(); // Call this to set initial sort icon state

  // Count unique users for initial pagination
  const uniqueUsernamesOnLoad = new Set(usersData.map(user => user.username));
  const totalUsersOnLoad = uniqueUsernamesOnLoad.size;

  $('#totalRows').text(totalUsersOnLoad);
  const rowsPerPageOnLoad = parseInt($('#rowsPerPageSelect').val()) || 10;
  const displayEndOnLoad = Math.min(rowsPerPageOnLoad, totalUsersOnLoad);
  $('#rowsPerPage').text(displayEndOnLoad);
  $('#currentPage').text('1');

  // Update the hidden input for pagination to use the correct count
  document.getElementById('total-users').value = totalUsersOnLoad;
});
