<?php
session_start();
require '../../../../../config/ims-tmdd.php';

// Include Header
include '../../general/header.php';

// If not logged in, redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php");
    exit();
}

// Fetch archived departments (is_disabled = 1)
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
    a.EntityID AS deleted_dept_id,
    d.department_name,
    d.abbreviation
FROM audit_log a
JOIN users op ON a.UserID = op.id
LEFT JOIN departments d ON a.EntityID = d.id
WHERE a.Module = 'Department Management'
AND a.Action = 'Remove'
AND EXISTS (
    SELECT 1 FROM departments WHERE id = a.EntityID AND is_disabled = 1
)
AND a.TrackID = (
    SELECT MAX(a2.TrackID)
    FROM audit_log a2
    WHERE a2.EntityID = a.EntityID
    AND a2.Module = 'Department Management'
    AND a2.Action = 'Remove'
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
    if ($action === 'modified') {
        return '<i class="fas fa-edit"></i>';
    } elseif ($action === 'add') {
        return '<i class="fas fa-plus-circle"></i>';
    } elseif ($action === 'remove' || $action === 'delete') {
        return '<i class="fas fa-trash"></i>';
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
    <title>Archived Departments</title>
    <!-- Bootstrap and Font Awesome CDNs -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS for audit logs -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    <!-- Include Toast CSS/JS (make sure showToast is defined) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Define showToast function to ensure it's available
        function showToast(message, type = 'info') {
            // Check if Bootstrap 5's toast is available
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                // Create toast container if it doesn't exist
                let toastContainer = document.getElementById('toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.id = 'toast-container';
                    toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                    document.body.appendChild(toastContainer);
                }
                
                // Create a new toast element
                const toastEl = document.createElement('div');
                toastEl.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0`;
                toastEl.setAttribute('role', 'alert');
                toastEl.setAttribute('aria-live', 'assertive');
                toastEl.setAttribute('aria-atomic', 'true');
                
                toastEl.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                
                toastContainer.appendChild(toastEl);
                
                // Initialize and show the toast
                const toast = new bootstrap.Toast(toastEl, {
                    autohide: true,
                    delay: 5000
                });
                toast.show();
                
                // Remove toast after it's hidden
                toastEl.addEventListener('hidden.bs.toast', function() {
                    toastEl.remove();
                });
            } else {
                // Fallback to alert if Bootstrap toast is not available
                const alertType = type === 'error' ? 'danger' : type;
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${alertType} alert-dismissible fade show`;
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                // Insert at the top of the page
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.insertBefore(alertDiv, mainContent.firstChild);
                } else {
                    document.body.insertBefore(alertDiv, document.body.firstChild);
                }
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        }
    </script>
</head>
<body>
<?php include '../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <!-- Card header -->
            <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                <h3 class="text-white">
                    <i class="fas fa-archive me-2"></i>
                    Archived Departments
                </h3>
            </div>
            <div class="card-body">
                <!-- Bulk action buttons -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="bulk-actions mb-3">
                            <!-- Bulk actions only show if 2 or more are selected -->
                            <button type="button" id="restore-selected" class="btn btn-success" disabled style="display: none;">Restore Selected</button>
                            <button type="button" id="delete-selected-permanently" class="btn btn-danger" disabled style="display: none;">Delete Selected Permanently</button>
                        </div>
                    </div>
                </div>
                <!-- Table container -->
                <div class="table-responsive" id="table">
                    <table id="archiveTable" class="table table-hover">
                        <colgroup>
                            <col class="checkbox">
                            <col class="track">
                            <col class="user">
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
                            <th>Details</th>
                            <th>Department Data</th>
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
                                        <input type="checkbox" class="select-row" value="<?php echo $log['deleted_dept_id']; ?>">
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
                                    <td data-label="Details">
                                        <?php 
                                        // Show department information if available from the JOIN
                                        if (isset($log['department_name']) && !empty($log['department_name'])) {
                                            echo "Department: <strong>" . htmlspecialchars($log['department_name']) . "</strong>";
                                            if (!empty($log['abbreviation'])) {
                                                echo " (" . htmlspecialchars($log['abbreviation']) . ")";
                                            }
                                        } else {
                                            // Fallback to details from audit log
                                            echo nl2br(htmlspecialchars($log['details'])); 
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Department Data">
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
                                            <?php 
                                            // Format date_time properly with error handling
                                            if (!empty($log['date_time']) && $log['date_time'] !== '0000-00-00 00:00:00') {
                                                try {
                                                    $dateTime = new DateTime($log['date_time']); 
                                                    echo $dateTime->format('Y-m-d H:i:s');
                                                } catch (Exception $e) {
                                                    // Fallback if date is invalid
                                                    echo htmlspecialchars($log['date_time']);
                                                }
                                            } else {
                                                echo '<em class="text-muted">Date not available</em>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="btn-group-vertical gap-1">
                                            <!-- Individual restore now triggers a confirmation modal -->
                                            <button type="button" class="btn btn-success restore-btn" data-id="<?php echo $log['deleted_dept_id']; ?>">
                                                <i class="fas fa-undo me-1"></i> Restore
                                            </button>
                                            <button type="button" class="btn btn-danger delete-permanent-btn" data-id="<?php echo $log['deleted_dept_id']; ?>">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state text-center py-4">
                                        <i class="fas fa-inbox fa-3x mb-3"></i>
                                        <h4>No Archived Departments Found</h4>
                                        <p class="text-muted">There are no archived departments to display.</p>
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
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows">100</span> entries
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
                Are you sure you want to permanently delete this department?
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
                Are you sure you want to restore this department?
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
                Are you sure you want to permanently delete the selected departments?
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
                Are you sure you want to restore the selected departments?
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
<!-- Add Bootstrap 5 JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
            $('#restore-selected, #delete-selected-permanently').prop('disabled', false).show();
        } else {
            $('#restore-selected, #delete-selected-permanently').prop('disabled', true).hide();
        }
    }

    // --- Individual Restore (with modal) ---
    $(document).on('click', '.restore-btn', function (e) {
        e.preventDefault();
        restoreId = $(this).data('id');
        var restoreModal = new bootstrap.Modal(document.getElementById('restoreArchiveModal'));
        restoreModal.show();
    });

    // Add console logging for ajax errors
    function handleAjaxError(xhr, status, error, operation) {
        console.error('AJAX Error during ' + operation + ':', {
            status: status,
            error: error,
            response: xhr.responseText,
            statusCode: xhr.status
        });
        
        let errorMessage = 'Error processing ' + operation + ' request';
        
        try {
            // Try to parse the response as JSON
            const response = JSON.parse(xhr.responseText);
            if (response && response.message) {
                errorMessage = response.message;
            }
        } catch (e) {
            // If parsing fails, use the raw response text or default message
            errorMessage = xhr.responseText || errorMessage;
        }
        
        showToast(errorMessage, 'error');
    }

    // --- Individual Restore AJAX Call ---
    $(document).on('click', '#confirmRestoreBtn', function () {
        if (restoreId) {
            console.log('Sending restore request for department ID:', restoreId);
            
            $.ajax({
                url: '../../modules/role_manager/restore_department.php',
                method: 'POST',
                data: { id: restoreId },
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    console.log('Restore response:', response);
                    
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
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'restore');
                }
            });
        }
    });

    // --- Individual Permanent Delete ---
    $(document).on('click', '.delete-permanent-btn', function(e) {
        e.preventDefault();
        deleteId = $(this).data('id');
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteArchiveModal'));
        deleteModal.show();
    });

    // --- Individual Permanent Delete AJAX Call ---
    $(document).on('click', '#confirmDeleteBtn', function () {
        if (deleteId) {
            console.log('Sending delete request for department ID:', deleteId);
            
            $.ajax({
                url: '../../modules/role_manager/delete_department.php',
                method: 'POST',
                data: { dept_id: deleteId, permanent: 1 },
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    console.log('Delete response:', response);
                    
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
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'delete');
                }
            });
        }
    });

    var bulkRestoreIds = [];

    // When bulk restore button is clicked, gather selected IDs and show modal
    $(document).on('click', '#restore-selected', function () {
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
        if (bulkRestoreIds.length > 0) {
            console.log('Sending bulk restore request for department IDs:', bulkRestoreIds);
            
            $.ajax({
                url: '../../modules/role_manager/restore_department.php',
                method: 'POST',
                data: { dept_ids: bulkRestoreIds },
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    console.log('Bulk restore response:', response);
                    
                    // Hide the bulk restore modal
                    var modalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkRestoreModal'));
                    modalInstance.hide();
                    if (response.status === 'success') {
                        $('#archiveTable').load(location.href + ' #archiveTable', function () {
                            updateBulkButtons();
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'bulk restore');
                }
            });
        }
    });

    // --- Bulk Delete ---
    $(document).on('click', '#delete-selected-permanently', function () {
        var selected = $('.select-row:checked');
        bulkDeleteIds = [];
        selected.each(function () {
            bulkDeleteIds.push($(this).val());
        });
        var bulkModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
        bulkModal.show();
    });
    $(document).on('click', '#confirmBulkDeleteBtn', function () {
        if (bulkDeleteIds.length > 0) {
            console.log('Sending bulk delete request for department IDs:', bulkDeleteIds);
            
            $.ajax({
                url: '../../modules/role_manager/delete_department.php',
                method: 'POST',
                data: { dept_ids: bulkDeleteIds, permanent: 1 },
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    console.log('Bulk delete response:', response);
                    
                    // Hide bulk delete modal immediately
                    var bulkModalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal'));
                    bulkModalInstance.hide();

                    if (response.status === 'success') {
                        $('#archiveTable').load(location.href + ' #archiveTable', function () {
                            updateBulkButtons();
                            showToast(response.message, 'success');
                        });
                    } else {
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'bulk delete');
                }
            });
        }
    });
</script>
<?php include '../../general/footer.php'; ?>
 
</body>
</html> 