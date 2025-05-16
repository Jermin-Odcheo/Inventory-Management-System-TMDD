$(document).ready(function() {
    // Department selection handling
    let selectedDepartments = [];
    
    // Initialize the page
    initializeUI();
    
    // Helper: Build a cache-busted URL for reloading content
    function getCacheBustedUrl(selector) {
        var baseUrl = window.location.href.split('#')[0];
        var connector = (baseUrl.indexOf('?') > -1) ? '&' : '?';
        return baseUrl + connector + '_=' + new Date().getTime() + ' ' + selector;
    }
    
    // Function to reload just the table instead of the whole page
    function reloadUserTable() {
        // Save current search and filter values
        const searchValue = $('#search-filters').val();
        const departmentFilter = $('#department-filter').val();
        
        // Also save current pagination state if possible
        const currentPage = $('#currentPage').text();
        const rowsPerPage = $('#rowsPerPageSelect').val();
        
        // Reload the table with the current filters
        $('#umTable').load(getCacheBustedUrl('#umTable'), function() {
            // Re-apply any filters after reload
            if (searchValue) $('#search-filters').val(searchValue);
            if (departmentFilter) $('#department-filter').val(departmentFilter);
            
            // Re-initialize any event handlers for the refreshed table
            updateBulkDeleteButton();
            
            // Update pagination display
            const totalUsers = parseInt($('#total-users').val()) || 0;
            
            // Update pagination text values
            if (totalUsers <= rowsPerPage) {
                $('#currentPage').text('1');
                $('#rowsPerPage').text(totalUsers);
            } else {
                $('#currentPage').text(currentPage);
                $('#rowsPerPage').text(rowsPerPage);
            }
            $('#totalRows').text(totalUsers);
            
            // Show/hide pagination controls
            if (totalUsers <= rowsPerPage) {
                $('#prevPage, #nextPage').addClass('d-none');
                $('#pagination').empty();
            } else {
                $('#prevPage, #nextPage').removeClass('d-none');
                
                // If pagination.js is used
                if (typeof initializePagination === 'function') {
                    initializePagination();
                }
            }
        });
    }
    
    // Manually bind modal open events using Bootstrap 5's API
    $('#create-btn').on('click', function() {
        var createModal = document.getElementById('createUserModal');
        var modal = new bootstrap.Modal(createModal);
        modal.show();
    });
    
    // Use event delegation for dynamically added elements
    $(document).on('click', '.edit-btn', function() {
        const userId = $(this).data('id');
        const email = $(this).data('email');
        const username = $(this).data('username');
        const firstName = $(this).data('first-name');
        const lastName = $(this).data('last-name');
        
        // Set values in form
        $('#editUserID').val(userId);
        $('#editEmail').val(email);
        $('#editUsername').val(username);
        $('#editFirstName').val(firstName);
        $('#editLastName').val(lastName);
        
        // Clear previous department selections
        selectedDepartments = [];
        updateEditDepartmentsDisplay();
        
        // Fetch user's departments
        $.ajax({
            url: 'get_user_departments.php',
            type: 'GET',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Ensure department ids are treated as integers
                    selectedDepartments = response.departments.map(dept => ({
                        id: parseInt(dept.id),
                        name: dept.name
                    }));
                    console.log("Departments loaded:", selectedDepartments);
                    updateEditDepartmentsDisplay();
                } else {
                    console.error("Failed to load departments:", response.message);
                    showToast(response.message || 'Failed to load user departments', 'error', 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching departments:", {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusText: xhr.statusText,
                    readyState: xhr.readyState
                });
                
                // Try to extract more helpful error message from HTML response if present
                let errorMsg = 'Failed to load user departments. Please try again.';
                try {
                    // Look for PHP error message in response
                    if (xhr.responseText.includes('Fatal error') || xhr.responseText.includes('Parse error')) {
                        errorMsg = 'Server error occurred. Please contact administrator.';
                        // Log the full error for debugging
                        console.error("Server error details:", xhr.responseText);
                    }
                } catch (e) {
                    console.error("Error parsing response:", e);
                }
                
                showToast(errorMsg, 'error', 5000);
            }
        });
        
        // Show the modal
        var editModal = document.getElementById('editUserModal');
        var modal = new bootstrap.Modal(editModal);
        modal.show();
    });
    
    $(document).on('click', '.delete-btn', function() {
        const userId = $(this).data('id');
        
        $('#confirmDeleteMessage').text('Are you sure you want to remove this user?');
        $('#confirmDeleteButton').data('ids', [userId]);
        
        var deleteModal = document.getElementById('confirmDeleteModal');
        var modal = new bootstrap.Modal(deleteModal);
        modal.show();
    });
    
    $('#delete-selected').on('click', function() {
        const selectedIds = getSelectedUserIds();
        
        if (selectedIds.length > 0) {
            const message = selectedIds.length === 1 
                ? 'Are you sure you want to remove 1 user?' 
                : `Are you sure you want to remove ${selectedIds.length} users?`;
                
            $('#confirmDeleteMessage').text(message);
            $('#confirmDeleteButton').data('ids', selectedIds);
            
            var deleteModal = document.getElementById('confirmDeleteModal');
            var modal = new bootstrap.Modal(deleteModal);
            modal.show();
        }
    });
    
    // Handle filter changes
    $('#search-filters').on('input', function() {
        applyFilters();
    });
    
    $('#department-filter').on('change', function() {
        applyFilters();
    });

    function applyFilters() {
        window.location.href = window.location.pathname + 
            '?search=' + encodeURIComponent($('#search-filters').val()) + 
            '&department=' + encodeURIComponent($('#department-filter').val());
    }
    
    // when the modal hides, reset everything
    $('#createUserModal').on('hidden.bs.modal', function(){
    // 1) reset the HTML form fields
    $('#createUserForm')[0].reset();

    // 2) clear your department selection state
    selectedDepartments = [];
    updateDepartmentsDisplay();
    });

    // ===== CREATE USER FUNCTIONALITY =====
    $('#createUserForm').on('submit', function(e) {
        e.preventDefault();
        
        // Create a form data object
        const formData = new FormData(this);
        
        // Add department IDs - departments are required
        if (selectedDepartments.length === 0) {
            showToast('At least one department is required', 'error', 3000);
            return; // Prevent form submission
        }
        
        selectedDepartments.forEach((dept, index) => {
            formData.append(`departments[${index}]`, dept.id);
        });
        
        // Roles are now optional - removed default role assignment
        
        $.ajax({
            url: 'create_user.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var createModal = bootstrap.Modal.getInstance(document.getElementById('createUserModal'));
                    if (createModal) {
                        createModal.hide();
                    }
                    
                    // Reset form and selections
                    $('#createUserForm')[0].reset();
                    selectedDepartments = [];
                    updateDepartmentsDisplay();
                    
                    // Reload table and show success message
                    reloadUserTable();
                    showToast('User created successfully!', 'success', 3000);
                } else {
                    showToast(response.message || 'Failed to create user.', 'error', 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error creating user:", error, xhr.responseText);
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    showToast(errorResponse.message || 'Error creating user. Please try again.', 'error', 5000);
                } catch (e) {
                    showToast('Error creating user. Please try again.', 'error', 5000);
                }
            }
        });
    });
    
    // Department selection
    $('#modal_department').on('change', function() {
        const deptId = $(this).val();
        const deptName = $(this).find('option:selected').text();
        
        if (deptId && !selectedDepartments.some(d => d.id === deptId)) {
            selectedDepartments.push({ id: deptId, name: deptName });
            updateDepartmentsDisplay();
        }
        
        // Reset selection
        $(this).val('');
    });
    
    // ===== EDIT USER FUNCTIONALITY =====
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        
        // Create a form data object
        const formData = new FormData(this);
        
        // Add department IDs
        selectedDepartments.forEach((dept, index) => {
            formData.append(`departments[${index}]`, dept.id);
        });
        
        $.ajax({
            url: 'update_user.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                    if (editModal) {
                        editModal.hide();
                    }
                    
                    // Reset selections
                    selectedDepartments = [];
                    
                    // Reload table and show success message
                    reloadUserTable();
                    showToast('User updated successfully!', 'success', 3000);
                } else {
                    showToast(response.message || 'Failed to update user.', 'error', 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error updating user:", error);
                console.error("Response text:", xhr.responseText);
                
                let errorMsg = 'Error updating user. Please try again.';
                
                // Enhanced error detection and reporting
                if (xhr.responseText) {
                    try {
                        // First check if response is JSON
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMsg = errorResponse.message || errorMsg;
                    } catch (e) {
                        // Not JSON, check for known error patterns in HTML response
                        if (xhr.responseText.includes('RBACService.php')) {
                            errorMsg = 'Server configuration error: RBACService.php file not found';
                        } else if (xhr.responseText.includes('Fatal error')) {
                            errorMsg = 'Server error: ' + (xhr.responseText.match(/Fatal error:(.+?)<br/i)?.[1]?.trim() || 'Unknown fatal error occurred');
                        } else if (xhr.responseText.includes('Warning')) {
                            errorMsg = 'Server warning: ' + (xhr.responseText.match(/Warning:(.+?)<br/i)?.[1]?.trim() || 'Unknown warning occurred');
                        }
                    }
                }
                
                showToast(errorMsg, 'error', 5000);
            }
        });
    });
    
    $('#editDepartments').on('change', function() {
        const deptId = parseInt($(this).val());
        const deptName = $(this).find('option:selected').text();
        
        if (deptId && !selectedDepartments.some(d => parseInt(d.id) === deptId)) {
            selectedDepartments.push({ id: deptId, name: deptName });
            console.log("Department added:", { id: deptId, name: deptName });
            console.log("Current departments:", selectedDepartments);
            updateEditDepartmentsDisplay();
        }
        
        // Reset selection
        $(this).val('');
    });
    
    // ===== DELETE USER FUNCTIONALITY =====
    // Handle delete confirmation
    $('#confirmDeleteButton').on('click', function() {
        const userIds = $(this).data('ids');
        
        const data = userIds.length === 1 
            ? { user_id: userIds[0] }  // Single delete
            : { user_ids: userIds };   // Bulk delete
        
        $.ajax({
            url: 'delete_user.php',  // Correct endpoint
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var deleteModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                    if (deleteModal) {
                        deleteModal.hide();
                    }
                    
                    // Reload table and show success message
                    reloadUserTable();
                    showToast(response.message || 'User(s) removed successfully!', 'success', 3000);
                } else {
                    showToast(response.message || 'Failed to remove user(s).', 'error', 5000);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error deleting user:", error);
                console.error("Response text:", xhr.responseText);
                
                let errorMsg = 'Error removing user. Please try again.';
                
                // Enhanced error detection and reporting
                if (xhr.responseText) {
                    try {
                        // First check if response is JSON
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMsg = errorResponse.message || errorMsg;
                    } catch (e) {
                        // Not JSON, check for known error patterns in HTML response
                        if (xhr.responseText.includes('RBACService')) {
                            errorMsg = 'Server configuration error: RBACService class not found';
                        } else if (xhr.responseText.includes('Fatal error')) {
                            errorMsg = 'Server error: ' + (xhr.responseText.match(/Fatal error:(.+?)<br/i)?.[1]?.trim() || 'Unknown fatal error occurred');
                        } else if (xhr.responseText.includes('Warning')) {
                            errorMsg = 'Server warning: ' + (xhr.responseText.match(/Warning:(.+?)<br/i)?.[1]?.trim() || 'Unknown warning occurred');
                        }
                    }
                }
                
                showToast(errorMsg, 'error', 5000);
            }
        });
    });
    
    // ===== BULK DELETE FUNCTIONALITY =====
    // Select all checkbox
    $('#select-all').on('change', function() {
        $('.select-row').prop('checked', $(this).prop('checked'));
        updateBulkDeleteButton();
    });
    
    // Individual checkboxes - use event delegation for dynamic content
    $(document).on('change', '.select-row', function() {
        updateBulkDeleteButton();
    });
    
    // ===== HELPER FUNCTIONS =====
    function initializeUI() {
        // Initialize any UI elements as needed
        updateBulkDeleteButton();
        
        // Password toggle visibility
        $('.toggle-password').on('click', function() {
            const passwordField = $(this).closest('.input-group').find('input');
            const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
            passwordField.attr('type', type);
            $(this).find('i').toggleClass('bi-eye bi-eye-slash');
        });
        
        // Close modal buttons
        $('.btn-close, .modal .btn-secondary').on('click', function() {
            var modalEl = $(this).closest('.modal')[0];
            var modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    }
    
    function updateBulkDeleteButton() {
        const selectedCount = $('.select-row:checked').length;
        
        if (selectedCount >= 2) {
            $('#delete-selected').show().prop('disabled', false);
        } else {
            $('#delete-selected').hide().prop('disabled', true);
        }
    }
    
    function getSelectedUserIds() {
        return $('.select-row:checked').map(function() {
            return $(this).val();
        }).get();
    }
    
    function updateDepartmentsDisplay() {
        // Update create user departments display
        const $list = $('#createAssignedDepartmentsList');
        const $table = $('#createAssignedDepartmentsTable tbody');
        
        $list.empty();
        $table.empty();
        
        selectedDepartments.forEach(function(dept) {
            // Add badge to list
            $list.append(`
                <span class="badge bg-primary me-1 mb-1">${dept.name}</span>
            `);
            
            // Add row to table
            $table.append(`
                <tr data-dept-id="${dept.id}">
                    <td>${dept.name}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-dept" data-dept-id="${dept.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
        
        // Add event handlers for removal buttons
        $('.remove-dept').on('click', function() {
            const deptId = $(this).data('dept-id');
            selectedDepartments = selectedDepartments.filter(d => d.id !== deptId);
            updateDepartmentsDisplay();
        });
    }
    
    function updateEditDepartmentsDisplay() {
        // Update edit user departments display
        const $list = $('#assignedDepartmentsList');
        const $table = $('#assignedDepartmentsTable tbody');
        
        $list.empty();
        $table.empty();
        
        console.log("Displaying departments:", selectedDepartments);
        
        selectedDepartments.forEach(function(dept) {
            // Add badge to list
            $list.append(`
                <span class="badge bg-primary me-1 mb-1">${dept.name}</span>
            `);
            
            // Add row to table
            $table.append(`
                <tr data-dept-id="${dept.id}">
                    <td>${dept.name}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-edit-dept" data-dept-id="${dept.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
        
        // Add event handlers for removal buttons
        $('.remove-edit-dept').on('click', function() {
            const deptId = parseInt($(this).data('dept-id'));
            selectedDepartments = selectedDepartments.filter(d => parseInt(d.id) !== deptId);
            console.log("Department removed, remaining:", selectedDepartments);
            updateEditDepartmentsDisplay();
        });
    }
});
