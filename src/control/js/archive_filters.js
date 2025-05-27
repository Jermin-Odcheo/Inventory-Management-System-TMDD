/**
 * Archive Filtering Solution
 * This script provides filtering for all archive pages in the system.
 * Vanilla JavaScript version (no jQuery dependency)
 */

// When document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Store original rows on page load
    let originalRows = [];
    
    function captureRows() {
        // Get rows from the table body (works with any table ID structure)
        const tbody = document.querySelector('#archiveTableBody, #archivedRolesTable tbody');
        if (tbody) {
            originalRows = Array.from(tbody.querySelectorAll('tr'));
        } else {
            console.warn("Could not find table body");
        }
    }
    
    // Initialize once
    captureRows();
    
    // Function to clear all filters
    function clearFilters() {
        // Reset filter values
        const filterAction = document.getElementById('filterAction');
        if (filterAction) filterAction.value = '';
        
        const filterStatus = document.getElementById('filterStatus');
        if (filterStatus) filterStatus.value = '';
        
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';
        
        // Reset Select2 if it exists
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            jQuery('#filterAction, #filterStatus').trigger('change');
        }
        
        // Re-apply filters (which will show all rows since filters are cleared)
        applyFilters();
    }
    
    // Apply filters to rows
    function applyFilters() {
        // Get filter values
        const actionFilter = document.getElementById('filterAction')?.value.toLowerCase() || '';
        const statusFilter = document.getElementById('filterStatus')?.value.toLowerCase() || '';
        const searchFilter = document.getElementById('searchInput')?.value.toLowerCase() || '';
    
        // Get the current tbody
        const tbody = document.querySelector('#archiveTableBody, #archivedRolesTable tbody');
        if (!tbody) {
            console.warn("Table body not found for filtering");
            return;
        }
        
        // Clone original rows
        const rows = originalRows.slice();
        
        // Clear tbody
        tbody.innerHTML = '';
        
        // Counter for visible rows
        let visibleCount = 0;
        
        // Filter and add rows
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            let showRow = true;
            
            // Action filter - improved to better match action text
            if (actionFilter && showRow) {
                const actionCell = row.querySelector('[data-label="Action"], .action-cell, .action');
                if (actionCell) {
                    // Get the actual text content, remove any extra whitespace and convert to lowercase
                    const actionText = actionCell.textContent.toLowerCase().trim();
                    
                    // Check if the filter value is contained within the action text
                    // This is more reliable than exact matching
                    showRow = actionText.includes(actionFilter);
                }
            }
            
            // Status filter - improved to better match status text
            if (statusFilter && showRow) {
                const statusCell = row.querySelector('[data-label="Status"], .status-cell, .status');
                if (statusCell) {
                    // Get the actual text content, remove any extra whitespace and convert to lowercase
                    const statusText = statusCell.textContent.toLowerCase().trim();
                    
                    // Check if the filter value is contained within the status text
                    showRow = statusText.includes(statusFilter);
                }
            }
            
            // Search filter
            if (searchFilter && showRow) {
                showRow = row.textContent.toLowerCase().includes(searchFilter);
            }
            
            // Add row if it passes all filters
            if (showRow) {
                tbody.appendChild(row.cloneNode(true));
                visibleCount++;
            }
        }
        
        // Show "no results" message if needed
        if (visibleCount === 0) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.id = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="10">
                    <div class="empty-state text-center py-4">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <h4>No matching records found</h4>
                        <p class="text-muted">Try adjusting your filter criteria.</p>
                    </div>
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }
        
        // Update pagination if it exists
        if (typeof updatePagination === 'function') {
            window.filteredRows = Array.from(tbody.querySelectorAll('tr'));
            updatePagination();
        }
    }
    
    // Wire up event handlers
    const filterAction = document.getElementById('filterAction');
    if (filterAction) {
        filterAction.addEventListener('change', applyFilters);
    }
    
    const filterStatus = document.getElementById('filterStatus');
    if (filterStatus) {
        filterStatus.addEventListener('change', applyFilters);
    }
    
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Add debounce for search input to improve performance
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(applyFilters, 300);
        });
    }
    
    // Wire up clear filters button
    const clearFiltersBtn = document.getElementById('clearArchiveFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', clearFilters);
    }
    
    // Handle pagination button clicks
    const prevPage = document.getElementById('prevPage');
    const nextPage = document.getElementById('nextPage');
    const pagination = document.getElementById('pagination');
    
    if (prevPage) prevPage.addEventListener('click', () => setTimeout(captureRows, 100));
    if (nextPage) nextPage.addEventListener('click', () => setTimeout(captureRows, 100));
    if (pagination) pagination.addEventListener('click', () => setTimeout(captureRows, 100));
    
    // Handle rows per page changes
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => setTimeout(captureRows, 100));
    }
    
    // Make functions available globally for AJAX callbacks
    window.archiveFilters = {
        captureRows: captureRows,
        applyFilters: applyFilters,
        clearFilters: clearFilters
    };
}); 