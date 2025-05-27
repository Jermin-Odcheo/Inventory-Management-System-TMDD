document.addEventListener('DOMContentLoaded', function() {
    const sortableHeaders = document.querySelectorAll('th.sortable');

    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortBy = this.dataset.sortBy;
            let sortOrder = this.dataset.sortOrder || 'asc'; // Default to ascending if not set

            console.log(`[CLICK] Header clicked: "${this.textContent.trim()}", data-sort-by: "${sortBy}", current sortOrder: "${sortOrder}"`);

            // Toggle sort order
            if (this.classList.contains('asc')) {
                sortOrder = 'desc';
            } else if (this.classList.contains('desc')) {
                sortOrder = 'asc';
            } else {
                // If no order is set (first click), default to asc
                sortOrder = 'asc';
            }

            // Construct new URL parameters
            const url = new URL(window.location.href);
            url.searchParams.set('sort_by', sortBy);
            url.searchParams.set('sort_order', sortOrder);

            console.log(`[CLICK] Redirecting to: ${url.toString()}`);
            // Redirect to the new URL to trigger server-side sorting
            window.location.href = url.toString();
        });
    });

    // On page load, apply active sort classes and icons
    const urlParams = new URLSearchParams(window.location.search);
    const currentSortBy = urlParams.get('sort_by');
    const currentSortOrder = urlParams.get('sort_order');

    console.log(`[LOAD] Page loaded. URL params - sort_by: "${currentSortBy}", sort_order: "${currentSortOrder}"`);

    if (currentSortBy && currentSortOrder) {
        // Find the active header based on data-sort-by attribute
        const activeHeader = document.querySelector(`th.sortable[data-sort-by="${currentSortBy}"]`);
        
        if (activeHeader) {
            console.log(`[LOAD] Active header identified: "${activeHeader.textContent.trim()}"`);

            // Iterate over all sortable headers to reset their icons and classes
            sortableHeaders.forEach(header => {
                header.classList.remove('asc', 'desc'); // Remove active sort classes
                const icon = header.querySelector('.fas'); // Get the Font Awesome icon within this header

                if (icon) {
                    // Remove all specific sort direction classes
                    icon.classList.remove('fa-sort-up', 'fa-sort-down');
                    // Ensure the default sort icon is present
                    icon.classList.add('fa-sort');
                    console.log(`[LOAD] Resetting icon for header: "${header.textContent.trim()}"`);
                } else {
                    console.warn(`[LOAD] No .fas icon found in header: "${header.textContent.trim()}"`);
                }
            });

            // Apply the active sort class to the currently sorted header
            activeHeader.classList.add(currentSortOrder);
            activeHeader.dataset.sortOrder = currentSortOrder; // Update its data-sort-order attribute

            // Get the specific icon for the active header
            const activeIcon = activeHeader.querySelector('.fas');

            if (activeIcon) {
                // Remove the default sort icon class
                activeIcon.classList.remove('fa-sort');
                // Add the appropriate sort direction icon
                if (currentSortOrder === 'asc') {
                    activeIcon.classList.add('fa-sort-up');
                } else {
                    activeIcon.classList.add('fa-sort-down');
                }
                console.log(`[LOAD] Applied "${currentSortOrder}" icon to active header: "${activeHeader.textContent.trim()}"`);
            } else {
                console.error(`[LOAD] Critical: No .fas icon found in active header "${activeHeader.textContent.trim()}" with data-sort-by="${currentSortBy}".`);
            }
        } else {
            console.warn(`[LOAD] No sortable header found matching data-sort-by="${currentSortBy}". Icon will not be updated.`);
        }
    } else {
        console.log('[LOAD] No sorting parameters in URL. Displaying default icons.');
        // Ensure all icons are default if no sorting is applied
        sortableHeaders.forEach(header => {
            header.classList.remove('asc', 'desc');
            const icon = header.querySelector('.fas');
            if (icon) {
                icon.classList.remove('fa-sort-up', 'fa-sort-down');
                icon.classList.add('fa-sort');
            }
        });
    }
});
