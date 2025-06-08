<?php
/**
 * Department Audit Log Module
 *
 * This file provides comprehensive audit logging functionality for department management activities. It tracks and records all changes made to departments, including creation, modification, and deletion events. The module ensures detailed logging of user actions, timestamps, and relevant data changes for security and accountability purposes.
 *
 * @package    InventoryManagementSystem
 * @subpackage LogManagement
 * @author     TMDD Interns 25'
 */
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';
include '../../../general/sidebar.php';

// Add dm-audit-log class to body
echo '<script>document.body.classList.add("dm-audit-log");</script>';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

/**
 * Initialize RBAC and Check Permissions
 *
 * Initializes the Role-Based Access Control (RBAC) system and checks if the user has the necessary permissions
 * to view audit logs for department management.
 *
 * @return void
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check for required privilege
$hasAuditPermission = $rbac->hasPrivilege('Audit', 'Track');
$hasDeptPermission = $rbac->hasPrivilege('Management', 'Track');

/**
 * Initialize Sorting Parameters
 *
 * Sets up the sorting parameters for displaying audit logs based on user input or default values.
 *
 * @return void
 */
$sortColumn = $_GET['sort'] ?? 'TrackID';
$sortOrder = $_GET['order'] ?? 'DESC';

/**
 * Fetch Unique Values for Dropdowns
 *
 * Retrieves unique action and status types from the audit log for filtering purposes.
 *
 * @return void
 */
try {
    // Get unique action types
    $actionTypesQuery = "SELECT DISTINCT Action 
                        FROM audit_log 
                        WHERE Module = 'Department Management' 
                        AND Action IS NOT NULL 
                        AND Action != '' 
                        ORDER BY Action";
    $actionTypesStmt = $pdo->prepare($actionTypesQuery);
    $actionTypesStmt->execute();
    $actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Debug action types query
    error_log("Action Types Query: " . $actionTypesQuery);
    error_log("Action Types Found: " . print_r($actionTypes, true));

    // Get unique status types
    $statusTypesQuery = "SELECT DISTINCT Status 
                        FROM audit_log 
                        WHERE Module = 'Department Management' 
                        AND Status IS NOT NULL 
                        AND Status != '' 
                        ORDER BY Status";
    $statusTypesStmt = $pdo->prepare($statusTypesQuery);
    $statusTypesStmt->execute();
    $statusTypes = $statusTypesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Debug status types query
    error_log("Status Types Query: " . $statusTypesQuery);
    error_log("Status Types Found: " . print_r($statusTypes, true));

    // If no values found, try without the Module filter to debug
    if (empty($actionTypes)) {
        $debugActionQuery = "SELECT DISTINCT Action, Module FROM audit_log WHERE Action IS NOT NULL AND Action != ''";
        $debugActionStmt = $pdo->prepare($debugActionQuery);
        $debugActionStmt->execute();
        $debugActions = $debugActionStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Debug - All Actions in Database: " . print_r($debugActions, true));
    }

    if (empty($statusTypes)) {
        $debugStatusQuery = "SELECT DISTINCT Status, Module FROM audit_log WHERE Status IS NOT NULL AND Status != ''";
        $debugStatusStmt = $pdo->prepare($debugStatusQuery);
        $debugStatusStmt->execute();
        $debugStatuses = $debugStatusStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Debug - All Statuses in Database: " . print_r($debugStatuses, true));
    }

} catch (PDOException $e) {
    error_log("Error fetching dropdown values: " . $e->getMessage());
    $actionTypes = [];
    $statusTypes = [];
}

/**
 * Validate Sort Column
 *
 * Ensures the sort column is one of the allowed values to prevent SQL injection.
 *
 * @return void
 */
$allowedColumns = ['TrackID', 'email', 'Module', 'Action', 'Details', 'Status', 'Date_Time'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'TrackID';
}

/**
 * Validate Sort Order
 *
 * Ensures the sort order is either ASC or DESC.
 *
 * @return void
 */
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

/**
 * Check User Permission
 *
 * Displays an access denied message if the user lacks the necessary permissions.
 *
 * @return void
 */
if (!$hasAuditPermission && !$hasDeptPermission) {
    echo '
      <div class="container d-flex justify-content-center align-items-center" 
           style="height:70vh; padding-left:300px">
        <div class="alert alert-danger text-center">
          <h1><i class="bi bi-shield-lock"></i> Access Denied</h1>
          <p class="mb-0">You do not have permission to view this page.</p>
        </div>
      </div>
    ';
    exit();
}

/**
 * Build Query Filters
 *
 * Constructs the WHERE clause and parameters for filtering audit logs based on user input.
 *
 * @return void
 */
// Start with a base WHERE clause
$where = "WHERE audit_log.Module = 'Department Management'";
$params = [];

// Filter by action type
if (!empty($_GET['action_type'])) {
    $where .= " AND audit_log.Action = :action_type";
    $params[':action_type'] = $_GET['action_type'];
}

// Filter by status
if (!empty($_GET['status'])) {
    $where .= " AND audit_log.Status = :status";
    $params[':status'] = $_GET['status'];
}

// Filter by search string
if (!empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    $where .= " AND (
        users.email LIKE :search_email 
        OR audit_log.NewVal LIKE :search_newval 
        OR audit_log.OldVal LIKE :search_oldval
        OR audit_log.Details LIKE :search_details
        OR audit_log.Action LIKE :search_action
        OR audit_log.Status LIKE :search_status
    )";
    $params[':search_email'] = $searchTerm;
    $params[':search_newval'] = $searchTerm;
    $params[':search_oldval'] = $searchTerm;
    $params[':search_details'] = $searchTerm;
    $params[':search_action'] = $searchTerm;
    $params[':search_status'] = $searchTerm;
}

// Date filters
$dateFilterType = $_GET['date_filter_type'] ?? '';

switch ($dateFilterType) {
    case 'mdy':
        if (!empty($_GET['date_from'])) {
            $where .= " AND DATE(audit_log.date_time) >= :date_from";
            $params[':date_from'] = date('Y-m-d', strtotime($_GET['date_from']));
        }
        if (!empty($_GET['date_to'])) {
            $where .= " AND DATE(audit_log.date_time) <= :date_to";
            $params[':date_to'] = date('Y-m-d', strtotime($_GET['date_to']));
        }
        break;

    case 'month_year':
        if (!empty($_GET['month_year_from'])) {
            $where .= " AND DATE_FORMAT(audit_log.date_time, '%Y-%m') >= :month_year_from";
            $params[':month_year_from'] = date('Y-m', strtotime($_GET['month_year_from']));
        }
        if (!empty($_GET['month_year_to'])) {
            $where .= " AND DATE_FORMAT(audit_log.date_time, '%Y-%m') <= :month_year_to";
            $params[':month_year_to'] = date('Y-m', strtotime($_GET['month_year_to']));
        }        
        break;

    case 'year':
        if (!empty($_GET['year_from'])) {
            $where .= " AND YEAR(audit_log.date_time) >= :year_from";
            $params[':year_from'] = intval($_GET['year_from']);
        }
        if (!empty($_GET['year_to'])) {
            $where .= " AND YEAR(audit_log.date_time) <= :year_to";
            $params[':year_to'] = intval($_GET['year_to']);
        }
        break;
}

// Debug the query and parameters
error_log("Query WHERE clause: " . $where);
error_log("Query parameters: " . print_r($params, true));

/**
 * Fetch Audit Logs
 *
 * Retrieves audit logs from the database based on the constructed filters and sorting parameters.
 *
 * @return void
 */
// Modify the query to include sorting
$query = "SELECT audit_log.*, users.email AS email 
          FROM audit_log 
          LEFT JOIN users ON audit_log.UserID = users.id
          $where
          ORDER BY $sortColumn $sortOrder";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug the results
    error_log("Number of records found: " . count($auditLogs));
} catch (PDOException $e) {
    error_log("Error executing query: " . $e->getMessage());
    $auditLogs = [];
}

/**
 * Format New Value
 *
 * Formats a JSON string into an HTML list for display purposes.
 *
 * @param string|null $jsonStr The JSON string to format.
 * @return string The formatted HTML string.
 */
function formatNewValue($jsonStr)
{
    if ($jsonStr === null) {
        return '<em class="text-muted">N/A</em>';
    }

    $data = json_decode($jsonStr, true);
    if (!is_array($data)) {
        return '<span>' . htmlspecialchars((string)$jsonStr) . '</span>';
    }

    $html = '<ul class="list-group">';
    foreach ($data as $key => $value) {
        $friendlyKey = ucwords(str_replace('_', ' ', $key));
        $displayValue = is_null($value) ? '<em>null</em>' : htmlspecialchars($value);
        $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>' . $friendlyKey . ':</strong> <span>' . $displayValue . '</span>
                  </li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Format Audit Diff
 *
 * Compares two JSON strings and shows a diff of changes, ignoring certain fields.
 *
 * @param string|null $oldJson The old JSON string.
 * @param string|null $newJson The new JSON string.
 * @param string|null $status The status of the log entry.
 * @return string The formatted diff as HTML.
 */
function formatAuditDiff($oldJson, $newJson, $status = null)
{
    if ($status !== null && strtolower($status) === 'failed') {
        return '';
    }
    if ($oldJson === null || $newJson === null) {
        return '<em class="text-muted">No comparison data available</em>';
    }

    $oldData = json_decode($oldJson, true);
    $newData = json_decode($newJson, true);

    if (!is_array($oldData) || !is_array($newData)) {
        return '<span>' . htmlspecialchars((string)$newJson) . '</span>';
    }

    $keys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    $descriptions = [];
    foreach ($keys as $key) {
        if (strtolower($key) === 'is_disabled') {
            continue;
        }
        $oldVal = $oldData[$key] ?? '';
        $newVal = $newData[$key] ?? '';
        if ($oldVal !== $newVal) {
            $friendlyField = ucwords(str_replace('_', ' ', $key));
            $descriptions[] = "The {$friendlyField} was changed from '<em>{$oldVal}</em>' to '<strong>{$newVal}</strong>'.";
        }
    }

    if (empty($descriptions)) {
        return '<em>No changes detected.</em>';
    }

    $html = '<ul class="list-unstyled mb-0">';
    $total = count($descriptions);
    $i = 0;
    foreach ($descriptions as $desc) {
        $html .= "<li>{$desc}";
        if (++$i < $total) {
            $html .= "<hr class='my-1'>";
        }
        $html .= "</li>";
    }
    $html .= "</ul>";
    return $html;
}

/**
 * Returns an icon based on the given action.
 */
function getActionIcon($action)
{
    $action = strtolower($action);
    $icons = [
        'modified' => '<i class="fas fa-edit"></i>',
        'create'   => '<i class="fas fa-plus-circle"></i>',
        'remove'   => '<i class="fas fa-trash"></i>',
        'delete'   => '<i class="fas fa-trash"></i>',
        'restore'  => '<i class="fas fa-undo"></i>',
        'bulk_restore' => '<i class="fas fa-undo"></i>',
        'bulk_delete' => '<i class="fas fa-trash"></i>'
    ];
    return $icons[$action] ?? '<i class="fas fa-info-circle"></i>';
}

/**
 * Returns a status icon based on the log status.
 */
function getStatusIcon($status)
{
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
}

/**
 * Returns an array with formatted Details and Changes columns.
 */
function formatDetailsAndChanges($log)
{
    $action = strtolower($log['Action'] ?? '');
    $oldData = ($log['OldVal'] !== null) ? json_decode($log['OldVal'], true) : [];
    $newData = ($log['NewVal'] !== null) ? json_decode($log['NewVal'], true) : [];

    $details = htmlspecialchars($log['Details'] ?? '');
    $changes = '';

    switch ($action) {
        case 'create':
            $changes = formatNewValue($log['NewVal']);
            break;

        case 'modified':
            $changes = formatAuditDiff($log['OldVal'], $log['NewVal'], $log['Status']);
            break;

        case 'restore':
        case 'bulk_restore':
            $changes = "is_disabled 1 -> 0";
            break;

        case 'remove':
            $changes = formatNewValue($log['NewVal']);
            break;

        case 'delete':
        case 'bulk_delete':
            $changes = formatNewValue($log['OldVal']);
            break;

        default:
            // Format the old and new values
            $oldFormatted = isset($log['OldVal']) ? formatNewValue($log['OldVal']) : '';
            $newFormatted = isset($log['NewVal']) ? formatNewValue($log['NewVal']) : '';

            // Priority: NewVal if non-empty, otherwise OldVal, otherwise "No changes"
            if (!empty($newFormatted)) {
                $changes = $newFormatted;
            } elseif (!empty($oldFormatted)) {
                $changes = $oldFormatted;
            } else {
                $changes = 'No changes';
            }
            break;
    }

    return [$details, $changes];
}

/**
 * Normalizes the action for display.
 */
function getNormalizedAction($log)
{
    $action = strtolower($log['Action'] ?? '');

    // Check for restore action
    if (!is_null($log['OldVal']) && !is_null($log['NewVal'])) {
        $oldData = json_decode($log['OldVal'], true);
        $newData = json_decode($log['NewVal'], true);
        if (
            is_array($oldData) && is_array($newData) &&
            isset($oldData['is_disabled'], $newData['is_disabled']) &&
            (int)$oldData['is_disabled'] === 1 && (int)$newData['is_disabled'] === 0
        ) {
            return 'restore';
        }
    }

    return $action;
}

// Display readable action names
function getDisplayAction($action)
{
    $actionMap = [
        'bulk_restore' => 'Restore',
        'bulk_delete' => 'Delete',
    ];

    return $actionMap[$action] ?? ucfirst($action);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="preload" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css" as="style"
        onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    </noscript>
    <meta charset="UTF-8">
    <title>Department Management Audit Logs</title>

</head>

<body>

    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                    <h3 class="text-white">
                        <i class="fas fa-history me-2"></i>
                        Department Management Audit Logs
                    </h3>
                </div>

                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-shield-alt me-2"></i>
                        <?php if (!$hasAuditPermission): ?>
                            You have Department Management tracking permissions.
                        <?php else: ?>
                            You have access to Department Management audit logs.
                        <?php endif; ?>
                    </div>

                    <!-- Filter Section -->
                    <form method="GET" class="row g-3 mb-4" id="auditFilterForm" onsubmit="return false;">
                        <div class="col-md-3">
                            <label for="actionType" class="form-label">Action Type</label>
                            <select class="form-select" name="action_type" id="actionType">
                                <option value="">All</option>
                                <?php 
                                if (!empty($actionTypes)) {
                                    foreach ($actionTypes as $action) {
                                        $selected = ($_GET['action_type'] ?? '') === $action ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($action) . '" ' . $selected . '>' . 
                                             htmlspecialchars($action) . '</option>';
                                    }
                                } else {
                                    // Fallback options if database query fails
                                    $defaultActions = ['Create', 'Modified', 'Remove', 'Restore', 'Delete'];
                                    foreach ($defaultActions as $action) {
                                        $selected = ($_GET['action_type'] ?? '') === $action ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($action) . '" ' . $selected . '>' . 
                                             htmlspecialchars($action) . '</option>';
                                    }
                                    // Add debug info
                                    error_log("Using fallback action types as no values were found in database");
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="">All</option>
                                <?php 
                                if (!empty($statusTypes)) {
                                    foreach ($statusTypes as $status) {
                                        $selected = ($_GET['status'] ?? '') === $status ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($status) . '" ' . $selected . '>' . 
                                             htmlspecialchars($status) . '</option>';
                                    }
                                } else {
                                    // Fallback options if database query fails
                                    $defaultStatuses = ['Successful', 'Failed'];
                                    foreach ($defaultStatuses as $status) {
                                        $selected = ($_GET['status'] ?? '') === $status ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($status) . '" ' . $selected . '>' . 
                                             htmlspecialchars($status) . '</option>';
                                    }
                                    // Add debug info
                                    error_log("Using fallback status types as no values were found in database");
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Date Range selector -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Date Filter Type</label>
                            <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm live-filter">
                                <option value="" <?= empty($filters['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
                                <option value="month_year" <?= (($_GET['date_filter_type'] ?? '') === 'month_year') ? 'selected' : '' ?>>Month-Year Range</option>
                                <option value="year" <?= (($_GET['date_filter_type'] ?? '') === 'year') ? 'selected' : '' ?>>Year Range</option>
                                <option value="mdy" <?= (($_GET['date_filter_type'] ?? '') === 'mdy') ? 'selected' : '' ?>>Month-Date-Year Range</option>
                            </select>
                            <div id="date-filter-error" class="position-relative"></div>
                        </div>

                        <!-- MDY Range -->
                        <div class="col-12 col-md-3 date-filter date-mdy d-none">
                            <label class="form-label fw-semibold">Date From</label>
                            <input type="date" name="date_from" class="form-control shadow-sm live-filter"
                                value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                                placeholder="Start Date (YYYY-MM-DD)">
                        </div>
                        <div class="col-12 col-md-3 date-filter date-mdy d-none">
                            <label class="form-label fw-semibold">Date To</label>
                            <input type="date" name="date_to" class="form-control shadow-sm live-filter"
                                value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                placeholder="End Date (YYYY-MM-DD)">
                        </div>

                        <!-- Year Range -->
                        <div class="col-12 col-md-3 date-filter date-year d-none">
                            <label class="form-label fw-semibold">Year From</label>
                            <input type="number" name="year_from" class="form-control shadow-sm live-filter"
                                min="1900" max="2100"
                                placeholder="e.g., 2023"
                                value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">
                        </div>

                        <div class="col-12 col-md-3 date-filter date-year d-none">
                            <label class="form-label fw-semibold">Year To</label>
                            <input type="number" name="year_to" class="form-control shadow-sm live-filter"
                                min="1900" max="2100"
                                placeholder="e.g., 2025"
                                value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
                        </div>

                        <!-- Month-Year Range -->
                        <div class="col-12 col-md-3 date-filter date-month_year d-none">
                            <label class="form-label fw-semibold">From (MM-YYYY)</label>
                            <input type="month" name="month_year_from" class="form-control shadow-sm live-filter"
                                value="<?= htmlspecialchars($_GET['month_year_from'] ?? '') ?>"
                                placeholder="e.g., 2023-01">
                        </div>
                        <div class="col-12 col-md-3 date-filter date-month_year d-none">
                            <label class="form-label fw-semibold">To (MM-YYYY)</label>
                            <input type="month" name="month_year_to" class="form-control shadow-sm live-filter"
                                value="<?= htmlspecialchars($_GET['month_year_to'] ?? '') ?>"
                                placeholder="e.g., 2023-12">
                        </div>

                        <!-- Search bar -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label fw-semibold">Search</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" id="searchInput" class="form-control live-filter" placeholder="Search keyword..."
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>
                        <!-- Filter Button-->
                        <div class="col-6 col-md-2 d-grid" style="align-items: center;">
                            <button type="button" id="applyFilters" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
                        </div>
                        <!-- Clear Filter Button -->
                        <div class="col-6 col-md-2 d-grid" style="align-items: center;">
                            <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>

                <!--Table values-->
                <div class="table-responsive" id="table">
                    <table class="table table-hover">
                        <colgroup>
                            <col class="track">
                            <col class="user">
                            <col class="module">
                            <col class="action">
                            <col class="details">
                            <col class="changes">
                            <col class="status">
                            <col class="date">
                        </colgroup>
                        <thead class="table-light">
                            <tr>
                                <th class="sortable" data-column="TrackID">
                                    Track ID
                                    <?php if ($sortColumn === 'TrackID'): ?>
                                        <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="sortable" data-column="email">
                                    User
                                    <?php if ($sortColumn === 'email'): ?>
                                        <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="sortable" data-column="Module">
                                    Module
                                    <?php if ($sortColumn === 'Module'): ?>
                                        <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="sortable" data-column="Action">
                                    Action
                                    <?php if ($sortColumn === 'Action'): ?>
                                        <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th>Details</th>
                                <th>Changes</th>
                                <th class="sortable" data-column="Status">
                                    Status
                                    <?php if ($sortColumn === 'Status'): ?>
                                        <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                                <th class="sortable" data-column="Date_Time">
                                    Date & Time
                                    <?php if ($sortColumn === 'Date_Time'): ?>
                                        <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?>"></i>
                                    <?php else: ?>
                                        <i class="fas fa-sort"></i>
                                    <?php endif; ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="auditTable">
                            <?php if (!empty($auditLogs)): ?>
                                <?php foreach ($auditLogs as $log):
                                    $normalizedAction = getNormalizedAction($log);
                                    list($detailsHTML, $changesHTML) = formatDetailsAndChanges($log);
                                ?>
                                    <tr>
                                        <td data-label="Track ID">
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($log['TrackID']); ?>
                                            </span>
                                        </td>

                                        <td data-label="User">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-circle me-2"></i>
                                                <?php echo htmlspecialchars($log['email'] ?? 'N/A'); ?>
                                            </div>
                                        </td>

                                        <td data-label="Module">
                                            <?php echo !empty($log['Module']) ? htmlspecialchars(trim($log['Module'])) : '<em class="text-muted">N/A</em>'; ?>
                                        </td>

                                        <td data-label="Action">
                                            <?php
                                            $actionText = getDisplayAction($normalizedAction);
                                            echo "<span class='action-badge action-" . strtolower($normalizedAction) . "'>";
                                            echo getActionIcon($normalizedAction) . ' ' . htmlspecialchars($actionText);
                                            echo "</span>";
                                            ?>
                                        </td>

                                        <td data-label="Details" class="data-container">
                                            <?php echo nl2br($detailsHTML); ?>
                                        </td>

                                        <td data-label="Changes" class="data-container">
                                            <?php echo $changesHTML; ?>
                                        </td>

                                        <?php
                                        $statusRaw = $log['Status'] ?? '';
                                        $statusClean = strtolower(trim($statusRaw)); // Normalize for comparison
                                        $isSuccess = in_array($statusClean, ['successful', 'success']); // Accept both variants
                                        ?>
                                        <td data-label="Status">
                                            <span class="badge <?php echo $isSuccess ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo getStatusIcon($statusRaw) . ' ' . htmlspecialchars($statusRaw); ?>
                                            </span>
                                        </td>

                                        <td data-label="Date & Time">
                                            <div class="d-flex align-items-center">
                                                <i class="far fa-clock me-2"></i>
                                                <?php echo htmlspecialchars($log['Date_Time'] ?? ''); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state text-center py-4">
                                            <i class="fas fa-inbox fa-3x mb-3"></i>
                                            <h4>No Audit Logs Found</h4>
                                            <p class="text-muted">There are no audit log entries to display.</p>
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
                                    <?php $totalLogs = count($auditLogs); ?>
                                    <input type="hidden" id="total-logs" value="<?= $totalLogs ?>">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                                </div>
                            </div>
                            <div class="col-12 col-sm-auto ms-sm-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="30">30</option>
                                        <option value="50">50</option>
                                    </select>
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

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/logs.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set a flag to indicate that this page explicitly initializes pagination
            window.paginationInitialized = true;
            
            // Initialize pagination with the audit table ID
            initPagination({
                tableId: 'auditTable'
            });

            // Get form and filter elements
            const filterForm = document.getElementById('auditFilterForm');
            const actionTypeSelect = document.getElementById('actionType');
            const statusSelect = document.getElementById('status');
            const searchInput = document.getElementById('searchInput');
            const applyFiltersBtn = document.getElementById('applyFilters');
            const clearFiltersBtn = document.getElementById('clearFilters');
            const dateFilterType = document.getElementById('dateFilterType');

            // Function to submit the form
            function submitForm() {
                const formData = new FormData(filterForm);
                const queryString = new URLSearchParams(formData).toString();
                window.location.href = window.location.pathname + '?' + queryString;
            }

            // Add event listeners for dropdowns
            if (actionTypeSelect) {
                actionTypeSelect.addEventListener('change', function() {
                    submitForm();
                });
            }

            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    submitForm();
                });
            }

            // Add event listener for search input - only trigger on Enter key or Apply button
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitForm();
                    }
                });
            }

            // Add event listener for apply filters button
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    // Validate date filters before submitting
                    let isValid = true;
                    let errorMessage = '';
                    let selectedType = dateFilterType.value;
                    
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
                        // Remove any existing error messages
                        const existingError = document.querySelector('.date-filter-error');
                        if (existingError) {
                            existingError.remove();
                        }
                        
                        // Show the error message below the form
                        const errorContainer = document.createElement('div');
                        errorContainer.className = 'date-filter-error alert alert-danger alert-dismissible fade show';
                        errorContainer.style.marginTop = '20px';
                        errorContainer.style.maxWidth = '100%';
                        errorContainer.role = 'alert';
                        errorContainer.innerHTML = `
                            <strong> </strong> ${errorMessage}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        filterForm.parentNode.insertBefore(errorContainer, filterForm.nextSibling);
                        
                        // Auto hide after 3 seconds
                        setTimeout(function() {
                            if (errorContainer) {
                                errorContainer.style.transition = 'opacity 0.5s';
                                errorContainer.style.opacity = '0';
                                setTimeout(() => {
                                    errorContainer.remove();
                                }, 500);
                            }
                        }, 3000);
                        
                        return;
                    }
                    
                    // If all validations pass, submit the form
                    submitForm();
                });
            }

            // Add event listener for clear filters button
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Reset all form fields
                    filterForm.reset();
                    
                    // Reset date fields visibility
                    const allDateFilters = document.querySelectorAll('.date-filter');
                    allDateFilters.forEach(field => field.classList.add('d-none'));
                    
                    // Clear any error messages
                    const errorElement = document.querySelector('.date-filter-error');
                    if (errorElement) {
                        errorElement.remove();
                    }
                    
                    // Clear URL parameters and reload page
                    window.location.href = window.location.pathname;
                });
            }

            // Date filter type change handler
            if (dateFilterType) {
                dateFilterType.addEventListener('change', function() {
                    const allDateFilters = document.querySelectorAll('.date-filter');
                    allDateFilters.forEach(field => field.classList.add('d-none'));
                    
                    // Clear any error messages when changing filter type
                    const errorElement = document.querySelector('.date-filter-error');
                    if (errorElement) {
                        errorElement.remove();
                    }
                    
                    if (this.value) {
                        const selected = document.querySelectorAll('.date-' + this.value);
                        selected.forEach(field => field.classList.remove('d-none'));
                    }
                });

                // Initialize date fields visibility
                const allDateFilters = document.querySelectorAll('.date-filter');
                allDateFilters.forEach(field => field.classList.add('d-none'));
                if (dateFilterType.value) {
                    const selected = document.querySelectorAll('.date-' + dateFilterType.value);
                    selected.forEach(field => field.classList.remove('d-none'));
                }
            }

            // Add event listeners for date inputs - only trigger on Apply button
            document.querySelectorAll('.date-filter input').forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        submitForm();
                    }
                });
            });
        });

        // Add sorting functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sortableHeaders = document.querySelectorAll('.sortable');
            sortableHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.dataset.column;
                    const currentOrder = '<?= $sortOrder ?>';
                    const currentColumn = '<?= $sortColumn ?>';
                    
                    // Determine new sort order
                    let newOrder = 'ASC';
                    if (column === currentColumn) {
                        newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                    }
                    
                    // Get current URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    
                    // Update sort parameters
                    urlParams.set('sort', column);
                    urlParams.set('order', newOrder);
                    
                    // Redirect to new URL with sort parameters
                    window.location.href = window.location.pathname + '?' + urlParams.toString();
                });
            });
        });
    </script>
</body>

</html>