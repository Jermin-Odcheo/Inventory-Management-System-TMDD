<?php
session_start();
require '../../../../../config/ims-tmdd.php';

// Include Header
include '../../general/header.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php");
    exit();
}

// Fetch all audit logs (including permanent deletes)
$query = "SELECT audit_log.*, users.email AS email 
          FROM audit_log 
          LEFT JOIN users ON audit_log.UserID = users.id
          WHERE audit_log.Module = 'User Management'
          ORDER BY audit_log.TrackID DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Formats a JSON string into an HTML list.
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
 * Compares two JSON strings and shows a diff of changes (ignoring is_deleted and password differences).
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
        'modified' => '<i class="fas fa-user-edit"></i>',
        'create'   => '<i class="fas fa-user-plus"></i>',
        'remove'   => '<i class="fas fa-user-slash"></i>',
        'delete'   => '<i class="fas fa-user-slash"></i>',
        'restored' => '<i class="fas fa-undo"></i>'
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
 * Processes error messages when the log status is failed.
 * Returns an array with [details, changes].
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
 * Returns an array with formatted Details and Changes columns.
 */
function formatDetailsAndChanges($log)
{
    $action = strtolower($log['Action'] ?? '');
    $oldData = ($log['OldVal'] !== null) ? json_decode($log['OldVal'], true) : [];
    $newData = ($log['NewVal'] !== null) ? json_decode($log['NewVal'], true) : [];

    $userEmail = $log['user_email'] ?? ($newData['email'] ?? 'User');
    $targetEntityName = $newData['email'] ?? $oldData['email'] ?? 'Unknown';
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
            $changedFields = getChangedFieldNames($oldData, $newData);
            $defaultMessage = !empty($changedFields)
                ? "Modified Fields: " . htmlspecialchars(implode(', ', $changedFields))
                : "Modified Fields: None";
            list($details, $changes) = processStatusMessage($defaultMessage, $log, function () use ($log) {
                return formatNewValue($log['NewVal']);
            });
            break;

        case 'restored':
            $details = htmlspecialchars("$targetEntityName has been restored");
            $changes = "is_deleted 1 -> 0";
            break;

        case 'remove':
            $details = htmlspecialchars("$targetName has been removed");
            $changes = "is_deleted 0 -> 1";
            break;

        case 'delete':
            $details = htmlspecialchars("$targetEntityName has been deleted from the database");
            $changes = formatNewValue($log['OldVal']);
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
 * Returns a list of changed field names.
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
 * Normalizes the action for display.
 * If the JSON values indicate a restore, it returns 'restored'.
 */
function getNormalizedAction($log)
{
    $action = strtolower($log['Action'] ?? '');
    if (!is_null($log['OldVal']) && !is_null($log['NewVal'])) {
        $oldData = json_decode($log['OldVal'], true);
        $newData = json_decode($log['NewVal'], true);
        if (is_array($oldData) && is_array($newData) &&
            isset($oldData['is_deleted'], $newData['is_deleted']) &&
            (int)$oldData['is_deleted'] === 1 && (int)$newData['is_deleted'] === 0) {
            return 'restored';
        }
    }
    return $action;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preload"  href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css" as="style"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    </noscript>
    <meta charset="UTF-8">
    <title>Audit Logs Dashboard</title>

</head>
<body>
<?php include '../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                <h3 class="text-white">
                    <i class="fas fa-history me-2"></i>
                    Audit Logs Dashboard
                </h3>
            </div>

            <div class="card-body">
                <!-- Filter Section -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search audit logs...">
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterAction" class="form-select">
                            <option value="">All Actions</option>
                            <option value="create">Create</option>
                            <option value="modified">Modified</option>
                            <option value="remove">Remove</option>
                            <option value="delete">Delete</option>
                            <option value="restored">Restored</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <select id="filterStatus" class="form-select">
                            <option value="">All Status</option>
                            <option value="successful">Successful</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

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
                            <div class="col-12 col-sm-auto">
                                <div class="text-muted">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows">100</span> entries
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
                    </div> <!-- /.Pagination -->
                </div><!-- /.table-responsive -->
            </div><!-- /.card-body -->
        </div><!-- /.card -->
    </div><!-- /.container-fluid -->
</div><!-- /.main-content -->

<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/logs.js" defer></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
</body>
</html>
