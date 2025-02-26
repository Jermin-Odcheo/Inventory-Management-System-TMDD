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
            }, 300); // Match the CSS transition duration.
        }
    });

    setTimeout(updatePagination, 300); // Wait for fade-out before paginating
}

document.getElementById('searchInput').addEventListener('keyup', filterTable);
document.getElementById('filterAction').addEventListener('change', filterTable);
document.getElementById('filterStatus').addEventListener('change', filterTable);

// Sorting functionality with smooth fade-out and fade-in transitions.
function sortTableByColumn(table, column, asc = true) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    // Fade out rows.
    rows.forEach(row => {
        row.style.opacity = '0';
    });


    // Wait for the fade-out transition.
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
            row.style.opacity = '0';  // Ensure they start faded out.
            tbody.appendChild(row);
        });

        // Fade in sorted rows.
        setTimeout(() => {
            sortedRows.forEach(row => {
                row.style.opacity = '1';
            });
        }, 50);

        // Update header classes for sort indicators.
        table.querySelectorAll('th').forEach(th => th.classList.remove('th-sort-asc', 'th-sort-desc'));
        table.querySelector(`thead th:nth-child(${column + 1})`).classList.toggle('th-sort-asc', asc);
        table.querySelector(`thead th:nth-child(${column + 1})`).classList.toggle('th-sort-desc', !asc);
    }, 300); // 300ms matches the CSS transition duration.

    updatePagination();
}

// Attach click event listeners to each table header.
document.querySelectorAll('thead th').forEach((header, index) => {
    header.addEventListener('click', () => {
        const tableElement = header.closest('table');
        const currentIsAscending = header.classList.contains('th-sort-asc');
        sortTableByColumn(tableElement, index, !currentIsAscending);
    });
});
