<?php
session_start();
require '../../../../../config/ims-tmdd.php';

// Include Header
include '../../general/header.php';

//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php"); // Redirect to login page
    exit();
}

// Fetch only equipment management related audit logs
$query = "SELECT audit_log.*, users.email AS user_email 
          FROM audit_log 
          LEFT JOIN users ON audit_log.UserID = users.id
          WHERE audit_log.Module LIKE 'Equipment%'
          ORDER BY audit_log.Date_Time DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
 * Helper function to return an icon based on action.
 */
function getActionIcon($action)
{
    $action = strtolower($action);
    if ($action === 'modified') {
        return '<i class="fas fa-user-edit"></i>';
    } elseif ($action === 'add') {
        return '<i class="fas fa-user-plus"></i>';
    } elseif ($action === 'soft delete' || $action === 'permanent delete') {
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

    // Use user_email from the log if available, or fallback to newData email
    $userEmail = $log['user_email'] ?? ($newData['Email'] ?? 'User');

    // For soft delete, try to use the target's name (if available)
    $targetName = $userEmail;
    if ($action === 'soft delete') {
        if (isset($newData['First_Name'], $newData['Last_Name'])) {
            $targetName = $newData['First_Name'] . ' ' . $newData['Last_Name'];
        }
    }

    // Prepare default strings
    $details = '';
    $changes = '';

    switch ($action) {
        case 'add':
            $details = htmlspecialchars("$userEmail has been created");
            $changes = formatNewValue($log['NewVal']);
            break;

        case 'modified':
            $changedFields = getChangedFieldNames($oldData, $newData);
            if (!empty($changedFields)) {
                $details = "Updated Fields: " . htmlspecialchars(implode(', ', $changedFields));
            } else {
                $details = "Updated Fields: None";
            }
            $changes = formatAuditDiff($log['OldVal'], $log['NewVal']);
            break;

        case 'restored':
            $details = htmlspecialchars("$userEmail has been restored");
            $changes = "is_deleted 1 -> 0";
            break;

        case 'soft delete':
            // Use the target's name instead of a generic message
            $details = htmlspecialchars("$userEmail has been soft deleted");
            $changes = "is_deleted 0 -> 1";
            break;

        case 'permanent delete':
            $details = htmlspecialchars("$userEmail has been deleted from the database");
            $changes = formatNewValue($log['NewVal']);
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
    $changed = [];
    // We combine the keys
    $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
    foreach ($allKeys as $key) {
        $oldVal = $oldData[$key] ?? null;
        $newVal = $newData[$key] ?? null;
        if ($oldVal !== $newVal) {
            $changed[] = ucwords(str_replace('_', ' ', $key));
        }
    }
    return $changed;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Equipment Management Audit Logs</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/Inventory-Managment-System-TMDD/src/view/styles/css/audit_log.css">
</head>
<body>
<?php include '../../general/sidebar.php'; ?>

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
                <!-- Filter Section -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control"
                                   placeholder="Search audit logs...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterAction" class="form-select">
                            <option value="">All Actions</option>
                            <option value="add">Add</option>
                            <option value="modified">Modified</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterModule" class="form-select">
                            <option value="">All Modules</option>
                            <option value="Equipment Details">Equipment Details</option>
                            <option value="Equipment Location">Equipment Location</option>
                            <option value="Equipment Status">Equipment Status</option>
                        </select>
                    </div>
                </div>

                <!-- Table container -->
                <div class="table-responsive">
                    <table class="table table-hover" id="table">
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
                            <th>#</th>
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
                                <tr>
                                    <!-- Keep the same row structure but data will be equipment-specific -->
                                    <td data-label="Track ID">
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($log['TrackID']); ?>
                                        </span>
                                    </td>
                                    <td data-label="User">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2"></i>
                                            <?php echo htmlspecialchars($log['user_email'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td data-label="Module">
                                        <?php echo !empty($log['Module']) ? htmlspecialchars(trim($log['Module'])) : '<em class="text-muted">N/A</em>'; ?>
                                    </td>
                                    <td data-label="Action">
                                        <?php
                                        $actionText = !empty($log['Action']) ? $log['Action'] : 'Unknown';
                                        echo "<span class='action-badge action-" . strtolower($actionText) . "'>";
                                        echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                        echo "</span>";
                                        ?>
                                    </td>
                                    <?php
                                    list($detailsHTML, $changesHTML) = formatDetailsAndChanges($log);
                                    ?>
                                    <td data-label="Details" class="data-container">
                                        <?php echo nl2br($detailsHTML); ?>
                                    </td>
                                    <td data-label="Changes" class="data-container">
                                        <?php echo nl2br($changesHTML); ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge <?php echo (strtolower($log['Status'] ?? '') === 'successful') ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo getStatusIcon($log['Status']) . ' ' . htmlspecialchars($log['Status']); ?>
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
                                        <h4>No Equipment Audit Logs Found</h4>
                                        <p class="text-muted">There are no equipment management audit log entries to display.</p>
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
                        <!-- Pagination Info -->
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span
                                        id="totalRows">100</span> entries
                            </div>
                        </div>

                        <!-- Pagination Controls -->
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i>
                                    Previous
                                </button>

                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>

                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    Next
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/logs.js" defer></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
</body>
</html>

