<?php
ob_start();
require_once('../../../../../config/ims-tmdd.php');
session_start();
include '../../general/header.php';
include '../../general/sidebar.php';
include '../../general/footer.php';

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header('Location: ../../../../../public/index.php');
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

// 3) Button flags
$canRestore = $rbac->hasPrivilege('Roles and Privileges', 'Modify');
$canPermanentDelete = $rbac->hasPrivilege('Roles and Privileges', 'Delete');

// SQL query for archived roles with audit information
$sql = "
SELECT 
    r.id AS role_id,
    r.role_name AS role_name,
    CONCAT(u.First_Name, ' ', u.Last_Name) AS operator_name,
    u.Email AS operator_email,
    al.Module AS module,
    al.Action AS action,
    al.Details AS details,
    al.OldVal AS old_val,
    al.NewVal AS new_val,
    al.Status AS status,
    al.Date_Time AS date_time,
    al.UserID AS operator_id
FROM roles r
LEFT JOIN audit_log al ON al.EntityID = r.id AND al.Module = 'Roles and Privileges'
LEFT JOIN users u ON u.id = al.UserID
WHERE r.is_disabled = 1
  AND al.TrackID = (
    SELECT MAX(a2.TrackID)
    FROM audit_log a2
    WHERE a2.EntityID = r.id AND a2.Module = 'Roles and Privileges'
  )
ORDER BY al.Date_Time DESC, r.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Format JSON data into a list (for the 'Changes' column).
 */
function formatNewValue($jsonStr)
{
    $data = json_decode($jsonStr, true);
    if (!is_array($data)) {
        return '<span>' . htmlspecialchars($jsonStr) . '</span>';
    }
    $html = '<ul class="list-group">';
    foreach ($data as $key => $value) {
        $displayValue = is_null($value) ? '<em>null</em>' : htmlspecialchars($value);
        $friendlyKey = ucwords(str_replace('_', ' ', $key));
        $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>' . $friendlyKey . ':</strong> <span>' . $displayValue . '</span>
                  </li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Helper function to return an icon based on action.
 */
function getActionIcon($action)
{
    $action = strtolower($action);
    if ($action === 'modified') {
        return '<i class="fas fa-edit"></i>';
    } elseif ($action === 'add') {
        return '<i class="fas fa-plus"></i>';
    } elseif ($action === 'soft delete' || $action === 'permanent delete') {
        return '<i class="fas fa-trash-alt"></i>';
    } else {
        return '<i class="fas fa-info-circle"></i>';
    }
}

/**
 * Helper function to return a status icon.
 */
function getStatusIcon($status)
{
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
}

/**
 * Format data to display old values (for the 'Changes' column).
 */
function formatChanges($oldJsonStr)
{
    $oldData = json_decode($oldJsonStr, true);

    // If not an array, simply return the value
    if (!is_array($oldData)) {
        return '<span>' . htmlspecialchars($oldJsonStr) . '</span>';
    }

    $html = '<ul class="list-group">';
    foreach ($oldData as $key => $value) {
        // Special handling for modules_and_privileges which is a nested array
        if ($key === 'modules_and_privileges' && is_array($value)) {
            $html .= '<li class="list-group-item">
                        <strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong>
                        <ul class="list-group mt-2">';

            foreach ($value as $module => $privileges) {
                $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>' . htmlspecialchars($module) . ':</strong>
                            <span class="old-value text-danger"><i class="fas fa-history me-1"></i> ' .
                    htmlspecialchars($privileges) . '</span>
                          </li>';
            }

            $html .= '</ul></li>';
        } else {
            // Format the value
            $displayValue = is_null($value) ? '<em>null</em>' : htmlspecialchars($value);
            $friendlyKey = ucwords(str_replace('_', ' ', $key));

            $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>' . $friendlyKey . ':</strong>
                        <span class="old-value text-danger"><i class="fas fa-history me-1"></i> ' . $displayValue . '</span>
                      </li>';
        }
    }
    $html .= '</ul>';
    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Archived Roles</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" src="<?php echo BASE_URL; ?> /src/view/styles/css/audit_log.css">

    <style>
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 300px;
        }

        #tableContainer {
            max-height: 500px;
            overflow-y: auto;
        }

    </style>
</head>

<body>
    <div class="wrapper">
        <div class="main-content container-fluid">
            <header class="main-header">
                <h1>Archived Roles</h1>
            </header>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                    <h3 class="text-white">
                        <i class="fas fa-archive me-2"></i>
                        Archived Roles Audit Log
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive" id="table">
                        <table id="archivedRolesTable" class="table table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th><input type="checkbox" id="select-all"></th>
                                    <th style="width: 25px;">ID</th>
                                    <th>User</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Changes</th>
                                    <th>Status</th>
                                    <th>Date &amp; Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($roleData)): ?>
                                    <?php foreach ($roleData as $role): ?>
                                        <tr data-role-id="<?php echo $role['role_id']; ?>">
                                            <td>
                                                <input type="checkbox" class="select-row" value="<?php echo $role['role_id']; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($role['role_id']); ?></td>
                                            <td class="user">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <small><?php echo htmlspecialchars($role['operator_email'] ?? 'Unknown'); ?></small>
                                                </div>
                                            </td>
                                            <td class="module">
                                                <?php echo !empty($role['module']) ? htmlspecialchars(trim($role['module'])) : '<em class="text-muted">N/A</em>'; ?>
                                            </td>
                                            <td class="action">
                                                <?php
                                                $actionText = !empty($role['action']) ? $role['action'] : 'Unknown';
                                                echo '<span class="action-badge action-' . strtolower(str_replace(' ', '.', $actionText)) . '">';
                                                echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                                echo '</span>';
                                                ?>
                                            </td>
                                            <td class="details">
                                                <?php echo nl2br(htmlspecialchars($role['details'])); ?>
                                            </td>
                                            <td class="changes">
                                                <?php echo formatChanges($role['old_val']); ?>
                                            </td>
                                            <td class="status">
                                                <span class="badge <?php echo (strtolower($role['status']) === 'successful') ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo getStatusIcon($role['status']) . ' ' . htmlspecialchars($role['status']); ?>
                                                </span>
                                            </td>
                                            <td class="date-time">
                                                <div class="d-flex align-items-center">
                                                    <i class="far fa-clock me-2"></i>
                                                    <?php echo htmlspecialchars($role['date_time']); ?>
                                                </div>
                                            </td>
                                            <td class="actions">
                                                <div class="btn-group-vertical gap-1">
                                                    <?php if ($canRestore): ?>
                                                        <button type="button" class="btn btn-success restore-btn"
                                                            data-role-id="<?php echo $role['role_id']; ?>"
                                                            data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#confirmRestoreModal">
                                                            <i class="fas fa-undo me-1"></i> Restore
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($canPermanentDelete): ?>
                                                        <button type="button" class="btn btn-danger delete-btn"
                                                            data-role-id="<?php echo $role['role_id']; ?>"
                                                            data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                                            <i class="fas fa-trash me-1"></i> Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10">
                                            <div class="empty-state text-center py-4">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <h4>No Archived Roles Found</h4>
                                                <p class="text-muted">There are no archived roles to display.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="container-fluid">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-sm-auto">
                                    <div class="text-muted">
                                        Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                            id="totalRows">100</span> entries
                                    </div>
                                </div>
                                <div class="col-12 col-sm-auto ms-sm-auto">
                                    <div class="d-flex align-items-center gap-2">
                                        <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </button>
                                        <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                            <option value="10" selected>10</option>
                                            <option value="20">20</option>
                                            <option value="30">30</option>
                                            <option value="50">50</option>
                                        </select>
                                        <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <ul class="pagination justify-content-center" id="pagination"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

    <!-- Modals -->
    <div class="modal fade" id="confirmRestoreModal" tabindex="-1" aria-labelledby="confirmRestoreModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to restore the role "<span id="restoreRoleNamePlaceholder"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmRestoreButton" href="#" class="btn btn-primary">Restore</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Permanent Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">Warning: This action cannot be undone!</p>
                    Are you sure you want to permanently delete the role "<span id="roleNamePlaceholder"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete Permanently</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Restore Modal -->
    <div class="modal fade" id="bulkRestoreModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Bulk Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to restore the selected roles?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmBulkRestoreBtn" class="btn btn-success">Restore</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Bulk Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">Warning: This action cannot be undone!</p>
                    Are you sure you want to permanently delete the selected roles?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmBulkDeleteBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pass RBAC privileges to JavaScript
        const userPrivileges = {
            canRestore: <?php echo json_encode($canRestore); ?>,
            canPermanentDelete: <?php echo json_encode($canPermanentDelete); ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Handle select all checkbox
            $(document).on('change', '#select-all', function() {
                $('.select-row').prop('checked', $(this).prop('checked'));
                updateBulkButtons();
            });

            $(document).on('change', '.select-row', updateBulkButtons);

            function updateBulkButtons() {
                var count = $('.select-row:checked').length;
                // Show bulk actions only if 2 or more are selected
                if (count >= 2) {
                    $('#bulk-restore, #bulk-delete').prop('disabled', false).show();
                } else {
                    $('#bulk-restore, #bulk-delete').prop('disabled', true).hide();
                }
            }

            // Handle restore role modal
            $('#confirmRestoreModal').on('show.bs.modal', function(event) {
                if (!userPrivileges.canRestore) {
                    event.preventDefault();
                    return false;
                }

                var button = $(event.relatedTarget);
                var roleID = button.data('role-id');
                var roleName = button.data('role-name');
                $('#restoreRoleNamePlaceholder').text(roleName);
                $('#confirmRestoreButton').data('role-id', roleID);
            });

            // Confirm restore role via AJAX
            $(document).on('click', '#confirmRestoreButton', function(e) {
                if (!userPrivileges.canRestore) return;

                e.preventDefault();
                $(this).blur();
                var roleID = $(this).data('role-id');
                $.ajax({
                    type: 'POST',
                    url: '../role_manager/restore_role.php',
                    data: {
                        id: roleID
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#archivedRolesTable').load(location.href + ' #archivedRolesTable', function() {
                                updatePagination();
                                showToast(response.message, 'success', 5000);
                            });
                            $('#confirmRestoreModal').modal('hide');
                            $('.modal-backdrop').remove();
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error restoring role: ' + error, 'error', 5000);
                    }
                });
            });

            // Handle permanent delete role modal
            $('#confirmDeleteModal').on('show.bs.modal', function(event) {
                if (!userPrivileges.canPermanentDelete) {
                    event.preventDefault();
                    return false;
                }

                var button = $(event.relatedTarget);
                var roleID = button.data('role-id');
                var roleName = button.data('role-name');
                $('#roleNamePlaceholder').text(roleName);
                $('#confirmDeleteButton').data('role-id', roleID);
            });

            // Confirm permanent delete role via AJAX
            $(document).on('click', '#confirmDeleteButton', function(e) {
                if (!userPrivileges.canPermanentDelete) return;

                e.preventDefault();
                $(this).blur();
                var roleID = $(this).data('role-id');
                $.ajax({
                    type: 'POST',
                    url: '../role_manager/permanent_delete_role.php',
                    data: {
                        id: roleID
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#archivedRolesTable').load(location.href + ' #archivedRolesTable', function() {
                                updatePagination();
                                showToast(response.message, 'success', 5000);
                            });
                            $('#confirmDeleteModal').modal('hide');
                            $('.modal-backdrop').remove();
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error permanently deleting role: ' + error, 'error', 5000);
                    }
                });
            });

            // Bulk Restore
            var bulkRestoreIds = [];

            // When bulk restore button is clicked
            $(document).on('click', '#bulk-restore', function() {
                if (!userPrivileges.canRestore) return;

                bulkRestoreIds = [];
                $('.select-row:checked').each(function() {
                    bulkRestoreIds.push($(this).val());
                });

                if (bulkRestoreIds.length > 0) {
                    $('#bulkRestoreModal').modal('show');
                }
            });

            // Confirm bulk restore
            $(document).on('click', '#confirmBulkRestoreBtn', function() {
                if (!userPrivileges.canRestore || bulkRestoreIds.length === 0) return;

                $.ajax({
                    type: 'POST',
                    url: '../role_manager/restore_role.php',
                    data: {
                        ids: bulkRestoreIds
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#archivedRolesTable').load(location.href + ' #archivedRolesTable', function() {
                                updatePagination();
                                showToast(response.message, 'success', 5000);
                            });
                            $('#bulkRestoreModal').modal('hide');
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error restoring roles: ' + error, 'error', 5000);
                    }
                });
            });

            // Bulk Delete
            var bulkDeleteIds = [];

            // When bulk delete button is clicked
            $(document).on('click', '#bulk-delete', function() {
                if (!userPrivileges.canPermanentDelete) return;

                bulkDeleteIds = [];
                $('.select-row:checked').each(function() {
                    bulkDeleteIds.push($(this).val());
                });

                if (bulkDeleteIds.length > 0) {
                    $('#bulkDeleteModal').modal('show');
                }
            });

            // Confirm bulk delete
            $(document).on('click', '#confirmBulkDeleteBtn', function() {
                if (!userPrivileges.canPermanentDelete || bulkDeleteIds.length === 0) return;

                $.ajax({
                    type: 'POST',
                    url: '../role_manager/permanent_delete_role.php',
                    data: {
                        ids: bulkDeleteIds
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#archivedRolesTable').load(location.href + ' #archivedRolesTable', function() {
                                updatePagination();
                                showToast(response.message, 'success', 5000);
                            });
                            $('#bulkDeleteModal').modal('hide');
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error deleting roles: ' + error, 'error', 5000);
                    }
                });
            });
        });
    </script>
</body>

</html>