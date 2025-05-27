<?php
session_start();
require '../../../../../config/ims-tmdd.php';
include '../../general/header.php';
include '../../general/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

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

$fieldsToShow = [
    'asset_tag' => 'Asset Tag',
    'building_loc' => 'Building Location',
    'floor_n' => 'Floor',
    'specific_area' => 'Specific Area',
    'person_responsible' => 'Person Responsible',
    'device_state' => 'Device State',
    'remarks' => 'Remarks'
];

// Prepare dropdown filter values
$filterValues = [
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
        if (!empty($values[$key]) && !in_array($values[$key], $arr)) {
            $arr[] = $values[$key];
        }
    }
}

// Fetch all audit logs (or a pre-filtered subset if performance is an issue for very large datasets)
// For client-side pagination, we fetch all relevant logs first.
$conditions = [
    "audit_log.Module = 'Equipment Location'",
    "audit_log.Status = 'Successful'",
    "audit_log.Action IN ('modified', 'create')"
];

$whereClause = implode(" AND ", $conditions);
$query = "SELECT * FROM audit_log WHERE $whereClause ORDER BY TrackID DESC"; // Initial order
$stmt = $pdo->prepare($query);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// IMPORTANT: Encode the PHP data directly into a JavaScript variable
$initialAuditLogsJson = json_encode($auditLogs);
?>

<link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .btn-group .btn.active {
        background-color: #0d6efd;
        color: white;
    }
    /* Styles for pagination controls */
    .pagination-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        flex-wrap: wrap; /* Allow wrapping on smaller screens */
    }
    .pagination-info {
        display: flex;
        align-items: center;
        margin-bottom: 10px; /* Space out on smaller screens */
    }
    .pagination-info select {
        margin: 0 5px;
    }
    .pagination-buttons .btn {
        margin-left: 5px;
    }
    .pagination {
        margin-bottom: 0; /* Remove default margin from ul.pagination */
    }
    /* Responsive adjustments for pagination controls */
    @media (max-width: 768px) {
        .pagination-controls {
            flex-direction: column;
            align-items: flex-start;
        }
        .pagination-info, .pagination-buttons {
            width: 100%;
            justify-content: center; /* Center buttons/info on small screens */
            margin-bottom: 10px;
        }
        .pagination {
            justify-content: center;
            width: 100%;
        }
    }

    /* Styles for date filter inputs */
    .date-inputs-container {
        display: none; /* Hidden by default */
        margin-top: 10px;
        padding: 10px;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        background-color: #f9f9f9;
        flex-wrap: wrap;
        gap: 10px;
    }
    .month-picker-container, .date-range-container {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .month-picker-container select, .date-range-container input {
        flex: 1; /* Allow inputs to grow */
        min-width: 120px; /* Ensure they don't get too small */
    }

    /* Select2 custom styling to match other filter elements */
    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 5px;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px;
        color: #212529;
        padding-left: 8px;
    }

    /* Make all filter elements have consistent height */
    .filter-container select, 
    .filter-container input,
    .filter-container .select2-container {
        height: calc(1.5em + 0.75rem + 2px);
    }

    /* Style the filter container elements for consistent spacing */
    .filter-container > div {
        padding: 0 8px;
    }
    
    /* Ensure Select2 container is properly aligned */
    .filter-container .select2-container--default .select2-selection--single {
        display: flex;
        align-items: center;
    }
    
    /* Match Select2 dropdown to Bootstrap form-select style */
    .select2-dropdown {
        border-color: #ced4da;
    }
    
    /* Match dropdown item styling */
    .select2-container--default .select2-results__option {
        padding: 6px 12px;
    }

    th.sortable.asc::after {
        content: " ▲";
    }

    th.sortable.desc::after {
        content: " ▼";
    }
</style>

<div class="container-fluid">
</head> 
<body>
<div class="main-container">
        <header class="main-header">
            <h1> Equipment Location Change Logs</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Equipment Locations</h2>
            </div>
            <div class="card-body">
                <div class="container-fluid px-0">
                    <div class="filter-container d-flex flex-wrap align-items-center mb-3" id="filterContainer">
                        <div class="col-auto me-2">
                            </div>
                        <div class="col-md-2 col-sm-6 mb-2">
                            <select class="form-select" id="filterBuilding">
                                <option value="">Filter by Building</option>
                                <option value="all">All Buildings</option>
                                <?php
                                if (!empty($filterValues['building_loc'])) {
                                    foreach ($filterValues['building_loc'] as $building) {
                                        echo "<option>" . htmlspecialchars($building) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb-2">
                            <select class="form-select" id="dateFilter">
                                <option value="">Filter by Date</option>
                                <option value="desc">Newest to Oldest</option>
                                <option value="asc">Oldest to Newest</option>
                                <option value="month">Specific Month</option>
                                <option value="range">Custom Date Range</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-12 mb-2">
                            <div class="input-group">
                                <input type="text" id="eqSearch" class="form-control" placeholder="Search Asset/Person/Remarks...">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                            </div>
                        </div>
                        <div class="col-auto ms-auto mb-2">
                            <a href="equipment_location.php" class="btn btn-primary"> Edit Equipment Location</a>
                        </div>
                        <div class="col-auto mb-2">
                            <button id="resetFilters" class="btn btn-outline-secondary">Reset Filters</button>
                        </div>
                    </div>

                    <div id="dateInputsContainer" class="date-inputs-container">
                        <div class="month-picker-container" id="monthPickerContainer">
                            <select class="form-select" id="monthSelect">
                                <option value="">Select Month</option>
                                <?php
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                foreach ($months as $index => $month) {
                                    echo "<option value='" . ($index + 1) . "'>" . $month . "</option>";
                                }
                                ?>
                            </select>
                            <select class="form-select" id="yearSelect">
                                <option value="">Select Year</option>
                                <?php
                                $currentYear = date('Y');
                                for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                    echo "<option value='" . $year . "'>" . $year . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="date-range-container" id="dateRangePickers">
                            <input type="date" class="form-control" id="dateFrom" placeholder="From">
                            <input type="date" class="form-control" id="dateTo" placeholder="To">
                        </div>
                    </div>
                </div>

                <div class="table-responsive" id="table">
                    <table id="elTable" class ="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="string">Asset Tag</th>
                                <th class="sortable" data-sort="string">Building Location</th>
                                <th class="sortable" data-sort="string">Floor</th>
                                <th class="sortable" data-sort="string">Specific Area</th>
                                <th class="sortable" data-sort="string">Person Responsible</th>
                                <th class="sortable" data-sort="string">Device State</th>
                                <th class="sortable" data-sort="string">Remarks</th>
                                <th class="sortable" data-sort="date">Modified Time</th>
                            </tr>
                        </thead>
                        <tbody id="auditTable"> 
                            <tr id="noInitialResultsMessage" style="display: none;">
                                <td colspan="<?= count($fieldsToShow) + 1 ?>" class="text-center py-4">
                                    <div class="alert alert-info mb-0">
                                        <i class="bi bi-info-circle me-2"></i> No Equipment Location records found.
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="container-fluid mt-3">
                    <div class="row align-items-center g-3">
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">0</span> to <span id="rowsPerPage">0</span> of <span id="totalRows">0</span> entries
                            </div>
                        </div>
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </button>
                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button id="nextPage" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                                    Next <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <ul class="pagination justify-content-center" id="pagination">
                                    </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="<?php echo BASE_URL; ?>src/control/js/pagination.js"></script>

<script>
// Store the PHP-fetched data globally for JavaScript access
window.initialAuditLogsData = <?php echo $initialAuditLogsJson; ?>;
console.log("equipLoc_change_log.php: Raw PHP data loaded into window.initialAuditLogsData:", window.initialAuditLogsData.length, "entries.");

document.addEventListener("DOMContentLoaded", function () {
    console.log("equipLoc_change_log.php: DOMContentLoaded fired.");

    // Populate window.allRows from the pre-loaded PHP data
    // We need to convert the raw data objects into simulated TR elements
    window.allRows = window.initialAuditLogsData.map(log => {
        const tr = document.createElement('tr');
        const newValues = JSON.parse(log.NewVal); // Parse the NewVal JSON string
        
        // Match the order of your <th> elements
        const fields = [
            newValues.asset_tag,
            newValues.building_loc,
            newValues.floor_n,
            newValues.specific_area,
            newValues.person_responsible,
            newValues.device_state,
            newValues.remarks,
            log.Date_Time // Use the raw Date_Time for sorting
        ];

        fields.forEach(field => {
            const td = document.createElement('td');
            td.textContent = field !== undefined && field !== null ? field : ''; // Handle undefined/null
            tr.appendChild(td);
        });
        return tr;
    });

    console.log(`equipLoc_change_log.php: Initial window.allRows populated from data: ${window.allRows.length} rows.`, window.allRows);

    // Set a flag or identifier for the current page's table ID
    window.pageSpecificTableId = 'auditTable';

    // Initialize pagination with the correct table ID
    console.log("equipLoc_change_log.php: Calling initPagination...");
    initPagination({
        tableId: 'auditTable', // The ID of your tbody
        rowsPerPageSelectId: 'rowsPerPageSelect',
        currentPageId: 'currentPage',
        rowsPerPageId: 'rowsPerPage',
        totalRowsId: 'totalRows',
        prevPageId: 'prevPage',
        nextPageId: 'nextPage',
        paginationId: 'pagination'
    });
    console.log("equipLoc_change_log.php: initPagination call completed.");


    // Define the page-specific filterTable function
    window.filterTable = function() {
        console.log('equipLoc_change_log.php: Page-specific filterTable called.');
        window.filterTable.isPageSpecific = true; // Mark as page-specific

        const searchText = document.getElementById('eqSearch')?.value.toLowerCase() || '';
        const filterBuilding = document.getElementById('filterBuilding')?.value.toLowerCase() || '';
        const dateFilterType = document.getElementById('dateFilter')?.value.toLowerCase() || '';
        const selectedMonth = document.getElementById('monthSelect')?.value || '';
        const selectedYear = document.getElementById('yearSelect')?.value || '';
        const dateFrom = document.getElementById('dateFrom')?.value || '';
        const dateTo = document.getElementById('dateTo')?.value || '';

        console.log('Filter values:', {searchText, filterBuilding, dateFilterType, selectedMonth, selectedYear, dateFrom, dateTo});

        // Use the globally stored allRows for filtering
        let rowsToFilter = window.allRows || [];
        console.log(`Page-specific filterTable: Filtering from ${rowsToFilter.length} total rows.`);

        let tempFilteredRows = rowsToFilter.filter(row => {
            if (!row || row.tagName !== 'TR') return false; // Ensure it's a valid row element

            const cells = Array.from(row.children);
            // Ensure you have enough cells before accessing them
            // Columns: Asset Tag, Building, Floor, Area, Person, Device State, Remarks, Modified Time (8 columns)
            if (cells.length < 8) { 
                console.warn("Row has fewer cells than expected, skipping:", row);
                return false;
            }

            const rowData = {
                asset_tag: cells[0]?.textContent.toLowerCase() || '',
                building_loc: cells[1]?.textContent.toLowerCase() || '',
                floor_n: cells[2]?.textContent.toLowerCase() || '',
                specific_area: cells[3]?.textContent.toLowerCase() || '',
                person_responsible: cells[4]?.textContent.toLowerCase() || '',
                device_state: cells[5]?.textContent.toLowerCase() || '',
                remarks: cells[6]?.textContent.toLowerCase() || '',
                date_time: cells[7]?.textContent || '' // Modified Time
            };
            // console.log("Processing rowData:", rowData);

            // Apply search filter across relevant fields
            const searchMatch = !searchText ||
                                  rowData.asset_tag.includes(searchText) ||
                                  rowData.person_responsible.includes(searchText) ||
                                  rowData.remarks.includes(searchText);
            
            // Apply building filter
            let buildingMatch = true;
            if (filterBuilding && filterBuilding !== 'all' && filterBuilding !== 'filter by building') {
                buildingMatch = rowData.building_loc === filterBuilding;
            }
            
            // Apply date filter
            let dateMatch = true;
            const rowDate = rowData.date_time ? new Date(rowData.date_time) : null;

            if (rowDate && dateFilterType) {
                if (dateFilterType === 'month' && selectedMonth && selectedYear) {
                    if (!isNaN(rowDate.getTime())) {
                        dateMatch = (rowDate.getMonth() + 1 === parseInt(selectedMonth)) && 
                                    (rowDate.getFullYear() === parseInt(selectedYear));
                    } else {
                        dateMatch = false;
                    }
                } else if (dateFilterType === 'range' && dateFrom && dateTo) {
                    const from = new Date(dateFrom);
                    const to = new Date(dateTo);
                    to.setHours(23, 59, 59, 999); 
                    if (!isNaN(rowDate.getTime()) && !isNaN(from.getTime()) && !isNaN(to.getTime())) {
                        dateMatch = rowDate >= from && rowDate <= to;
                    } else {
                        dateMatch = false;
                    }
                }
            }

            return searchMatch && buildingMatch && dateMatch;
        });

        // Apply sorting after filtering if a date sorting type is selected
        if (dateFilterType === 'asc' || dateFilterType === 'desc') {
            console.log(`Applying date sort: ${dateFilterType}`);
            tempFilteredRows.sort((a, b) => {
                // Get the date string from the 'Modified Time' column (index 7)
                const dateA = a.cells && a.cells[7] ? new Date(a.cells[7].textContent) : new Date(0);
                const dateB = b.cells && b.cells[7] ? new Date(b.cells[7].textContent) : new Date(0);
                
                const timeA = isNaN(dateA.getTime()) ? (dateFilterType === 'asc' ? Infinity : -Infinity) : dateA.getTime();
                const timeB = isNaN(dateB.getTime()) ? (asc ? Infinity : -Infinity) : dateB.getTime();

                return dateFilterType === 'asc' ? (timeA - timeB) : (timeB - timeA);
            });
        }

        window.filteredRows = tempFilteredRows;
        console.log(`Page-specific filterTable: Filtered down to ${window.filteredRows.length} rows.`);
        
        const rppSelect = document.getElementById('rowsPerPageSelect'); 
        console.log("filterTable Debug: window.filteredRows.length BEFORE updatePagination:", window.filteredRows.length);
        console.log("filterTable Debug: rowsPerPageSelect value BEFORE updatePagination:", rppSelect ? rppSelect.value : 'N/A');

        if (typeof paginationConfig !== 'undefined') {
            paginationConfig.currentPage = 1; // Reset to first page on filter/sort
            console.log("filterTable: Resetting currentPage to 1.");
        } else {
            console.error("filterTable: paginationConfig is undefined! Cannot reset currentPage.");
            return;
        }
        
        if (typeof updatePagination === 'function') {
            console.log("filterTable: Calling updatePagination...");
            updatePagination();
        } else {
            console.error("filterTable: updatePagination function not found! Cannot update pagination.");
        }
        
        console.log('FilterTable completed. Final filtered rows count:', window.filteredRows.length);
    };

    // Attach event listeners for filter inputs
    document.getElementById('eqSearch')?.addEventListener('input', window.filterTable);
    document.getElementById('filterBuilding')?.addEventListener('change', window.filterTable);
    document.getElementById('dateFilter')?.addEventListener('change', function() {
        console.log("Date filter type changed:", this.value);
        const filterType = this.value;
        document.getElementById('dateInputsContainer').style.display = 'none';
        document.getElementById('monthPickerContainer').style.display = 'none';
        document.getElementById('dateRangePickers').style.display = 'none';

        // Reset other date filter inputs when type changes
        document.getElementById('monthSelect').value = '';
        document.getElementById('yearSelect').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';

        if (filterType === 'month') {
            document.getElementById('dateInputsContainer').style.display = 'flex';
            document.getElementById('monthPickerContainer').style.display = 'flex';
        } else if (filterType === 'range') {
            document.getElementById('dateInputsContainer').style.display = 'flex';
            document.getElementById('dateRangePickers').style.display = 'flex';
        }
        window.filterTable(); // Trigger filter after changing date filter type
    });
    document.getElementById('monthSelect')?.addEventListener('change', window.filterTable);
    document.getElementById('yearSelect')?.addEventListener('change', window.filterTable);
    document.getElementById('dateFrom')?.addEventListener('change', window.filterTable);
    document.getElementById('dateTo')?.addEventListener('change', window.filterTable);

    // Reset Filters button handler
    document.getElementById('resetFilters')?.addEventListener('click', function() {
        console.log("Reset Filters button clicked.");
        document.getElementById('eqSearch').value = '';
        document.getElementById('filterBuilding').value = ''; // Reset select to default option
        document.getElementById('dateFilter').value = ''; // Reset date filter type
        document.getElementById('monthSelect').value = '';
        document.getElementById('yearSelect').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        document.getElementById('dateInputsContainer').style.display = 'none'; // Hide date inputs
        window.filterTable(); // Apply reset filters
    });

    // Column sorting logic
    document.querySelectorAll("#elTable th.sortable").forEach(th => {
        th.style.cursor = "pointer";
        th.addEventListener("click", function() {
            console.log("Column header clicked for sorting:", th.textContent);
            const table = th.closest("table");
            const index = Array.from(th.parentNode.children).indexOf(th);
            const type = th.dataset.sort || "string";
            const asc = !th.classList.contains("asc");

            // Sort the original allRows, then re-filter and paginate
            let rowsToSort = [...window.allRows]; 

            rowsToSort.sort((a, b) => {
                let x = a.children[index].innerText.trim();
                let y = b.children[index].innerText.trim();

                if (type === "number") {
                    x = parseFloat(x) || 0;
                    y = parseFloat(y) || 0;
                } else if (type === "date") {
                    x = new Date(x);
                    y = new Date(y);
                    const timeX = isNaN(x.getTime()) ? (asc ? Infinity : -Infinity) : x.getTime();
                    const timeY = isNaN(y.getTime()) ? (asc ? Infinity : -Infinity) : y.getTime();
                    return asc ? (timeX - timeY) : (timeB - timeA);
                } else {
                    x = x.toLowerCase();
                    y = y.toLowerCase();
                }

                return asc ? (x > y ? 1 : -1) : (x < y ? 1 : -1);
            });

            // Update allRows with the sorted order
            window.allRows = rowsToSort;
            console.log("window.allRows updated after sorting. New length:", window.allRows.length);
            
            // Clear existing sort classes and apply new one
            table.querySelectorAll("th").forEach(t => t.classList.remove("asc", "desc"));
            th.classList.add(asc ? "asc" : "desc");
            
            // Re-apply filters and update pagination based on the newly sorted allRows
            window.filterTable(); // Call the main filter function
        });
    });

    // Initial filter and pagination update after all is set up
    console.log("equipLoc_change_log.php: Setting timeout for initial filterTable call.");
    setTimeout(window.filterTable, 100);
});

// Initialize Select2 for the building filter (optional, but good for consistency)
$(document).ready(function() {
    console.log("equipLoc_change_log.php: jQuery document ready, initializing Select2 for filterBuilding.");
    if ($.fn.select2) {
        try {
            if ($('#filterBuilding').data('select2')) {
                $('#filterBuilding').select2('destroy');
            }
            $('#filterBuilding').select2({
                placeholder: 'Filter by Building',
                allowClear: true,
                width: '100%',
                dropdownAutoWidth: true,
                minimumResultsForSearch: 0,
                dropdownParent: $('#filterBuilding').parent(),
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    return $('<span>').text(data.text).addClass('py-1');
                },
                templateSelection: function(data) {
                    return $('<span>').text(data.text).addClass('py-1');
                }
            });
            console.log("equipLoc_change_log.php: Select2 initialized for filterBuilding.");
        } catch (e) {
            console.error('equipLoc_change_log.php: Error initializing Select2 for filterBuilding:', e);
        }
    } else {
        console.warn("equipLoc_change_log.php: Select2 plugin not found. Ensure it's loaded before this script.");
    }
});
</script>
</body>
</html>
