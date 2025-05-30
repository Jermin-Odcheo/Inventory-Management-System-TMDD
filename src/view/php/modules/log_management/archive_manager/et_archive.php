<?php
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';

// If not logged in, redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php");
    exit();
}

// Initialize RBAC for Equipment Transaction
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Equipment Transactions', 'View');
$canRestore = $rbac->hasPrivilege('Equipment Transactions', 'Restore');
$canRemove = $rbac->hasPrivilege('Equipment Transactions', 'Remove');
$canDelete = $rbac->hasPrivilege('Equipment Transactions', 'Permanently Delete');

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
$moduleType = $_GET['module_type'] ?? '';

$baseWhere = "a.Module IN ('Purchase Order', 'Charge Invoice', 'Receiving Report')
  AND LOWER(a.Action) IN ('delete', 'remove', 'soft delete', 'permanent delete')
  AND a.Status = 'Successful'
  AND a.TrackID = (
      SELECT MAX(a2.TrackID)
      FROM audit_log a2
      WHERE a2.EntityID = a.EntityID
        AND a2.Module = a.Module
  )
  AND (
      (a.Module = 'Purchase Order' AND po.is_disabled = 1)
      OR (a.Module = 'Charge Invoice' AND ci.is_disabled = 1)
      OR (a.Module = 'Receiving Report' AND rr.is_disabled = 1)
  )";

// Apply module type filter
if (!empty($moduleType)) {
    $baseWhere .= " AND a.Module = :module_type";
    $params[':module_type'] = $moduleType;
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
    a.EntityID AS deleted_entity_id
FROM audit_log a
JOIN users op ON a.UserID = op.id
LEFT JOIN purchase_order po ON a.Module = 'Purchase Order' AND a.EntityID = po.id
LEFT JOIN charge_invoice ci ON a.Module = 'Charge Invoice' AND a.EntityID = ci.id
LEFT JOIN receive_report rr ON a.Module = 'Receiving Report' AND a.EntityID = rr.id
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
    if ($action === 'modified') {
        return '<i class="fas fa-user-edit"></i>';
    } elseif ($action === 'add') {
        return '<i class="fas fa-user-plus"></i>';
    } elseif ($action === 'delete' || $action === 'remove') {
        return '<i class="fas fa-user-slash"></i>';
    } else {
        return '<i class="fas fa-info-circle"></i>';
    }
}

/**
 * Helper function to return a status icon.
 */
function getStatusIcon($status)
{
    $status = strtolower((string)$status);
    return ($status === 'successful')
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
    <title>Equipment Transaction Archives</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        .main-content {
            padding-top: 100px;
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
                        Equipment Transaction Archives
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
                    <form method="GET" class="row g-3 mb-4 w-100" id="archiveFilterForm" action="">
                        <div class="col-md-3">
                            <label for="moduleType" class="form-label">Module Type</label>
                            <select class="form-select" name="module_type" id="moduleType">
                                <option value="">All</option>
                                <?php
                                // Define the allowed modules
                                $allowedModules = ['Purchase Order', 'Charge Invoice', 'Receiving Report'];
                                
                                // If no modules found in database, use the allowed modules
                                if (empty($moduleTypes)) {
                                    $moduleTypes = $allowedModules;
                                }
                                
                                // Output the options
                                foreach ($moduleTypes as $module) {
                                    $selected = ($_GET['module_type'] ?? '') === $module ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($module) . '" ' . $selected . '>' .
                                        htmlspecialchars($module) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <!-- Date Range selector -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Date Filter Type</label>
                            <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm">
                                <option value="" <?= empty($_GET['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
                                <option value="month_year" <?= (($_GET['date_filter_type'] ?? '') === 'month_year') ? 'selected' : '' ?>>Month-Year Range</option>
                                <option value="year" <?= (($_GET['date_filter_type'] ?? '') === 'year') ? 'selected' : '' ?>>Year Range</option>
                                <option value="mdy" <?= (($_GET['date_filter_type'] ?? '') === 'mdy') ? 'selected' : '' ?>>Month-Date-Year Range</option>
                            </select>
                        </div>
                        
                        <!-- MDY Range -->
                        <div class="col-md-3 date-filter date-mdy d-none">
                            <label class="form-label fw-semibold">Date From</label>
                            <input type="date" name="date_from" class="form-control shadow-sm"
                                value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                                placeholder="Start Date (YYYY-MM-DD)">
                        </div>
                        <div class="col-md-3 date-filter date-mdy d-none">
                            <label class="form-label fw-semibold">Date To</label>
                            <input type="date" name="date_to" class="form-control shadow-sm"
                                value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                placeholder="End Date (YYYY-MM-DD)">
                        </div>
                        
                        <!-- Year Range -->
                        <div class="col-md-3 date-filter date-year d-none">
                            <label class="form-label fw-semibold">Year From</label>
                            <input type="number" name="year_from" class="form-control shadow-sm"
                                min="1900" max="2100"
                                placeholder="e.g., 2023"
                                value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 date-filter date-year d-none">
                            <label class="form-label fw-semibold">Year To</label>
                            <input type="number" name="year_to" class="form-control shadow-sm"
                                min="1900" max="2100"
                                placeholder="e.g., 2025"
                                value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
                        </div>
                        
                        <!-- Month-Year Range -->
                        <div class="col-md-3 date-filter date-month_year d-none">
                            <label class="form-label fw-semibold">From (MM-YYYY)</label>
                            <input type="month" name="month_year_from" class="form-control shadow-sm"
                                value="<?= htmlspecialchars($_GET['month_year_from'] ?? '') ?>"
                                placeholder="e.g., 2023-01">
                        </div>
                        <div class="col-md-3 date-filter date-month_year d-none">
                            <label class="form-label fw-semibold">To (MM-YYYY)</label>
                            <input type="month" name="month_year_to" class="form-control shadow-sm"
                                value="<?= htmlspecialchars($_GET['month_year_to'] ?? '') ?>"
                                placeholder="e.g., 2023-12">
                        </div>
                        
                        <!-- Search bar -->
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Search</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" id="searchInput" class="form-control" 
                                    placeholder="Search equipment type archives..." 
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" id="applyFilters" class="btn btn-dark flex-grow-1">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm flex-grow-1">
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
                                    <th>Details</th>
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
                                                <input type="checkbox" class="select-row" value="<?php echo $log['deleted_entity_id']; ?>">
                                            </td>
                                            <td data-label="Track ID">
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($log['track_id']); ?></span>
                                            </td>
                                            <td data-label="Operator">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <small><?php echo htmlspecialchars($log['operator_email']); ?></small>
                                                </div>
                                            </td>
                                            <td data-label="Module">
                                                <?php echo !empty($log['module']) ? htmlspecialchars(trim((string)$log['module'])) : '<em class="text-muted">N/A</em>'; ?>
                                            </td>
                                            <td data-label="Action">
                                                <?php
                                                $rawAction   = !empty($log['action']) ? $log['action'] : 'Unknown';
                                                // make everything lowercase then uppercase only the first character
                                                $actionText  = ucfirst(strtolower($rawAction));

                                                echo '<span class="action-badge action-' . strtolower($actionText) . '">';
                                                echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                                echo '</span>';
                                                ?>
                                            </td>

                                            <td data-label="Details">
                                                <?php echo nl2br(htmlspecialchars((string)($log['details'] ?? ''))); ?>
                                            </td>
                                            <td data-label="Changes">
                                                <?php echo formatChanges($log['old_val']); ?>
                                            </td>
                                            <td data-label="Status">
                                                <?php
                                                $statusText = (strtolower((string)$log['status']) === 'successful') ? 'Successful' : 'Failed';
                                                $statusClass = (strtolower((string)$log['status']) === 'successful') ? 'bg-success' : 'bg-danger';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo getStatusIcon($log['status']) . ' ' . htmlspecialchars($statusText); ?>
                                                </span>
                                            </td>
                                            <td data-label="Date & Time">
                                                <div class="d-flex align-items-center">
                                                    <i class="far fa-clock me-2"></i>
                                                    <?php 
                                                    if (!empty($log['date_time'])) {
                                                        $date = new DateTime($log['date_time']);
                                                        echo $date->format('M d, Y h:i A');
                                                    } else {
                                                        echo '<em class="text-muted">N/A</em>';
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td data-label="Actions">
                                                <div class="btn-group-vertical gap-1">
                                                    <?php if ($canRestore): ?>
                                                        <button type="button" class="btn btn-success restore-btn" data-id="<?php echo $log['deleted_entity_id']; ?>">
                                                            <i class="fas fa-undo me-1"></i> Restore
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($canDelete): ?>
                                                        <button type="button" class="btn btn-danger delete-permanent-btn" data-id="<?php echo $log['deleted_entity_id']; ?>">
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
                                    <input type="hidden" id="total-users" value="<?= $totalLogs ?>">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
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

    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/logs.js" defer></script>
    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/pagination.js" defer></script>
    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/archive_filters.js" defer></script>
    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/sort_archives.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../../../general/footer.php'; ?>
    <script>
        // Pass RBAC permissions to JavaScript
        var userPrivileges = {
            canRestore: <?php echo json_encode($canRestore); ?>,
            canRemove: <?php echo json_encode($canRemove); ?>,
            canDelete: <?php echo json_encode($canDelete); ?>
        };

        // DOMContentLoaded listener for initial setup
        document.addEventListener('DOMContentLoaded', function() {
            // Set the correct table ID for both pagination.js and logs.js
            window.paginationConfig = window.paginationConfig || {};
            window.paginationConfig.tableId = 'archiveTableBody';

            // Populate window.allRows with all rows from the table body.
            // This is the full dataset that pagination.js and archive_filters.js will operate on.
            window.allRows = Array.from(document.querySelectorAll('#archiveTableBody tr'));
            console.log(`em_archive.php: Initial window.allRows populated with ${window.allRows.length} rows.`);

            // Initialize Pagination
            // This will set up pagination.js with the correct table ID.
            if (typeof initPagination === 'function' && !window.paginationInitialized) {
                initPagination({
                    tableId: 'archiveTableBody',
                    currentPage: 1
                });
                window.paginationInitialized = true; // Prevent double initialization
                console.log('em_archive.php: initPagination called.');
            } else if (window.paginationInitialized) {
                console.log('em_archive.php: Pagination already initialized. Skipping initPagination.');
            } else {
                console.error('em_archive.php: initPagination function not found.');
            }

            // After initial setup, apply filters to ensure the table starts in a filtered state
            // and pagination is correctly applied to the filtered data.
            // This will also trigger updatePagination internally.
            if (window.archiveFilters && typeof window.archiveFilters.applyFilters === 'function') {
                window.archiveFilters.applyFilters();
                console.log('em_archive.php: Initial applyFilters called.');
            } else {
                console.error('em_archive.php: window.archiveFilters.applyFilters function not found. Initial filtering might not work.');
            }
        });

        // jQuery document ready block for bulk actions and AJAX callbacks
        $(document).ready(function() {
            var deleteId = null;
            var restoreId = null;
            var bulkDeleteIds = [];
            var restoreModule = null; // Added for module-specific restore
            var deleteModule = null; // Added for module-specific delete

            // Delegated events for checkboxes
            $(document).on('change', '#select-all', function() {
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
            $(document).on('click', '.restore-btn', function(e) {
                if (!userPrivileges.canRestore) return;

                e.preventDefault();
                restoreId = $(this).data('id');
                restoreModule = $(this).closest('tr').find('td[data-label="Module"]').text().trim();
                var restoreModal = new bootstrap.Modal(document.getElementById('restoreArchiveModal'));
                restoreModal.show();
            });
            $(document).on('click', '#confirmRestoreBtn', function() {
                if (!userPrivileges.canRestore || !restoreId || !restoreModule) return;

                var restoreUrl = '';
                var data = {
                    id: restoreId
                };
                // Determine the correct restore URL based on the module
                if (restoreModule === 'Equipment Location') {
                    restoreUrl = '../../equipment_manager/restore_equipment_location.php';
                } else if (restoreModule === 'Equipment Status') {
                    restoreUrl = '../../equipment_manager/restore_equipment_status.php';
                } else if (restoreModule === 'Equipment Details') {
                    restoreUrl = '../../equipment_manager/restore_equipment_details.php';
                } else if (restoreModule === 'Purchase Order') {
                    restoreUrl = '../../equipment_transactions/restore_purchase_order.php';
                } else if (restoreModule === 'Charge Invoice') {
                    restoreUrl = '../../equipment_transactions/restore_charge_invoice.php';
                } else if (restoreModule === 'Receiving Report') {
                    restoreUrl = '../../equipment_transactions/restore_receiving_report.php';
                } else {
                    showToast('Unknown module for restore: ' + restoreModule, 'error');
                    console.error('Unknown module for restore:', restoreModule);
                    return;
                }

                $.ajax({
                    url: restoreUrl,
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('restoreArchiveModal'));
                        modalInstance.hide();
                        if (response.status && response.status.toLowerCase() === 'success') {
                            // Reload only the tbody content to avoid full page refresh
                            $('#archiveTableBody').load(location.href + ' #archiveTableBody > *', function() {
                                // After content is loaded, re-capture all rows for filtering and pagination
                                if (window.archiveFilters && typeof window.archiveFilters.captureRows === 'function') {
                                    window.archiveFilters.captureRows();
                                } else {
                                    console.error('window.archiveFilters.captureRows function not available after AJAX reload.');
                                }
                                // Then re-apply filters and update pagination based on the new data
                                if (window.archiveFilters && typeof window.archiveFilters.applyFilters === 'function') {
                                    window.archiveFilters.applyFilters();
                                } else {
                                    console.error('window.archiveFilters.applyFilters function not available after AJAX reload.');
                                }
                                updateBulkButtons();
                                showToast(response.message, 'success');
                            });
                        } else {
                            // Check for specific duplicate entry error and display user-friendly message
                            if (response.message && (
                                response.message.includes('Duplicate entry') || 
                                response.message.includes('Integrity constraint violation: 1062')
                            )) {
                                // Display a user-friendly message based on the module type
                                if (restoreModule === 'Purchase Order') {
                                    showToast('This Purchase Order cannot be restored because a document with the same PO number already exists in the system.', 'error');
                                } else if (restoreModule === 'Charge Invoice') {
                                    showToast('This Charge Invoice cannot be restored because a document with the same CI number already exists in the system.', 'error');
                                } else if (restoreModule === 'Receiving Report') {
                                    showToast('This Receiving Report cannot be restored because a document with the same RR number already exists in the system.', 'error');
                                } else {
                                    showToast('This record cannot be restored because a similar active record already exists in the system.', 'error');
                                }
                            } else {
                                showToast(response.message, 'error');
                            }
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
                deleteModule = $(this).closest('tr').find('td[data-label="Module"]').text().trim();
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteArchiveModal'));
                deleteModal.show();
            });

            $(document).on('click', '#confirmDeleteBtn', function() {
                if (!userPrivileges.canDelete || !deleteId || !deleteModule) return;

                var deleteUrl = '';
                // Determine the correct delete URL based on the module
                if (deleteModule === 'Purchase Order') {
                    deleteUrl = '../../equipment_transactions/delete_purchase_order.php';
                } else if (deleteModule === 'Charge Invoice') {
                    deleteUrl = '../../equipment_transactions/delete_charge_invoice.php';
                } else if (deleteModule === 'Receiving Report') {
                    deleteUrl = '../../equipment_transactions/delete_receiving_report.php';
                } else {
                    showToast('Unknown module for delete: ' + deleteModule, 'error');
                    console.error('Unknown module for delete:', deleteModule);
                    return;
                }

                var data = {
                    id: deleteId,
                    permanent: 1
                };
                $.ajax({
                    url: deleteUrl,
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteArchiveModal'));
                        modalInstance.hide();
                        if (response.status && response.status.toLowerCase() === 'success') {
                            // Reload only the tbody content
                            $('#archiveTableBody').load(location.href + ' #archiveTableBody > *', function() {
                                // After content is loaded, re-capture all rows for filtering and pagination
                                if (window.archiveFilters && typeof window.archiveFilters.captureRows === 'function') {
                                    window.archiveFilters.captureRows();
                                } else {
                                    console.error('window.archiveFilters.captureRows function not available after AJAX reload.');
                                }
                                // Then re-apply filters and update pagination based on the new data
                                if (window.archiveFilters && typeof window.archiveFilters.applyFilters === 'function') {
                                    window.archiveFilters.applyFilters();
                                } else {
                                    console.error('window.archiveFilters.applyFilters function not available after AJAX reload.');
                                }
                                updateBulkButtons();
                                showToast(response.message, 'success');
                            });
                        } else {
                            showToast(response.message || 'Error processing delete for module: ' + deleteModule, 'error');
                        }
                    },
                    error: function() {
                        showToast('Error processing request.', 'error');
                    }
                });
            });


            var bulkRestoreIds = [];

            // When bulk restore button is clicked, gather selected IDs and show modal
            $(document).on('click', '#restore-selected', function() {
                if (!userPrivileges.canRestore) return;

                var selected = $('.select-row:checked');
                bulkRestoreIds = [];
                selected.each(function() {
                    bulkRestoreIds.push($(this).val());
                });
                var bulkRestoreModal = new bootstrap.Modal(document.getElementById('bulkRestoreModal'));
                bulkRestoreModal.show();
            });

            // When confirming bulk restore in the modal, perform the AJAX call
            $(document).on('click', '#confirmBulkRestoreBtn', function() {
                if (!userPrivileges.canRestore || bulkRestoreIds.length === 0) return;

                // Group selected items by module
                var moduleGroups = {};
                $('.select-row:checked').each(function() {
                    var $row = $(this).closest('tr');
                    var entityId = $(this).val();
                    var module = $row.find('td[data-label="Module"]').text().trim();
                    
                    if (!moduleGroups[module]) {
                        moduleGroups[module] = [];
                    }
                    moduleGroups[module].push(entityId);
                });

                var totalRequests = Object.keys(moduleGroups).length;
                var completedRequests = 0;
                var successCount = 0;
                var errorMessages = [];

                // Process each module group separately
                for (var module in moduleGroups) {
                    var restoreUrl = '';
                    
                    // Determine the correct restore URL based on the module
                    if (module === 'Purchase Order') {
                        restoreUrl = '../../equipment_transactions/restore_purchase_order.php';
                    } else if (module === 'Charge Invoice') {
                        restoreUrl = '../../equipment_transactions/restore_charge_invoice.php';
                    } else if (module === 'Receiving Report') {
                        restoreUrl = '../../equipment_transactions/restore_receiving_report.php';
                    } else {
                        errorMessages.push('Unknown module for restore: ' + module);
                        completedRequests++;
                        continue;
                    }

                    // For each module type, send a bulk request with all IDs of that type
                    $.ajax({
                        url: restoreUrl,
                        method: 'POST',
                        data: function() {
                            // Use the correct parameter name based on module type
                            var data = { bulk: 1 };
                            if (module === 'Purchase Order') {
                                data.po_ids = moduleGroups[module];
                            } else if (module === 'Charge Invoice') {
                                data.ci_ids = moduleGroups[module];
                            } else if (module === 'Receiving Report') {
                                data.rr_ids = moduleGroups[module];
                            }
                            return data;
                        }(),
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            completedRequests++;
                            
                            if (response.status && response.status.toLowerCase() === 'success') {
                                successCount++;
                            } else {
                                // Check for duplicate entry errors and format user-friendly messages
                                if (response.message && (
                                    response.message.includes('Duplicate entry') || 
                                    response.message.includes('Integrity constraint violation: 1062')
                                )) {
                                    // Create more user-friendly error messages based on module type
                                    if (module === 'Purchase Order') {
                                        errorMessages.push('One or more Purchase Orders cannot be restored because documents with the same PO numbers already exist in the system');
                                    } else if (module === 'Charge Invoice') {
                                        errorMessages.push('One or more Charge Invoices cannot be restored because documents with the same CI numbers already exist in the system');
                                    } else if (module === 'Receiving Report') {
                                        errorMessages.push('One or more Receiving Reports cannot be restored because documents with the same RR numbers already exist in the system');
                                    } else {
                                        errorMessages.push('One or more records cannot be restored because similar active records already exist in the system');
                                    }
                                } else {
                                errorMessages.push(response.message || 'Error processing request');
                                }
                            }
                            
                            // Check if all requests are completed
                            if (completedRequests === totalRequests) {
                                processAllRestoreResponses();
                            }
                        },
                        error: function() {
                            completedRequests++;
                            errorMessages.push('Error processing restore request');
                            
                            // Check if all requests are completed
                            if (completedRequests === totalRequests) {
                                processAllRestoreResponses();
                            }
                        }
                    });
                }
                
                // Process all responses after all AJAX calls are completed
                function processAllRestoreResponses() {
                    var modalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkRestoreModal'));
                    modalInstance.hide();
                    
                    // Reload the table content
                    $('#archiveTableBody').load(location.href + ' #archiveTableBody > *', function() {
                        // After content is loaded, re-capture all rows for filtering and pagination
                        if (window.archiveFilters && typeof window.archiveFilters.captureRows === 'function') {
                            window.archiveFilters.captureRows();
                        } else {
                            console.error('window.archiveFilters.captureRows function not available after AJAX reload.');
                        }
                        // Then re-apply filters and update pagination based on the new data
                        if (window.archiveFilters && typeof window.archiveFilters.applyFilters === 'function') {
                            window.archiveFilters.applyFilters();
                        } else {
                            console.error('window.archiveFilters.applyFilters function not available after AJAX reload.');
                        }
                        updateBulkButtons();
                        
                        // Show appropriate toast message
                        if (successCount === totalRequests) {
                            showToast('All items restored successfully', 'success');
                        } else if (successCount > 0) {
                            showToast('Some items restored successfully. Errors: ' + errorMessages.join('; '), 'warning');
                        } else {
                            showToast('Failed to restore items: ' + errorMessages.join('; '), 'error');
                        }
                    });
                }
            });


            // --- Bulk Delete ---
            $(document).on('click', '#delete-selected-permanently', function() {
                if (!userPrivileges.canDelete) return;

                var selected = $('.select-row:checked');
                bulkDeleteIds = [];
                selected.each(function() {
                    bulkDeleteIds.push($(this).val());
                });
                var bulkModal = new bootstrap.Modal(document.getElementById('bulkDeleteModal'));
                bulkModal.show();
            });
            $(document).on('click', '#confirmBulkDeleteBtn', function() {
                if (!userPrivileges.canDelete || bulkDeleteIds.length === 0) return;

                // Group selected items by module
                var moduleGroups = {};
                $('.select-row:checked').each(function() {
                    var $row = $(this).closest('tr');
                    var entityId = $(this).val();
                    var module = $row.find('td[data-label="Module"]').text().trim();
                    
                    if (!moduleGroups[module]) {
                        moduleGroups[module] = [];
                    }
                    moduleGroups[module].push(entityId);
                });

                var totalRequests = Object.keys(moduleGroups).length;
                var completedRequests = 0;
                var successCount = 0;
                var errorMessages = [];

                // Process each module group separately
                for (var module in moduleGroups) {
                    var deleteUrl = '';
                    
                    // Determine the correct delete URL based on the module
                    if (module === 'Purchase Order') {
                        deleteUrl = '../../equipment_transactions/delete_purchase_order.php';
                    } else if (module === 'Charge Invoice') {
                        deleteUrl = '../../equipment_transactions/delete_charge_invoice.php';
                    } else if (module === 'Receiving Report') {
                        deleteUrl = '../../equipment_transactions/delete_receiving_report.php';
                    } else {
                        errorMessages.push('Unknown module for delete: ' + module);
                        completedRequests++;
                        continue;
                    }

                    // For each module type, send a bulk request with all IDs of that type
                    $.ajax({
                        url: deleteUrl,
                        method: 'POST',
                        data: function() {
                            // Use the correct parameter name based on module type
                            var data = { 
                                bulk: 1,
                                permanent: 1
                            };
                            
                            if (module === 'Purchase Order') {
                                data.po_ids = moduleGroups[module];
                            } else if (module === 'Charge Invoice') {
                                data.ci_ids = moduleGroups[module];
                            } else if (module === 'Receiving Report') {
                                data.rr_ids = moduleGroups[module];
                            } else {
                                data.ids = moduleGroups[module]; // Fallback for compatibility
                            }
                            
                            return data;
                        }(),
                        dataType: 'json',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(response) {
                            completedRequests++;
                            
                            if (response.status && response.status.toLowerCase() === 'success') {
                                successCount++;
                            } else {
                                errorMessages.push(response.message || 'Error processing request');
                            }
                            
                            // Check if all requests are completed
                            if (completedRequests === totalRequests) {
                                processAllDeleteResponses();
                            }
                        },
                        error: function() {
                            completedRequests++;
                            errorMessages.push('Error processing delete request');
                            
                            // Check if all requests are completed
                            if (completedRequests === totalRequests) {
                                processAllDeleteResponses();
                            }
                        }
                    });
                }
                
                // Process all responses after all AJAX calls are completed
                function processAllDeleteResponses() {
                    var bulkModalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal'));
                    bulkModalInstance.hide();
                    
                    // Reload the table content
                    $('#archiveTableBody').load(location.href + ' #archiveTableBody > *', function() {
                        // After content is loaded, re-capture all rows for filtering and pagination
                        if (window.archiveFilters && typeof window.archiveFilters.captureRows === 'function') {
                            window.archiveFilters.captureRows();
                        } else {
                            console.error('window.archiveFilters.captureRows function not available after AJAX reload.');
                        }
                        // Then re-apply filters and update pagination based on the new data
                        if (window.archiveFilters && typeof window.archiveFilters.applyFilters === 'function') {
                            window.archiveFilters.applyFilters();
                        } else {
                            console.error('window.archiveFilters.applyFilters function not available after AJAX reload.');
                        }
                        updateBulkButtons();
                        
                        // Show appropriate toast message
                        if (successCount === totalRequests) {
                            showToast('All items deleted successfully', 'success');
                        } else if (successCount > 0) {
                            showToast('Some items deleted successfully. Errors: ' + errorMessages.join('; '), 'warning');
                        } else {
                            showToast('Failed to delete items: ' + errorMessages.join('; '), 'error');
                        }
                    });
                }
            });
        }); // End of jQuery document ready

        // Custom script to ensure filtering only happens on button click
        document.addEventListener('DOMContentLoaded', function() {
            // Date filter handling
            const filterType = document.getElementById('dateFilterType');
            const allDateFilters = document.querySelectorAll('.date-filter');
            const filterForm = document.getElementById('archiveFilterForm');
            const moduleTypeSelect = document.getElementById('moduleType');

            function updateDateFields() {
                allDateFilters.forEach(field => field.classList.add('d-none'));
                if (!filterType.value) return;
                document.querySelectorAll('.date-' + filterType.value).forEach(field => field.classList.remove('d-none'));
            }

            if (filterType) {
                filterType.addEventListener('change', updateDateFields);
                updateDateFields(); // Initialize on page load
            }

            // Filter form submission
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get all form data
                    const formData = new FormData(filterForm);
                    const params = new URLSearchParams();
                    
                    // Add all form fields to params
                    for (let [key, value] of formData.entries()) {
                        // Only add non-empty values and handle date filters properly
                        if (value && value.trim() !== '') {
                            // Special handling for date filters
                            if (key === 'date_filter_type') {
                                params.append(key, value);
                                // Only add date values if a filter type is selected
                                if (value === 'mdy') {
                                    if (formData.get('date_from')) params.append('date_from', formData.get('date_from'));
                                    if (formData.get('date_to')) params.append('date_to', formData.get('date_to'));
                                } else if (value === 'month_year') {
                                    if (formData.get('month_year_from')) params.append('month_year_from', formData.get('month_year_from'));
                                    if (formData.get('month_year_to')) params.append('month_year_to', formData.get('month_year_to'));
                                } else if (value === 'year') {
                                    if (formData.get('year_from')) params.append('year_from', formData.get('year_from'));
                                    if (formData.get('year_to')) params.append('year_to', formData.get('year_to'));
                                }
                            } else {
                                // For non-date fields, just add them if they have a value
                                params.append(key, value);
                            }
                        }
                    }
                    
                    // Redirect to the same page with the filter parameters
                    window.location.href = window.location.pathname + '?' + params.toString();
                });
            }
            
            // Clear filters button
            const clearFiltersBtn = document.getElementById('clearFilters');
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Reset all form fields
                    if (filterForm) {
                        filterForm.reset();
                    }
                    
                    // Reset date filter type
                    if (filterType) {
                        filterType.value = '';
                    }
                    
                    // Reset module type
                    if (moduleTypeSelect) {
                        moduleTypeSelect.value = '';
                    }
                    
                    // Hide all date filter fields
                    allDateFilters.forEach(field => {
                        field.classList.add('d-none');
                        // Clear the value of any input within the field
                        const inputs = field.querySelectorAll('input');
                        inputs.forEach(input => input.value = '');
                    });
                    
                    // Clear search input
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    
                    // Redirect to the base URL without any parameters
                    window.location.href = window.location.pathname;
                });
            }
        });
    </script>
</body>

</html>
