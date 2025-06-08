<?php
/**
 * Department Archive Module
 *
 * This file provides functionality for archiving and managing historical department records. It handles the storage, retrieval, and management of archived department data, ensuring data preservation while maintaining system performance. The module supports comprehensive archiving features including data compression, indexing, and secure storage.
 *
 * @package    InventoryManagementSystem
 * @subpackage LogManagement
 * @author     TMDD Interns 25'
 */
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';

/**
 * If the user is not logged in, they are redirected to the login page.
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

/**
 * @var RBACService $rbac The RBAC service instance.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Management', 'View');

/**
 * @var bool $canRestore The flag indicating if the user can restore department data.
 * @var bool $canRemove The flag indicating if the user can remove department data.
 * @var bool $canDelete The flag indicating if the user can permanently delete department data.
 */
$canRestore = $rbac->hasPrivilege('Management', 'Restore');
$canRemove = $rbac->hasPrivilege('Management', 'Remove');
$canDelete = $rbac->hasPrivilege('Management', 'Permanently Delete');

// --- Sorting Logic ---
/**
 * @var string $sort_by The column to sort the data by.
 * @var string $sort_order The order to sort the data by.
 */
$sort_by = $_GET['sort_by'] ?? 'track_id'; // Default sort column
$sort_order = $_GET['sort_order'] ?? 'desc'; // Default sort order

// Whitelist allowed columns to prevent SQL injection
/**
 * @var array $allowedSortColumns The allowed columns to sort the data by.
 */
$allowedSortColumns = [
    'track_id' => 'a.TrackID',
    'operator_name' => 'operator_name', // Alias from CONCAT
    'module' => 'a.Module',
    'action' => 'a.Action',
    'status' => 'a.Status',
    'date_time' => 'a.Date_Time',
    'department_name' => 'd.department_name' // For sorting by department name
];

/**
 * @var string $sort_by The column to sort the data by.
 * @var string $sort_order The order to sort the data by.
 */
if (!array_key_exists($sort_by, $allowedSortColumns)) {
    $sort_by = 'track_id'; // Fallback to default
}
/**
 * @var string $sort_order The order to sort the data by.
 */
if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
    $sort_order = 'desc'; // Fallback to default
}

/**
 * @var string $dateFilterType The type of date filter to apply.
 * @var string $baseWhere The base SQL WHERE clause.
 * @var array $params The parameters for the SQL query.
 */
$dateFilterType = $_GET['date_filter_type'] ?? '';
$baseWhere = "LOWER(a.Module) = 'department management' 
    AND LOWER(a.Action) IN ('delete', 'remove')
    AND a.TrackID = (
        SELECT MAX(a2.TrackID)
        FROM audit_log a2
        WHERE a2.EntityID = a.EntityID AND a2.Module = a.Module
    )
    AND d.is_disabled = 1";
$params = [];

/**
 * @var string $actionType The action type to apply to the SQL query.
 */
if (!empty($_GET['action_type'])) {
    $baseWhere .= " AND a.Action = :action_type";
    $params[':action_type'] = $_GET['action_type'];
}

/**
 * @var string $status The status to apply to the SQL query.
 */
if (!empty($_GET['status'])) {
    $baseWhere .= " AND a.Status = :status";
    $params[':status'] = $_GET['status'];
}

/**
 * @var string $searchTerm The search term to apply to the SQL query.
 */
if (!empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    $baseWhere .= " AND (
        op.Email LIKE :search_email 
        OR a.Details LIKE :search_details
        OR a.OldVal LIKE :search_oldval
        OR a.NewVal LIKE :search_newval
        OR d.department_name LIKE :search_dept
    )";
    $params[':search_email'] = $searchTerm;
    $params[':search_details'] = $searchTerm;
    $params[':search_oldval'] = $searchTerm;
    $params[':search_newval'] = $searchTerm;
    $params[':search_dept'] = $searchTerm;
}

/**
 * @var string $dateFilterType The type of date filter to apply.
 * @var string $baseWhere The base SQL WHERE clause.
 * @var array $params The parameters for the SQL query.
 */
// Server-side validation for date filters to prevent "from" being greater than "to"
$dateValidationError = false;

if ($dateFilterType === 'mdy') {
    $fromDate = $_GET['date_from'] ?? null;
    $toDate = $_GET['date_to'] ?? null;
    
    if (!empty($fromDate) && !empty($toDate) && strtotime($fromDate) > strtotime($toDate)) {
        $dateValidationError = true;
        // Reset these parameters to prevent invalid filtering
        unset($_GET['date_from'], $_GET['date_to']);
    } else {
        if (!empty($fromDate)) {
            $baseWhere .= " AND DATE(a.Date_Time) >= :date_from";
            $params[':date_from'] = $fromDate;
        }
        if (!empty($toDate)) {
            $baseWhere .= " AND DATE(a.Date_Time) <= :date_to";
            $params[':date_to'] = $toDate;
        }
    }
} else if ($dateFilterType === 'month_year') {
    $fromMonthYear = $_GET['month_year_from'] ?? null;
    $toMonthYear = $_GET['month_year_to'] ?? null;
    
    if (!empty($fromMonthYear) && !empty($toMonthYear) && $fromMonthYear > $toMonthYear) {
        $dateValidationError = true;
        // Reset these parameters to prevent invalid filtering
        unset($_GET['month_year_from'], $_GET['month_year_to']);
    } else {
        if (!empty($fromMonthYear)) {
            $baseWhere .= " AND a.Date_Time >= STR_TO_DATE(:month_year_from, '%Y-%m')";
            $params[':month_year_from'] = $fromMonthYear;
        }
        if (!empty($toMonthYear)) {
            $baseWhere .= " AND a.Date_Time < DATE_ADD(STR_TO_DATE(:month_year_to, '%Y-%m'), INTERVAL 1 MONTH)";
            $params[':month_year_to'] = $toMonthYear;
        }
    }
} else if ($dateFilterType === 'year') {
    $fromYear = isset($_GET['year_from']) ? (int)$_GET['year_from'] : null;
    $toYear = isset($_GET['year_to']) ? (int)$_GET['year_to'] : null;
    
    if ($fromYear && $toYear && $fromYear > $toYear) {
        $dateValidationError = true;
        // Reset these parameters to prevent invalid filtering
        unset($_GET['year_from'], $_GET['year_to']);
    } else {
        if ($fromYear) {
            $baseWhere .= " AND YEAR(a.Date_Time) >= :year_from";
            $params[':year_from'] = $fromYear;
        }
        if ($toYear) {
            $baseWhere .= " AND YEAR(a.Date_Time) <= :year_to";
            $params[':year_to'] = $toYear;
        }
    }
}

/**
 * @var int $rowsPerPage The number of rows per page.
 * @var int $currentPage The current page number.
 * @var int $offset The offset for the query.
 */
$rowsPerPage = isset($_GET['rows_per_page']) ? (int)$_GET['rows_per_page'] : 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $rowsPerPage;

/**
 * @var string $countQuery The SQL query to count total records for pagination.
 */
$countQuery = "
    SELECT COUNT(*) as total
    FROM audit_log a
    JOIN users op ON a.UserID = op.id
    JOIN departments d ON a.EntityID = d.id
    WHERE $baseWhere
";

/**
 * @var PDOStatement $countStmt The prepared statement for the count query.
 * @var int $totalRows The total number of records.
 * @var int $totalPages The total number of pages.
 */
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRows / $rowsPerPage);

/**
 * @var string $orderByClause The SQL ORDER BY clause for the query.
 */
$orderByClause = "ORDER BY " . $allowedSortColumns[$sort_by] . " " . strtoupper($sort_order);

/**
 * @var string $query The SQL query for archived department data.
 */
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
        a.EntityID AS deleted_entity_id,
        d.department_name
    FROM audit_log a
    JOIN users op ON a.UserID = op.id
    JOIN departments d ON a.EntityID = d.id
    WHERE $baseWhere
    $orderByClause
    LIMIT :limit OFFSET :offset
";

try {
    $stmt = $pdo->prepare($query);
    $params[':limit'] = $rowsPerPage;
    $params[':offset'] = $offset;
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$logs) {
        $logs = [];
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

/**
 * Format JSON data into a list for display in the 'Changes' column.
 *
 * @param string $jsonStr The JSON string containing the data to format.
 * @return string HTML formatted list of key-value pairs from the JSON data.
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
 * Helper function to return an icon based on the action type.
 *
 * @param string $action The action type to get an icon for.
 * @return string HTML string containing the FontAwesome icon for the action.
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
 * Helper function to return a status icon based on the status value.
 *
 * @param string $status The status of the action (e.g., 'successful').
 * @return string HTML string containing the FontAwesome icon for the status.
 */
function getStatusIcon($status)
{
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
}

/**
 * Format JSON data to display old values only in the 'Changes' column.
 *
 * This function processes the old values from a JSON string and formats them into an HTML list
 * for display purposes, highlighting changes made to department data.
 *
 * @param string $oldJsonStr The JSON string containing the old values to format.
 * @return string HTML formatted list of old values.
 */
function formatChanges($oldJsonStr)
{
    $oldData = json_decode($oldJsonStr, true);

    // If not an array, simply return the value
    if (!is_array($oldData)) {
        return '<span>' . htmlspecialchars($oldJsonStr) . '</span>';
    }
    /**
     * @var string $html The HTML string containing the formatted changes.
     */
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

    <style>
        .main-content {
            padding-top: 150px;
        }

        /* Styles for sortable headers */
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 25px;
            /* Make space for the icon */
        }

        th.sortable .fas {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
            /* Default icon color */
            transition: color 0.2s ease;
        }

        th.sortable:hover .fas {
            color: #888;
            /* Hover color */
        }

        th.sortable.asc .fas.fa-sort-up,
        th.sortable.desc .fas.fa-sort-down {
            color: #333;
            /* Active icon color */
        }

        th.sortable.asc .fas.fa-sort,
        th.sortable.desc .fas.fa-sort {
            display: none;
            /* Hide generic sort icon when specific order is applied */
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

                    <!-- Filter Section -->
                    <form method="GET" class="row g-3 mb-4" id="archiveFilterForm" action="">
                        <?php if ($dateValidationError): ?>
                        <div class="col-md-12">
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Invalid date range: "From" date cannot be greater than "To" date. Date filter has been reset.
                            </div>
                        </div>
                        <?php endif; ?>
                        
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
                                    placeholder="Search department archives..."
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-6 col-md-2 d-grid">
                            <button type="submit" id="applyFilters" class="btn btn-dark">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                        
                        <div id="date-filter-error" class="position-relative"></div>

                        <div class="col-6 col-md-2 d-grid">
                            <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                                <i class="fas fa-times-circle"></i> Clear
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive" id="table">
                        <table id="archiveTable" class="table table-hover">
                            <colgroup>
                                <col class="track">
                                <col class="user">
                                <col class="action">
                                <col class="details">
                                <col class="changes">
                                <col class="date">
                                <col class="actions">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th class="sortable" data-sort-by="track_id"># <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort-by="operator_name">User <i class="fas fa-sort"></i></th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Changes</th>
                                    <th class="sortable" data-sort-by="date_time">Date & Time <i class="fas fa-sort"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="archiveTableBody">
                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td data-label="Track ID">
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($log['track_id']); ?></span>
                                            </td>
                                            <td data-label="User">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <small><?php echo htmlspecialchars($log['operator_email']); ?></small>
                                                </div>
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
                                                if (isset($log['department_name']) && !empty($log['department_name'])) {
                                                    echo "Department: <strong>" . htmlspecialchars($log['department_name']) . "</strong>";
                                                } else {
                                                    echo nl2br(htmlspecialchars($log['details']));
                                                }
                                                ?>
                                            </td>
                                            <td data-label="Changes">
                                                <?php echo formatChanges($log['old_val']); ?>
                                            </td>
                                            <td data-label="Date & Time">
                                                <div class="d-flex align-items-center">
                                                    <i class="far fa-clock me-2"></i>
                                                    <?php
                                                    if (!empty($log['date_time']) && $log['date_time'] !== '0000-00-00 00:00:00') {
                                                        try {
                                                            $dateTime = new DateTime($log['date_time']);
                                                            echo $dateTime->format('Y-m-d H:i:s');
                                                        } catch (Exception $e) {
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
                                        <td colspan="7">
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
                                    Showing <span id="currentPage"><?= $currentPage ?></span> of <span id="totalPages"><?= $totalPages ?></span> pages (<span id="totalRows"><?= $totalRows ?></span> entries)
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto ms-sm-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                        <option value="10" <?= $rowsPerPage == 10 ? 'selected' : '' ?>>10</option>
                                        <option value="20" <?= $rowsPerPage == 20 ? 'selected' : '' ?>>20</option>
                                        <option value="30" <?= $rowsPerPage == 30 ? 'selected' : '' ?>>30</option>
                                        <option value="50" <?= $rowsPerPage == 50 ? 'selected' : '' ?>>50</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <ul class="pagination justify-content-center" id="pagination">
                                    <!-- First Page Button -->
                                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=1&<?= http_build_query(array_diff_key($_GET, array_flip(['page']))) ?>" id="firstPage" <?= $currentPage <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>«</a>
                                    </li>
                                    <!-- Previous Page Button -->
                                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= max(1, $currentPage - 1) ?>&<?= http_build_query(array_diff_key($_GET, array_flip(['page']))) ?>" id="prevPage" <?= $currentPage <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>‹</a>
                                    </li>
                                    <!-- Page Numbers -->
                                    <?php
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $startPage + 4);
                                    if ($endPage === $totalPages) {
                                        $startPage = max(1, $endPage - 4);
                                    }

                                    if ($startPage > 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }

                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        echo '<li class="page-item ' . ($i == $currentPage ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&' . http_build_query(array_diff_key($_GET, array_flip(['page']))) . '">' . $i . '</a></li>';
                                    }

                                    if ($endPage < $totalPages) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    ?>
                                    <!-- Next Page Button -->
                                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= min($totalPages, $currentPage + 1) ?>&<?= http_build_query(array_diff_key($_GET, array_flip(['page']))) ?>" id="nextPage" <?= $currentPage >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>›</a>
                                    </li>
                                    <!-- Last Page Button -->
                                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $totalPages ?>&<?= http_build_query(array_diff_key($_GET, array_flip(['page']))) ?>" id="lastPage" <?= $currentPage >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>»</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/logs.js" defer></script>
    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/archive_filters.js" defer></script>
    <script type="text/javascript" src="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>src/control/js/sort_archives.js" defer></script>
    <?php include_once '../../../general/footer.php'; ?>
    <script>
        // Pass RBAC permissions to JavaScript
        var userPrivileges = {
            canRestore: <?php echo json_encode($canRestore); ?>,
            canRemove: <?php echo json_encode($canRemove); ?>,
            canDelete: <?php echo json_encode($canDelete); ?>
        };

        // Main JavaScript for pagination, filters, and sorting
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Select2 for dropdowns if available
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                jQuery('#rowsPerPageSelect').select2({
                    minimumResultsForSearch: Infinity, // Disable search for small dropdowns
                    width: 'auto'
                });
            }

            // Function to update URL with new parameters
            function updateUrl(params) {
                const currentParams = new URLSearchParams(window.location.search);
                for (const [key, value] of Object.entries(params)) {
                    if (value) {
                        currentParams.set(key, value);
                    } else {
                        currentParams.delete(key);
                    }
                }
                window.location.href = window.location.pathname + '?' + currentParams.toString();
            }

            // First page button
            document.getElementById('firstPage').addEventListener('click', function(e) {
                e.preventDefault();
                const currentPage = parseInt(document.getElementById('currentPage').textContent);
                if (currentPage > 1) {
                    updateUrl({
                        page: 1
                    });
                }
            });

            // Previous page button
            document.getElementById('prevPage').addEventListener('click', function(e) {
                e.preventDefault();
                const currentPage = parseInt(document.getElementById('currentPage').textContent);
                if (currentPage > 1) {
                    updateUrl({
                        page: currentPage - 1
                    });
                }
            });

            // Next page button
            document.getElementById('nextPage').addEventListener('click', function(e) {
                e.preventDefault();
                const currentPage = parseInt(document.getElementById('currentPage').textContent);
                const totalPages = <?php echo json_encode($totalPages); ?>;
                if (currentPage < totalPages) {
                    updateUrl({
                        page: currentPage + 1
                    });
                }
            });

            // Last page button
            document.getElementById('lastPage').addEventListener('click', function(e) {
                e.preventDefault();
                const currentPage = parseInt(document.getElementById('currentPage').textContent);
                const totalPages = <?php echo json_encode($totalPages); ?>;
                if (currentPage < totalPages) {
                    updateUrl({
                        page: totalPages
                    });
                }
            });

            // Rows per page select
            document.getElementById('rowsPerPageSelect').addEventListener('change', function() {
                updateUrl({
                    page: 1, // Reset to first page when changing rows per page
                    rows_per_page: this.value
                });
            });

            // Date filter handling
            const filterType = document.getElementById('dateFilterType');
            const allDateFilters = document.querySelectorAll('.date-filter');
            const filterForm = document.getElementById('archiveFilterForm');

            function updateDateFields() {
                allDateFilters.forEach(field => field.classList.add('d-none'));
                // Clear any error messages when changing filter type
                document.getElementById('date-filter-error').innerHTML = '';
                if (filterType.value) {
                    document.querySelectorAll('.date-' + filterType.value).forEach(field => field.classList.remove('d-none'));
                }
            }

            if (filterType) {
                filterType.addEventListener('change', updateDateFields);
                updateDateFields(); // Initialize on page load
            }

            // Filter form submission with client-side validation
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Validate date filters
                    let isValid = true;
                    let errorMessage = '';
                    const selectedType = filterType.value;
                    
                    if (selectedType) {
                        let fromDate, toDate;
                        
                        if (selectedType === 'mdy') {
                            fromDate = document.querySelector('input[name="date_from"]').value;
                            toDate = document.querySelector('input[name="date_to"]').value;
                            
                            // Check if dates are empty
                            if (fromDate && !toDate) {
                                isValid = false;
                                errorMessage = 'Please select both From and To dates';
                            } else if (!fromDate && toDate) {
                                isValid = false;
                                errorMessage = 'Please select both From and To dates';
                            } else if (fromDate && toDate) {
                                // Convert to Date objects for comparison
                                const fromDateObj = new Date(fromDate);
                                const toDateObj = new Date(toDate);
                                
                                // Check if from date is greater than to date
                                if (fromDateObj > toDateObj) {
                                    isValid = false;
                                    errorMessage = 'From date cannot be greater than To date';
                                }
                            }
                        } else if (selectedType === 'month_year') {
                            fromDate = document.querySelector('input[name="month_year_from"]').value;
                            toDate = document.querySelector('input[name="month_year_to"]').value;
                            
                            // Check if dates are empty
                            if (fromDate && !toDate) {
                                isValid = false;
                                errorMessage = 'Please select both From and To month-year values';
                            } else if (!fromDate && toDate) {
                                isValid = false;
                                errorMessage = 'Please select both From and To month-year values';
                            } else if (fromDate && toDate) {
                                // Compare the month-year values as strings (YYYY-MM format)
                                if (fromDate > toDate) {
                                    isValid = false;
                                    errorMessage = 'From month-year cannot be greater than To month-year';
                                }
                            }
                        } else if (selectedType === 'year') {
                            fromDate = document.querySelector('input[name="year_from"]').value;
                            toDate = document.querySelector('input[name="year_to"]').value;
                            
                            // Check if years are empty
                            if (fromDate && !toDate) {
                                isValid = false;
                                errorMessage = 'Please select both From and To years';
                            } else if (!fromDate && toDate) {
                                isValid = false;
                                errorMessage = 'Please select both From and To years';
                            } else if (fromDate && toDate) {
                                // Compare as integers
                                const fromYear = parseInt(fromDate);
                                const toYear = parseInt(toDate);
                                
                                if (fromYear > toYear) {
                                    isValid = false;
                                    errorMessage = 'From year cannot be greater than To year';
                                }
                            }
                        }
                    }
                    
                    // Display validation error if any
                    if (!isValid) {
                        // Show the error message
                        const dateContainer = document.getElementById('date-filter-error');
                        dateContainer.innerHTML = '<div class="validation-tooltip" style="position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background-color: #d9534f; color: white; padding: 6px 10px; border-radius: 4px; font-size: 0.85em; z-index: 1000; margin-top: 5px; white-space: nowrap; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">' + errorMessage + '<div style="position: absolute; top: -5px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-bottom: 5px solid #d9534f;"></div></div>';
                        
                        // Auto hide after 3 seconds
                        setTimeout(function() {
                            const errorElement = document.querySelector('#date-filter-error .validation-tooltip');
                            if (errorElement) {
                                errorElement.style.transition = 'opacity 0.5s';
                                errorElement.style.opacity = '0';
                                setTimeout(() => {
                                    document.getElementById('date-filter-error').innerHTML = '';
                                }, 500);
                            }
                        }, 3000);
                        
                        return false;
                    }
                    
                    // If validation passes, continue with form submission
                    const formData = new FormData(filterForm);
                    const params = new URLSearchParams(window.location.search);

                    // Reset page to 1 when applying filters
                    params.set('page', '1');

                    // Preserve rows_per_page if set
                    const rowsPerPage = document.getElementById('rowsPerPageSelect').value;
                    if (rowsPerPage) {
                        params.set('rows_per_page', rowsPerPage);
                    }

                    // Add form fields to params
                    for (let [key, value] of formData.entries()) {
                        if (value) {
                            params.set(key, value);
                        } else {
                            params.delete(key);
                        }
                    }

                    window.location.href = window.location.pathname + '?' + params.toString();
                });
            }

            // Clear filters button
            const clearFiltersBtn = document.getElementById('clearFilters');
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const params = new URLSearchParams();

                    // Preserve rows_per_page if set
                    const rowsPerPage = document.getElementById('rowsPerPageSelect').value;
                    if (rowsPerPage) {
                        params.set('rows_per_page', rowsPerPage);
                    }

                    // Reset form and date fields
                    if (filterForm) {
                        filterForm.reset();
                    }
                    if (filterType) {
                        filterType.value = '';
                    }
                    allDateFilters.forEach(field => {
                        field.classList.add('d-none');
                        const inputs = field.querySelectorAll('input');
                        inputs.forEach(input => input.value = '');
                    });

                    // Redirect with only rows_per_page (if set)
                    window.location.href = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                });
            }

            // Sorting
            document.querySelectorAll('.sortable').forEach(header => {
                header.addEventListener('click', function() {
                    const sortBy = this.getAttribute('data-sort-by');
                    const currentParams = new URLSearchParams(window.location.search);
                    const currentSortBy = currentParams.get('sort_by') || 'track_id';
                    const currentSortOrder = currentParams.get('sort_order') || 'desc';

                    // Toggle sort order if clicking the same column
                    const newSortOrder = (sortBy === currentSortBy && currentSortOrder === 'desc') ? 'asc' : 'desc';

                    // Update URL with sorting and reset page to 1
                    currentParams.set('sort_by', sortBy);
                    currentParams.set('sort_order', newSortOrder);
                    currentParams.set('page', '1');

                    window.location.href = window.location.pathname + '?' + currentParams.toString();
                });
            });
        });

        var deleteId = null;
        var restoreId = null;
        var bulkDeleteIds = [];
        var bulkRestoreIds = [];

        // Safeguard for jQuery usage
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

        // Handle AJAX errors
        function handleAjaxError(xhr, status, error, operation) {
            console.error('AJAX Error during ' + operation + ':', {
                status: status,
                error: error,
                response: xhr.responseText,
                statusCode: xhr.status
            });

            let errorMessage = 'Error processing ' + operation + ' request';

            try {
                const response = JSON.parse(xhr.responseText);
                if (response && response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                errorMessage = xhr.responseText || errorMessage;
            }

            showToast(errorMessage, 'error');
        }

        // Delegated events for checkboxes
        useJQuery(function($) {
            $(document).on('change', '#select-all', function() {
                $('.select-row').prop('checked', $(this).prop('checked'));
                updateBulkButtons();
            });
            $(document).on('change', '.select-row', updateBulkButtons);
        });

        function updateBulkButtons() {
            useJQuery(function($) {
                var count = $('.select-row:checked').length;
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

        // Individual Restore (with modal)
        useJQuery(function($) {
            $(document).on('click', '.restore-btn', function(e) {
                if (!userPrivileges.canRestore) return;

                e.preventDefault();
                restoreId = $(this).data('id');
                var restoreModal = new bootstrap.Modal(document.getElementById('restoreArchiveModal'));
                restoreModal.show();
            });
        });

        // Individual Restore AJAX Call
        useJQuery(function($) {
            $(document).on('click', '#confirmRestoreBtn', function() {
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
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('restoreArchiveModal'));
                        modalInstance.hide();

                        if (response.status && response.status.toLowerCase() === 'success') {
                            $('#archiveTable').load(location.href + ' #archiveTable', function() {
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

        // Individual Permanent Delete
        useJQuery(function($) {
            $(document).on('click', '.delete-permanent-btn', function(e) {
                if (!userPrivileges.canDelete) return;

                e.preventDefault();
                deleteId = $(this).data('id');
                var deleteModal = new bootstrap.Modal(document.getElementById('deleteArchiveModal'));
                deleteModal.show();
            });
        });

        // Individual Permanent Delete AJAX Call
        useJQuery(function($) {
            $(document).on('click', '#confirmDeleteBtn', function() {
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
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteArchiveModal'));
                        modalInstance.hide();

                        if (response.status && response.status.toLowerCase() === 'success') {
                            $('#archiveTable').load(location.href + ' #archiveTable', function() {
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

        // Bulk Restore
        useJQuery(function($) {
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
        });

        // Bulk Restore AJAX Call
        useJQuery(function($) {
            $(document).on('click', '#confirmBulkRestoreBtn', function() {
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
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkRestoreModal'));
                        modalInstance.hide();
                        if (response.status === 'success') {
                            $('#archiveTable').load(location.href + ' #archiveTable', function() {
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

        // Bulk Delete
        useJQuery(function($) {
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
        });

        // Bulk Delete AJAX Call
        useJQuery(function($) {
            $(document).on('click', '#confirmBulkDeleteBtn', function() {
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
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        var bulkModalInstance = bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal'));
                        bulkModalInstance.hide();

                        if (response.status === 'success') {
                            $('#archiveTable').load(location.href + ' #archiveTable', function() {
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
</body>

</html>