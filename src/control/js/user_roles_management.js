document.addEventListener("DOMContentLoaded", function () {
  // DOM elements
  const addUserRoleBtn = document.getElementById("create-btn");
  const userRolesTable = document.getElementById("urTable");
  const searchUsersInput = document.getElementById("search-users");
  const roleFilterDropdown = document.getElementById("role-filter");
  const deptFilterDropdown = document.getElementById("dept-filter");
  const sortUserBtn = document.getElementById("sort-user");
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

  // Sorting state
  let userSortDirection = "asc"; // 'asc' or 'desc'
  // Clear Filters Button Handler
  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener("click", function () {
      // Clear all filter inputs
      if (searchUsersInput) searchUsersInput.value = "";
      if (roleFilterDropdown) roleFilterDropdown.value = "";
      if (deptFilterDropdown) deptFilterDropdown.value = "";

      // Reset sort direction to default
      userSortDirection = "asc";
      if (sortUserBtn) sortUserBtn.innerHTML = "A→Z";

      // Re-render table with no filters
      renderUserRolesTable(null, null, null, userSortDirection);

      // Show confirmation toast
      Toast.success("All filters cleared", 3000, "Success");
    });
  }

  // Render user roles table using all active users
  function renderUserRolesTable(
    filterUserId = null,
    filterRoleId = null,
    filterDeptName = null,
    sortDirection = null
  ) {
    const tbody = $("#urTable tbody");
    tbody.empty();
    let filteredUsers = usersData.filter((user) => {
      if (
        filterUserId &&
        !user.username.toLowerCase().includes(filterUserId.toLowerCase())
      ) {
        return false;
      }
      return true;
    });

    // Sort users if requested
    if (sortDirection === "asc") {
      filteredUsers.sort((a, b) => a.username.localeCompare(b.username));
    } else if (sortDirection === "desc") {
      filteredUsers.sort((a, b) => b.username.localeCompare(a.username));
    }

    filteredUsers.forEach((user) => {
      let assignments = userRoleDepartments.filter(
        (assignment) => assignment.userId === user.id
      );

      if (filterRoleId) {
        assignments = assignments.filter(
          (assignment) => assignment.roleId === parseInt(filterRoleId)
        );
      }

      if (filterDeptName) {
        assignments = assignments.filter((assignment) =>
          assignment.departmentIds.some(function (deptId) {
            const dept = getDepartmentById(deptId);
            return dept && dept.department_name === filterDeptName;
          })
        );
      }

      if ((filterRoleId || filterDeptName) && assignments.length === 0) {
        return;
      }

      if (assignments.length === 0) {
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
                          ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="null">
                        <i class="bi bi-pencil-square"></i>
                      </button>`
                          : ""
                      }
                      ${
                        userPrivileges.canDelete
                          ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="null">
                        <i class="bi bi-trash"></i>
                      </button>`
                          : ""
                      }
                    </td>
                  </tr>
                `);
        tbody.append(tr);
      } else {
        // Consolidate assignments by department
        const deptMap = new Map(); // Map of departmentName => array of roles

        assignments.forEach((assignment) => {
          const role = getRoleById(assignment.roleId);

          assignment.departmentIds.forEach((deptId) => {
            const dept = getDepartmentById(deptId);
            if (!dept) return;

            // Skip if department name is empty
            const deptName = dept.department_name;
            if (!deptName) return;

            if (!deptMap.has(deptName)) {
              deptMap.set(deptName, []);
            }

            // Include roles even if null (now displays as "No Role Assigned")
            deptMap.get(deptName).push({
              roleName: role ? role.role_name : "No Role Assigned",
              roleId: assignment.roleId,
              userId: assignment.userId,
              departmentId: deptId, // Add departmentId to the role info
            });
          });
        });

        // Convert the map to array for easier rendering
        const consolidatedDepts = Array.from(deptMap).map(
          ([deptName, roles]) => ({
            departmentName: deptName,
            roles: roles,
          })
        );

        // Render the consolidated data
        if (consolidatedDepts.length === 0) {
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
                              ? `<button class="edit-btn" data-user-id="${user.id}" data-role-id="null">
                            <i class="bi bi-pencil-square"></i>
                          </button>`
                              : ""
                          }
                          ${
                            userPrivileges.canDelete
                              ? `<button class="delete-btn" data-user-id="${user.id}" data-role-id="null">
                            <i class="bi bi-trash"></i>
                          </button>`
                              : ""
                          }
                        </td>
                      </tr>
                    `);
          tbody.append(tr);
        } else {
          consolidatedDepts.forEach((dept, deptIndex) => {
            // Filter out empty role names before joining with comma
            // Also filter out "No Role Assigned" if there are other roles
            let rolesList = dept.roles.map((r) => r.roleName);

            // Check if we have real roles (not "No Role Assigned")
            const hasRegularRoles = rolesList.some(
              (name) => name !== "No Role Assigned" && name.trim() !== ""
            );

            // If we have regular roles, filter out the "No Role Assigned" placeholders
            if (hasRegularRoles) {
              rolesList = rolesList.filter(
                (name) => name !== "No Role Assigned" && name.trim() !== ""
              );
            }

            // Join the role names with commas
            const roleNames = rolesList.join(", ");

            // Use "No Role Assigned" when there are no roles
            const displayRoleNames = roleNames || "No Role Assigned";

            // Get the first role for the action buttons
            const firstRole = dept.roles[0];

            // Get department ID from:
            // 1. directly from the role object if available (preferred)
            // 2. try to find it from department name as fallback
            let deptId = firstRole.departmentId || null;
            if (!deptId) {
              const deptObj = departmentsData.find(
                (d) => d.department_name === dept.departmentName
              );
              deptId = deptObj ? deptObj.id : null;
            }

            // Set role ID for the button: use "null" for null roles
            const roleIdAttr =
              firstRole.roleId === null ? "null" : firstRole.roleId;

            // Use "No Department" when department name is empty
            const displayDeptName = dept.departmentName || "No Department";

            let tr;
            if (deptIndex === 0) {
              tr = $(`
                              <tr>
                                <td rowspan="${consolidatedDepts.length}">${
                userPrivileges.canDelete
                  ? '<input type="checkbox" class="select-row" value="' +
                    user.id +
                    '">'
                  : ""
              }</td>
                                <td rowspan="${consolidatedDepts.length}">${
                user.username
              }</td>
                                <td>${displayDeptName}</td>
                                <td>${displayRoleNames}</td>
                                <td>
                                  ${
                                    userPrivileges.canModify
                                      ? `<button class="edit-btn" data-user-id="${firstRole.userId}" data-role-id="${roleIdAttr}" data-dept-id="${deptId}">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>`
                                      : ""
                                  }
                                  ${
                                    userPrivileges.canDelete
                                      ? `<button class="delete-btn" data-user-id="${firstRole.userId}" data-role-id="${roleIdAttr}" data-dept-id="${deptId}">
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
                                <td>${displayDeptName}</td>
                                <td>${displayRoleNames}</td>
                                <td>
                                  ${
                                    userPrivileges.canModify
                                      ? `<button class="edit-btn" data-user-id="${firstRole.userId}" data-role-id="${roleIdAttr}" data-dept-id="${deptId}">
                                    <i class="bi bi-pencil-square"></i>
                                  </button>`
                                      : ""
                                  }
                                  ${
                                    userPrivileges.canDelete
                                      ? `<button class="delete-btn" data-user-id="${firstRole.userId}" data-role-id="${roleIdAttr}" data-dept-id="${deptId}">
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
      }
    });
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
      $("#clear-filters-btn").click(function () {
        $("#search-users").val("");
        $("#role-filter").val("");
        $("#dept-filter").val("");
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
      return { id: id, role_name: `Unknown Role` };
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

          // For debugging
          console.log(
            "Edit clicked for userId:",
            userId,
            "roleId:",
            roleId,
            "original:",
            roleIdStr
          );
          console.log("Available assignments:", userRoleDepartments);

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

              // Clear the roles container and reset selected roles
              document.getElementById("added-departments-container").innerHTML =
                "";
              selectedRoles = [];
              // Make sure the global selectedRoles is also initialized
              window.selectedRoles = [];

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

            document.getElementById("added-departments-container").innerHTML =
              "";
            selectedRoles = [];

            // Add the current role
            const role = getRoleById(roleId);
            if (role && role.id !== 0) {
              console.log("Adding current role to selection:", role);
              addItemToSelection(
                "added-departments-container",
                role,
                "role_for_dept"
              );
            } else {
              console.log("No role to add or role ID is 0");
            }

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
          const roleId =
            roleIdStr === "null" || roleIdStr === "0"
              ? null
              : parseInt(roleIdStr);
          const departmentId = parseInt(this.dataset.deptId) || null;

          // For debugging
          console.log(
            "Delete clicked for userId:",
            userId,
            "roleId:",
            roleId,
            "original:",
            roleIdStr
          );

          // Check if the user has actual roles or departments
          const hasAssignments = userRoleDepartments.some(
            (a) => a.userId === userId
          );

          // Don't allow deletion if user has no assignments
          if (!hasAssignments) {
            Toast.info("This user has no roles to delete", 5000, "Info");
            return;
          }

          // Instead of a simple confirm(), show the custom delete modal.
          pendingDelete = { userId, roleId, departmentId };
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
      console.log("Department dropdown change event triggered");
      console.log("Selected value:", this.value);
      
      const roleId = parseInt(this.value);
      if (roleId) {
        console.log("Role ID:", roleId);
        const role = getRoleById(roleId);
        console.log("Role object:", role);
        
        addItemToSelection(
          "added-departments-container",
          role,
          "role_for_dept"
        );
        this.value = "";
        // Reset Select2 to show placeholder after selection
        $(this).val(null).trigger('change');
      }
    });
  }

  // Filter handlers
  if (searchUsersInput) {
    searchUsersInput.addEventListener("input", function () {
      const filterUserId = this.value;
      const filterRoleId = roleFilterDropdown ? roleFilterDropdown.value : null;
      const filterDeptName = deptFilterDropdown
        ? deptFilterDropdown.value
        : null;
      renderUserRolesTable(
        filterUserId,
        filterRoleId,
        filterDeptName,
        userSortDirection
      );
    });
  }

  if (roleFilterDropdown) {
    roleFilterDropdown.addEventListener("change", function () {
      const filterUserId = searchUsersInput ? searchUsersInput.value : null;
      const filterRoleId = this.value;
      const filterDeptName = deptFilterDropdown
        ? deptFilterDropdown.value
        : null;
      renderUserRolesTable(
        filterUserId,
        filterRoleId,
        filterDeptName,
        userSortDirection
      );
    });
  }

  if (deptFilterDropdown) {
    deptFilterDropdown.addEventListener("change", function () {
      const filterUserId = searchUsersInput ? searchUsersInput.value : null;
      const filterRoleId = roleFilterDropdown ? roleFilterDropdown.value : null;
      const filterDeptName = this.value;
      renderUserRolesTable(
        filterUserId,
        filterRoleId,
        filterDeptName,
        userSortDirection
      );
    });
  }

  // Sort users handler
  if (sortUserBtn) {
    sortUserBtn.addEventListener("click", function () {
      userSortDirection = userSortDirection === "asc" ? "desc" : "asc";
      // Update the sort icon
      this.innerHTML = userSortDirection === "asc" ? "A→Z" : "Z→A";

      const filterUserId = searchUsersInput ? searchUsersInput.value : null;
      const filterRoleId = roleFilterDropdown ? roleFilterDropdown.value : null;
      const filterDeptName = deptFilterDropdown
        ? deptFilterDropdown.value
        : null;
      renderUserRolesTable(
        filterUserId,
        filterRoleId,
        filterDeptName,
        userSortDirection
      );
    });
  }

  // Modal open/close handlers
  if (addUserRoleBtn && userPrivileges.canCreate) {
    addUserRoleBtn.addEventListener("click", function () {
      // Reset all selections
      selectedRoles = [];
      selectedUsers = [];
      selectedDepartment = null;
      
      // Clear all selection containers
      if (selectedRolesContainer) selectedRolesContainer.innerHTML = "";
      if (selectedUsersContainer) selectedUsersContainer.innerHTML = "";
      if (selectedDepartmentContainer) selectedDepartmentContainer.innerHTML = "";
      
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
        const { userId, roleId, departmentId } = pendingDelete;

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

        // Send AJAX request to delete assignment from the database
        fetch("delete_user_role.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ 
            userId, 
            roleId, 
            departmentId,
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
                // Find the specific assignment
                const assignmentIndex = userRoleDepartments.findIndex(
                  (a) => a.userId === userId && a.roleId === roleId
                );

                if (assignmentIndex !== -1) {
                  const assignment = userRoleDepartments[assignmentIndex];
                  // Only remove the specific department ID
                  assignment.departmentIds = assignment.departmentIds.filter(
                    (deptId) => deptId !== departmentId
                  );

                  // If no departments left, remove the entire assignment
                  if (assignment.departmentIds.length === 0) {
                    userRoleDepartments.splice(assignmentIndex, 1);
                  }
                }
              }

              // Re-render the table with the updated data
              renderUserRolesTable(null, null, null, userSortDirection);
              Toast.success(
                "Role assignment has been removed successfully",
                5000,
                "Deleted"
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
      // Debug log to check what's in the arrays
      console.log("Selected users (local):", selectedUsers);
      console.log("Selected department (local):", selectedDepartment);
      console.log("Selected users (window):", window.selectedUsers);
      console.log("Selected department (window):", window.selectedDepartment);
      
      // Use window.selectedUsers and window.selectedDepartment which are set by the Select2 handlers
      const usersToSave = window.selectedUsers || selectedUsers || [];
      const departmentToSave = window.selectedDepartment || selectedDepartment;
      const rolesToSave = window.selectedRoles || selectedRoles || [];
      
      console.log("Users to save:", usersToSave);
      console.log("Department to save:", departmentToSave);
      console.log("Roles to save:", rolesToSave);
      
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
            renderUserRolesTable(null, null, null, userSortDirection);
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
        // When editing a user with no roles, we need to handle the case where roleId is null or 0
        // Log full data for debugging
        console.log("Saving with currentEditingData:", currentEditingData);
        console.log("Selected roles (local):", selectedRoles);
        console.log("Selected roles (window):", window.selectedRoles);

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

        console.log("Checking for changes:");
        console.log("Current role ID:", currentEditingData.roleId);
        console.log("Updated roles:", updatedRoles);

        fetch("update_user_department.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            userId: currentEditingData.userId,
            oldRoleId: oldRoleId,
            roleIds: updatedRoles,
            departmentId: departmentId,
            preserveExistingDepartments: true,
            trackChanges: true,  // Add flag to track changes in audit log
          }),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              // Log the server response for debugging
              console.log("Server response:", data);

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

                console.log(
                  "Updated userRoleDepartments:",
                  userRoleDepartments
                );
              }

              // Close the modal
              addDepartmentRoleModal.style.display = "none";

              // Completely re-render the table with fresh data
              renderUserRolesTable(null, null, null, userSortDirection);

              Toast.success("Roles updated successfully", 5000, "Success");
            } else {
              Toast.error("Failed to update roles", 5000, "Error");
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
              renderUserRolesTable(null, null, null, userSortDirection);
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

  // Initial render of the table
  renderUserRolesTable(null, null, null, userSortDirection);
});
