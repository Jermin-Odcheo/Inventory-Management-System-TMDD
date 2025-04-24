$(document).ready(function () {

    // Helper: Build a cache-busted URL for reloading content.
    function getCacheBustedUrl(selector) {
        var baseUrl = location.href.split('#')[0];
        var connector = (baseUrl.indexOf('?') > -1) ? '&' : '?';
        return baseUrl + connector + '_=' + new Date().getTime() + ' ' + selector;
    }

    // Toggle custom department input
    $('#modal_department').on('change', function () {
        if ($(this).val() === 'custom') {
            $('#modal_custom_department').show().attr('required', true);
        } else {
            $('#modal_custom_department').hide().attr('required', false);
        }
    });

    // Password visibility toggle
    $('.toggle-password').on('click', function() {
        const passwordField = $(this).closest('.input-group').find('input');
        const passwordType = passwordField.attr('type');
        
        if (passwordType === 'password') {
            passwordField.attr('type', 'text');
            $(this).find('i').removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            passwordField.attr('type', 'password');
            $(this).find('i').removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });
    
    // Password strength meter
    $('#password').on('input', function() {
        const password = $(this).val();
        const strengthMeter = $(this).closest('.mb-3').find('.password-strength');
        
        if (password.length > 0) {
            strengthMeter.removeClass('d-none');
            
            // Calculate password strength
            let strength = 0;
            const progressBar = strengthMeter.find('.progress-bar');
            
            // Length check
            if (password.length >= 8) strength += 1;
            
            // Character variety checks
            if (/[A-Z]/.test(password)) strength += 1; // Uppercase
            if (/[a-z]/.test(password)) strength += 1; // Lowercase
            if (/[0-9]/.test(password)) strength += 1; // Numbers
            if (/[^A-Za-z0-9]/.test(password)) strength += 1; // Special chars
            
            // Update UI based on strength
            progressBar.removeClass('weak medium strong');
            let strengthText = '';
            let percentage = 0;
            
            if (strength <= 2) {
                progressBar.addClass('weak');
                strengthText = 'Weak';
                percentage = 25;
            } else if (strength <= 3) {
                progressBar.addClass('medium');
                strengthText = 'Medium';
                percentage = 50;
            } else {
                progressBar.addClass('strong');
                strengthText = 'Strong';
                percentage = 100;
            }
            
            progressBar.css('width', percentage + '%');
            strengthMeter.find('.strength-text').text(strengthText);
        } else {
            strengthMeter.addClass('d-none');
        }
    });

    // Reset password strength when modal is closed
    $('#createUserModal').on('hidden.bs.modal', function() {
        const strengthMeter = $(this).find('.password-strength');
        strengthMeter.addClass('d-none');
        strengthMeter.find('.progress-bar').css('width', '0%').removeClass('weak medium strong');
        strengthMeter.find('.strength-text').text('Password strength');
    });

    // Handle "Create User" form submission via AJAX
    $('#createUserForm').on('submit', function (e) {
        e.preventDefault();
        
        // Simple validation
        let valid = true;
        $(this).find('[required]').each(function() {
            if ($(this).val().trim() === '') {
                $(this).addClass('is-invalid');
                valid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Check if at least one role is selected
        if ($('input[name="roles[]"]:checked').length === 0) {
            $('.invalid-feedback').show();
            valid = false;
        } else {
            $('.invalid-feedback').hide();
        }
        
        if (!valid) {
            return;
        }
        
        // Show loading state on submit button
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnText = submitBtn.html();
        submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...').prop('disabled', true);
        
        var actionUrl = $(this).attr('action');
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $("#createUserModal").modal('hide');  // Hides the modal container
                    $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                        showToast(response.message, 'success');
                    });
                    $('#createUserForm')[0].reset();  // Resets the form fields
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function (xhr) {
                var response = xhr.responseJSON;
                if (response && response.message) {
                    showToast(response.message, 'error');
                } else {
                    showToast('Error adding user.', 'error');
                }
            },
            complete: function() {
                // Reset button state
                submitBtn.html(originalBtnText).prop('disabled', false);
            }
        });
    });

    var deleteAction = null;
    // Handle "Edit User" form submission via AJAX
    $("#editUserForm").on("submit", function (e) {
        e.preventDefault();
        var submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...'
        );
        $.ajax({
            type: "POST",
            url: "update_user.php",
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $("#editUserModal").modal('hide');
                    $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                        showToast(response.message, 'success');
                    });
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function (xhr) {
                var response = xhr.responseJSON;
                if (response && response.message) {
                    showToast(response.message, 'error');
                } else {
                    showToast('Error updating user.', 'error');
                }
            },
            complete: function () {
                submitButton.prop('disabled', false).text('Save Changes');
            }
        });
    });

    // Handle delete button click
    $(document).on('click', '.delete-btn', function() {
        const userId = $(this).data('id');
        deleteAction = {type: 'single', userId: userId};
        $('#confirmDeleteMessage').text('Are you sure you want to archive this user?');
        $('#confirmDeleteModal').modal('show');
    });

    // Bulk delete
    $("#delete-selected").click(function () {
        const selected = $(".select-row:checked").map(function () {
            return $(this).val();
        }).get();
        if (selected.length === 0) {
            showToast('Please select users to archive.', 'warning');
            return;
        }
        deleteAction = {type: 'bulk', selected: selected};
        $('#confirmDeleteMessage').text(`Are you sure you want to archive ${selected.length} selected user(s)?`);
        $('#confirmDeleteModal').modal('show');
    });

    // Delete account confirmation
    $("#confirmDeleteAccount").click(function () {
        deleteAction = {type: 'account'};
        $('#confirmDeleteMessage').text("Are you sure you want to delete your account?");
        $('#confirmDeleteModal').modal('show');
        $('#delete-selected').modal('hide');
    });

    // Confirm delete action
    $('#confirmDeleteButton').on('click', function () {
        $(this).blur();
        $('#confirmDeleteModal').modal('hide');
        var currentAction = deleteAction;
        deleteAction = null;
        if (currentAction) {
            if (currentAction.type === 'single') {
                $.ajax({
                    type: "POST",
                    url: "delete_user.php",
                    data: {user_id: currentAction.userId},
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error: function () {
                        showToast('Error deleting user.', 'error');
                    }
                });
            } else if (currentAction.type === 'bulk') {
                $.ajax({
                    type: "POST",
                    url: "delete_user.php",
                    data: {user_ids: currentAction.selected},
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $("#select-all").prop("checked", false);
                            $(".select-row").prop("checked", false);
                            $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                                showToast(response.message, 'success');
                                toggleBulkDeleteButton();
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error: function () {
                        showToast('Error deleting users.', 'error');
                    }
                });
            } else if (currentAction.type === 'account') {
                $.ajax({
                    type: "POST",
                    url: "delete_account.php",
                    data: {action: "delete_account"},
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message, 'error');
                        }
                    },
                    error: function () {
                        showToast('Error deleting account.', 'error');
                    }
                });
            }
        }
    });

    //Clears modal input when user closes the modal and removes lingering backdrops
    $('#createUserModal, #editUserModal,#confirmDeleteModal,#addUserModal' ).on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
        $(this).find('form')[0].reset();
    });

    // Close alerts when close button is clicked
    $(document).on('click', '.btn-close', function () {
        $(this).closest('.alert').hide();
    });

    // Submit form on department filter change
    $('.department-filter').on('change', function () {
        $(this).closest('form').submit();
    });

    // Search and filter functionality
    const searchInput = $('#search-filters');
    const departmentFilter = $('#department-filter');
    let searchTimeout;

    function loadFilteredData() {
        const searchQuery = searchInput.val();
        const selectedDepartment = departmentFilter.val();
        const queryParams = new URLSearchParams({
            search: searchQuery,
            department: selectedDepartment
        });
        const baseUrl = window.location.href.split('?')[0];
        const newUrl = `${baseUrl}?${queryParams.toString()}`;

        $('#umTable tbody').load(`${newUrl} #umTable tbody > *`, function () {
            history.pushState(null, '', newUrl);

            if ($.trim($('#umTable tbody').html()) === '') {
                const emptyStateHtml = `
                    <tr>
                        <td colspan="100%">
                            <div class="empty-state">
                                <div class="empty-state-icon">🔍</div>
                                <div class="empty-state-message">No matching search found</div>
                                <button class="empty-state-action" id="clear-filters-btn">Clear filters</button>
                            </div>
                        </td>
                    </tr>`;
                $('#umTable tbody').html(emptyStateHtml);
                $('#clear-filters-btn').click(function () {
                    searchInput.val('');
                    departmentFilter.val('all');
                    loadFilteredData();
                });
            }
        });
    }

    // Debounce search input
    searchInput.on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadFilteredData, 500);
    });

    // Immediate department filter submission
    departmentFilter.on('change', function () {
        loadFilteredData();
    });

    // Populate Edit Modal with existing data
    $('#editUserModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var userId = button.data('id');
        var email = button.data('email');
        var firstName = button.data('first-name');
        var lastName = button.data('last-name');
        var department = button.data('department');
        var modal = $(this);
        modal.find('#editUserID').val(userId);
        modal.find('#editEmail').val(email);
        modal.find('#editFirstName').val(firstName);
        modal.find('#editLastName').val(lastName);
        modal.find('#editDepartment').val(department);
        
        // Reset all checkboxes first
        $('.edit-role-checkbox').prop('checked', false);
        
        // Fetch the user's roles
        $.ajax({
            type: "GET",
            url: "get_user_roles.php",
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                // Check the user's roles
                if (response.success && response.roles && response.roles.length > 0) {
                    response.roles.forEach(function(roleId) {
                        $('#edit_role_' + roleId).prop('checked', true);
                    });
                }
            },
            error: function (xhr) {
                console.error("Error fetching roles:", xhr.responseText);
                // Don't show error toast here as it's disruptive to the user experience
            }
        });
    });

    // "Select All" checkbox functionality
    $(document).on('click', '#select-all', function () {
        $(".select-row").prop('checked', $(this).prop('checked'));
        toggleBulkDeleteButton();
    });

    $(document).on('change', '.select-row', function () {
        toggleBulkDeleteButton();
    });

    function toggleBulkDeleteButton() {
        const anyChecked = $(".select-row:checked").length > 1;
        $("#delete-selected").prop('disabled', !anyChecked).toggle(anyChecked);
    }

    // Filter form submission for dynamic URL update
    $('form.d-flex').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        var baseUrl = window.location.href.split('?')[0];
        var newUrl = baseUrl + '?' + formData;
        $('#umTable tbody').load(newUrl + ' #umTable tbody > *', function () {
            history.pushState(null, '', newUrl);
        });
    });

});

