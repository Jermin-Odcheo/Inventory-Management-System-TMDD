$(document).ready(function () {
    // Helper: Build a cache-busted URL for reloading content
    function getCacheBustedUrl(selector) {
        var baseUrl = location.href.split('#')[0];
        var connector = (baseUrl.indexOf('?') > -1) ? '&' : '?';
        return baseUrl + connector + '_=' + new Date().getTime() + ' ' + selector;
    }

    // **1. Load edit role modal content via AJAX**
    $(document).on('click', '.edit-role-btn', function () {
        var roleID = $(this).data('role-id');
        $('#editRoleContent').html("Loading...");
        $.ajax({
            url: 'edit_roles.php',
            type: 'GET',
            data: {id: roleID},
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            success: function (response) {
                $('#editRoleContent').html(response);
                $('#roleID').val(roleID);
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $('#editRoleContent').html('<p class="text-danger">Error loading role data. Please try again.</p>');
            }
        });
    });

    // **2. Handle delete role modal**
    $('#confirmDeleteModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var roleID = button.data('role-id');
        var roleName = button.data('role-name');
        $('#roleNamePlaceholder').text(roleName);
        $('#confirmDeleteButton').data('role-id', roleID);
    });

    // **3. Confirm delete role via AJAX**
    $(document).on('click', '#confirmDeleteButton', function (e) {
        e.preventDefault();
        $(this).blur();
        var roleID = $(this).data('role-id');
        $.ajax({
            type: 'POST',
            url: 'delete_role.php',
            data: {id: roleID},
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#rolesTable').load(getCacheBustedUrl('#rolesTable'), function () {
                        updatePagination();
                        showToast(response.message, 'success', 5000);
                    });
                    $('#confirmDeleteModal').modal('hide');
                    $('.modal-backdrop').remove();
                } else {
                    showToast(response.message || 'An error occurred', 'error', 5000);
                }
            },
            error: function (xhr, status, error) {
                showToast('Error deleting role: ' + error, 'error', 5000);
            }
        });
    });

    // **4. Load add role modal content**
    $('#addRoleModal').on('show.bs.modal', function () {
        $('#addRoleContent').html("Loading...");
        $.ajax({
            url: 'add_role.php',
            type: 'GET',
            success: function (response) {
                $('#addRoleContent').html(response);
            },
            error: function () {
                $('#addRoleContent').html('<p class="text-danger">Error loading form.</p>');
            }
        });
    });

    // **5. Handle form submissions for add/edit role**
    $(document).on('submit', '#addRoleForm, #editRoleForm', function (e) {
        e.preventDefault();
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var originalBtnText = submitBtn.html();
        submitBtn.html('<span class="spinner-border spinner-border-sm"></span> Saving...').prop('disabled', true);

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#rolesTable').load(getCacheBustedUrl('#rolesTable'), function () {
                        updatePagination();
                        showToast(response.message, 'success', 5000);
                    });
                    $('.modal').modal('hide');
                    $('.modal-backdrop').remove();
                } else {
                    showToast(response.message || 'An error occurred', 'error', 5000);
                }
            },
            error: function (xhr, status, error) {
                showToast('Error saving role: ' + error, 'error', 5000);
            },
            complete: function () {
                submitBtn.html(originalBtnText).prop('disabled', false);
            }
        });
    });

    // **6. Clear modals on hide**
    $('.modal').on('hidden.bs.modal', function () {
        $(this).find('form').trigger('reset');
        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
    });

    // **7. Undo button via AJAX**
    $(document).on('click', '#undoButton', function () {
        $.ajax({
            url: 'undo.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#rolesTable').load(getCacheBustedUrl('#rolesTable'), function () {
                        updatePagination();
                        showToast(response.message, 'success', 5000);
                    });
                } else {
                    showToast(response.message || 'An error occurred', 'error', 5000);
                }
            },
            error: function (xhr, status, error) {
                showToast('Error processing undo request: ' + error, 'error', 5000);
            }
        });
    });

    // **8. Redo button via AJAX**
    $(document).on('click', '#redoButton', function () {
        $.ajax({
            url: 'redo.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#rolesTable').load(getCacheBustedUrl('#rolesTable'), function () {
                        updatePagination();
                        showToast(response.message, 'success', 5000);
                    });
                } else {
                    showToast(response.message || 'An error occurred', 'error', 5000);
                }
            },
            error: function (xhr, status, error) {
                showToast('Error processing redo request: ' + error, 'error', 5000);
            }
        });
    });

    // Add hover effects for buttons
    $('.edit-role-btn').hover(
        function() { $(this).addClass('btn-warning-dark'); },
        function() { $(this).removeClass('btn-warning-dark'); }
    );

    $('.delete-role-btn').hover(
        function() { $(this).addClass('btn-danger-dark'); },
        function() { $(this).removeClass('btn-danger-dark'); }
    );

    // Initialize pagination
    updatePagination();
}); 