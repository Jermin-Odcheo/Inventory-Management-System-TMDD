<?php
/**
 * Equipment Location Change Log Module
 *
 * This file provides functionality to track and manage changes in equipment locations. It handles the logging of location changes, including timestamps, user actions, and location details. The module ensures proper audit trail maintenance and supports integration with other modules for comprehensive equipment tracking.
 *
 * @package    InventoryManagementSystem
 * @subpackage EquipmentManager
 * @author     TMDD Interns 25'
 */
session_start();
require '../../../../../config/ims-tmdd.php';
include '../../general/header.php';
include '../../general/sidebar.php';

/**
 * Session Validation
 * 
 * Validates if a user is logged in by checking the session variable. If not, redirects to the login page.
 */
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

/**
 * RBAC Privilege Check
 * 
 * Initializes the RBAC service and ensures the user has the 'View' privilege for equipment management.
 */
$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Equipment Management', 'View')) {
    echo '
    <div class="container d-flex justify-content-center align-items-center" style="height:70vh; padding-left:300px">
        <div class="alert alert-danger text-center">
            <h1><i class="bi bi-shield-lock"></i> Access Denied</h1>
            <p class="mb-0">You do not have permission to view this page.</p>
        </div>
    </div>';
    exit();
}

/**
 * Fields to Display in Table
 * @var array
 */
$fieldsToShow = [
    'asset_tag' => 'Asset Tag',
    'building_loc' => 'Building Location',
    'floor_n' => 'Floor',
    'specific_area' => 'Specific Area',
    'person_responsible' => 'Person Responsible',
    'device_state' => 'Device State',
    'remarks' => 'Remarks'
];

/**
 * Filter Preparation and Population
 * 
 * Prepares arrays for dropdown filter values by querying the database for unique values from audit logs
 * related to equipment location changes. This allows users to filter logs by specific attributes.
 */
/**
 * Filter Values for Dropdowns
 * @var array
 */
$filterValues = [
    'building_loc' => [],
    'floor_n' => [],
    'specific_area' => [],
    'device_state' => []
];

/**
 * Case-Insensitive Tracker for Filter Values
 * 
 * Tracks lowercase values to avoid duplicates with different cases in filter dropdowns.
 * @var array
 */
$caseInsensitiveTracker = [
    'building_loc' => [],
    'floor_n' => [],
    'specific_area' => [],
    'device_state' => []
];

$filterQuery = "SELECT NewVal FROM audit_log WHERE Module = 'Equipment Location' AND Status = 'Successful' AND Action IN ('modified', 'create')";
$filterStmt = $pdo->prepare($filterQuery);
$filterStmt->execute();
while ($row = $filterStmt->fetch(PDO::FETCH_ASSOC)) {
    $values = json_decode($row['NewVal'], true);
    foreach ($filterValues as $key => &$arr) {
        if (!empty($values[$key])) {
            // For device_state, use case-insensitive comparison
            $lowerCaseValue = strtolower($values[$key]);
            
            // Check if we've already seen this value (case-insensitive)
            if (!in_array($lowerCaseValue, $caseInsensitiveTracker[$key])) {
                // Capitalize first letter for device_state
                if ($key === 'device_state') {
                    $formattedValue = ucfirst(strtolower($values[$key]));
                    $arr[] = $formattedValue;
                } else {
                    $arr[] = $values[$key];
                }
                $caseInsensitiveTracker[$key][] = $lowerCaseValue;
            }
        }
    }
}

/**
 * Fetch Audit Logs
 * 
 * Retrieves all relevant audit logs for equipment location changes from the database for client-side filtering and pagination.
 */
$sql = "SELECT * FROM audit_log WHERE Module = 'Equipment Location' AND Status = 'Successful' AND Action IN ('modified', 'create') ORDER BY TrackID DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
/**
 * Array of audit log entries fetched from the database.
 * @var array
 */
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .filter-container {
            width: 100%;
            padding: 0;
            margin: 0 auto;
            box-sizing: border-box;
        }

        form.row.g-3 {
            width: 100%;
            margin: 0;
            row-gap: 1rem;
        }

        form .form-select {
            width: 100% !important;
            max-width: 100% !important;
        }

        form .form-control:not(.input-group > .form-control) {
            width: 100% !important;
            max-width: 100% !important;
        }

        @media (max-width: 576px) {
            form .col-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        th.sortable.asc::after {
            content: " ▲";
        }

        th.sortable.desc::after {
            content: " ▼";
        }

        /* Empty state styling for no results */
        .empty-state {
            color: #6c757d;
            /* text-muted */
            font-size: 1.1rem;
        }

        .empty-state i {
            color: #0d6efd;
            /* info color */
        }
    </style>
</head>

<body>
    <div class="main-container">
        <header class="main-header">
            <h1> Asset Location Change Logs</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Asset Location Changes</h2>
            </div>
            <div class="card-body">
                <div class="container-fluid px-0">
                    <div class="filter-container" id="filterContainer">
                        <form id="filterForm" method="GET" class="row g-3 align-items-end mb-4 bg-light p-3 rounded shadow-sm">
                            <?php foreach ($filterValues as $key => $options): ?>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <label class="form-label fw-semibold"><?= $fieldsToShow[$key] ?></label>
                                    <select name="<?= $key ?>" class="form-select shadow-sm">
                                        <option value="">All <?= $fieldsToShow[$key] ?></option>
                                        <?php foreach ($options as $val): ?>
                                            <option value="<?= htmlspecialchars($val) ?>" <?= ($filters[$key] ?? '') === $val ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($val) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>

                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label fw-semibold">Search</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" id="searchInput" class="form-control" placeholder="Search keyword..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold">Date Filter Type</label>
                                <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm">
                                    <option value="" <?= empty($filters['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
                                    <option value="mdy" <?= ($filters['date_filter_type'] ?? '') === 'mdy' ? 'selected' : '' ?>>Month-Day-Year Range</option>
                                    <option value="year" <?= ($filters['date_filter_type'] ?? '') === 'year' ? 'selected' : '' ?>>Year Range</option>
                                    <option value="month_year" <?= ($filters['date_filter_type'] ?? '') === 'month_year' ? 'selected' : '' ?>>Month-Year Range</option>
                                </select>

                            </div>

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

                            <div class="col-6 col-md-2 d-grid">
                                <button type="button" class="btn btn-dark" onclick="window.filterTable()"><i class="bi bi-funnel"></i> Filter</button>
                            </div>

                            <div class="col-6 col-md-2 d-grid">
                                <a href="javascript:void(0)" class="btn btn-secondary shadow-sm" onclick="document.getElementById('filterForm').reset(); document.getElementById('dateFilterType').value = ''; updateDateFields();"><i class="bi bi-x-circle"></i> Clear</a>
                            </div>

                            <div class="col-12 col-md-3 d-grid">
                                <a href="equipment_location.php" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Edit Equipment Location</a>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive" id="table">
                        <table id="auditTable" class="table">
                            <thead>
                                <tr>
                                    <?php foreach ($fieldsToShow as $key => $label): ?>
                                        <th class="sortable" data-sort="string" data-field-key="<?= $key ?>"><?= htmlspecialchars($label) ?></th>
                                    <?php endforeach; ?>
                                    <th class="sortable" data-sort="date">Modified Time</th>
                                </tr>
                            </thead>
                            <tbody id="auditLogTbody"> <?php if (!empty($auditLogs)): ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <?php $newValues = json_decode($log['NewVal'], true); ?>
                                        <tr data-newval="<?= htmlspecialchars(json_encode($newValues)) ?>">
                                            <?php foreach ($fieldsToShow as $key => $label): ?>
                                                <td><?= isset($newValues[$key]) ? htmlspecialchars($newValues[$key]) : '' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= date("Y-m-d H:i:s", strtotime($log['Date_Time'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr id="noResultsMessage" style="display: none;">
                                        <td colspan="<?= count($fieldsToShow) + 1 ?>" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="bi bi-info-circle me-2" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                                <h4>No matching records found</h4>
                                                <p class="text-muted">Try adjusting your search or filter criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="container-fluid">
                        <div class="row align-items-center g-3">
                            <div class="col-12 col-sm-auto">
                                <div class="text-muted">
                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage">20</span> of <span id="totalRows">0</span> entries
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
                            <div class="row mt-3">
                                <div class="col-12">
                                    <ul class="pagination justify-content-center" id="pagination"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</body>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>
<script>
    // Define updateDateFields in global scope so it can be accessed by other functions
    function updateDateFields() {
        const allDateFilters = document.querySelectorAll('.date-filter');
        const filterType = document.getElementById('dateFilterType');
        
        allDateFilters.forEach(field => field.classList.add('d-none'));
        if (!filterType.value) return;

        const selected = document.querySelectorAll('.date-' + filterType.value);
        selected.forEach(field => field.classList.remove('d-none'));
    }

    // date-time filter script (existing function)
    document.addEventListener('DOMContentLoaded', function() {
        const filterType = document.getElementById('dateFilterType');
        
        filterType.addEventListener('change', updateDateFields);
        updateDateFields(); // initial load
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Collect all rows from the table body into window.allRows
        // This must be done AFTER the PHP has rendered the table
        // Ensure to exclude the 'noResultsMessage' row if it exists initially
        window.allRows = Array.from(document.querySelectorAll('#auditLogTbody tr')).filter(row => row.id !== 'noResultsMessage');
        window.pageSpecificTableId = 'auditLogTbody'; // Important for pagination.js to use this specific table's rows

        // Prevent pagination.js from adding auto-filter to search input
        // This must be done BEFORE pagination.js sets up its event listeners
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            // Mark the search input as already having a listener
            // This prevents pagination.js from attaching its auto-filter
            searchInput.dataset.listenerAttached_auditLogTbody = 'true';
            
            // Clone and replace the search input to remove any existing event listeners
            const newSearchInput = searchInput.cloneNode(true);
            searchInput.parentNode.replaceChild(newSearchInput, searchInput);
        }

        // Initialize pagination with the correct table ID
        initPagination({
            tableId: 'auditLogTbody', // Use the actual ID of your table tbody
            currentPage: 1
        });

        // Define global filterTable function
        window.filterTable = function() {
            const buildingLocFilter = document.querySelector('select[name="building_loc"]').value;
            const floorNFilter = document.querySelector('select[name="floor_n"]').value;
            const specificAreaFilter = document.querySelector('select[name="specific_area"]').value;
            const deviceStateFilter = document.querySelector('select[name="device_state"]').value;
            const searchFilter = document.querySelector('input[name="search"]').value.toLowerCase();
            const dateFilterType = document.getElementById('dateFilterType').value;
            const dateFrom = document.querySelector('input[name="date_from"]')?.value;
            const dateTo = document.querySelector('input[name="date_to"]')?.value;
            const monthFrom = document.querySelector('input[name="month_from"]')?.value;
            const monthTo = document.querySelector('input[name="month_to"]')?.value;
            const yearFrom = document.querySelector('input[name="year_from"]')?.value;
            const yearTo = document.querySelector('input[name="year_to"]')?.value;
            const monthYearFrom = document.querySelector('input[name="month_year_from"]')?.value;
            const monthYearTo = document.querySelector('input[name="month_year_to"]')?.value;

            window.filteredRows = window.allRows.filter(row => {
                // Parse the NewVal JSON from the data attribute
                const newValuesJson = JSON.parse(row.dataset.newval);
                // Get the date from the last cell (Modified Time)
                const dateTime = new Date(row.cells[row.cells.length - 1].innerText);

                let match = true;

                // Apply dropdown filters
                if (buildingLocFilter && newValuesJson.building_loc !== buildingLocFilter) match = false;
                if (floorNFilter && newValuesJson.floor_n !== floorNFilter) match = false;
                if (specificAreaFilter && newValuesJson.specific_area !== specificAreaFilter) match = false;
                if (deviceStateFilter && (newValuesJson.device_state !== deviceStateFilter && 
                                         newValuesJson.device_state?.toLowerCase() !== deviceStateFilter.toLowerCase())) match = false;

                // Apply search filter across all relevant fields
                if (searchFilter && match) {
                    let searchMatch = false;
                    const fieldsToShowKeys = [
                        'asset_tag', 'building_loc', 'floor_n', 'specific_area',
                        'person_responsible', 'device_state', 'remarks'
                    ];
                    for (const key of fieldsToShowKeys) {
                        if (newValuesJson[key] && String(newValuesJson[key]).toLowerCase().includes(searchFilter)) {
                            searchMatch = true;
                            break;
                        }
                    }
                    if (!searchMatch) match = false;
                }

                // Apply date filters
                if (match && dateFilterType) {
                    if (dateFilterType === 'mdy') {
                        const fromDate = dateFrom ? new Date(dateFrom + 'T00:00:00') : null;
                        const toDate = dateTo ? new Date(dateTo + 'T23:59:59') : null;
                        if (fromDate && dateTime < fromDate) match = false;
                        if (toDate && dateTime > toDate) match = false;
                    } else if (dateFilterType === 'month') {
                        const fromMonth = monthFrom ? new Date(monthFrom + '-01T00:00:00') : null;
                        // Calculate the last day of the 'toMonth' to include all days in that month
                        const toMonthDate = monthTo ? new Date(monthTo + '-01T00:00:00') : null;
                        const toMonthLastDay = toMonthDate ? new Date(toMonthDate.getFullYear(), toMonthDate.getMonth() + 1, 0, 23, 59, 59, 999) : null;

                        if (fromMonth && dateTime < fromMonth) match = false;
                        if (toMonthLastDay && dateTime > toMonthLastDay) match = false;
                    } else if (dateFilterType === 'year') {
                        const fromYear = yearFrom ? new Date(yearFrom + '-01-01T00:00:00') : null;
                        const toYear = yearTo ? new Date(yearTo + '-12-31T23:59:59') : null;
                        if (fromYear && dateTime < fromYear) match = false;
                        if (toYear && dateTime > toYear) match = false;
                    } else if (dateFilterType === 'month_year') {
                        const fromMonthYear = monthYearFrom ? new Date(monthYearFrom + '-01T00:00:00') : null;
                        // Calculate the last day of the 'toMonthYear' to include all days in that month
                        const toMonthYearDate = monthYearTo ? new Date(monthYearTo + '-01T00:00:00') : null;
                        const toMonthYearLastDay = toMonthYearDate ? new Date(toMonthYearDate.getFullYear(), toMonthYearDate.getMonth() + 1, 0, 23, 59, 59, 999) : null;

                        if (fromMonthYear && dateTime < fromMonthYear) match = false;
                        if (toMonthYearLastDay && dateTime > toMonthYearLastDay) match = false;
                    }
                }
                return match;
            });

            // Reset current page to 1 after filtering
            if (typeof paginationConfig !== 'undefined') {
                paginationConfig.currentPage = 1;
            }
            updatePagination();
        };

        // Set flag to indicate this is a page-specific filterTable function
        // This prevents pagination.js from using its generic filter
        window.filterTable.isPageSpecific = true;

        // Update clear button to also trigger filter function after clearing
        document.querySelector('.btn-secondary').addEventListener('click', function() {
            document.getElementById('filterForm').reset();
            document.getElementById('dateFilterType').value = '';
            updateDateFields();
            
            // Reset table to show all data
            window.filteredRows = [...window.allRows];
            
            // Reset to page 1 and update pagination
            if (typeof paginationConfig !== 'undefined') {
                paginationConfig.currentPage = 1;
            }
            updatePagination();
        });

        // Initial filter to display all data on load without any filtering
        window.filteredRows = [...window.allRows];
        updatePagination();
    });

    // Sorting logic adapted for client-side pagination
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("#auditTable th.sortable").forEach(th => {
            th.style.cursor = "pointer";
            th.addEventListener("click", function() {
                const table = th.closest("table");
                const tbody = table.querySelector("tbody");
                const rowsToSort = [...window.allRows]; // Create a copy to sort from the full dataset

                const index = Array.from(th.parentNode.children).indexOf(th);
                const type = th.dataset.sort || "string";
                const fieldKey = th.dataset.fieldKey; // Get the original field key for data-newval lookup
                const asc = !th.classList.contains("asc");

                rowsToSort.sort((a, b) => {
                    let x, y;

                    if (type === "date") { // For 'Modified Time' column
                        x = new Date(a.children[index].innerText.trim());
                        y = new Date(b.children[index].innerText.trim());
                        const timeX = isNaN(x.getTime()) ? (asc ? Infinity : -Infinity) : x.getTime();
                        const timeY = isNaN(y.getTime()) ? (asc ? Infinity : -Infinity) : y.getTime();
                        return asc ? (timeX - timeY) : (timeY - timeX);
                    } else { // For other columns, parse from the data-newval attribute
                        const aNewVal = JSON.parse(a.dataset.newval);
                        const bNewVal = JSON.parse(b.dataset.newval);

                        x = aNewVal[fieldKey] !== undefined ? String(aNewVal[fieldKey]).trim() : '';
                        y = bNewVal[fieldKey] !== undefined ? String(bNewVal[fieldKey]).trim() : '';

                        if (type === "number") {
                            x = parseFloat(x) || 0;
                            y = parseFloat(y) || 0;
                        } else {
                            x = x.toLowerCase();
                            y = y.toLowerCase();
                        }
                        return asc ? (x > y ? 1 : -1) : (x < y ? 1 : -1);
                    }
                });

                window.allRows = rowsToSort; // Update the global allRows with the sorted order
                table.querySelectorAll("th").forEach(t => t.classList.remove("asc", "desc"));
                th.classList.add(asc ? "asc" : "desc");
                window.filterTable(); // Re-filter and paginate based on the new sorted order
            });
        });
    });
</script>
