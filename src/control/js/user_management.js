$(document).ready(function () {
    // 1) Helper: Build a cache‑busted URL for reloads
    function getCacheBustedUrl(selector) {
        var baseUrl   = location.href.split('#')[0];
        var connector = baseUrl.indexOf('?') > -1 ? '&' : '?';
        return baseUrl + connector + '_=' + Date.now() + ' ' + selector;
    }

    // 2) Toggle custom department input in Create modal
    $('#modal_department').on('change', function () {
        if ($(this).val() === 'custom') {
            $('#modal_custom_department').show().attr('required', true);
        } else {
            $('#modal_custom_department').hide().attr('required', false);
        }
    });

    // 3) Create User via AJAX
    $('#createUserForm').on('submit', function (e) {
        e.preventDefault();
        var actionUrl = $(this).attr('action');
        $.post(actionUrl, $(this).serialize(), 'json')
            .done(function (response) {
                if (response.success) {
                    $('#createUserModal').modal('hide');
                    $('#umTable tbody').load(
                        getCacheBustedUrl('#umTable tbody > *'),
                        () => showToast(response.message, 'success')
                    );
                    $('#createUserForm')[0].reset();
                } else {
                    showToast(response.message, 'error');
                }
            })
            .fail(function (xhr) {
                var resp = xhr.responseJSON;
                showToast((resp && resp.message) || 'Error adding user.', 'error');
            });
    });

    // 4) Track delete action
    var deleteAction = null;

    // 5) Single-user delete: open confirm modal
    $(document).on('click', '.delete-user', function () {
        deleteAction = { type: 'single', userId: $(this).data('id') };
        $('#confirmDeleteMessage').text("Are you sure you want to archive this user?");
        $('#confirmDeleteModal').modal('show');
    });

    // 6) Bulk delete: open confirm modal
    $('#delete-selected').on('click', function () {
        var ids = $('.select-row:checked').map(function () { return this.value; }).get();
        if (ids.length < 2) {
            showToast('Please select at least two users to archive.', 'warning');
            return;
        }
        deleteAction = { type: 'bulk', selected: ids };
        $('#confirmDeleteMessage').text(`Are you sure you want to archive ${ids.length} users?`);
        $('#confirmDeleteModal').modal('show');
    });

    // 7) Account delete: open confirm modal
    $('#confirmDeleteAccount').on('click', function () {
        deleteAction = { type: 'account' };
        $('#confirmDeleteMessage').text("Are you sure you want to delete your account?");
        $('#confirmDeleteModal').modal('show');
    });

    // 8) Confirm delete (single, bulk, or account)
    $('#confirmDeleteButton').on('click', function () {
        $('#confirmDeleteModal').modal('hide');
        if (!deleteAction) return;

        var url, data;
        if (deleteAction.type === 'single') {
            url  = 'delete_user.php';
            data = { user_id: deleteAction.userId };
        } else if (deleteAction.type === 'bulk') {
            url  = 'delete_user.php';
            data = { user_ids: deleteAction.selected };
        } else if (deleteAction.type === 'account') {
            url  = 'delete_account.php';
            data = { action: 'delete_account' };
        } else {
            deleteAction = null;
            return;
        }

        deleteAction = null;
        $.post(url, data, 'json')
            .done(function (res) {
                if (res.success) {
                    $('#umTable tbody').load(
                        getCacheBustedUrl('#umTable tbody > *'),
                        function () {
                            updateBulkDeleteButton();
                            showToast(res.message, 'success');
                        }
                    );
                } else {
                    showToast(res.message, 'error');
                }
            })
            .fail(function () {
                showToast('Error deleting.', 'error');
            });
    });

    // 9) Clean up modals & reset forms
    $('#createUserModal, #editUserModal, #confirmDeleteModal, #addUserModal')
        .on('hidden.bs.modal', function () {
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
            $(this).find('form')[0]?.reset();
        });

    // 10) Close alerts
    $(document).on('click', '.btn-close', function () {
        $(this).closest('.alert').hide();
    });

    // 11) Search & filter
    var searchInput      = $('#search-filters'),
        departmentFilter = $('#department-filter'),
        searchTimeout;

    function loadFilteredData() {
        var params = new URLSearchParams({
            search:     searchInput.val(),
            department: departmentFilter.val()
        });
        var baseUrl = location.href.split('?')[0],
            url     = baseUrl + '?' + params.toString();

        $('#umTable tbody').load(
            url + ' #umTable tbody > *',
            function () {
                history.pushState(null, '', url);
                if (!$.trim($('#umTable tbody').text())) {
                    $('#umTable tbody').html(`
                <tr><td colspan="100%">
                  <div class="empty-state text-center py-5">
                    <div class="empty-state-icon fs-1 mb-2">🔍</div>
                    <div class="empty-state-message mb-3">
                      No matching search found
                    </div>
                    <button id="clear-filters-btn" class="btn btn-outline-primary">
                      Clear filters
                    </button>
                  </div>
                </td></tr>
              `);
                    $('#clear-filters-btn').click(function(){
                        searchInput.val('');
                        departmentFilter.val('all');
                        loadFilteredData();
                    });
                }
            }
        );
    }

    searchInput.on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadFilteredData, 500);
    });
    departmentFilter.on('change', loadFilteredData);

    // 12) Edit User modal population
    $('#editUserModal').on('show.bs.modal', function (ev) {
        var btn   = $(ev.relatedTarget),
            modal = $(this);
        modal.find('#editUserID')      .val(btn.data('id'));
        modal.find('#editEmail')       .val(btn.data('email'));
        modal.find('#editFirstName')   .val(btn.data('first-name'));
        modal.find('#editLastName')    .val(btn.data('last-name'));
        modal.find('#editDepartment')  .val(btn.data('department'));
    });

    // 13) Edit User via AJAX
    $('#editUserForm').on('submit', function (e) {
        e.preventDefault();
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...'
        );

        $.post('update_user.php', $(this).serialize(), 'json')
            .done(function (res) {
                if (res.success) {
                    $('#editUserModal').modal('hide');
                    $('#umTable tbody').load(
                        getCacheBustedUrl('#umTable tbody > *'),
                        () => showToast(res.message, 'success')
                    );
                } else {
                    showToast(res.message, 'error');
                }
            })
            .fail(() => showToast('Error updating user.', 'error'))
            .always(() => btn.prop('disabled', false).text('Save Changes'));
    });

    // 14) Bulk‑delete button toggle logic
    const $deleteBtn = $('#delete-selected'),
        $selectAll= $('#select-all'),
        $table    = $('#umTable');

    if ($deleteBtn.length) {
        function updateBulkDeleteButton() {
            var $rows   = $table.find('.select-row'),
                checked = $rows.filter(':checked').length;
            $deleteBtn.toggle(checked >= 2).prop('disabled', checked < 2);
            $selectAll.prop('checked', checked === $rows.length);
        }

        // bind and init
        $table.on('click change', '.select-row', updateBulkDeleteButton);
        $selectAll.on('change', function(){
            $table.find('.select-row').prop('checked', this.checked);
            updateBulkDeleteButton();
        });
        updateBulkDeleteButton();
    }
});
