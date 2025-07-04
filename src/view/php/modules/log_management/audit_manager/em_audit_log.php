<?php
/**
 * Equipment Management Audit Log Module
 *
 * This file provides comprehensive audit logging functionality for equipment management activities. It tracks and records all changes made to equipment records, including creation, modification, and deletion events. The module ensures detailed logging of user actions, timestamps, and relevant data changes for security and accountability purposes.
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

// Add em-audit-log class to body
echo '<script>document.body.classList.add("em-audit-log");</script>';

//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php"); // Redirect to login page
    exit();
}

/**
 * Initialize RBAC and Check Permissions
 *
 * Initializes the Role-Based Access Control (RBAC) system and checks if the user has the necessary permissions
 * to view audit logs for equipment management.
 *
 * @return void
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check for required privilege
$hasAuditPermission = $rbac->hasPrivilege('Audit', 'Track');
$hasEMPermission = $rbac->hasPrivilege('Equipment Management', 'Track');

/**
 * Check User Permission
 *
 * Displays an access denied message if the user lacks the necessary permissions.
 *
 * @return void
 */
if (!$hasAuditPermission && !$hasEMPermission) {
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
 * Retrieves all audit logs related to equipment management from the database.
 *
 * @return void
 */
$query = "
    SELECT
        audit_log.*,
        users.email AS email
    FROM audit_log
    LEFT JOIN users
      ON audit_log.UserID = users.id
    WHERE audit_log.Module IN (
      'Equipment Management',
      'Equipment Details',
      'Equipment Location',
      'Equipment Status'
    )
    ORDER BY
      audit_log.TrackID DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Department ID to Name Mapping
 *
 * Creates a mapping of department IDs to names for display purposes in audit logs.
 *
 * @return void
 */
$departmentIdNameMap = [];
try {
    $deptStmt = $pdo->query("SELECT id, department_name FROM departments");
    while ($row = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
        $departmentIdNameMap[$row['id']] = $row['department_name'];
    }
} catch (Exception $e) {
}

/**
 * Get Department Name
 *
 * Retrieves the department name for a given ID from the mapping.
 *
 * @param int|string $id The department ID.
 * @return string The department name or ID if not found.
 */
function getDepartmentName($id)
{
    global $departmentIdNameMap;
    return isset($departmentIdNameMap[$id]) ? $departmentIdNameMap[$id] : $id;
}

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
 * Formats a JSON string or array into a visually appealing HTML list.
 *
 * @param string|array $jsonStr The JSON string or array to format.
 * @return string The formatted HTML list.
 */
function formatNewValue($jsonStr)
{
    if ($jsonStr === null) {
        return '<em class="text-muted">N/A</em>';
    }

    // If the input is already an array, use it directly
    $data = is_array($jsonStr) ? $jsonStr : json_decode($jsonStr, true);
    
    if (!is_array($data)) {
        // If json_decode failed and input is not an array, return the original string
        return '<span>' . htmlspecialchars(is_string($jsonStr) ? $jsonStr : json_encode($jsonStr)) . '</span>';
    }

    $html = '<ul class="list-group">';
    foreach ($data as $key => $value) {
        // Convert null to a display string
        if (is_null($value)) {
            $displayValue = '<em>null</em>';
        } elseif (is_array($value)) {
            $displayValue = htmlspecialchars(json_encode($value));
        } else {
            $displayValue = htmlspecialchars((string)$value);
        }
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

    // Exclude these fields from the diff
    $excludeFields = ['is_deleted', 'equipment_location_id'];

    foreach ($keys as $key) {
        $lcKey = strtolower($key);

        // Exclude system and irrelevant fields
        if (in_array($lcKey, $excludeFields)) {
            continue;
        }

        // Handle password changes
        if ($lcKey === 'password') {
            if (isset($oldData[$key], $newData[$key]) && $oldData[$key] !== $newData[$key]) {
                $descriptions[] = "The password has been changed.";
            }
            continue;
        }

        // Special handling for department_id
        if ($lcKey === 'department_id') {
            $oldVal = isset($oldData[$key]) ? $oldData[$key] : '';
            $newVal = isset($newData[$key]) ? $newData[$key] : '';
            if ($oldVal !== $newVal) {
                $oldDept = getDepartmentName($oldVal);
                $newDept = getDepartmentName($newVal);
                $descriptions[] = "The Department was changed from '<em>{$oldDept}</em>' to '<strong>{$newDept}</strong>'.";
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
    } elseif ($action === 'create') {
        return '<span class="action-badge action-create"><i class="fas fa-plus-circle me-1"></i> Create</span>';
    } elseif ($action === 'remove') {
        return '<span class="action-badge action-delete"><i class="fas fa-trash-alt me-1"></i> Removed</span>';
    } elseif ($action === 'delete') {
        return '<span class="action-badge action-delete"><i class="fas fa-trash-alt me-1"></i> Delete</span>';
    } elseif ($action === 'restored') {
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
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
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

    // Parse JSON fields for old/new data with null checks
    $oldData = !is_null($log['OldVal']) ? json_decode($log['OldVal'], true) : [];
    $newData = !is_null($log['NewVal']) ? json_decode($log['NewVal'], true) : [];

    // Prepare default strings
    $details = '';
    $changes = '';

    //Target Entity Set to ASSET TAG
    $targetEntityName = $newData['asset_tag'] ?? $oldData['asset_tag'] ?? 'Unknown';

    // List of fields that should never be shown in diff
    $systemFields = ['date_created', 'date_acquired', 'date_modified', 'is_disabled'];

    // Filter out system fields from both datasets before comparison
    $filteredOldData = array_diff_key($oldData, array_flip($systemFields));
    $filteredNewData = array_diff_key($newData, array_flip($systemFields));

    // --- Custom Add Action for Added Fields on Edit ---
    if ($action === 'modified') {
        $addFields = [];
        $addChanges = [];
        // Merge all relevant fields for Equipment Status, Equipment Location, and Equipment Details
        $fieldsToCheck = [
            // Equipment Status fields
            'status',
            'action',
            'remarks',
            // Equipment Location fields
            'building_loc',
            'floor_no',
            'specific_area',
            'person_responsible',
            'department_id',
            // Equipment Details fields
            'asset_description_1',
            'asset_description_2',
            'specifications',
            'brand',
            'model',
            'serial_number',
            'rr_no',
            'location',
            'accountable_individual',
            'remarks'
        ];
        foreach ($fieldsToCheck as $field) {
            $oldVal = isset($filteredOldData[$field]) ? trim((string)$filteredOldData[$field]) : '';
            $newVal = isset($filteredNewData[$field]) ? trim((string)$filteredNewData[$field]) : '';
            if (($oldVal === '' || $oldVal === null) && $newVal !== '') {
                if ($field === 'department_id') {
                    $label = 'Department';
                    $displayVal = getDepartmentName($newVal);
                } else {
                    $label = ucwords(str_replace('_', ' ', $field));
                    $displayVal = htmlspecialchars($newVal);
                }
                $addFields[] = $label;
                $addChanges[] = "The {$label} was changed from '<em>N/A</em>' to '<strong>{$displayVal}</strong>'.";
            }
        }
        if (!empty($addFields)) {
            // Show as Modified action with consistent format
            $details = "Asset Tag: <strong>{$targetEntityName}</strong> has been modified<br>Modified fields: <strong><em>" . implode(', ', $addFields) . "</em></strong>";
            $changes = '<ul class="list-unstyled mb-0">';
            $count = count($addChanges);
            $index = 0;
            foreach ($addChanges as $change) {
                $changes .= "<li>{$change}";
                $index++;
                if ($index < $count) {
                    $changes .= "<hr class='my-1'>";
                }
                $changes .= "</li>";
            }
            $changes .= "</ul>";
            return [$details, $changes];
        }
    }
    // --- End Custom Add Action ---

    switch ($action) {
        case 'create':
            $details = "Asset Tag: <strong>{$targetEntityName}</strong> has been created";
            $changes = formatNewValue($log['NewVal']);
            break;
        case 'modified':
            $changedFields = getChangedFieldNames($filteredOldData, $filteredNewData);
            if (!empty($changedFields)) {
                $details = "Asset Tag: <strong>{$targetEntityName}</strong> has been modified<br>Modified fields: <strong><em>" . implode(', ', $changedFields) . "</em></strong>";
                $changes = formatAuditDiff(json_encode($filteredOldData), json_encode($filteredNewData));
            } else {
                $details = "Asset Tag: <strong>{$targetEntityName}</strong> has been modified<br>Modified fields: <strong><em>None</em></strong>";
                $changes = "";
            }
            break;
        case 'restored':
            $details = "Asset Tag: <strong>{$targetEntityName}</strong> has been restored";
            $changes = formatNewValue($log['OldVal']);
            break;
        case 'remove':
            $details = "Asset Tag: <strong>{$targetEntityName}</strong> has been removed";
            $changes = formatNewValue($log['NewVal']);
            break;
        case 'delete':
            $details = "Asset Tag: <strong>{$targetEntityName}</strong> has been deleted from the database";
            $changes = formatNewValue($log['OldVal']);
            break;
        default:
            $details = "Asset Tag: <strong>{$targetEntityName}</strong><br>" . htmlspecialchars($log['Details'] ?? '');
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
    // Fields that should NOT be included in the diff (but allow department_id for summary)
    $systemFields = ['date_created', 'date_acquired', 'date_modified', 'is_disabled', 'equipment_location_id'];

    $changed = [];
    $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));

    foreach ($allKeys as $key) {
        // Skip system-managed fields and excluded fields, but NOT department_id
        if (in_array($key, $systemFields)) {
            continue;
        }

        $oldVal = $oldData[$key] ?? null;
        $newVal = $newData[$key] ?? null;

        if ($oldVal !== $newVal) {
            if ($key === 'department_id') {
                $changed[] = 'Department';
            } else {
                $changed[] = ucwords(str_replace('_', ' ', $key));
            }
        }
    }

    return $changed;
}

/**
 * Build Query Filters
 *
 * Constructs the WHERE clause and parameters for filtering audit logs based on user input.
 *
 * @return void
 */
//Filter Section
// Base WHERE clause to filter only Equipment Management related modules (and subcomponents)
$where = "WHERE audit_log.Module IN ('Equipment Details', 'Equipment Location', 'Equipment Status')";
$params = [];

// Filter by action type (Create, Modified, Removed, etc.)
if (!empty($_GET['action_type'])) {
    $actionType = $_GET['action_type'];
    // Map display value 'Removed' back to DB value 'remove'
    if (strtolower($actionType) === 'removed') {
        $actionType = 'remove';
    }
    $where .= " AND audit_log.Action = :action_type";
    $params[':action_type'] = $actionType;
}

// Filter by status (Successful, Failed)
if (!empty($_GET['status'])) {
    $where .= " AND audit_log.Status = :status";
    $params[':status'] = $_GET['status'];
}

// Filter by search string (matching user email or old/new values)
if (!empty($_GET['search'])) {
    $where .= " AND (users.email LIKE :search_email OR audit_log.NewVal LIKE :search_newval OR audit_log.OldVal LIKE :search_oldval)";
    $searchParam = '%' . $_GET['search'] . '%';
    $params[':search_email'] = $searchParam;
    $params[':search_newval'] = $searchParam;
    $params[':search_oldval'] = $searchParam;
}

// Filter by module type
if (!empty($_GET['module_type'])) {
    $where .= " AND audit_log.Module = :module_type";
    $params[':module_type'] = $_GET['module_type'];
    error_log("Module type filter applied: " . $_GET['module_type']);
}

// Date filters based on date_filter_type
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

/**
 * Fetch Filtered Audit Logs
 *
 * Retrieves audit logs based on the constructed filters and orders them by date.
 *
 * @return void
 */
// Query to get audit logs with user emails
$query = "SELECT audit_log.TrackID,
    audit_log.UserID,
    audit_log.EntityID,
    audit_log.Module,
    audit_log.Action,
    audit_log.Details,
    audit_log.OldVal,
    audit_log.NewVal,
    audit_log.Status,
    audit_log.Date_Time,
    users.email AS email
    FROM audit_log 
    LEFT JOIN users ON audit_log.UserID = users.id
    $where
    ORDER BY audit_log.date_time DESC";

// Debug the query and parameters
error_log("Final Query: " . $query);
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
    <link rel="stylesheet" href="<?= BASE_URL ?>src/view/styles/css/audit_log.css">
</head>

<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                    <h3 class="text-white">
                        <i class="fas fa-history me-2"></i>
                        Equipment Management Audit Logs
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
                                $allowedModules = ['Equipment Details', 'Equipment Location', 'Equipment Status'];
                                
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
                                if (!empty($actionTypes)):
                                    foreach ($actionTypes as $action):
                                        $selected = ($_GET['action_type'] ?? '') === $action ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($action) . '" ' . $selected . '>' .
                                            htmlspecialchars($action) . '</option>';
                                    endforeach;
                                else:
                                    // Fallback options if database query fails
                                    $defaultActions = ['Create', 'Modified', 'Removed', 'Restored', 'Delete'];
                                    foreach ($defaultActions as $action):
                                        $selected = ($_GET['action_type'] ?? '') === $action ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($action) . '" ' . $selected . '>' .
                                            htmlspecialchars($action) . '</option>';
                                    endforeach;
                                endif;
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="">All</option>
                                <?php
                                if (!empty($statusTypes)):
                                    foreach ($statusTypes as $status):
                                        $selected = ($_GET['status'] ?? '') === $status ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($status) . '" ' . $selected . '>' .
                                            htmlspecialchars($status) . '</option>';
                                    endforeach;
                                else:
                                    // Fallback options if database query fails
                                    $defaultStatuses = ['Successful', 'Failed'];
                                    foreach ($defaultStatuses as $status):
                                        $selected = ($_GET['status'] ?? '') === $status ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($status) . '" ' . $selected . '>' .
                                            htmlspecialchars($status) . '</option>';
                                    endforeach;
                                    // Add debug info
                                    error_log("Using fallback status types as no values were found in database");
                                endif;
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
                                <col class="date">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th>Track ID</th>
                                    <th>User</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Changes</th>
                                    <th>Date & Time</th>
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
                                                // --- Sync Action badge with Details: if Details starts with 'Added Fields:', show Add ---
                                                list($detailsHTML, $changesHTML) = formatDetailsAndChanges($log);
                                                if (strtolower($log['Action'] ?? '') === 'modified' && strpos($detailsHTML, 'Added Fields:') === 0) {
                                                    $actionText = 'Modified';
                                                }
                                                // --- End Sync ---
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