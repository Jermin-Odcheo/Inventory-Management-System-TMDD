$(document).ready(function() {
    // Department selection handling
    let selectedDepartments = [];
    
    // Initialize the page
    initializeUI();
    
    // Add CSS styles for loading overlay
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 255, 255, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }
        `)
        .appendTo('head');
    
    // Email validation function
    function validateEmail(email) {
        const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        return regex.test(email);
    }
    
    // Toast notification system
    window.Toast = {
        container: null,
        init: function() {
            // Create toast container if it doesn't exist
            if (!document.querySelector('.toast-container')) {
                this.container = document.createElement('div');
                this.container.className = 'toast-container position-fixed top-0 end-0 p-3';
                document.body.appendChild(this.container);
            } else {
                this.container = document.querySelector('.toast-container');
            }
        },
        create: function(message, type) {
            if (!this.container) this.init();
            
            const toastId = 'toast-' + Date.now();
            const toastEl = document.createElement('div');
            toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.setAttribute('id', toastId);
            
            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            this.container.appendChild(toastEl);
            
            const toast = new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 5000
            });
            
            toast.show();
            
            // Remove from DOM after hidden
            toastEl.addEventListener('hidden.bs.toast', function() {
                toastEl.remove();
            });
            
            return toast;
        },
        success: function(message) {
            return this.create(message, 'success');
        },
        error: function(message) {
            return this.create(message, 'danger');
        },
        warning: function(message) {
            return this.create(message, 'warning');
        },
        info: function(message) {
            return this.create(message, 'info');
        }
    };
    
    // Initialize Toast
    Toast.init();
    
    // Helper: Build a cache-busted URL for reloading content
    function getCacheBustedUrl(selector) {
        var baseUrl = window.location.href.split('#')[0];
        var connector = (baseUrl.indexOf('?') > -1) ? '&' : '?';
        return baseUrl + connector + '_=' + new Date().getTime() + ' ' + selector;
    }
    
    // Function to reload just the table instead of the whole page
    function reloadUserTable() {
        // Store current search and filter values
        const searchValue = $('#search-filters').val();
        const departmentFilter = $('#department-filter').val();
        
        // Store current pagination state
        const currentPage = window.paginationConfig ? window.paginationConfig.currentPage : 1;
        const rowsPerPage = parseInt($('#rowsPerPageSelect').val()) || 10;
        
        // Store current scroll position
        const scrollPosition = window.scrollY || document.documentElement.scrollTop;
        
        // Ensure modals are properly cleaned up
        $('.modal').modal('hide');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
        
        // Show a loading indicator
        const loadingOverlay = $('<div class="loading-overlay"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        $('#table').append(loadingOverlay);
        
        $.ajax({
            url: window.location.href,
            type: 'GET',
            success: function(response) {
                // Extract just the table HTML from the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(response, 'text/html');
                const newTable = doc.querySelector('#umTable');
                
                if (newTable) {
                    // Replace the current table with the new one
                    $('#umTable').replaceWith(newTable);
                    
                    // Re-apply any filters after reload
                    if (searchValue) $('#search-filters').val(searchValue);
                    if (departmentFilter) $('#department-filter').val(departmentFilter);
                    
                    // Re-initialize any event handlers for the refreshed table
                    updateBulkDeleteButton();
                    
                    // Reset the global arrays for pagination
                    window.allRows = Array.from(document.querySelectorAll('#umTableBody tr'));
                    window.filteredRows = window.allRows;
                    
                    // Restore pagination state
                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = currentPage;
                    }
                    
                    // Reinitialize pagination
                    if (typeof updatePagination === 'function') {
                        updatePagination();
                        if (typeof window.forcePaginationCheck === 'function') {
                            setTimeout(window.forcePaginationCheck, 100);
                        }
                    }
                    
                    // Apply filters if needed
                    if (searchValue || (departmentFilter && departmentFilter !== 'all')) {
                        filterTable();
                    }
                    
                    // Remove loading overlay
                    $('.loading-overlay').remove();
                    
                    // Restore scroll position
                    setTimeout(function() {
                        window.scrollTo(0, scrollPosition);
                    }, 100);
                    
                    // Show success message
                    Toast.success('Data updated successfully');
                } else {
                    console.error('Could not find table in response');
                    $('.loading-overlay').remove();
                    Toast.error('Failed to refresh data. Please reload the page.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error refreshing table:', error);
                $('.loading-overlay').remove();
                Toast.error('Failed to refresh data. Please reload the page.');
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
        $(".modal-backdrop").hide();
        const userId = $(this).data('id');
        const email = $(this).data('email');
        const username = $(this).data('username');
        const firstName = $(this).data('first-name');
        const lastName = $(this).data('last-name');
        
        console.log("Opening edit modal for user:", userId, email, username);
        
        // Set values in form
        $('#editUserID').val(userId);
        $('#editEmail').val(email);
        $('#editUsername').val(username);
        $('#editFirstName').val(firstName);
        $('#editLastName').val(lastName);
        
        // Clear previous department selections and hidden inputs
        selectedDepartments = [];
        $('#editUserForm input[name="departments[]"]').remove();
        $('#editUserForm input[name="no_departments"]').remove();
        
        // Immediately add a hidden input with the user ID to ensure we can identify the user
        // even if the AJAX call fails
        $('<input>').attr({
            type: 'hidden',
            name: 'user_id_backup',
            value: userId
        }).appendTo($('#editUserForm'));
        
        // Fetch user's departments
        console.log("Fetching departments for user ID:", userId);
        $.ajax({
            url: 'get_user_departments.php',
            type: 'GET',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                console.log("Department fetch response:", response);
                if (response.success && response.departments && response.departments.length > 0) {
                    // Ensure department ids are treated as integers
                    selectedDepartments = response.departments.map(dept => ({
                        id: parseInt(dept.id),
                        name: dept.name
                    }));
                    console.log("Departments loaded successfully:", selectedDepartments);
                    updateEditDepartmentsDisplay();
                    
                    // Also add the departments directly to the form as hidden inputs
                    const $form = $('#editUserForm');
                    selectedDepartments.forEach(function(dept) {
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'departments[]',
                            value: dept.id
                        }).appendTo($form);
                    });
                } else {
                    console.error("Failed to load departments or no departments returned:", response);
                    if (response.success && (!response.departments || response.departments.length === 0)) {
                        Toast.warning('User has no departments assigned');
                    } else {
                        Toast.error(response.message || 'Failed to load user departments');
                    }
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
                
                // Try to extract JSON from HTML response if it exists
                try {
                    const responseText = xhr.responseText;
                    console.log("Attempting to extract JSON from response:", responseText);
                    
                    // Direct attempt to extract the JSON portion by finding the first {
                    const jsonStartIndex = responseText.indexOf('{');
                    const jsonEndIndex = responseText.lastIndexOf('}') + 1;
                    
                    if (jsonStartIndex >= 0 && jsonEndIndex > jsonStartIndex) {
                        const jsonString = responseText.substring(jsonStartIndex, jsonEndIndex);
                        console.log("Extracted JSON string:", jsonString);
                        
                        try {
                            const jsonData = JSON.parse(jsonString);
                            console.log("Successfully parsed JSON:", jsonData);
                            
                            // Process the JSON response
                            if (jsonData.success) {
                                // Process departments
                                selectedDepartments = jsonData.departments.map(dept => ({
                                    id: parseInt(dept.id),
                                    name: dept.name
                                }));
                                console.log("Departments loaded from error handler:", selectedDepartments);
                                updateEditDepartmentsDisplay();
                                return;
                            } else {
                                // Display the error message from the JSON
                                Toast.error(jsonData.message || 'Failed to load user departments');
                                return;
                            }
                        } catch (parseError) {
                            console.error("Error parsing extracted JSON:", parseError);
                        }
                    } else {
                        console.error("Could not find valid JSON start/end in response");
                    }
                    
                    // More robust pattern as a fallback
                    const jsonRegex = /(\{[\s\S]*?\})/g;
                    let match;
                    let lastMatch;
                    let matches = [];
                    
                    while ((match = jsonRegex.exec(responseText)) !== null) {
                        lastMatch = match[1];
                        matches.push(lastMatch);
                    }
                    
                    console.log("All JSON-like matches found:", matches);
                    
                    if (lastMatch) {
                        try {
                            // Try to parse the extracted JSON
                            const jsonData = JSON.parse(lastMatch);
                            console.log("Successfully extracted JSON from response with warnings:", jsonData);
                            
                            // Process the JSON response
                            if (jsonData.success) {
                                // Process departments even though there were warnings
                                selectedDepartments = jsonData.departments.map(dept => ({
                                    id: parseInt(dept.id),
                                    name: dept.name
                                }));
                                console.log("Departments loaded from regex fallback:", selectedDepartments);
                                updateEditDepartmentsDisplay();
                                return;
                            } else {
                                // Display the error message from the JSON
                                Toast.error(jsonData.message || 'Failed to load user departments');
                                return;
                            }
                        } catch (parseError) {
                            console.error("Error parsing extracted JSON:", parseError, "Extracted text:", lastMatch);
                        }
                    }
                } catch (e) {
                    console.error("Error extracting JSON from response:", e);
                }
                
                // Fallback error handling if JSON extraction fails
                let errorMsg = 'Failed to load user departments. Please try again.';
                if (responseText) {
                    // Look for PHP error message in response
                    if (responseText.includes('Fatal error')) {
                        errorMsg = 'Server error occurred. Please contact administrator.';
                    } else if (responseText.includes('Warning')) {
                        errorMsg = 'Server warning occurred but may have succeeded. Try reloading.';
                    }
                }
                
                Toast.error(errorMsg);
            }
        });
        
        // Show the modal
        var editModal = document.getElementById('editUserModal');
        var modal = new bootstrap.Modal(editModal);
        modal.show();
    });
    
    // Handle delete button clicks
    $(document).on('click', '.delete-btn', function() {
        const userId = $(this).data('id');
        $('#confirmDeleteMessage').text('Are you sure you want to remove this user?');
        $('#confirmDeleteButton').data('id', userId);
        $('#confirmDeleteButton').data('type', 'single');
        
        // Show the modal with compact sizing
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        confirmModal.show();
    });
    
    // Handle bulk delete button click
    $('#delete-selected').on('click', function() {
        const selectedIds = [];
        $('.select-row:checked').each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length > 0) {
            $('#confirmDeleteMessage').text(`Are you sure you want to remove ${selectedIds.length} selected users?`);
            $('#confirmDeleteButton').data('type', 'multiple');
            
            // Show the modal
            const confirmModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            confirmModal.show();
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
            Toast.error('At least one department is required');
            return false;
        }
        
        // Ensure department IDs are integers
        selectedDepartments.forEach((dept, index) => {
            // Make sure dept.id is treated as an integer
            const deptId = parseInt(dept.id);
            if (!isNaN(deptId)) {
                formData.append(`departments[${index}]`, deptId);
                console.log(`Adding department ${dept.name} with ID: ${deptId}`);
            }
        });
        
        // Log all form data for debugging
        console.log("Form data departments:");
        for (let pair of formData.entries()) {
            if (pair[0].includes('departments')) {
                console.log(pair[0], pair[1], typeof pair[1]);
            }
        }
        
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
                    Toast.success('User created successfully!');
                } else {
                    Toast.error(response.message || 'Failed to create user.');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error creating user:", error, xhr.responseText);
                
                try {
                    const responseText = xhr.responseText;
                    console.log("Attempting to extract JSON from response:", responseText);
                    
                    // Check specifically for duplicate username error
                    if (responseText.includes('Username already exists') || 
                        responseText.includes('username is already taken') || 
                        responseText.includes('Duplicate entry') && responseText.includes('username')) {
                        Toast.error('Username already exists. Please choose a different username.');
                        return;
                    }
                    
                    // Direct attempt to extract the JSON portion by finding the first {
                    const jsonStartIndex = responseText.indexOf('{');
                    const jsonEndIndex = responseText.lastIndexOf('}') + 1;
                    
                    if (jsonStartIndex >= 0 && jsonEndIndex > jsonStartIndex) {
                        const jsonString = responseText.substring(jsonStartIndex, jsonEndIndex);
                        console.log("Extracted JSON string:", jsonString);
                        
                        try {
                            const jsonData = JSON.parse(jsonString);
                            console.log("Successfully parsed JSON:", jsonData);
                            
                            // Process the JSON response
                            if (jsonData.success) {
                                // Process success even though there were warnings
                                Toast.success('User created successfully!');
                                
                                // Reset form and close modal
                                $('#createUserForm')[0].reset();
                                selectedDepartments = [];
                                updateDepartmentsDisplay();
                                
                                // Close the modal
                                var createModal = bootstrap.Modal.getInstance(document.getElementById('createUserModal'));
                                if (createModal) {
                                    createModal.hide();
                                }
                                
                                // Reload table
                                reloadUserTable();
                                return;
                            } else {
                                // Display the error message from the JSON
                                Toast.error(jsonData.message || 'Failed to create user');
                                return;
                            }
                        } catch (parseError) {
                            console.error("Error parsing extracted JSON:", parseError);
                        }
                    }
                } catch (e) {
                    console.error("Error extracting JSON from response:", e);
                }
                
                // If we couldn't extract JSON, try standard JSON parsing
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    Toast.error(errorResponse.message || 'Error creating user. Please try again.');
                } catch (e) {
                    // If even that fails, provide a generic error
                    let errorMsg = 'Error creating user. Please try again.';
                    
                    // Try to extract error from HTML response
                    if (xhr.responseText.includes('Fatal error')) {
                        errorMsg = 'Server error: ' + (xhr.responseText.match(/Fatal error:(.+?)<br/i)?.[1]?.trim() || 'Unknown error occurred');
                    } else if (xhr.responseText.includes('Warning')) {
                        errorMsg = 'Server warning occurred. Please try again.';
                    } else if (xhr.responseText.includes('username is already taken') || xhr.responseText.includes('Username already exists')) {
                        errorMsg = 'Username already exists. Please choose a different username.';
                    } else if (xhr.responseText.includes('Duplicate entry') && xhr.responseText.includes('username')) {
                        errorMsg = 'Username already exists. Please choose a different username.';
                    }
                    
                    Toast.error(errorMsg);
                }
            }
        });
    });
    
    // Department selection
    $('#modal_department').on('change', function() {
        const deptIdRaw = $(this).val();
        const deptId = parseInt(deptIdRaw);
        const deptName = $(this).find('option:selected').text();
        
        if (deptId && !isNaN(deptId) && !selectedDepartments.some(d => parseInt(d.id) === deptId)) {
            console.log(`Adding department: ${deptName} with ID: ${deptId}`);
            selectedDepartments.push({ id: deptId, name: deptName });
            updateDepartmentsDisplay();
        }
        
        // Reset selection
        $(this).val('');
    });
    
    // ===== EDIT USER FUNCTIONALITY =====
    $('#submitEditUser').on('click', function() {
        const form = $('#editUserForm');
        const formData = new FormData(form[0]);
        
        // Make sure we have the user ID
        const userId = $('#editUserID').val();
        if (!userId) {
            Toast.error('User ID is missing');
            return;
        }
        
        // Validate email has domain
        const email = $('#editEmail').val();
        if (!validateEmail(email)) {
            $('#editEmail').addClass('is-invalid');
            return;
        } else {
            $('#editEmail').removeClass('is-invalid');
        }
        
        // Get existing departments from hidden inputs
        const hiddenDepts = form.find('input[name="departments[]"]');
        
        // Log what departments we have
        console.log("Hidden department inputs:", hiddenDepts.length);
        hiddenDepts.each(function() {
            console.log("Department input value:", $(this).val());
        });
        
        // Check if we have departments
        if (hiddenDepts.length === 0) {
            // No departments in hidden inputs, check the table
            const departmentRows = $('#assignedDepartmentsTable tbody tr');
            
            if (departmentRows.length === 0) {
                // No departments in table either, check selectedDepartments array
                if (!selectedDepartments || selectedDepartments.length === 0) {
                    // No departments anywhere - this is an error
                    Toast.error('At least one department must be assigned');
                    return;
                }
                
                // We have departments in the array, add them to the form
                console.log("Adding departments from selectedDepartments array:", selectedDepartments.length);
                selectedDepartments.forEach(function(dept, index) {
                    const deptId = parseInt(dept.id);
                    if (!isNaN(deptId)) {
                        formData.append(`departments[${index}]`, deptId);
                        
                        // Also add hidden input for future reference
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'departments[]',
                            value: deptId
                        }).appendTo(form);
                    }
                });
            } else {
                // We have departments in the table, use those
                console.log("Adding departments from table rows:", departmentRows.length);
                departmentRows.each(function(index) {
                    const deptId = $(this).data('department-id');
                    if (deptId) {
                        formData.append(`departments[${index}]`, deptId);
                        
                        // Also add hidden input for future reference
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'departments[]',
                            value: deptId
                        }).appendTo(form);
                    }
                });
            }
        } else {
            // We already have departments in hidden inputs, make sure they're in the formData
            console.log("Using existing hidden department inputs:", hiddenDepts.length);
            hiddenDepts.each(function(index) {
                const deptId = $(this).val();
                formData.append(`departments[${index}]`, deptId);
            });
        }
        
        // Final check - if we still don't have departments, add a special flag to tell the server
        // to use the user's existing departments
        if (!formData.has('departments[0]')) {
            console.warn("No departments found, adding use_existing_departments flag");
            formData.append('use_existing_departments', '1');
        }
        
        // Log form data for debugging
        console.log("Form data departments:");
        for (let pair of formData.entries()) {
            if (pair[0].includes('departments') || pair[0].includes('use_existing')) {
                console.log(pair[0], pair[1]);
            }
        }
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        Toast.success('User updated successfully');
                        $('#editUserModal').modal('hide');
                        // Reload table after successful update
                        reloadUserTable();
                    } else {
                        Toast.error(result.message || 'Failed to update user');
                    }
                } catch (e) {
                    // Handle non-JSON responses which might still indicate success
                    if (typeof response === 'string' && response.includes('success')) {
                        Toast.success('User updated successfully');
                        $('#editUserModal').modal('hide');
                        // Reload table after successful update
                        reloadUserTable();
                    } else {
                        Toast.error('Error processing response');
                        console.error('Error parsing response:', e, response);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("Error updating user:", error);
                console.error("Response text:", xhr.responseText);
                
                try {
                    const responseText = xhr.responseText;
                    console.log("Attempting to extract JSON from response:", responseText);
                    
                    // Check specifically for duplicate username error
                    if (responseText.includes('Username already exists') || 
                        responseText.includes('username is already taken') || 
                        responseText.includes('Duplicate entry') && responseText.includes('username')) {
                        Toast.error('Username already exists. Please choose a different username.');
                        return;
                    }
                    
                    // Direct attempt to extract the JSON portion by finding the first {
                    const jsonStartIndex = responseText.indexOf('{');
                    const jsonEndIndex = responseText.lastIndexOf('}') + 1;
                    
                    if (jsonStartIndex >= 0 && jsonEndIndex > jsonStartIndex) {
                        const jsonString = responseText.substring(jsonStartIndex, jsonEndIndex);
                        console.log("Extracted JSON string:", jsonString);
                        
                        try {
                            const jsonData = JSON.parse(jsonString);
                            console.log("Successfully parsed JSON:", jsonData);
                            
                            // Process the JSON response
                            if (jsonData.success) {
                                // Process success even though there were warnings
                                Toast.success('User updated successfully!');
                                
                                // Reset selections and close modal
                                selectedDepartments = [];
                                
                                // Close the modal
                                var editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                                if (editModal) {
                                    editModal.hide();
                                }
                                
                                // Reload table
                                reloadUserTable();
                                return;
                            } else {
                                // Display the error message from the JSON
                                Toast.error(jsonData.message || 'Failed to update user');
                                return;
                            }
                        } catch (parseError) {
                            console.error("Error parsing extracted JSON:", parseError);
                        }
                    }
                } catch (e) {
                    console.error("Error extracting JSON from response:", e);
                }
                
                // If we couldn't extract JSON, use our fallback error detection
                let errorMsg = 'Error updating user. Please try again.';
                
                // Enhanced error detection and reporting
                if (responseText) {
                    try {
                        // First check if response is JSON
                        const errorResponse = JSON.parse(responseText);
                        errorMsg = errorResponse.message || errorMsg;
                    } catch (e) {
                        // Not JSON, check for known error patterns in HTML response
                        if (responseText.includes('RBACService.php')) {
                            errorMsg = 'Server configuration error: RBACService.php file not found';
                        } else if (responseText.includes('Fatal error')) {
                            errorMsg = 'Server error: ' + (responseText.match(/Fatal error:(.+?)<br/i)?.[1]?.trim() || 'Unknown fatal error occurred');
                        } else if (responseText.includes('Warning')) {
                            errorMsg = 'Server warning: ' + (responseText.match(/Warning:(.+?)<br/i)?.[1]?.trim() || 'Unknown warning occurred');
                        } else if (responseText.includes('username is already taken') || responseText.includes('Username already exists')) {
                            errorMsg = 'Username already exists. Please choose a different username.';
                        } else if (responseText.includes('Duplicate entry') && responseText.includes('username')) {
                            errorMsg = 'Username already exists. Please choose a different username.';
                        }
                    }
                }
                
                Toast.error(errorMsg);
            }
        });
    });
    
    $('#editDepartments').on('change', function() {
        const deptIdRaw = $(this).val();
        const deptId = parseInt(deptIdRaw);
        const deptName = $(this).find('option:selected').text();
        
        if (deptId && !isNaN(deptId) && !selectedDepartments.some(d => parseInt(d.id) === deptId)) {
            console.log(`Adding department: ${deptName} with ID: ${deptId}`);
            selectedDepartments.push({ id: deptId, name: deptName });
            updateEditDepartmentsDisplay();
        }
        
        // Reset selection
        $(this).val('');
    });
    
    // ===== DELETE USER FUNCTIONALITY =====
    // Handle confirmation of deletion
    $('#confirmDeleteButton').on('click', function() {
        const type = $(this).data('type');
        
        if (type === 'single') {
            const userId = $(this).data('id');
            deleteUser(userId);
        } else if (type === 'multiple') {
            const selectedIds = [];
            $('.select-row:checked').each(function() {
                selectedIds.push($(this).val());
            });
            deleteMultipleUsers(selectedIds);
        }
        
        // Hide the modal
        const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
        confirmModal.hide();
    });
    
    // Function to delete a single user
    function deleteUser(userId) {
        $.ajax({
            url: 'delete_user.php',
            type: 'POST',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Reload the table instead of just removing the row
                    reloadUserTable();
                    Toast.success('User removed successfully');
                } else {
                    Toast.error(response.message || 'Failed to remove user');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error deleting user:", error, xhr.responseText);
                
                try {
                    const responseText = xhr.responseText;
                    console.log("Attempting to extract JSON from response:", responseText);
                    
                    // Direct attempt to extract the JSON portion by finding the first {
                    const jsonStartIndex = responseText.indexOf('{');
                    const jsonEndIndex = responseText.lastIndexOf('}') + 1;
                    
                    if (jsonStartIndex >= 0 && jsonEndIndex > jsonStartIndex) {
                        const jsonString = responseText.substring(jsonStartIndex, jsonEndIndex);
                        console.log("Extracted JSON string:", jsonString);
                        
                        try {
                            const jsonData = JSON.parse(jsonString);
                            console.log("Successfully parsed JSON:", jsonData);
                            
                            // Process the JSON response
                            if (jsonData.success) {
                                // Process success even though there were warnings
                                reloadUserTable();
                                Toast.success('User removed successfully');
                                return;
                            } else {
                                // Display the error message from the JSON
                                Toast.error(jsonData.message || 'Failed to remove user');
                                return;
                            }
                        } catch (parseError) {
                            console.error("Error parsing extracted JSON:", parseError);
                        }
                    }
                } catch (e) {
                    console.error("Error extracting JSON from response:", e);
                }
                
                Toast.error('An error occurred while processing your request');
            }
        });
    }
    
    // Function to delete multiple users
    function deleteMultipleUsers(userIds) {
        $.ajax({
            url: 'delete_user.php',
            type: 'POST',
            data: { user_ids: userIds },
            dataType: 'json',
            success: function(response) {
                if (
                  response.status &&
                  response.status.toLowerCase() === "success"
                ) {
                  // Reload the table instead of just removing rows
                  reloadUserTable();
                  Toast.success("Selected users removed successfully");
                  $("#delete-selected").hide().prop("disabled", true);
                } else {
                  Toast.error(
                    response.message || "Failed to remove users"
                  );
                }
            },
            error: function(xhr, status, error) {
                console.error("Error deleting multiple users:", error, xhr.responseText);
                
                try {
                    const responseText = xhr.responseText;
                    console.log("Attempting to extract JSON from response:", responseText);
                    
                    // Direct attempt to extract the JSON portion by finding the first {
                    const jsonStartIndex = responseText.indexOf('{');
                    const jsonEndIndex = responseText.lastIndexOf('}') + 1;
                    
                    if (jsonStartIndex >= 0 && jsonEndIndex > jsonStartIndex) {
                        const jsonString = responseText.substring(jsonStartIndex, jsonEndIndex);
                        console.log("Extracted JSON string:", jsonString);
                        
                        try {
                            const jsonData = JSON.parse(jsonString);
                            console.log("Successfully parsed JSON:", jsonData);
                            
                            // Process the JSON response
                            if (jsonData.success || (jsonData.status && jsonData.status.toLowerCase() === "success")) {
                                // Process success even though there were warnings
                                reloadUserTable();
                                Toast.success("Selected users removed successfully");
                                $("#delete-selected").hide().prop("disabled", true);
                                return;
                            } else {
                                // Display the error message from the JSON
                                Toast.error(jsonData.message || 'Failed to remove users');
                                return;
                            }
                        } catch (parseError) {
                            console.error("Error parsing extracted JSON:", parseError);
                        }
                    }
                } catch (e) {
                    console.error("Error extracting JSON from response:", e);
                }
                
                Toast.error('An error occurred while processing your request');
            }
        });
    }
    
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
        
        // Debug log the departments
        console.log("Current create departments:", selectedDepartments);
        
        selectedDepartments.forEach(function(dept) {
            // Ensure ID is an integer
            const deptId = parseInt(dept.id);
            
            // Add badge to list
            $list.append(`
                <span class="badge bg-primary me-1 mb-1">${dept.name}</span>
            `);
            
            // Add row to table
            $table.append(`
                <tr data-department-id="${deptId}">
                    <td>${dept.name}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-dept" data-dept-id="${deptId}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
        
        // Add event handlers for removal buttons
        $('.remove-dept').on('click', function() {
            const deptId = parseInt($(this).data('dept-id'));
            console.log(`Removing department with ID: ${deptId}`);
            
            // Filter out the removed department, ensuring integer comparison
            selectedDepartments = selectedDepartments.filter(d => parseInt(d.id) !== deptId);
            console.log("Departments after removal:", selectedDepartments);
            
            // Update display
            updateDepartmentsDisplay();
        });
    }
    
    function updateEditDepartmentsDisplay() {
        // Update edit user departments display
        const $list = $('#assignedDepartmentsList');
        const $table = $('#assignedDepartmentsTable tbody');
        const $form = $('#editUserForm');
        
        $list.empty();
        $table.empty();
        
        // Remove existing department inputs
        $form.find('input[name="departments[]"]').remove();
        
        // Debug log the departments
        console.log("Updating edit departments display with:", selectedDepartments);
        
        if (!selectedDepartments || selectedDepartments.length === 0) {
            console.warn("No departments to display in edit modal");
            // Add a hidden input to indicate no departments were selected
            $('<input>').attr({
                type: 'hidden',
                name: 'no_departments',
                value: '1'
            }).appendTo($form);
            return;
        }
        
        // Remove any previous no_departments flag
        $form.find('input[name="no_departments"]').remove();
        
        selectedDepartments.forEach(function(dept) {
            // Ensure ID is an integer
            const deptId = parseInt(dept.id);
            if (isNaN(deptId)) {
                console.error("Invalid department ID:", dept.id);
                return;
            }
            
            // Add badge to list
            $list.append(`
                <span class="badge bg-primary me-1 mb-1">${dept.name}</span>
            `);
            
            // Add row to table
            $table.append(`
                <tr data-department-id="${deptId}">
                    <td>${dept.name}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-edit-dept" data-dept-id="${deptId}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
            
            // Also add hidden inputs for each department
            $('<input>').attr({
                type: 'hidden',
                name: 'departments[]',
                value: deptId
            }).appendTo($form);
        });
        
        // Add event handlers for removal buttons
        $('.remove-edit-dept').on('click', function() {
            const deptId = parseInt($(this).data('dept-id'));
            console.log(`Removing department with ID: ${deptId}`);
            
            // Filter out the removed department, ensuring integer comparison
            selectedDepartments = selectedDepartments.filter(d => parseInt(d.id) !== deptId);
            console.log("Departments after removal:", selectedDepartments);
            
            // Remove the hidden input for this department
            $form.find(`input[name="departments[]"][value="${deptId}"]`).remove();
            
            // Update display
            updateEditDepartmentsDisplay();
        });
    }
});

// Ensure modals are properly initialized and configured
document.addEventListener('DOMContentLoaded', function() {
    // Fix modal backdrop issue
    const fixModalBackdrop = () => {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                setTimeout(() => {
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.style.zIndex = '1040';
                    }
                    modal.style.zIndex = '1050';
                }, 0);
            });
        });
    };
    
    fixModalBackdrop();
    
    // Rest of your existing code...
});
