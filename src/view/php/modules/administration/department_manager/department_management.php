            // Add clear filters functionality
            $('#clearFilters').on('click', function() {
                // Clear search input
                $('#eqSearch').val('');
                
                // Reset sort headers
                $('.sortable').removeClass('asc desc');
                
                // Reset to initial sort (newest first)
                const initialSortHeader = $('.sortable[data-sort="acronym"]');
                initialSortHeader.addClass('desc');
                
                // Get original rows if not already stored
                if (!allTableRows || allTableRows.length === 0) {
                    allTableRows = $('#departmentTable tbody tr').get();
                }
                
                // Sort by ID descending (newest first)
                const rows = allTableRows;
                rows.sort(function(a, b) {
                    const idA = parseInt($(a).find('.edit-btn').data('id'));
                    const idB = parseInt($(b).find('.edit-btn').data('id'));
                    return idB - idA;
                });
                allTableRows = rows;
                
                // Reset to first page and render
                currentPage = 1;
                renderTableRows();
                updatePaginationControls();
            }); 