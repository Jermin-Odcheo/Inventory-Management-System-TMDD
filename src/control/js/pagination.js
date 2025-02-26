// Global Variables
let currentPage = 1;
let rowsPerPage;
let prevButton, nextButton, rowsSelect, currentPageSpan, rowsPerPageSpan, totalRowsSpan;

// Pagination function
function updatePagination() {
    const rows = document.querySelectorAll('#table tbody tr');
    const totalRows = rows.length;
    totalRowsSpan.textContent = totalRows;

    const maxPages = Math.ceil(totalRows / rowsPerPage);
    currentPage = Math.max(1, Math.min(currentPage, maxPages));

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    // Step 1: Fade out all rows first
    rows.forEach((row) => {
        row.style.opacity = "0"; // Fade out
        row.style.transform = "translateY(10px)";
    });

    // Step 2: Wait for the fade-out animation, then update visibility
    setTimeout(() => {
        rows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.classList.remove('hidden-row'); // Show row
                setTimeout(() => {
                    row.style.opacity = "1"; // Fade in
                    row.style.transform = "translateY(0)";
                }, 100);
            } else {
                row.classList.add('hidden-row'); // Hide row
            }
        });
    }, 200); // Wait 200ms for fade-out to complete

    currentPageSpan.textContent = currentPage;
    rowsPerPageSpan.textContent = rowsPerPage;

    // Show or hide the Previous and Next buttons
    if (currentPage === 1) {
        prevButton.style.setProperty('display', 'none', 'important');
    } else {
        prevButton.style.setProperty('display', 'inline-block', 'important');
    }

    if (currentPage >= maxPages) {
        nextButton.style.setProperty('display', 'none', 'important');
    } else {
        nextButton.style.setProperty('display', 'inline-block', 'important');
    }

    // Render the page numbers
    renderPagination();
}

// Event Listener for DOMContentLoaded
document.addEventListener('DOMContentLoaded', function () {
    prevButton = document.getElementById('prevPage');
    nextButton = document.getElementById('nextPage');
    rowsSelect = document.getElementById('rowsPerPageSelect');
    currentPageSpan = document.getElementById('currentPage');
    rowsPerPageSpan = document.getElementById('rowsPerPage');
    totalRowsSpan = document.getElementById('totalRows');

    rowsPerPage = parseInt(rowsSelect.value);

    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            updatePagination();
        }
    });

    nextButton.addEventListener('click', function () {
        const rowsCount = document.querySelectorAll('#table tbody tr').length;
        const maxPages = Math.ceil(rowsCount / rowsPerPage);
        if (currentPage < maxPages) {
            nextButton.classList.add('loading');
            setTimeout(() => {
                currentPage++;
                updatePagination();
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

    const rows = document.querySelectorAll('#table tbody tr');
    const totalRows = rows.length;
    const maxPages = Math.ceil(totalRows / rowsPerPage);

    if (maxPages <= 1) return;

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

    if (startPage > 1) {
        let li = document.createElement('li');
        li.className = 'page-item';
        li.innerHTML = `<a class="page-link" href="#">1</a>`;
        li.addEventListener('click', function (e) {
            e.preventDefault();
            currentPage = 1;
            updatePagination();
            renderPagination();
        });
        paginationContainer.appendChild(li);

        if (startPage > 2) {
            let ellipsis = document.createElement('li');
            ellipsis.className = 'page-item disabled';
            ellipsis.innerHTML = `<span class="page-link">...</span>`;
            paginationContainer.appendChild(ellipsis);
        }
    }

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
            renderPagination();
        });
        li.appendChild(a);
        paginationContainer.appendChild(li);
    }

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
            renderPagination();
        });
        paginationContainer.appendChild(li);
    }
}