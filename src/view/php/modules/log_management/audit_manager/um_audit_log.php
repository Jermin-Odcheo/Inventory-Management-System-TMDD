<?php
/**
 * User Management Audit Log Module
 *
 * This file provides comprehensive audit logging functionality for user management activities. It tracks and records all changes made to user accounts, including creation, modification, and deletion events. The module ensures detailed logging of user actions, timestamps, and relevant data changes for security and accountability purposes.
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

// Add audit-log class to body
echo '<script>document.body.classList.add("audit-log");</script>';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

/**
 * Initialize RBAC and Check Permissions
 *
 * Initializes the Role-Based Access Control (RBAC) system and checks if the user has the necessary permissions
 * to view audit logs for user management.
 *
 * @return void
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check for required privilege
$hasAuditPermission = $rbac->hasPrivilege('Audit', 'Track');
$hasUMPermission = $rbac->hasPrivilege('User Management', 'Track');

/**
 * Initialize Date Filter Type
 *
 * Sets the date filter type based on user input for filtering audit logs.
 *
 * @return void
 */
$dateFilterType = $_GET['date_filter_type'] ?? '';

/**
 * Initialize Pagination Parameters
 * 
 * Sets up pagination parameters based on user input or default values.
 * 
 * @return void
 */
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$recordsPerPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;

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
if (!$hasAuditPermission && !$hasUMPermission) {
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
 * Fetch Audit Logs
 *
 * Retrieves all audit logs related to user management from the database.
 *
 * @return void
 */
$query = "SELECT audit_log.*, users.email AS email 
          FROM audit_log 
          LEFT JOIN users ON audit_log.UserID = users.id
          WHERE audit_log.Module = 'User Management'
          ORDER BY audit_log.TrackID DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Fetch Unique Values for Dropdowns
 *
 * Retrieves unique action and status types from the audit log for filtering purposes.
 *
 * @return void
 */
$actionTypesQuery = "SELECT DISTINCT Action FROM audit_log WHERE Module = 'User Management' ORDER BY Action";
$statusTypesQuery = "SELECT DISTINCT Status FROM audit_log WHERE Module = 'User Management' ORDER BY Status";

$actionTypesStmt = $pdo->query($actionTypesQuery);
$statusTypesStmt = $pdo->query($statusTypesQuery);

$actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);
$statusTypes = $statusTypesStmt->fetchAll(PDO::FETCH_COLUMN);

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

        // Special handling for departments
        if ($key === 'departments' && !empty($value)) {
            // Simple format matching the modified action display
            $deptHtml = "<strong>{$friendlyKey}:</strong> ";
            $deptHtml .= "<span class='text-primary'>" . htmlspecialchars($value) . "</span>";

            $html .= '<li class="list-group-item">' . $deptHtml . '</li>';
        } else {
            $displayValue = is_null($value) ? '<em>null</em>' : htmlspecialchars($value);
            $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>' . $friendlyKey . ':</strong> <span>' . $displayValue . '</span>
                      </li>';
        }
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
        if (strtolower($key) === 'is_deleted') {
            continue;
        }
        if (strtolower($key) === 'password') {
            if (isset($oldData[$key], $newData[$key]) && $oldData[$key] !== $newData[$key]) {
                $descriptions[] = "The password has been changed.";
            }
            continue;
        }
        $oldVal = $oldData[$key] ?? '';
        $newVal = $newData[$key] ?? '';
        if ($oldVal !== $newVal) {
            $friendlyField = ucwords(str_replace('_', ' ', $key));

            // Special handling for departments to display as lists
            if ($key === 'departments') {
                // Split the department strings by commas
                $oldDepts = !empty($oldVal) ? explode(', ', $oldVal) : [];
                $newDepts = !empty($newVal) ? explode(', ', $newVal) : [];

                // Find added and removed departments
                $added = array_diff($newDepts, $oldDepts);
                $removed = array_diff($oldDepts, $newDepts);

                // Simplest possible format - just a direct text comparison
                $html = "<strong>Departments:</strong> ";
                $html .= "<span class='text-secondary'>" . htmlspecialchars($oldVal) . "</span>";
                $html .= " <i class='fas fa-arrow-right'></i> ";
                $html .= "<span class='text-primary'>" . htmlspecialchars($newVal) . "</span>";

                $descriptions[] = $html;
            } else {
                $descriptions[] = "The {$friendlyField} was changed from '<em>{$oldVal}</em>' to '<strong>{$newVal}</strong>'.";
            }
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
 * Get Action Icon
 *
 * Returns an icon based on the given action type.
 *
 * @param string $action The action type.
 * @return string The HTML icon string.
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
 * Get Status Icon
 *
 * Returns a status icon based on the log status.
 *
 * @param string $status The status of the log entry.
 * @return string The HTML icon string.
 */
function getStatusIcon($status)
{
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
}

/**
 * Process Status Message
 *
 * Processes error messages when the log status is failed.
 *
 * @param string $defaultMessage The default message to display.
 * @param array $log The log entry data.
 * @param callable $changeCallback Callback to format changes if status is not failed.
 * @return array An array with formatted details and changes.
 */
function processStatusMessage($defaultMessage, $log, $changeCallback)
{
    $isFailed = (strtolower($log['Status'] ?? '') === 'failed');
    $customMessage = trim($log['Details'] ?? '');
    if ($isFailed && !empty($customMessage) && $customMessage !== $defaultMessage) {
        $details = $defaultMessage . "<hr><br><strong style='color:red;font-style:italic;'>Error:</strong> "
            . '<span style="color:red;font-style:italic;">' . nl2br(htmlspecialchars($customMessage)) . '</span>';
        return [$details, 'N/A'];
    }
    return [$defaultMessage, $changeCallback()];
}

/**
 * Format Details and Changes
 *
 * Formats the details and changes columns based on the action type in the log entry.
 *
 * @param array $log The log entry data.
 * @return array An array containing formatted details and changes HTML.
 */
function formatDetailsAndChanges($log)
{
    $action = strtolower($log['Action'] ?? '');
    $oldData = ($log['OldVal'] !== null) ? json_decode($log['OldVal'], true) : [];
    $newData = ($log['NewVal'] !== null) ? json_decode($log['NewVal'], true) : [];

    $userEmail = $log['email'] ?? ($newData['email'] ?? 'User');
    $targetEntityName = $newData['username'] ?? $oldData['username'] ?? 'Unknown';
    $targetName = $userEmail;
    if ($action === 'remove' && isset($newData['first_name'], $newData['last_name'])) {
        $targetName = $newData['first_name'] . ' ' . $newData['last_name'];
    }

    $details = '';
    $changes = '';

    switch ($action) {
        case 'create':
            $defaultMessage = htmlspecialchars("$targetEntityName has been created");
            list($details, $changes) = processStatusMessage($defaultMessage, $log, function () use ($log) {
                return formatNewValue($log['NewVal']);
            });
            break;

        case 'modified':
            // Check if this is a user role modification
            if (isset($oldData['role']) && isset($newData['role'])) {
                $oldRole = $oldData['role'];
                $newRole = $newData['role'];
                $details = htmlspecialchars("Modified role for $targetEntityName");
                $changes = htmlspecialchars("$oldRole -> $newRole");
            } else {
                $changedFields = getChangedFieldNames($oldData, $newData);
                $defaultMessage = !empty($changedFields)
                    ? "Modified Fields: " . htmlspecialchars(implode(', ', $changedFields))
                    : "Modified Fields: None";
                list($details, $changes) = processStatusMessage($defaultMessage, $log, function () use ($log, $oldData, $newData) {
                    // Special handling for departments - display in table format
                    if (isset($oldData['departments']) || isset($newData['departments'])) {
                        $oldDeptStr = $oldData['departments'] ?? '';
                        $newDeptStr = $newData['departments'] ?? '';

                        // Simplest possible format - just a direct text comparison
                        $html = "<strong>Departments:</strong> ";
                        $html .= "<span class='text-secondary'>" . htmlspecialchars($oldDeptStr) . "</span>";
                        $html .= " <i class='fas fa-arrow-right'></i> ";
                        $html .= "<span class='text-primary'>" . htmlspecialchars($newDeptStr) . "</span>";

                        return $html;
                    }

                    // For other fields, show old -> new
                    $changesText = [];
                    foreach ($oldData as $key => $value) {
                        if (isset($newData[$key]) && $value !== $newData[$key]) {
                            if ($key !== 'departments') { // Skip departments as they're handled above
                                $changesText[] = "$key: $value -> " . $newData[$key];
                            }
                        }
                    }
                    return !empty($changesText) ? implode("<br>", $changesText) : formatNewValue($log['NewVal']);
                });
            }
            break;

        case 'restored':
            $defaultMessage = htmlspecialchars("$targetEntityName has been restored");
            list($details, $changes) = processStatusMessage(
                $defaultMessage,
                $log,
                function () use ($log) {
                    // 1) decode
                    $old = json_decode($log['OldVal'], true);
                    // 2) remove the is_disabled flag
                    unset($old['is_disabled']);
                    unset($old['is_disabled']);
                    // 3) re-encode & hand it off to your existing formatter
                    return formatNewValue(json_encode($old));
                }
            );
            break;

        case 'remove':
            // First check for our new format with roles array
            if (isset($oldData['roles']) && is_array($oldData['roles'])) {
                // Use the details directly from the database
                $details = htmlspecialchars($log['Details'] ?? '');

                // Format the changes as a clean list
                $changes = '<div class="role-removal-info">';
                $changes .= '<div><strong>Username:</strong> ' . htmlspecialchars($oldData['username'] ?? 'Unknown') . '</div>';
                $changes .= '<div><strong>Department:</strong> ' . htmlspecialchars($oldData['department'] ?? 'Unknown Department') . '</div>';
                $changes .= '<div><strong>Removed Roles:</strong> ' . htmlspecialchars(implode(", ", $oldData['roles'])) . '</div>';
                $changes .= '</div>';
            }
            // Check for older format with single role
            else if (isset($oldData['role'])) {
                $role = $oldData['role'];
                $details = htmlspecialchars("The role '$role' for $targetEntityName has been removed");
                $changes = formatNewValue($log['OldVal']);
            }
            // Default case for user removal
            else {
                $defaultMessage = htmlspecialchars("$targetEntityName has been removed");
                list($details, $changes) = processStatusMessage(
                    $defaultMessage,
                    $log,
                    function () use ($log) {
                        // 1) decode
                        $old = json_decode($log['OldVal'], true);
                        // 2) remove the is_disabled flag and status
                        unset($old['is_disabled']);
                        unset($old['status']);
                        // 3) re-encode & hand it off to your existing formatter
                        return formatNewValue(json_encode($old));
                    }
                );
            }
            break;

        case 'delete':
            $details = htmlspecialchars("$targetEntityName has been deleted from the database");
            $changes = formatNewValue($log['OldVal']);
            break;

        case 'add':
            // Check if this is a user role addition
            if (isset($newData['role'])) {
                $role = $newData['role'];

                // Check if this is actually a role modification or removal
                // If we have old role data, treat it as a modification
                if (isset($oldData['role']) && $oldData['role'] !== $role) {
                    $oldRole = $oldData['role'];
                    $details = htmlspecialchars("Modified role for: $targetEntityName: $oldRole -> $role");
                    $changes = htmlspecialchars("$oldRole -> $role");
                }
                // Otherwise it's a standard role addition
                else {
                    $details = htmlspecialchars("The role '$role' has been added to $targetEntityName");
                    $changes = formatNewValue($log['NewVal']);
                }
            } else {
                $details = htmlspecialchars($log['Details'] ?? '');
                $changes = formatNewValue($log['NewVal']);
            }
            break;

        default:
            // Use htmlspecialchars if you want to escape HTML entities in the details
            $details = htmlspecialchars($log['Details'] ?? '');

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
 * Get Changed Field Names
 *
 * Returns a list of field names that have changed between old and new data.
 *
 * @param array $oldData The old data array.
 * @param array $newData The new data array.
 * @return array List of changed field names.
 */
function getChangedFieldNames(array $oldData, array $newData)
{
    $changed = [];
    $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    foreach ($allKeys as $key) {
        if (($oldData[$key] ?? null) !== ($newData[$key] ?? null)) {
            $changed[] = ucwords(str_replace('_', ' ', $key));
        }
    }
    return $changed;
}

/**
 * Get Normalized Action
 *
 * Normalizes the action for display, adjusting based on JSON values for specific cases like restore or role modifications.
 *
 * @param array $log The log entry data.
 * @return string The normalized action type.
 */
function getNormalizedAction($log)
{
    $action = strtolower($log['Action'] ?? '');

    // For user role removals, always ensure action is "remove"
    if ($action === 'remove' && !is_null($log['OldVal'])) {
        $oldData = json_decode($log['OldVal'], true);
        if (is_array($oldData) && (isset($oldData['role']) || isset($oldData['roles']))) {
            return 'remove';
        }
    }

    // Check if this is actually a role modification labeled as "add"
    if ($action === 'add' && !is_null($log['OldVal']) && !is_null($log['NewVal'])) {
        $oldData = json_decode($log['OldVal'], true);
        $newData = json_decode($log['NewVal'], true);

        if (
            is_array($oldData) && is_array($newData) &&
            isset($oldData['role']) && isset($newData['role']) &&
            $oldData['role'] !== $newData['role']
        ) {
            return 'modified';
        }
    }

    // Check for restore action (existing logic)
    if (!is_null($log['OldVal']) && !is_null($log['NewVal'])) {
        $oldData = json_decode($log['OldVal'], true);
        $newData = json_decode($log['NewVal'], true);
        if (
            is_array($oldData) && is_array($newData) &&
            isset($oldData['is_deleted'], $newData['is_deleted']) &&
            (int)$oldData['is_deleted'] === 1 && (int)$newData['is_deleted'] === 0
        ) {
            return 'restored';
        }
    }

    return $action;
}

/**
 * Build Query Filters
 *
 * Constructs the WHERE clause and parameters for filtering audit logs based on user input.
 *
 * @return void
 */
//Filter code
$where = "WHERE audit_log.Module = 'User Management'";
$params = [];

// Basic filters without date filtering
if (!empty($_GET['action_type'])) {
    $where .= " AND audit_log.Action = :action_type";
    $params[':action_type'] = $_GET['action_type'];
}

if (!empty($_GET['status'])) {
    $where .= " AND audit_log.Status = :status";
    $params[':status'] = $_GET['status'];
}

if (!empty($_GET['search'])) {
    $where .= " AND (users.email LIKE :search_email OR audit_log.NewVal LIKE :search_newval OR audit_log.OldVal LIKE :search_oldval)";
    $searchParam = '%' . $_GET['search'] . '%';
    $params[':search_email'] = $searchParam;
    $params[':search_newval'] = $searchParam;
    $params[':search_oldval'] = $searchParam;
}

// Instead of wrapping audit_log.date_time with DATE() use full datetime comparison for MDY filter
if ($dateFilterType === 'mdy') {
    if (!empty($_GET['date_from'])) {
        $where .= " AND DATE(audit_log.date_time) >= :date_from";
        $params[':date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $where .= " AND DATE(audit_log.date_time) <= :date_to";
        $params[':date_to'] = $_GET['date_to'];
    }    
}

// For month-year filter, use STR_TO_DATE to compare datetimes instead of DATE_FORMAT
if ($dateFilterType === 'month_year') {
    if (!empty($_GET['month_year_from'])) {
        $where .= " AND audit_log.date_time >= STR_TO_DATE(:month_year_from, '%Y-%m')";
        $params[':month_year_from'] = $_GET['month_year_from'];
    }
    if (!empty($_GET['month_year_to'])) {
        $where .= " AND audit_log.date_time < DATE_ADD(STR_TO_DATE(:month_year_to, '%Y-%m'), INTERVAL 1 MONTH)";
        $params[':month_year_to'] = $_GET['month_year_to'];
    }
}

// Year filter
if ($dateFilterType === 'year') {
    if (!empty($_GET['year_from'])) {
        $where .= " AND YEAR(audit_log.date_time) >= :year_from";
        $params[':year_from'] = $_GET['year_from'];
    }
    if (!empty($_GET['year_to'])) {
        $where .= " AND YEAR(audit_log.date_time) <= :year_to";
        $params[':year_to'] = $_GET['year_to'];
    }
}

/**
 * Count Total Records
 * 
 * Count the total number of filtered records for pagination.
 * 
 * @return void
 */
$countQuery = "
    SELECT COUNT(*) as total
    FROM audit_log 
    LEFT JOIN users ON audit_log.UserID = users.id 
    $where
";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate total pages
$totalPages = ceil($totalRecords / $recordsPerPage);
$currentPage = min($currentPage, max(1, $totalPages));

// Calculate OFFSET for SQL query
$offset = ($currentPage - 1) * $recordsPerPage;

/**
 * Fetch Filtered Audit Logs
 *
 * Retrieves audit logs based on the constructed filters and sorting parameters.
 *
 * @return void
 */
// Modify the query to include sorting, pagination with LIMIT and OFFSET
$query = "
  SELECT audit_log.*, users.email AS email 
  FROM audit_log 
  LEFT JOIN users ON audit_log.UserID = users.id 
  $where 
  ORDER BY $sortColumn $sortOrder
  LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

// Bind all other parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>User Management Audit Logs</title>
</head>

<body>


    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                    <h3 class="text-white">
                        <i class="fas fa-history me-2"></i>
                        User Management Audit Logs
                    </h3>
                </div>

                <div class="card-body">
                    <!-- Permission Info Banner -->
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-shield-alt me-2"></i>
                        <?php if (!$hasAuditPermission): ?>
                            You have User Management tracking permissions.
                        <?php else: ?>
                            You have access to User Management audit logs.
                        <?php endif; ?>
                    </div>

                    <!-- Filter Section -->
                    <form method="GET" class="row g-3 mb-4" id="auditFilterForm">
                        <div class="col-md-3">
                            <label for="actionType" class="form-label">Action Type</label>
                            <select class="form-select" name="action_type" id="actionType">
                                <option value="">All</option>
                                <?php foreach ($actionTypes as $action): ?>
                                    <option value="<?= htmlspecialchars($action) ?>" <?= ($_GET['action_type'] ?? '') === $action ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($action) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="">All</option>
                                <?php foreach ($statusTypes as $status): ?>
                                    <option value="<?= htmlspecialchars($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Range selector -->
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Date Filter Type</label>
                            <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm">
                                <option value="" <?= empty($filters['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
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
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" id="searchInput" class="form-control" placeholder="Search keyword..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-6 col-md-2 d-grid">
                            <button type="submit" id="applyFilters" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
                        </div>

                        <div class="col-6 col-md-2 d-grid">
                            <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm" style="align-items: center;">
                                <i class="bi bi-x-circle"></i> Clear
                            </button>
                        </div>
                    </form>

                    <!-- Table container -->
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
                                            <!-- TRACK ID -->
                                            <td data-label="Track ID">
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($log['TrackID']); ?>
                                                </span>
                                            </td>

                                            <!-- USER -->
                                            <td data-label="User">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <?php echo htmlspecialchars($log['email'] ?? 'N/A'); ?>
                                                </div>
                                            </td>

                                            <!-- MODULE -->
                                            <td data-label="Module">
                                                <?php echo !empty($log['Module']) ? htmlspecialchars(trim($log['Module'])) : '<em class="text-muted">N/A</em>'; ?>
                                            </td>

                                            <!-- ACTION -->
                                            <td data-label="Action">
                                                <?php
                                                $actionText = ucfirst($normalizedAction);
                                                echo "<span class='action-badge action-" . strtolower($actionText) . "'>";
                                                echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                                echo "</span>";
                                                ?>
                                            </td>

                                            <!-- DETAILS -->
                                            <td data-label="Details" class="data-container">
                                                <?php echo nl2br($detailsHTML); ?>
                                            </td>

                                            <!-- CHANGES -->
                                            <td data-label="Changes" class="data-container">
                                                <?php echo nl2br($changesHTML); ?>
                                            </td>

                                            <?php
                                            $statusRaw = $log['Status'] ?? '';
                                            $statusClean = strtolower(trim($statusRaw)); // Normalize for comparison
                                            $isSuccess = in_array($statusClean, ['successful', 'success']); // Accept both variants
                                            
                                            // Normalize status display to "Successful"
                                            $displayStatus = $isSuccess ? "Successful" : $statusRaw;
                                            ?>
                                            <!-- STATUS -->
                                            <td data-label="Status">
                                                <span class="badge <?php echo $isSuccess ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php
                                                    echo getStatusIcon($statusRaw) . ' ' . htmlspecialchars($displayStatus);
                                                    
                                                    // DEBUG: Print raw status for unknown values
                                                    if (!$isSuccess) {
                                                        echo "<!-- DEBUG: Raw Status = '" . addslashes($statusRaw) . "' -->";
                                                    }
                                                    ?>
                                                </span>
                                            </td>

                                            <!-- DATE & TIME -->
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

                        <!-- Pagination Controls -->
                        <div class="container-fluid">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-sm-auto">
                                    <div class="text-muted">
                                        <?php 
                                        $startRecord = min($totalRecords, ($currentPage - 1) * $recordsPerPage + 1);
                                        $endRecord = min($totalRecords, $currentPage * $recordsPerPage);
                                        ?>
                                        Showing <span id="startRecord"><?= $startRecord ?></span> to <span id="endRecord"><?= $endRecord ?></span> of <span id="totalRows"><?= $totalRecords ?></span> entries
                                    </div>
                                </div>
                                <div class="col-12 col-sm-auto ms-sm-auto">
                                    <div class="d-flex align-items-center gap-2">
                                        <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                            <option value="10" <?= $recordsPerPage == 10 ? 'selected' : '' ?>>10</option>
                                            <option value="20" <?= $recordsPerPage == 20 ? 'selected' : '' ?>>20</option>
                                            <option value="30" <?= $recordsPerPage == 30 ? 'selected' : '' ?>>30</option>
                                            <option value="50" <?= $recordsPerPage == 50 ? 'selected' : '' ?>>50</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <!-- Server-side pagination controls -->
                                    <ul class="pagination justify-content-center">
                                        <?php if ($totalPages > 1): ?>
                                            <!-- First Page -->
                                            <li class="page-item <?= ($currentPage == 1) ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPageUrl(1) ?>" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <!-- Previous Page -->
                                            <li class="page-item <?= ($currentPage == 1) ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPageUrl(max(1, $currentPage - 1)) ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <!-- Page Numbers -->
                                            <?php
                                            $startPage = max(1, min($currentPage - 2, $totalPages - 4));
                                            $endPage = min($totalPages, max(5, $currentPage + 2));
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= buildPageUrl($i) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <!-- Next Page -->
                                            <li class="page-item <?= ($currentPage == $totalPages) ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPageUrl(min($totalPages, $currentPage + 1)) ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            
                                            <!-- Last Page -->
                                            <li class="page-item <?= ($currentPage == $totalPages) ? 'disabled' : '' ?>">
                                                <a class="page-link" href="<?= buildPageUrl($totalPages) ?>" aria-label="Last">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div> <!-- /.Pagination -->
                    </div><!-- /.table-responsive -->
                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div><!-- /.container-fluid -->
    </div><!-- /.main-content -->

    <?php
    /**
     * Build Page URL
     * 
     * Helper function to build pagination URLs while preserving all existing query parameters.
     * 
     * @param int $page The page number to link to
     * @return string The URL with all parameters
     */
    function buildPageUrl($page) {
        $params = $_GET;
        $params['page'] = $page;
        
        if (isset($_GET['per_page'])) {
            $params['per_page'] = $_GET['per_page'];
        }
        
        return '?' . http_build_query($params);
    }
    ?>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/logs.js" defer></script>
    <script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const filterType = document.getElementById('dateFilterType');
    const allDateFilters = document.querySelectorAll('.date-filter');
    const form = document.getElementById('auditFilterForm');
    const clearButton = document.getElementById('clearFilters');
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');

    function updateDateFields() {
        allDateFilters.forEach(field => field.classList.add('d-none'));
        if (!filterType.value) return;
        document.querySelectorAll('.date-' + filterType.value).forEach(field => field.classList.remove('d-none'));
    }

    filterType.addEventListener('change', updateDateFields);
    updateDateFields();

    // Handle rows per page change
    rowsPerPageSelect.addEventListener('change', function() {
        const perPage = this.value;
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('per_page', perPage);
        urlParams.set('page', 1); // Reset to first page when changing records per page
        window.location.href = window.location.pathname + '?' + urlParams.toString();
    });

    // Date filter validation
    function validateDateRange(fromValue, toValue, format) {
        if (!fromValue || !toValue) return true; // If either field is empty, don't validate

        let fromDate, toDate;

        switch (format) {
            case 'mdy':
                fromDate = new Date(fromValue);
                toDate = new Date(toValue);
                break;
            case 'month_year':
                fromDate = new Date(fromValue + '-01'); // Add day for valid date
                toDate = new Date(toValue + '-01');
                break;
            case 'year':
                fromDate = new Date(fromValue, 0, 1); // Jan 1st of the year
                toDate = new Date(toValue, 0, 1);
                break;
            default:
                return true;
        }

        return fromDate <= toDate;
    }

    // Override any existing form submission handlers to prioritize validation
    if (form) {
        // Remove any existing submit handlers to avoid conflicts
        form.removeEventListener('submit', form.onsubmit, true);
        form.onsubmit = null;

        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default submission initially
            e.stopImmediatePropagation(); // Stop any other handlers from running

            const dateFilterType = filterType.value;
            let isValid = true;
            let errorMessage = '';

            if (dateFilterType === 'mdy') {
                const dateFrom = document.querySelector('input[name="date_from"]').value;
                const dateTo = document.querySelector('input[name="date_to"]').value;

                if (!validateDateRange(dateFrom, dateTo, 'mdy')) {
                    isValid = false;
                    errorMessage = '"Date From" cannot be greater than "Date To"';
                }
            } else if (dateFilterType === 'month_year') {
                const monthYearFrom = document.querySelector('input[name="month_year_from"]').value;
                const monthYearTo = document.querySelector('input[name="month_year_to"]').value;

                if (!validateDateRange(monthYearFrom, monthYearTo, 'month_year')) {
                    isValid = false;
                    errorMessage = '"From (YYYY-MM)" cannot be greater than "To (YYYY-MM)"';
                }
            } else if (dateFilterType === 'year') {
                const yearFrom = document.querySelector('input[name="year_from"]').value;
                const yearTo = document.querySelector('input[name="year_to"]').value;

                if (yearFrom && yearTo && parseInt(yearFrom) > parseInt(yearTo)) {
                    isValid = false;
                    errorMessage = '"Year From" cannot be greater than "Year To"';
                }
            }

            if (!isValid) {
                // Remove any existing error messages
                const existingError = document.getElementById('filterError');
                if (existingError) {
                    existingError.remove();
                }

                // 1) pick your filter-row container
                const filterRow = document.querySelector('.col-6.col-md.2.d-grid');

                // 2) build a "block" error div (no absolute positioning needed)
                const errorDiv = document.createElement('div');
                errorDiv.id = 'filterError';
                errorDiv.className = 'validation-tooltip mt-2';  // mt-2 gives a little gap
                Object.assign(errorDiv.style, {
                    display: 'inline-block',
                    backgroundColor: '#d9534f',
                    color: 'white',
                    padding: '6px 10px',
                    borderRadius: '4px',
                    fontSize: '0.85em',
                    whiteSpace: 'nowrap',
                    boxShadow: '0 2px 5px rgba(0,0,0,0.2)',
                    zIndex: 1000
                });
                errorDiv.textContent = errorMessage;

                // 3) insert it *after* the filter row, so it sits right below the date filters
                filterRow.insertAdjacentElement('afterend', errorDiv);

                // optional: scroll into view
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                // auto-dismiss
                setTimeout(() => {
                    const fadeOutError = document.getElementById('filterError');
                    if (fadeOutError) {
                        // Simple fade out effect
                        let opacity = 1;
                        const fadeInterval = setInterval(() => {
                            if (opacity <= 0.1) {
                                clearInterval(fadeInterval);
                                fadeOutError.remove();
                            }
                            opacity -= 0.1;
                            fadeOutError.style.opacity = opacity;
                        }, 50);
                    }
                }, 3000);

                return false; // Explicitly prevent form submission
            }

            // Remove any existing error message if validation passes
            const existingError = document.getElementById('filterError');
            if (existingError) {
                existingError.remove();
            }
            
            // If validation passes, manually submit the form
            this.submit();
        }, true); // Use capture phase to ensure this handler runs first
    }

    clearButton.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default button behavior
        
        // Reset all form fields
        form.reset();
        
        // Clear any hidden fields
        const hiddenFields = form.querySelectorAll('input[type="hidden"]');
        hiddenFields.forEach(field => field.value = '');
        
        // Reset date filter visibility
        updateDateFields();
        
        // Clear URL parameters and reload the page
        window.location.href = window.location.pathname;
    });

    // Add sorting functionality
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

<style>
.sortable {
    cursor: pointer;
    position: relative;
    padding-right: 20px !important;
}

.sortable:hover {
    background-color: #f8f9fa;
}

.sortable i {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

.sortable:hover i {
    color: #0d6efd;
}

/* Additional pagination styles */
.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.pagination .page-link {
    color: #0d6efd;
}

.pagination .page-item.disabled .page-link {
    color: #6c757d;
}
</style>

</body>

</html>