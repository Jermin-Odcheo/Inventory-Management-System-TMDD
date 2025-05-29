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

// Initialize RBAC for Roles and Privileges
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Management', 'View');

// Check for additional privileges
$canRestore = $rbac->hasPrivilege('Management', 'Restore');
$canRemove = $rbac->hasPrivilege('Management', 'Remove');
$canDelete = $rbac->hasPrivilege('Management', 'Permanently Delete');

// --- Sorting Logic ---
$sort_by = $_GET['sort_by'] ?? 'track_id'; // Default sort column
$sort_order = $_GET['sort_order'] ?? 'desc'; // Default sort order

// Whitelist allowed columns to prevent SQL injection
$allowedSortColumns = [
    'track_id' => 'a.TrackID',
    'operator_name' => 'operator_name', // Alias from CONCAT
    'module' => 'a.Module',
    'action' => 'a.Action',
    'status' => 'a.Status',
    'date_time' => 'a.Date_Time',
    'department_name' => 'd.department_name' // For sorting by department name
];

// Validate sort_by and sort_order
if (!array_key_exists($sort_by, $allowedSortColumns)) {
    $sort_by = 'track_id'; // Fallback to default
}
if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
    $sort_order = 'desc'; // Fallback to default
}

$orderByClause = "ORDER BY " . $allowedSortColumns[$sort_by] . " " . strtoupper($sort_order);


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
{$orderByClause}
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
    <title>Departments Archive</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script>
        // Only load jQuery if it's not already loaded
        if (typeof jQuery === 'undefined') {
            document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
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
    <style>
        .main-content {
            padding-top: 150px;
        }
        /* Styles for sortable headers */
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 25px; /* Make space for the icon */
        }

        th.sortable .fas {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc; /* Default icon color */
            transition: color 0.2s ease;
        }

        th.sortable:hover .fas {
            color: #888; /* Hover color */
        }

        th.sortable.asc .fas.fa-sort-up,
        th.sortable.desc .fas.fa-sort-down {
            color: #333; /* Active icon color */
        }

        th.sortable.asc .fas.fa-sort,
        th.sortable.desc .fas.fa-sort {
            display: none; /* Hide generic sort icon when specific order is applied */
        }
    </style>
</head>
<body>
<?php include '../../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                <h3 class="text-white">
                    <i class="fas fa-archive me-2"></i>
                    Departments Archive
                </h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="bulk-actions mb-3">
                            <?php if ($canRestore): ?>
                            <button type="button" id="restore-selected" class="btn btn-success" disabled style="display: none;">Restore Selected</button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                            <button type="button" id="delete-selected-permanently" class="btn btn-danger" disabled style="display: none;">Delete Selected Permanently</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search department archives...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterAction" class="form-select">
                            <option value="">All Actions</option>
                            <option value="Create">Create</option>
                            <option value="Modified">Modified</option>
                            <option value="Remove">Remove</option>
                            <option value="Delete">Delete</option>
                            <option value="Restored">Restored</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterStatus" class="form-select">
                            <option value="">All Status</option>
                            <option value="Successful">Successful</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-12 mt-2 text-end">
                        <button type="button" id="clearArchiveFilters" class="btn btn-secondary">
                            <i class="fas fa-times-circle me-1"></i> Clear Filters
                        </button>
                    </div>
                </div>
                
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
                            <th class="sortable" data-sort-by="track_id"># <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort-by="operator_name">User <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort-by="module">Module <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort-by="action">Action <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort-by="department_name">Details <i class="fas fa-sort"></i></th>
                            <th>Changes</th>
                            <th class="sortable" data-sort-by="status">Status <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort-by="date_time">Date &amp; Time <i class="fas fa-sort"></i></th>
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
                                    <td data-label="Module">
                                        <?php echo !empty($log['module']) ? htmlspecialchars(trim($log['module'])) : '<em class="text-muted">N/A</em>'; ?>
                                    </td>
                                    <td data-label="Action" class="action-cell">
                                        <?php
                                        $actionText = !empty($log['action']) ? $log['action'] : 'Unknown';
                                        echo '<span class="action-badge action-' . strtolower($actionText) . '">';
                                        echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                        echo '</span>';
                                        ?>
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
                                    <td data-label="Changes">
                                        <?php echo formatChanges($log['old_val']); ?>
                                    </td>
                                    <td data-label="Status" class="status-cell">
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
                                            <?php if ($canRestore): ?>
                                            <button type="button" class="btn btn-success restore-btn" data-id="<?php echo $log['deleted_dept_id']; ?>">
                                                <i class="fas fa-undo me-1"></i> Restore
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($canDelete): ?>
                                            <button type="button" class="btn btn-danger delete-permanent-btn" data-id="<?php echo $log['deleted_dept_id']; ?>">
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
                                        <h4>No Archived Departments Found</h4>
                                        <p class="text-muted">There are no archived departments to display.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                <?php $totalLogs = count($logs); ?>
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
                </div> </div>
        </div>
    </div>
</div> <div class="modal fade" id="deleteArchiveModal" tabindex="-1">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Check if jQuery is loaded, if not, load it
    if (typeof jQuery === 'undefined') {
        console.warn('jQuery not found, loading it now...');
        document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
    }
</script>

<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js" defer></script>
<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/logs.js" defer></script>
<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/archive_filters.js" defer></script>
<script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/sort_archives.js" defer></script>

<script>
    // Pass RBAC permissions to JavaScript
    var userPrivileges = {
        canRestore: <?php echo json_encode($canRestore); ?>,
        canRemove: <?php echo json_encode($canRemove); ?>,
        canDelete: <?php echo json_encode($canDelete); ?>
    };
    
    // Custom initialization for this page
    document.addEventListener('DOMContentLoaded', function() {
        // Set the correct table ID for pagination
        window.paginationConfig = window.paginationConfig || {};
        window.paginationConfig.tableId = 'archiveTableBody';
        
        // Store original rows for filtering
        window.allRows = Array.from(document.querySelectorAll('#archiveTableBody tr'));
        
        // Initialize Pagination
        initPagination({
            tableId: 'archiveTableBody',
            currentPage: 1
        });
        
        // Initialize Select2 for dropdowns if jQuery and Select2 are available
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            jQuery('#filterAction, #filterStatus, #rowsPerPageSelect').select2({
                minimumResultsForSearch: 10, // Only show search box if more than 10 items
                width: '100%'
            });
        }
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Store original rows for filtering
        window.allRows = Array.from(document.querySelectorAll('#archiveTableBody tr'));
        
        // Initialize Pagination
        initPagination({
            tableId: 'archiveTableBody',
            currentPage: 1
        });
        
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
    
    // Safeguard for jQuery usage - ensures $ and jQuery are defined before using them
    function useJQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            console.warn('jQuery not available, retrying in 100ms...');
            setTimeout(function() {
                useJQuery(callback);
            }, 100);
        }
    }
    
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
    
    // Delegated events for checkboxes using the safeguard
    useJQuery(function($) {
        $(document).on('change', '#select-all', function () {
            $('.select-row').prop('checked', $(this).prop('checked'));
            updateBulkButtons();
        });
        $(document).on('change', '.select-row', updateBulkButtons);
    });
    
    function updateBulkButtons() {
        useJQuery(function($) {
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
        });
    }

    // --- Individual Restore (with modal) ---
    useJQuery(function($) {
        $(document).on('click', '.restore-btn', function (e) {
            if (!userPrivileges.canRestore) return;
            
            e.preventDefault();
            restoreId = $(this).data('id');
            var restoreModal = new bootstrap.Modal(document.getElementById('restoreArchiveModal'));
            restoreModal.show();
        });
    });

    // --- Individual Restore AJAX Call ---
    useJQuery(function($) {
        $(document).on('click', '#confirmRestoreBtn', function () {
            if (!userPrivileges.canRestore || !restoreId) return;
            
            
            $.ajax({
                url: '../../management/department_manager/restore_department.php',
                method: 'POST',
                data: { 
                    id: restoreId,
                    action: 'restore',
                    module: 'Department Management' 
                },
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
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'restore');
                }
            });
        });
    });

    // --- Individual Permanent Delete ---
    useJQuery(function($) {
        $(document).on('click', '.delete-permanent-btn', function(e) {
            if (!userPrivileges.canDelete) return;
            
            e.preventDefault();
            deleteId = $(this).data('id');
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteArchiveModal'));
            deleteModal.show();
        });
    });

    // --- Individual Permanent Delete AJAX Call ---
    useJQuery(function($) {
        $(document).on('click', '#confirmDeleteBtn', function () {
            if (!userPrivileges.canDelete || !deleteId) return;
            
            
            $.ajax({
                url: '../../management/department_manager/delete_department.php',
                method: 'POST',
                data: { 
                    dept_id: deleteId, 
                    permanent: 1,
                    action: 'delete',
                    module: 'Department Management' 
                },
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
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'delete');
                }
            });
        });
    });

    var bulkRestoreIds = [];

    // When bulk restore button is clicked, gather selected IDs and show modal
    useJQuery(function($) {
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
    });

    // When confirming bulk restore in the modal, perform the AJAX call
    useJQuery(function($) {
        $(document).on('click', '#confirmBulkRestoreBtn', function () {
            if (!userPrivileges.canRestore || bulkRestoreIds.length === 0) return;
                
            $.ajax({
                url: '../../management/department_manager/restore_department.php',
                method: 'POST',
                data: { 
                    dept_ids: bulkRestoreIds,
                    action: 'bulk_restore',
                    module: 'Department Management'
                },
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
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'bulk restore');
                }
            });
        });
    });

    // --- Bulk Delete ---
    useJQuery(function($) {
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
    });
    
    useJQuery(function($) {
        $(document).on('click', '#confirmBulkDeleteBtn', function () {
            if (!userPrivileges.canDelete || bulkDeleteIds.length === 0) return;
        
            $.ajax({
                url: '../../management/department_manager/delete_department.php',
                method: 'POST',
                data: { 
                    dept_ids: bulkDeleteIds, 
                    permanent: 1,
                    action: 'bulk_delete',
                    module: 'Department Management'
                },
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
                        showToast(response.message || 'Unknown error occurred', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    handleAjaxError(xhr, status, error, 'bulk delete');
                }
            });
        });
    });
</script>
<?php include '../../../general/footer.php'; ?>
 
</body>
</html> 
