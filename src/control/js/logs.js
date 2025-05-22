// Global variable to store the original table rows
let allRows = [];

// On DOMContentLoaded, capture all original table rows
// and set up filter event listeners only
// (Pagination is handled by pagination.js)
document.addEventListener('DOMContentLoaded', () => {
    // Use tableId from pagination.js if available, otherwise default to 'auditTable'
    const tableId = window.paginationConfig?.tableId || 'auditTable';
    
    // Store all rows for filtering
    allRows = Array.from(document.querySelectorAll(`#${tableId} tr`));
    window.allRows = allRows; // Make it accessible globally
    
    // Set up module filter if it exists
    const moduleFilter = document.getElementById('filterModule');
    if (moduleFilter) {
        moduleFilter.addEventListener('change', filterTable);
    }
    
    // Set up filter event listeners
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
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
    setTimeout(filterTable, 100); // Slight delay to ensure DOM is ready
});

// Rebuild table body with only the rows that match the filters
function filterTable() {
    console.log("Filtering table..."); // Debug
    
    const searchFilter = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const actionFilter = document.getElementById('filterAction')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('filterStatus')?.value.toLowerCase() || '';
    const moduleFilter = document.getElementById('filterModule')?.value.toLowerCase() || '';
    
    console.log("Filters:", { searchFilter, actionFilter, statusFilter, moduleFilter }); // Debug
    
    // Get tableId from pagination.js if available, otherwise default to 'auditTable'
    const tableId = window.paginationConfig?.tableId || 'auditTable';
    
    // Use window.allRows if available (might be updated by other code)
    const rowsToFilter = window.allRows || allRows;
    console.log(`Filtering ${rowsToFilter.length} rows`); // Debug

    // Filter rows from the original set
    const filteredRows = rowsToFilter.filter(row => {
        // For debugging purposes
        if (!row) return false;
        
        // Skip header row
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
                
                console.log(`Row action: ${actionText}, Filter: ${actionFilter}, Match: ${matchesAction}`); // Debug
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
                
                console.log(`Row status: ${statusText}, Filter: ${statusFilter}, Match: ${matchesStatus}`); // Debug
            } else {
                matchesStatus = false;
            }
        }
        
        // Module filter
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
    
    console.log(`Filtered to ${filteredRows.length} rows`); // Debug

    // Get the tbody element
    const tbody = document.getElementById(tableId);
    if (!tbody) {
        console.error(`Could not find tbody with ID ${tableId}`); // Debug
        return;
    }
    
    // Store filtered rows in window for pagination
    window.filteredRows = filteredRows;
    
    // Clear the tbody
    tbody.innerHTML = '';
    
    // If no filtered rows, show a message
    if (filteredRows.length === 0) {
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
    } else {
        // Add filtered rows back to the table
        filteredRows.forEach(row => {
            tbody.appendChild(row.cloneNode(true));
        });
    }
    
    // After filtering, let pagination.js update the pagination
    if (typeof updatePagination === 'function') {
        updatePagination();
    }
}