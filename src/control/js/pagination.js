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
    Object.assign(paginationConfig, config); // Merge new config into the global one.
    
    const tableBody = document.getElementById(paginationConfig.tableId);
    if (tableBody) {
        // Prioritize window.allRows if it's explicitly set by the calling page for THIS tableId
        if (window.allRows && window.allRows.length > 0 && (window.pageSpecificTableId === paginationConfig.tableId)) {
             allRows = window.allRows;
        } else {
             allRows = Array.from(tableBody.querySelectorAll('tr:not(#noResultsMessage)')); // Exclude noResultsMessage row
             window.allRows = allRows; 
             window.pageSpecificTableId = paginationConfig.tableId; 
        }
        window.filteredRows = [...allRows];
    } else {
        return; 
    }
    
    setupEventListeners(); // This will now use dataset attributes to avoid double listening more robustly
    
    // The calling page (e.g., equipment_details.php) is responsible for the initial call to its filterTable,
    // which in turn should call updatePagination.
    // If no page-specific filterTable is intended, then a generic one or direct updatePagination call happens here.
    if (typeof window.filterTable === 'function' && window.filterTable.isPageSpecific) {
        // Example: window.filterTable(); // Page script should do this.
    } else if (document.getElementById('searchInput')) { 
        // Fallback to generic filter if searchInput exists and no page-specific filter is flagged
        filterTable(); 
    } else {
        updatePagination(); 
    }
    
    paginationConfig.isInitialized = true; // Mark this specific configuration as initialized
    // Store the initialized config globally if it's the main one for the page
    window.paginationConfig = paginationConfig; 
    
    return {
        update: updatePagination,
        getConfig: () => paginationConfig,
        setConfig: (newConfig) => Object.assign(paginationConfig, newConfig)
    };
}

document.addEventListener('DOMContentLoaded', () => {
    // This fallback should only run if NO page-specific initPagination has occurred.
    if (typeof window.paginationConfig === 'undefined' || !window.paginationConfig.isInitialized) {
        const defaultTableId = 'auditTable'; 
        if (document.getElementById(defaultTableId)) {
            initPagination({ tableId: defaultTableId }); 
        } else {
            return;
        }
    }
});

function setupEventListeners() {
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
        }
    }

    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    if (rowsPerPageSelect) {
        const listenerKey = `rppsListener_${paginationConfig.tableId}`;
        if (!rowsPerPageSelect.dataset[listenerKey]) {
            rowsPerPageSelect.addEventListener('change', () => {
                paginationConfig.currentPage = 1;
                updatePagination();
            });
            rowsPerPageSelect.dataset[listenerKey] = 'true';
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
                if (paginationConfig.currentPage > 1) {
                    paginationConfig.currentPage--;
                    updatePagination();
                }
            });
            newPrevButton.dataset[listenerFlag] = 'true'; // Mark listener as attached for this specific config
        }
    }

    const nextButton = document.getElementById(paginationConfig.nextPageId);
    if (nextButton) {
        const listenerFlag = `nextBtnListener_${paginationConfig.tableId}`;
        if (!nextButton.dataset[listenerFlag]) {
            const newNextButton = nextButton.cloneNode(true);
            nextButton.parentNode.replaceChild(newNextButton, nextButton);

            newNextButton.addEventListener('click', (event) => {
                const totalPages = getTotalPages();
                if (paginationConfig.currentPage < totalPages) {
                    paginationConfig.currentPage++;
                    updatePagination();
                }
            });
            newNextButton.dataset[listenerFlag] = 'true';
        }
    }
}

// Generic filterTable function (fallback if no page-specific one is used)
function filterTable() {
    const searchFilter = document.getElementById('searchInput')?.value.toLowerCase() || '';
    
    const rowsToFilter = allRows || []; 

    window.filteredRows = rowsToFilter.filter(row => {
        if (!row || row.querySelector('th')) return false; 
        if (searchFilter) {
            return row.textContent.toLowerCase().includes(searchFilter);
        }
        return true; // No generic filter applied, show all
    });
    
    if (paginationConfig) { // Ensure paginationConfig is defined
       paginationConfig.currentPage = 1;
    } else {
       return;
    }
    updatePagination();
}

function updatePagination() {
    if (!paginationConfig || !paginationConfig.tableId) {
        return;
    }
    const tbody = document.getElementById(paginationConfig.tableId);
    if (!tbody) {
        // Try to find the table using a more flexible selector for cases where tbody is not directly used
        const tableSelector = `#${paginationConfig.tableId}`;
        const table = document.querySelector(tableSelector);
        if (!table) {
            return;
        }
        
        // Find the tbody within the table if not directly specified
        const tbodyElement = table.tagName === 'TBODY' ? table : table.querySelector('tbody');
        if (!tbodyElement) {
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

    if (paginationConfig.currentPage > totalPages) paginationConfig.currentPage = totalPages;
    if (paginationConfig.currentPage < 1 && totalPages >= 1) paginationConfig.currentPage = 1;
    else if (totalPages === 0 && paginationConfig.currentPage !== 0) paginationConfig.currentPage = 0;
    
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
