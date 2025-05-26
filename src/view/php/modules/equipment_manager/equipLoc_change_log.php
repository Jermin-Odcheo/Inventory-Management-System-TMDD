<?php
session_start();
require '../../../../../config/ims-tmdd.php';
// include '../../general/header.php';
// include '../../general/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$rbac = new RBACService($pdo, $_SESSION['user_id']);
$hasEqLocPermission = $rbac->hasPrivilege('Equipment Management', 'View');

if (!$hasEqLocPermission) {
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

// ----------- Fetch Building Locations dynamically from equipment_location table -----------
$buildingStmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc ASC");
$allBuildings = $buildingStmt->fetchAll(PDO::FETCH_COLUMN);

// --------- Initialize GET parameters ---------
$statusFilter = $_GET['device_status'] ?? '';
$buildingFilter = $_GET['building_loc'] ?? '';
$floorFilter = $_GET['floor'] ?? '';
$areaFilter = $_GET['specific_area'] ?? '';
$assetTagSearch = trim($_GET['asset_tag'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortColumn = $_GET['sort'] ?? 'Date_Time'; // default sort by modification date
$sortOrder = $_GET['order'] ?? 'DESC'; // default descending

// Validate sort inputs (to avoid SQL injection)
$validSortColumns = ['asset_tag', 'building_loc', 'floor', 'specific_area', 'person_responsible', 'department', 'device_status', 'remarks', 'Date_Time'];
if (!in_array($sortColumn, $validSortColumns)) {
    $sortColumn = 'Date_Time';
}
$sortOrder = strtoupper($sortOrder);
if (!in_array($sortOrder, ['ASC', 'DESC'])) {
    $sortOrder = 'DESC';
}

// Prepare query parts and parameters
$whereClauses = ["Module = 'Equipment Location'", "Action IN ('Create', 'Modify')"];
$params = [];

// Filters: Since filters are single-select dropdowns now, treat as scalar values
if (!empty($statusFilter)) {
    $whereClauses[] = "JSON_EXTRACT(NewVal, '$.device_status') = ?";
    $params[] = $statusFilter;
}

if (!empty($buildingFilter)) {
    $whereClauses[] = "JSON_EXTRACT(NewVal, '$.building_loc') = ?";
    $params[] = $buildingFilter;
}

if (!empty($floorFilter)) {
    $whereClauses[] = "JSON_EXTRACT(NewVal, '$.floor') = ?";
    $params[] = $floorFilter;
}

if (!empty($areaFilter)) {
    $whereClauses[] = "JSON_EXTRACT(NewVal, '$.specific_area') = ?";
    $params[] = $areaFilter;
}

if (!empty($assetTagSearch)) {
    $whereClauses[] = "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.asset_tag')) LIKE ?";
    $params[] = "%$assetTagSearch%";
}

// Date range filtering
if (!empty($dateFrom)) {
    $whereClauses[] = "Date_Time >= ?";
    $params[] = $dateFrom . " 00:00:00";
}
if (!empty($dateTo)) {
    $whereClauses[] = "Date_Time <= ?";
    $params[] = $dateTo . " 23:59:59";
}

$whereSql = implode(' AND ', $whereClauses);

// Adjust sort column to JSON fields or Date_Time
// Mapping column keys to JSON_EXTRACT if necessary
$sortColumnSqlMap = [
    'asset_tag' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.asset_tag'))",
    'building_loc' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.building_loc'))",
    'floor' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.floor'))",
    'specific_area' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.specific_area'))",
    'person_responsible' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.person_responsible'))",
    'department' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.department'))",
    'device_status' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.device_status'))",
    'remarks' => "JSON_UNQUOTE(JSON_EXTRACT(NewVal, '$.remarks'))",
    'Date_Time' => "Date_Time"
];

$orderBy = $sortColumnSqlMap[$sortColumn] . " " . $sortOrder;

$query = "
    SELECT NewVal, Action, Date_Time
    FROM audit_log
    WHERE $whereSql
    ORDER BY $orderBy
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getActionIcon($action) {
    switch (strtolower($action)) {
        case 'added':
            return '<i class="fas fa-plus-circle"></i>';
        case 'modified':
            return '<i class="fas fa-edit"></i>';
        default:
            return '<i class="fas fa-info-circle"></i>';
    }
}

// Function to build sortable column header links
function sortLink($column, $currentSort, $currentOrder, $label) {
    $order = 'ASC';
    $arrow = '';
    if ($currentSort === $column) {
        if ($currentOrder === 'ASC') {
            $order = 'DESC';
            $arrow = ' &uarr;';
        } else {
            $order = 'ASC';
            $arrow = ' &darr;';
        }
    }
    $queryParams = $_GET;
    $queryParams['sort'] = $column;
    $queryParams['order'] = $order;
    $queryString = http_build_query($queryParams);
    return "<a href=\"?" . htmlspecialchars($queryString) . "\">" . htmlspecialchars($label) . $arrow . "</a>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Equipment Location Change Log</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <style>
        main.container {
            padding-top: 2rem;
            padding-left: 320px; /* Adjust for sidebar */
            padding-right: 2rem;
            padding-bottom: 2rem;
            min-height: 80vh;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .action-badge {
            padding: 5px 10px;
            border-radius: 0.375rem;
            color: white;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .action-added {
            background-color: #28a745;
        }
        .action-modified {
            background-color: #fd7e14;
        }
        @media (max-width: 767.98px) {
            main.container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        /* Date range hidden by default */
        #dateRangeFilters {
            display: none;
        }
    </style>
</head>
<body>

<main class="container">
    <h2 class="mb-4">Equipment Location – Successful Modifications and Additions</h2>

    <!-- Filter/Search Form -->
    <form method="GET" class="mb-4" id="filterForm">
        <div class="row g-3 align-items-end">
            <!-- Device Status (single-select) -->
            <div class="col-md-2">
                <label for="device_status" class="form-label">Device Status</label>
                <select name="device_status" id="device_status" class="form-select">
                    <option value="">-- All --</option>
                    <?php
                    $allStatuses = ['Active', 'Inactive', 'Repair', 'Retired'];
                    foreach ($allStatuses as $status) {
                        $selected = ($status === $statusFilter) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($status) . "\" $selected>" . htmlspecialchars($status) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Building Location (single-select dynamic) -->
            <div class="col-md-2">
                <label for="building_loc" class="form-label">Building Location</label>
                <select name="building_loc" id="building_loc" class="form-select">
                    <option value="">-- All --</option>
                    <?php
                    foreach ($allBuildings as $building) {
                        $selected = ($building === $buildingFilter) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($building) . "\" $selected>" . htmlspecialchars($building) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Floor (single-select) -->
            <div class="col-md-2">
                <label for="floor" class="form-label">Floor</label>
                <select name="floor" id="floor" class="form-select">
                    <option value="">-- All --</option>
                    <?php
                    $allFloors = ['Ground', '1', '2', '3', '4', '5'];
                    foreach ($allFloors as $floor) {
                        $selected = ($floor === $floorFilter) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($floor) . "\" $selected>" . htmlspecialchars($floor) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Specific Area (single-select) -->
            <div class="col-md-2">
                <label for="specific_area" class="form-label">Specific Area</label>
                <select name="specific_area" id="specific_area" class="form-select">
                    <option value="">-- All --</option>
                    <?php
                    $allAreas = ['Main Office', 'Lab A', 'Lab B', 'Warehouse', 'Reception'];
                    foreach ($allAreas as $area) {
                        $selected = ($area === $areaFilter) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($area) . "\" $selected>" . htmlspecialchars($area) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Asset Tag Search -->
            <div class="col-md-2">
                <label for="asset_tag" class="form-label">Asset Tag</label>
                <input type="text" name="asset_tag" id="asset_tag" value="<?= htmlspecialchars($assetTagSearch) ?>" class="form-control" placeholder="Search Asset Tag" />
            </div>

            <!-- Sort Options -->
            <div class="col-md-2">
                <label for="sort" class="form-label">Sort By</label>
                <select name="sort" id="sort" class="form-select">
                    <option value="Date_Time" <?= ($sortColumn === 'Date_Time') ? 'selected' : '' ?>>Modification Date</option>
                    <option value="asset_tag" <?= ($sortColumn === 'asset_tag') ? 'selected' : '' ?>>Asset Tag</option>
                    <option value="building_loc" <?= ($sortColumn === 'building_loc') ? 'selected' : '' ?>>Building Location</option>
                    <option value="floor" <?= ($sortColumn === 'floor') ? 'selected' : '' ?>>Floor</option>
                    <option value="specific_area" <?= ($sortColumn === 'specific_area') ? 'selected' : '' ?>>Specific Area</option>
                    <option value="person_responsible" <?= ($sortColumn === 'person_responsible') ? 'selected' : '' ?>>Person Responsible</option>
                    <option value="department" <?= ($sortColumn === 'department') ? 'selected' : '' ?>>Department</option>
                    <option value="device_status" <?= ($sortColumn === 'device_status') ? 'selected' : '' ?>>Device Status</option>
                    <option value="remarks" <?= ($sortColumn === 'remarks') ? 'selected' : '' ?>>Remarks</option>
                </select>
            </div>

            <!-- Order (ASC/DESC) -->
            <div class="col-md-2">
                <label for="order" class="form-label">Order</label>
                <select name="order" id="order" class="form-select">
                    <option value="DESC" <?= ($sortOrder === 'DESC') ? 'selected' : '' ?>>Descending</option>
                    <option value="ASC" <?= ($sortOrder === 'ASC') ? 'selected' : '' ?>>Ascending</option>
                </select>
            </div>
        </div>

        <!-- Date Range Filters (hidden by default) -->
        <div class="row g-3 mt-3" id="dateRangeFilters">
            <div class="col-md-3">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" />
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" />
            </div>
        </div>

        <!-- Buttons -->
        <div class="mt-4">
            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
            <button type="button" id="clearFilters" class="btn btn-secondary">Clear</button>
            <button type="button" id="toggleDateFilter" class="btn btn-info ms-2">Toggle Date Range Filter</button>
        </div>
    </form>

    <!-- Data Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover align-middle">
            <thead class="table-dark text-center">
                <tr>
                    <th><?= sortLink('asset_tag', $sortColumn, $sortOrder, 'Asset Tag') ?></th>
                    <th><?= sortLink('building_loc', $sortColumn, $sortOrder, 'Building Location') ?></th>
                    <th><?= sortLink('floor', $sortColumn, $sortOrder, 'Floor') ?></th>
                    <th><?= sortLink('specific_area', $sortColumn, $sortOrder, 'Specific Area') ?></th>
                    <th><?= sortLink('person_responsible', $sortColumn, $sortOrder, 'Accountable Individual') ?></th>
                    <th><?= sortLink('department', $sortColumn, $sortOrder, 'Department') ?></th>
                    <th><?= sortLink('device_status', $sortColumn, $sortOrder, 'Device Status') ?></th>
                    <th><?= sortLink('remarks', $sortColumn, $sortOrder, 'Remarks') ?></th>
                    <th><?= sortLink('Date_Time', $sortColumn, $sortOrder, 'Modification Date') ?></th>
                    <th>Action</th> <!-- Keeping action for icons but can be hidden if requested -->
                </tr>
            </thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="10" class="text-center">No records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): 
                        $newVal = json_decode($log['NewVal'], true);
                        if (!$newVal) continue; // skip if json decode fails

                        $actionClass = strtolower($log['Action']) === 'added' ? 'action-added' : 'action-modified';
                        $actionIcon = getActionIcon($log['Action']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($newVal['asset_tag'] ?? '') ?></td>
                        <td><?= htmlspecialchars($newVal['building_loc'] ?? '') ?></td>
                        <td><?= htmlspecialchars($newVal['floor'] ?? '') ?></td>
                        <td><?= htmlspecialchars($newVal['specific_area'] ?? '') ?></td>
                        <td><?= htmlspecialchars($newVal['person_responsible'] ?? '') ?></td>
                        <td><?= htmlspecialchars($newVal['department'] ?? '') ?></td>
                        <td><?= htmlspecialchars($newVal['device_status'] ?? '') ?></td>
                        <td><?= htmlspecialchars($newVal['remarks'] ?? '') ?></td>
                        <td><?= htmlspecialchars(date('M d, Y H:i', strtotime($log['Date_Time']))) ?></td>
                        <td class="text-center">
                            <span class="action-badge <?= $actionClass ?>" title="<?= htmlspecialchars($log['Action']) ?>">
                                <?= $actionIcon ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    // Toggle Date Range Filter display
    document.getElementById('toggleDateFilter').addEventListener('click', function() {
        const dateFilters = document.getElementById('dateRangeFilters');
        if (dateFilters.style.display === 'none' || dateFilters.style.display === '') {
            dateFilters.style.display = 'flex';
        } else {
            dateFilters.style.display = 'none';
            // Clear date inputs when hiding
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
        }
    });

    // Clear filters button resets all selects and inputs, then submits form
    document.getElementById('clearFilters').addEventListener('click', function() {
        const form = document.getElementById('filterForm');
        form.reset();
        // Hide date range when clearing
        document.getElementById('dateRangeFilters').style.display = 'none';
        form.submit();
    });

    // On page load, if date inputs have value, show date filter section automatically
    window.addEventListener('DOMContentLoaded', () => {
        const dateFromVal = document.getElementById('date_from').value;
        const dateToVal = document.getElementById('date_to').value;
        if (dateFromVal || dateToVal) {
            document.getElementById('dateRangeFilters').style.display = 'flex';
        }
    });
</script>

</body>
</html>
