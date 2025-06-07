<?php

/**
 * @file rm_archive.php
 * @brief handles the display of archived roles and their audit logs
 *
 * This script handles the display of archived roles and their audit logs. It checks user permissions,
 * fetches and filters archived data based on various criteria, and formats the data for presentation in a user interface.
 */
ob_start();
require_once('../../../../../../config/ims-tmdd.php');
session_start();
include '../../../general/header.php';
include '../../../general/sidebar.php';

// Add rm-archive class to body
echo '<script>document.body.classList.add("rm-archive");</script>';

// 1) Auth guard
/**
 * @var int|null $userId The user ID of the logged-in user.
 */
$userId = $_SESSION['user_id'] ?? null;
/**
 * If the user is not logged in, they are redirected to the login page.
 */
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}
/**
 * @var int $userId The user ID of the logged-in user.
 */
$userId = (int)$userId;

/**
 * @var RBACService $rbac The RBAC service instance.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
/**
 * @var bool $canView The flag indicating if the user can view roles.
 * @var bool $canRestore The flag indicating if the user can restore roles.
 * @var bool $canRemove The flag indicating if the user can remove roles.
 * @var bool $canPermanentDelete The flag indicating if the user can permanently delete roles.
 */
$rbac->requirePrivilege('Roles and Privileges', 'View');
$canRestore = $rbac->hasPrivilege('Roles and Privileges', 'Restore');
$canRemove = $rbac->hasPrivilege('Roles and Privileges', 'Remove');
$canPermanentDelete = $rbac->hasPrivilege('Roles and Privileges', 'Permanently Delete');

// --- Sorting Logic ---
/**
 * @var string $sort_by The column to sort the data by.
 * @var string $sort_order The order to sort the data by.
 */
$sort_by = $_GET['sort_by'] ?? 'date_time'; // Default sort column
$sort_order = $_GET['sort_order'] ?? 'desc'; // Default sort order

// Whitelist allowed columns to prevent SQL injection
/**
 * @var array $allowedSortColumns The allowed columns to sort the data by.
 */
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
/**
 * @var string $dateFilterType The type of date filter to apply.
 * @var string $baseWhere The base SQL WHERE clause.
 * @var array $params The parameters for the SQL query.
 */
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

// Initialize error message array
$errorMessages = [];

/**
 * @var string $searchTerm The search term to apply to the SQL query.
 */
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

/**
 * @var string $dateFilterType The type of date filter to apply.
 * @var string $baseWhere The base SQL WHERE clause.
 * @var array $params The parameters for the SQL query.
 */
if ($dateFilterType === 'mdy') {
    $dateFrom = !empty($_GET['date_from']) ? $_GET['date_from'] : null;
    $dateTo = !empty($_GET['date_to']) ? $_GET['date_to'] : null;

    // Server-side date validation
    if ($dateFrom && $dateTo && strtotime($dateFrom) > strtotime($dateTo)) {
        $errorMessages[] = '"Date From" cannot be greater than "Date To"';
    } else {
        if (!empty($dateFrom)) {
            $baseWhere .= " AND DATE(a.Date_Time) >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $baseWhere .= " AND DATE(a.Date_Time) <= :date_to";
            $params[':date_to'] = $dateTo;
        }
    }
} else if ($dateFilterType === 'month_year') {
    $monthYearFrom = !empty($_GET['month_year_from']) ? $_GET['month_year_from'] : null;
    $monthYearTo = !empty($_GET['month_year_to']) ? $_GET['month_year_to'] : null;

    // Server-side month-year validation
    if ($monthYearFrom && $monthYearTo && strtotime($monthYearFrom . '-01') > strtotime($monthYearTo . '-01')) {
        $errorMessages[] = '"From (MM-YYYY)" cannot be greater than "To (MM-YYYY)"';
    } else {
        if (!empty($monthYearFrom)) {
            $baseWhere .= " AND a.Date_Time >= STR_TO_DATE(:month_year_from, '%Y-%m')";
            $params[':month_year_from'] = $monthYearFrom;
        }
        if (!empty($monthYearTo)) {
            $baseWhere .= " AND a.Date_Time < DATE_ADD(STR_TO_DATE(:month_year_to, '%Y-%m'), INTERVAL 1 MONTH)";
            $params[':month_year_to'] = $monthYearTo;
        }
    }
} else if ($dateFilterType === 'year') {
    $yearFrom = !empty($_GET['year_from']) ? (int)$_GET['year_from'] : null;
    $yearTo = !empty($_GET['year_to']) ? (int)$_GET['year_to'] : null;

    // Server-side year validation
    if ($yearFrom && $yearTo && $yearFrom > $yearTo) {
        $errorMessages[] = '"Year From" cannot be greater than "Year To"';
    } else {
        if (!empty($yearFrom)) {
            $baseWhere .= " AND YEAR(a.Date_Time) >= :year_from";
            $params[':year_from'] = $yearFrom;
        }
        if (!empty($yearTo)) {
            $baseWhere .= " AND YEAR(a.Date_Time) <= :year_to";
            $params[':year_to'] = $yearTo;
        }
    }
}

$orderByClause = "ORDER BY " . $allowedSortColumns[$sort_by] . " " . strtoupper($sort_order);

/**
 * @var string $sql The SQL query for archived roles with audit information.
 */
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

/**
 * @var PDOStatement $stmt The prepared statement for the SQL query.
 */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
/**
 * @var array $roleData The data from the SQL query.
 */
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Format JSON data into a list for display in the 'Changes' column.
 *
 * @param string $jsonStr The JSON string containing the data to format.
 * @return string HTML formatted list of key-value pairs from the JSON data.
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
 * Helper function to return an icon based on the action type.
 *
 * @param string $action The action type to get an icon for.
 * @return string HTML string containing the FontAwesome icon for the action.
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
 * Helper function to return a status icon based on the status value.
 *
 * @param string $status The status of the action (e.g., 'successful').
 * @return string HTML string containing the FontAwesome icon for the status.
 */
function getStatusIcon($status)
{
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
}

/**
 * Format JSON data to display old values only in the 'Changes' column.
 *
 * This function processes the old values from a JSON string and formats them into an HTML list
 * for display purposes, highlighting changes made to room data.
 *
 * @param string $oldJsonStr The JSON string containing the old values to format.
 * @return string HTML formatted list of old values.
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <meta charset="UTF-8">
    <title>Roles Archives</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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
            max-height: 500px;
            overflow-y: auto;
        }

        #dateInputsContainer {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Styles for sortable headers */
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 25px;
            /* Make space for the icon */
        }

        th.sortable .fas {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
            /* Default icon color */
            transition: color 0.2s ease;
        }

        th.sortable:hover .fas {
            color: #888;
            /* Hover color */
        }

        th.sortable.asc .fas.fa-sort-up,
        th.sortable.desc .fas.fa-sort-down {
            color: #333;
            /* Active icon color */
        }

        th.sortable.asc .fas.fa-sort,
        th.sortable.desc .fas.fa-sort {
            display: none;
            /* Hide generic sort icon when specific order is applied */
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

                    <!-- Display validation errors if any -->
                    <?php if (!empty($errorMessages)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong><i class="fas fa-exclamation-triangle me-2"></i></strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errorMessages as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filter Section -->
                    <form method="GET" class="row g-3 mb-4" id="archiveFilterForm" action="">

                        <!-- Date Filter Row with flex layout -->
                        <div class="col-12 d-flex flex-wrap align-items-end gap-3 mb-3">
                            <!-- Date Range selector -->
                            <div style="width: 200px;">
                                <label class="form-label fw-semibold">Date Filter Type</label>
                                <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm">
                                    <option value="" <?= empty($_GET['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
                                    <option value="month_year" <?= (($_GET['date_filter_type'] ?? '') === 'month_year') ? 'selected' : '' ?>>Month-Year Range</option>
                                    <option value="year" <?= (($_GET['date_filter_type'] ?? '') === 'year') ? 'selected' : '' ?>>Year Range</option>
                                    <option value="mdy" <?= (($_GET['date_filter_type'] ?? '') === 'mdy') ? 'selected' : '' ?>>Month-Date-Year Range</option>
                                </select>
                            </div>

                            <!-- Date inputs container positioned to the right -->
                            <div id="dateInputsContainer" class="d-flex flex-wrap gap-3">
                                <!-- MDY Range -->
                                <div class="date-filter date-mdy d-none" style="width: 200px;">
                                    <label class="form-label fw-semibold">Date From</label>
                                    <input type="date" name="date_from" class="form-control shadow-sm"
                                        value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                                        placeholder="Start Date (YYYY-MM-DD)">
                                </div>
                                <div class="date-filter date-mdy d-none" style="width: 200px;">
                                    <label class="form-label fw-semibold">Date To</label>
                                    <input type="date" name="date_to" class="form-control shadow-sm"
                                        value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                        placeholder="End Date (YYYY-MM-DD)">
                                </div>

                                <!-- Year Range -->
                                <div class="date-filter date-year d-none" style="width: 200px;">
                                    <label class="form-label fw-semibold">Year From</label>
                                    <input type="number" name="year_from" class="form-control shadow-sm"
                                        min="1900" max="2100"
                                        placeholder="e.g., 2023"
                                        value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">
                                </div>
                                <div class="date-filter date-year d-none" style="width: 200px;">
                                    <label class="form-label fw-semibold">Year To</label>
                                    <input type="number" name="year_to" class="form-control shadow-sm"
                                        min="1900" max="2100"
                                        placeholder="e.g., 2025"
                                        value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
                                </div>

                                <!-- Month-Year Range -->
                                <div class="date-filter date-month_year d-none" style="width: 200px;">
                                    <label class="form-label fw-semibold">From (MM-YYYY)</label>
                                    <input type="month" name="month_year_from" class="form-control shadow-sm"
                                        value="<?= htmlspecialchars($_GET['month_year_from'] ?? '') ?>"
                                        placeholder="e.g., 2023-01">
                                </div>
                                <div class="date-filter date-month_year d-none" style="width: 200px;">
                                    <label class="form-label fw-semibold">To (MM-YYYY)</label>
                                    <input type="month" name="month_year_to" class="form-control shadow-sm"
                                        value="<?= htmlspecialchars($_GET['month_year_to'] ?? '') ?>"
                                        placeholder="e.g., 2023-12">
                                </div>
                            </div>

                            <!-- Filter buttons moved to the same row -->
                            <div class="ms-auto d-flex gap-2">
                                <button type="submit" id="applyFilters" class="btn btn-dark">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                                    <i class="fas fa-times-circle"></i> Clear
                                </button>
                            </div>
                        </div>

                        <!-- Search bar -->
                        <div class="col-12 col-sm-6 col-md-4">
                            <label class="form-label fw-semibold">Search</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" name="search" id="searchInput" class="form-control"
                                    placeholder="Search role archives..."
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive" id="table">
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
                                    <?php foreach ($roleData as $role): ?>
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
                                                echo '<span class="action-badge action-' . strtolower(str_replace(' ', '.', $actionText)) . '">';
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



                        <div class="container-fluid">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-sm-auto">
                                    <div class="text-muted">
                                        <?php $totalRoles = count($roleData); ?>
                                        <input type="hidden" id="total-users" value="<?= $totalRoles ?>">
                                        Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span
                                            id="totalRows"><?= $totalRoles ?></span> entries
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM loaded - initializing direct pagination");

            // Basic pagination elements
            const tableRows = document.querySelectorAll('#auditTable tr');
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
            const paginationUl = document.getElementById('pagination');
            const currentPageDisplay = document.getElementById('currentPage');
            const rowsPerPageDisplay = document.getElementById('rowsPerPage');
            const totalRowsDisplay = document.getElementById('totalRows');

            // Pagination state
            let currentPage = 1;
            let rowsPerPage = parseInt(rowsPerPageSelect.value) || 10;
            const totalRows = tableRows.length;

            console.log(`Pagination initialized with ${totalRows} rows, ${rowsPerPage} per page`);

            // Update displays
            totalRowsDisplay.textContent = totalRows;

            // Function to show only rows for current page
            function displayPage(page) {
                currentPage = page;
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;

                console.log(`Displaying page ${page}, rows ${start}-${Math.min(end, totalRows)}`);

                // Hide all rows first
                tableRows.forEach(row => row.style.display = 'none');

                // Show only rows for current page
                for (let i = start; i < Math.min(end, totalRows); i++) {
                    if (tableRows[i]) tableRows[i].style.display = '';
                }

                // Update the "showing X to Y of Z entries" text
                currentPageDisplay.textContent = page;
                rowsPerPageDisplay.textContent = Math.min(rowsPerPage, totalRows - start);

                // Update page buttons
                generatePagination();

                // Update prev/next button states
                updateButtonStates();
            }

            function generatePagination() {
                // Clear existing pagination
                paginationUl.innerHTML = '';

                // Calculate total pages
                const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));

                // Don't show pagination if only one page
                if (totalPages <= 1) {
                    return;
                }

                // Determine visible page range
                const maxVisiblePages = 5;
                let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

                // Adjust if at the end
                if (endPage === totalPages) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                }

                // Create first page button if needed
                if (startPage > 1) {
                    addPageButton(1);

                    // Add ellipsis if needed
                    if (startPage > 2) {
                        addEllipsis();
                    }
                }

                // Add numbered page buttons
                for (let i = startPage; i <= endPage; i++) {
                    addPageButton(i, i === currentPage);
                }

                // Add last page button if needed
                if (endPage < totalPages) {
                    // Add ellipsis if needed
                    if (endPage < totalPages - 1) {
                        addEllipsis();
                    }

                    addPageButton(totalPages);
                }
            }

            // Helper function to add a page button
            function addPageButton(pageNum, isActive = false) {
                const li = document.createElement('li');
                li.className = 'page-item' + (isActive ? ' active' : '');

                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = pageNum;
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    displayPage(pageNum);
                });

                li.appendChild(a);
                paginationUl.appendChild(li);
            }

            // Helper function to add ellipsis
            function addEllipsis() {
                const li = document.createElement('li');
                li.className = 'page-item disabled';

                const span = document.createElement('span');
                span.className = 'page-link';
                span.textContent = '...';

                li.appendChild(span);
                paginationUl.appendChild(li);
            }

            // Update prev/next button states
            function updateButtonStates() {
                const totalPages = Math.ceil(totalRows / rowsPerPage);

                // Update prev button
                prevBtn.disabled = currentPage <= 1;
                prevBtn.classList.toggle('disabled', currentPage <= 1);

                // Update next button
                nextBtn.disabled = currentPage >= totalPages;
                nextBtn.classList.toggle('disabled', currentPage >= totalPages);
            }

            // Event handler for previous button - using multiple approaches for reliability
            if (prevBtn) {
                console.log("Setting up prev button click handlers");

                // Remove any existing click handlers by cloning and replacing
                const newPrevBtn = prevBtn.cloneNode(true);
                prevBtn.parentNode.replaceChild(newPrevBtn, prevBtn);

                // Re-assign prevBtn reference to the new element
                prevBtn = newPrevBtn;

                // Using standard event listener
                prevBtn.addEventListener('click', function(e) {
                    console.log("Prev button clicked via DOM event");
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        displayPage(currentPage);
                    }
                });

                // Also add jQuery handler as a backup
                if (typeof jQuery !== 'undefined') {
                    jQuery(prevBtn).off('click').on('click', function(e) {
                        console.log("Prev button clicked via jQuery");
                        e.preventDefault();
                        if (currentPage > 1) {
                            currentPage--;
                            displayPage(currentPage);
                        }
                    });
                }

                // Direct onclick attribute as a fallback
                prevBtn.onclick = function(e) {
                    console.log("Prev button clicked via onclick");
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        displayPage(currentPage);
                    }
                    return false;
                };
            } else {
                console.error("Previous button not found in the DOM!");
            }

            // Event handler for next button
            if (nextBtn) {
                console.log("Setting up next button click handlers");

                // Remove any existing click handlers by cloning and replacing
                const newNextBtn = nextBtn.cloneNode(true);
                nextBtn.parentNode.replaceChild(newNextBtn, nextBtn);

                // Re-assign nextBtn reference to the new element
                nextBtn = newNextBtn;

                // Using standard event listener
                nextBtn.addEventListener('click', function(e) {
                    console.log("Next button clicked via DOM event");
                    e.preventDefault();
                    const totalPages = Math.ceil(totalRows / rowsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        displayPage(currentPage);
                    }
                });

                // Also add jQuery handler as a backup
                if (typeof jQuery !== 'undefined') {
                    jQuery(nextBtn).off('click').on('click', function(e) {
                        console.log("Next button clicked via jQuery");
                        e.preventDefault();
                        const totalPages = Math.ceil(totalRows / rowsPerPage);
                        if (currentPage < totalPages) {
                            currentPage++;
                            displayPage(currentPage);
                        }
                    });
                }

                // Direct onclick attribute as a fallback
                nextBtn.onclick = function(e) {
                    console.log("Next button clicked via onclick");
                    e.preventDefault();
                    const totalPages = Math.ceil(totalRows / rowsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        displayPage(currentPage);
                    }
                    return false;
                };
            }

            // Event handler for rows per page change
            rowsPerPageSelect.addEventListener('change', function() {
                rowsPerPage = parseInt(this.value);
                currentPage = 1; // Reset to first page
                displayPage(1);
            });

            // Initialize display
            displayPage(1);
        });
    </script>

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
        // Pass RBAC privileges to JavaScript
        var userPrivileges = {
            canRestore: <?php echo json_encode($canRestore); ?>,
            canRemove: <?php echo json_encode($canRemove); ?>,
            canPermanentDelete: <?php echo json_encode($canPermanentDelete); ?>
        };

        // Add click handler for restore buttons
        $(document).on('click', '.restore-btn', function() {
            let restoreId = $(this).data('role-id');
            let restoreName = $(this).data('role-name');
            $('#restoreRoleNamePlaceholder').text(restoreName);

            // Store the ID to use in confirm button handler
            $('#confirmRestoreBtn').data('role-id', restoreId);
        });

        // Update the restore confirmation handler
        $('#confirmRestoreBtn').on('click', function() {
            let restoreId = $(this).data('role-id');

            if (!userPrivileges.canRestore || !restoreId) return;

            $.ajax({
                url: '../../rolesandprivilege_manager/role_manager/restore_role.php',
                method: 'POST',
                data: {
                    id: restoreId,
                    action: 'restore',
                    module: 'Roles and Privileges'
                },
                dataType: 'json',
                success: function(response) {
                    // Hide restore modal
                    $('#confirmRestoreModal').modal('hide');

                    if (response.success) {
                        showToast(response.message || 'Role restored successfully', 'success');
                        // Reload the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        showToast(response.message || 'Failed to restore role', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showToast('Error restoring role: ' + error, 'error');
                }
            });
        });

        // Add click handler for delete buttons
        $(document).on('click', '.delete-btn', function() {
            let deleteId = $(this).data('role-id');
            let deleteName = $(this).data('role-name');
            $('#roleNamePlaceholder').text(deleteName);

            // Set the URL for the confirm button
            $('#confirmDeleteButton').attr('href', '../../rolesandprivilege_manager/role_manager/permanent_delete_role.php?id=' + deleteId);
        });

        // Date filter type change handler
        const filterType = document.getElementById('dateFilterType');
        if (filterType) {
            filterType.addEventListener('change', function() {
                // Hide all date filter fields first
                document.querySelectorAll('.date-filter').forEach(field => field.classList.add('d-none'));

                // Show relevant date filter fields based on selection
                if (this.value) {
                    document.querySelectorAll('.date-' + this.value).forEach(field => field.classList.remove('d-none'));
                }
            });

            // Initialize date filter fields visibility
            if (filterType.value) {
                document.querySelectorAll('.date-' + filterType.value).forEach(field => field.classList.remove('d-none'));
            }
        }

        // Clear filters button
        const clearFiltersBtn = document.getElementById('clearFilters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                const form = document.getElementById('archiveFilterForm');
                form.reset();

                // Clear date filter type and hide all date filter fields
                if (filterType) {
                    filterType.value = '';
                    document.querySelectorAll('.date-filter').forEach(field => field.classList.add('d-none'));
                }

                // Clear search input
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = '';
                }

                // Submit the form to reset the data
                form.submit();
            });
        }

        // Date filter validation
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('archiveFilterForm');

            // Date validation function
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
                }

                return fromDate <= toDate;
            }

            // Form submission validation
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    const dateFilterType = document.getElementById('dateFilterType').value;
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
                            errorMessage = '"From (MM-YYYY)" cannot be greater than "To (MM-YYYY)"';
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
  e.preventDefault();
  $('#filterError').remove();

  // 1) pick your filter-row container
  const filterRow = document.querySelector('.col-12.d-flex.flex-wrap.align-items-end');

  // 2) build a “block” error div (no absolute positioning needed)
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

  // 3) insert it *after* the filter row, so it sits right below
  filterRow.insertAdjacentElement('afterend', errorDiv);

  // optional: scroll into view
  errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  // auto-dismiss
  setTimeout(() => {
    $('#filterError').fadeOut('slow', () => $('#filterError').remove());
  }, 3000);

  return;
}


                    $('#filterError').remove();
                });
            }
        });
    </script>
</body>

</html>