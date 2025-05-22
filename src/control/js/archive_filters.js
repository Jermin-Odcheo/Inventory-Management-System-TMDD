/**
 * Archive Filtering Solution
 * This script provides filtering for all archive pages in the system
 */

// When document is ready
$(document).ready(function() {
    
    // Store original rows on page load
    var originalRows = [];
    
    function captureRows() {
        // Get rows from the table body (works with any table ID structure)
        const tbody = $('#archiveTable tbody, #archivedRolesTable tbody').get(0);
        if (tbody) {
            originalRows = $(tbody).find('tr').toArray();
        } else {
            console.warn("Could not find table body");
        }
    }
    
    // Initialize once
    captureRows();
    
    // Apply filters to rows
    function applyFilters() {
        // Get filter values
        const actionFilter = $('#filterAction').val().toLowerCase();
        const statusFilter = $('#filterStatus').val().toLowerCase();
        const searchFilter = $('#searchInput').val().toLowerCase();
    
        
        // Get the current tbody
        const tbody = $('#archiveTable tbody, #archivedRolesTable tbody').get(0);
        if (!tbody) {
            console.warn("Table body not found for filtering");
            return;
        }
        
        // Clone original rows
        const rows = originalRows.slice();
        
        // Clear tbody
        $(tbody).empty();
        
        // Counter for visible rows
        let visibleCount = 0;
        
        // Filter and add rows
        for (let i = 0; i < rows.length; i++) {
            const $row = $(rows[i]);
            let showRow = true;
            
            // Action filter
            if (actionFilter && showRow) {
                const $actionCell = $row.find('[data-label="Action"], .action');
                if ($actionCell.length) {
                    const actionText = $actionCell.text().toLowerCase().trim();
                    showRow = actionText.includes(actionFilter);
                }
            }
            
            // Status filter
            if (statusFilter && showRow) {
                const $statusCell = $row.find('[data-label="Status"], .status');
                if ($statusCell.length) {
                    const statusText = $statusCell.text().toLowerCase().trim();
                    showRow = statusText.includes(statusFilter);
                }
            }
            
            // Search filter
            if (searchFilter && showRow) {
                showRow = $row.text().toLowerCase().includes(searchFilter);
            }
            
            // Add row if it passes all filters
            if (showRow) {
                $(tbody).append($row);
                visibleCount++;
            }
        }
        
            
        // Show "no results" message if needed
        if (visibleCount === 0) {
            const noResultsRow = `
                <tr id="no-results-row">
                    <td colspan="10">
                        <div class="empty-state text-center py-4">
                            <i class="fas fa-search fa-3x mb-3"></i>
                            <h4>No matching records found</h4>
                            <p class="text-muted">Try adjusting your filter criteria.</p>
                        </div>
                    </td>
                </tr>
            `;
            $(tbody).html(noResultsRow);
        }
        
        // Update pagination if it exists
        if (typeof updatePagination === 'function') {
            window.filteredRows = $(tbody).find('tr').toArray();
            updatePagination();
        }
    }
    
    // Wire up event handlers
    $('#filterAction').on('change', applyFilters);
    $('#filterStatus').on('change', applyFilters);
    $('#searchInput').on('input', applyFilters);
    
    // Handle pagination button clicks (recapture rows when page changes)
    $('#prevPage, #nextPage, #pagination').on('click', function() {
        setTimeout(captureRows, 100);
    });
    
    // Handle rows per page changes
    $('#rowsPerPageSelect').on('change', function() {
        setTimeout(captureRows, 100);
    });
    
    // Handle DOM updates via AJAX
    $(document).ajaxComplete(function() {
        setTimeout(captureRows, 100);
    });
}); 