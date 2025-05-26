// Global variable to store the original table rows.
// This will be populated by logs.js and used as the source for filtering.
let allRows = window.allRows || [];

// Global variable to store the currently filtered rows.
// This will be updated by filterTable and used by updatePagination.
window.filteredRows = [];

// Configuration object with default values
let paginationConfig = {
    tableId: 'auditTable',      // Default table ID
    currentPage: 1,             // Current page
    rowsPerPageSelectId: 'rowsPerPageSelect',
    currentPageId: 'currentPage',
    rowsPerPageId: 'rowsPerPage',
    totalRowsId: 'totalRows',
    prevPageId: 'prevPage',
    nextPageId: 'nextPage',
    paginationId: 'pagination'
};

// Function to initialize pagination with a specific table
function initPagination(config = {}) {
    console.log('pagination.js: initPagination called with config:', config);
    // Update config with any user-specified values
    Object.assign(paginationConfig, config);
    
    // Ensure allRows is populated from window.allRows if it exists,
    // otherwise populate from the table initially. This handles cases
    // where logs.js might not have run yet, or for other tables.
    const tableBody = document.getElementById(paginationConfig.tableId);
    if (tableBody) {
        if (!window.allRows || window.allRows.length === 0) {
             // Fallback if logs.js hasn't populated it yet (less likely with defer, but good to have)
             window.allRows = Array.from(tableBody.querySelectorAll('tr'));
             console.log(`pagination.js: Populated window.allRows as fallback with ${window.allRows.length} rows.`);
        }
        allRows = window.allRows; // pagination.js uses its local allRows reference
        console.log(`pagination.js: Local allRows synchronized, total ${allRows.length} rows.`);
    } else {
        console.error(`pagination.js: Table body with ID ${paginationConfig.tableId} not found during initPagination.`);
    }
    
    // Initialize filters and set up event listeners
    setupEventListeners();
    
    // Perform an initial filter and update pagination
    filterTable(); // Call filterTable to apply initial filters and populate filteredRows
    
    console.log('pagination.js: initPagination completed.');
    return {
        update: updatePagination,
        getConfig: () => paginationConfig,
        setConfig: (config) => Object.assign(paginationConfig, config)
    };
}

// Re-added the DOMContentLoaded listener with a check to prevent double initialization.
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if a page hasn't explicitly initialized it already
    if (!window.paginationInitialized) { 
        console.log('pagination.js: DOMContentLoaded fallback triggered. Initializing with default config.');
        initPagination(); // Call initPagination with default config
    } else {
        console.log('pagination.js: DOMContentLoaded fallback skipped. Pagination already initialized by page script.');
    }
});


// Sets up event listeners for all filter inputs and pagination controls.
function setupEventListeners() {
    console.log('pagination.js: setupEventListeners called.');
    const searchInput = document.getElementById('searchInput');
    const filterAction = document.getElementById('filterAction');
    const filterStatus = document.getElementById('filterStatus');
    const filterModule = document.getElementById('filterModule'); // Added module filter listener

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
        console.log('pagination.js: Added listener to searchInput.');
    }

    if (filterAction) {
        filterAction.addEventListener('change', filterTable);
        console.log('pagination.js: Added listener to filterAction.');
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', filterTable);
        console.log('pagination.js: Added listener to filterStatus.');
    }

    if (filterModule) { // Added module filter listener
        filterModule.addEventListener('change', filterTable);
        console.log('pagination.js: Added listener to filterModule.');
    }

    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => {
            console.log('pagination.js: rowsPerPageSelect changed.');
            paginationConfig.currentPage = 1; // Reset to first page on rows per page change
            updatePagination();
        });
        console.log('pagination.js: Added listener to rowsPerPageSelect.');
    }

    const prevButton = document.getElementById(paginationConfig.prevPageId);
    const nextButton = document.getElementById(paginationConfig.nextPageId);

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            console.log('pagination.js: Previous button clicked. Current page:', paginationConfig.currentPage);
            if (paginationConfig.currentPage > 1) {
                paginationConfig.currentPage--;
                updatePagination();
            }
        });
        console.log('pagination.js: Added listener to prevButton.');
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            console.log('pagination.js: Next button clicked. Current page:', paginationConfig.currentPage);
            const totalPages = getTotalPages();
            if (paginationConfig.currentPage < totalPages) {
                paginationConfig.currentPage++;
                updatePagination();
            }
        });
        console.log('pagination.js: Added listener to nextButton.');
    }
}

// Rebuilds the table body with only the rows that match the filters.
// This function now centralizes all filtering logic.
function filterTable() {
    console.log('pagination.js: filterTable called.');
    const searchFilter = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const actionFilter = document.getElementById('filterAction')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('filterStatus')?.value.toLowerCase() || '';
    const moduleFilter = document.getElementById('filterModule')?.value.toLowerCase() || '';
    
    // Use window.allRows (the original, unfiltered rows) for filtering
    const rowsToFilter = window.allRows || [];
    console.log(`filterTable: Filtering from ${rowsToFilter.length} total rows.`);

    // Filter rows from the original set
    const filteredRows = rowsToFilter.filter(row => {
        // For debugging purposes
        if (!row) return false;
        
        // Skip header row if it somehow gets into allRows
        if (row.querySelector('th')) return false;
        
        let matchesSearch = true;
        let matchesAction = true;
        let matchesStatus = true;
        let matchesModule = true;
        
        // Search filter (applies to all cells)
        if (searchFilter) {
            matchesSearch = row.textContent.toLowerCase().includes(searchFilter);
        }
        
        // Action filter
        if (actionFilter) {
            const actionCell = row.querySelector('[data-label="Action"]');
            if (actionCell) {
                // Get text from the action badge, which contains the action name
                const actionBadge = actionCell.querySelector('.action-badge');
                const actionText = actionBadge ? 
                    actionBadge.textContent.toLowerCase().trim() : 
                    actionCell.textContent.toLowerCase().trim();
                
                // Convert both to lowercase and trim for comparison
                matchesAction = actionText.includes(actionFilter);
                
            } else {
                matchesAction = false;
            }
        }
        
        // Status filter
        if (statusFilter) {
            const statusCell = row.querySelector('[data-label="Status"]');
            if (statusCell) {
                // Get text from the status badge
                const statusBadge = statusCell.querySelector('.badge');
                const statusText = statusBadge ? 
                    statusBadge.textContent.toLowerCase().trim() : 
                    statusCell.textContent.toLowerCase().trim();
                
                // Handle status cases
                if (statusFilter === 'successful') {
                    matchesStatus = statusText.includes('successful');
                } else if (statusFilter === 'failed') {
                    matchesStatus = statusText.includes('failed');
                } else {
                    matchesStatus = statusText.includes(statusFilter);
                }
                
            } else {
                matchesStatus = false;
            }
        }
        
        // Module filter (specific to logs.js original implementation)
        if (moduleFilter) {
            const moduleCell = row.querySelector('[data-label="Module"]');
            if (moduleCell) {
                const moduleText = moduleCell.textContent.toLowerCase().trim();
                matchesModule = moduleText.includes(moduleFilter);
            } else {
                matchesModule = false;
            }
        }
        
        return matchesSearch && matchesAction && matchesStatus && matchesModule;
    });
    
    // Store filtered rows in window for pagination to use
    window.filteredRows = filteredRows;
    console.log(`filterTable: Filtered down to ${window.filteredRows.length} rows.`);
    
    // Reset to the first page after filtering
    paginationConfig.currentPage = 1;
    
    // Update pagination based on the new filtered set
    updatePagination();
}

// Updates the display of table rows and pagination controls based on current page and filters.
function updatePagination() {
    console.log('pagination.js: updatePagination called. Current Page:', paginationConfig.currentPage);
    const tbody = document.getElementById(paginationConfig.tableId);
    if (!tbody) {
        console.error(`Could not find tbody with ID ${paginationConfig.tableId}`);
        return;
    }
    
    // Use window.filteredRows for pagination
    const rowsToPaginate = window.filteredRows || [];
    const totalRows = rowsToPaginate.length;
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

    console.log(`updatePagination: Total Rows: ${totalRows}, Rows Per Page: ${rowsPerPage}, Total Pages: ${totalPages}`);

    // Adjust current page if it's out of bounds after filtering
    paginationConfig.currentPage = Math.min(paginationConfig.currentPage, totalPages);
    if (paginationConfig.currentPage < 1 && totalPages >= 1) {
        paginationConfig.currentPage = 1;
    } else if (totalPages === 0) {
        paginationConfig.currentPage = 0; // No pages if no rows
    }
    console.log('updatePagination: Adjusted Current Page:', paginationConfig.currentPage);


    const start = (paginationConfig.currentPage - 1) * rowsPerPage;
    const end = paginationConfig.currentPage * rowsPerPage;
    console.log(`updatePagination: Displaying rows from index ${start} to ${end}.`);

    // Clear the tbody before adding the current page's rows
    tbody.innerHTML = '';
    
    // If no filtered rows, show a "no results" message
    if (totalRows === 0) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.innerHTML = `
            <td colspan="10">
                <div class="empty-state text-center py-4">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <h4>No matching records found</h4>
                    <p class="text-muted">Try adjusting your search or filter criteria.</p>
                </div>
            </td>
        `;
        tbody.appendChild(noResultsRow);
        console.log('updatePagination: Displaying "No matching records found".');
    } else {
        // Add only the rows for the current page
        rowsToPaginate.slice(start, end).forEach(row => {
            tbody.appendChild(row.cloneNode(true)); // Append a clone to avoid moving nodes
        });
        console.log(`updatePagination: Appended ${rowsToPaginate.slice(start, end).length} rows to tbody.`);
    }

    // Update display text for "Showing X to Y of Z entries"
    const currentPageEl = document.getElementById(paginationConfig.currentPageId);
    if (currentPageEl) currentPageEl.textContent = totalRows === 0 ? 0 : start + 1;
    
    const rowsPerPageEl = document.getElementById(paginationConfig.rowsPerPageId);
    if (rowsPerPageEl) rowsPerPageEl.textContent = Math.min(end, totalRows);
    
    const totalRowsEl = document.getElementById(paginationConfig.totalRowsId);
    if (totalRowsEl) totalRowsEl.textContent = totalRows;

    // Disable/enable Prev/Next buttons
    const prevPageEl = document.getElementById(paginationConfig.prevPageId);
    if (prevPageEl) prevPageEl.disabled = (paginationConfig.currentPage <= 1);
    
    const nextPageEl = document.getElementById(paginationConfig.nextPageId);
    if (nextPageEl) nextPageEl.disabled = (paginationConfig.currentPage >= totalPages);

    // Render pagination links (1, 2, 3, ..., N)
    renderPaginationControls(totalPages);
    console.log('updatePagination: Finished rendering pagination controls.');
}

// Calculates the total number of pages based on filtered rows and rows per page.
function getTotalPages() {
    const rowsToPaginate = window.filteredRows || [];
    const totalRows = rowsToPaginate.length;
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    return Math.ceil(totalRows / rowsPerPage) || 1;
}

// Renders the pagination links (e.g., 1, 2, ..., 5, 6).
function renderPaginationControls(totalPages) {
    console.log('pagination.js: renderPaginationControls called. Total Pages:', totalPages);
    const paginationContainer = document.getElementById(paginationConfig.paginationId);
    if (!paginationContainer) {
        console.error(`Could not find pagination container with ID ${paginationConfig.paginationId}`);
        return;
    }

    paginationContainer.innerHTML = ''; // Clear existing pagination links

    if (totalPages <= 1) {
        console.log('renderPaginationControls: Only one page, no pagination links needed.');
        return; // No pagination needed for 1 or fewer pages
    }

    // Always show first page
    addPaginationItem(paginationContainer, 1, paginationConfig.currentPage === 1);

    // Show ellipses and a window of pages around current page
    const maxVisiblePages = 5; // Adjust to show more or fewer pages in the control
    const halfWindow = Math.floor(maxVisiblePages / 2);
    
    let startPage = Math.max(2, paginationConfig.currentPage - halfWindow);
    let endPage = Math.min(totalPages - 1, paginationConfig.currentPage + halfWindow);
    
    // Adjust start and end to ensure 'maxVisiblePages' are shown if possible
    if (paginationConfig.currentPage <= halfWindow + 1) {
        endPage = Math.min(totalPages - 1, maxVisiblePages);
    } else if (paginationConfig.currentPage >= totalPages - halfWindow) {
        startPage = Math.max(2, totalPages - maxVisiblePages);
    }
    
    // Show ellipsis after first page if needed
    if (startPage > 2) {
        addPaginationItem(paginationContainer, '...');
    }
    
    // Show pages in the window
    for (let i = startPage; i <= endPage; i++) {
        addPaginationItem(paginationContainer, i, i === paginationConfig.currentPage);
    }
    
    // Show ellipsis before last page if needed
    if (endPage < totalPages - 1) {
        addPaginationItem(paginationContainer, '...');
    }
    
    // Always show last page if more than 1 page
    if (totalPages > 1) {
        addPaginationItem(paginationContainer, totalPages, paginationConfig.currentPage === totalPages);
    }
    console.log('renderPaginationControls: Pagination links rendered.');
}

// Helper function to add a single pagination item (page number or ellipsis).
function addPaginationItem(container, page, isActive = false) {
    const li = document.createElement('li');
    li.className = 'page-item' + (isActive ? ' active' : '');

    const a = document.createElement('a');
    a.className = 'page-link';
    a.href = '#';
    a.textContent = page;

    if (page !== '...') {
        a.addEventListener('click', function (e) {
            e.preventDefault(); // Prevent default link behavior
            console.log(`addPaginationItem: Page link clicked - ${page}.`);
            paginationConfig.currentPage = parseInt(page);
            updatePagination(); // Update pagination when a page number is clicked
        });
    } else {
        // Disable ellipsis links
        a.setAttribute('disabled', true);
        li.classList.add('disabled');
    }

    li.appendChild(a);
    container.appendChild(li);
}
