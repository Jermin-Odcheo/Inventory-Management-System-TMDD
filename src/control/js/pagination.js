// Global variable to store the original table rows.
// This will be populated by page-specific scripts (e.g., logs.js or inline scripts)
// and used as the source for filtering.
let allRows = window.allRows || [];

// Global variable to store the currently filtered rows.
// This will be updated by filterTable and used by updatePagination.
window.filteredRows = [];

// Configuration object with default values
// Page-specific scripts can override these by passing a config object to initPagination
let paginationConfig = {
    tableId: 'auditTable',      // Default table ID, should be overridden by specific pages
    currentPage: 1,             // Current page
    rowsPerPageSelectId: 'rowsPerPageSelect',
    currentPageId: 'currentPage',
    rowsPerPageId: 'rowsPerPage',
    totalRowsId: 'totalRows',
    prevPageId: 'prevPage',
    nextPageId: 'nextPage',
    paginationId: 'pagination',
    isInitialized: false // Flag to track if pagination has been initialized FOR THIS CONFIG
};

// Function to initialize pagination with a specific table and configuration
function initPagination(config = {}) {
    console.log(`pagination.js: initPagination called for tableId: ${config.tableId || paginationConfig.tableId}. Current global config tableId: ${window.paginationConfig ? window.paginationConfig.tableId : 'undefined'}, isInitialized: ${window.paginationConfig ? window.paginationConfig.isInitialized : 'undefined'}`);
    
    // Create a new config object for this instance to avoid clashes if multiple tables use this script.
    // However, for a single page context like equipment_details, we often rely on one global.
    // For this fix, we'll continue to use the global `paginationConfig` but be careful.
    Object.assign(paginationConfig, config); // Merge new config into the global one.
    
    const tableBody = document.getElementById(paginationConfig.tableId);
    if (tableBody) {
        // Prioritize window.allRows if it's explicitly set by the calling page for THIS tableId
        if (window.allRows && window.allRows.length > 0 && (window.pageSpecificTableId === paginationConfig.tableId)) {
             allRows = window.allRows;
             console.log(`pagination.js: Using pre-populated window.allRows for ${paginationConfig.tableId}, total ${allRows.length} rows.`);
        } else {
             allRows = Array.from(tableBody.querySelectorAll('tr:not(#noResultsMessage)')); // Exclude noResultsMessage row
             window.allRows = allRows; 
             window.pageSpecificTableId = paginationConfig.tableId; 
             console.log(`pagination.js: Populated allRows from table ${paginationConfig.tableId}, total ${allRows.length} rows.`);
        }
        window.filteredRows = [...allRows];
    } else {
        console.error(`pagination.js: Table body with ID ${paginationConfig.tableId} not found during initPagination.`);
        return; 
    }
    
    setupEventListeners(); // This will now use dataset attributes to avoid double listening more robustly
    
    // The calling page (e.g., equipment_details.php) is responsible for the initial call to its filterTable,
    // which in turn should call updatePagination.
    // If no page-specific filterTable is intended, then a generic one or direct updatePagination call happens here.
    if (typeof window.filterTable === 'function' && window.filterTable.isPageSpecific) {
        console.log('pagination.js: Page-specific filterTable found. It should call updatePagination after filtering.');
        // Example: window.filterTable(); // Page script should do this.
    } else if (document.getElementById('searchInput')) { 
        // Fallback to generic filter if searchInput exists and no page-specific filter is flagged
        console.log('pagination.js: Calling generic filterTable.');
        filterTable(); 
    } else {
        console.log('pagination.js: No specific or generic filter setup found, calling updatePagination directly.');
        updatePagination(); 
    }
    
    paginationConfig.isInitialized = true; // Mark this specific configuration as initialized
    // Store the initialized config globally if it's the main one for the page
    window.paginationConfig = paginationConfig; 
    console.log(`pagination.js: initPagination for ${paginationConfig.tableId} completed. Global isInitialized: ${window.paginationConfig.isInitialized}`);
    
    return {
        update: updatePagination,
        getConfig: () => paginationConfig,
        setConfig: (newConfig) => Object.assign(paginationConfig, newConfig)
    };
}

document.addEventListener('DOMContentLoaded', () => {
    // This fallback should only run if NO page-specific initPagination has occurred.
    if (typeof window.paginationConfig === 'undefined' || !window.paginationConfig.isInitialized) {
        console.log('pagination.js: DOMContentLoaded fallback triggered because no specific pagination was initialized.');
        const defaultTableId = 'auditTable'; 
        if (document.getElementById(defaultTableId)) {
            console.log(`pagination.js: DOMContentLoaded: Initializing with default config for table "${defaultTableId}".`);
            initPagination({ tableId: defaultTableId }); 
        } else {
            console.log(`pagination.js: DOMContentLoaded fallback: Default table "${defaultTableId}" not found. Skipping default initialization.`);
        }
    } else {
        console.log(`pagination.js: DOMContentLoaded fallback skipped. Pagination (table: ${window.paginationConfig.tableId}) already initialized by a page-specific script.`);
    }
});

function setupEventListeners() {
    console.log(`pagination.js: setupEventListeners for current config (table: ${paginationConfig.tableId}).`);
    
    // Example for generic search input - adapt if your page uses different filter IDs
    const searchInput = document.getElementById('searchInput'); 
    if (searchInput) {
        // Use a unique key for the listener flag if multiple tables might share filter inputs
        const listenerKey = `listenerAttached_${paginationConfig.tableId}`;
        if (!searchInput.dataset[listenerKey]) {
            searchInput.addEventListener('input', () => {
                // Ensure 'filterTable' is the correct one (page-specific or generic)
                if (typeof window.filterTable === 'function') {
                    window.filterTable();
                } else {
                    filterTable(); // Fallback to pagination.js generic filterTable
                }
            });
            searchInput.dataset[listenerKey] = 'true';
            console.log(`pagination.js: Added input listener to generic searchInput for ${paginationConfig.tableId}.`);
        }
    }

    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    if (rowsPerPageSelect) {
        const listenerKey = `rppsListener_${paginationConfig.tableId}`;
        if (!rowsPerPageSelect.dataset[listenerKey]) {
            rowsPerPageSelect.addEventListener('change', () => {
                console.log(`pagination.js: rowsPerPageSelect changed for ${paginationConfig.tableId}.`);
                paginationConfig.currentPage = 1;
                updatePagination();
            });
            rowsPerPageSelect.dataset[listenerKey] = 'true';
            console.log(`pagination.js: Added change listener to ${paginationConfig.rowsPerPageSelectId} for ${paginationConfig.tableId}.`);
        }
    }

    const prevButton = document.getElementById(paginationConfig.prevPageId);
    if (prevButton) {
        // Using a more specific dataset key to avoid conflicts if multiple paginations exist.
        const listenerFlag = `prevBtnListener_${paginationConfig.tableId}`;
        if (!prevButton.dataset[listenerFlag]) {
            // Clone to remove any unrelated listeners if IDs are somehow reused.
            const newPrevButton = prevButton.cloneNode(true);
            prevButton.parentNode.replaceChild(newPrevButton, prevButton);
            
            newPrevButton.addEventListener('click', (event) => {
                console.log(`PAGINATION_PREV_CLICK: Table: ${paginationConfig.tableId}, Timestamp: ${event.timeStamp}, CurrentPage BEFORE: ${paginationConfig.currentPage}`);
                if (paginationConfig.currentPage > 1) {
                    paginationConfig.currentPage--;
                    console.log(`PAGINATION_PREV_CLICK: CurrentPage AFTER: ${paginationConfig.currentPage}`);
                    updatePagination();
                } else {
                    console.log(`PAGINATION_PREV_CLICK: Already on first page.`);
                }
            });
            newPrevButton.dataset[listenerFlag] = 'true'; // Mark listener as attached for this specific config
            console.log(`pagination.js: Added click listener to ${paginationConfig.prevPageId} for ${paginationConfig.tableId}.`);
        } else {
             console.log(`pagination.js: Click listener for ${paginationConfig.prevPageId} (table ${paginationConfig.tableId}) already attached.`);
        }
    }

    const nextButton = document.getElementById(paginationConfig.nextPageId);
    if (nextButton) {
        const listenerFlag = `nextBtnListener_${paginationConfig.tableId}`;
        if (!nextButton.dataset[listenerFlag]) {
            const newNextButton = nextButton.cloneNode(true);
            nextButton.parentNode.replaceChild(newNextButton, nextButton);

            newNextButton.addEventListener('click', (event) => {
                console.log(`PAGINATION_NEXT_CLICK: Table: ${paginationConfig.tableId}, Timestamp: ${event.timeStamp}, CurrentPage BEFORE: ${paginationConfig.currentPage}`);
                const totalPages = getTotalPages();
                if (paginationConfig.currentPage < totalPages) {
                    paginationConfig.currentPage++;
                    console.log(`PAGINATION_NEXT_CLICK: CurrentPage AFTER: ${paginationConfig.currentPage}`);
                    updatePagination();
                } else {
                    console.log(`PAGINATION_NEXT_CLICK: Already on last page (Page ${paginationConfig.currentPage} of ${totalPages}).`);
                }
            });
            newNextButton.dataset[listenerFlag] = 'true';
            console.log(`pagination.js: Added click listener to ${paginationConfig.nextPageId} for ${paginationConfig.tableId}.`);
        } else {
            console.log(`pagination.js: Click listener for ${paginationConfig.nextPageId} (table ${paginationConfig.tableId}) already attached.`);
        }
    }
}

// Generic filterTable function (fallback if no page-specific one is used)
function filterTable() {
    console.log('pagination.js: Generic filterTable called (fallback).');
    const searchFilter = document.getElementById('searchInput')?.value.toLowerCase() || '';
    // Add other generic filter inputs if applicable
    
    const rowsToFilter = allRows || []; 
    console.log(`Generic filterTable: Filtering from ${rowsToFilter.length} total rows.`);

    window.filteredRows = rowsToFilter.filter(row => {
        if (!row || row.querySelector('th')) return false; 
        if (searchFilter) {
            return row.textContent.toLowerCase().includes(searchFilter);
        }
        return true; // No generic filter applied, show all
    });
    
    console.log(`Generic filterTable: Filtered down to ${window.filteredRows.length} rows.`);
    if (paginationConfig) { // Ensure paginationConfig is defined
       paginationConfig.currentPage = 1;
    } else {
       console.error("Generic filterTable: paginationConfig is undefined!");
       return;
    }
    updatePagination();
}

function updatePagination() {
    if (!paginationConfig || !paginationConfig.tableId) {
        console.error(`updatePagination: paginationConfig or tableId is not defined. Config:`, paginationConfig);
        return;
    }
    console.log(`pagination.js: updatePagination for ${paginationConfig.tableId}. Current Page: ${paginationConfig.currentPage}`);
    const tbody = document.getElementById(paginationConfig.tableId);
    if (!tbody) {
        // Try to find the table using a more flexible selector for cases where tbody is not directly used
        const tableSelector = `#${paginationConfig.tableId}`;
        const table = document.querySelector(tableSelector);
        if (!table) {
            console.error(`updatePagination: Could not find element with ID ${paginationConfig.tableId}`);
            return;
        }
        
        // Find the tbody within the table if not directly specified
        const tbodyElement = table.tagName === 'TBODY' ? table : table.querySelector('tbody');
        if (!tbodyElement) {
            console.error(`updatePagination: Could not find tbody within ${paginationConfig.tableId}`);
            return;
        }
        
        // Update the rows directly
        handlePagination(tbodyElement);
    } else {
        handlePagination(tbody);
    }
}

function handlePagination(tbody) {
    const rowsToPaginate = window.filteredRows || [];
    const totalRows = rowsToPaginate.length;
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

    console.log(`updatePagination: Total Filtered: ${totalRows}, PerPage: ${rowsPerPage}, TotalPages: ${totalPages}`);

    if (paginationConfig.currentPage > totalPages) paginationConfig.currentPage = totalPages;
    if (paginationConfig.currentPage < 1 && totalPages >= 1) paginationConfig.currentPage = 1;
    else if (totalPages === 0 && paginationConfig.currentPage !== 0) paginationConfig.currentPage = 0;
    
    console.log('updatePagination: Adjusted Current Page:', paginationConfig.currentPage);

    const start = paginationConfig.currentPage > 0 ? (paginationConfig.currentPage - 1) * rowsPerPage : 0;
    const end = paginationConfig.currentPage > 0 ? paginationConfig.currentPage * rowsPerPage : 0;
    
    // Don't clear the table, instead hide/show rows based on the current page
    const allRowsInTable = Array.from(tbody.querySelectorAll('tr'));
    
    // First hide all rows
    allRowsInTable.forEach(row => {
        row.style.display = 'none';
    });
    
    if (totalRows === 0) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.id = 'noResultsMessage';
        const cell = noResultsRow.insertCell();
        const table = tbody.closest('table');
        let colspan = 10; 
        if (table && table.tHead && table.tHead.rows.length > 0 && table.tHead.rows[0].cells.length > 0) {
            colspan = table.tHead.rows[0].cells.length;
        }
        cell.colSpan = colspan;
        cell.textContent = 'No results found.';
        cell.className = 'text-center'; 
        
        tbody.appendChild(noResultsRow);
    } else {
        // Show only the rows for the current page
        const rowsToShow = rowsToPaginate.slice(start, end);
        
        // Map the filtered rows to the actual DOM rows
        rowsToShow.forEach(row => {
            // Make this row visible
            row.style.display = '';
        });
    }

    // Update pagination controls and info
    renderPaginationControls(totalPages);
    updatePaginationInfo(totalRows, rowsPerPage, start, Math.min(end, totalRows));
    updateNavigationButtons(totalPages);
}

// New function to preserve sort parameters when navigating between pages
function preserveSortParameters() {
    // Get current sort parameters from URL
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const sortBy = urlParams.get('sort_by');
        const sortOrder = urlParams.get('sort_order');
        
        // Return sort parameters if they exist
        if (sortBy && sortOrder) {
            return { sort_by: sortBy, sort_order: sortOrder };
        }
    } catch (e) {
        console.error('Error parsing sort parameters:', e);
    }
    
    // Return null if no sort parameters found
    return null;
}

function getTotalPages() {
    if (!paginationConfig || !paginationConfig.rowsPerPageSelectId) {
        console.error("getTotalPages: paginationConfig or rowsPerPageSelectId is undefined.");
        return 1; // Fallback
    }
    const rowsToPaginate = window.filteredRows || [];
    const totalRows = rowsToPaginate.length;
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    return Math.ceil(totalRows / rowsPerPage) || 1;
}

function renderPaginationControls(totalPages) {
    const paginationContainer = document.getElementById(paginationConfig.paginationId);
    if (!paginationContainer) {
        console.error(`renderPaginationControls: Pagination container with ID ${paginationConfig.paginationId} not found.`);
        return;
    }
    
    // Clear existing pagination elements
    paginationContainer.innerHTML = '';
    
    if (totalPages <= 1) {
        // Don't show pagination for a single page or no results
        return;
    }

    // Parameters to preserve any existing sort
    const sortParams = preserveSortParameters();
    
    // << First page
    addPaginationItem(
        paginationContainer, 
        '&laquo;', 
        false,
        paginationConfig.currentPage === 1,
        () => {
            paginationConfig.currentPage = 1;
            updatePagination();
        },
        sortParams
    );
        
    // Previous page
    addPaginationItem(
        paginationContainer,
        '&lsaquo;',
        false,
        paginationConfig.currentPage === 1,
        () => {
            if (paginationConfig.currentPage > 1) {
                paginationConfig.currentPage--;
                updatePagination();
            }
        },
        sortParams
    );
    
    // Calculate page range to show
    let startPage = Math.max(1, paginationConfig.currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    startPage = Math.max(1, endPage - 4);
    
    // First page if needed
    if (startPage > 1) {
        addPaginationItem(
            paginationContainer,
            '1',
            paginationConfig.currentPage === 1,
            false,
            () => {
                paginationConfig.currentPage = 1;
                updatePagination();
            },
            sortParams
        );
        
        if (startPage > 2) {
            addPaginationItem(paginationContainer, '...', false, true, null, sortParams);
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        addPaginationItem(
            paginationContainer,
            i.toString(),
            paginationConfig.currentPage === i,
            false,
            () => {
                paginationConfig.currentPage = i;
                updatePagination();
            },
            sortParams
        );
    }
    
    // Last page if needed
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            addPaginationItem(paginationContainer, '...', false, true, null, sortParams);
        }
        
        addPaginationItem(
            paginationContainer,
            totalPages.toString(),
            paginationConfig.currentPage === totalPages,
            false,
            () => {
                paginationConfig.currentPage = totalPages;
                updatePagination();
            },
            sortParams
        );
    }
    
    // Next page
    addPaginationItem(
        paginationContainer,
        '&rsaquo;',
        false,
        paginationConfig.currentPage === totalPages,
        () => {
            if (paginationConfig.currentPage < totalPages) {
                paginationConfig.currentPage++;
                updatePagination();
            }
        },
        sortParams
    );
    
    // >> Last page
    addPaginationItem(
        paginationContainer,
        '&raquo;',
        false,
        paginationConfig.currentPage === totalPages,
        () => {
            paginationConfig.currentPage = totalPages;
            updatePagination();
        },
        sortParams
    );
}

function addPaginationItem(container, pageContent, isActive = false, isDisabled = false, clickHandler, sortParams = null) {
    const li = document.createElement('li');
    li.className = 'page-item';
    if (isActive) li.classList.add('active');
    if (isDisabled) li.classList.add('disabled');
    
    const pageLink = document.createElement('a');
    pageLink.className = 'page-link';
    pageLink.innerHTML = pageContent; // Use innerHTML to support symbols like &laquo;
    
    if (!isDisabled && clickHandler) {
        pageLink.addEventListener('click', (event) => {
            event.preventDefault();
            clickHandler();
        });
        pageLink.style.cursor = 'pointer';
    }
    
    li.appendChild(pageLink);
    container.appendChild(li);
}

// Helper function to update pagination info display
function updatePaginationInfo(totalRows, rowsPerPage, start, end) {
    const currentPageEl = document.getElementById(paginationConfig.currentPageId);
    if (currentPageEl) currentPageEl.textContent = totalRows === 0 ? 0 : start + 1;
    
    const rowsPerPageEl = document.getElementById(paginationConfig.rowsPerPageId);
    if (rowsPerPageEl) rowsPerPageEl.textContent = Math.min(end, totalRows);
    
    const totalRowsEl = document.getElementById(paginationConfig.totalRowsId);
    if (totalRowsEl) totalRowsEl.textContent = totalRows;
}

// Helper function to update navigation button states
function updateNavigationButtons(totalPages) {
    const prevPageEl = document.getElementById(paginationConfig.prevPageId);
    if (prevPageEl) {
        prevPageEl.disabled = (paginationConfig.currentPage <= 1 || totalPages === 0);
        
        // Update href attribute if it exists
        if (prevPageEl.hasAttribute('href') && paginationConfig.currentPage > 1) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', paginationConfig.currentPage - 1);
            prevPageEl.href = `?${urlParams.toString()}`;
        }
    }
    
    const nextPageEl = document.getElementById(paginationConfig.nextPageId);
    if (nextPageEl) {
        nextPageEl.disabled = (paginationConfig.currentPage >= totalPages || totalPages === 0);
        
        // Update href attribute if it exists
        if (nextPageEl.hasAttribute('href') && paginationConfig.currentPage < totalPages) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', paginationConfig.currentPage + 1);
            nextPageEl.href = `?${urlParams.toString()}`;
        }
    }
}
