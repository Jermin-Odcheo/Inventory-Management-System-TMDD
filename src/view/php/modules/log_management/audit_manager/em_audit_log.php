<?php
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';
include '../../../general/sidebar.php';
//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php"); // Redirect to login page
    exit();
}

// Initialize RBAC and check permissions
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check for required privilege
$hasAuditPermission = $rbac->hasPrivilege('Audit', 'Track');
$hasAuditPermission = $rbac->hasPrivilege('Equipment Management', 'Track');

// If user doesn't have permission, show an inline "no permission" page
if (!$hasAuditPermission) {
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

// --- Department ID to Name Map (for audit diff display) ---
$departmentIdNameMap = [];
try {
    $deptStmt = $pdo->query("SELECT id, department_name FROM departments");
    while ($row = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
        $departmentIdNameMap[$row['id']] = $row['department_name'];
    }
} catch (Exception $e) {
}

function getDepartmentName($id)
{
    global $departmentIdNameMap;
    return isset($departmentIdNameMap[$id]) ? $departmentIdNameMap[$id] : $id;
}

/**
 * Helper function to display JSON data with <br> for new lines.
 */
function formatJsonData($jsonStr)
{
    if (!$jsonStr) {
        return '<em class="text-muted">N/A</em>';
    }
    return nl2br(htmlspecialchars($jsonStr));
}

/**
 * New helper function to format a JSON string into a visually appealing list.
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
 * Helper function to compare old/new JSON data for modifications.
 * Special handling for the "is_deleted" field is included.
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
 * Helper function to return an icon based on action.
 */
function getActionIcon($action)
{
    $action = strtolower($action);

    if ($action === 'modified') {
        return '<span class="action-badge action-modified"><i class="fas fa-pen me-1"></i> Modified</span>';
    } elseif ($action === 'create') {
        return '<span class="action-badge action-create"><i class="fas fa-plus-circle me-1"></i> Create</span>';
    } elseif ($action === 'remove' || $action === 'delete') {
        return '<span class="action-badge action-delete"><i class="fas fa-trash-alt me-1"></i> Removed</span>';
    } elseif ($action === 'restored') {
        return '<span class="action-badge action-restored"><i class="fas fa-trash-restore me-1"></i> Restored</span>';
    } else {
        return '<span class="action-badge"><i class="fas fa-info-circle me-1"></i> ' . ucfirst($action) . '</span>';
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
 * Format the "Details" and "Changes" columns based on the action.
 * Returns an array: [ $detailsHTML, $changesHTML ]
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
                $addChanges[] = "The {$label} '" . $displayVal . "' is added.";
            }
        }
        if (!empty($addFields)) {
            // Show as Add action
            $details = 'Added Fields: ' . htmlspecialchars(implode(', ', $addFields));
            $changes = '<ul class="list-unstyled mb-0">';
            foreach ($addChanges as $msg) {
                $changes .= '<li>' . $msg . '</li>';
            }
            $changes .= '</ul>';
            return [$details, $changes];
        }
    }
    // --- End Custom Add Action ---

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
            $changes = "is_deleted 1 -> 0";
            break;
        case 'remove':
            $details = htmlspecialchars("$targetEntityName has been removed");
            $changes = "is_deleted 0 -> 1";
            break;
        case 'delete':
            $details = htmlspecialchars("$targetEntityName has been deleted from the database");
            $changes = formatNewValue($log['OldVal']);
            break;
        default:
            $details = htmlspecialchars($log['Details'] ?? '');
            $changes = formatNewValue($log['OldVal']);
            break;
    }

    return [$details, $changes];
}

/**
 * Helper function to find which fields changed (just the field names).
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

//Filter Section
// Base WHERE clause to filter only Equipment Management related modules (and subcomponents)
$where = "WHERE audit_log.Module IN ('Equipment Details', 'Equipment Location', 'Equipment Status')";
$params = [];

// Filter by action type (Create, Modified, Remove, etc.)
if (!empty($_GET['action_type'])) {
    $where .= " AND audit_log.Action = :action_type";
    $params[':action_type'] = $_GET['action_type'];
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

// Query to get audit logs with user emails
$query = "SELECT audit_log.*, users.email AS email 
          FROM audit_log 
          LEFT JOIN users ON audit_log.UserID = users.id
          $where
          ORDER BY audit_log.date_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <div class="row mb-4">

                        <!-- Filter Section -->
                        <form method="GET" class="row g-3 mb-4" id="auditFilterForm" onsubmit="return false;">
    <div class="col-md-3">
        <label for="actionType" class="form-label">Action Type</label>
        <select class="form-select live-filter" name="action_type" id="actionType">
            <option value="">All</option>
            <option value="Create" <?= ($_GET['action_type'] ?? '') === 'Create' ? 'selected' : '' ?>>Create</option>
            <option value="Modified" <?= ($_GET['action_type'] ?? '') === 'Modified' ? 'selected' : '' ?>>Modified</option>
            <option value="Remove" <?= ($_GET['action_type'] ?? '') === 'Remove' ? 'selected' : '' ?>>Remove</option>
            <option value="Restored" <?= ($_GET['action_type'] ?? '') === 'Restored' ? 'selected' : '' ?>>Restored</option>
            <option value="Login" <?= ($_GET['action_type'] ?? '') === 'Login' ? 'selected' : '' ?>>Login</option>
            <option value="Logout" <?= ($_GET['action_type'] ?? '') === 'Logout' ? 'selected' : '' ?>>Logout</option>
        </select>
    </div>

    <div class="col-md-3">
        <label for="status" class="form-label">Status</label>
        <select class="form-select live-filter" name="status" id="status">
            <option value="">All</option>
            <option value="Successful" <?= ($_GET['status'] ?? '') === 'Successful' ? 'selected' : '' ?>>Successful</option>
            <option value="Failed" <?= ($_GET['status'] ?? '') === 'Failed' ? 'selected' : '' ?>>Failed</option>
        </select>
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label fw-semibold">Date Filter Type</label>
        <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm live-filter">
            <option value="" <?= empty($_GET['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
            <option value="month_year" <?= (($_GET['date_filter_type'] ?? '') === 'month_year') ? 'selected' : '' ?>>Month-Year Range</option>
            <option value="year" <?= (($_GET['date_filter_type'] ?? '') === 'year') ? 'selected' : '' ?>>Year Range</option>
            <option value="mdy" <?= (($_GET['date_filter_type'] ?? '') === 'mdy') ? 'selected' : '' ?>>Month-Date-Year Range</option>
        </select>
    </div>

    <!-- MDY Range -->
    <div class="col-12 col-md-3 date-filter date-mdy d-none">
        <label class="form-label fw-semibold">Date From</label>
        <input type="date" name="date_from" class="form-control shadow-sm live-filter" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
    </div>
    <div class="col-12 col-md-3 date-filter date-mdy d-none">
        <label class="form-label fw-semibold">Date To</label>
        <input type="date" name="date_to" class="form-control shadow-sm live-filter" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
    </div>

    <!-- Year Range -->
    <div class="col-12 col-md-3 date-filter date-year d-none">
        <label class="form-label fw-semibold">Year From</label>
        <input type="number" name="year_from" class="form-control shadow-sm live-filter" min="1900" max="2100" placeholder="e.g., 2023" value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">
    </div>
    <div class="col-12 col-md-3 date-filter date-year d-none">
        <label class="form-label fw-semibold">Year To</label>
        <input type="number" name="year_to" class="form-control shadow-sm live-filter" min="1900" max="2100" placeholder="e.g., 2025" value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
    </div>

    <!-- Month-Year Range -->
    <div class="col-12 col-md-3 date-filter date-month_year d-none">
        <label class="form-label fw-semibold">From (MM-YYYY)</label>
        <input type="month" name="month_year_from" class="form-control shadow-sm live-filter" value="<?= htmlspecialchars($_GET['month_year_from'] ?? '') ?>">
    </div>
    <div class="col-12 col-md-3 date-filter date-month_year d-none">
        <label class="form-label fw-semibold">To (MM-YYYY)</label>
        <input type="month" name="month_year_to" class="form-control shadow-sm live-filter" value="<?= htmlspecialchars($_GET['month_year_to'] ?? '') ?>">
    </div>

    <!-- Search bar -->
    <div class="col-12 col-sm-6 col-md-3">
        <label class="form-label fw-semibold">Search</label>
        <div class="input-group shadow-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" name="search" id="searchInput" class="form-control live-filter" placeholder="Search keyword..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        </div>
    </div>

    <!-- Filter Button -->
    <div class="col-6 col-md-2 d-grid" style="align-items: center;">
        <button type="button" id="applyFilters" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
    </div>

    <!-- Clear Filter Button -->
    <div class="col-6 col-md-2 d-grid" style="align-items: center;">
        <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm"><i class="bi bi-x-circle"></i> Clear</button>
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
                                    <th>Track ID</th>
                                    <th>User</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Changes</th>
                                    <th>Status</th>
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
                                                    $actionText = 'Add';
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

                                            <!-- STATUS -->
                                            <td data-label="Status">
                                                <span class="badge <?php echo (strtolower($log['Status'] ?? '') === 'successful') ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo getStatusIcon($log['Status']) . ' ' . htmlspecialchars($log['Status']); ?>
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
                                        <button id="prevPage"
                                            class="btn btn-outline-primary d-flex align-items-center gap-1">
                                            <i class="bi bi-chevron-left"></i>
                                            Previous
                                        </button>

                                        <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                            <option value="10" selected>10</option>
                                            <option value="20">20</option>
                                            <option value="30">30</option>
                                            <option value="50">50</option>
                                        </select>

                                        <button id="nextPage"
                                            class="btn btn-outline-primary d-flex align-items-center gap-1">
                                            Next
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
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
        const dateFilterTypeSelect = document.getElementById('dateFilterType');
        const allDateFilters = document.querySelectorAll('.date-filter');

        function toggleDateFilters() {
            const selectedType = dateFilterTypeSelect.value;
            allDateFilters.forEach(el => el.classList.add('d-none'));
            if (selectedType) {
                document.querySelectorAll(`.date-filter.date-${selectedType}`).forEach(el => el.classList.remove('d-none'));
            }
        }

        dateFilterTypeSelect.addEventListener('change', toggleDateFilters);
        toggleDateFilters(); // initial call
    });
    </script>

    <!-- Add JavaScript for live filtering -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store all table rows for filtering
            const tableBody = document.getElementById('auditTable');
            const allRows = Array.from(tableBody.querySelectorAll('tr'));
            let filteredRows = [...allRows]; // Start with all rows
            
            // Get filter elements
            const filterForm = document.getElementById('auditFilterForm');
            const filterType = document.getElementById('dateFilterType');
            const allDateFilters = document.querySelectorAll('.date-filter');
            const searchInput = document.getElementById('searchInput');
            const actionTypeFilter = document.getElementById('actionType');
            const statusFilter = document.getElementById('status');
            const applyFiltersBtn = document.getElementById('applyFilters');
            const clearFiltersBtn = document.getElementById('clearFilters');
            
            // Date filter fields
            const dateFromInput = filterForm.querySelector('[name="date_from"]');
            const dateToInput = filterForm.querySelector('[name="date_to"]');
            const yearFromInput = filterForm.querySelector('[name="year_from"]');
            const yearToInput = filterForm.querySelector('[name="year_to"]');
            const monthYearFromInput = filterForm.querySelector('[name="month_year_from"]');
            const monthYearToInput = filterForm.querySelector('[name="month_year_to"]');
            
            // Handle date filter type changes
            function updateDateFields() {
                allDateFilters.forEach(field => field.classList.add('d-none'));
                if (!filterType.value) return;

                const selected = document.querySelectorAll('.date-' + filterType.value);
                selected.forEach(field => field.classList.remove('d-none'));
            }

            filterType.addEventListener('change', function() {
                updateDateFields();
            });
            
            // Initial date fields setup
            updateDateFields();
            
            // Set up event listeners for live filtering
            document.querySelectorAll('.live-filter').forEach(filter => {
                filter.addEventListener('change', applyFilters);
            });
            
            // For search input, use input event with debounce
            if (searchInput) {
                let debounceTimer;
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        applyFilters();
                    }, 300); // 300ms debounce for search
                });
            }
            
            // Apply filters button
            applyFiltersBtn.addEventListener('click', applyFilters);
            
            // Clear filters button
            clearFiltersBtn.addEventListener('click', function() {
                // Reset all form fields
                filterForm.reset();
                
                // Reset date fields visibility
                updateDateFields();
                
                // Show all rows
                filteredRows = [...allRows];
                updateTable();
            });
            
            // Function to apply all filters
            function applyFilters() {
                const searchTerm = searchInput.value.toLowerCase().trim();
                const actionType = actionTypeFilter.value.toLowerCase();
                const status = statusFilter.value.toLowerCase();
                const dateFilterTypeValue = filterType.value;
                
                // Filter the rows
                filteredRows = allRows.filter(row => {
                    // Text search (across all columns)
                    const rowText = row.textContent.toLowerCase();
                    const matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
                    
                    // Action type filter
                    const actionCell = row.querySelector('[data-label="Action"]');
                    const actionText = actionCell ? actionCell.textContent.toLowerCase() : '';
                    const matchesAction = actionType === '' || actionText.includes(actionType);
                    
                    // Status filter
                    const statusCell = row.querySelector('[data-label="Status"]');
                    const statusText = statusCell ? statusCell.textContent.toLowerCase() : '';
                    const matchesStatus = status === '' || statusText.includes(status);
                    
                    // Date filtering
                    let matchesDate = true;
                    
                    if (dateFilterTypeValue) {
                        const dateCell = row.querySelector('[data-label="Date & Time"]');
                        const dateText = dateCell ? dateCell.textContent.trim() : '';
                        
                        if (dateText) {
                            const rowDate = new Date(dateText);
                            
                            switch (dateFilterTypeValue) {
                                case 'mdy':
                                    if (dateFromInput.value) {
                                        const fromDate = new Date(dateFromInput.value);
                                        if (rowDate < fromDate) matchesDate = false;
                                    }
                                    if (dateToInput.value) {
                                        const toDate = new Date(dateToInput.value);
                                        toDate.setHours(23, 59, 59); // End of day
                                        if (rowDate > toDate) matchesDate = false;
                                    }
                                    break;
                                    
                                case 'year':
                                    const rowYear = rowDate.getFullYear();
                                    if (yearFromInput.value && rowYear < parseInt(yearFromInput.value)) {
                                        matchesDate = false;
                                    }
                                    if (yearToInput.value && rowYear > parseInt(yearToInput.value)) {
                                        matchesDate = false;
                                    }
                                    break;
                                    
                                case 'month_year':
                                    const rowYearMonth = rowDate.getFullYear() * 100 + rowDate.getMonth() + 1;
                                    
                                    if (monthYearFromInput.value) {
                                        const [fromYear, fromMonth] = monthYearFromInput.value.split('-').map(Number);
                                        const fromYearMonth = fromYear * 100 + fromMonth;
                                        if (rowYearMonth < fromYearMonth) matchesDate = false;
                                    }
                                    
                                    if (monthYearToInput.value) {
                                        const [toYear, toMonth] = monthYearToInput.value.split('-').map(Number);
                                        const toYearMonth = toYear * 100 + toMonth;
                                        if (rowYearMonth > toYearMonth) matchesDate = false;
                                    }
                                    break;
                            }
                        }
                    }
                    
                    return matchesSearch && matchesAction && matchesStatus && matchesDate;
                });
                
                // Update the table with filtered rows
                updateTable();
            }
            
            // Function to update table with filtered rows
            function updateTable() {
                // Clear the table
                tableBody.innerHTML = '';
                
                // Show no results message if no matches
                if (filteredRows.length === 0) {
                    const noResultsRow = document.createElement('tr');
                    noResultsRow.innerHTML = `
                        <td colspan="8">
                            <div class="empty-state text-center py-4">
                                <i class="fas fa-search fa-3x mb-3"></i>
                                <h4>No matching records found</h4>
                                <p class="text-muted">Try adjusting your search or filter criteria.</p>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(noResultsRow);
                } else {
                    // Add filtered rows to the table
                    filteredRows.forEach(row => {
                        tableBody.appendChild(row.cloneNode(true));
                    });
                }
                
                // Update pagination if it exists
                if (typeof updatePagination === 'function') {
                    // Store filtered rows for pagination
                    window.filteredRows = filteredRows;
                    // Reset to first page
                    if (window.paginationConfig) {
                        window.paginationConfig.currentPage = 1;
                    }
                    updatePagination();
                }
                
                // Update counts display
                const totalRowsElement = document.getElementById('totalRows');
                if (totalRowsElement) {
                    totalRowsElement.textContent = filteredRows.length;
                }
            }
        });
    </script>
</body>

</html>