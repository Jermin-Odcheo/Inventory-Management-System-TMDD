let currentPage = 1;
let rowsPerPage;
let prevButton, nextButton, rowsSelect, currentPageSpan, rowsPerPageSpan, totalRowsSpan;

// Pagination function with simpler, more direct approach
function updatePagination() {
    // Try different selectors to find the table rows
    let tableRows = document.querySelectorAll('#table table tbody tr');
    
    // If no rows found with first selector, try alternatives
    if (!tableRows || tableRows.length <= 1) {
        tableRows = document.querySelectorAll('#table tbody tr');
    }
    
    // If still no rows, try more generic selector
    if (!tableRows || tableRows.length <= 1) {
        tableRows = document.querySelectorAll('table tbody tr');
    }
    
    console.log("Selected rows count:", tableRows.length);
    
    const uniqueUsernames = new Set();
    
    tableRows.forEach(row => {
        const userCell = row.querySelector('td:nth-child(2)');
        if (userCell) {
            const username = userCell.textContent.trim();
            if (username) {
                uniqueUsernames.add(username);
            }
        }
    });
    
    // Get the count of unique users
    let totalRows = uniqueUsernames.size;
    
    // Get the total rows from the hidden input if it exists
    const totalRowsInput = document.getElementById('total-users');
    if (totalRowsInput) {
        totalRows = parseInt(totalRowsInput.value, 10);
        console.log("Total rows from input:", totalRows);
    }

    // Fallback method: If total rows is still 0 or 1, count all visible rows
    if (totalRows <= 1 && tableRows.length > 1) {
        console.log("Using fallback row counting method");
        totalRows = tableRows.length;
    }
    
    // Debug
    console.log("Total rows calculated:", totalRows, "Table rows count:", tableRows.length);
    
    totalRowsSpan.textContent = totalRows;

    if (totalRows === 0) {
        console.log("Table is empty. Setting pagination to 0.");

        // Update pagination info for empty table
        currentPageSpan.textContent = '0';
        rowsPerPageSpan.textContent = '0';
        totalRowsSpan.textContent = '0';

        // Hide navigation buttons
        prevButton.style.display = 'none';
        nextButton.style.display = 'none';

        // Clear pagination numbers
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';

        return; // Exit the function early
    }

    // Calculate page information
    const maxPages = Math.ceil(totalRows / rowsPerPage);
    currentPage = Math.max(1, Math.min(currentPage, maxPages)); // Make sure current page is valid

    // Calculate which rows to show
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, totalRows);

    console.log(`Page ${currentPage}: Showing rows ${startIndex} to ${endIndex-1} of ${totalRows}`);

    // IMPROVED ROW DISPLAY LOGIC
    // First hide ALL rows
    tableRows.forEach(row => {
        row.style.display = 'none'; // Hide every row first
    });

    // Convert tableRows to array for easier manipulation
    const rowsArray = Array.from(tableRows);
    
    // For debugging
    console.log(`Looking to display rows from index ${startIndex} to ${endIndex-1}`);

    // SIMPLIFIED APPROACH: Direct pagination without user grouping
    // For cases where we're not grouping by user, just show rows by index
    let displayedCount = 0;
    let currentRowIndex = 0;
    
    // Skip rows until we reach our starting point
    for (let i = 0; i < rowsArray.length && displayedCount < rowsPerPage; i++) {
        if (currentRowIndex >= startIndex && currentRowIndex < endIndex) {
            rowsArray[i].style.display = '';
            displayedCount++;
        }
        currentRowIndex++;
    }
    
    // Log how many rows were displayed
    console.log(`Displayed ${displayedCount} rows for page ${currentPage}`);

    // Update pagination info text
    const displayStart = totalRows === 0 ? 0 : startIndex + 1;
    const displayEnd = Math.min(startIndex + rowsPerPage, totalRows);

    // Ensure correct text when table is empty
    if (totalRows === 0) {
        currentPageSpan.textContent = '0';
        rowsPerPageSpan.textContent = '0';
        totalRowsSpan.textContent = '0';
    } else {
        currentPageSpan.textContent = displayStart;
        rowsPerPageSpan.textContent = displayEnd;
        totalRowsSpan.textContent = totalRows;
    }

    // Hide both navigation buttons if all data fits on one page
    if (maxPages <= 1) {
        prevButton.style.setProperty('display', 'none', 'important');
        nextButton.style.setProperty('display', 'none', 'important');
    } else {
        // Otherwise show/hide based on current page position
        if (currentPage <= 1) {
            prevButton.style.setProperty('display', 'none', 'important');
        } else {
            prevButton.style.setProperty('display', 'inline-block', 'important');
        }
        
        if (currentPage >= maxPages) {
            nextButton.style.setProperty('display', 'none', 'important');
        } else {
            nextButton.style.setProperty('display', 'inline-block', 'important');
        }
    }

    // Scroll to top of table
    const tableElement = document.getElementById('table');
    if (tableElement) {
        tableElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Update pagination numbers
    renderPagination();
}

// Event Listener for DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
    // Get DOM elements
    prevButton = document.getElementById('prevPage');
    nextButton = document.getElementById('nextPage');
    rowsSelect = document.getElementById('rowsPerPageSelect');
    currentPageSpan = document.getElementById('currentPage');
    rowsPerPageSpan = document.getElementById('rowsPerPage');
    totalRowsSpan = document.getElementById('totalRows');

    // Set initial rows per page
    rowsPerPage = parseInt(rowsSelect.value);

    // Add event listeners
    prevButton.addEventListener('click', function (e) {
        e.preventDefault();
        if (currentPage > 1) {
            currentPage--;
            updatePagination();
        }
    });

    nextButton.addEventListener('click', function (e) {
        e.preventDefault();
        // Get actual total rows, either from hidden input or table rows
        const totalRowsInput = document.getElementById('total-users');
        let totalRows = document.querySelectorAll('#table tbody tr').length;
        
        if (totalRowsInput) {
            totalRows = parseInt(totalRowsInput.value, 10);
        }
        
        const maxPages = Math.ceil(totalRows / rowsPerPage);

        console.log("Next button clicked. Current page:", currentPage, "Max pages:", maxPages);

        if (currentPage < maxPages) {
            nextButton.classList.add('loading');
            currentPage++;
            updatePagination();
            setTimeout(() => {
                nextButton.classList.remove('loading');
            }, 300);
        }
    });

    rowsSelect.addEventListener('change', function () {
        rowsPerPage = parseInt(this.value);
        currentPage = 1; // Reset to first page
        updatePagination();
    });

    // Initial setup
    updatePagination();
});

// Render pagination numbers
function renderPagination() {
    const paginationContainer = document.getElementById('pagination');
    paginationContainer.innerHTML = '';

    // Try different selectors to find the table rows
    let paginationRows = document.querySelectorAll('#table table tbody tr');
    
    // If no rows found with first selector, try alternatives
    if (!paginationRows || paginationRows.length <= 1) {
        paginationRows = document.querySelectorAll('#table tbody tr');
    }
    
    // If still no rows, try more generic selector
    if (!paginationRows || paginationRows.length <= 1) {
        paginationRows = document.querySelectorAll('table tbody tr');
    }
    
    console.log("renderPagination selected rows count:", paginationRows.length);
    
    const uniqueUsernames = new Set();
    
    paginationRows.forEach(row => {
        const userCell = row.querySelector('td:nth-child(2)');
        if (userCell) {
            const username = userCell.textContent.trim();
            if (username) {
                uniqueUsernames.add(username);
            }
        }
    });
    
    // Get the count of unique users
    let totalRows = uniqueUsernames.size;
    
    // Get the total rows from the hidden input if it exists
    const totalRowsInput = document.getElementById('total-users');
    if (totalRowsInput) {
        totalRows = parseInt(totalRowsInput.value, 10);
    }
    
    // Fallback method: If total rows is still 0 or 1, count all visible rows
    if (totalRows <= 1 && paginationRows.length > 1) {
        console.log("Using fallback row counting method in renderPagination");
        totalRows = paginationRows.length;
    }
    
    const maxPages = Math.ceil(totalRows / rowsPerPage);
    
    // Debug
    console.log("renderPagination - totalRows:", totalRows, "maxPages:", maxPages);
    
    // Don't show pagination controls if no data or all fits on one page
    if (totalRows === 0 || maxPages <= 1) {
        // Hide both navigation buttons when all data fits on one page
        if (prevButton) prevButton.style.setProperty('display', 'none', 'important');
        if (nextButton) nextButton.style.setProperty('display', 'none', 'important');
        return;
    }

    // Otherwise, manage button visibility based on current page
    if (prevButton) {
        if (currentPage <= 1) {
            prevButton.style.setProperty('display', 'none', 'important');
        } else {
            prevButton.style.setProperty('display', 'inline-block', 'important');
        }
    }
    
    if (nextButton) {
        if (currentPage >= maxPages) {
            nextButton.style.setProperty('display', 'none', 'important');
        } else {
            nextButton.style.setProperty('display', 'inline-block', 'important');
        }
    }

    // Calculate range of pages to show - MODIFIED FOR BETTER PAGINATION
    let visiblePages = [];
    
    // Always include page 1
    visiblePages.push(1);
    
    // If we have lots of pages, limit how many we show
    if (maxPages > 9) {
        // Current page and surrounding pages
        for (let i = Math.max(2, currentPage - 2); i <= Math.min(maxPages - 1, currentPage + 2); i++) {
            visiblePages.push(i);
        }
        
        // Always include last page
        visiblePages.push(maxPages);
        
        // Sort and deduplicate
        visiblePages = [...new Set(visiblePages)].sort((a, b) => a - b);
        
        // Add ellipses where needed
        let finalPages = [];
        for (let i = 0; i < visiblePages.length; i++) {
            finalPages.push(visiblePages[i]);
            
            // Add ellipsis if there's a gap
            if (i < visiblePages.length - 1 && visiblePages[i + 1] > visiblePages[i] + 1) {
                finalPages.push('...');
            }
        }
        
        visiblePages = finalPages;
    } else {
        // For fewer pages, show all page numbers
        visiblePages = Array.from({length: maxPages}, (_, i) => i + 1);
    }
    
    // Create the pagination buttons
    visiblePages.forEach(page => {
        let li = document.createElement('li');
        
        if (page === '...') {
            // Ellipsis
            li.className = 'page-item disabled';
            li.innerHTML = '<span class="page-link">...</span>';
        } else {
            // Regular page number
            li.className = 'page-item' + (page === currentPage ? ' active' : '');
            let a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.textContent = page;
            a.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = page;
                updatePagination();
            });
            li.appendChild(a);
        }
        
        paginationContainer.appendChild(li);
    });
}