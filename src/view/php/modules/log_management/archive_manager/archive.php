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
    'date_time' => 'a.Date_Time'
];

// Validate sort_by and sort_order
if (!array_key_exists($sort_by, $allowedSortColumns)) {
    $sort_by = 'track_id'; // Fallback to default
}
if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
    $sort_order = 'desc'; // Fallback to default
}

// --- Filter Logic ---
$dateFilterType = $_GET['date_filter_type'] ?? '';
$baseWhere = "(u.is_disabled = 1) AND a.TrackID = (
    SELECT MAX(a2.TrackID)
    FROM audit_log a2
    WHERE a2.EntityID = a.EntityID
)";
$params = [];

// Apply action filter
if (!empty($_GET['action_type'])) {
    $baseWhere .= " AND a.Action = :action_type";
    $params[':action_type'] = $_GET['action_type'];
}

// Apply status filter
if (!empty($_GET['status'])) {
    $baseWhere .= " AND a.Status = :status";
    $params[':status'] = $_GET['status'];
}

// Apply search filter
if (!empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    $baseWhere .= " AND (
        op.Email LIKE :search_email 
        OR a.Details LIKE :search_details
        OR a.OldVal LIKE :search_oldval
        OR a.NewVal LIKE :search_newval
    )";
    $params[':search_email'] = $searchTerm;
    $params[':search_details'] = $searchTerm;
    $params[':search_oldval'] = $searchTerm;
    $params[':search_newval'] = $searchTerm;
}

// Apply date filters
if ($dateFilterType === 'mdy') {
    if (!empty($_GET['date_from'])) {
        $baseWhere .= " AND DATE(a.Date_Time) >= :date_from";
        $params[':date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $baseWhere .= " AND DATE(a.Date_Time) <= :date_to";
        $params[':date_to'] = $_GET['date_to'];
    }
} else if ($dateFilterType === 'month_year') {
    if (!empty($_GET['month_year_from'])) {
        $baseWhere .= " AND a.Date_Time >= STR_TO_DATE(:month_year_from, '%Y-%m')";
        $params[':month_year_from'] = $_GET['month_year_from'];
    }
    if (!empty($_GET['month_year_to'])) {
        $baseWhere .= " AND a.Date_Time < DATE_ADD(STR_TO_DATE(:month_year_to, '%Y-%m'), INTERVAL 1 MONTH)";
        $params[':month_year_to'] = $_GET['month_year_to'];
    }
} else if ($dateFilterType === 'year') {
    if (!empty($_GET['year_from'])) {
        $baseWhere .= " AND YEAR(a.Date_Time) >= :year_from";
        $params[':year_from'] = $_GET['year_from'];
    }
    if (!empty($_GET['year_to'])) {
        $baseWhere .= " AND YEAR(a.Date_Time) <= :year_to";
        $params[':year_to'] = $_GET['year_to'];
    }
}

$orderByClause = "ORDER BY " . $allowedSortColumns[$sort_by] . " " . strtoupper($sort_order);

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
WHERE $baseWhere
{$orderByClause}
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
  
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
                    User Management Archives
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
                
                <!-- Filter Section -->
                <form method="GET" class="row g-3 mb-4" id="archiveFilterForm" action="">
                    <!-- Date Range selector -->
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold">Date Filter Type</label>
                        <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm">
                            <option value="" <?= empty($_GET['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
                            <option value="month_year" <?= (($_GET['date_filter_type'] ?? '') === 'month_year') ? 'selected' : '' ?>>Month-Year Range</option>
                            <option value="year" <?= (($_GET['date_filter_type'] ?? '') === 'year') ? 'selected' : '' ?>>Year Range</option>
                            <option value="mdy" <?= (($_GET['date_filter_type'] ?? '') === 'mdy') ? 'selected' : '' ?>>Month-Date-Year Range</option>
                        </select>
                    </div>
                    
                    <!-- MDY Range -->
                    <div class="col-12 col-md-3 date-filter date-mdy d-none">
                        <label class="form-label fw-semibold">Date From</label>
                        <input type="date" name="date_from" class="form-control shadow-sm"
                            value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                            placeholder="Start Date (YYYY-MM-DD)">
                    </div>
                    <div class="col-12 col-md-3 date-filter date-mdy d-none">
                        <label class="form-label fw-semibold">Date To</label>
                        <input type="date" name="date_to" class="form-control shadow-sm"
                            value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                            placeholder="End Date (YYYY-MM-DD)">
                    </div>
                    
                    <!-- Year Range -->
                    <div class="col-12 col-md-3 date-filter date-year d-none">
                        <label class="form-label fw-semibold">Year From</label>
                        <input type="number" name="year_from" class="form-control shadow-sm"
                            min="1900" max="2100"
                            placeholder="e.g., 2023"
                            value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-3 date-filter date-year d-none">
                        <label class="form-label fw-semibold">Year To</label>
                        <input type="number" name="year_to" class="form-control shadow-sm"
                            min="1900" max="2100"
                            placeholder="e.g., 2025"
                            value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
                    </div>
                    
                    <!-- Month-Year Range -->
                    <div class="col-12 col-md-3 date-filter date-month_year d-none">
                        <label class="form-label fw-semibold">From (MM-YYYY)</label>
                        <input type="month" name="month_year_from" class="form-control shadow-sm"
                            value="<?= htmlspecialchars($_GET['month_year_from'] ?? '') ?>"
                            placeholder="e.g., 2023-01">
                    </div>
                    <div class="col-12 col-md-3 date-filter date-month_year d-none">
                        <label class="form-label fw-semibold">To (MM-YYYY)</label>
                        <input type="month" name="month_year_to" class="form-control shadow-sm"
                            value="<?= htmlspecialchars($_GET['month_year_to'] ?? '') ?>"
                            placeholder="e.g., 2023-12">
                    </div>
                    
                    <!-- Search bar -->
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label fw-semibold">Search</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" id="searchInput" class="form-control" 
                                placeholder="Search archives..." 
                                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="col-6 col-md-2 d-grid">
                        <button type="submit" id="applyFilters" class="btn btn-dark">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                    
                    <div class="col-6 col-md-2 d-grid">
                        <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                            <i class="fas fa-times-circle"></i> Clear
                        </button>
                    </div>
                </form>
                
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
                            <col class="date">
                            <col class="actions">
                        </colgroup>
                        <thead class="table-light">
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th class="sortable" data-sort-by="track_id"># <i class="fas fa-sort"></i></th>
                            <th class="sortable" data-sort-by="operator_name">User <i class="fas fa-sort"></i></th>
                            <th>Module </th>
                            <th>Action</th>
                            <th>Details</th> 
                            <th>Changes</th> 
                            <th class="sortable" data-sort-by="date_time">Date &amp; Time <i class="fas fa-sort"></i></th>
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
                                    <td data-label="Date &amp; Time">
                                        <div class="d-flex align-items-center">
                                            <i class="far fa-clock me-2"></i>
                                            <?php echo htmlspecialchars($log['date_time']); ?>
                                        </div>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="btn-group-vertical gap-1">
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
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                <?php $totalLogs = count($logs); ?>
                                <input type="hidden" id="total-users" value="<?= $totalLogs?>">
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
                Are you sure you want to permanently delete this record?
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
                Are you sure you want to restore this record?
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
                Are you sure you want to permanently delete the selected users?
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
                Are you sure you want to restore the selected users?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmBulkRestoreBtn" class="btn btn-success">Restore</button>
            </div>
        </div>
    </div>
</div>

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
    
    // Custom filtering for archive page
    document.addEventListener('DOMContentLoaded', function() {
        
        // Initialize filters
        const actionFilter = document.getElementById('filterAction');
        const statusFilter = document.getElementById('filterStatus');
        const searchInput = document.getElementById('searchInput');
        
        // Remove direct event handlers to prevent immediate filtering
        // Only filter when the form is submitted via the Filter button
        
        // Date filter handling
        const filterType = document.getElementById('dateFilterType');
        const allDateFilters = document.querySelectorAll('.date-filter');

        function updateDateFields() {
            allDateFilters.forEach(field => field.classList.add('d-none'));
            if (!filterType.value) return;
            document.querySelectorAll('.date-' + filterType.value).forEach(field => field.classList.remove('d-none'));
        }

        if (filterType) {
            filterType.addEventListener('change', updateDateFields);
            updateDateFields(); // Initialize on page load
        }

        // Clear filters button
        const clearFiltersBtn = document.getElementById('clearFilters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                const form = document.getElementById('archiveFilterForm');
                form.reset();
                
                // Clear date filter type and hide all date filter fields
                const filterType = document.getElementById('dateFilterType');
                if (filterType) {
                    filterType.value = '';
                    document.querySelectorAll('.date-filter').forEach(field => field.classList.add('d-none'));
                }
                
                // Clear search input
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = '';
                }
                
                // Submit the form to reset the data
                form.submit();
            });
        }
    });
    
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
        
        // Initial check
        setTimeout(forcePaginationCheck, 100);
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
                        showToast(response.message, 'success', 5000, 'Success');
                    });
                } else {
                    showToast(response.message, 'error', 5000, 'Error');
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
                        showToast(response.message, 'success', 5000, 'Success');
                    });
                } else {
                    showToast(response.message, 'error', 5000, 'Error');
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
                if (response.status && response.status.toLowerCase() === 'success') {
                    $('#archiveTable').load(location.href + ' #archiveTable', function () {
                        updateBulkButtons();
                        showToast(response.message, 'success', 5000, 'Success');
                    });
                } else {
                    showToast(response.message, 'error', 5000, 'Error');
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

                if (response.status && response.status.toLowerCase() === 'success') {
                    $('#archiveTable').load(location.href + ' #archiveTable', function () {
                        updateBulkButtons();
                        showToast(response.message, 'success', 5000, 'Success');
                    });
                } else {
                    showToast(response.message, 'error', 5000, 'Error');
                }
            },
            error: function() {
                showToast('Error processing bulk delete.', 'error');
            }
        });
    });
</script>

<script>
$(document).ready(function() {

    // Store original table rows on page load for restoring
    var originalRows = [];
    
    function initializeRows() {
        // Get a fresh copy of all rows
        originalRows = $('#archiveTableBody tr').toArray();
    }
    
    // Initialize once page loads
    initializeRows();
    
    // Function to filter rows
    function filterTable() {
        var actionFilter = $('#filterAction').val().toLowerCase();
        var statusFilter = $('#filterStatus').val().toLowerCase();
        var searchFilter = $('#searchInput').val().toLowerCase();

        // Clone all original rows
        var allRows = originalRows.slice();
        
        // Filter rows
        var filteredRows = $.grep(allRows, function(row) {
            var $row = $(row);
            var matches = true;
            
            // Filter by action
            if (actionFilter) {
                var $actionCell = $row.find('td[data-label="Action"]');
                var actionText = $actionCell.text().toLowerCase();
                
                if (!actionText.includes(actionFilter)) {
                    matches = false;
                }
            }
            
            // Filter by status
            if (statusFilter && matches) {
                var $statusCell = $row.find('td[data-label="Status"]');
                var statusText = $statusCell.text().toLowerCase();
                
                if (!statusText.includes(statusFilter)) {
                    matches = false;
                }
            }
            
            // Filter by search
            if (searchFilter && matches) {
                var rowText = $row.text().toLowerCase();
                if (!rowText.includes(searchFilter)) {
                    matches = false;
                }
            }
            
            return matches;
        });
        
        
        // Clear table
        $('#archiveTableBody').empty();
        
        // Show filtered rows or no results message
        if (filteredRows.length > 0) {
            // Add filtered rows back to table
            $.each(filteredRows, function(i, row) {
                $('#archiveTableBody').append(row);
            });
        } else {
            // Show no results message
            var noResultsHtml = `
                <tr id="no-results-row">
                    <td colspan="10" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <h4>No matching records found</h4>
                            <p class="text-muted">Try adjusting your filter criteria.</p>
                        </div>
                    </td>
                </tr>
            `;
            $('#archiveTableBody').html(noResultsHtml);
        }
        
        // Update pagination if needed
        if (typeof updatePagination === 'function') {
            updatePagination();
        }
    }
    
    // Attach filter handlers
    $('#filterAction').on('change', function() {
        filterTable();
    });
    
    $('#filterStatus').on('change', function() {
        filterTable();
    });
    
    $('#searchInput').on('input', function() {
        filterTable();
    });
    
    // In case table gets updated by pagination or AJAX
    $(document).ajaxComplete(function() {
        // Wait a moment for DOM to update
        setTimeout(function() {
            initializeRows();
        }, 100);
    });
    
    // Refresh table when rows per page changes
    $('#rowsPerPageSelect').on('change', function() {
        // Wait for pagination to update
        setTimeout(function() {
            initializeRows();
            filterTable();
        }, 100);
    });
    
    // Handle pagination button clicks
    $('#prevPage, #nextPage, #pagination').on('click', function() {
        // Wait for pagination to update
        setTimeout(function() {
            initializeRows();
            filterTable();
        }, 100);
    });
    
    // Function to help debug - check what text is in the rows
    window.checkRowContents = function() {
        $('#archiveTableBody tr').each(function(i, row) {
            var $row = $(row);
            
            var $actionCell = $row.find('td[data-label="Action"]');
            if ($actionCell.length) {
            }
            
            var $statusCell = $row.find('td[data-label="Status"]');
            if ($statusCell.length) {
            }
        });
    };
    
    // Run initial check
    window.checkRowContents();
});
</script>

<?php include '../../../general/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
