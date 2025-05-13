let currentPage = 1;
let rowsPerPage;
let prevButton, nextButton, rowsSelect, currentPageSpan, rowsPerPageSpan, totalRowsSpan;

// Pagination function with simpler, more direct approach
function updatePagination() {
    // Get all data rows
    const allRows = document.querySelectorAll('#table table tbody tr');
    console.log("Total rows in table:", allRows.length);
    
    // Get the total rows from the hidden input if it exists
    const totalRowsInput = document.getElementById('total-users');
    let totalRows = allRows.length;
    
    if (totalRowsInput) {
        totalRows = parseInt(totalRowsInput.value, 10);
        console.log("Total rows from input:", totalRows);
    }
    
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

    // IMPORTANT: First hide ALL rows
    allRows.forEach(row => {
        row.style.display = 'none'; // Hide every row first
    });

    // Then only show the rows for current page
    for (let i = startIndex; i < Math.min(endIndex, allRows.length); i++) {
        if (allRows[i]) {
            allRows[i].style.display = ''; // Show row (using empty string reverts to default display value)
        }
    }

    // Update pagination info text
    const displayStart = totalRows === 0 ? 0 : startIndex + 1;
    const displayEnd = Math.min(endIndex, totalRows);

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

    // Get actual total rows, either from hidden input or table rows
    const totalRowsInput = document.getElementById('total-users');
    let totalRows = document.querySelectorAll('#table tbody tr').length;
    
    if (totalRowsInput) {
        totalRows = parseInt(totalRowsInput.value, 10);
    }
    
    const maxPages = Math.ceil(totalRows / rowsPerPage);
    
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

    // Calculate range of pages to show
    let startPage, endPage;
    if (maxPages <= 5) {
        startPage = 1;
        endPage = maxPages;
    } else {
        if (currentPage <= 3) {
            startPage = 1;
            endPage = 5;
        } else if (currentPage + 2 >= maxPages) {
            startPage = maxPages - 4;
            endPage = maxPages;
        } else {
            startPage = currentPage - 2;
            endPage = currentPage + 2;
        }
    }

    // Add first page + ellipsis if needed
    if (startPage > 1) {
        let li = document.createElement('li');
        li.className = 'page-item';
        li.innerHTML = `<a class="page-link" href="#">1</a>`;
        li.addEventListener('click', function (e) {
            e.preventDefault();
            currentPage = 1;
            updatePagination();
        });
        paginationContainer.appendChild(li);

        if (startPage > 2) {
            let ellipsis = document.createElement('li');
            ellipsis.className = 'page-item disabled';
            ellipsis.innerHTML = `<span class="page-link">...</span>`;
            paginationContainer.appendChild(ellipsis);
        }
    }

    // Add page numbers
    for (let i = startPage; i <= endPage; i++) {
        let li = document.createElement('li');
        li.className = 'page-item' + (i === currentPage ? ' active' : '');
        let a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = i;
        a.addEventListener('click', function (e) {
            e.preventDefault();
            currentPage = i;
            updatePagination();
        });
        li.appendChild(a);
        paginationContainer.appendChild(li);
    }

    // Add last page + ellipsis if needed
    if (endPage < maxPages) {
        if (endPage < maxPages - 1) {
            let ellipsis = document.createElement('li');
            ellipsis.className = 'page-item disabled';
            ellipsis.innerHTML = `<span class="page-link">...</span>`;
            paginationContainer.appendChild(ellipsis);
        }
        let li = document.createElement('li');
        li.className = 'page-item';
        li.innerHTML = `<a class="page-link" href="#">${maxPages}</a>`;
        li.addEventListener('click', function (e) {
            e.preventDefault();
            currentPage = maxPages;
            updatePagination();
        });
        paginationContainer.appendChild(li);
    }
}