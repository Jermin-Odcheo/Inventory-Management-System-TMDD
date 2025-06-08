<?php
/**
 * @file et_audit_log.php
 * @brief handles the display of audit logs for equipment tracking activities
 *
 * This script handles the display of audit logs for equipment tracking activities. It checks user permissions,
 * fetches and filters audit log data based on various criteria, and formats the data for presentation in a user interface.
 */
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';
include '../../../general/sidebar.php';

// Add et-audit-log class to body
echo '<script>document.body.classList.add("et-audit-log");</script>';

//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php"); // Redirect to login page
    exit();
}

// Initialize RBAC and check permissions
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check for required privilege
$hasAuditPermission = $rbac->hasPrivilege('Audit', 'Track');
$hasETPermission = $rbac->hasPrivilege('Equipment Transactions', 'Track');

// If user doesn't have permission, show an inline "no permission" page
if (!$hasAuditPermission && !$hasETPermission) {
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

// Fetch all audit logs (including permanent deletes)
$query = "
 SELECT
    audit_log.TrackID,
    audit_log.UserID,
    audit_log.EntityID,
    audit_log.Module,
    audit_log.Action,
    audit_log.Details,
    audit_log.OldVal,
    audit_log.NewVal,
    UPPER(audit_log.Status) AS status,
    audit_log.Date_Time,
    users.email AS email
  FROM audit_log
    LEFT JOIN users
      ON audit_log.UserID = users.id
    WHERE audit_log.Module IN (
      'Purchase Order',
      'Charge Invoice',
      'Receiving Report'
    )
    ORDER BY
      audit_log.TrackID DESC
";

// Add debugging for the query
// echo "<pre style='font-size:10px;'>Query: " . htmlspecialchars($query) . "</pre>";

$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug the first few records to see what's in them
// if (!empty($auditLogs)) {
//     echo "<pre style='font-size:10px;'>First record: ";
//     print_r($auditLogs[0]);
//     echo "</pre>";
// }

/**
 * Format JSON Data
 *
 * Formats a JSON string with line breaks for display purposes.
 *
 * @param string|null $jsonStr The JSON string to format.
 * @return string The formatted string with HTML line breaks.
 */
function formatJsonData($jsonStr)
{
    if (!$jsonStr) {
        return '<em class="text-muted">N/A</em>';
    }
    return nl2br(htmlspecialchars($jsonStr));
}

/**
 * Format New Value
 *
 * Formats a JSON string into a visually appealing HTML list.
 *
 * @param string|null $jsonStr The JSON string to format.
 * @return string The formatted HTML list.
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
        // Convert null to a display string
        $displayValue = is_null($value) ? '<em>N/A</em>' : htmlspecialchars($value);
        $friendlyKey = ucwords(str_replace('_', ' ', $key));
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
 * Compares old and new JSON data for modifications, with special handling for certain fields.
 *
 * @param string|null $oldJson The old JSON string.
 * @param string|null $newJson The new JSON string.
 * @return string The formatted diff as HTML.
 */
function formatAuditDiff($oldJson, $newJson)
{
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
        $lcKey = strtolower($key);

        // Exclude is_deleted differences entirely.
        if ($lcKey === 'is_deleted') {
            continue;
        }

        // Handle password changes
        if ($lcKey === 'password') {
            if (isset($oldData[$key], $newData[$key]) && $oldData[$key] !== $newData[$key]) {
                $descriptions[] = "The password has been changed.";
            }
            continue;
        }

        // Generic handling for other fields
        $oldVal = isset($oldData[$key]) ? $oldData[$key] : '';
        $newVal = isset($newData[$key]) ? $newData[$key] : '';
        if ($oldVal !== $newVal) {
            $friendlyField = ucwords(str_replace('_', ' ', $key));
            $descriptions[] = "The {$friendlyField} was changed from '<em>{$oldVal}</em>' to '<strong>{$newVal}</strong>'.";
        }
    }

    if (empty($descriptions)) {
        return '<em>No changes detected.</em>';
    }

    $html = '<ul class="list-unstyled mb-0">';
    $count = count($descriptions);
    $index = 0;
    foreach ($descriptions as $desc) {
        $html .= "<li>{$desc}";
        $index++;
        if ($index < $count) {
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
 * Returns an HTML string with an icon based on the action type.
 *
 * @param string $action The action type.
 * @return string The HTML string with the icon and action text.
 */
function getActionIcon($action)
{
    $action = strtolower($action);

    if ($action === 'modified') {
        return '<span class="action-badge action-modified"><i class="fas fa-pen me-1"></i> Modified</span>';
    } elseif ($action === 'create' || $action === 'created') {
        return '<span class="action-badge action-create"><i class="fas fa-plus-circle me-1"></i> Create</span>';
    } elseif ($action === 'remove' || $action === 'removed') {
        return '<span class="action-badge action-delete"><i class="fas fa-trash-alt me-1"></i> Removed</span>';
    } elseif ($action === 'delete' || $action === 'permanent delete') {
        return '<span class="action-badge action-delete"><i class="fas fa-trash-alt me-1"></i> Deleted</span>';
    } elseif ($action === 'restored' || $action === 'restore') {
        return '<span class="action-badge action-restored"><i class="fas fa-trash-restore me-1"></i> Restored</span>';
    } else {
        return '<span class="action-badge"><i class="fas fa-info-circle me-1"></i> ' . ucfirst($action) . '</span>';
    }
}

/**
 * Get Status Icon
 *
 * Returns an HTML string with an icon based on the status.
 *
 * @param string $status The status of the log entry.
 * @return string The HTML string with the icon.
 */
function getStatusIcon($status)
{
    $statusLower = strtolower(trim($status));
    return (in_array($statusLower, ['successful', 'success']))
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
    $statusLower = strtolower(trim($log['Status'] ?? ''));
    $isFailed = !in_array($statusLower, ['successful', 'success']);
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
    // Normalize action to lowercase
    $action = strtolower($log['Action'] ?? '');
    $module = $log['Module'] ?? '';
    // Parse JSON fields for old/new data with null checks
    $oldData = !is_null($log['OldVal']) ? json_decode($log['OldVal'], true) : [];
    $newData = !is_null($log['NewVal']) ? json_decode($log['NewVal'], true) : [];

    // Prepare default strings
    $details = '';
    $changes = '';

    //Target Entity Set to PO Number
    $targetEntityName = 'Unknown';

    // Set the correct entity name field based on module
    if ($module === 'Purchase Order') {
        $targetEntityName = $oldData['po_no'] ?? $newData['po_no'] ?? 'Unknown';
    } else if ($module === 'Charge Invoice') {
        $targetEntityName = $oldData['invoice_no'] ?? $newData['invoice_no'] ?? 'Unknown';
    } else if ($module === 'Receiving Report') {
        $targetEntityName = $oldData['rr_no'] ?? $newData['rr_no'] ?? 'Unknown';
    }

    // List of fields that should never be shown in diff
    $systemFields = ['date_created', 'date_acquired', 'date_modified', 'is_disabled'];

    // Filter out system fields from both datasets before comparison
    $filteredOldData = array_diff_key($oldData, array_flip($systemFields));
    $filteredNewData = array_diff_key($newData, array_flip($systemFields));

    // --- BEGIN: Custom Add Action for PO Number ---
    if ($action === 'add' && $module === 'Charge Invoice') {
        $poNo = $newData['po_no'] ?? 'Unknown';
        $id = $newData['id'] ?? ($oldData['id'] ?? '');
        $details = htmlspecialchars("Po No '{$poNo}' has been created");
        $changes = '<ul class="list-group">'
            . '<li class="list-group-item"><strong>Id:</strong> ' . htmlspecialchars($id) . '</li>'
            . '<li class="list-group-item"><strong>PO Number:</strong> ' . htmlspecialchars($poNo) . '</li>'
            . '</ul>';
        return [$details, $changes];
    }
    // --- END: Custom Add Action for PO Number ---
    // --- BEGIN: Custom Add Action for PO Number in Receiving Report ---
    if ($action === 'add' && $module === 'Receiving Report') {
        $poNo = $newData['po_no'] ?? 'Unknown';
        $id = $newData['id'] ?? ($oldData['id'] ?? '');
        $details = htmlspecialchars("Po No '{$poNo}' has been created");
        $changes = '<ul class="list-group">'
            . '<li class="list-group-item"><strong>Id:</strong> ' . htmlspecialchars($id) . '</li>'
            . '<li class="list-group-item"><strong>PO Number:</strong> ' . htmlspecialchars($poNo) . '</li>'
            . '</ul>';
        return [$details, $changes];
    }
    // --- END: Custom Add Action for PO Number in Receiving Report ---

    switch ($action) {
        case 'create':
            $details = htmlspecialchars("$targetEntityName has been created");
            $changes = formatNewValue($log['NewVal']);
            break;
        case 'modified':
            $changedFields = getChangedFieldNames($filteredOldData, $filteredNewData);
            if (!empty($changedFields)) {
                $details = "Modified Fields: " . htmlspecialchars(implode(', ', $changedFields));
            } else {
                $details = "Modified Fields: None";
            }
            $changes = formatAuditDiff(json_encode($filteredOldData), json_encode($filteredNewData));
            break;
        case 'restored':
            $details = htmlspecialchars("$targetEntityName has been restored");
            unset($log['is_disabled']);
            $changes = formatNewValue(jsonStr: $log['OldVal']);
            break;

        case 'removed':
            $defaultMessage = htmlspecialchars("$targetEntityName has been removed");
            list($details, $changes) = processStatusMessage(
                $defaultMessage,
                $log,
                function () use ($log) {
                    // 1) decode
                    $old = json_decode($log['OldVal'], true);
                    // 2) remove the is_disabled flag and status
                    unset($old['is_disabled']);
                    // 3) re-encode & hand it off to your existing formatter
                    return formatNewValue(json_encode($old));
                }
            );
            break;
        case 'deleted':
        case 'permanent delete':
            $defaultMessage = htmlspecialchars("$targetEntityName has been permanently deleted");
            list($details, $changes) = processStatusMessage(
                $defaultMessage,
                $log,
                function () use ($log) {
                    // 1) decode
                    $old = json_decode($log['OldVal'], true);
                    // 2) remove the is_disabled flag and status
                    unset($old['is_disabled']);
                    // 3) re-encode & hand it off to your existing formatter
                    return formatNewValue(json_encode($old));
                }
            );
            break;
        default:
            $details = htmlspecialchars($log['Details'] ?? '');
            $changes = formatNewValue($log['OldVal']);
            break;
    }

    return [$details, $changes];
}

/**
 * Get Changed Field Names
 *
 * Identifies field names that have changed between old and new data, excluding system fields.
 *
 * @param array $oldData The old data array.
 * @param array $newData The new data array.
 * @return array List of changed field names.
 */
function getChangedFieldNames(array $oldData, array $newData)
{
    // Fields that should NOT be included in the diff
    $systemFields = ['date_created', 'date_acquired', 'date_modified', 'is_disabled'];

    $changed = [];
    $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));

    foreach ($allKeys as $key) {
        // Skip system-managed fields
        if (in_array($key, $systemFields)) {
            continue;
        }

        $oldVal = $oldData[$key] ?? null;
        $newVal = $newData[$key] ?? null;

        if ($oldVal !== $newVal) {
            $changed[] = ucwords(str_replace('_', ' ', $key));
        }
    }

    return $changed;
}

// Fetch unique values for dropdowns
try {
    // Get unique action types
    $actionTypesQuery = "SELECT DISTINCT Action 
                        FROM audit_log 
                        WHERE Module IN ('Purchase Order', 'Charge Invoice', 'Receiving Report')
                        AND Action IS NOT NULL 
                        AND Action != '' 
                        ORDER BY Action";
    $actionTypesStmt = $pdo->prepare($actionTypesQuery);
    $actionTypesStmt->execute();
    $actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get unique status types
    $statusTypesQuery = "SELECT DISTINCT Status 
                        FROM audit_log 
                        WHERE Module IN ('Purchase Order', 'Charge Invoice', 'Receiving Report')
                        AND Status IS NOT NULL 
                        AND Status != '' 
                        ORDER BY Status";
    $statusTypesStmt = $pdo->prepare($statusTypesQuery);
    $statusTypesStmt->execute();
    $statusTypes = $statusTypesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get unique module types
    $moduleTypesQuery = "SELECT DISTINCT Module 
                        FROM audit_log 
                        WHERE Module IN ('Purchase Order', 'Charge Invoice', 'Receiving Report')
                        AND Module IS NOT NULL 
                        AND Module != '' 
                        ORDER BY Module";
    $moduleTypesStmt = $pdo->prepare($moduleTypesQuery);
    $moduleTypesStmt->execute();
    $moduleTypes = $moduleTypesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching dropdown values: " . $e->getMessage());
    $actionTypes = [];
    $statusTypes = [];
    $moduleTypes = [];
}

// Initialize sorting parameters
$sortColumn = $_GET['sort'] ?? 'TrackID';
$sortOrder = $_GET['order'] ?? 'DESC';

// Validate sort column to prevent SQL injection
$allowedColumns = ['TrackID', 'email', 'Module', 'Action', 'Details', 'Status', 'Date_Time'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'TrackID';
}

// Validate sort order
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

// Initialize base WHERE clause
$where = "WHERE audit_log.Module IN ('Purchase Order', 'Charge Invoice', 'Receiving Report')";
$params = [];

// Filter by module type
if (!empty($_GET['module_type'])) {
    $where .= " AND audit_log.Module = :module_type";
    $params[':module_type'] = $_GET['module_type'];
}

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
        if (!empty($_GET['month_year_to'])) {  // <-- FIXED KEY
            $where .= " AND DATE_FORMAT(audit_log.date_time, '%Y-%m') <= :month_year_to";
            $params[':month_year_to'] = date('Y-m', strtotime($_GET['month_year_to']));  // <-- FIXED PARAM
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

// Update the main query to use the WHERE clause
$query = "SELECT audit_log.TrackID,
    audit_log.UserID,
    audit_log.EntityID,
    audit_log.Module,
    audit_log.Action,
    audit_log.Details,
    audit_log.OldVal,
    audit_log.NewVal,
    UPPER(audit_log.Status) AS status,
    audit_log.Date_Time,
    users.email AS email
          FROM audit_log 
          LEFT JOIN users ON audit_log.UserID = users.id
          $where
          ORDER BY audit_log.date_time DESC";

// Debug the query and parameters
error_log("Query: " . $query);
error_log("Parameters: " . print_r($params, true));

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug the results
error_log("Number of results: " . count($auditLogs));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Audit Logs Dashboard</title>
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>src/view/styles/css/audit_log.css">
    <!-- Add CSS for sortable headers -->
    <style>
        .sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
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
            font-size: 0.8em;
        }

        .sortable:hover i {
            color: #495057;
        }

        .sortable i.fa-sort-up,
        .sortable i.fa-sort-down {
            color: #0d6efd;
        }
    </style>
</head>

<body>


    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                    <h3 class="text-white">
                        <i class="fas fa-history me-2"></i>
                        Equipment Transaction Audit Logs
                    </h3>
                </div>

                <div class="card-body">
                    <!-- Permission Info Banner -->
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-shield-alt me-2"></i>
                        You have permission to view Equipment Management audit logs.
                    </div>

                    <!-- Filter Section -->
                    <form method="GET" class="row g-3 mb-4" id="auditFilterForm" onsubmit="return false;">
                        <div class="col-md-3">
                            <label for="moduleType" class="form-label">Module Type</label>
                            <select class="form-select" name="module_type" id="moduleType">
                                <option value="">All</option>
                                <?php
                                // Define the allowed modules
                                $allowedModules = ['Purchase Order', 'Charge Invoice', 'Receiving Report'];
                                
                                // Get unique modules from the database
                                $moduleTypesQuery = "SELECT DISTINCT Module 
                                                   FROM audit_log 
                                                   WHERE Module IN ('Purchase Order', 'Charge Invoice', 'Receiving Report')
                                                   ORDER BY Module";
                                $moduleTypesStmt = $pdo->prepare($moduleTypesQuery);
                                $moduleTypesStmt->execute();
                                $moduleTypes = $moduleTypesStmt->fetchAll(PDO::FETCH_COLUMN);
                                
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

                        <div class="col-md-3">
                            <label for="actionType" class="form-label">Action Type</label>
                            <select class="form-select" name="action_type" id="actionType">
                                <option value="">All</option>
                                <?php
                                if (!empty($actionTypes)) {
                                    foreach ($actionTypes as $action) {
    $displayAction = ucfirst(strtolower($action));
    $selected = ($_GET['action_type'] ?? '') === $action ? 'selected' : '';
    echo '<option value="' . htmlspecialchars($action) . '" ' . $selected . '>' .
        htmlspecialchars($displayAction) . '</option>';
}
                                } else {
                                    // Fallback options if database query fails
                                    $defaultActions = ['Create', 'Modified', 'Remove', 'Restore', 'Delete'];
                                    foreach ($defaultActions as $action) {
                                        $selected = ($_GET['action_type'] ?? '') === $action ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($action) . '" ' . $selected . '>' .
                                            htmlspecialchars($action) . '</option>';
                                    }
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
              

                <!-- Table container with colgroup for column widths -->
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
                                    <i class="fas fa-sort<?= $sortColumn === 'TrackID' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                                <th class="sortable" data-column="email">
                                    User
                                    <i class="fas fa-sort<?= $sortColumn === 'email' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                                <th class="sortable" data-column="Module">
                                    Module
                                    <i class="fas fa-sort<?= $sortColumn === 'Module' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                                <th class="sortable" data-column="Action">
                                    Action
                                    <i class="fas fa-sort<?= $sortColumn === 'Action' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                                <th class="sortable" data-column="Details">
                                    Details
                                    <i class="fas fa-sort<?= $sortColumn === 'Details' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                                <th class="sortable" data-column="Changes">
                                    Changes
                                    <i class="fas fa-sort<?= $sortColumn === 'Changes' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                                <th class="sortable" data-column="Status">
                                    Status
                                    <i class="fas fa-sort<?= $sortColumn === 'Status' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                                <th class="sortable" data-column="Date_Time">
                                    Date & Time
                                    <i class="fas fa-sort<?= $sortColumn === 'Date_Time' ? '-' . ($sortOrder === 'ASC' ? 'up' : 'down') : '' ?>"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="auditTable">
                            <?php if (!empty($auditLogs)): ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <?php
                                    // Normalize action to lower case for comparisons.
                                    $actionLower = strtolower($log['Action'] ?? '');
                                    ?>
                                    <tr>
                                        <!-- TRACK ID -->
                                        <td data-label="Track ID">
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($log['TrackID']); ?>
                                            </span>
                                        </td>

                                        <!-- USER WHO PERFORMED -->
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
                                            $actionText = !empty($log['Action']) ? $log['Action'] : 'Unknown';
                                            echo "<!-- Debug: Action Text = $actionText -->";

                                            // Check for restore action based on JSON values with null checks
                                            if (!is_null($log['OldVal']) && !is_null($log['NewVal'])) {
                                                $oldData = json_decode($log['OldVal'], true);
                                                $newData = json_decode($log['NewVal'], true);
                                                if (is_array($oldData) && is_array($newData)) {
                                                    if (
                                                        isset($oldData['is_deleted'], $newData['is_deleted']) &&
                                                        (int)$oldData['is_deleted'] === 1 && (int)$newData['is_deleted'] === 0
                                                    ) {
                                                        $actionText = 'Restored';
                                                    }
                                                }
                                            }
                                            echo getActionIcon($actionText);
                                            ?>
                                        </td>
                                        <?php
                                        list($detailsHTML, $changesHTML) = formatDetailsAndChanges($log);
                                        ?>

                                        <!-- DETAILS -->
                                        <td data-label="Details" class="data-container">
                                            <?php echo nl2br($detailsHTML); ?>
                                        </td>

                                        <!-- CHANGES -->
                                        <td data-label="Changes" class="data-container">
                                            <?php echo nl2br($changesHTML); ?>
                                        </td>

                                        <!-- STATUS -->
                                        <td data-label="Status">
                                            <?php
                                            $rawStatus = $log['Status'] ?? $log['status'] ?? '';
                                            $normalized = strtolower(trim($rawStatus));
                                            $isSuccess = in_array($normalized, ['successful', 'success']);
                                            ?>
                                            <!-- Raw Status: '<?= htmlspecialchars($rawStatus) ?>' -->
                                            <!-- Normalized: '<?= htmlspecialchars($normalized) ?>' -->
                                            <!-- Is Success: <?= $isSuccess ? 'true' : 'false' ?> -->
                                            <span class="badge <?= $isSuccess ? 'bg-success' : 'bg-danger' ?>">
                                                <?= getStatusIcon($rawStatus) . ' ' . ($isSuccess ? 'Successful' : 'Failed') ?>
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
                            <!-- Pagination Info -->
                            <div class="col-12 col-sm-auto">
                                <div class="text-muted">
                                    <?php $totalLogs = count($auditLogs); ?>
                                    <input type="hidden" id="total-users" value="<?= $totalLogs ?>">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows"><?= $totalLogs ?></span> entries
                                </div>
                            </div>

                            <!-- Pagination Controls -->
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
                        <!-- New Pagination Page Numbers -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <ul class="pagination justify-content-center" id="pagination"></ul>
                            </div>
                        </div>
                    </div> <!-- /.End of Pagination -->
                </div><!-- /.table-responsive -->
            </div><!-- /.card-body -->
        </div><!-- /.card -->
    </div><!-- /.container-fluid -->
    </div><!-- /.main-content -->
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/logs.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
    <!-- Add JavaScript for sorting -->
    <script>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get filter elements
            const filterForm = document.getElementById('auditFilterForm');
            const searchInput = document.getElementById('searchInput');
            const moduleTypeFilter = document.getElementById('moduleType');
            const actionTypeFilter = document.getElementById('actionType');
            const statusFilter = document.getElementById('status');
            const dateFilterType = document.getElementById('dateFilterType');
            const applyFiltersBtn = document.getElementById('applyFilters');
            const clearFiltersBtn = document.getElementById('clearFilters');

            // Handle date filter type changes
            if (dateFilterType) {
                // Initial setup of date filter fields
                function setupDateFilters() {
                    // Hide all date filter fields first
                    document.querySelectorAll('.date-filter').forEach(field => {
                        field.classList.add('d-none');
                    });
                    
                    // Show the relevant date filter fields based on current selection
                    const selectedType = dateFilterType.value;
                    if (selectedType) {
                        document.querySelectorAll('.date-' + selectedType).forEach(field => {
                            field.classList.remove('d-none');
                        });
                    }
                }

                // Run initial setup
                setupDateFilters();

                // Add change event listener
                dateFilterType.addEventListener('change', setupDateFilters);
            }

            // Clear filters button
            clearFiltersBtn.addEventListener('click', function() {
                // Reset all form fields
                filterForm.reset();
                
                // Explicitly reset module type dropdown to first option
                if (moduleTypeFilter) {
                    moduleTypeFilter.value = '';
                }
                
                // Explicitly reset action type dropdown to first option
                if (actionTypeFilter) {
                    actionTypeFilter.value = '';
                }
                
                // Explicitly reset status dropdown to first option
                if (statusFilter) {
                    statusFilter.value = '';
                }
                
                // Clear search input
                if (searchInput) {
                    searchInput.value = '';
                }
                
                // Reset date filter type and hide all date filter fields
                if (dateFilterType) {
                    dateFilterType.value = '';
                    document.querySelectorAll('.date-filter').forEach(field => {
                        field.classList.add('d-none');
                    });
                }
                
                // Clear any hidden inputs
                const hiddenInputs = filterForm.querySelectorAll('input[type="hidden"]');
                hiddenInputs.forEach(input => {
                    input.value = '';
                });
                
                // Redirect to the base URL without any query parameters
                window.location.href = window.location.pathname;
            });

            // Apply filters button
            applyFiltersBtn.addEventListener('click', function(e) {
                // Date filter validation
                const dateType = dateFilterType.value;
                let isValid = true;
                let errorMessage = '';

                if (dateType === 'mdy') {
                    const dateFrom = document.querySelector('input[name="date_from"]').value;
                    const dateTo = document.querySelector('input[name="date_to"]').value;
                    if (dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)) {
                        isValid = false;
                        errorMessage = '"From" date cannot be greater than "To" date.';
                    }
                } else if (dateType === 'month_year') {
                    const monthYearFrom = document.querySelector('input[name="month_year_from"]').value;
                    const monthYearTo = document.querySelector('input[name="month_year_to"]').value;
                    if (monthYearFrom && monthYearTo && new Date(monthYearFrom) > new Date(monthYearTo)) {
                        isValid = false;
                        errorMessage = '"From" month-year cannot be greater than "To" month-year.';
                    }
                } else if (dateType === 'year') {
                    const yearFrom = document.querySelector('input[name="year_from"]').value;
                    const yearTo = document.querySelector('input[name="year_to"]').value;
                    if (yearFrom && yearTo && parseInt(yearFrom) > parseInt(yearTo)) {
                        isValid = false;
                        errorMessage = '"From" year cannot be greater than "To" year.';
                    }
                }

                if (!isValid) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    // Remove any existing error message
                    const existingError = document.querySelector('.date-error-message');
                    if (existingError) {
                        existingError.remove();
                    }

                    // Add error message below the auditFilterForm with max-width
                    const formContainer = document.getElementById('auditFilterForm');
                    if (formContainer) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger alert-dismissible fade show mt-2 date-error-message';
                        errorDiv.role = 'alert';
                        errorDiv.style.cssText = 'max-width: 100%; margin-top: 10px;';
                        errorDiv.innerHTML = `
                            <strong> </strong> ${errorMessage}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        formContainer.insertAdjacentElement('afterend', errorDiv);

                        // Auto-dismiss after 3 seconds
                        setTimeout(() => {
                            errorDiv.classList.remove('show');
                            errorDiv.classList.add('fade');
                            setTimeout(() => errorDiv.remove(), 150);
                        }, 3000);
                    }

                    return false;
                } else {
                    // If validation passes, submit the form
                    filterForm.submit();
                }
            });
        });
    </script>
</body>

</html>