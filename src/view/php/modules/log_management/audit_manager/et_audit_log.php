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
$hasAuditPermission = $rbac->hasPrivilege('Equipment Transactions', 'Track');

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
    audit_log.Status AS status,  
    users.email  AS email
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
        return '<span class="action-badge action-modified"><i class="fas fa-pen me-1"></i> Modified</span>';
    } elseif ($action === 'create') {
        return '<span class="action-badge action-create"><i class="fas fa-plus-circle me-1"></i> Create</span>';
    } elseif ($action === 'remove') {
        return '<span class="action-badge action-delete"><i class="fas fa-trash-alt me-1"></i> Removed</span>';
    } elseif ($action === 'delete' || $action === 'permanent delete') {
        return '<span class="action-badge action-delete"><i class="fas fa-trash-alt me-1"></i> Permanently Deleted</span>';
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
            $changes = "is_deleted 1 -> 0";
            break;
        case 'remove':
            $details = htmlspecialchars("$targetEntityName has been removed");
            $changes = "is_deleted 0 -> 1";
            break;
        case 'delete':
        case 'permanent delete':
            $details = htmlspecialchars("$targetEntityName has been permanently deleted");
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
                                <option value="create">Create</option>
                                <option value="modified">Modified</option>
                                <option value="remove">Removed</option>
                                <option value="add">Add</option>
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
                                                $status = $log['status'] ?? '';
                                                $isSuccess = (strtolower($status) === 'successful');
                                                $statusText = $isSuccess ? 'Successful' : 'Failed';
                                                ?>
                                                <span class="badge <?= $isSuccess ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= getStatusIcon($status) . ' ' . htmlspecialchars($statusText) ?>
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

</body>

</html>