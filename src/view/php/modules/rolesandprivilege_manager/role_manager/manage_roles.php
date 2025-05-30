<?php
ob_start();
require_once('../../../../../../config/ims-tmdd.php');
session_start();
include '../../../general/header.php';
include '../../../general/sidebar.php';
include '../../../general/footer.php';
// 1) Auth guard
$userId = $_SESSION['user_id'] ?? null;
if (!is_int($userId) && !ctype_digit((string)$userId)) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}
$userId = (int)$userId;

// 2) Init RBAC & enforce "View"
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Roles and Privileges', 'View');

// 3) Button flagsStatus
$canCreate = $rbac->hasPrivilege('Roles and Privileges', 'Create');
$canModify = $rbac->hasPrivilege('Roles and Privileges', 'Modify');
$canRemove = $rbac->hasPrivilege('Roles and Privileges', 'Remove');
$canUndo = $rbac->hasPrivilege('Roles and Privileges', 'Undo');
$canRedo = $rbac->hasPrivilege('Roles and Privileges', 'Redo');
$canViewArchive = $rbac->hasPrivilege('Roles and Privileges', 'View');

// In manage_roles.php, update the SQL query to:
$sql = "
SELECT 
    r.id AS Role_ID,
    r.role_name AS Role_Name,
    m.id AS Module_ID,
    m.module_name AS Module_Name,
    COALESCE((
        SELECT GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ')
        FROM role_module_privileges rmp2
        JOIN privileges p ON p.id = rmp2.privilege_id
        WHERE rmp2.role_id = r.id
          AND rmp2.module_id = m.id
    ), 'No privileges') AS Privileges
FROM roles r
CROSS JOIN modules m
WHERE r.is_disabled = 0
ORDER BY r.id, m.id;
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group data by role and module with improved uniqueness handling
$roles = [];
foreach ($roleData as $row) {
    $roleID = $row['Role_ID'];
    $moduleID = $row['Module_ID'];
    $moduleName = !empty($row['Module_Name']) ? $row['Module_Name'] : 'General';
    
    if (!isset($roles[$roleID])) {
        $roles[$roleID] = [
            'Role_Name' => $row['Role_Name'],
            'Modules' => []
        ];
    }
    
    // Ensure each module is stored separately
    if (!isset($roles[$roleID]['Modules'][$moduleName])) {
        $roles[$roleID]['Modules'][$moduleName] = [];
    }
    
    // Only add privileges if they exist
    if ($row['Privileges'] !== 'No privileges') {
        // Split the privileges string and add each privilege individually
        foreach (explode(', ', $row['Privileges']) as $privilege) {
            if (!empty($privilege) && !in_array($privilege, $roles[$roleID]['Modules'][$moduleName])) {
                $roles[$roleID]['Modules'][$moduleName][] = $privilege;
            }
        }
    }
}

// Ensure unique privileges per module
foreach ($roles as $roleID => &$role) {
    foreach ($role['Modules'] as &$privileges) {
        $privileges = array_values(array_unique($privileges));
    }
}
unset($role, $privileges);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Role Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;

            margin-left: 300px;
        }

        #tableContainer {
            max-height: 500px;
            overflow-y: auto;
        }

        /* Button Styles */
        .edit-btn,
        .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.375rem;
            border-radius: 9999px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
        }

        .edit-btn {
            color: #4f46e5;
        }

        .delete-btn {
            color: #ef4444;
        }

        .edit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .delete-btn:hover {
            background-color: #fee2e2;
        }

        .edit-btn:active {
            transform: translateY(0);
        }

        /* Modal backdrop fix */
        .modal-backdrop {
            opacity: 0.5 !important; /* Force consistent opacity with !important */
            background-color: #000;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            width: 100vw;
            height: 100vh;
            transition: none !important; /* Disable any transitions */
            animation: none !important; /* Disable any animations */
        }
        
        /* Disable fade transitions for modals and backdrops */
        .modal-backdrop.fade,
        .modal.fade,
        .modal.fade .modal-dialog {
            transition: none !important;
            animation: none !important;
        }
        
        /* Ensure consistent backdrop appearance in all states */
        .modal-backdrop.show {
            opacity: 0.5 !important;
        }
        
        body.modal-open {
            overflow: hidden;
            padding-right: 0 !important;
        }
        
        /* Ensure modals are on top of backdrop */
        .modal {
            z-index: 1050;
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <div class="main-content container-fluid">
            <div class="row">
                <main class="col-md-12 px-md-4 py-4">
                    <h2 class="mb-4">Role Management</h2>

                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                            <span><i class="bi bi-list-ul"></i> List of Roles</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($roles)): ?>
                                <div class="filter-container">
                                    <div class="d-flex justify-content-start mb-3 gap-2 align-items-center">
                                        <?php if ($canCreate): ?>
                                            <button type="button" class="btn btn-success btn-dark" data-bs-toggle="modal"
                                                data-bs-target="#addRoleModal">
                                                <i class="bi bi-plus-lg"></i> Create New Role
                                            </button>
                                        <?php endif; ?>

                                        <div class="input-group w-auto" id="livesearch">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" placeholder="Search..." id="eqSearch">
                                        </div>

                                        <button type="button" id="clearFilters" class="btn btn-secondary shadow-sm">
                                            <i class="bi bi-x-circle"></i> Clear
                                        </button>
                                    </div>
                                </div>

                                <!-- Table -->
                                <div class="table-responsive" id="table">
                                    <table id="rolesTable" class="table table-striped table-hover align-middle">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width: 25px;">ID</th>
                                                <th style="width: 250px;">Role Name</th>
                                                <th>Modules & Privileges</th>
                                                <th style="width: 250px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="auditTable">
                                            <?php foreach ($roles as $roleID => $role): ?>
                                                <tr data-role-id="<?php echo $roleID; ?>">
                                                    <td><?php echo htmlspecialchars($roleID); ?></td>
                                                    <td class="role-name"><?php echo htmlspecialchars($role['Role_Name']); ?></td>
                                                    <td class="privilege-list">
                                                        <?php foreach ($role['Modules'] as $moduleName => $privileges): ?>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($moduleName); ?></strong>:
                                                                <?php echo !empty($privileges)
                                                                    ? implode(', ', array_map('htmlspecialchars', $privileges))
                                                                    : '<em>No privileges</em>'; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($canModify): ?>
                                                            <button type="button" class="edit-btn edit-role-btn"
                                                                data-role-id="<?php echo $roleID; ?>" data-bs-toggle="modal"
                                                                data-bs-target="#editRoleModal">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($canRemove): ?>
                                                            <button type="button" class="delete-btn delete-role-btn"
                                                                data-role-id="<?php echo $roleID; ?>"
                                                                data-role-name="<?php echo htmlspecialchars($role['Role_Name']); ?>"
                                                                data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <!-- Pagination -->
                                    <div class="container-fluid">
                                        <div class="row align-items-center g-3">
                                            <div class="col-12 col-sm-auto">
                                                <div class="text-muted">
                                                    Showing <span id="currentPage">1</span> to <span id="rowsPerPage"><?php echo min(10, count($roles)); ?></span> of <span
                                                        id="totalRows"><?php echo count($roles); ?></span> entries
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-auto ms-sm-auto">
                                                <div class="d-flex align-items-center gap-2">
                                                    <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                                        <i class="bi bi-chevron-left"></i> Previous
                                                    </button>
                                                    <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                                        <option value="5">5</option>
                                                        <option value="10" selected>10</option>
                                                        <option value="20">20</option>
                                                        <option value="50">50</option>
                                                    </select>
                                                    <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                                        Next <i class="bi bi-chevron-right"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <ul class="pagination justify-content-center" id="pagination"></ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="mb-0">No roles found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

    <!-- Modals (unchanged) -->
    <div class="modal" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true" style="transition: none !important;">
        <div class="modal-dialog modal-lg modal-dialog-centered" style="transition: none !important;">
            <div class="modal-content">
                <div id="editRoleContent">Loading...</div>
            </div>
        </div>
    </div>

    <div class="modal" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
        aria-hidden="true" style="transition: none !important;">
        <div class="modal-dialog modal-dialog-centered" style="transition: none !important;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the role "<span id="roleNamePlaceholder"></span>"?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a id="confirmDeleteButton" href="#" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true" style="transition: none !important;">
        <div class="modal-dialog modal-dialog-centered" style="transition: none !important;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="addRoleContent">Loading...</div>
            </div>
        </div>
    </div>

    <script>
        // Pass RBAC privileges to JavaScript
        const userPrivileges = {
            canCreate: <?php echo json_encode($canCreate); ?>,
            canModify: <?php echo json_encode($canModify); ?>,
            canRemove: <?php echo json_encode($canRemove); ?>,
            canUndo: <?php echo json_encode($canUndo); ?>,
            canRedo: <?php echo json_encode($canRedo); ?>,
            canViewArchive: <?php echo json_encode($canViewArchive); ?>
        };

        // Function to refresh the roles table without page reload
        function refreshRolesTable() {
            // Store current scroll position
            const scrollPosition = window.scrollY || document.documentElement.scrollTop;

            // Don't hide modals or remove backdrops here - just refresh the table data
            $.ajax({
                url: location.href,
                type: 'GET',
                success: function(response) {
                    // Extract just the table HTML from the response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(response, 'text/html');
                    const newTable = doc.querySelector('#rolesTable');

                    if (newTable) {
                        // Replace the current table with the new one
                        $('#rolesTable').replaceWith(newTable);

                        // Reset the global arrays for pagination
                        window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
                        window.filteredRows = window.allRows;
                        window.currentPage = 1;

                        // Reinitialize pagination
                        if (typeof updatePagination === 'function') {
                            updatePagination();
                            setTimeout(forcePaginationCheck, 100);
                        }

                        // Restore scroll position after everything is loaded
                        setTimeout(function() {
                            window.scrollTo(0, scrollPosition);
                        }, 150);
                    } else {
                        console.error('Could not find table in response');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error refreshing table:', error);
                    showToast('Failed to refresh data. Please reload the page.', 'error', 5000);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Clear any previously defined pagination variables
            window.paginationConfig = null;
            
            // Initialize our own pagination variables
            window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
            window.filteredRows = window.allRows;
            window.currentPage = 1;
            
            console.log(`Initialized pagination with ${window.allRows.length} total rows`);
            
            // Basic error checks for required elements
            if (!document.getElementById('auditTable')) {
                console.error("Could not find audit table");
            }
            
            if (!document.getElementById('prevPage')) {
                console.error("Could not find previous page button");
            }
            
            if (!document.getElementById('nextPage')) {
                console.error("Could not find next page button");
            }

            // Initialize all modals with proper backdrop
            $('.modal').each(function() {
                var modalId = $(this).attr('id');
                $('#' + modalId).modal({
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
            });

            // Function to ensure modal backdrop is present
            function ensureModalBackdrop() {
                console.log('Ensuring modal backdrop...');
                
                // Check if any modal is currently visible
                const visibleModals = $('.modal.show');
                const hasVisibleModal = visibleModals.length > 0;
                
                // Check if backdrop exists
                const existingBackdrop = $('.modal-backdrop');
                const hasBackdrop = existingBackdrop.length > 0;
                
                console.log('Visible modals:', visibleModals.length, 'Existing backdrops:', existingBackdrop.length);
                
                // If there's a visible modal but no backdrop, create one
                if (hasVisibleModal && !hasBackdrop) {
                    console.log('Creating new backdrop for visible modal');
                    
                    // Create and append the backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop show';
                    backdrop.style.opacity = '0.5';
                    backdrop.style.transition = 'none !important';
                    backdrop.style.animation = 'none !important';
                    document.body.appendChild(backdrop);
                    
                    // Ensure body has modal-open class
                    $('body').addClass('modal-open');
                    
                    return true;
                } 
                // If there's a backdrop but no visible modal, remove it
                else if (!hasVisibleModal && hasBackdrop) {
                    console.log('No visible modals, removing backdrop');
                    
                    // Check if any modal is in the process of being shown
                    const modalBeingShown = $('.modal').filter(function() {
                        return $(this).css('display') !== 'none' && !$(this).hasClass('show');
                    });
                    
                    if (modalBeingShown.length === 0) {
                        // Safe to remove backdrop
                        existingBackdrop.remove();
                        $('body').removeClass('modal-open');
                        $('body').css({
                            'overflow': '',
                            'padding-right': ''
                        });
                    } else {
                        console.log('Modal in transition, keeping backdrop');
                    }
                    
                    return false;
                }
                // If there's a visible modal and a backdrop, ensure correct styling
                else if (hasVisibleModal && hasBackdrop) {
                    console.log('Ensuring backdrop styling');
                    
                    // Ensure correct styling
                    existingBackdrop.addClass('show');
                    existingBackdrop.css({
                        'opacity': '0.5',
                        'transition': 'none !important',
                        'animation': 'none !important'
                    });
                    
                    // Ensure body has modal-open class
                    $('body').addClass('modal-open');
                    
                    return true;
                }
                
                return hasBackdrop;
            }
            
            // Function to clean up modal elements
            function cleanupModalElements() {
                // Hide any visible modals
                $('.modal').modal('hide');
                
                // Remove all modal backdrops
                $('.modal-backdrop').remove();
                
                // Remove modal open class and inline styles from body
                $('body').removeClass('modal-open');
                $('body').css('overflow', '');
                $('body').css('padding-right', '');
            }

            // Remove any custom pagination functions that might interfere with pagination.js
            // and replace with a compatible function
            function updatePaginationControls(visibleRows) {
                // This triggers the existing pagination.js updatePagination function
                if (typeof updatePagination === 'function') {
                    updatePagination();
                }
            }

            // Ensure scrolling is properly restored when any modal is closed
            $('.modal').on('hidden.bs.modal', function(e) {
                console.log('Modal hidden event triggered');
                
                // Only remove backdrop if there are no other visible modals
                if ($('.modal.show').length === 0) {
                    console.log('No visible modals, removing backdrop');
                    // Remove modal backdrop
                    $('.modal-backdrop').remove();
                    // Remove modal-open class and reset body styles
                    $('body').removeClass('modal-open');
                    $('body').css({
                        'overflow': '',
                        'padding-right': ''
                    });
                } else {
                    console.log('Other modals still visible, keeping backdrop');
                    // Ensure backdrop exists for other visible modals
                    ensureModalBackdrop();
                }
            });

            // Ensure modals are properly cleaned up when they're opened
            $('.modal').on('show.bs.modal', function(e) {
                console.log('Modal show event triggered for', this.id);
                
                // Don't remove existing backdrops if other modals are showing
                if ($('.modal.show').length === 0 && $('.modal-backdrop').length > 1) {
                    // Only remove excess backdrops
                    $('.modal-backdrop').not(':first').remove();
                }
                
                // Always ensure this modal has a backdrop
                ensureModalBackdrop();
            });
            
            // Add additional handler for backdrop clicks
            $(document).on('click', '.modal-backdrop', function(e) {
                console.log('Backdrop clicked');
                // Ensure proper cleanup when backdrop is clicked
                if ($('.modal.show').length === 0) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                    $('body').css({
                        'overflow': '',
                        'padding-right': ''
                    });
                }
            });

            // Special handler for edit modal to ensure backdrop
            $('#editRoleModal').on('show.bs.modal', function() {
                ensureModalBackdrop();
            });

            // Check if Select2 is available
            if ($.fn.select2) {
                // Initialize Select2 for better dropdown experience
                $('#moduleFilter').select2({
                    placeholder: 'Select Module...',
                    allowClear: true,
                    width: '100%'
                });

                $('#privilegeFilter').select2({
                    placeholder: 'Select Privileges...',
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false
                });
            }

            // Filter function
            function filterTable() {
                const roleNameFilter = $('#roleNameFilter').val().toLowerCase();
                const moduleFilter = $('#moduleFilter').val();
                const privilegeFilters = $('#privilegeFilter').val() || [];

                // Remove any "no results" message first
                $('#no-results-row').remove();

                // Create a filtered array of rows that we'll use for pagination
                window.filteredRows = window.allRows.filter(row => {
                    const $row = $(row);
                    const roleName = $row.find('.role-name').text().toLowerCase();
                    const privilegeList = $row.find('.privilege-list');
                    const privilegeText = privilegeList.text().toLowerCase();

                    let showRow = true;

                    // Apply role name filter
                    if (roleNameFilter && !roleName.includes(roleNameFilter)) {
                        showRow = false;
                    }

                    // Module and privilege combination filtering
                    if (moduleFilter) {
                        // Find the specific module section
                        let moduleFound = false;
                        let moduleHasSelectedPrivileges = true;

                        privilegeList.find('div').each(function() {
                            const moduleSection = $(this).text().toLowerCase();
                            if (moduleSection.includes(moduleFilter.toLowerCase())) {
                                moduleFound = true;

                                // If privileges are selected, check if this module actually has them
                                if (privilegeFilters.length > 0) {
                                    // Check if module has "No privileges"
                                    if (moduleSection.includes("no privileges")) {
                                        moduleHasSelectedPrivileges = false;
                                        return false; // Break the each loop
                                    }

                                    // Check if module has ALL selected privileges
                                    const hasAllPrivileges = privilegeFilters.every(privilege =>
                                        moduleSection.includes(privilege.toLowerCase())
                                    );

                                    if (!hasAllPrivileges) {
                                        moduleHasSelectedPrivileges = false;
                                        return false; // Break the each loop
                                    }
                                }

                                return false; // Break the each loop once we found the module
                            }
                        });

                        // Don't show if module wasn't found or doesn't have the selected privileges
                        if (!moduleFound || !moduleHasSelectedPrivileges) {
                            showRow = false;
                        }
                    }
                    // Only privilege filtering (no module selected)
                    else if (privilegeFilters.length > 0) {
                        const hasAllPrivileges = privilegeFilters.every(privilege =>
                            privilegeText.includes(privilege.toLowerCase())
                        );
                        if (!hasAllPrivileges) {
                            showRow = false;
                        }
                    }

                    return showRow;
                });

                // Show "no results" message if no matches
                if (window.filteredRows.length === 0) {
                    $('#auditTable').append(
                        '<tr id="no-results-row"><td colspan="4" class="text-center py-3">' +
                        '<div class="alert alert-info mb-0">' +
                        '<i class="bi bi-info-circle me-2"></i>No matching roles found. Try adjusting your filters.' +
                        '</div></td></tr>'
                    );
                }

                // Reset pagination to first page and update with filtered rows
                window.currentPage = 1;
                updatePagination();
            }

            // Add event listeners for filters
            $('#roleNameFilter').on('input', filterTable);
            $('#moduleFilter').on('change', filterTable);
            $('#privilegeFilter').on('change', filterTable);

            // Add clear filters functionality
            $('#clearFilters').on('click', function() {
                // Clear search input
                $('#eqSearch').val('');
                
                // Reset table to initial state
                window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
                window.filteredRows = window.allRows;
                
                // Reset to first page
                window.currentPage = 1;
                
                // Update pagination
                updatePagination();
                
                // Force pagination check
                forcePaginationCheck();
                
                // Remove any "no results" message
                $('#no-results-row').remove();
            });

            // Live search functionality
            $('#eqSearch').on('keyup', function() {
                const searchText = $(this).val().toLowerCase();
                
                // Get all rows if not already stored
                if (!window.allRows) {
                    window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
                }

                if (searchText.length > 0) {
                    window.filteredRows = window.allRows.filter(row => {
                        const rowText = $(row).text().toLowerCase();
                        return rowText.includes(searchText);
                    });
                } else {
                    window.filteredRows = window.allRows;
                }

                // Remove any "no results" message
                $('#no-results-row').remove();

                // Show "no results" message if no matches
                if (window.filteredRows.length === 0) {
                    $('#auditTable').append(
                        '<tr id="no-results-row"><td colspan="4" class="text-center py-3">' +
                        '<div class="alert alert-info mb-0">' +
                        '<i class="bi bi-info-circle me-2"></i>No matching roles found. Try adjusting your search.' +
                        '</div></td></tr>'
                    );
                }

                // Reset to first page and update
                window.currentPage = 1;
                updatePagination();
                forcePaginationCheck();
            });

            // Initialize the pagination system correctly
            window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
            window.filteredRows = window.allRows; // Initially all rows are visible

            // Add this override for pagination.js to work with our filtered rows
            window.updatePagination = function() {
                console.log("Custom updatePagination called. Current page:", window.currentPage);
                
                // Make sure we have the correct rows to paginate
                window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
                
                // If filtered rows is empty or not defined, use all rows
                if (!window.filteredRows || window.filteredRows.length === 0) {
                    window.filteredRows = window.allRows;
                }
                
                // Hide all rows first
                window.allRows.forEach(row => {
                    row.style.display = 'none';
                });
                
                // Calculate which rows to show based on current page and rows per page
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value);
                const startIndex = (window.currentPage - 1) * rowsPerPage;
                const totalRows = window.filteredRows.length;
                const endIndex = Math.min(startIndex + rowsPerPage, totalRows);
                
                console.log(`Displaying rows ${startIndex+1}-${endIndex} of ${totalRows}`);
                
                // Show only the rows for the current page
                for (let i = startIndex; i < endIndex; i++) {
                    if (window.filteredRows[i]) {
                        window.filteredRows[i].style.display = '';
                    }
                }
                
                // Update pagination info display
                document.getElementById('currentPage').textContent = totalRows === 0 ? 0 : (startIndex + 1);
                document.getElementById('rowsPerPage').textContent = Math.min(endIndex, totalRows);
                document.getElementById('totalRows').textContent = totalRows;
                
                // Update pagination controls
                const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
                renderPaginationControls(totalPages);
                
                // Enable/disable prev/next buttons
                const prevBtn = document.getElementById('prevPage');
                const nextBtn = document.getElementById('nextPage');
                
                if (prevBtn) {
                    prevBtn.disabled = window.currentPage <= 1;
                    prevBtn.style.display = window.currentPage <= 1 ? 'none' : '';
                }
                
                if (nextBtn) {
                    nextBtn.disabled = window.currentPage >= totalPages;
                    nextBtn.style.display = window.currentPage >= totalPages ? 'none' : '';
                }
                
                // Run the additional check for pagination visibility
                forcePaginationCheck();
            };

            // Override pagination rendering to use ellipsis style
            window.renderPaginationControls = function(totalPages) {
                const paginationContainer = document.getElementById('pagination');
                if (!paginationContainer) return;

                paginationContainer.innerHTML = '';

                if (totalPages <= 1) return;

                // Always show first page
                addPaginationItem(paginationContainer, 1, window.currentPage === 1);

                // Show ellipses and a window of pages around current page
                const maxVisiblePages = 5; // Adjust as needed
                const halfWindow = Math.floor(maxVisiblePages / 2);

                let startPage = Math.max(2, window.currentPage - halfWindow);
                let endPage = Math.min(totalPages - 1, window.currentPage + halfWindow);

                // Adjust for edge cases
                if (window.currentPage <= halfWindow + 1) {
                    // Near start, show more pages after current
                    endPage = Math.min(totalPages - 1, maxVisiblePages);
                } else if (window.currentPage >= totalPages - halfWindow) {
                    // Near end, show more pages before current
                    startPage = Math.max(2, totalPages - maxVisiblePages);
                }

                // Show ellipsis after first page if needed
                if (startPage > 2) {
                    addPaginationItem(paginationContainer, '...');
                }

                // Show pages in the window
                for (let i = startPage; i <= endPage; i++) {
                    addPaginationItem(paginationContainer, i, window.currentPage === i);
                }

                // Show ellipsis before last page if needed
                if (endPage < totalPages - 1) {
                    addPaginationItem(paginationContainer, '...');
                }

                // Always show last page
                if (totalPages > 1) {
                    addPaginationItem(paginationContainer, totalPages, window.currentPage === totalPages);
                }
            };

            // Helper function to add pagination items
            window.addPaginationItem = function(container, page, isActive = false) {
                const li = document.createElement('li');
                li.className = 'page-item' + (isActive ? ' active' : '');

                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = page;

                if (page !== '...') {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        window.currentPage = parseInt(page);
                        updatePagination();
                    });
                } else {
                    li.classList.add('disabled');
                }

                li.appendChild(a);
                container.appendChild(li);
            };

            // Function to refresh row data after any table modifications
            function refreshTableRowData() {
                // Update the allRows and filteredRows arrays
                window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
                // Apply any active filters
                filterTable();
                // Reset to page 1
                window.currentPage = 1;
                // Update pagination
                updatePagination();
            }

            // Setup pagination controls
            document.getElementById('prevPage').addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Previous button clicked. Current page before:", window.currentPage);
                if (window.currentPage > 1) {
                    window.currentPage--;
                    console.log("Current page after:", window.currentPage);
                    updatePagination(); // Update display
                }
            });

            document.getElementById('nextPage').addEventListener('click', function(e) {
                e.preventDefault();
                console.log("Next button clicked. Current page before:", window.currentPage);
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value);
                const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);

                if (window.currentPage < totalPages) {
                    window.currentPage++;
                    console.log("Current page after:", window.currentPage);
                    updatePagination(); // Update display
                }
            });

            document.getElementById('rowsPerPageSelect').addEventListener('change', function() {
                console.log("Rows per page changed");
                window.currentPage = 1;
                updatePagination(); // Update display
            });

            // **1. Load edit role modal content via AJAX**
            $(document).on('click', '.edit-role-btn', function() {
                if (!userPrivileges.canModify) return;

                var roleID = $(this).data('role-id');
                $('#editRoleContent').html("Loading...");
                
                // Force backdrop to be visible
                setTimeout(function() {
                    ensureModalBackdrop();
                }, 50);
                
                $.ajax({
                    url: 'edit_roles.php',
                    type: 'GET',
                    data: {
                        id: roleID
                    },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        $('#editRoleContent').html(response);
                        $('#roleID').val(roleID);
                        
                        // Force backdrop to be visible again after content is loaded
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 50);
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        $('#editRoleContent').html('<p class="text-danger">Error loading role data. Please try again.</p>');
                    }
                });
            });

            // **2. Handle delete role modal**
            $('#confirmDeleteModal').on('show.bs.modal', function(event) {
                if (!userPrivileges.canRemove) {
                    event.preventDefault();
                    return false;
                }

                // Ensure this modal has a backdrop
                ensureModalBackdrop();

                var button = $(event.relatedTarget);
                var roleID = button.data('role-id');
                var roleName = button.data('role-name');
                $('#roleNamePlaceholder').text(roleName);
                $('#confirmDeleteButton').data('role-id', roleID);
            });

            // **3. Confirm delete role via AJAX**
            $(document).on('click', '#confirmDeleteButton', function(e) {
                if (!userPrivileges.canRemove) return;

                e.preventDefault();
                $(this).blur();
                var roleID = $(this).data('role-id');
                $.ajax({
                    type: 'POST',
                    url: 'delete_role.php',
                    data: {
                        id: roleID
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Close modal first to avoid UI issues
                            $('#confirmDeleteModal').modal('hide');
                            
                            // Only cleanup this specific modal without removing all backdrops
                            setTimeout(function() {
                                // Only remove backdrop if there are no other visible modals
                                if ($('.modal.show').length === 0) {
                                    $('.modal-backdrop').remove();
                                    $('body').removeClass('modal-open');
                                    $('body').css('overflow', '');
                                    $('body').css('padding-right', '');
                                }
                                
                                // Refresh the table without reloading the whole page
                                refreshRolesTable();
                                showToast(response.message, 'success', 5000);
                            }, 300);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error deleting role: ' + error, 'error', 5000);
                    }
                });
            });

            // **4. Load add role modal content**
            $('#addRoleModal').on('show.bs.modal', function(event) {
                if (!userPrivileges.canCreate) {
                    event.preventDefault();
                    return false;
                }

                // Ensure this modal has a backdrop
                ensureModalBackdrop();

                // Reset form if it exists
                if ($('#addRoleForm').length) {
                    $('#addRoleForm')[0].reset();
                    $('#addRoleForm').find('button[type="submit"]').prop('disabled', false);
                }

                $('#addRoleContent').html("Loading...");
                $.ajax({
                    url: 'add_role.php',
                    type: 'GET',
                    success: function(response) {
                        $('#addRoleContent').html(response);
                        
                        // Double-check backdrop after content is loaded
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 50);
                    },
                    error: function() {
                        $('#addRoleContent').html('<p class="text-danger">Error loading form.</p>');
                    }
                });
            });
            
            // Handle add role modal hidden event
            $('#addRoleModal').on('hidden.bs.modal', function() {
                // Reset form if it exists
                if ($('#addRoleForm').length) {
                    $('#addRoleForm')[0].reset();
                    $('#addRoleForm').find('button[type="submit"]').prop('disabled', false);
                }
                
                // Ensure proper cleanup
                setTimeout(function() {
                    if ($('.modal.show').length === 0) {
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        $('body').css({
                            'overflow': '',
                            'padding-right': ''
                        });
                    }
                }, 100);
            });

            // **7. Undo button via AJAX**
            $(document).on('click', '#undoButton', function() {
                if (!userPrivileges.canUndo) return;

                $.ajax({
                    url: 'undo.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Refresh the table without reloading the whole page
                            refreshRolesTable();
                            showToast(response.message, 'success', 5000);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error processing undo request: ' + error, 'error', 5000);
                    }
                });
            });

            // **8. Redo button via AJAX**
            $(document).on('click', '#redoButton', function() {
                if (!userPrivileges.canRedo) return;

                $.ajax({
                    url: 'redo.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Refresh the table without reloading the whole page
                            refreshRolesTable();
                            showToast(response.message, 'success', 5000);
                        } else {
                            showToast(response.message || 'An error occurred', 'error', 5000);
                        }
                    },
                    error: function(xhr, status, error) {
                        showToast('Error processing redo request: ' + error, 'error', 5000);
                    }
                });
            });

            // Function to force hide pagination buttons when not needed
            function forcePaginationCheck() {
                const totalRows = window.filteredRows ? window.filteredRows.length : 0;
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value);
                const prevBtn = document.getElementById('prevPage');
                const nextBtn = document.getElementById('nextPage');
                const paginationEl = document.getElementById('pagination');

                // Hide pagination completely if all rows fit on one page
                if (totalRows <= rowsPerPage) {
                    if (prevBtn) prevBtn.style.cssText = 'display: none !important';
                    if (nextBtn) nextBtn.style.cssText = 'display: none !important';
                    if (paginationEl) paginationEl.style.cssText = 'display: none !important';
                } else {
                    // Show pagination but conditionally hide prev/next buttons
                    if (paginationEl) paginationEl.style.cssText = '';

                    if (prevBtn) {
                        if (window.currentPage <= 1) {
                            prevBtn.style.cssText = 'display: none !important';
                        } else {
                            prevBtn.style.cssText = '';
                        }
                    }

                    if (nextBtn) {
                        const totalPages = Math.ceil(totalRows / rowsPerPage);
                        if (window.currentPage >= totalPages) {
                            nextBtn.style.cssText = 'display: none !important';
                        } else {
                            nextBtn.style.cssText = '';
                        }
                    }
                }
            }

            // Run immediately and after any filtering
            forcePaginationCheck();
            $('#roleNameFilter, #moduleFilter, #privilegeFilter').on('input change', function() {
                setTimeout(forcePaginationCheck, 100);
            });

            // Initialize pagination
            window.currentPage = 1;
            
            // Make sure we have the correct updatePagination function
            console.log("Initializing pagination system");
            
            // Call the updatePagination function to show initial set of rows
            updatePagination();
            
            // Force an initial check of pagination controls
            setTimeout(forcePaginationCheck, 100);

            // Fix for modal backdrops - ensure they're properly initialized
            $('#editRoleModal, #confirmDeleteModal, #addRoleModal').each(function() {
                // Remove any existing modal backdrop configuration
                $(this).data('bs.modal', null);
                
                // Initialize with explicit backdrop option
                var modalOptions = {
                    backdrop: true,  // Set to 'static' to prevent closing when clicking outside
                    keyboard: true,
                    focus: true
                };
                
                try {
                    // Initialize the modal with Bootstrap 5 options
                    var bsModal = new bootstrap.Modal(this, modalOptions);
                    
                    // Store the modal instance for future reference
                    $(this).data('bs.modal', bsModal);
                } catch (e) {
                    console.error('Error initializing modal:', e);
                }
            });
            
            // Add global event listener for all modals to ensure backdrop
            $(document).on('shown.bs.modal', '.modal', function() {
                console.log('Modal fully shown, ensuring backdrop');
                ensureModalBackdrop();
            });
            
            // Add global event listener for when modal is about to be shown
            $(document).on('show.bs.modal', '.modal', function() {
                console.log('Modal about to show, ensuring backdrop');
                ensureModalBackdrop();
            });
            
            // Add event listener for when backdrop is clicked
            $(document).on('click', function(e) {
                // Check if the click was on the backdrop
                if ($(e.target).hasClass('modal-backdrop')) {
                    console.log('Backdrop clicked directly');
                    
                    // Force a small delay to let Bootstrap process the modal hiding
                    setTimeout(function() {
                        // If no modals are visible, remove backdrop
                        if ($('.modal.show').length === 0) {
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                            $('body').css({
                                'overflow': '',
                                'padding-right': ''
                            });
                        } else {
                            // Otherwise ensure backdrop exists
                            ensureModalBackdrop();
                        }
                    }, 100);
                }
            });
            
            // Disable Bootstrap's default fade animations for modals
            $(document).ready(function() {
                // Override Bootstrap's modal animation
                if ($.fn.modal && $.fn.modal.Constructor && $.fn.modal.Constructor.prototype) {
                    try {
                        // Save original method
                        const originalBackdrop = $.fn.modal.Constructor.prototype._backdrop;
                        
                        // Override the backdrop method
                        $.fn.modal.Constructor.prototype._backdrop = function(callback) {
                            console.log('Custom backdrop method called');
                            
                            // Call original method first
                            if (typeof originalBackdrop === 'function') {
                                originalBackdrop.call(this, callback);
                            }
                            
                            // Then ensure our custom backdrop styling
                            ensureModalBackdrop();
                            
                            // Call the callback if provided
                            if (typeof callback === 'function') {
                                callback();
                            }
                        };
                    } catch (e) {
                        console.error('Error overriding Bootstrap modal:', e);
                    }
                }
            });
            
            // Add a global click handler for modal triggers
            $(document).on('click', '[data-bs-toggle="modal"]', function() {
                console.log('Modal trigger clicked');
                
                // Force backdrop creation on next tick
                setTimeout(function() {
                    ensureModalBackdrop();
                }, 10);
            });

            // Add a MutationObserver to monitor when modals are added to the DOM
            $(document).ready(function() {
                // Create a MutationObserver to monitor for modal changes
                const modalObserver = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        // Check for added nodes
                        if (mutation.addedNodes.length) {
                            mutation.addedNodes.forEach(function(node) {
                                // Check if the added node is a modal or contains a modal
                                if (node.classList && node.classList.contains('modal')) {
                                    console.log('Modal added to DOM:', node.id);
                                    ensureModalBackdrop();
                                } else if (node.querySelectorAll) {
                                    const modals = node.querySelectorAll('.modal');
                                    if (modals.length) {
                                        console.log('Modal found inside added node');
                                        ensureModalBackdrop();
                                    }
                                }
                            });
                        }
                        
                        // Check for attribute changes on modals
                        if (mutation.type === 'attributes' && 
                            mutation.target.classList && 
                            mutation.target.classList.contains('modal')) {
                            if (mutation.attributeName === 'class' && 
                                mutation.target.classList.contains('show')) {
                                console.log('Modal class changed to show');
                                ensureModalBackdrop();
                            }
                        }
                    });
                });

                // Start observing the document body for modal-related changes
                modalObserver.observe(document.body, { 
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class']
                });
                
                // Also ensure backdrop when page is fully loaded
                setTimeout(function() {
                    if ($('.modal.show').length > 0) {
                        console.log('Modal found on page load');
                        ensureModalBackdrop();
                    }
                }, 500);
            });

            // Add global event listener for when modal is hidden
            $(document).on('hidden.bs.modal', '.modal', function() {
                console.log('Modal hidden, checking if backdrop should be removed');
                
                // Use setTimeout to allow all modal events to complete
                setTimeout(function() {
                    // Check if any modals are still visible
                    if ($('.modal.show').length === 0) {
                        console.log('No visible modals after hiding, removing backdrop');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        $('body').css({
                            'overflow': '',
                            'padding-right': ''
                        });
                    } else {
                        console.log('Other modals still visible, keeping backdrop');
                        ensureModalBackdrop();
                    }
                }, 100);
            });
            
            // Add global event listener for when modal is about to be hidden
            $(document).on('hide.bs.modal', '.modal', function() {
                console.log('Modal about to hide');
                
                // Store the number of visible modals before this one hides
                const visibleModalsCount = $('.modal.show').length;
                $(this).data('visibleModalsBeforeHide', visibleModalsCount);
                
                // If this is the last visible modal, mark it for potential backdrop removal
                if (visibleModalsCount === 1 && $(this).hasClass('show')) {
                    console.log('Last visible modal is being hidden');
                    $(this).data('lastVisibleModal', true);
                }
            });

            // Add direct event handler for backdrop clicks
            $(document).ready(function() {
                // Use event delegation for dynamically added backdrops
                $(document).on('mousedown touchstart', '.modal-backdrop', function(e) {
                    console.log('Direct backdrop click detected');
                    
                    // Get the currently visible modal(s)
                    const visibleModals = $('.modal.show');
                    
                    if (visibleModals.length > 0) {
                        console.log('Visible modals found when backdrop clicked:', visibleModals.length);
                        
                        // Mark that this was a direct backdrop click
                        $(document).data('backdropDirectClick', true);
                        
                        // Store timestamp of the click
                        $(document).data('backdropClickTime', new Date().getTime());
                    }
                });
                
                // Add a global document click handler to detect clicks outside modal content
                $(document).on('mousedown touchstart', function(e) {
                    // Check if click was outside any modal content
                    if ($(e.target).closest('.modal-content').length === 0 && 
                        $('.modal.show').length > 0 && 
                        !$(e.target).hasClass('modal-backdrop')) {
                        
                        console.log('Click outside modal content detected');
                        
                        // Force check for backdrop on next tick
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 100);
                    }
                });
                
                // Special handler for ESC key (which can also close modals)
                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape' && $('.modal.show').length > 0) {
                        console.log('ESC key pressed with modal open');
                        
                        // Force check for backdrop on next tick
                        setTimeout(function() {
                            ensureModalBackdrop();
                        }, 100);
                    }
                });
            });
        });
    </script>
</body>

</html>