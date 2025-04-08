// Global variable to store the original table rows
let allRows = [];

// On DOMContentLoaded, capture all original table rows
document.addEventListener('DOMContentLoaded', () => {
    allRows = Array.from(document.querySelectorAll('#auditTable tr'));
    updatePagination();
});

// Rebuild table body with only the rows that match the filters
function filterTable() {
    const searchFilter = document.getElementById('searchInput').value.toLowerCase();
    const actionFilter = document.getElementById('filterAction').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value.toLowerCase();

    // Filter rows from the original set
    const filteredRows = allRows.filter(row => {
        const actionCell = row.querySelector('[data-label="Action"]');
        const statusCell = row.querySelector('[data-label="Status"]');
        const actionText = actionCell ? actionCell.textContent.toLowerCase() : '';
        const statusText = statusCell ? statusCell.textContent.toLowerCase() : '';
        const rowText = row.textContent.toLowerCase();

        const matchesSearch = rowText.includes(searchFilter);
        const matchesAction = actionFilter === '' || actionText.includes(actionFilter);
        const matchesStatus = statusFilter === '' || statusText.includes(statusFilter);
        return matchesSearch && matchesAction && matchesStatus;
    });

    // Rebuild the <tbody> with only filtered rows
    const tbody = document.getElementById('auditTable');
    tbody.innerHTML = '';
    filteredRows.forEach(row => {
        row.style.display = ''; // ensure row is visible
        tbody.appendChild(row);
    });

    // Reset current page to 1 after filtering
    document.getElementById('currentPage').textContent = '1';
    updatePagination();
}

// Paginate only the rows currently in the table body (the filtered rows)
function updatePagination() {
    const tbody = document.getElementById('auditTable');
    const filteredRows = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = filteredRows.length;
    const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value, 10);
    let currentPage = parseInt(document.getElementById('currentPage').textContent, 10) || 1;
    const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;

    // Ensure currentPage is within bounds
    if (currentPage > totalPages) {
        currentPage = totalPages;
        document.getElementById('currentPage').textContent = currentPage;
    }

    // Update the "Showing X to Y of Z entries" display:
    const start = (currentPage - 1) * rowsPerPage + 1;
    const end = Math.min(currentPage * rowsPerPage, totalRows);
    document.getElementById('rowsPerPage').textContent = end;
    document.getElementById('totalRows').textContent = totalRows;

    // Show only the rows on the current page
    filteredRows.forEach((row, index) => {
        if (index >= (currentPage - 1) * rowsPerPage && index < currentPage * rowsPerPage) {
            row.style.display = '';  // Visible row
        } else {
            row.style.display = 'none'; // Hide this row
        }
    });

    // Update the pagination buttons
    document.getElementById('prevPage').disabled = (currentPage === 1);
    document.getElementById('nextPage').disabled = (currentPage >= totalPages);

    // Rebuild pagination links (optional)
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
        li.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('currentPage').textContent = i;
            updatePagination();
        });
        pagination.appendChild(li);
    }
}

// Attach event listeners to filter inputs and pagination controls
document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('filterAction').addEventListener('change', filterTable);
document.getElementById('filterStatus').addEventListener('change', filterTable);
document.getElementById('rowsPerPageSelect').addEventListener('change', updatePagination);

document.getElementById('prevPage').addEventListener('click', () => {
    const currentPage = parseInt(document.getElementById('currentPage').textContent, 10);
    if (currentPage > 1) {
        document.getElementById('currentPage').textContent = currentPage - 1;
        updatePagination();
    }
});

document.getElementById('nextPage').addEventListener('click', () => {
    const currentPage = parseInt(document.getElementById('currentPage').textContent, 10);
    const totalRows = parseInt(document.getElementById('totalRows').textContent, 10);
    const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value, 10);
    if (currentPage < Math.ceil(totalRows / rowsPerPage)) {
        document.getElementById('currentPage').textContent = currentPage + 1;
        updatePagination();
    }
});