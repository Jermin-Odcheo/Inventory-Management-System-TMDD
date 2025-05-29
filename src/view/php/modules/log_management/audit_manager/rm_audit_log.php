<?php
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';
include '../../../general/sidebar.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Initialize RBAC and check permissions
$rbac = new RBACService($pdo, $_SESSION['user_id']);

// Check for required privilege
$hasAuditPermission = $rbac->hasPrivilege('Audit', 'Track');
$hasRMPermission = $rbac->hasPrivilege('Roles and Privileges', 'Track');

// If user doesn't have permission, show an inline "no permission" page
if (!$hasAuditPermission && !$hasRMPermission) {
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

// Fetch all audit logs for Roles and Privileges module
$query = "SELECT audit_log.*, users.Email AS email 
          FROM audit_log 
          LEFT JOIN users ON audit_log.UserID = users.id
          WHERE audit_log.Module = 'Roles and Privileges'
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
    
    // Display role name if present
    if (isset($data['role_name'])) {
        $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Role Name:</strong>
                    <span>' . htmlspecialchars($data['role_name']) . '</span>
                 </li>';
    }
    
    // Display role ID if present
    if (isset($data['role_id'])) {
        $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>Role ID:</strong>
                    <span>' . htmlspecialchars($data['role_id']) . '</span>
                 </li>';
    }
    
    // Special handling for modules_and_privileges
    if (isset($data['modules_and_privileges']) && is_array($data['modules_and_privileges'])) {
        $html .= '<li class="list-group-item">
                    <strong>Modules and Privileges:</strong>
                    <ul class="list-group mt-2">';
        
        foreach ($data['modules_and_privileges'] as $module => $privileges) {
            $privilegeStr = is_array($privileges) ? implode(', ', $privileges) : (string)$privileges;
            $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>' . htmlspecialchars($module) . ':</strong>
                        <span>' . htmlspecialchars($privilegeStr) . '</span>
                     </li>';
        }
        
        $html .= '</ul></li>';
    }
    
    $html .= '</ul>';
    return $html;
}

/**
 * Compares two JSON strings and shows a diff of changes.
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

    $descriptions = [];

    // Compare role name if it exists
    if (isset($oldData['role_name'], $newData['role_name']) && 
        $oldData['role_name'] !== $newData['role_name']) {
        $descriptions[] = "Role name changed from '<em>" . htmlspecialchars($oldData['role_name']) . 
                         "</em>' to '<strong>" . htmlspecialchars($newData['role_name']) . "</strong>'.";
    }

    // Compare privileges (per module)
    $oldPrivileges = $oldData['privileges'] ?? [];
    $newPrivileges = $newData['privileges'] ?? [];

    $allModules = array_unique(array_merge(
        array_keys($oldPrivileges),
        array_keys($newPrivileges)
    ));

    foreach ($allModules as $module) {
        $oldPrivs = $oldPrivileges[$module] ?? [];
        $newPrivs = $newPrivileges[$module] ?? [];

        // Normalize arrays to avoid issues if they are associative
        $oldPrivs = array_values($oldPrivs);
        $newPrivs = array_values($newPrivs);

        // Added and removed privileges
        $added = array_diff($newPrivs, $oldPrivs);
        $removed = array_diff($oldPrivs, $newPrivs);

        if (!empty($added)) {
            $descriptions[] = "Module <strong>" . htmlspecialchars($module) . "</strong>: Added privileges → " . 
                              "<strong>" . htmlspecialchars(implode(', ', $added)) . "</strong>";
        }
        if (!empty($removed)) {
            $descriptions[] = "Module <strong>" . htmlspecialchars($module) . "</strong>: Removed privileges → " . 
                              "<em>" . htmlspecialchars(implode(', ', $removed)) . "</em>";
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
        'remove'   => '<i class="fas fa-trash-alt"></i>',
        'delete'   => '<i class="fas fa-trash"></i>',
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
 * Returns an array with formatted Details and Changes columns for roles.
 */
function formatDetailsAndChanges($log)
{
    $action = strtolower($log['Action'] ?? '');
    $oldData = ($log['OldVal'] !== null) ? json_decode($log['OldVal'], true) : [];
    $newData = ($log['NewVal'] !== null) ? json_decode($log['NewVal'], true) : [];

    // Extract role name from the data
    $roleName = '';
    if (isset($newData['role_name'])) {
        $roleName = $newData['role_name'];
    } elseif (isset($oldData['role_name'])) {
        $roleName = $oldData['role_name'];
    }

    $details = '';
    $changes = '';

    switch ($action) {
        case 'create':
            $defaultMessage = htmlspecialchars("Role '$roleName' has been created");
            list($details, $changes) = processStatusMessage($defaultMessage, $log, function () use ($log) {
                return formatNewValue($log['NewVal']);
            });
            break;

        case 'modified':
            $defaultMessage = htmlspecialchars("Role '$roleName' has been modified");
            list($details, $changes) = processStatusMessage($defaultMessage, $log, function () use ($log) {
                return formatAuditDiff($log['OldVal'], $log['NewVal'], $log['Status']);
            });
            break;

        case 'remove':
            $defaultMessage = htmlspecialchars("Role '$roleName' has been archived");
            list($details, $changes) = processStatusMessage($defaultMessage, $log, function () use ($log) {
                return formatNewValue($log['OldVal']);
            });
            break;

        case 'restore':
        case 'restored':
            $defaultMessage = htmlspecialchars("Role '$roleName' has been restored");
            list($details, $changes) = processStatusMessage($defaultMessage, $log, function () use ($log) {
                // Use the same format as remove action
                return formatNewValue($log['NewVal']);
            });
            break;

            case 'delete':
                // build your "permanently deleted" message
                $defaultMessage = htmlspecialchars("Role '{$roleName}' has been permanently deleted");
            
                // details column is just the message
                $details = $defaultMessage;
            
                // changes column is the fully-decoded OldVal JSON (role_name, role_id, modules_and_privileges)
                $changes = formatNewValue($log['OldVal']);
            
                break;

        default:
            // Use the details from the log or generate a default message
            $details = !empty($log['Details']) ? nl2br(htmlspecialchars($log['Details'])) : 'Action performed on role';
            
            // Format the old and new values based on what's available
            if (!empty($log['NewVal'])) {
                $changes = formatNewValue($log['NewVal']);
            } elseif (!empty($log['OldVal'])) {
                $changes = formatNewValue($log['OldVal']);
            } else {
                $changes = '<em class="text-muted">No data available</em>';
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
    
    // Map actions to their display names
    $actionMap = [
        'create' => 'create',
        'modified' => 'modified',
        'remove' => 'remove',
        'restore' => 'restored',
        'restored' => 'restored',
        'delete' => 'delete'
    ];
    
    return $actionMap[$action] ?? $action;
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
    <title>Role Management Audit Logs</title>
</head>
<body>


<div class="main-content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                <h3 class="text-white">
                    <i class="fas fa-history me-2"></i>
                    Role Management Audit Logs
                </h3>
            </div>

            <div class="card-body">
                <!-- Permission Info Banner -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-shield-alt me-2"></i>
                    You have permission to view Roles and Privileges audit logs.
                </div>
                
                <!-- Filter Section -->
                <div class="row mb-4">
                    <form method="GET" class="row g-3 mb-4" id="auditFilterForm" onsubmit="return false;">
                        <div class="col-md-4 mb-2">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="searchInput" class="form-control live-filter" placeholder="Search audit logs...">
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <select id="actionType" class="form-select live-filter">
                                <option value="">All Actions</option>
                                <option value="create">Create</option>
                                <option value="modified">Modified</option>
                                <option value="remove">Remove</option>
                                <option value="delete">Delete</option>
                                <option value="restored">Restored</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <select id="status" class="form-select live-filter">
                                <option value="">All Status</option>
                                <option value="successful">Successful</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mt-2">
                            <button type="button" id="applyFilters" class="btn btn-dark"><i class="bi bi-funnel"></i> Apply Filters</button>
                            <button type="button" id="clearFilters" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Clear Filters</button>
                        </div>
                    </form>
                </div>
                
                <!-- Add JavaScript for live filtering -->
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Store all table rows for filtering
                        const tableBody = document.getElementById('auditTable');
                        const allRows = Array.from(tableBody.querySelectorAll('tr'));
                        let filteredRows = [...allRows]; // Start with all rows
                        
                        // Get filter elements
                        const filterForm = document.getElementById('auditFilterForm');
                        const searchInput = document.getElementById('searchInput');
                        const actionTypeFilter = document.getElementById('actionType');
                        const statusFilter = document.getElementById('status');
                        const applyFiltersBtn = document.getElementById('applyFilters');
                        const clearFiltersBtn = document.getElementById('clearFilters');
                        
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
                            
                            // Show all rows
                            filteredRows = [...allRows];
                            updateTable();
                        });
                        
                        // Function to apply all filters
                        function applyFilters() {
                            const searchTerm = searchInput.value.toLowerCase().trim();
                            const actionType = actionTypeFilter.value.toLowerCase();
                            const status = statusFilter.value.toLowerCase();
                            
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
                                
                                return matchesSearch && matchesAction && matchesStatus;
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
                                        <?php echo $detailsHTML; ?>
                                    </td>

                                    <!-- CHANGES -->
                                    <td data-label="Changes" class="data-container">
                                        <?php echo $changesHTML; ?>
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
                                        <h4>No Role Management Audit Logs Found</h4>
                                        <p class="text-muted">There are no role management audit log entries to display.</p>
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
