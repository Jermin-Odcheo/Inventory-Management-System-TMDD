// Global variable to store the original table rows.
// This will be populated by logs.js and used as the source for filtering.
let allRows = window.allRows || [];

// Global variable to store the currently filtered rows.
// This will be updated by filterTable and used by updatePagination.
window.filteredRows = window.filteredRows || [];

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
             window.allRows = Array.from(tableBody.querySelectorAll('tr:not(#noResultsMessage)'));
             console.log(`pagination.js: Populated window.allRows as fallback with ${window.allRows.length} rows.`);
        }
        allRows = window.allRows; // pagination.js uses its local allRows reference
        console.log(`pagination.js: Local allRows synchronized, total ${allRows.length} rows.`);
        
        // Initialize filteredRows to all rows if not already set
        if (!window.filteredRows || window.filteredRows.length === 0) {
            window.filteredRows = [...allRows];
            console.log(`pagination.js: Initialized filteredRows with ${window.filteredRows.length} rows.`);
        }
    } else {
        console.error(`pagination.js: Table body with ID ${paginationConfig.tableId} not found during initPagination.`);
    }
    
    // Initialize filters and set up event listeners
    setupEventListeners();
    
    // Perform an initial filter and update pagination
    if (typeof filterTable === 'function') {
        filterTable(); // Call filterTable to apply initial filters and populate filteredRows
    } else {
        updatePagination(); // If no filterTable function, just update pagination
    }
    
    console.log('pagination.js: initPagination completed.');
    return {
        update: updatePagination,
        getConfig: () => paginationConfig,
        setConfig: (config) => Object.assign(paginationConfig, config),
        resetFilters: resetAllFilters
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
    
    // Row per page change handler
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => {
            console.log('pagination.js: rowsPerPageSelect changed.');
            paginationConfig.currentPage = 1; // Reset to first page on rows per page change
            updatePagination();
        });
        console.log('pagination.js: Added listener to rowsPerPageSelect.');
    }

    // Pagination navigation buttons
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

// Function to reset all filters to their default state
function resetAllFilters() {
    console.log('pagination.js: resetAllFilters called');
    
    // Reset search inputs
    const searchInputs = document.querySelectorAll('input[type="text"][id*="search"], input[type="search"]');
    searchInputs.forEach(input => {
        input.value = '';
    });
    
    // Reset select dropdowns
    const selectDropdowns = document.querySelectorAll('select[id*="filter"]');
    selectDropdowns.forEach(select => {
        if (select.querySelector('option[value="all"]')) {
            select.value = 'all';
        } else {
            select.value = '';
        }
        
        // If it's a Select2 dropdown, trigger the change event
        if (window.jQuery && window.jQuery(select).data('select2')) {
            window.jQuery(select).trigger('change.select2');
        }
    });
    
    // Reset date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.value = '';
    });
    
    // Hide date containers if they exist
    const dateContainers = document.querySelectorAll('[id*="dateInputs"], [id*="datePicker"]');
    dateContainers.forEach(container => {
        container.style.display = 'none';
    });
    
    // Reset to show all rows
    if (window.allRows) {
        window.filteredRows = [...window.allRows];
    }
    
    // Reset pagination to first page
    paginationConfig.currentPage = 1;
    
    // Update the UI
    if (typeof filterTable === 'function') {
        filterTable();
    } else {
        updatePagination();
    }
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

    // Make sure current page is in valid range
    if (paginationConfig.currentPage > totalPages) {
        paginationConfig.currentPage = totalPages;
    } else if (paginationConfig.currentPage < 1) {
        paginationConfig.currentPage = 1;
    }

    // Calculate start and end indices for current page
    const startIndex = (paginationConfig.currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, totalRows);

    // First, remove any existing "no results" message or create it if needed
    let noResultsMessage = document.getElementById('noResultsMessage');
    
    // If no results and no message exists, create one
    if (totalRows === 0 && !noResultsMessage) {
        noResultsMessage = document.createElement('tr');
        noResultsMessage.id = 'noResultsMessage';
        const colSpan = tbody.querySelector('tr')?.children?.length || 5;
        noResultsMessage.innerHTML = `
            <td colspan="${colSpan}" class="text-center py-4">
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-circle me-2"></i> No results found for the current filter criteria.
                </div>
            </td>
        `;
        tbody.appendChild(noResultsMessage);
    }
    
    // Show/hide the message based on results
    if (noResultsMessage) {
        noResultsMessage.style.display = totalRows === 0 ? 'table-row' : 'none';
    }

    // First, hide all rows by adding filtered-out class
    const allTableRows = Array.from(tbody.querySelectorAll('tr:not(#noResultsMessage)'));
    allTableRows.forEach(row => {
        row.classList.add('filtered-out');
    });

    // Then, show only the rows for the current page
    for (let i = startIndex; i < endIndex; i++) {
        if (rowsToPaginate[i]) {
            rowsToPaginate[i].classList.remove('filtered-out');
        }
    }

    // Update pagination controls
    const currentPageSpan = document.getElementById(paginationConfig.currentPageId);
    if (currentPageSpan) {
        currentPageSpan.textContent = totalRows === 0 ? 0 : paginationConfig.currentPage;
    }

    const rowsPerPageSpan = document.getElementById(paginationConfig.rowsPerPageId);
    if (rowsPerPageSpan) {
        rowsPerPageSpan.textContent = totalRows === 0 ? 0 : Math.min(endIndex, totalRows);
    }

    const totalRowsSpan = document.getElementById(paginationConfig.totalRowsId);
    if (totalRowsSpan) {
        totalRowsSpan.textContent = totalRows;
    }

    // Enable/disable prev/next buttons based on current page
    const prevButton = document.getElementById(paginationConfig.prevPageId);
    const nextButton = document.getElementById(paginationConfig.nextPageId);

    if (prevButton) {
        prevButton.disabled = paginationConfig.currentPage <= 1;
        prevButton.classList.toggle('disabled', paginationConfig.currentPage <= 1);
        
        // Hide prev button if on first page or no data
        if (paginationConfig.currentPage <= 1 || totalRows <= rowsPerPage) {
            prevButton.style.display = 'none';
        } else {
            prevButton.style.display = '';
        }
    }

    if (nextButton) {
        nextButton.disabled = paginationConfig.currentPage >= totalPages;
        nextButton.classList.toggle('disabled', paginationConfig.currentPage >= totalPages);
        
        // Hide next button if on last page or no data
        if (paginationConfig.currentPage >= totalPages || totalRows <= rowsPerPage) {
            nextButton.style.display = 'none';
        } else {
            nextButton.style.display = '';
        }
    }

    // Update pagination numbers
    renderPaginationControls(totalPages);
    
    // Hide pagination container if only one page
    const paginationContainer = document.getElementById(paginationConfig.paginationId);
    if (paginationContainer) {
        if (totalPages <= 1 || totalRows <= rowsPerPage) {
            paginationContainer.style.display = 'none';
        } else {
            paginationContainer.style.display = '';
        }
    }
}

function getTotalPages() {
    const rowsToPaginate = window.filteredRows || [];
    const totalRows = rowsToPaginate.length;
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    return Math.ceil(totalRows / rowsPerPage) || 1;
}

function renderPaginationControls(totalPages) {
    const paginationContainer = document.getElementById(paginationConfig.paginationId);
    if (!paginationContainer) return;

    // Clear existing pagination items
    paginationContainer.innerHTML = '';

    // Don't render pagination if there's only one page
    if (totalPages <= 1) {
        paginationContainer.style.display = 'none';
        return;
    } else {
        paginationContainer.style.display = '';
    }

    const currentPage = paginationConfig.currentPage;
    const maxPagesToShow = 5; // Maximum number of page numbers to show

    let startPage, endPage;
    if (totalPages <= maxPagesToShow) {
        // Show all pages if there are fewer than maxPagesToShow
        startPage = 1;
        endPage = totalPages;
    } else {
        // Calculate start and end pages to show
        if (currentPage <= Math.ceil(maxPagesToShow / 2)) {
            startPage = 1;
            endPage = maxPagesToShow;
        } else if (currentPage + Math.floor(maxPagesToShow / 2) >= totalPages) {
            startPage = totalPages - maxPagesToShow + 1;
            endPage = totalPages;
        } else {
            startPage = currentPage - Math.floor(maxPagesToShow / 2);
            endPage = currentPage + Math.floor(maxPagesToShow / 2);
        }
    }

    // Add "First" page if not starting from page 1
    if (startPage > 1) {
        addPaginationItem(paginationContainer, 1);
        if (startPage > 2) {
            // Add ellipsis if there's a gap
            const ellipsis = document.createElement('li');
            ellipsis.className = 'page-item disabled';
            ellipsis.innerHTML = '<span class="page-link">...</span>';
            paginationContainer.appendChild(ellipsis);
        }
    }

    // Add page numbers
    for (let i = startPage; i <= endPage; i++) {
        addPaginationItem(paginationContainer, i, i === currentPage);
    }

    // Add "Last" page if not ending at the last page
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            // Add ellipsis if there's a gap
            const ellipsis = document.createElement('li');
            ellipsis.className = 'page-item disabled';
            ellipsis.innerHTML = '<span class="page-link">...</span>';
            paginationContainer.appendChild(ellipsis);
        }
        addPaginationItem(paginationContainer, totalPages);
    }
}

function addPaginationItem(container, page, isActive = false) {
    const li = document.createElement('li');
    li.className = `page-item${isActive ? ' active' : ''}`;
    
    const a = document.createElement('a');
    a.className = 'page-link';
    a.href = '#';
    a.textContent = page;
    
    a.addEventListener('click', function(e) {
        e.preventDefault();
        paginationConfig.currentPage = page;
        updatePagination();
    });
    
    li.appendChild(a);
    container.appendChild(li);
}
