// Global variable to store the original table rows
let allRows = [];

// On DOMContentLoaded, capture all original table rows
// and set up filter event listeners only
// (Pagination is handled by pagination.js)
document.addEventListener('DOMContentLoaded', () => {
    // Use tableId from pagination.js if available, otherwise default to 'auditTable'
    const tableId = window.paginationConfig?.tableId || 'auditTable';
    allRows = Array.from(document.querySelectorAll(`#${tableId} tr`));
    
    // Set up module filter if it exists
    const moduleFilter = document.getElementById('filterModule');
    if (moduleFilter) {
        moduleFilter.addEventListener('change', filterTable);
    }
    
    // Set up filter event listeners
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterTable);
    }
    
    const actionFilter = document.getElementById('filterAction');
    if (actionFilter) {
        actionFilter.addEventListener('change', filterTable);
    }
    
    const statusFilter = document.getElementById('filterStatus');
    if (statusFilter) {
        statusFilter.addEventListener('change', filterTable);
    }
    
    // Initial filter (pagination will be updated by pagination.js)
    filterTable();
});

// Rebuild table body with only the rows that match the filters
function filterTable() {
    const searchFilter = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const actionFilter = document.getElementById('filterAction')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('filterStatus')?.value.toLowerCase() || '';
    const moduleFilter = document.getElementById('filterModule')?.value.toLowerCase() || '';

    // Get tableId from pagination.js if available, otherwise default to 'auditTable'
    const tableId = window.paginationConfig?.tableId || 'auditTable';

    // Filter rows from the original set
    const filteredRows = allRows.filter(row => {
        // Try to find cells by data-label first, then by class name
        const actionCell = row.querySelector('[data-label="Action"]') || row.querySelector('.action');
        const statusCell = row.querySelector('[data-label="Status"]') || row.querySelector('.status');
        const moduleCell = row.querySelector('[data-label="Module"]') || row.querySelector('.module');
        
        // Extract text content, default to empty string if cell not found
        const actionText = actionCell ? actionCell.textContent.toLowerCase() : '';
        const statusText = statusCell ? statusCell.textContent.toLowerCase() : '';
        const moduleText = moduleCell ? moduleCell.textContent.toLowerCase() : '';
        const rowText = row.textContent.toLowerCase();
        
        // Match search term against entire row text
        const matchesSearch = searchFilter === '' || rowText.includes(searchFilter);
        
        // Match action filter
        const matchesAction = actionFilter === '' || actionText.includes(actionFilter);
        
        // Match status filter with special handling for successful/failed
        let matchesStatus = true;
        if (statusFilter !== '') {
            const normalizedStatus = statusText.trim().toLowerCase();
            if (statusFilter === 'successful') {
                matchesStatus = normalizedStatus.includes('successful') || normalizedStatus.includes('success');
            } else if (statusFilter === 'failed') {
                matchesStatus = normalizedStatus.includes('failed') || normalizedStatus.includes('fail');
            } else {
                matchesStatus = normalizedStatus.includes(statusFilter);
            }
        }
        
        // Match module filter
        const matchesModule = moduleFilter === '' || moduleText.includes(moduleFilter);
        
        return matchesSearch && matchesAction && matchesStatus && matchesModule;
    });

    // Get the tbody element
    const tbody = document.getElementById(tableId);
    if (!tbody) return;
    
    // Store filtered rows in window for pagination
    window.filteredRows = filteredRows;
    
    // Rebuild the <tbody> with only filtered rows
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