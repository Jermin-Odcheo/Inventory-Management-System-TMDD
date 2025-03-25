// Combined filtering function for search, action, and status filters.
function filterTable() {
    const searchFilter = document.getElementById('searchInput').value.toLowerCase();
    const actionFilter = document.getElementById('filterAction').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
    const rows = document.querySelectorAll('#auditTable tr');

    rows.forEach(row => {
        const actionText = row.querySelector('[data-label="Action"]').textContent.toLowerCase();
        const statusText = row.querySelector('[data-label="Status"]').textContent.toLowerCase();
        const rowText = row.textContent.toLowerCase();

        const matchesSearch = rowText.includes(searchFilter);
        const matchesAction = actionFilter === '' || actionText.includes(actionFilter);
        const matchesStatus = statusFilter === '' || statusText.includes(statusFilter);

        if (matchesSearch && matchesAction && matchesStatus) {
            row.style.display = '';
            row.style.opacity = '1';
        } else {
            row.style.opacity = '0';
            setTimeout(() => {
                row.style.display = 'none';
            }, 50); // Reduced from 300ms for minimal transition.
        }
    });

    setTimeout(updatePagination, 50); // Minimal delay before paginating.
}

document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('filterAction').addEventListener('change', filterTable);
document.getElementById('filterStatus').addEventListener('change', filterTable);

// Sorting functionality with minimal fade-out and fade-in transitions.
function sortTableByColumn(table, column, asc = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    // Fade out rows quickly.
    rows.forEach(row => {
        row.style.opacity = '0';
    });

    // Wait for the minimal fade-out transition.
    setTimeout(() => {
        const dirModifier = asc ? 1 : -1;
        const sortedRows = rows.sort((a, b) => {
            const aText = a.querySelector(`td:nth-child(${column + 1})`).textContent.trim();
            const bText = b.querySelector(`td:nth-child(${column + 1})`).textContent.trim();

            // Check if values are numeric.
            const aNum = parseFloat(aText.replace(/[^0-9.-]+/g, ""));
            const bNum = parseFloat(bText.replace(/[^0-9.-]+/g, ""));
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return (aNum - bNum) * dirModifier;
            }

            // Check if values are dates.
            const aDate = new Date(aText);
            const bDate = new Date(bText);
            if (!isNaN(aDate) && !isNaN(bDate)) {
                return (aDate - bDate) * dirModifier;
            }

            // Fallback to text comparison.
            return aText.localeCompare(bText) * dirModifier;
        });

        // Remove existing rows and re-add sorted rows.
        while (tbody.firstChild) {
            tbody.removeChild(tbody.firstChild);
        }
        sortedRows.forEach(row => {
            row.style.opacity = '0';  // Start faded out.
            tbody.appendChild(row);
        });

        // Fade in sorted rows quickly.
        setTimeout(() => {
            sortedRows.forEach(row => {
                row.style.opacity = '1';
            });
        }, 10); // Minimal fade-in delay.

        // Update header classes for sort indicators.
        table.querySelectorAll('th').forEach(th => th.classList.remove('th-sort-asc', 'th-sort-desc'));
        const targetHeader = table.querySelector(`thead th:nth-child(${column + 1})`);
        targetHeader.classList.toggle('th-sort-asc', asc);
        targetHeader.classList.toggle('th-sort-desc', !asc);
    }, 50); // Minimal fade-out delay.

    updatePagination();
}

// Attach click event listeners to each table header.
// For the Track ID column (index 0) default sort will be descending.
document.querySelectorAll('thead th').forEach((header, index) => {
    header.addEventListener('click', () => {
        const tableElement = header.closest('table');
        let asc;
        if (index === 0) {
            // For Track ID column, default to descending (highest to lowest).
            // If already sorted descending, then switch to ascending.
            asc = header.classList.contains('th-sort-desc') ? true : false;
        } else {
            // For other columns, toggle normal.
            asc = header.classList.contains('th-sort-asc') ? false : true;
        }
        sortTableByColumn(tableElement, index, asc);
    });
});
