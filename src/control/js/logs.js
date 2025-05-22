// Global variable to store the original table rows
let allRows = [];

// On DOMContentLoaded, capture all original table rows
// and set up filter event listeners only
// (Pagination is handled by pagination.js)
document.addEventListener('DOMContentLoaded', () => {
    allRows = Array.from(document.querySelectorAll('#auditTable tr'));
    // Set up module filter if it exists
    const moduleFilter = document.getElementById('filterModule');
    if (moduleFilter) {
        moduleFilter.addEventListener('change', filterTable);
    }
    // Set up filter event listeners
    document.getElementById('searchInput').addEventListener('keyup', filterTable);
    document.getElementById('filterAction').addEventListener('change', filterTable);
    if (document.getElementById('filterStatus')) {
        document.getElementById('filterStatus').addEventListener('change', filterTable);
    }
    // Initial filter (pagination will be updated by pagination.js)
    filterTable();
});

// Rebuild table body with only the rows that match the filters
function filterTable() {
    const searchFilter = document.getElementById('searchInput').value.toLowerCase();
    const actionFilter = document.getElementById('filterAction').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus') ? document.getElementById('filterStatus').value.toLowerCase() : '';
    const moduleFilter = document.getElementById('filterModule') ? document.getElementById('filterModule').value.toLowerCase() : '';

    // Filter rows from the original set
    const filteredRows = allRows.filter(row => {
        const actionCell = row.querySelector('[data-label="Action"]');
        const statusCell = row.querySelector('[data-label="Status"]');
        const moduleCell = row.querySelector('[data-label="Module"]');
        const actionText = actionCell ? actionCell.textContent.toLowerCase() : '';
        const statusText = statusCell ? statusCell.textContent.toLowerCase() : '';
        const moduleText = moduleCell ? moduleCell.textContent.toLowerCase() : '';
        const rowText = row.textContent.toLowerCase();
        const matchesSearch = rowText.includes(searchFilter);
        const matchesAction = actionFilter === '' || actionText.includes(actionFilter);
        let matchesStatus = true;
        if (statusFilter !== '') {
            const normalizedStatus = statusText.trim().toLowerCase();
            if (statusFilter === 'successful') {
                matchesStatus = ['successful', 'success'].includes(normalizedStatus);
            } else if (statusFilter === 'failed') {
                matchesStatus = ['failed', 'fail'].includes(normalizedStatus);
            } else {
                matchesStatus = normalizedStatus.includes(statusFilter);
            }
        }
        const matchesModule = moduleFilter === '' || moduleText.includes(moduleFilter);
        return matchesSearch && matchesAction && matchesStatus && matchesModule;
    });

    // Rebuild the <tbody> with only filtered rows
    const tbody = document.getElementById('auditTable');
    tbody.innerHTML = '';
    filteredRows.forEach(row => {
        row.style.display = ''; // ensure row is visible
        tbody.appendChild(row);
    });
    // After filtering, let pagination.js update the pagination
    if (typeof updatePagination === 'function') {
        updatePagination();
    }
}