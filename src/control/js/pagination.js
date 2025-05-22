// Global variable to store the original table rows
let allRows = [];

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('auditTable');
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

    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => {
            currentPage = 1;
            updatePagination();
        });
    }

    const prevButton = document.getElementById('prevPage');
    const nextButton = document.getElementById('nextPage');

    if (prevButton) {
        prevButton.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                updatePagination();
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', () => {
            const totalPages = getTotalPages();
            if (currentPage < totalPages) {
                currentPage++;
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

    const tbody = document.getElementById('auditTable');
    tbody.innerHTML = '';
    filteredRows.forEach(row => tbody.appendChild(row));

    currentPage = 1;
    updatePagination();
}

let currentPage = 1;

function updatePagination() {
    const tbody = document.getElementById('auditTable');
    const filteredRows = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = filteredRows.length;
    const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect')?.value || '10');
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

    currentPage = Math.min(currentPage, totalPages);

    const start = (currentPage - 1) * rowsPerPage;
    const end = currentPage * rowsPerPage;

    // Show/hide rows
    filteredRows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });

    // Update display text
    document.getElementById('currentPage').textContent = start + 1;
    document.getElementById('rowsPerPage').textContent = Math.min(end, totalRows);
    document.getElementById('totalRows').textContent = totalRows;

    // Disable buttons
    document.getElementById('prevPage').disabled = (currentPage === 1);
    document.getElementById('nextPage').disabled = (currentPage === totalPages);

    // Render pagination links
    renderPaginationControls(totalPages);
}

function getTotalPages() {
    const tbody = document.getElementById('auditTable');
    const filteredRows = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = filteredRows.length;
    const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect')?.value || '10');
    return Math.ceil(totalRows / rowsPerPage);
}

function renderPaginationControls(totalPages) {
    const paginationContainer = document.getElementById('pagination');
    if (!paginationContainer) return;

    paginationContainer.innerHTML = '';

    if (totalPages <= 1) return;

    const rangeStart = Math.max(2, currentPage - 2);
    const rangeEnd = Math.min(totalPages - 1, currentPage + 2);

    addPaginationItem(paginationContainer, 1, currentPage === 1);

    if (rangeStart > 2) {
        addPaginationItem(paginationContainer, '...');
    }

    for (let i = rangeStart; i <= rangeEnd; i++) {
        addPaginationItem(paginationContainer, i, i === currentPage);
    }

    if (rangeEnd < totalPages - 1) {
        addPaginationItem(paginationContainer, '...');
    }

    if (totalPages > 1) {
        addPaginationItem(paginationContainer, totalPages, currentPage === totalPages);
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
            currentPage = parseInt(page);
            updatePagination();
        });
    } else {
        a.setAttribute('disabled', true);
        li.classList.add('disabled');
    }

    li.appendChild(a);
    container.appendChild(li);
}
