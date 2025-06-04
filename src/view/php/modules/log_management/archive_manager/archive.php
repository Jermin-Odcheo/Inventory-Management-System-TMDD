<?php
session_start();
require '../../../../../../config/ims-tmdd.php';

// Include Header
include '../../../general/header.php';

// If not logged in, redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Initialize RBAC for User Management
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('User Management', 'View');

// Check for additional privileges
$canRestore = $rbac->hasPrivilege('User Management', 'Restore');
$canRemove = $rbac->hasPrivilege('User Management', 'Remove');
$canDelete = $rbac->hasPrivilege('User Management', 'Permanently Delete');

// --- Sorting Logic ---
$sort_by = $_GET['sort_by'] ?? 'track_id'; // Default sort column
$sort_order = $_GET['sort_order'] ?? 'desc'; // Default sort order

// Whitelist allowed columns to prevent SQL injection
$allowedSortColumns = [
    'track_id' => 'a.TrackID',
    'operator_name' => 'operator_name', // Alias from CONCAT
    'module' => 'a.Module',
    'action' => 'a.Action',
    'status' => 'a.Status',
    'date_time' => 'a.Date_Time'
];

// Validate sort_by and sort_order
if (!array_key_exists($sort_by, $allowedSortColumns)) {
    $sort_by = 'track_id'; // Fallback to default
}
if (!in_array(strtolower($sort_order), ['asc', 'desc'])) {
    $sort_order = 'desc'; // Fallback to default
}

// --- Filter Logic ---
$dateFilterType = $_GET['date_filter_type'] ?? '';
$baseWhere = "LOWER(a.Action) = 'remove' AND a.TrackID IN (
    SELECT MAX(a2.TrackID)
    FROM audit_log a2
    WHERE a2.EntityID = a.EntityID
    AND LOWER(a2.Action) = 'remove'
    GROUP BY a2.EntityID
)";
$params = [];

// Apply action filter
if (!empty($_GET['action_type'])) {
    $baseWhere .= " AND a.Action = :action_type";
    $params[':action_type'] = $_GET['action_type'];
}

// Apply status filter
if (!empty($_GET['status'])) {
    $baseWhere .= " AND a.Status = :status";
    $params[':status'] = $_GET['status'];
}

// Apply search filter
if (!empty($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    $baseWhere .= " AND (
        op.Email LIKE :search_email 
        OR a.Details LIKE :search_details
        OR a.OldVal LIKE :search_oldval
        OR a.NewVal LIKE :search_newval
    )";
    $params[':search_email'] = $searchTerm;
    $params[':search_details'] = $searchTerm;
    $params[':search_oldval'] = $searchTerm;
    $params[':search_newval'] = $searchTerm;
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

// --- Pagination Logic ---
$rows_per_page = isset($_GET['rows_per_page']) ? (int)$_GET['rows_per_page'] : 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $rows_per_page;

// Count total records for pagination
$count_query = "SELECT COUNT(DISTINCT a.TrackID) as total FROM audit_log a LEFT JOIN users u ON a.EntityID = u.id JOIN users op ON a.UserID = op.id WHERE $baseWhere";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $rows_per_page);

// Adjust current page if it exceeds total pages
if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $rows_per_page;
}

// Modify main query to include LIMIT and OFFSET for pagination
$query = "
SELECT DISTINCT
    a.TrackID AS track_id,
    CONCAT(op.First_Name, ' ', op.Last_Name) AS operator_name,
    op.Email AS operator_email,
    a.Module AS module,
    a.Action AS action,
    a.Details AS details,
    a.OldVal AS old_val,
    a.NewVal AS new_val,
    a.Status AS status,
    a.Date_Time AS date_time,
    a.EntityID AS deleted_user_id
FROM audit_log a
LEFT JOIN users u ON a.EntityID = u.id
JOIN users op ON a.UserID = op.id
WHERE $baseWhere
{$orderByClause}
LIMIT :offset, :rows_per_page
";

$params[':offset'] = $offset;
$params[':rows_per_page'] = $rows_per_page;

try {
    // Debug the query and parameters
    echo "<!-- Debug Query: " . $query . " -->";
    echo "<!-- Debug Params: " . print_r($params, true) . " -->";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug the results
    echo "<!-- Debug Results Count: " . count($logs) . " -->";
    if (!empty($logs)) {
        echo "<!-- Debug First Row: " . print_r($logs[0], true) . " -->";
    }
    
    if (!$logs) {
        $logs = [];
    }
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Debug the table rendering
echo "<!-- Debug: Starting table rendering -->";
echo "<!-- Debug: Number of logs to render: " . count($logs) . " -->";

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
 * Helper function to return a status icon.
 */
function getStatusIcon($status)
{
    return (strtolower($status) === 'successful')
        ? '<i class="fas fa-check-circle"></i>'
        : '<i class="fas fa-times-circle"></i>';
}

/**
 * Format JSON data to display old values only.
 */
function formatChanges($oldJsonStr) {
    // If the input is already an array, use it directly
    if (is_array($oldJsonStr)) {
        $oldData = $oldJsonStr;
    } else {
        // Try to decode JSON string
        $oldData = json_decode($oldJsonStr, true);
    }

    // If not an array or JSON decode failed, return the original string
    if (!is_array($oldData)) {
        return '<span>' . htmlspecialchars((string)$oldJsonStr) . '</span>';
    }

    $html = '<ul class="list-group">';
    foreach ($oldData as $key => $value) {
        // Format the value
        if (is_array($value)) {
            $displayValue = '<em>Array</em>';
        } else if (is_null($value)) {
            $displayValue = '<em>null</em>';
        } else if (is_bool($value)) {
            $displayValue = $value ? 'true' : 'false';
        } else {
            $displayValue = htmlspecialchars((string)$value);
        }
        
        $friendlyKey = ucwords(str_replace('_', ' ', $key));

        $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>' . $friendlyKey . ':</strong>
                    <span class="old-value text-danger"><i class="fas fa-history me-1"></i> ' . $displayValue . '</span>
                  </li>';
    }
    $html .= '</ul>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Users Management Archive</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/audit_log.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/pagination.css">

    <style>
        .main-content {
            padding-top: 150px;
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
    <?php include '../../../general/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center bg-dark">
                    <h3 class="text-white">
                        <i class="fas fa-archive me-2"></i>
                        User Management Archives
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="bulk-actions mb-3">
                                <?php if ($canRestore): ?>
                                    <button type="button" id="restore-selected" class="btn btn-success" disabled style="display: none;">Restore Selected</button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <button type="button" id="delete-selected-permanently" class="btn btn-danger" disabled style="display: none;">Delete Selected Permanently</button>
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
                                    placeholder="Search archives..."
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

                    <div class="table-responsive" id="table">
                        <table id="archiveTable" class="table table-hover">
                            <colgroup>
                                <col class="track">
                                <col class="user">
                                <col class="action">
                                <col class="details">
                                <col class="changes">
                                <col class="date">
                                <col class="actions">
                            </colgroup>
                            <thead class="table-light">
                                <tr>
                                    <th class="sortable" data-sort-by="track_id"># <i class="fas fa-sort"></i></th>
                                    <th class="sortable" data-sort-by="operator_name">User <i class="fas fa-sort"></i></th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Changes</th>
                                    <th class="sortable" data-sort-by="date_time">Date &amp; Time <i class="fas fa-sort"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="archiveTableBody">
                                <?php 
                                if (!empty($logs)): 
                                    foreach ($logs as $log): 
                                ?>
                                        <tr>
                                            <td data-label="Track ID">
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($log['track_id']); ?></span>
                                            </td>
                                            <td data-label="User">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2"></i>
                                                    <small><?php echo htmlspecialchars($log['operator_email']); ?></small>
                                                </div>
                                            </td>
                                            <td data-label="Action">
                                                <?php
                                                $actionText = !empty($log['action']) ? $log['action'] : 'Unknown';
                                                echo '<span class="action-badge action-' . strtolower($actionText) . '">';
                                                echo getActionIcon($actionText) . ' ' . htmlspecialchars($actionText);
                                                echo '</span>';
                                                ?>
                                            </td>
                                            <td data-label="Details">
                                                <?php echo nl2br(htmlspecialchars($log['details'])); ?>
                                            </td>
                                            <td data-label="Changes">
                                                <?php echo formatChanges($log['old_val']); ?>
                                            </td>
                                            <td data-label="Date &amp; Time">
                                                <div class="d-flex align-items-center">
                                                    <i class="far fa-clock me-2"></i>
                                                    <?php echo htmlspecialchars($log['date_time']); ?>
                                                </div>
                                            </td>
                                            <td data-label="Actions">
                                                <div class="btn-group-vertical gap-1">
                                                    <?php if ($canRestore): ?>
                                                        <button type="button" class="btn btn-success restore-btn" data-id="<?php echo $log['deleted_user_id']; ?>">
                                                            <i class="fas fa-undo me-1"></i> Restore
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($canDelete): ?>
                                                        <button type="button" class="btn btn-danger delete-permanent-btn" data-id="<?php echo $log['deleted_user_id']; ?>">
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
                                                <h4>No Archived Users Found</h4>
                                                <p class="text-muted">There are no archived users to display.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Enhanced Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="d-flex align-items-center gap-2">
                            <span>Show</span>
                            <select id="rowsPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="40">40</option>
                                <option value="50">50</option>
                            </select>
                            <span>entries</span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span>
                                Showing <span id="startRecord">1</span> to <span id="endRecord">10</span> of <span id="totalRecords"><?php echo count($logs); ?></span> entries
                            </span>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item">
                                        <button id="firstPage" class="page-link" aria-label="First">
                                            <i class="fas fa-angle-double-left"></i>
                                        </button>
                                    </li>
                                    <li class="page-item">
                                        <button id="prevPage" class="page-link" aria-label="Previous">
                                            <i class="fas fa-angle-left"></i>
                                        </button>
                                    </li>
                                    <div id="paginationNumbers" class="d-flex"></div>
                                    <li class="page-item">
                                        <button id="nextPage" class="page-link" aria-label="Next">
                                            <i class="fas fa-angle-right"></i>
                                        </button>
                                    </li>
                                    <li class="page-item">
                                        <button id="lastPage" class="page-link" aria-label="Last">
                                            <i class="fas fa-angle-double-right"></i>
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>

                    <style>
                        .pagination {
                            margin: 0;
                            display: flex;
                            align-items: center;
                        }
                        .pagination .page-link {
                            padding: 0.25rem 0.5rem;
                            font-size: 0.875rem;
                            line-height: 1.5;
                            border-radius: 0.2rem;
                        }
                        #paginationNumbers {
                            display: flex;
                            gap: 0.25rem;
                        }
                        #paginationNumbers button {
                            min-width: 2rem;
                            height: 2rem;
                            padding: 0.25rem 0.5rem;
                            font-size: 0.875rem;
                            line-height: 1.5;
                            border-radius: 0.2rem;
                            border: 1px solid #dee2e6;
                            background-color: #fff;
                            color: #0d6efd;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        #paginationNumbers button.active {
                            background-color: #0d6efd;
                            color: #fff;
                            border-color: #0d6efd;
                        }
                        #paginationNumbers button:hover:not(.active) {
                            background-color: #e9ecef;
                        }
                        .page-item.disabled .page-link {
                            color: #6c757d;
                            pointer-events: none;
                            background-color: #fff;
                            border-color: #dee2e6;
                        }
                    </style>

                    <script>
                        // Pass RBAC permissions to JavaScript
                        var userPrivileges = {
                            canRestore: <?php echo json_encode($canRestore); ?>,
                            canRemove: <?php echo json_encode($canRemove); ?>,
                            canDelete: <?php echo json_encode($canDelete); ?>
                        };

                        // Pagination Configuration
                        const paginationConfig = {
                            currentPage: <?php echo $current_page; ?>,
                            rowsPerPage: <?php echo $rows_per_page; ?>,
                            totalRecords: <?php echo $total_records; ?>,
                            totalPages: <?php echo $total_pages; ?>,
                            maxPageButtons: 5
                        };

                        // Function to update pagination display
                        function updatePaginationDisplay() {
                            document.getElementById('startRecord').textContent = ((paginationConfig.currentPage - 1) * paginationConfig.rowsPerPage) + 1;
                            document.getElementById('endRecord').textContent = Math.min(paginationConfig.currentPage * paginationConfig.rowsPerPage, paginationConfig.totalRecords);
                            document.getElementById('totalRecords').textContent = paginationConfig.totalRecords;
                            updatePaginationButtons();
                            updatePageNumbers();
                        }

                        // Update Pagination Buttons
                        function updatePaginationButtons() {
                            document.getElementById('firstPage').parentElement.classList.toggle('disabled', paginationConfig.currentPage === 1);
                            document.getElementById('prevPage').parentElement.classList.toggle('disabled', paginationConfig.currentPage === 1);
                            document.getElementById('nextPage').parentElement.classList.toggle('disabled', paginationConfig.currentPage === paginationConfig.totalPages);
                            document.getElementById('lastPage').parentElement.classList.toggle('disabled', paginationConfig.currentPage === paginationConfig.totalPages);
                        }

                        // Update Page Numbers
                        function updatePageNumbers() {
                            const paginationNumbers = document.getElementById('paginationNumbers');
                            paginationNumbers.innerHTML = '';

                            let startPage = Math.max(1, paginationConfig.currentPage - Math.floor(paginationConfig.maxPageButtons / 2));
                            let endPage = Math.min(paginationConfig.totalPages, startPage + paginationConfig.maxPageButtons - 1);

                            if (endPage - startPage + 1 < paginationConfig.maxPageButtons) {
                                startPage = Math.max(1, endPage - paginationConfig.maxPageButtons + 1);
                            }

                            // Add page numbers
                            for (let i = startPage; i <= endPage; i++) {
                                const button = document.createElement('button');
                                button.className = i === paginationConfig.currentPage ? 'active' : '';
                                button.textContent = i;
                                button.onclick = () => goToPage(i);
                                paginationNumbers.appendChild(button);
                            }
                        }

                        // Navigation Functions
                        function goToPage(page) {
                            paginationConfig.currentPage = page;
                            window.location.href = updateQueryStringParameter(window.location.href, 'page', page);
                        }

                        function goToFirstPage() {
                            goToPage(1);
                        }

                        function goToLastPage() {
                            goToPage(paginationConfig.totalPages);
                        }

                        function goToPrevPage() {
                            if (paginationConfig.currentPage > 1) {
                                goToPage(paginationConfig.currentPage - 1);
                            }
                        }

                        function goToNextPage() {
                            if (paginationConfig.currentPage < paginationConfig.totalPages) {
                                goToPage(paginationConfig.currentPage + 1);
                            }
                        }

                        // Helper function to update query string parameters
                        function updateQueryStringParameter(uri, key, value) {
                            var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
                            var separator = uri.indexOf('?') !== -1 ? "&" : "?";
                            if (uri.match(re)) {
                                return uri.replace(re, '$1' + key + "=" + value + '$2');
                            } else {
                                return uri + separator + key + "=" + value;
                            }
                        }

                        // Event Listeners
                        document.addEventListener('DOMContentLoaded', function() {
                            // Initialize pagination display
                            updatePaginationDisplay();

                            // Add event listeners for pagination controls
                            document.getElementById('firstPage').addEventListener('click', goToFirstPage);
                            document.getElementById('prevPage').addEventListener('click', goToPrevPage);
                            document.getElementById('nextPage').addEventListener('click', goToNextPage);
                            document.getElementById('lastPage').addEventListener('click', goToLastPage);

                            // Handle rows per page change
                            document.getElementById('rowsPerPageSelect').addEventListener('change', function(e) {
                                paginationConfig.rowsPerPage = parseInt(e.target.value);
                                paginationConfig.currentPage = 1; // Reset to first page
                                window.location.href = updateQueryStringParameter(window.location.href, 'rows_per_page', paginationConfig.rowsPerPage) + '&page=1';
                            });

                            // Initialize filters
                            const filterType = document.getElementById('dateFilterType');
                            const allDateFilters = document.querySelectorAll('.date-filter');

                            function updateDateFields() {
                                allDateFilters.forEach(field => field.classList.add('d-none'));
                                if (!filterType.value) return;
                                document.querySelectorAll('.date-' + filterType.value).forEach(field => field.classList.remove('d-none'));
                            }

                            if (filterType) {
                                filterType.addEventListener('change', updateDateFields);
                                updateDateFields(); // Initialize on page load
                            }

                            // Clear filters button
                            const clearFiltersBtn = document.getElementById('clearFilters');
                            if (clearFiltersBtn) {
                                clearFiltersBtn.addEventListener('click', function() {
                                    const form = document.getElementById('archiveFilterForm');
                                    form.reset();

                                    // Clear date filter type and hide all date filter fields
                                    const filterType = document.getElementById('dateFilterType');
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

                            // Modal functionality for Restore and Delete buttons
                            const restoreButtons = document.querySelectorAll('.restore-btn');
                            const deleteButtons = document.querySelectorAll('.delete-permanent-btn');
                            const restoreModal = new bootstrap.Modal(document.getElementById('restoreArchiveModal'));
                            const deleteModal = new bootstrap.Modal(document.getElementById('deleteArchiveModal'));
                            let currentRestoreId = null;
                            let currentDeleteId = null;

                            restoreButtons.forEach(button => {
                                button.addEventListener('click', function() {
                                    currentRestoreId = this.getAttribute('data-id');
                                    restoreModal.show();
                                });
                            });

                            deleteButtons.forEach(button => {
                                button.addEventListener('click', function() {
                                    currentDeleteId = this.getAttribute('data-id');
                                    deleteModal.show();
                                });
                            });

                            document.getElementById('confirmRestoreBtn').addEventListener('click', function() {
                                if (currentRestoreId) {
                                    // Here you would typically send an AJAX request to restore the record
                                    console.log('Restoring record with ID: ' + currentRestoreId);
                                    // For now, just close the modal
                                    restoreModal.hide();
                                }
                            });

                            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                                if (currentDeleteId) {
                                    // Here you would typically send an AJAX request to delete the record
                                    console.log('Deleting record with ID: ' + currentDeleteId);
                                    // For now, just close the modal
                                    deleteModal.hide();
                                }
                            });
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteArchiveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to permanently delete this record?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmDeleteBtn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="restoreArchiveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to restore this record?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmRestoreBtn" class="btn btn-success">Restore</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Bulk Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to permanently delete the selected users?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmBulkDeleteBtn" class="btn btn-danger">Delete</button>
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
                    Are you sure you want to restore the selected users?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmBulkRestoreBtn" class="btn btn-success">Restore</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../../general/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>