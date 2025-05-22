<?php
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';

// If not logged in, redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Initialize RBAC for User Management
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

// Check for additional privileges
$canRestore = $rbac->hasPrivilege('User Management', 'Restore');
$canRemove = $rbac->hasPrivilege('User Management', 'Remove');
$canDelete = $rbac->hasPrivilege('User Management', 'Permanently Delete');

$query = "
 
SELECT
    a.TrackID AS track_id,
    CONCAT(op.First_Name, ' ', op.Last_Name) AS operator_name,
    op.Email AS operator_email,
    a.Module AS module,
    a.Action AS action,
    a.Details AS details,
    a.OldVal AS old_val,
    a.NewVal AS new_val,
    a.Status AS status,
    a.Date_Time AS date_time,
    a.EntityID AS deleted_user_id
FROM audit_log a
LEFT JOIN users u ON a.EntityID = u.id
JOIN users op ON a.UserID = op.id
WHERE (u.is_disabled = 1)
AND a.TrackID = (
SELECT MAX(a2.TrackID)
        FROM audit_log a2
        WHERE a2.EntityID = a.EntityID
    )
ORDER BY a.TrackID DESC
 
";


try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$logs) {
        $logs = [];
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

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
    $icons = [
        'modified' => '<i class="fas fa-user-edit"></i>',
        'create'   => '<i class="fas fa-user-plus"></i>',
        'remove'   => '<i class="fas fa-user-slash"></i>',
        'delete'   => '<i class="fas fa-user-slash"></i>',
        'restored' => '<i class="fas fa-undo"></i>'
    ];
    return $icons[$action] ?? '<i class="fas fa-info-circle"></i>';
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
 * Format JSON data to display old values only.
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
        // Format the value
        $displayValue = is_null($value) ? '<em>null</em>' : htmlspecialchars($value);
        $friendlyKey = ucwords(str_replace('_', ' ', $key));

        $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>' . $friendlyKey . ':</strong>
                    <span class="old-value text-danger"><i class="fas fa-history me-1"></i> ' . $displayValue . '</span>
                  </li>';
    }
    $html .= '</ul>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users Management Archive</title>
    <!-- Bootstrap and Font Awesome CDNs -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS for audit logs -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    <!-- Include Toast CSS/JS (make sure showToast is defined) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .main-content {
            padding-top: 150px;
        }
    </style>
</head>
<body>
<?php include '../../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <!-- Card header -->
            <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                <h3 class="text-white">
                    <i class="fas fa-archive me-2"></i>
                    User Management Archives
                </h3>
            </div>
            <div class="card-body">
                <!-- Bulk action buttons -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="bulk-actions mb-3">
                            <!-- Bulk actions only show if 2 or more are selected -->
                            <?php if ($canRestore): ?>
                            <button type="button" id="restore-selected" class="btn btn-success" disabled style="display: none;">Restore Selected</button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button type="button" id="delete-selected-permanently" class="btn btn-danger" disabled style="display: none;">Delete Selected Permanently</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search archives...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterAction" class="form-select">
                            <option value="">All Actions</option>
                            <option value="create">Create</option>
                            <option value="modified">Modified</option>
                            <option value="remove">Remove</option>
                            <option value="delete">Delete</option>
                            <option value="restored">Restored</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterStatus" class="form-select">
                            <option value="">All Status</option>
                            <option value="successful">Successful</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>
                
                <!-- Table container -->
                <div class="table-responsive" id="table">
                    <table id="archiveTable" class="table table-hover">
                        <colgroup>
                            <col class="checkbox">
                            <col class="track">
                            <col class="user">
                            <col class="module">
                            <col class="action">
                            <col class="details">
                            <col class="changes">
                            <col class="status">
                            <col class="date">
                            <col class="actions">
                        </colgroup>
                        <thead class="table-light">
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th>#</th>
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
                        <tbody id="archiveTableBody">
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td data-label="Select">
                                        <input type="checkbox" class="select-row" value="<?php echo $log['deleted_user_id']; ?>">
                                    </td>
                                    <td data-label="Track ID">
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($log['track_id']); ?></span>
                                    </td>
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2"></i>
                                            <small><?php echo htmlspecialchars($log['operator_email']); ?></small>
                                        </div>
                                    </td>
                                    <td data-label="Module">
                                        <?php echo !empty($log['module']) ? htmlspecialchars(trim($log['module'])) : '<em class="text-muted">N/A</em>'; ?>
                                    </td>
                                    <td data-label="Action">
                                        <?php
                                        $actionText = !empty($log['action']) ? $log['action'] : 'Unknown';
                                        echo '<span class="action-badge action-' . strtolower($actionText) . '">';
                                        echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                        echo '</span>';
                                        ?>
                                    </td>
                                    <td data-label="Details">
                                        <?php echo nl2br(htmlspecialchars($log['details'])); ?>
                                    </td>
                                    <td data-label="Changes">
                                        <?php echo formatChanges($log['old_val']); ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge <?php echo (strtolower($log['status']) === 'successful') ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo getStatusIcon($log['status']) . ' ' . htmlspecialchars($log['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Date &amp; Time">
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-clock me-2"></i>
                                            <?php echo htmlspecialchars($log['date_time']); ?>
                                        </div>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="btn-group-vertical gap-1">
                                            <!-- Individual restore now triggers a confirmation modal -->
                                            <?php if ($canRestore): ?>
                                            <button type="button" class="btn btn-success restore-btn" data-id="<?php echo $log['deleted_user_id']; ?>">
                                                <i class="fas fa-undo me-1"></i> Restore
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                            <button type="button" class="btn btn-danger delete-permanent-btn" data-id="<?php echo $log['deleted_user_id']; ?>">
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
                                        <h4>No Archived Users Found</h4>
                                        <p class="text-muted">There are no archived users to display.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Controls -->
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                <?php $totalLogs = count($logs); ?>
                                <input type="hidden" id="total-users" value="<?= $totalLogs ?>">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
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
                </div> <!-- /.End of Pagination -->
            </div>
        </div>
    </div>
</div> <!-- /.End of Main Content -->

<!-- Delete Archive Modal (for individual deletion) -->
<div class="modal fade" id="deleteArchiveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to permanently delete this record?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Restore Archive Modal (for individual restore) -->
<div class="modal fade" id="restoreArchiveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Restore</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to restore this record?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmRestoreBtn" class="btn btn-success">Restore</button>
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
                Are you sure you want to permanently delete the selected users?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmBulkDeleteBtn" class="btn btn-danger">Delete</button>
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
                Are you sure you want to restore the selected users?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmBulkRestoreBtn" class="btn btn-success">Restore</button>
            </div>
        </div>
    </div>
</div>

<!-- Include pagination script if needed -->
<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js" defer></script>
<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/logs.js" defer></script>
<script>
    // Pass RBAC permissions to JavaScript
    var userPrivileges = {
        canRestore: <?php echo json_encode($canRestore); ?>,
        canRemove: <?php echo json_encode($canRemove); ?>,
        canDelete: <?php echo json_encode($canDelete); ?>
    };
    
    // Initialize pagination when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Set the correct table ID for both pagination.js and logs.js
        window.paginationConfig = window.paginationConfig || {};
        window.paginationConfig.tableId = 'archiveTableBody';
        
        // Initialize pagination with the archive table ID
        initPagination({
            tableId: 'archiveTableBody',
            currentPage: 1
        });
        
        // Store original rows for filtering
        window.allRows = Array.from(document.querySelectorAll('#archiveTableBody tr'));
        
        // Force hide pagination buttons if no data or all fits on one page
        function forcePaginationCheck() {
            const totalRows = window.filteredRows ? window.filteredRows.length : 0;
            const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value);
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            const paginationEl = document.getElementById('pagination');

            // Hide pagination completely if all rows fit on one page
            if (totalRows <= rowsPerPage) {
                if (prevBtn) prevBtn.style.cssText = 'display: none !important';
                if (nextBtn) nextBtn.style.cssText = 'display: none !important';
                if (paginationEl) paginationEl.style.cssText = 'display: none !important';
            } else {
                // Show pagination but conditionally hide prev/next buttons
                if (paginationEl) paginationEl.style.cssText = '';

                if (prevBtn) {
                    if (window.currentPage <= 1) {
                        prevBtn.style.cssText = 'display: none !important';
                    } else {
                        prevBtn.style.cssText = '';
                    }
                }

                if (nextBtn) {
                    const totalPages = Math.ceil(totalRows / rowsPerPage);
                    if (window.currentPage >= totalPages) {
                        nextBtn.style.cssText = 'display: none !important';
                    } else {
                        nextBtn.style.cssText = '';
                    }
                }
            }
        }
        
        // Run forcePaginationCheck after pagination updates
        const originalUpdatePagination = window.updatePagination || function() {};
        window.updatePagination = function() {
            // Get all rows again in case the DOM was updated
            window.allRows = Array.from(document.querySelectorAll('#archiveTableBody tr'));
            
            // If filtered rows is empty or not defined, use all rows
            if (!window.filteredRows || window.filteredRows.length === 0) {
                window.filteredRows = window.allRows;
            }
            
            // Update total rows display
            const totalRowsEl = document.getElementById('totalRows');
            if (totalRowsEl) {
                totalRowsEl.textContent = window.filteredRows.length;
            }
            
            // Call original updatePagination
            originalUpdatePagination();
            forcePaginationCheck();
        };
        
        // Call updatePagination immediately
        updatePagination();
    });
    
    var deleteId = null;
    var restoreId = null;
    var bulkDeleteIds = [];

    // Delegated events for checkboxes
    $(document).on('change', '#select-all', function () {
        $('.select-row').prop('checked', $(this).prop('checked'));
        updateBulkButtons();
    });
    $(document).on('change', '.select-row', updateBulkButtons);
    function updateBulkButtons() {
        var count = $('.select-row:checked').length;
        // Show bulk actions only if 2 or more are selected
        if (count >= 2) {
            if (userPrivileges.canRestore) {
                $('#restore-selected').prop('disabled', false).show();
            }
            if (userPrivileges.canDelete) {
                $('#delete-selected-permanently').prop('disabled', false).show();
            }
        } else {
            $('#restore-selected, #delete-selected-permanently').prop('disabled', true).hide();
        }
    }

    // --- Individual Restore (with modal) ---
    $(document).on('click', '.restore-btn', function (e) {
        if (!userPrivileges.canRestore) return;
        
        e.preventDefault();
        restoreId = $(this).data('id');
        var restoreModal = new bootstrap.Modal(document.getElementById('restoreArchiveModal'));
        restoreModal.show();
    });
    $(document).on('click', '#confirmRestoreBtn', function () {
        if (!userPrivileges.canRestore || !restoreId) return;
        
        $.ajax({
            url: '../../user_manager/restore_user.php',
            method: 'POST',
            data: { id: restoreId },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                // Hide restore modal immediately
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('restoreArchiveModal'));
                modalInstance.hide();

                // Check for success status in a case-insensitive manner
                if (response.status && response.status.toLowerCase() === 'success') {
                    $('#archiveTable').load(location.href + ' #archiveTable', function () {
                        updateBulkButtons();
                        showToast(response.message, 'success');
                    });
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('Error processing restore request.', 'error');
            }
        });
    });


    // --- Individual Permanent Delete ---
    $(document).on('click', '.delete-permanent-btn', function(e) {
        if (!userPrivileges.canDelete) return;
        
        e.preventDefault();
        deleteId = $(this).data('id');
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteArchiveModal'));
        deleteModal.show();
    });

    $(document).on('click', '#confirmDeleteBtn', function () {
        if (!userPrivileges.canDelete || !deleteId) return;
        
        $.ajax({
            url: '../../user_manager/delete_user.php',
            method: 'POST',
            data: { user_id: deleteId, permanent: 1 },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                // Hide delete modal immediately
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteArchiveModal'));
                modalInstance.hide();

                // Use case-insensitive check for "success"
                if (response.status && response.status.toLowerCase() === 'success') {
                    $('#archiveTable').load(location.href + ' #archiveTable', function () {
                        updateBulkButtons();
                        showToast(response.message, 'success');
                    });
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('Error processing request.', 'error');
            }
        });
    });


    var bulkRestoreIds = [];

    // When bulk restore button is clicked, gather selected IDs and show modal
    $(document).on('click', '#restore-selected', function () {
        if (!userPrivileges.canRestore) return;
        
        var selected = $('.select-row:checked');
        bulkRestoreIds = [];
        selected.each(function () {
            bulkRestoreIds.push($(this).val());
        });
        var bulkRestoreModal = new bootstrap.Modal(document.getElementById('bulkRestoreModal'));
        bulkRestoreModal.show();
    });

    // When confirming bulk restore in the modal, perform the AJAX call
    $(document).on('click', '#confirmBulkRestoreBtn', function () {
        if (!userPrivileges.canRestore || bulkRestoreIds.length === 0) return;
        
        $.ajax({
            url: '../../user_manager/restore_user.php',
            method: 'POST',
            data: { user_ids: bulkRestoreIds },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                // Hide the bulk restore modal
                var modalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkRestoreModal'));
                modalInstance.hide();
                if (response.status === 'success') {
                    $('#archiveTable').load(location.href + ' #archiveTable', function () {
                        updateBulkButtons();
                        showToast(response.message, 'success');
                    });
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('Error processing bulk restore.', 'error');
            }
        });
    });


    // --- Bulk Delete ---
    $(document).on('click', '#delete-selected-permanently', function () {
        if (!userPrivileges.canDelete) return;
        
        var selected = $('.select-row:checked');
        bulkDeleteIds = [];
        selected.each(function () {
            bulkDeleteIds.push($(this).val());
        });
        var bulkModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
        bulkModal.show();
    });
    $(document).on('click', '#confirmBulkDeleteBtn', function () {
        if (!userPrivileges.canDelete || bulkDeleteIds.length === 0) return;
        
        $.ajax({
            url: '../../user_manager/delete_user.php',
            method: 'POST',
            data: { user_ids: bulkDeleteIds, permanent: 1 },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function(response) {
                // Hide bulk delete modal immediately
                var bulkModalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal'));
                bulkModalInstance.hide();

                if (response.status === 'success') {
                    $('#archiveTable').load(location.href + ' #archiveTable', function () {
                        updateBulkButtons();
                        showToast(response.message, 'success');
                    });
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('Error processing bulk delete.', 'error');
            }
        });
    });
</script>
<?php include '../../../general/footer.php'; ?>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
