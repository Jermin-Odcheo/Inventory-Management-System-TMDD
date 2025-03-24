$(document).ready(function () {
// Helper: Build a cache-busted URL for reloading content.
    function getCacheBustedUrl(selector) {
        var baseUrl = location.href.split('#')[0];
        var connector = (baseUrl.indexOf('?') > -1) ? '&' : '?';
        return baseUrl + connector + '_=' + new Date().getTime() + ' ' + selector;
    }


    $(document).ready(function () {
        // Toggle custom department input
        $('#modal_department').on('change', function () {
            if ($(this).val() === 'custom') {
                $('#modal_custom_department').show().attr('required', true);
            } else {
                $('#modal_custom_department').hide().attr('required', false);
            }
        });
    });

    // Handle "Add User" form submission via AJAX
    $('#addUserForm').on('submit', function (e) {
        e.preventDefault();
        var actionUrl = $(this).attr('action');
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $("#addUserModal").modal('hide');
                    $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                        showToast(response.message, 'success');
                    });
                    $('#addUserForm')[0].reset();
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function (xhr) {
                // Attempt to parse the JSON error message from the response
                var response = xhr.responseJSON;
                if (response && response.message) {
                    showToast(response.message, 'error');
                } else {
                    showToast('Error adding user.', 'error');
                }
            }
        });
    });


    var deleteAction = null;
    // Single-user delete
    $(document).on('click', '.delete-user', function () {
        const userId = $(this).data("id");
        deleteAction = {type: 'single', userId: userId};
        $('#confirmDeleteMessage').text("Are you sure you want to archive this user?");
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
                            // Uncheck all checkboxes to clear the selection
                            $("#select-all").prop("checked", false);
                            $(".select-row").prop("checked", false);
                            // Reload the table, then update the bulk delete button
                            $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                                showToast(response.message, 'success');
                                // Update/hide the "Delete Selected" button since no rows are selected
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
            }
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
    });


// Remove lingering modal backdrop for delete modal
    $('#confirmDeleteModal').on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    });

// Remove lingering backdrop for edit modal as well
    $('#editUserModal').on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    });

    $('#addUserModal').on('hidden.bs.modal', function () {
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    });

    $(document).on('click', '.btn-close', function () {
        $(this).closest('.alert').hide();
    });

    $('.department-filter').on('change', function () {
        $(this).closest('form').submit();
    });


    $(document).ready(function () {
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
                    $('#clear-filters-btn').click(function() {
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
    });

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
                    // Hide the modal only when the update is successful.
                    $("#editUserModal").modal('hide');
                    // Reload table and show a success message.
                    $('#umTable tbody').load(getCacheBustedUrl('#umTable tbody > *'), function () {
                        showToast(response.message, 'success');
                    });
                } else {
                    // In case of error, show error message and keep modal open.
                    showToast(response.message, 'error');
                }
            },
            error: function () {
                showToast('Error updating user.', 'error');
            },
            complete: function () {
                // Always re-enable the submit button regardless of outcome.
                submitButton.prop('disabled', false).text('Save Changes');
                // Do not hide the modal here so it remains open on error.
            }
        });
    });


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

    $('form.d-flex').on('submit', function (e) {
        e.preventDefault();
        var formData = $(this).serialize();
        var baseUrl = window.location.href.split('?')[0];
        var newUrl = baseUrl + '?' + formData;

        $('#umTable tbody').load(newUrl + ' #umTable tbody > *', function () {
            history.pushState(null, '', newUrl);
        });
    });




    $('.department-filter').on('change', function () {
        $(this).closest('form').submit();
    });

});