// Department Management Pagination Script

// Global variables for pagination
let departmentRows = [];
let filteredDepartmentRows = [];

// Configuration object with default values
const departmentPaginationConfig = {
    tableId: 'departmentTable',  // Department table ID
    currentPage: 1,             // Current page
    rowsPerPageSelectId: 'rowsPerPageSelect',
    currentPageId: 'currentPage',
    rowsPerPageId: 'rowsPerPage',
    totalRowsId: 'totalRows',
    prevPageId: 'prevPage',
    nextPageId: 'nextPage',
    paginationId: 'pagination'
};

// Initialize pagination for department table
function initDepartmentPagination() {
 
    
    // Get all rows from the department table (excluding header row)
    const tableBody = document.querySelector('#departmentTable tbody');
    if (tableBody) {
        departmentRows = Array.from(tableBody.querySelectorAll('tr'));
        console.log(`department_pagination.js: Found ${departmentRows.length} department rows`);
        
        // Store original rows for reference
        filteredDepartmentRows = [...departmentRows];
        
        // Set up event listeners
        setupDepartmentEventListeners();
        
        // Perform initial pagination
        updateDepartmentPagination();
       
    } else {
        console.error('department_pagination.js: Department table body not found');
    }
}

// Set up event listeners for department table
function setupDepartmentEventListeners() {
   
    
    // Search input listener
    const searchInput = document.getElementById('eqSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterDepartmentTable(this.value.toLowerCase());
        });
        
    }
    
    // Rows per page select listener
    const rowsPerPageSelect = document.getElementById(departmentPaginationConfig.rowsPerPageSelectId);
    if (rowsPerPageSelect) {
        rowsPerPageSelect.addEventListener('change', () => {
            
            departmentPaginationConfig.currentPage = 1; // Reset to first page
            updateDepartmentPagination();
        });
     
    }
    
    // Previous page button listener
    const prevButton = document.getElementById(departmentPaginationConfig.prevPageId);
    if (prevButton) {
        prevButton.addEventListener('click', (e) => {
            e.preventDefault();
            if (departmentPaginationConfig.currentPage > 1) {
                departmentPaginationConfig.currentPage--;
                updateDepartmentPagination();
            }
        });
    
    }
    
    // Next page button listener
    const nextButton = document.getElementById(departmentPaginationConfig.nextPageId);
    if (nextButton) {
        nextButton.addEventListener('click', (e) => {
            e.preventDefault();
            const totalPages = getDepartmentTotalPages();
            if (departmentPaginationConfig.currentPage < totalPages) {
                departmentPaginationConfig.currentPage++;
                updateDepartmentPagination();
            }
        });
  
    }
}

// Filter department table based on search input
function filterDepartmentTable(searchTerm = '') {
 
    
    if (searchTerm === '') {
        // If no search term, show all rows
        filteredDepartmentRows = [...departmentRows];
    } else {
        // Filter rows based on search input
        filteredDepartmentRows = departmentRows.filter(row => {
            if (!row) return false;
            return row.textContent.toLowerCase().includes(searchTerm);
        });
    }
    
   
    
    // Reset to first page after filtering
    departmentPaginationConfig.currentPage = 1;
    
    // Update pagination
    updateDepartmentPagination();
}

// Update department pagination based on current page and filters
function updateDepartmentPagination() {
    console.log(`department_pagination.js: Updating pagination. Current page: ${departmentPaginationConfig.currentPage}`);
    
    // Get pagination parameters
    const rowsPerPageSelect = document.getElementById(departmentPaginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    const totalRows = filteredDepartmentRows.length;
    const totalPages = getDepartmentTotalPages();
    
    // Calculate start and end indices for current page
    const startIndex = (departmentPaginationConfig.currentPage - 1) * rowsPerPage;
    const endIndex = Math.min(startIndex + rowsPerPage, totalRows);
    
    console.log(`department_pagination.js: Showing rows ${startIndex + 1} to ${endIndex} of ${totalRows}`);
    
    // Hide all rows first
    departmentRows.forEach(row => {
        row.style.display = 'none';
    });
    
    // Show only rows for current page
    for (let i = startIndex; i < endIndex; i++) {
        if (filteredDepartmentRows[i]) {
            filteredDepartmentRows[i].style.display = '';
        }
    }
    
    // Update pagination info text
    const currentPageSpan = document.getElementById(departmentPaginationConfig.currentPageId);
    const rowsPerPageSpan = document.getElementById(departmentPaginationConfig.rowsPerPageId);
    const totalRowsSpan = document.getElementById(departmentPaginationConfig.totalRowsId);
    
    if (currentPageSpan) currentPageSpan.textContent = startIndex + 1;
    if (rowsPerPageSpan) rowsPerPageSpan.textContent = Math.min(endIndex, totalRows);
    if (totalRowsSpan) totalRowsSpan.textContent = totalRows;
    
    // Update pagination controls
    renderDepartmentPaginationControls(totalPages);
    
    // Enable/disable prev/next buttons
    const prevButton = document.getElementById(departmentPaginationConfig.prevPageId);
    const nextButton = document.getElementById(departmentPaginationConfig.nextPageId);
    
    if (prevButton) {
        prevButton.disabled = departmentPaginationConfig.currentPage === 1;
    }
    
    if (nextButton) {
        nextButton.disabled = departmentPaginationConfig.currentPage === totalPages;
    }
}

// Get total number of pages
function getDepartmentTotalPages() {
    const rowsPerPageSelect = document.getElementById(departmentPaginationConfig.rowsPerPageSelectId);
    const rowsPerPage = parseInt(rowsPerPageSelect?.value || '10');
    return Math.ceil(filteredDepartmentRows.length / rowsPerPage) || 1;
}

// Render pagination controls (page numbers)
function renderDepartmentPaginationControls(totalPages) {
    const paginationContainer = document.getElementById(departmentPaginationConfig.paginationId);
    if (!paginationContainer) return;
    
    paginationContainer.innerHTML = '';
    
    // Don't show pagination if only one page
    if (totalPages <= 1) return;
    
    // Determine which page numbers to show
    let startPage = Math.max(1, departmentPaginationConfig.currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    
    // Adjust start page if end page is at max
    if (endPage === totalPages) {
        startPage = Math.max(1, endPage - 4);
    }
    
    // First page
    if (startPage > 1) {
        addDepartmentPaginationItem(paginationContainer, 1);
        if (startPage > 2) {
            // Add ellipsis if there's a gap
            const ellipsis = document.createElement('li');
            ellipsis.className = 'page-item disabled';
            ellipsis.innerHTML = '<span class="page-link">...</span>';
            paginationContainer.appendChild(ellipsis);
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        addDepartmentPaginationItem(paginationContainer, i, i === departmentPaginationConfig.currentPage);
    }
    
    // Last page
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            // Add ellipsis if there's a gap
            const ellipsis = document.createElement('li');
            ellipsis.className = 'page-item disabled';
            ellipsis.innerHTML = '<span class="page-link">...</span>';
            paginationContainer.appendChild(ellipsis);
        }
        addDepartmentPaginationItem(paginationContainer, totalPages);
    }
}

// Add a pagination item (page number)
function addDepartmentPaginationItem(container, page, isActive = false) {
    const item = document.createElement('li');
    item.className = `page-item ${isActive ? 'active' : ''}`;
    
    const link = document.createElement('a');
    link.className = 'page-link';
    link.href = '#';
    link.textContent = page;
    
    link.addEventListener('click', (e) => {
        e.preventDefault();
        departmentPaginationConfig.currentPage = page;
        updateDepartmentPagination();
    });
    
    item.appendChild(link);
    container.appendChild(item);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initDepartmentPagination);

// Export function for manual initialization
window.updateDepartmentPagination = updateDepartmentPagination;
window.initDepartmentPagination = initDepartmentPagination; 