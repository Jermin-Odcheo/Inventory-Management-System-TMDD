// Global variable to store the original table rows
// Only declare if it doesn't exist yet
if (typeof window.allRows === 'undefined') {
    window.allRows = [];
}

// On DOMContentLoaded, capture all original table rows
// and set up filter event listeners
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOMContentLoaded event fired');
    const tableId = 'auditTable';
    
    // Store all rows for filtering
    window.allRows = Array.from(document.querySelectorAll(`#${tableId} tr`));
    console.log('Initial rows found:', window.allRows.length);
    
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
    
    // Initial filter
    console.log('Calling initial filterTable');
    setTimeout(filterTable, 100); // Slight delay to ensure DOM is ready
});

// Filter table rows based on search and filter criteria
function filterTable() {
    console.log('filterTable function called');
    const searchFilter = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const actionFilter = document.getElementById('filterAction')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('filterStatus')?.value.toLowerCase() || '';
    const moduleFilter = document.getElementById('filterModule')?.value.toLowerCase() || '';
    
    const tableId = 'auditTable';
    const rowsToFilter = window.allRows || [];
    console.log('Rows to filter:', rowsToFilter.length);

    // Filter rows from the original set
    const filteredRows = rowsToFilter.filter(row => {
        if (!row) return false;
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
                const actionBadge = actionCell.querySelector('.action-badge');
                const actionText = actionBadge ? 
                    actionBadge.textContent.toLowerCase().trim() : 
                    actionCell.textContent.toLowerCase().trim();
                matchesAction = actionText.includes(actionFilter);
            } else {
                matchesAction = false;
            }
        }
        
        // Status filter
        if (statusFilter) {
            const statusCell = row.querySelector('[data-label="Status"]');
            if (statusCell) {
                const statusBadge = statusCell.querySelector('.badge');
                const statusText = statusBadge ? 
                    statusBadge.textContent.toLowerCase().trim() : 
                    statusCell.textContent.toLowerCase().trim();
                
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
    
    console.log('Filtered rows:', filteredRows.length);
    
    // Get the tbody element
    const tbody = document.getElementById(tableId);
    if (!tbody) {
        console.error(`Could not find tbody with ID ${tableId}`);
        return;
    }
    
    // Clear the tbody
    tbody.innerHTML = '';
    
    // If no filtered rows, show a message
    if (filteredRows.length === 0) {
        console.log('No matching records found');
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
        console.log('Adding filtered rows to table');
        // Add filtered rows back to the table
        filteredRows.forEach(row => {
            tbody.appendChild(row.cloneNode(true));
        });
    }
}