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
    CASE 
        WHEN LOWER(m.module_name) = 'audit' THEN 
            COALESCE((
                SELECT GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ')
                FROM role_module_privileges rmp2
                JOIN privileges p ON p.id = rmp2.privilege_id
                WHERE rmp2.role_id = r.id
                  AND rmp2.module_id = m.id

            ), 'No privileges') 
        ELSE
            COALESCE((
                SELECT GROUP_CONCAT(p.priv_name ORDER BY p.priv_name SEPARATOR ', ')
                FROM role_module_privileges rmp2
                JOIN privileges p ON p.id = rmp2.privilege_id
                WHERE rmp2.role_id = r.id
                  AND rmp2.module_id = m.id
            ), 'No privileges')
    END AS Privileges
FROM roles r
CROSS JOIN modules m
WHERE r.is_disabled = 0
ORDER BY r.id, m.id;
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$roleData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group data by role and module (unchanged)
$roles = [];
foreach ($roleData as $row) {
    $roleID = $row['Role_ID'];
    if (!isset($roles[$roleID])) {
        $roles[$roleID] = [
            'Role_Name' => $row['Role_Name'],
            'Modules' => []
        ];
    }
    $moduleName = !empty($row['Module_Name']) ? $row['Module_Name'] : 'General';
    if (!isset($roles[$roleID]['Modules'][$moduleName])) {
        $roles[$roleID]['Modules'][$moduleName] = [];
    }
    if (!empty($row['Privileges'])) {
        $roles[$roleID]['Modules'][$moduleName][] = $row['Privileges'];
    }
}

foreach ($roles as $roleID => &$role) {
    foreach ($role['Modules'] as $moduleName => &$privileges) {
        $privileges = array_unique($privileges);
    }
}
unset($role);
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
    </style>
</head>

<body>

    <div class="wrapper">
        <div class="main-content container-fluid">
            <header class="main-header">
                <h1> Role Management</h1>
            </header>

            <!-- Add Filter Section -->
            <div class="row mb-3">
                
            <div class="d-flex justify-content-end mb-3">
                <?php if ($canCreate): ?>
                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="bi bi-plus-lg"></i> Create New Role
                    </button>
                <?php endif; ?>
            </div>
            
                <!-- <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="roleNameFilter" class="form-control" placeholder="Filter by role name...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="moduleFilter" class="form-select">
                        <option value="">All Modules</option>
                        <?php
                        $modules = array_unique(array_column($roleData, 'Module_Name'));
                        foreach ($modules as $module) {
                            echo "<option value='" . htmlspecialchars($module) . "'>" . htmlspecialchars($module) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="privilegeFilter" class="form-select" multiple>
                        <?php
                        $fixedPrivileges = ['Track', 'Create', 'Remove', 'Permanently Delete', 'Modify', 'View', 'Restore'];
                        foreach ($fixedPrivileges as $privilege) {
                            echo "<option value='" . htmlspecialchars($privilege) . "'>" . htmlspecialchars($privilege) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button id="clear-filters-btn" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle"></i> Clear Filters
                    </button>
                </div> -->
            </div>

            <!-- Table Filter Section -->
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
                        <?php if (!empty($roles)): ?>
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
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No roles found.</td>
                            </tr>
                        <?php endif; ?>
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
        </div>
    </div>

    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

    <!-- Modals (unchanged) -->
    <div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div id="editRoleContent">Loading...</div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
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

    <div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
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

            // Ensure modals are properly cleaned up
            $('.modal').modal('hide');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();

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

                            // Double check that modal classes are removed
                            $('body').removeClass('modal-open');
                            $('body').css('overflow', '');
                            $('body').css('padding-right', '');
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
            // Remove any custom pagination functions that might interfere with pagination.js
            // and replace with a compatible function
            function updatePaginationControls(visibleRows) {
                // This triggers the existing pagination.js updatePagination function
                if (typeof updatePagination === 'function') {
                    updatePagination();
                }
            }

            // Ensure scrolling is properly restored when any modal is closed
            $('.modal').on('hidden.bs.modal', function() {
                setTimeout(function() {
                    $('body').removeClass('modal-open');
                    $('body').css('overflow', '');
                    $('body').css('padding-right', '');
                    $('.modal-backdrop').remove();
                }, 100);
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

            // Clear filters button
            $('#clear-filters-btn').on('click', function() {
                $('#roleNameFilter').val('');

                // Handle Select2 dropdowns if available
                if ($.fn.select2) {
                    $('#moduleFilter').val('').trigger('change');
                    $('#privilegeFilter').val(null).trigger('change');
                } else {
                    $('#moduleFilter').val('');
                    $('#privilegeFilter').val([]);
                }

                // Reset to show all rows (use original allRows)
                window.filteredRows = window.allRows;
                window.currentPage = 1;
                updatePagination();

                // Remove any "no results" message
                $('#no-results-row').remove();
            });

            // Initialize the pagination system correctly
            window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));
            window.filteredRows = window.allRows; // Initially all rows are visible

            // Add this override for pagination.js to work with our filtered rows
            window.updatePagination = function() {
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value);
                const totalRows = window.filteredRows.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);

                // Update info display
                document.getElementById('currentPage').textContent = window.currentPage;
                document.getElementById('rowsPerPage').textContent = Math.min(rowsPerPage, totalRows);
                document.getElementById('totalRows').textContent = totalRows;

                // Hide all rows first
                window.allRows.forEach(row => {
                    row.style.display = 'none';
                });

                // Show only the rows for the current page
                const startIndex = (window.currentPage - 1) * rowsPerPage;
                const endIndex = Math.min(startIndex + rowsPerPage, totalRows);

                for (let i = startIndex; i < endIndex; i++) {
                    if (window.filteredRows[i]) {
                        window.filteredRows[i].style.display = '';
                    }
                }

                // Update pagination controls
                renderPaginationControls(totalPages);

                // Enable/disable prev/next buttons
                document.getElementById('prevPage').disabled = window.currentPage <= 1;
                document.getElementById('nextPage').disabled = window.currentPage >= totalPages;
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
            document.getElementById('prevPage').addEventListener('click', function() {
                if (window.currentPage > 1) {
                    window.currentPage--;
                    updatePagination();
                }
            });

            document.getElementById('nextPage').addEventListener('click', function() {
                const rowsPerPage = parseInt(document.getElementById('rowsPerPageSelect').value);
                const totalPages = Math.ceil(window.filteredRows.length / rowsPerPage);

                if (window.currentPage < totalPages) {
                    window.currentPage++;
                    updatePagination();
                }
            });

            document.getElementById('rowsPerPageSelect').addEventListener('change', function() {
                window.currentPage = 1;
                updatePagination();
            });

            // **1. Load edit role modal content via AJAX**
            $(document).on('click', '.edit-role-btn', function() {
                if (!userPrivileges.canModify) return;

                var roleID = $(this).data('role-id');
                $('#editRoleContent').html("Loading...");
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

                        // Form is loaded in modal content, no need for parent window access
                        // The script in edit_roles.php will handle capturing the original state
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
                            $('body').removeClass('modal-open');
                            $('body').css('overflow', '');
                            $('body').css('padding-right', '');
                            $('.modal-backdrop').remove();

                            // Refresh the table without reloading the whole page
                            refreshRolesTable();
                            showToast(response.message, 'success', 5000);
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

                $('#addRoleContent').html("Loading...");
                $.ajax({
                    url: 'add_role.php',
                    type: 'GET',
                    success: function(response) {
                        $('#addRoleContent').html(response);
                    },
                    error: function() {
                        $('#addRoleContent').html('<p class="text-danger">Error loading form.</p>');
                    }
                });
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

            // Add forcePaginationCheck to updatePagination
            const originalUpdatePagination = window.updatePagination;
            window.updatePagination = function() {
                // Get all rows again in case the DOM was updated
                window.allRows = Array.from(document.querySelectorAll('#auditTable tr'));

                // If filtered rows is empty or not defined, use all rows
                if (!window.filteredRows || window.filteredRows.length === 0) {
                    window.filteredRows = window.allRows;
                }

                originalUpdatePagination();
                forcePaginationCheck();
            };

            // Run immediately and after any filtering
            forcePaginationCheck();
            $('#roleNameFilter, #moduleFilter, #privilegeFilter').on('input change', function() {
                setTimeout(forcePaginationCheck, 100);
            });

            // Initialize current page
            window.currentPage = 1;
            updatePagination();
        });

        function updatePagination() {
            console.log('pagination.js: updatePagination called. Current Page:', paginationConfig.currentPage);

            // 1) Grab the tbody where rows get injected
            const tbody = document.getElementById(paginationConfig.tableId);
            if (!tbody) {
                console.error(`Could not find tbody with ID ${paginationConfig.tableId}`);
                return;
            }
            tbody.innerHTML = ''; // clear out any existing rows

            // 2) Compute slicing indexes
            const rowsToPaginate = window.filteredRows || [];
            const totalRows = rowsToPaginate.length;
            const rowsPerPage = parseInt(
                document.getElementById(paginationConfig.rowsPerPageSelectId)?.value || '10',
                10
            );
            const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
            const start = (paginationConfig.currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            // 3) Render rows or "no results"
            if (totalRows === 0) {
                const noResultsRow = document.createElement('tr');
                noResultsRow.innerHTML = `
      <td colspan="10">
        <div class="empty-state text-center py-4">
          <i class="fas fa-search fa-3x mb-3"></i>
          <h4>No matching records found</h4>
          <p class="text-muted">Try adjusting your search or filter criteria.</p>
        </div>
      </td>
    `;
                tbody.appendChild(noResultsRow);
            } else {
                rowsToPaginate.slice(start, end).forEach(row => {
                    tbody.appendChild(row.cloneNode(true));
                });
            }

            // 4) Update "Showing X to Y of Z" text
            const currentPageEl = document.getElementById(paginationConfig.currentPageId);
            const rowsPerPageEl = document.getElementById(paginationConfig.rowsPerPageId);
            const totalRowsEl = document.getElementById(paginationConfig.totalRowsId);

            if (currentPageEl) currentPageEl.textContent = totalRows === 0 ? 0 : (start + 1);
            if (rowsPerPageEl) rowsPerPageEl.textContent = Math.min(end, totalRows);
            if (totalRowsEl) totalRowsEl.textContent = totalRows;

            // 5) Enable/disable Prev & Next
            const prevPageEl = document.getElementById(paginationConfig.prevPageId);
            const nextPageEl = document.getElementById(paginationConfig.nextPageId);
            if (prevPageEl) prevPageEl.disabled = (paginationConfig.currentPage <= 1);
            if (nextPageEl) nextPageEl.disabled = (paginationConfig.currentPage >= totalPages);

            // 6) **Show/Hide** Prev & Next buttons
            if (prevPageEl) {
                prevPageEl.style.display = (paginationConfig.currentPage > 1) ? '' : 'none';
            }
            if (nextPageEl) {
                nextPageEl.style.display = (paginationConfig.currentPage < totalPages) ? '' : 'none';
            }

            // 7) Hide the page-number links entirely when there's only one page
            const paginationContainer = document.getElementById(paginationConfig.paginationId);
            if (paginationContainer) {
                paginationContainer.style.display = (totalPages > 1) ? '' : 'none';
            }

            // 8) Re-render the numbered page links
            renderPaginationControls(totalPages);
        }
    </script>
</body>

</html>