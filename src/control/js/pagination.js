// Global variable to store the original table rows
let allRows = [];
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
    // Update config with any user-specified values
    Object.assign(paginationConfig, config);
    
    // Get table rows from the specified table
    const tableBody = document.getElementById(paginationConfig.tableId);
    if (tableBody) {
        allRows = Array.from(tableBody.querySelectorAll('tr'));
    }
    
    // Initialize filters and set up event listeners
    setupEventListeners();
    
    // Initial call to update pagination
    updatePagination();
    
    return {
        update: updatePagination,
        getConfig: () => paginationConfig,
        setConfig: (config) => Object.assign(paginationConfig, config)
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById(paginationConfig.tableId);
    if (tableBody) {
        allRows = Array.from(tableBody.querySelectorAll('tr'));
    }

    // Initialize filters
    setupEventListeners();

    // Initial call
    updatePagination();
});

function setupEventListeners() {
    const searchInput = document.getElementById('searchInput');
    const filterAction = document.getElementById('filterAction');
    const filterStatus = document.getElementById('filterStatus');

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }

    if (filterAction) {
        filterAction.addEventListener('change', filterTable);
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', filterTable);
    }

    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => {
            paginationConfig.currentPage = 1;
            updatePagination();
        });
    }

    const prevButton = document.getElementById(paginationConfig.prevPageId);
    const nextButton = document.getElementById(paginationConfig.nextPageId);

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (paginationConfig.currentPage > 1) {
                paginationConfig.currentPage--;
                updatePagination();
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            const totalPages = getTotalPages();
            if (paginationConfig.currentPage < totalPages) {
                paginationConfig.currentPage++;
                updatePagination();
            }
        });
    }
}

function filterTable() {
    const searchQuery = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const actionFilter = (document.getElementById('filterAction')?.value || '').toLowerCase();
    const statusFilter = (document.getElementById('filterStatus')?.value || '').toLowerCase();

    const filteredRows = allRows.filter(row => {
        const userCell = row.querySelector('td:nth-child(2)');
        const actionCell = row.querySelector('td:nth-child(3)');
        const statusCell = row.querySelector('td:nth-child(6)');
        const rowText = row.textContent.toLowerCase();

        const matchesSearch = searchQuery === '' || rowText.includes(searchQuery);
        const matchesAction = actionFilter === '' || (actionCell?.textContent.toLowerCase().includes(actionFilter) ?? false);
        const matchesStatus = statusFilter === '' || (statusCell?.textContent.toLowerCase().includes(statusFilter) ?? false);

        return matchesSearch && matchesAction && matchesStatus;
    });

    const tbody = document.getElementById(paginationConfig.tableId);
    if (tbody) {
        tbody.innerHTML = '';
        filteredRows.forEach(row => tbody.appendChild(row));
    }

    paginationConfig.currentPage = 1;
    updatePagination();
}

function updatePagination() {
    const tbody = document.getElementById(paginationConfig.tableId);
    if (!tbody) return;
    
    const filteredRows = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = filteredRows.length;
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

    paginationConfig.currentPage = Math.min(paginationConfig.currentPage, totalPages);

    const start = (paginationConfig.currentPage - 1) * rowsPerPage;
    const end = paginationConfig.currentPage * rowsPerPage;

    // Show/hide rows
    filteredRows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });

    // Update display text
    const currentPageEl = document.getElementById(paginationConfig.currentPageId);
    if (currentPageEl) currentPageEl.textContent = start + 1;
    
    const rowsPerPageEl = document.getElementById(paginationConfig.rowsPerPageId);
    if (rowsPerPageEl) rowsPerPageEl.textContent = Math.min(end, totalRows);
    
    const totalRowsEl = document.getElementById(paginationConfig.totalRowsId);
    if (totalRowsEl) totalRowsEl.textContent = totalRows;

    // Disable buttons
    const prevPageEl = document.getElementById(paginationConfig.prevPageId);
    if (prevPageEl) prevPageEl.disabled = (paginationConfig.currentPage === 1);
    
    const nextPageEl = document.getElementById(paginationConfig.nextPageId);
    if (nextPageEl) nextPageEl.disabled = (paginationConfig.currentPage === totalPages);

    // Render pagination links
    renderPaginationControls(totalPages);
}

function getTotalPages() {
    const tbody = document.getElementById(paginationConfig.tableId);
    if (!tbody) return 1;
    
    const filteredRows = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = filteredRows.length;
    const rowsPerPageSelect = document.getElementById(paginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    return Math.ceil(totalRows / rowsPerPage);
}

function renderPaginationControls(totalPages) {
    const paginationContainer = document.getElementById(paginationConfig.paginationId);
    if (!paginationContainer) return;

    paginationContainer.innerHTML = '';

    if (totalPages <= 1) return;

    // Always show first page
    addPaginationItem(paginationContainer, 1, paginationConfig.currentPage === 1);

    // Show ellipses and a window of pages around current page
    const maxVisiblePages = 5; // Adjust to show more or fewer pages
    const halfWindow = Math.floor(maxVisiblePages / 2);
    
    let startPage = Math.max(2, paginationConfig.currentPage - halfWindow);
    let endPage = Math.min(totalPages - 1, paginationConfig.currentPage + halfWindow);
    
    // Adjust for edge cases
    if (paginationConfig.currentPage <= halfWindow + 1) {
        // Near start, show more pages after current
        endPage = Math.min(totalPages - 1, maxVisiblePages);
    } else if (paginationConfig.currentPage >= totalPages - halfWindow) {
        // Near end, show more pages before current
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
    
    // Always show last page
    if (totalPages > 1) {
        addPaginationItem(paginationContainer, totalPages, paginationConfig.currentPage === totalPages);
    }
}

function addPaginationItem(container, page, isActive = false) {
    const li = document.createElement('li');
    li.className = 'page-item' + (isActive ? ' active' : '');

    const a = document.createElement('a');
    a.className = 'page-link';
    a.href = '#';
    a.textContent = page;

    if (page !== '...') {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            paginationConfig.currentPage = parseInt(page);
            updatePagination();
        });
    } else {
        a.setAttribute('disabled', true);
        li.classList.add('disabled');
    }

    li.appendChild(a);
    container.appendChild(li);
}
