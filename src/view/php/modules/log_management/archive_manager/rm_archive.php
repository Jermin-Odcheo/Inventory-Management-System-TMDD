<?php
ob_start();
require_once('../../../../../../config/ims-tmdd.php');
session_start();
include '../../../general/header.php';
include '../../../general/sidebar.php';
include '../../../general/footer.php';

// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

// 3) Button flags
$canRestore = $rbac->hasPrivilege('Roles and Privileges', 'Restore');
$canRemove = $rbac->hasPrivilege('Roles and Privileges', 'Remove');
$canPermanentDelete = $rbac->hasPrivilege('Roles and Privileges', 'Permanently Delete');

// --- Sorting Logic ---
$sort_by = $_GET['sort_by'] ?? 'date_time'; // Default sort column
$sort_order = $_GET['sort_order'] ?? 'desc'; // Default sort order

// Whitelist allowed columns to prevent SQL injection
$allowedSortColumns = [
    'role_id' => 'r.id',
    'operator_name' => 'operator_name', // Alias from CONCAT
    'module' => 'a.Module',
    'action' => 'a.Action',
    'status' => 'a.Status',
    'date_time' => 'a.Date_Time'
];

// Validate sort_by and sort_order
if (!array_key_exists($sort_by, $allowedSortColumns)) {
    $sort_by = 'date_time'; // Fallback to default
}
if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
    $sort_order = 'desc'; // Fallback to default
}

// --- Filter Logic ---
$dateFilterType = $_GET['date_filter_type'] ?? '';
$baseWhere = "r.is_disabled = 1 
    AND LOWER(a.Module) = 'roles and privileges'
    AND LOWER(a.Action) IN ('delete', 'remove')
    AND a.Status = 'successful'
    AND a.TrackID = (
        SELECT MAX(a2.TrackID)
        FROM audit_log a2
        WHERE a2.EntityID = a.EntityID 
        AND a2.Module = a.Module
        AND a2.Status = 'successful'
    )";
$params = [];

// Apply search filter
if (!empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    $baseWhere .= " AND (
        r.role_name LIKE :search_role
        OR op.Email LIKE :search_email
        OR a.Details LIKE :search_details
    )";
    $params[':search_role'] = $searchTerm;
    $params[':search_email'] = $searchTerm;
    $params[':search_details'] = $searchTerm;
}

// Apply date filters
if ($dateFilterType === 'mdy') {
    if (!empty($_GET['date_from'])) {
        $baseWhere .= " AND DATE(a.Date_Time) >= :date_from";
        $params[':date_from'] = $_GET['date_from'];
    }
    if (!empty($_GET['date_to'])) {
        $baseWhere .= " AND DATE(a.Date_Time) <= :date_to";
        $params[':date_to'] = $_GET['date_to'];
    }
} else if ($dateFilterType === 'month_year') {
    if (!empty($_GET['month_year_from'])) {
        $baseWhere .= " AND a.Date_Time >= STR_TO_DATE(:month_year_from, '%Y-%m')";
        $params[':month_year_from'] = $_GET['month_year_from'];
    }
    if (!empty($_GET['month_year_to'])) {
        $baseWhere .= " AND a.Date_Time < DATE_ADD(STR_TO_DATE(:month_year_to, '%Y-%m'), INTERVAL 1 MONTH)";
        $params[':month_year_to'] = $_GET['month_year_to'];
    }
} else if ($dateFilterType === 'year') {
    if (!empty($_GET['year_from'])) {
        $baseWhere .= " AND YEAR(a.Date_Time) >= :year_from";
        $params[':year_from'] = $_GET['year_from'];
    }
    if (!empty($_GET['year_to'])) {
        $baseWhere .= " AND YEAR(a.Date_Time) <= :year_to";
        $params[':year_to'] = $_GET['year_to'];
    }
}

$orderByClause = "ORDER BY " . $allowedSortColumns[$sort_by] . " " . strtoupper($sort_order);

// SQL query for archived roles with audit information
$sql = "
    SELECT 
        r.id AS role_id,
        r.role_name,
        r.is_disabled,
        a.TrackID AS last_audit_id,
        a.Action AS last_action,
        a.Status AS action_status,
        a.OldVal AS old_values,
        a.NewVal AS new_values,
        a.Date_Time AS audit_timestamp,
        a.Details AS details,
        a.Module AS module,
        a.Action AS action,
        a.Status AS status,
        a.OldVal AS old_val,
        a.Date_Time AS date_time,
        CONCAT(op.First_Name, ' ', op.Last_Name) AS operator_name,
        op.Email AS operator_email
    FROM roles r
    LEFT JOIN audit_log a ON a.EntityID = r.id AND a.Module = 'Roles and Privileges'
    LEFT JOIN users op ON a.UserID = op.id
    WHERE $baseWhere
    $orderByClause
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enhanced Debug information
error_log("=== Debug Information ===");
error_log("SQL Query: " . $sql);
error_log("Parameters: " . print_r($params, true));
error_log("Number of rows fetched: " . count($roleData));
error_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));

if (empty($roleData)) {
    error_log("No data was fetched from the database");
} else {
    error_log("First row of data: " . print_r($roleData[0], true));
    error_log("Total rows: " . count($roleData));
}

// Debug the table structure
error_log("Table Structure:");
error_log("Table ID: auditTable");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("RBAC privileges: " . print_r([
    'canRestore' => $canRestore,
    'canRemove' => $canRemove,
    'canPermanentDelete' => $canPermanentDelete
], true));

/**
 * Format JSON data into a list (for the 'Changes' column).
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
 * Helper function to return an icon based on action.
 */
function getActionIcon($action)
{
    $action = strtolower($action);
    if ($action === 'modified') {
        return '<i class="fas fa-user-edit"></i>';
    } elseif ($action === 'add') {
        return '<i class="fas fa-user-plus"></i>';
    } elseif ($action === 'delete' || $action === 'remove') {
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
 * Format data to display old values (for the 'Changes' column).
 */
function formatChanges($oldJsonStr)
{
    $oldData = json_decode($oldJsonStr, true);

    // If not an array, simply return the value
    if (!is_array($oldData)) {
        return '<span>' . htmlspecialchars($oldJsonStr) . '</span>';
    }

    $html = '<ul class="list-group">';
    foreach ($oldData as $key => $value) {
        // Special handling for modules_and_privileges which is a nested array
        if ($key === 'modules_and_privileges' && is_array($value)) {
            $html .= '<li class="list-group-item">
                        <strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong>
                        <ul class="list-group mt-2">';

            foreach ($value as $module => $privileges) {
                // Join privileges array into a comma-separated string
                $privilegesStr = is_array($privileges) ? implode(', ', $privileges) : $privileges;

                $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>' . htmlspecialchars($module) . ':</strong>
                            <span class="old-value text-danger">
                                <i class="fas fa-history me-1"></i> ' . htmlspecialchars($privilegesStr) . '
                            </span>
                          </li>';
            }

            $html .= '</ul></li>';
        } else {
            // Format the value
            if (is_null($value)) {
                $displayValue = '<em>null</em>';
            } elseif (is_array($value)) {
                $displayValue = htmlspecialchars(json_encode($value));
            } else {
                $displayValue = htmlspecialchars((string)$value);
            }

            $friendlyKey = ucwords(str_replace('_', ' ', $key));

            $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong>' . $friendlyKey . ':</strong>
                        <span class="old-value text-danger">
                            <i class="fas fa-history me-1"></i> ' . $displayValue . '
                        </span>
                      </li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <meta charset="UTF-8">
    <title>Roles Archives</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" src="<?php echo BASE_URL; ?> /src/view/styles/css/audit_log.css">
    <style>
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 300px;
            padding-top: 120px;
        }

        #tableContainer {
            width: 100%;
        }

        /* Action badges */
        .action-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .action-badge i {
            margin-right: 0.5rem;
        }

        .action-modified {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .action-add {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .action-delete, .action-remove {
            background-color: #ffebee;
            color: #c62828;
        }

        /* Styles for sortable headers */
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 25px;
        }

        th.sortable .fas {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
            transition: color 0.2s ease;
        }

        th.sortable:hover .fas {
            color: #888;
        }

        th.sortable.asc .fas.fa-sort-up,
        th.sortable.desc .fas.fa-sort-down {
            color: #333;
        }

        th.sortable.asc .fas.fa-sort,
        th.sortable.desc .fas.fa-sort {
            display: none;
        }

        /* Pagination styles */
        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-link {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            color: #6c757d;
            background-color: #fff;
            border: 1px solid #dee2e6;
        }

        .pagination .page-link:hover {
            color: #0056b3;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .pagination .page-item.active .page-link {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        #rowsPerPage {
            width: auto;
            min-width: 80px;
        }

        .form-select-sm {
            padding: 0.25rem 2rem 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        #tableInfo {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="main-content container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                    <h3 class="text-white">
                        <i class="fas fa-archive me-2"></i>
                        Roles Archives
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="bulk-actions mb-3">
                                <?php if ($canRestore): ?>
                                    <button type="button" id="bulk-restore" class="btn btn-success" style="display: none;">
                                        <i class="fas fa-undo me-1"></i> Restore Selected
                                    </button>
                                <?php endif; ?>
                                <?php if ($canRemove): ?>
                                    <button type="button" id="bulk-delete" class="btn btn-danger" style="display: none;">
                                        <i class="fas fa-trash me-1"></i> Delete Selected Permanently
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Section -->
                    <form method="GET" class="row g-3 mb-4" id="archiveFilterForm" action="">

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
                                    placeholder="Search role archives..."
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-6 col-md-2 d-grid">
                            <button type="submit" id="applyFilters" class="btn btn-dark">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>

                        <div class="col-6 col-md-2 d-grid">
                            <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                                <i class="fas fa-times-circle"></i> Clear
                            </button>
                        </div>
                    </form>

                    <div class="table-responsive" id="tableContainer">
                        <table id="archivedRolesTable" class="table table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <!-- <th><input type="checkbox" id="select-all"></th> -->
                                    <th class="sortable" data-sort-by="role_id" style="width: 25px;">ID <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort-by="operator_name">User<i class="fas fa-sort"></i></th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Changes</th>
                                    <th class="sortable" data-sort-by="date_time">Date &amp; Time <i class="fas fa-sort"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="auditTable">
                                <?php if (!empty($roleData)): ?>
                                    <?php 
                                    // Debug output of first row keys
                                    if (isset($roleData[0])) {
                                        error_log("Available columns in data: " . implode(", ", array_keys($roleData[0])));
                                    }
                                    foreach ($roleData as $role): ?>
                                        <tr data-role-id="<?php echo $role['role_id']; ?>">
                                            <!-- <td>
                                                <input type="checkbox" class="select-row" value="<?php echo $role['role_id']; ?>">
                                            </td> -->
                                            <td><?php echo htmlspecialchars($role['role_id']); ?></td>
                                            <td class="user">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <small><?php echo htmlspecialchars($role['operator_email'] ?? 'Unknown'); ?></small>
                                                </div>
                                            </td>
                                            <td class="action">
                                                <?php
                                                $actionText = !empty($role['action']) ? $role['action'] : 'Unknown';
                                                echo '<span class="action-badge action-' . strtolower($actionText) . '">';
                                                echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                                echo '</span>';
                                                ?>
                                            </td>
                                            <td class="details">
                                                <?php echo nl2br(htmlspecialchars($role['details'] ?? '')); ?>
                                            </td>
                                            <td class="changes">
                                                <?php echo formatChanges($role['old_val'] ?? ''); ?>
                                            </td>
                                            <td class="date-time">
                                                <div class="d-flex align-items-center">
                                                    <i class="far fa-clock me-2"></i>
                                                    <?php echo htmlspecialchars($role['date_time'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="actions">
                                                <div class="btn-group-vertical gap-1">
                                                    <?php if ($canRestore): ?>
                                                        <button type="button" class="btn btn-success restore-btn"
                                                            data-role-id="<?php echo $role['role_id']; ?>"
                                                            data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#confirmRestoreModal">
                                                            <i class="fas fa-undo me-1"></i> Restore
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($canRemove): ?>
                                                        <button type="button" class="btn btn-danger delete-btn"
                                                            data-role-id="<?php echo $role['role_id']; ?>"
                                                            data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                            data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                                            <i class="fas fa-trash me-1"></i> Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10">
                                            <div class="empty-state text-center py-4">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <h4>No Archived Roles Found</h4>
                                                <p class="text-muted">There are no archived roles to display.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination Controls -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="d-flex align-items-center">
                                <label for="rowsPerPage" class="me-2">Show entries:</label>
                                <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="30">30</option>
                                    <option value="40">40</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center">
                                <span id="tableInfo" class="me-3"></span>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item" id="prevPage">
                                            <a class="page-link" href="#" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <li class="page-item" id="nextPage">
                                            <a class="page-link" href="#" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/logs.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/archive_filters.js" defer></script>
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/sort_archives.js" defer></script>

    <div class="modal fade" id="confirmRestoreModal" tabindex="-1" aria-labelledby="confirmRestoreModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to restore the role "<span id="restoreRoleNamePlaceholder"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmRestoreBtn" class="btn btn-success">Restore</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Permanent Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">Warning: This action cannot be undone!</p>
                    Are you sure you want to permanently delete the role "<span id="roleNamePlaceholder"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete Permanently</a>
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
                    Are you sure you want to restore the selected roles?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmBulkRestoreBtn" class="btn btn-success">Restore</button>
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
                    <p class="text-danger fw-bold">Warning: This action cannot be undone!</p>
                    Are you sure you want to permanently delete the selected roles?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmBulkDeleteBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('archivedRolesTable');
            const tbody = document.getElementById('auditTable');
            const rowsPerPageSelect = document.getElementById('rowsPerPage');
            const prevPageBtn = document.getElementById('prevPage');
            const nextPageBtn = document.getElementById('nextPage');
            const tableInfo = document.getElementById('tableInfo');
            
            let currentPage = 1;
            let rowsPerPage = parseInt(rowsPerPageSelect.value);
            let allRows = Array.from(tbody.getElementsByTagName('tr'));
            let filteredRows = [...allRows];
            
            function updateTable() {
                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                const paginatedRows = filteredRows.slice(start, end);
                
                // Clear current table content
                tbody.innerHTML = '';
                
                // Add paginated rows
                paginatedRows.forEach(row => tbody.appendChild(row.cloneNode(true)));
                
                // Update table info
                const totalRows = filteredRows.length;
                const startRow = totalRows === 0 ? 0 : start + 1;
                const endRow = Math.min(end, totalRows);
                tableInfo.textContent = `Showing ${startRow} to ${endRow} of ${totalRows} entries`;
                
                // Update button states
                prevPageBtn.classList.toggle('disabled', currentPage === 1);
                nextPageBtn.classList.toggle('disabled', end >= totalRows);
            }
            
            // Event listeners
            rowsPerPageSelect.addEventListener('change', function() {
                rowsPerPage = parseInt(this.value);
                currentPage = 1;
                updateTable();
            });
            
            prevPageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (currentPage > 1) {
                    currentPage--;
                    updateTable();
                }
            });
            
            nextPageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const maxPage = Math.ceil(filteredRows.length / rowsPerPage);
                if (currentPage < maxPage) {
                    currentPage++;
                    updateTable();
                }
            });
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    filteredRows = allRows.filter(row => {
                        const text = row.textContent.toLowerCase();
                        return text.includes(searchTerm);
                    });
                    currentPage = 1;
                    updateTable();
                });
            }
            
            // Initial table update
            updateTable();
        });
    </script>
</body>

</html>